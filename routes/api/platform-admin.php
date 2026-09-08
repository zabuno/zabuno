<?php

declare(strict_types=1);

use App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController;
use App\Http\Controllers\PlatformAdmin\DisableProviderCredentialController;
use App\Http\Controllers\PlatformAdmin\EndSupportAccessController;
use App\Http\Controllers\PlatformAdmin\ListCoreModulesController;
use App\Http\Controllers\PlatformAdmin\ListManagedPlansController;
use App\Http\Controllers\PlatformAdmin\ListManagedUsersController;
use App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController;
use App\Http\Controllers\PlatformAdmin\ListPlatformAuditLogController;
use App\Http\Controllers\PlatformAdmin\ListProviderConnectionsController;
use App\Http\Controllers\PlatformAdmin\ListProviderCredentialsController;
use App\Http\Controllers\PlatformAdmin\OpenSupportAccessController;
use App\Http\Controllers\PlatformAdmin\ProbeProviderConnectionController;
use App\Http\Controllers\PlatformAdmin\RefundPaymentTransactionController;
use App\Http\Controllers\PlatformAdmin\SetProviderConnectionStateController;
use App\Http\Controllers\PlatformAdmin\ShowAiAuditController;
use App\Http\Controllers\PlatformAdmin\ShowBillingModeController;
use App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController;
use App\Http\Controllers\PlatformAdmin\ShowManagedWorkspaceController;
use App\Http\Controllers\PlatformAdmin\ShowSupportAccessController;
use App\Http\Controllers\PlatformAdmin\ShowTenantSupportViewController;
use App\Http\Controllers\PlatformAdmin\StoreManagedPlanController;
use App\Http\Controllers\PlatformAdmin\StoreManualPaymentController;
use App\Http\Controllers\PlatformAdmin\StoreProviderConnectionController;
use App\Http\Controllers\PlatformAdmin\StoreProviderCredentialController;
use App\Http\Controllers\PlatformAdmin\StoreReleaseAttestationController;
use App\Http\Controllers\PlatformAdmin\UpdateBillingModeController;
use App\Http\Controllers\PlatformAdmin\UpdateProviderConnectionController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::middleware(EnsurePlatformSuperAdmin::class)->group(function () {
        Route::get('/admin/plans', ListManagedPlansController::class);
        Route::post('/admin/plans', StoreManagedPlanController::class);
        Route::post('/admin/plans/{plan}/activate', ActivateManagedPlanController::class);

        Route::get('/admin/workspaces', ListManagedWorkspacesController::class);
        /*
            Kiracı ayrıntısı (`docs/122` Y2). Liste vardı, satıra tıklayınca
            hiçbir şey yoktu. SALT OKUNUR ve öyle KALDI: bu ucun POST/PUT/
            DELETE eşi bilerek yoktur. Kiracı olarak bakma Y7'de geldi ama
            buraya BAĞLANMADI — kendi ucu, kendi sebebi ve kendi süresi var
            (bu dosyanın sonu). Kiracı ayrıntısına bir "bakmaya başla"
            düğmesi konsaydı, `docs/122` §5'in zorluk şartı sessizce iptal
            edilmiş olurdu.
        */
        Route::get('/admin/workspaces/{workspace}', ShowManagedWorkspaceController::class)
            ->whereNumber('workspace');
        Route::get('/admin/workspaces/{workspace}/subscription', ShowManagedSubscriptionController::class);
        Route::post('/admin/workspaces/{workspace}/manual-payments', StoreManualPaymentController::class)->middleware('throttle:5,1');
        // İADE (docs/107 Faz 1.1, docs/123): süperadmin, sebep zorunlu,
        // defterde ters kayıt, denetim satırı. Manuel ödemeyle aynı hız sınırı.
        Route::post('/admin/workspaces/{workspace}/transactions/{transaction}/refund', RefundPaymentTransactionController::class)->middleware('throttle:5,1');
        // KİP ANAHTARI (docs/123): canlı tahsilat yalnız kasa dolu VE
        // süperadmin açıkça açtıysa; her değişim denetime yazılır.
        Route::get('/admin/settings/billing-mode', ShowBillingModeController::class);
        Route::put('/admin/settings/billing-mode', UpdateBillingModeController::class)->middleware('throttle:20,1');

        /*
            Kullanıcı görünürlüğü (`docs/122` Y2): kim, hangi çalışma
            alanında, hangi rolle, adresi doğrulanmış mı. Parola sıfırlama
            ya da kilitleme ucu YOK — istenen görünürlüktü, müdahale değil.
        */
        Route::get('/admin/users', ListManagedUsersController::class);

        /*
            Denetim günlüğü ekranının ucu (`docs/122` Y2). Dört tablo aylardır
            doluyordu ve okuyan yeri yoktu; okunmayan denetim izi yoktur.
        */
        Route::get('/admin/audit-log', ListPlatformAuditLogController::class);

        // Sağlayıcı kimlik-bilgisi kasası — `docs/94`. Yazma uçları
        // superadmin arkasında ve throttle'lı; yine de sır cevaba çıkmaz.
        // AI denetim izi (`docs/98` FF-66): kim hangi anahtarı ne zaman
        // yazdı, hangi tenant hangi hesaba yapıştı. Sır taşımaz.
        Route::get('/admin/ai/audit', ShowAiAuditController::class);

        /*
            Modül envanteri (`docs/111` adım 1) — mühendislik kanıtı, ticaret
            değil; ekranı da ticari `/platform` bölümlerinin arasında değil,
            `/platform/engineering` kabuğunda.
            Yalnız OKUMA: modül açma/kapama bu depoda modellenmiş değil, o
            yüzden bir yazma ucu da yok (`docs/111` §5.1).
        */
        Route::get('/admin/modules', ListCoreModulesController::class);

        // İnsan tanıklığı kaydı (`docs/98` FF-63) — yalnız superadmin.
        Route::post('/admin/release-attestations', StoreReleaseAttestationController::class)->middleware('throttle:20,1');

        Route::get('/admin/credentials', ListProviderCredentialsController::class);
        Route::put('/admin/credentials/{provider}', StoreProviderCredentialController::class)->middleware('throttle:20,1');
        Route::post('/admin/credentials/{provider}/disable', DisableProviderCredentialController::class)->middleware('throttle:20,1');

        /*
            Çok-bağlantı yüzeyi — `docs/95` Faz 3. Üstteki sağlayıcı-düzeyi
            uçlar KALDIRILMADI: onlar aynı verinin "varsayılan bağlantı"
            kısayolu ve Faz 2'den beri yayınlanmış yüzey. Silme ucu bilerek
            YOK — kapatmak silmek değildir; yanlışlıkla kapatılan bir hesap
            anahtar yeniden girilmeden geri açılabilmeli.
        */
        Route::get('/admin/connections', ListProviderConnectionsController::class);
        Route::post('/admin/connections', StoreProviderConnectionController::class)->middleware('throttle:20,1');
        Route::put('/admin/connections/{connection}', UpdateProviderConnectionController::class)->middleware('throttle:20,1');
        /*
            Uyumluluk yoklaması (`docs/95` Faz 3). Ayrı ve DAHA SIKI hız
            sınırı: dışarıya gerçek bir ağ çağrısı yapar, token harcamasa
            bile sınırsız denemeye açık bırakılmaz.
        */
        Route::post('/admin/connections/{connection}/probe', ProbeProviderConnectionController::class)
            ->middleware('throttle:10,1');
        Route::post('/admin/connections/{connection}/{state}', SetProviderConnectionStateController::class)
            ->whereIn('state', ['disable', 'enable'])
            ->middleware('throttle:20,1');

        /*
            DESTEK GÖRÜNÜMÜ VE KİRACI OLARAK BAKMA (`docs/122` Y7,
            `docs/133`).

            Y2 turunda bu dosyanın kendi yorumu şöyle diyordu: *"kiracı
            olarak oturum açma (impersonation) Y7'ye bırakılmıştır."* O gün
            bugündür ve kapı, `docs/122` §5'in şart koştuğu dört kilitle
            birlikte açılıyor — sebep, süre, kiracının görebileceği kayıt,
            ve salt-okunurluk.

            SIRA KASITLI: önce SALT OKUNUR görünüm, sonra bakma ucu. Destek
            çağrılarının çoğu `support-view` ile cevaplanır ve kiracının
            gözüne girmeyi hiç gerektirmez; impersonation son çaredir.

            `support-view` GET'tir ve hız sınırı taşımaz: salt okunur, tıpkı
            kiracı ayrıntısı gibi. `support-access` POST'tur ve manuel
            ödemeyle AYNI sınırı taşır (`throttle:5,1`) — bir kiracıya bakmak
            en az bir ödeme kaydı kadar ağır bir fiildir.

            BİTİRME UCU AYRI VE OTURUM KİMLİĞİ TAŞIMAZ: kim olduğu oturumdan
            okunur, gövdeden değil, dolayısıyla başkasının oturumunu
            kapatmanın yolu yoktur. Bu yol, salt-okunur kilidin iki
            istisnasından biridir (`EnsureSupportAccessIsReadOnly`).

            UZATMA UCU YOKTUR ve bu listede bir gün `PUT
            /admin/support-access` ya da `.../extend` biçiminde bir satır
            belirirse, o bir kapsam kararıdır ve `docs/122` §5'e aykırıdır:
            uzatılabilir bir süre yalnız ertelenmiş bir süresizliktir.
        */
        Route::get('/admin/workspaces/{workspace}/support-view', ShowTenantSupportViewController::class)
            ->whereNumber('workspace');
        Route::get('/admin/support-access', ShowSupportAccessController::class);
        Route::post('/admin/support-access/end', EndSupportAccessController::class)->middleware('throttle:20,1');
        Route::post('/admin/workspaces/{workspace}/support-access', OpenSupportAccessController::class)
            ->whereNumber('workspace')
            ->middleware('throttle:5,1');
    });
});
