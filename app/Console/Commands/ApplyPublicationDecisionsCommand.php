<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PagePublicationStatus;
use App\Domain\Content\PublicationDecision;
use App\Models\ContentPage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Verilmiş yayın kararlarını UYGULAR — `docs/144`.
 *
 * ── Bu komut `site:sync-content-status`un devamı DEĞİLDİR ────────────────
 *
 * O komutun tavanı `content_draft`tır ve bu pakette YÜKSELTİLMEDİ. Cümlesi
 * de yerinde duruyor: *"Bir betiğin atlayabildiği kapı, kapı değildir."*
 *
 * Aradaki fark, kararın nereden geldiğidir. `sync-content-status` bir ÖLÇÜM
 * yapar ("bu sayfanın metni yazılmış mı?") ve ölçümden bir durum türetir;
 * hiçbir insan devrede değildir, bu yüzden tavanı vardır. Bu komut ise bir
 * ölçüm yapmaz — kod incelemesinden geçmiş, sayfaları ADIYLA sayan, her
 * satırında kararı vereni ve sebebini taşıyan bir dosyayı okur ve o kararı
 * uygular. Kapıdan geçen şey bir betik değil, bir insandır.
 *
 * Toptan bir "her şeyi yayınla" yolu YOKTUR ve eklenemez: komut yalnız
 * kararlar dosyasında adı geçen satırlara dokunur.
 *
 * ── Ölçmeden yayınlamaz ──────────────────────────────────────────────────
 *
 * Bir anahtarın dosyada yazılı olması yetmez. Her satır için iki şey ölçülür
 * ve ölçülemezse komut HİÇBİR ŞEY YAPMADAN durur:
 *
 *   1. O sayfanın O DİLDE yazılmış metni var mı (`ContentLibraryPort`)?
 *   2. O satır kütükte var mı (`content_pages`)?
 *
 * İkinci ölçüm, komutun `site:import-map`ten SONRA çalıştığını da garanti
 * eder. Sessizce atlasaydı, sahibin gördüğü şey "komut yeşil yandı, altbilgi
 * hâlâ boş" olurdu — ve aranacak yer belli olmazdı.
 *
 * Yarım uygulama YOK: kararlar tek bir işlemde (transaction) uygulanır.
 * Yarım açılmış bir yayın, hiç açılmamış olandan kötüdür — sahip altbilgide
 * bir şeyler görür ve kararının tamamının işlediğini sanır.
 *
 * ── Geri alma ────────────────────────────────────────────────────────────
 *
 *   php artisan site:apply-publication-decisions --rollback
 *
 * Durum makinesi yayından taslağa dönmeye izin vermez ve VERMEMELİ:
 * yayınlanmış bir adres yayınlanmıştır, arama motoru onu görmüştür. Dürüst
 * geri alma `maintenance` kapısıdır — sayfa gezintiden ve sitemap'ten çıkar,
 * ziyaretçiye 503 "vardı, kısa süreliğine yok" der. `--restore` onu geri
 * getirir.
 */
final class ApplyPublicationDecisionsCommand extends Command
{
    protected $signature = 'site:apply-publication-decisions
        {--rollback : Kararı geri al — yayındaki sayfaları bakıma çeker (503)}
        {--restore : Geri alınmış kararı yeniden uygular — bakımdakileri yayına döndürür}
        {--dry-run : Hiçbir şey yazma, ne olacağını söyle}';

    protected $description = 'Adıyla sayılmış yayın kararlarını kütüğe uygular; geri alma ve geri getirme dahil.';

    public function handle(ContentLibraryPort $library): int
    {
        if ($this->option('rollback') && $this->option('restore')) {
            $this->error('`--rollback` ve `--restore` aynı anda verilemez.');

            return self::FAILURE;
        }

        try {
            $decisions = PublicationDecision::listFrom((array) config('content-publication-decisions'));
        } catch (InvalidArgumentException $exception) {
            $this->error('Kararlar dosyası okunamadı: '.$exception->getMessage());

            return self::FAILURE;
        }

        if ($decisions === []) {
            $this->info('site:apply-publication-decisions — karar yok, yapılacak iş yok.');

            return self::SUCCESS;
        }

        /** @var list<array{decision: PublicationDecision, page: ContentPage}> $work */
        $work = [];

        foreach ($decisions as $decision) {
            /*
                METNİ OLMAYAN SATIR YAYINLANMAZ — ölçüm, iddia değil.

                Kütükte "yayında" işaretlenmiş ama metni olmayan bir sayfa
                ziyaretçiye 404 döner (`ResolvePageDelivery` son emniyet
                kemeri). Yani onu buradan geçirmek, kütüğü yalancı yapmaktan
                başka bir şey üretmezdi.
            */
            if ($library->find($decision->pageKey, $decision->locale) === null) {
                $this->error(
                    "[{$decision->pageKey}/{$decision->locale}] için yazılmış bir metin yok; "
                    .'yayınlanmış görünüp 404 dönen bir sayfa en kötü hâldir.',
                );

                return self::FAILURE;
            }

            $page = ContentPage::query()
                ->where('page_key', $decision->pageKey)
                ->where('locale', $decision->locale)
                ->first();

            if ($page === null) {
                $this->error(
                    "[{$decision->pageKey}/{$decision->locale}] kütükte yok. "
                    .'Önce `php artisan site:import-map` çalıştırılmalı.',
                );

                return self::FAILURE;
            }

            $work[] = ['decision' => $decision, 'page' => $page];
        }

        $mode = match (true) {
            (bool) $this->option('rollback') => 'rollback',
            (bool) $this->option('restore') => 'restore',
            default => 'publish',
        };

        $changed = 0;
        $untouched = 0;

        $apply = function () use ($work, $mode, &$changed, &$untouched): void {
            foreach ($work as $item) {
                $decision = $item['decision'];
                $page = $item['page'];

                $applied = match ($mode) {
                    'rollback' => $this->rollback($page),
                    'restore' => $this->restore($page),
                    default => $this->publish($page),
                };

                if (! $applied) {
                    $untouched++;

                    continue;
                }

                $changed++;

                if (! $this->option('dry-run')) {
                    $page->save();
                }

                // Kararın uygulandığı KAYDA GEÇER: kim, hangi gün, neden.
                $this->line(
                    "  {$mode}: {$page->canonical_path} — {$decision->decidedBy}, "
                    ."{$decision->decidedOn}: {$decision->reason}",
                );
            }
        };

        if ($this->option('dry-run')) {
            $apply();
        } else {
            /*
                TEK İŞLEM. On sekiz sayfanın on ikisi açılıp altısı açılmasaydı,
                sahip altbilgide bir şeyler görür ve kararının tamamının
                işlediğini sanırdı.
            */
            DB::transaction($apply);
        }

        $this->info(
            'site:apply-publication-decisions'.($this->option('dry-run') ? ' (deneme)' : '')
            ." — {$mode}: {$changed} uygulandı, {$untouched} dokunulmadı.",
        );

        return self::SUCCESS;
    }

    /**
     * Kararı uygular: mutlu yolu adım adım yürüyerek `published`a çıkar.
     *
     * ZATEN BİR KEZ YAYINLANMIŞ satıra DOKUNULMAZ. Bu, komutun tekrar
     * çalıştırılmasını zararsız yapan şeydir ve daha önemlisi, sahibin
     * SONRADAN verdiği bir kararı korur: bir sayfayı bakıma aldıysa, bir
     * sonraki koşu onu sessizce yayına döndürmemeli. Bir insanın kararını
     * bir betikle geri almak, kütüğü güvenilmez yapardı.
     */
    private function publish(ContentPage $page): bool
    {
        if ($page->was_ever_published) {
            return false;
        }

        $status = $page->status();

        while ($status !== PagePublicationStatus::Published) {
            $next = $status->next();

            if ($next === null || ! $status->canMoveTo($next)) {
                $this->warn("  atlandı: {$page->canonical_path} — {$status->value} durumundan yayına yol yok.");

                return false;
            }

            $status = $next;
        }

        $page->publication_status = $status->value;
        $page->was_ever_published = true;
        $page->published_at = now();
        $page->unpublished_at = null;

        return true;
    }

    /** Yayındaki sayfayı bakıma çeker — 503, gezintiden ve sitemap'ten çıkar. */
    private function rollback(ContentPage $page): bool
    {
        if ($page->status() !== PagePublicationStatus::Published) {
            return false;
        }

        $page->publication_status = PagePublicationStatus::Maintenance->value;
        $page->unpublished_at = now();

        return true;
    }

    /** Bakımdaki sayfayı yayına döndürür — geri almanın geri alınması. */
    private function restore(ContentPage $page): bool
    {
        if ($page->status() !== PagePublicationStatus::Maintenance) {
            return false;
        }

        $page->publication_status = PagePublicationStatus::Published->value;
        $page->unpublished_at = null;

        return true;
    }
}
