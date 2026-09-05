<?php

declare(strict_types=1);

use App\Http\Controllers\Media\ConvertMediaController;
use App\Http\Controllers\Media\CreateOriginalDownloadLinkController;
use App\Http\Controllers\Media\DeleteMediaController;
use App\Http\Controllers\Media\DeleteMediaFolderController;
use App\Http\Controllers\Media\DetachMediaUsagesController;
use App\Http\Controllers\Media\ListConversionTargetsController;
use App\Http\Controllers\Media\ListDerivativeRulesController;
use App\Http\Controllers\Media\ListMediaAuditsController;
use App\Http\Controllers\Media\ListMediaController;
use App\Http\Controllers\Media\ListMediaFoldersController;
use App\Http\Controllers\Media\ListMediaProcessingJobsController;
use App\Http\Controllers\Media\ListMediaVersionsController;
use App\Http\Controllers\Media\ListSlotPoliciesController;
use App\Http\Controllers\Media\MoveMediaToFolderController;
use App\Http\Controllers\Media\PlanMediaBulkOperationController;
use App\Http\Controllers\Media\RenameMediaFolderController;
use App\Http\Controllers\Media\ReprocessMediaBatchController;
use App\Http\Controllers\Media\ReprocessMediaController;
use App\Http\Controllers\Media\RestoreMediaController;
use App\Http\Controllers\Media\RestoreMediaVersionController;
use App\Http\Controllers\Media\RunMediaBulkOperationController;
use App\Http\Controllers\Media\ServeMediaPreviewController;
use App\Http\Controllers\Media\ShowMediaGovernanceController;
use App\Http\Controllers\Media\ShowMediaMaturityController;
use App\Http\Controllers\Media\ShowMediaQuotaController;
use App\Http\Controllers\Media\ShowMediaSettingsController;
use App\Http\Controllers\Media\ShowMediaStorageBreakdownController;
use App\Http\Controllers\Media\ShowMediaUsagesController;
use App\Http\Controllers\Media\ShowMediaViewerController;
use App\Http\Controllers\Media\StoreMediaController;
use App\Http\Controllers\Media\StoreMediaFolderController;
use App\Http\Controllers\Media\UpdateMediaAltTextController;
use App\Http\Controllers\Media\UpdateMediaLegalHoldController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/media', StoreMediaController::class);
    Route::get('/workspaces/{workspace}/media', ListMediaController::class);
    // Slot politikaları workspace'e bağlı DEĞİLDİR: ürünün kendi kuralları.
    Route::get('/media/slot-policies', ListSlotPoliciesController::class);
    Route::delete('/workspaces/{workspace}/media/{media}', DeleteMediaController::class);

    /*
        SÜRÜMLER (`docs/49` Faz 3, `docs/98` FF-69). Asıl değişmez; yeniden
        üretim ve geri alma yeni sürüm açar, hiçbir satır silinmez. Yeniden
        üretim hız sınırlı: her çağrı görsel işler.
    */
    Route::get('/workspaces/{workspace}/media/{media}/versions', ListMediaVersionsController::class);
    Route::post('/workspaces/{workspace}/media/{media}/reprocess', ReprocessMediaController::class)
        ->middleware('throttle:10,1');
    Route::post('/workspaces/{workspace}/media/{media}/versions/{version}/restore', RestoreMediaVersionController::class);

    /*
        KULLANIM GRAFİĞİ ve ÇÖP (`docs/49` Faz 4-5, `docs/98` FF-70). Silme
        artık çöpe atar; geri alınabilir; süresi dolan `media:purge-trash`
        ile kalıcı gider. "Nerede kullanılıyor?" silmeden önce sorulur.
    */
    Route::get('/workspaces/{workspace}/media/{media}/usages', ShowMediaUsagesController::class);
    Route::post('/workspaces/{workspace}/media/{media}/detach', DetachMediaUsagesController::class);
    Route::post('/workspaces/{workspace}/media/{media}/restore', RestoreMediaController::class);
    // Alt metni düzelt (`docs/49` §5.2 re-naming, FF-76).
    Route::patch('/workspaces/{workspace}/media/{media}', UpdateMediaAltTextController::class);

    // KOTA ve ASIL İNDİRME (`docs/49` Faz 6-7, `docs/98` FF-71).
    Route::get('/workspaces/{workspace}/media/quota', ShowMediaQuotaController::class);
    // Denetim izi (`docs/49` Faz 7 madde 4): kim ne zaman ne yaptı.
    Route::get('/workspaces/{workspace}/media/audits', ListMediaAuditsController::class);
    Route::post('/workspaces/{workspace}/media/{media}/download-link', CreateOriginalDownloadLinkController::class);

    /*
        KLASÖRLER (`docs/108` §3 madde 1). Elli fotoğraf tek düz listede
        duruyordu; arama yalnız adını hatırladığın dosyayı bulur. Klasör
        yolu `/media/folders` altında toplanır — `/media/{media}` yolları
        hep bir alt segmentle devam ettiği için ikisi çakışmaz.

        Silme ve taşıma hız sınırsızdır: ikisi de tek bir satır günceller,
        dış bir maliyet doğurmaz.
    */
    Route::get('/workspaces/{workspace}/media/folders', ListMediaFoldersController::class);
    Route::post('/workspaces/{workspace}/media/folders', StoreMediaFolderController::class);
    Route::patch('/workspaces/{workspace}/media/folders/{folder}', RenameMediaFolderController::class);
    Route::delete('/workspaces/{workspace}/media/folders/{folder}', DeleteMediaFolderController::class);
    Route::put('/workspaces/{workspace}/media/{media}/folder', MoveMediaToFolderController::class);

    /*
        GÖRÜNTÜLE (`docs/108` §3 madde 8, kaynak ekranı "Görüntüle").

        İki uç, iki ayrı iş: `viewer` ekrana HANGİ okuyucunun çizileceğini
        söyler (tür, açılabilir mi, açılmıyorsa neden, PDF ise okunabildiği
        kadarıyla sayfa sayısı); `preview` dosyanın kendisini panelin
        İÇİNDE açılacak biçimde verir — var olan asıl indirme ucu ise onu
        `attachment` olarak verir ve öyle vermek zorundadır.

        İkisi de SALT OKUNUR ve hız sınırsızdır: tek bir satır okur, dış
        bir maliyet doğurmaz. `preview` bir çerçeve/`<img>` isteğidir ve
        PDF'te her sayfa değişiminde tekrarlanır — ona sınır koymak, on
        iki sayfalık bir belgeyi okumayı ortasında kesmek olurdu.

        Yollar `/media/{media}` altında bir alt segmentle devam ediyor;
        `folders`/`jobs` gibi sabit segmentli yollarla çakışmaz.
    */
    Route::get('/workspaces/{workspace}/media/{media}/viewer', ShowMediaViewerController::class);
    Route::get('/workspaces/{workspace}/media/{media}/preview', ServeMediaPreviewController::class);

    /*
        BOYUT MOTORU ve KUYRUK (`docs/108` §3 madde 4-5, §6.1).

        Kural OKUMAK bir dosyayı bile değiştirmez, o yüzden hız sınırsızdır.
        TOPLU yeniden üretim ise tek çağrıda bütün hazır dosyaları işler;
        sınırı tek-varlık ucundan (`throttle:10,1`) daha sıkıdır — on kat
        işi on kat sık çalıştırmaya izin vermenin bir gerekçesi yok.

        Kuyruk salt okunurdur: "yeniden dene" var olan
        `media/{media}/reprocess` ucuna gider, burada iş başlatılmaz.

        Yollar `folders` gibi sabit segmentle başlıyor ve `/media/{media}`
        yollarıyla çakışmıyor.
    */
    Route::get('/workspaces/{workspace}/media/derivative-rules', ListDerivativeRulesController::class);

    /*
        YER ve AYARLAR (`docs/108` §6.4-§6.6). İkisi de SALT OKUNURDUR ve
        hız sınırsızdır: biri sayar, diğeri config okur, hiçbiri dosya
        işlemez.

        `media/storage-breakdown` kotadan AYRI bir uçtur. Kota durumu HER
        YÜKLEMEDE okunur (`MediaQuotaPort::admits`); kırılımı oraya
        eklemek her yüklemeye bir gruplama sorgusu daha bindirirdi.

        `media/settings` bir KAYDETME ucu değildir ve olmayacaktır: bu
        depoda desen değiştirilemez, güvenlik önlemi kapatılamaz. Uç yalnız
        durumu bildirir; ekran da kaydetme kutusu çizmez.

        İkisi de `folders` gibi sabit segmentle başlar ve `/media/{media}`
        yollarıyla çakışmaz.
    */
    Route::get('/workspaces/{workspace}/media/storage-breakdown', ShowMediaStorageBreakdownController::class);
    Route::get('/workspaces/{workspace}/media/settings', ShowMediaSettingsController::class);

    /*
        OLGUNLUK (`docs/109-PANEL-V3.md` §2, kaynak ekranı "Olgunluk").

        SALT OKUNURDUR ve hız sınırsızdır: yönlendirici koleksiyonunu ve
        test paketini okur, hiçbir dosya işlemez. Okuduğu şey KİRACI VERİSİ
        DEĞİL, ürünün kendi durumudur — ama adres yine de bir kiracıya ait
        olduğu için yetki sorulur.

        Ayarların HEMEN YANINDA durur ve ondan ayrıdır: ayarlar "sistem ne
        yapıyor" der, olgunluk "hangi yeteneğin arkasında ne kadar kanıt
        var" der.

        `folders` gibi sabit segmentle başlar; `/media/{media}` yolları her
        zaman bir alt segmentle devam ettiği için çakışma yok.
    */
    Route::get('/workspaces/{workspace}/media/maturity', ShowMediaMaturityController::class);
    Route::post('/workspaces/{workspace}/media/reprocess', ReprocessMediaBatchController::class)
        ->middleware('throttle:2,1');
    Route::get('/workspaces/{workspace}/media/jobs', ListMediaProcessingJobsController::class);

    /*
        DÖNÜŞTÜR (`docs/108` §6.3, kaynak ekranı "Dönüştür").

        Okumak SALT OKUNURDUR: hedef listesi, her hedefin BU KURULUMDA
        desteklenip desteklenmediği, seçilebilir dosyalar ve daha önce
        gerçekten tartılmış kazanç. Tek bir dosyayı bile değiştirmediği için
        hız sınırı yok.

        Dönüştürme ise toplu yeniden üretimle AYNI sınırı taşır
        (`throttle:2,1`): tek çağrı onlarca dosyayı kodlar ve AVIF
        kodlaması JPEG'den belirgin biçimde yavaştır. Kendi işleme hattı
        YOKTUR — var olan `ReprocessMediaAsset` bir hedef biçimle çağrılır,
        böylece "asıl korunur, yeni sürüm açılır" güvencesi tek bir yerde
        durur.

        İkisi de `folders`/`jobs` gibi sabit segmentle başlıyor;
        `/media/{media}` yolları her zaman bir alt segmentle devam ettiği
        için çakışma yok.
    */
    Route::get('/workspaces/{workspace}/media/conversion-targets', ListConversionTargetsController::class);
    Route::post('/workspaces/{workspace}/media/convert', ConvertMediaController::class)
        ->middleware('throttle:2,1');

    /*
        TOPLU İŞLEM ve YÖNETİŞİM (`docs/109-PANEL-V3.md` §2, kaynak
        ekranları "Toplu işlem" ve "Yönetişim").

        `bulk/plan` KURU ÇALIŞMADIR: hiçbir dosyaya dokunmaz, yalnız sayar.
        `POST` olması bir çelişki değil gövde gerekliliğidir — kapsam bin
        kimlikten oluşabilir ve bin kimliği adres satırına yazmak hem
        sınırı aşar hem de kimlikleri sunucu günlüklerine döker. Hız
        sınırı yine de var (`throttle:20,1`): bin kimlik için bin satır
        okur ve sınırsız tekrarı, ekranın her tuş vuruşunda planı yeniden
        istemesine izin verirdi.

        `bulk/run` var olan işleme yollarını çağırır (`ReprocessMediaAsset`,
        klasör taşıma, çöp, kalıcı silme). Sınırı dönüştürmeyle AYNIDIR
        (`throttle:2,1`): tek çağrı yüzlerce dosyaya dokunur ve o işi on
        kat sık çalıştırmaya izin vermenin bir gerekçesi yok.

        `governance` SALT OKUNURDUR ve hız sınırsızdır: yetki matrisini,
        saklama sayılarını ve denetim izini okur, hiçbir dosya işlemez.

        `{media}/legal-hold` tek bir dosyanın kilididir ve `workspace.
        manage` ister — kilit medya işi değil hukuk işidir.

        `bulk`/`governance` sabit segmentle başlar; `/media/{media}`
        yolları her zaman bir alt segmentle devam ettiği için çakışma yok.
    */
    Route::post('/workspaces/{workspace}/media/bulk/plan', PlanMediaBulkOperationController::class)
        ->middleware('throttle:20,1');
    Route::post('/workspaces/{workspace}/media/bulk/run', RunMediaBulkOperationController::class)
        ->middleware('throttle:2,1');
    Route::get('/workspaces/{workspace}/media/governance', ShowMediaGovernanceController::class);
    Route::put('/workspaces/{workspace}/media/{media}/legal-hold', UpdateMediaLegalHoldController::class);
});
