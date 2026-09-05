<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Media\Port\MalwareScannerAvailabilityPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Application\Media\UseCase\ProcessAcceptedMediaAsset;
use App\Application\Media\UseCase\ScanQuarantinedMediaAsset;
use App\Domain\Media\MediaAssetStatus;
use App\Models\MediaAsset;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * İYİLEŞME YOLU — FF-153 (`docs/76` P0-08'in son halkası).
 *
 *   php artisan media:rescan-held                    # tüm çalışma alanları
 *   php artisan media:rescan-held --workspace=7      # yalnız bir restoran
 *   php artisan media:rescan-held --dry-run          # ne yapılacağını yaz, dokunma
 *
 * NEDEN VAR. FF-150/151/152 ürüne gerçeği SÖYLEMEYİ öğretti: sunucuda virüs
 * tarayıcı yokken yüklenen dosya `scanning` durumunda bekler ve sahip bunu
 * yüklerken de, menüye bağlamaya çalışırken de okur. Ama gerçeği
 * DEĞİŞTİRMENİN yolu yoktu. Sahip sunucuya ClamAV kurduğunda, kesinti
 * boyunca yüklenmiş her dosya sonsuza dek beklemeye devam ediyordu:
 * `media:reprocess` yalnız ZATEN `ready` olan varlıkların türevlerini
 * yeniler, `scanning`de takılı olanlara bakmaz. Dürüstçe "bekliyor" demek,
 * sonsuza dek beklettikten sonra bir teselli değildir.
 *
 * BU KOMUT TARAMAYI ATLAMAZ. "Bekleyeni geçir" değil, "yeniden dene"
 * komutudur. Varlığı bir adım geri (`scanning` → `quarantined`) bırakır ve
 * NORMAL tarama hattını yeniden koşturur; hükmü veren yine
 * `ScanQuarantinedMediaAsset`'tir. Kirli çıkan dosya reddedilir, belirsiz
 * kalan yine bekler. İkinci bir "kurtarma hattı" yazmak, güvenlik
 * kararlarının iki ayrı yerde verilmesi ve bir gün ayrışması demek olurdu.
 *
 * ZAMANLANMIŞ GÖREV DEĞİLDİR ve `routes/console.php`'ye EKLENMEZ. Bu, bir
 * ORTAM DEĞİŞİKLİĞİNDEN sonra bir kez elle koşulacak kurtarma komutudur.
 * Dakikada bir koşan bir görev, tarayıcı yokken hiçbir şeyi ilerletmeden
 * her dosyayı sonsuza dek yeniden denerdi: iş kaydı hiçbir şey öğretmeyen
 * satırlarla dolar, sunucu boşuna çalışır ve gürültünün içinde gerçek bir
 * arıza görünmez olur. Kurtarmanın ne zaman koşacağını bilen tek şey,
 * tarayıcıyı kuran insandır.
 *
 * OPERATÖR İÇİNDİR, SAHİP İÇİN DEĞİL. Panele düğme açmaz: sahibin
 * karşısına "yeniden dene" koymak, sorunun onun elinde olduğunu ima
 * ederdi — oysa sorun sunucudadır ve çözümü sunucuya erişen kişidedir.
 */
final class RescanHeldMediaCommand extends Command
{
    protected $signature = 'media:rescan-held
        {--workspace= : yalnız bu çalışma alanı (boş bırakılırsa hepsi)}
        {--dry-run : hiçbir şeye dokunma; yalnız nelerin deneneceğini yaz}';

    protected $description = 'Re-scan media assets stranded before the virus scan and let clean ones continue into normal processing. Never skips the scan; does nothing when no scanner is available.';

    public function handle(
        MalwareScannerAvailabilityPort $scannerAvailability,
        MediaRepositoryPort $media,
        ScanQuarantinedMediaAsset $scan,
        ProcessAcceptedMediaAsset $process,
    ): int {
        $workspaceOption = $this->option('workspace');
        $workspaceId = $workspaceOption === null ? null : (int) $workspaceOption;

        if ($workspaceId !== null && $workspaceId < 1) {
            $this->error('--workspace bir çalışma alanı kimliği olmalı.');

            return self::FAILURE;
        }

        $candidates = $this->candidates($workspaceId);
        $total = $candidates->count();

        /*
            ÖNCE ORTAM, SONRA DOSYALAR. Tarayıcının olmadığı ORTAM
            düzeyinde bellidir; bunu her dosya için ayrı ayrı denemek,
            hiçbir şey öğretmeyen bir sürü `held` iş kaydı yazmak olurdu.
            Ve sessizce "başarılı" dönmek buradaki en kötü sonuçtur: sahip
            kurtarmanın işlediğini sanır, dosyalarını yayına aldığını sanır
            ve gerçeği ancak misafir boş bir menüye baktığında öğrenir.
        */
        if (! $scannerAvailability->isUsable()) {
            $this->error('Virüs taraması bu ortamda çalışmıyor; hiçbir dosya ilerletilmedi.');
            $this->line('Taranmayı bekleyen varlık: '.$total.'.');
            $this->line('Önce tarayıcıyı kurun (MEDIA_SCANNER_DRIVER=clamav ve çalıştırılabilir bir ikili), sonra bu komutu yeniden koşun.');

            // Bekleyen yoksa yapılamamış bir iş de yok; kurtarılacak dosya
            // varken ise komut işini yapamamıştır ve bunu çıkış koduyla da
            // söyler (bir işletim betiği çıktıyı okumayabilir).
            return $total === 0 ? self::SUCCESS : self::FAILURE;
        }

        if ($total === 0) {
            $this->info('Taranmayı bekleyen varlık yok.');

            return self::SUCCESS;
        }

        if ((bool) $this->option('dry-run')) {
            foreach ($candidates as $row) {
                $this->line(sprintf('#%d (çalışma alanı %d): yeniden taranacak — %s', (int) $row->id, (int) $row->workspace_id, (string) $row->status));
            }

            $this->info(sprintf('KURU ÇALIŞTIRMA — %d varlık yeniden taranacaktı; hiçbirine dokunulmadı.', $total));

            return self::SUCCESS;
        }

        $tally = ['ready' => 0, 'rejected' => 0, 'failed' => 0, 'waiting' => 0];

        foreach ($candidates as $row) {
            $assetId = (int) $row->id;

            /*
                KİRACI SINIRI YAPISALDIR. Çalışma alanı kimliği satırın
                KENDİSİNDEN gelir ve aşağıdaki her çağrıya taşınır; alttaki
                her sorgu `where workspace_id` ile koşulludur. Yani bir
                çalışma alanının komutu, `--workspace` yanlış yazılsa bile
                başka bir çalışma alanının dosyasına dokunamaz — sınır
                komutun dikkatine değil, sorgunun kendisine gömülüdür.
            */
            $assetWorkspaceId = (int) $row->workspace_id;

            // Bir adım geri: tarama hattının kendi kapısından geçebilsin.
            $media->releaseScanningToQuarantine($assetWorkspaceId, $assetId);

            // Ve NORMAL hat koşar. Hükmü veren yine tarayıcıdır.
            ($scan)($assetWorkspaceId, $assetId);

            // Temiz çıktıysa dosya `accepted`tır ve burada gerçekten
            // kullanılabilir hâle gelir. Kirli ya da belirsizse bu çağrı
            // sessizce hiçbir şey yapmaz (varlık `accepted` değildir).
            ($process)($assetWorkspaceId, $assetId);

            $after = $media->find($assetId);
            $status = (string) ($after?->status ?? '');
            $reason = trim((string) ($after?->statusReason ?? ''));

            $tally[match ($status) {
                MediaAssetStatus::Ready->value => 'ready',
                MediaAssetStatus::Rejected->value => 'rejected',
                MediaAssetStatus::Failed->value => 'failed',
                default => 'waiting',
            }]++;

            // Satır başına DURUM ve varsa SEBEP. "Bekliyor" tek başına bir
            // bilgi değil, bir çıkmazdır (`docs/76`).
            $this->line(sprintf(
                '#%d (çalışma alanı %d): %s%s',
                $assetId,
                $assetWorkspaceId,
                $status === '' ? 'bulunamadı' : $status,
                $reason === '' ? '' : ' — '.$reason,
            ));
        }

        /*
            SAYILAR SAYILMIŞTIR, TAHMİN EDİLMEMİŞTİR. Yüzde yok, kalan süre
            yok: bu komut ne kadar süreceğini bilmez ve bilmediğini
            uydurmaz (`EloquentMediaProcessingJobs`'un yüzde sütunu
            olmaması ile aynı karar).
        */
        $this->info(sprintf(
            'Denendi: %d · kullanıma hazır: %d · reddedildi: %d · işlemede takıldı: %d · hâlâ bekliyor: %d.',
            $total,
            $tally['ready'],
            $tally['rejected'],
            $tally['failed'],
            $tally['waiting'],
        ));

        // Reddedilen dosya bir başarısızlık DEĞİLDİR: hüküm verildi, zincir
        // kapandı. Kurtarılamamış olan yalnız hâlâ bekleyendir.
        return $tally['waiting'] === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Taranmayı bekleyen varlıklar.
     *
     * İKİ durum birden aranır. `scanning`, tarayıcı yokken yüklenmiş ve
     * mahsur kalmış dosyadır. `quarantined` ise bu komutun kendi yarım
     * kalmış bir koşusudur (süreç iki adımın arasında öldü) — onu da
     * toplamak, komutu kendi kendini onarır kılar.
     *
     * Çöpteki varlıklar KAPSAM DIŞIDIR: `MediaAsset` yumuşak silme
     * kullanır, dolayısıyla sorgu onları zaten görmez. Sahibin sildiği bir
     * dosyayı bir kurtarma komutunun geri diriltmesi, silmenin anlamını
     * bozardı.
     *
     * @return Collection<int, MediaAsset>
     */
    private function candidates(?int $workspaceId): Collection
    {
        return MediaAsset::query()
            ->whereIn('status', [
                MediaAssetStatus::Scanning->value,
                MediaAssetStatus::Quarantined->value,
            ])
            ->when($workspaceId !== null, static fn ($query) => $query->where('workspace_id', $workspaceId))
            ->orderBy('id')
            ->get(['id', 'workspace_id', 'status']);
    }
}
