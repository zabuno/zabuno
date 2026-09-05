<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Content\Port\ContentLibraryPort;
use App\Domain\Content\PagePublicationStatus;
use App\Models\ContentPage;
use Illuminate\Console\Command;

/**
 * İçeriği YAZILMIŞ sayfaları `content_draft`a taşır — FF-191, yönerge §6.
 *
 * "İçeriği bitenin durumunu ilerlet" bir cümle olarak kaldığı sürece elle
 * uygulanır ve bir gün yanlış uygulanır — genelde ileri doğru, çünkü hata
 * hep aynı yöne yapılır. Komut kararı ÖLÇÜME bağlar: durum, içerik kütüğünün
 * o sayfa ve O DİL için gerçekten bir metin taşıyıp taşımadığından türer.
 *
 * **Tavanı `content_draft`tır ve bu tavan tartışmaya kapalıdır.** Kalite
 * kapısı (içerik onayı, tasarım, SEO, erişilebilirlik, QA — yönerge §20)
 * insanların işidir. Bir betiğin atlayabildiği kapı, kapı değildir.
 *
 * Komut GERİ ALMAZ: incelemeye girmiş ya da yayınlanmış bir sayfaya
 * dokunmaz. Bir insanın verdiği kararı bir betikle geri almak, kütüğü
 * güvenilmez yapardı.
 */
final class SyncContentStatusCommand extends Command
{
    protected $signature = 'site:sync-content-status {--dry-run}';

    protected $description = 'İçeriği yazılmış kurumsal sayfaları content_draft durumuna taşır.';

    /** Bu komutun ilerletebileceği EN İLERİ durum. */
    private const CEILING = PagePublicationStatus::ContentDraft;

    public function handle(ContentLibraryPort $library): int
    {
        $advanced = 0;
        $untouched = 0;

        foreach ($library->all() as $content) {
            $page = ContentPage::query()
                // Dil ŞARTTIR: kütükteki kayıt kendi dilinde bir metin
                // taşımıyorsa "taslağı hazır" demek yalan olurdu.
                ->where('page_key', $content->pageKey)
                ->where('locale', $content->locale)
                ->first();

            if ($page === null) {
                continue;
            }

            $status = $page->status();
            $steps = self::stepsTo($status, self::CEILING);

            if ($steps === []) {
                $untouched++;

                continue;
            }

            if ($this->option('dry-run')) {
                $advanced++;

                continue;
            }

            /*
                Durum makinesi ADIM ADIM yürütülür, hedefe atlanmaz. Atlasaydık
                `canMoveTo` sözleşmesi (yönerge §6: "bir sayfa taslaktan doğrudan
                yayına atlayamaz") bu komutta geçersiz olurdu — ve bir kez
                geçersiz olan bir kural, ikinci kez de geçersiz olur.
            */
            foreach ($steps as $step) {
                $page->publication_status = $step->value;
            }

            $page->save();
            $advanced++;
        }

        $this->info("site:sync-content-status — {$advanced} ilerletildi, {$untouched} dokunulmadı.");

        return self::SUCCESS;
    }

    /**
     * Şu durumdan tavana giden adımlar; gidilemiyorsa boş.
     *
     * @return list<PagePublicationStatus>
     */
    private static function stepsTo(PagePublicationStatus $from, PagePublicationStatus $ceiling): array
    {
        $steps = [];
        $current = $from;

        while ($current !== $ceiling) {
            $next = $current->next();

            /*
                İleri yolu bitti ve tavana uğramadık: demek ki sayfa tavanı
                zaten GEÇMİŞ (incelemede, onaylı ya da yayında) ya da mutlu
                yolun dışında (bakım, emekli). İkisinde de yapılacak şey aynı:
                dokunma. Bir insanın verdiği kararı bir betikle geri almak,
                kütüğü güvenilmez yapardı.
            */
            if ($next === null || ! $current->canMoveTo($next)) {
                return [];
            }

            $steps[] = $next;
            $current = $next;
        }

        return $steps;
    }
}
