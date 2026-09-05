<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ModularApiRouteRegistrationTest extends TestCase
{
    /**
     * Frozen route signature snapshot: method|uri|name|action|middleware(sorted,csv).
     * `name` is the route name (empty string when unnamed) and `action` is
     * the exact Route::getActionName() output (controller::class@method or
     * a Closure marker). This is the current, working api.php behaviour and
     * must survive the later extraction into per-domain modules byte-for-byte.
     */
    private const FROZEN_ROUTE_SIGNATURES = [
        'GET|api/user||App\Http\Controllers\Auth\AuthenticatedUserController|api,auth:sanctum,verified',
        // HESAP BAKIMI (`docs/83`, P1-07): kullanıcı adını ve şifresini
        // panelden onarabiliyor. Şifre yolu hız sınırlı — mevcut şifre burada
        // doğrulanıyor ve sınırsız deneme, açık bırakılmış bir makinede
        // şifre tahmin etmenin yolu olurdu.
        'PUT|api/user/profile||App\Http\Controllers\Account\UpdateProfileController|api,auth:sanctum,verified',
        'PUT|api/user/avatar||App\Http\Controllers\Account\BindAvatarController|api,auth:sanctum,verified',
        'PUT|api/user/password||App\Http\Controllers\Account\UpdatePasswordController|api,auth:sanctum,throttle:6,1,verified',
        'POST|api/webhooks/iyzico-sandbox||App\Http\Controllers\Billing\ReceiveIyzicoSandboxWebhookController|api',
        'POST|api/billing/iyzico-sandbox/callback||App\Http\Controllers\Billing\ReceiveIyzicoSandboxCallbackController|api',
        'GET|api/reference/markets||App\Http\Controllers\Reference\ShowMarketReferenceController|api,auth:sanctum,verified',
        'POST|api/workspaces||App\Http\Controllers\Tenancy\CreateWorkspaceController|api,auth:sanctum,throttle:5,1,verified',
        'GET|api/workspaces||App\Http\Controllers\Tenancy\ListWorkspacesController|api,auth:sanctum,verified',
        'PUT|api/workspace-context||App\Http\Controllers\Tenancy\SwitchWorkspaceContextController|api,auth:sanctum,verified',
        'GET|api/workspace-context||App\Http\Controllers\Tenancy\CurrentWorkspaceContextController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand||App\Http\Controllers\Tenancy\StoreBrandController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/brand||App\Http\Controllers\Tenancy\ShowBrandController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/brand||App\Http\Controllers\Tenancy\UpdateBrandController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/brand/logo||App\Http\Controllers\Tenancy\BindBrandLogoController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand/locations||App\Http\Controllers\Tenancy\StoreLocationController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/brand/locations||App\Http\Controllers\Tenancy\ListLocationsController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/brand/locations/{location}||App\Http\Controllers\Tenancy\ShowLocationController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/brand/locations/{location}||App\Http\Controllers\Tenancy\UpdateLocationController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand/locations/{location}/menu||App\Http\Controllers\MenuCatalog\StoreMenuController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/brand/locations/{location}/menu||App\Http\Controllers\MenuCatalog\ShowMenuController|api,auth:sanctum,verified',
        /*
            ÇOKLU MENÜ VE SAAT BAZLI GEÇİŞ — sahibin 2026-09-05 kararı,
            `docs/109-PANEL-V3.md` §7.1: "çoklu menü YAPILSIN, saat bazlı
            geçişli".

            Bu altı imza dondurulmuş listeye SONRADAN eklendi ve gerekçesi
            budur. Kaynak `panel.dc.html` "Menüler" ekranında üç menü hapı
            gösteriyor (Ana menü yayında · Kahvaltı 07–11 · Ramazan kapalı);
            haplar bu yollar olmadan çizilemezdi:

            - `.../locations/{location}/menus` hapların listesi,
            - `GET .../menu/{menu}` hapa basınca O MENÜNÜN içeriği,
            - `PUT` / `DELETE .../menu/{menu}` menü düzenleme ve silme,
            - `.../menu/{menu}/service-window` menünün saat aralığı
              (`PUT` verir, `DELETE` menüyü kapatır — "Ramazan kapalı").

            `{menu}` sayıya sınırlıdır; aynı önekteki
            `menu/duplicate-candidates` yolunun bir menü kimliği sanılmaması
            için.
        */
        'GET|api/workspaces/{workspace}/brand/locations/{location}/menus||App\Http\Controllers\MenuCatalog\ListLocationMenusController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/menu/{menu}||App\Http\Controllers\MenuCatalog\ShowMenuTreeController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu/{menu}||App\Http\Controllers\MenuCatalog\RenameMenuController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/menu/{menu}||App\Http\Controllers\MenuCatalog\DeleteMenuController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu/{menu}/service-window||App\Http\Controllers\MenuCatalog\UpdateMenuServiceWindowController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/menu/{menu}/service-window||App\Http\Controllers\MenuCatalog\DeleteMenuServiceWindowController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/categories||App\Http\Controllers\MenuCatalog\StoreCategoryController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu-categories/{category}/products||App\Http\Controllers\MenuCatalog\StoreProductController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu-categories/{category}/menu-items||App\Http\Controllers\MenuCatalog\StoreMenuItemController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu-categories/{category}/menu-entries||App\Http\Controllers\MenuCatalog\StoreMenuEntryController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-items/{menuItem}/allergens||App\Http\Controllers\MenuCatalog\UpdateMenuItemAllergensController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-items/{menuItem}/price||App\Http\Controllers\MenuCatalog\UpdateMenuItemPriceController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-items/{menuItem}/visibility||App\Http\Controllers\MenuCatalog\UpdateMenuItemVisibilityController|api,auth:sanctum,verified',
        // MENÜYÜ İŞLETMEK (`docs/73`, P0-01): ürün bir menüyü yayımlayabiliyor
        // ama işletemiyordu — silme, ad düzeltme ve sıralama yoktu.
        'PUT|api/workspaces/{workspace}/menu-categories/{category}||App\Http\Controllers\MenuCatalog\RenameCategoryController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/menu-categories/{category}||App\Http\Controllers\MenuCatalog\DeleteCategoryController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-items/{menuItem}||App\Http\Controllers\MenuCatalog\RenameMenuItemController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/menu-items/{menuItem}||App\Http\Controllers\MenuCatalog\DeleteMenuItemController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-items/{menuItem}/image||App\Http\Controllers\MenuCatalog\BindMenuItemImageController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-items/{menuItem}/stock||App\Http\Controllers\MenuCatalog\UpdateMenuItemStockController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu/{menu}/stock||App\Http\Controllers\MenuCatalog\UpdateMenuStockController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/menu/{menu}/export.csv||App\Http\Controllers\MenuCatalog\ExportMenuCsvController|api,auth:sanctum,throttle:20,1,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/import||App\Http\Controllers\MenuCatalog\ImportMenuCsvController|api,auth:sanctum,throttle:10,1,verified',
        // FOTOĞRAFTAN MENÜ OKUMA (`docs/92`, P0-05 foto yolu). Okuma hız
        // sınırlı: her çağrı dış bir sağlayıcıya para ödetir. Onay AYRI bir
        // yoldur ve yetki orada yeniden doğrulanır.
        'POST|api/workspaces/{workspace}/menu/{menu}/ai-imports||App\Http\Controllers\Ai\StoreMenuAiImportController|api,auth:sanctum,throttle:6,1,verified',
        // TOPLU okuma (`docs/96` Faz 3): tek istek 10 fotoğrafa kadar dış
        // çağrı yapar, bu yüzden tekil yoldan daha sıkı hız sınırı taşır.
        'POST|api/workspaces/{workspace}/menu/{menu}/ai-imports/batch||App\Http\Controllers\Ai\StoreBulkMenuAiImportController|api,auth:sanctum,throttle:2,1,verified',
        // TOPLU ORKESTRA (FF-75).
        'POST|api/workspaces/{workspace}/menu/{menu}/ai-batches||App\Http\Controllers\Ai\StoreMenuAiBatchController|api,auth:sanctum,throttle:2,1,verified',
        'GET|api/workspaces/{workspace}/ai-batches/{batch}||App\Http\Controllers\Ai\ShowMenuAiBatchController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/ai-imports/batch/apply||App\Http\Controllers\Ai\ApplyBulkMenuAiImportController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/ai-imports/{artifact}||App\Http\Controllers\Ai\ShowMenuAiImportController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/ai-imports/{artifact}/apply||App\Http\Controllers\Ai\ApplyMenuAiImportController|api,auth:sanctum,throttle:10,1,verified',
        'POST|api/workspaces/{workspace}/menu-items/{menuItem}/description-drafts||App\Http\Controllers\Ai\StoreProductDescriptionDraftController|api,auth:sanctum,throttle:6,1,verified',
        'POST|api/workspaces/{workspace}/description-drafts/{artifact}/apply||App\Http\Controllers\Ai\ApplyProductDescriptionDraftController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/menu/duplicate-candidates||App\Http\Controllers\Ai\ShowDuplicateProductCandidatesController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/ai/availability||App\Http\Controllers\Ai\ShowAiAvailabilityController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-categories/{category}/item-order||App\Http\Controllers\MenuCatalog\ReorderMenuItemsController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu/{menu}/category-order||App\Http\Controllers\MenuCatalog\ReorderCategoriesController|api,auth:sanctum,verified',
        // MENÜ DENETİM İZİ (FF-163): FF-154/FF-156 izi yazıyordu ama okuyan
        // bir yüzey yoktu. Yetki `menu.manage` — fiyat geçmişi ticari bir
        // bilgidir ve Mutfak rolünün işi değil.
        'GET|api/workspaces/{workspace}/menu/audits||App\Http\Controllers\MenuCatalog\ListMenuAuditsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/publications||App\Http\Controllers\Publication\StorePublicationController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/menu/{menu}/publications/current||App\Http\Controllers\Publication\ShowCurrentPublicationController|api,auth:sanctum,verified',
        /*
            PLANLA — ZAMANLANMIŞ YAYIN (sahibin 2026-09-05 kararı; kanonik
            kaynak `panel.dc.html` Yayınlama ekranındaki "Planla" düğmesi).

            Bu üç imza donmuş listeye SIRASIYLA eklendi ve sıra anlamlıdır:
            `publications/schedule`, `publications` liste rotasından ÖNCE
            gelir. Ters sırada "schedule" kelimesi bir yayın kimliği sanılır
            ve sahip planını kurmak isterken bir yayın arar hâle gelirdi.

            Yetki: okuma `menu.view`, yazma ve iptal `menu.publish` —
            yayınlayamayan bir rol yayını ileri bir zamana da kuramaz.
        */
        'GET|api/workspaces/{workspace}/menu/{menu}/publications/schedule||App\Http\Controllers\Publication\ShowPublicationScheduleController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/publications/schedule||App\Http\Controllers\Publication\StorePublicationScheduleController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/menu/{menu}/publications/schedule/{schedule}||App\Http\Controllers\Publication\CancelPublicationScheduleController|api,auth:sanctum,verified',
        /*
            TELEFONDA ÖNİZLE (aynı karar). Burada YALNIZ imzalı adres
            üretilir; önizleme sayfasının kendisi `routes/web.php`
            içindedir ve oturum istemez — sahip onu telefonunda açar ve
            orada panele girmiş olması beklenemez. İmza yetkidir ve on beş
            dakika sonra ölür.
        */
        'POST|api/workspaces/{workspace}/menu/{menu}/draft-preview-link||App\Http\Controllers\Publication\CreateDraftPreviewLinkController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/menu/{menu}/publications||App\Http\Controllers\Publication\ListPublicationsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/publications/{publication}/restore||App\Http\Controllers\Publication\RestorePublicationController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand/locations/{location}/qr-codes||App\Http\Controllers\QrDestination\StoreQrCodeController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand/locations/{location}/tables/bulk||App\Http\Controllers\QrDestination\StoreBulkQrCodesController|api,auth:sanctum,throttle:5,1,verified',
        'GET|api/workspaces/{workspace}/brand/locations/{location}/qr-codes||App\Http\Controllers\QrDestination\ListQrCodesController|api,auth:sanctum,verified',
        // FF-123: salon bölümleri. Liste AYRI bir uçtur çünkü masası olmayan
        // bir bölüm QR kod listesinde hiç görünmez ve yeniden adlandırılamazdı.
        'GET|api/workspaces/{workspace}/brand/locations/{location}/dining-areas||App\Http\Controllers\QrDestination\ListDiningAreasController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/brand/locations/{location}/dining-areas/{area}||App\Http\Controllers\QrDestination\RenameDiningAreaController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/qr-codes/{qrCode}/disable||App\Http\Controllers\QrDestination\DisableQrCodeController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/qr-codes/{qrCode}/enable||App\Http\Controllers\QrDestination\EnableQrCodeController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/qr-codes/{qrCode}/destination||App\Http\Controllers\QrDestination\RetargetQrCodeController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/export.png||App\Http\Controllers\QrDestination\ExportQrCodePngController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/export.svg||App\Http\Controllers\QrDestination\ExportQrCodeSvgController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/export.pdf||App\Http\Controllers\QrDestination\ExportQrCodePdfController|api,auth:sanctum,verified',
        // FF-120: masaya konacak KART — tek kodun `export.pdf`'inden ayrı bir
        // çıktı. O, A4'ün ortasına konan çıplak bir kare; bu, kesilip
        // pleksiglasa girecek, marka kimliği taşıyan bir kart.
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/card.{format}||App\Http\Controllers\QrDestination\ExportQrCardController|api,auth:sanctum,verified',
        // FF-111: kesilip masalara dağıtılacak kart destesi. Kendi hız
        // sınırını taşır — her kart ayrı bir PNG üretir.
        'GET|api/workspaces/{workspace}/brand/locations/{location}/qr-codes/print.pdf||App\Http\Controllers\QrDestination\ExportQrPrintSheetController|api,auth:sanctum,throttle:10,1,verified',
        // FF-122: matbaaya giden toplu kart arşivi — deste PDF'i evde
        // kesilecek bir tabaka, bu ise her kartı ayrı dosya olarak veren bir
        // ZIP. Her kart ayrı bir render demek, kendi hız sınırını taşır.
        'GET|api/workspaces/{workspace}/brand/locations/{location}/qr-cards.zip||App\Http\Controllers\QrDestination\ExportQrCardsZipController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/brand/locations/{location}/analytics/summary||App\Http\Controllers\Analytics\ShowAnalyticsSummaryController|api,auth:sanctum,verified',
        // Markanın TAMAMI (`docs/68`): iki şubesi olan bir işletme bütünü
        // göremiyordu ve toplamı bulmak için şubeleri tek tek gezmek
        // zorundaydı.
        'GET|api/workspaces/{workspace}/analytics/summary||App\Http\Controllers\Analytics\ShowAnalyticsSummaryController|api,auth:sanctum,verified',
        // MENÜ MÜHENDİSLİĞİ (`docs/84`, P1-08): "menün 214 kez açıldı" menüyü
        // değiştirmek için hiçbir şey söylemiyordu.
        'GET|api/workspaces/{workspace}/analytics/menu-engineering||App\Http\Controllers\Analytics\ShowMenuEngineeringController|api,auth:sanctum,verified',
        /*
            ZAMAN SERİSİ (`docs/109` §1 Insights, §6.5).

            İki yol donduruluyor çünkü Insights ekranının çubuk+çizgi
            grafiği, saat ısı haritası ve şube halkası buradan besleniyor:
            aralık TOPLAMI bir haftanın şeklini gizliyordu ve "hangi gün
            çöktü", "öğle mi akşam mı", "geçen haftaya göre nasıl" soruları
            üründe hiç cevaplanamıyordu.

            İkili adres `summary` ile aynı gerekçeyi taşır (`docs/68`): üst
            çubuktaki "tüm şubeler" bağlamının analitikte de karşılığı olmalı.
        */
        'GET|api/workspaces/{workspace}/brand/locations/{location}/analytics/time-series||App\Http\Controllers\Analytics\ShowAnalyticsTimeSeriesController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/analytics/time-series||App\Http\Controllers\Analytics\ShowAnalyticsTimeSeriesController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/security/evidence/tenant-isolation||App\Http\Controllers\Security\ShowTenantIsolationEvidenceController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/security/evidence/backup-restore||App\Http\Controllers\Security\ShowBackupRestoreEvidenceController|api,auth:sanctum,verified',
        // FF-63 (`docs/98`): host yeteneği okuma ucu + insan tanıklıkları.
        'GET|api/workspaces/{workspace}/security/evidence/host-capability||App\Http\Controllers\Security\ShowHostCapabilityEvidenceController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/security/evidence/attestations/{key}||App\Http\Controllers\Security\ShowReleaseAttestationController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/entitlements||App\Http\Controllers\Entitlement\ShowWorkspaceEntitlementsController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/plans||App\Http\Controllers\Billing\ListPlansController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/subscription||App\Http\Controllers\Billing\ShowSubscriptionController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/ledger||App\Http\Controllers\Ledger\ShowWorkspaceLedgerController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/iyzico-sandbox/session||App\Http\Controllers\Billing\ShowIyzicoSandboxSessionController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/iyzico-sandbox/session||App\Http\Controllers\Billing\StoreIyzicoSandboxSessionController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media||App\Http\Controllers\Media\StoreMediaController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/media||App\Http\Controllers\Media\ListMediaController|api,auth:sanctum,verified',
        // Slot politikaları workspace'e bağlı DEĞİLDİR: ürünün kendi kuralları.
        'GET|api/media/slot-policies||App\Http\Controllers\Media\ListSlotPoliciesController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/media/{media}||App\Http\Controllers\Media\DeleteMediaController|api,auth:sanctum,verified',
        // SÜRÜMLER (`docs/49` Faz 3, FF-69): asıl değişmez, geçmiş silinmez.
        'GET|api/workspaces/{workspace}/media/{media}/versions||App\Http\Controllers\Media\ListMediaVersionsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/{media}/reprocess||App\Http\Controllers\Media\ReprocessMediaController|api,auth:sanctum,throttle:10,1,verified',
        'POST|api/workspaces/{workspace}/media/{media}/versions/{version}/restore||App\Http\Controllers\Media\RestoreMediaVersionController|api,auth:sanctum,verified',
        // KULLANIM GRAFİĞİ ve ÇÖP (`docs/49` Faz 4-5, FF-70).
        'GET|api/workspaces/{workspace}/media/{media}/usages||App\Http\Controllers\Media\ShowMediaUsagesController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/{media}/detach||App\Http\Controllers\Media\DetachMediaUsagesController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/{media}/restore||App\Http\Controllers\Media\RestoreMediaController|api,auth:sanctum,verified',
        'PATCH|api/workspaces/{workspace}/media/{media}||App\Http\Controllers\Media\UpdateMediaAltTextController|api,auth:sanctum,verified',
        // KOTA ve ASIL İNDİRME (FF-71).
        'GET|api/workspaces/{workspace}/media/quota||App\Http\Controllers\Media\ShowMediaQuotaController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/media/audits||App\Http\Controllers\Media\ListMediaAuditsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/{media}/download-link||App\Http\Controllers\Media\CreateOriginalDownloadLinkController|api,auth:sanctum,verified',
        /*
            KLASÖRLER (`docs/108` §3 madde 1). Bu beş yol donduruluyor
            çünkü kütüphane ekranı adreslerini doğrudan kuruyor: bir
            yeniden adlandırma, sahibin klasör ağacını sessizce kaybetmesi
            demek olurdu.

            Yollar `/media/folders` altında toplanıyor ve `/media/{media}`
            yollarıyla çakışmıyor: klasör yolları `folders` sabit
            segmentiyle başlıyor, varlık yolları ise her zaman bir alt
            segmentle (`/versions`, `/folder`, ...) devam ediyor.

            Hız sınırı YOK — okuma listeleme kadar ucuz, yazma tek satır
            günceller ve dış bir maliyet doğurmaz (karşılaştır:
            `reprocess` her çağrıda görsel işlediği için sınırlı).
        */
        'GET|api/workspaces/{workspace}/media/folders||App\Http\Controllers\Media\ListMediaFoldersController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/folders||App\Http\Controllers\Media\StoreMediaFolderController|api,auth:sanctum,verified',
        'PATCH|api/workspaces/{workspace}/media/folders/{folder}||App\Http\Controllers\Media\RenameMediaFolderController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/media/folders/{folder}||App\Http\Controllers\Media\DeleteMediaFolderController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/media/{media}/folder||App\Http\Controllers\Media\MoveMediaToFolderController|api,auth:sanctum,verified',
        /*
            GÖRÜNTÜLE (`docs/108` §3 madde 8, kaynak ekranı "Görüntüle").

            İki uç donduruluyor:

              - `media/{media}/viewer` ekrana HANGİ okuyucunun çizileceğini
                söyler. Ekran bunu kendi başına bilemez: listede dosyanın
                MIME türü yoktur ve uzantı yükleyenin denetimindedir.
              - `media/{media}/preview` dosyayı panelin İÇİNDE açılacak
                biçimde verir (`inline`), oysa var olan asıl indirme ucu
                dosyayı `attachment` olarak verir ve öyle vermek
                zorundadır.

            İkisi de hız sınırsızdır ve bu bilinçlidir: `preview` bir
            çerçeve isteğidir ve PDF'te her sayfa değişiminde tekrarlanır.

            Yollar `/media/{media}` altında bir alt segmentle devam ediyor;
            `media/folders`, `media/jobs` gibi sabit segmentli yollarla
            çakışmaz.
        */
        'GET|api/workspaces/{workspace}/media/{media}/viewer||App\Http\Controllers\Media\ShowMediaViewerController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/media/{media}/preview||App\Http\Controllers\Media\ServeMediaPreviewController|api,auth:sanctum,verified',
        /*
            BOYUT MOTORU ve KUYRUK (`docs/108` §3 madde 4-5, §6.1).

            Üç yol donduruluyor çünkü medya yöneticisinin iki bölümü
            adreslerini doğrudan kuruyor:

              - `media/derivative-rules` SALT OKUNUR: adlandırılmış türev
                kuralı + "kaç dosya etkilenir" istatistiği. Okumak tek bir
                dosyayı bile değiştirmediği için hız sınırı yok.
              - `media/reprocess` TOPLU yeniden üretimdir ve `throttle:2,1`
                taşır — tek varlık ucundan (`throttle:10,1`) DAHA SIKI,
                çünkü tek çağrı bütün hazır dosyaları işler. Aynı hız
                sınırını vermek, on kat işi on kat sık çalıştırmaya izin
                verirdi.
              - `media/jobs` kuyruğun salt okunur listesidir; "yeniden dene"
                var olan `media/{media}/reprocess` ucuna gider, kuyruğun
                kendi işleme hattı YOKTUR.

            Üçü de `folders` gibi sabit segmentle başlıyor; `/media/{media}`
            yolları her zaman bir alt segmentle devam ettiği için çakışma
            yok (`media/{media}/reprocess` üç segment, `media/reprocess`
            iki).
        */
        'GET|api/workspaces/{workspace}/media/derivative-rules||App\Http\Controllers\Media\ListDerivativeRulesController|api,auth:sanctum,verified',
        /*
            YER ve AYARLAR (`docs/108` §6.4-§6.6).

            İki yol daha donduruluyor çünkü medya yöneticisinin "Kota ve
            çöp" ile "Ayarlar" bölümleri adreslerini doğrudan kuruyor:

              - `media/storage-breakdown` SALT OKUNUR: "yeri ne dolduruyor?"
                sorusunun GERÇEK veriden gelen cevabı. Kotadan AYRI bir uç,
                çünkü kota durumu her yüklemede okunur
                (`MediaQuotaPort::admits`) ve kırılımı oraya eklemek her
                yüklemeye bir gruplama sorgusu daha bindirirdi. Okumak tek
                bir dosyayı bile değiştirmediği için hız sınırı yok.
              - `media/settings` SALT OKUNUR ve KAYDETME UCU YOKTUR: bu
                depoda dizin/ad/tarih deseni değiştirilemez, güvenlik
                önlemi kapatılamaz. Uç yalnız durumu bildirir; sahibin
                kararı (2026-09-05) uygulanmayan bir anahtarı çalışıyormuş
                gibi göstermeyi yasaklıyor.

            İkisi de `folders` gibi sabit segmentle başlıyor; `/media/{media}`
            yolları her zaman bir alt segmentle devam ettiği için çakışma yok.
        */
        'GET|api/workspaces/{workspace}/media/storage-breakdown||App\Http\Controllers\Media\ShowMediaStorageBreakdownController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/media/settings||App\Http\Controllers\Media\ShowMediaSettingsController|api,auth:sanctum,verified',
        /*
            OLGUNLUK (`docs/109-PANEL-V3.md` §2, kaynak ekranı "Olgunluk").

            GEREKÇE — bu yol neden donduruluyor: medya yöneticisinin
            "Olgunluk" bölümü adresini doğrudan kuruyor, tıpkı yanındaki
            `media/settings` gibi. Adres sessizce kayarsa bölüm boş açılır
            ve sahip bunu bir "henüz yapılmadı" sanır.

            SALT OKUNUR ve hız sınırsız: yönlendirici koleksiyonunu ve test
            paketini okur, hiçbir dosyaya dokunmaz, hiçbir satır yazmaz.
            Kiracı verisi de okumaz — ürünün kendi durumunu bildirir — ama
            adres bir kiracıya ait olduğu için yetki yine sorulur.

            `folders`/`settings` gibi sabit segmentle başlar; `/media/{media}`
            yolları her zaman bir alt segmentle devam ettiği için çakışma yok.
        */
        'GET|api/workspaces/{workspace}/media/maturity||App\Http\Controllers\Media\ShowMediaMaturityController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/reprocess||App\Http\Controllers\Media\ReprocessMediaBatchController|api,auth:sanctum,throttle:2,1,verified',
        'GET|api/workspaces/{workspace}/media/jobs||App\Http\Controllers\Media\ListMediaProcessingJobsController|api,auth:sanctum,verified',
        /*
            DÖNÜŞTÜR (`docs/108` §6.3, kaynak ekranı "Dönüştür").

            İki yol donduruluyor çünkü medya yöneticisinin "Dönüştür"
            bölümü adreslerini doğrudan kuruyor:

              - `media/conversion-targets` SALT OKUNUR: hedef listesi, her
                hedefin BU KURULUMDA desteklenip desteklenmediği,
                seçilebilir dosyalar ve gerçekten tartılmış kazanç. Okumak
                tek bir dosyayı bile değiştirmediği için hız sınırı yok.
              - `media/convert` toplu yeniden üretimle AYNI sınırı taşır
                (`throttle:2,1`): tek çağrı onlarca dosyayı kodlar ve AVIF
                kodlaması JPEG'den belirgin biçimde yavaştır. Kendi işleme
                hattı YOKTUR — var olan `ReprocessMediaAsset` bir hedef
                biçimle çağrılır.

            İkisi de sabit segmentle başlıyor; `/media/{media}` yolları her
            zaman bir alt segmentle devam ettiği için çakışma yok.
        */
        'GET|api/workspaces/{workspace}/media/conversion-targets||App\Http\Controllers\Media\ListConversionTargetsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/media/convert||App\Http\Controllers\Media\ConvertMediaController|api,auth:sanctum,throttle:2,1,verified',
        /*
            TOPLU İŞLEM ve YÖNETİŞİM (`docs/109-PANEL-V3.md` §2, kanonik
            kaynak `docs/reference/panel-v3/MedyaModulu.dc.html`).

            Dört yeni uç, dört ayrı gerekçe:

              - `media/bulk/plan` KURU ÇALIŞMADIR ve hiçbir dosyaya
                dokunmaz. `POST` olması bir çelişki değil GÖVDE
                gerekliliğidir: dondurulmuş kapsam bin kimlikten oluşabilir
                ve bin kimliği adres satırına yazmak hem sınırı aşar hem de
                kimlikleri sunucu günlüklerine döker. Sınırı gevşektir
                (`throttle:20,1`) çünkü yalnız okur — ama sıfır değildir,
                yoksa ekran her tuş vuruşunda planı yeniden isteyebilirdi.
              - `media/bulk/run` YENİ BİR İŞLEME HATTI AÇMAZ: var olan
                `ReprocessMediaAsset`, klasör taşıma, çöp ve kalıcı silme
                yollarını sırayla çağırır. Sınırı dönüştürmeyle AYNIDIR
                (`throttle:2,1`): tek çağrı yüzlerce dosyaya dokunur.
              - `media/governance` SALT OKUNURDUR ve hız sınırsızdır:
                yetki matrisini, saklama sayılarını ve denetim izini okur,
                tek bir dosya bile işlemez.
              - `{media}/legal-hold` tek bir dosyanın kilididir. `PUT`,
                çünkü aynı çağrı kilidi koyar da kaldırır da (`reason:
                null`); iki ayrı uç, aynı durumu iki yerden değiştirmek
                olurdu.

            `bulk` ve `governance` sabit segmentle başlar; `/media/{media}`
            yolları her zaman bir alt segmentle devam ettiği için çakışma
            yok.
        */
        'POST|api/workspaces/{workspace}/media/bulk/plan||App\Http\Controllers\Media\PlanMediaBulkOperationController|api,auth:sanctum,throttle:20,1,verified',
        'POST|api/workspaces/{workspace}/media/bulk/run||App\Http\Controllers\Media\RunMediaBulkOperationController|api,auth:sanctum,throttle:2,1,verified',
        'GET|api/workspaces/{workspace}/media/governance||App\Http\Controllers\Media\ShowMediaGovernanceController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/media/{media}/legal-hold||App\Http\Controllers\Media\UpdateMediaLegalHoldController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/team/members||App\Http\Controllers\Team\ListTeamMembersController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/team/members/{member}||App\Http\Controllers\Team\RemoveTeamMemberController|api,auth:sanctum,throttle:5,1,verified',
        'POST|api/workspaces/{workspace}/team/members/{member}/transfer-ownership||App\Http\Controllers\Team\TransferWorkspaceOwnershipController|api,auth:sanctum,throttle:5,1,verified',
        // Yanlış verilmiş bir rolü düzeltmek, üyeyi silip yeniden davet
        // etmeyi gerektirmemeli (`docs/83`).
        'PUT|api/workspaces/{workspace}/team/members/{member}/role||App\Http\Controllers\Team\UpdateTeamMemberRoleController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/team/invitations||App\Http\Controllers\Team\ListTeamInvitationsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/team/invitations||App\Http\Controllers\Team\StoreTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        'DELETE|api/workspaces/{workspace}/team/invitations/{invitation}||App\Http\Controllers\Team\CancelTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        // FF-160 (`docs/110` P0-06): e-postası çıkmayan bir davet için sahibin
        // elinde bir hamle olmalı. Sınır kardeşleriyle AYNI (`throttle:5,1`).
        'POST|api/workspaces/{workspace}/team/invitations/{invitation}/resend||App\Http\Controllers\Team\ResendTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        'POST|api/invitations/accept/{token}||App\Http\Controllers\Team\AcceptTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        /*
            DENETİM İZİ (FF-132) — medya izinden AYRI bir uç ve bu bilinçli:
            iz iki kaynağı birleştirir (medya + yayın) ve yarın üçüncüsü
            eklenebilir. Medya modülünün altına konsaydı, medyanın parçası
            sanılırdı. Sıra dosya yükleme sırasını izler: `team.php`den sonra.
        */
        'GET|api/workspaces/{workspace}/audit-trail||App\Http\Controllers\Workspace\ShowWorkspaceAuditTrailController|api,auth:sanctum,verified',
        'GET|api/admin/plans||App\Http\Controllers\PlatformAdmin\ListManagedPlansController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/plans||App\Http\Controllers\PlatformAdmin\StoreManagedPlanController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/plans/{plan}/activate||App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'GET|api/admin/workspaces||App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'GET|api/admin/workspaces/{workspace}/subscription||App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/workspaces/{workspace}/manual-payments||App\Http\Controllers\PlatformAdmin\StoreManualPaymentController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:5,1,verified',
        'GET|api/admin/ai/audit||App\Http\Controllers\PlatformAdmin\ShowAiAuditController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        // MODÜL ENVANTERİ (`docs/111` adım 1) — salt okunur, superadmin
        // arkasında, throttle'sız. Yazma ucu YOK ve bu listede bir gün
        // `POST|api/admin/modules/...` belirirse, o bir kapsam kararıdır:
        // modül açma/kapama bugün hiçbir yerde modellenmiş değil (§5.1).
        'GET|api/admin/modules||App\Http\Controllers\PlatformAdmin\ListCoreModulesController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/release-attestations||App\Http\Controllers\PlatformAdmin\StoreReleaseAttestationController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:20,1,verified',
        'GET|api/admin/credentials||App\Http\Controllers\PlatformAdmin\ListProviderCredentialsController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'PUT|api/admin/credentials/{provider}||App\Http\Controllers\PlatformAdmin\StoreProviderCredentialController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:20,1,verified',
        'POST|api/admin/credentials/{provider}/disable||App\Http\Controllers\PlatformAdmin\DisableProviderCredentialController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:20,1,verified',
        // ÇOK-BAĞLANTI YÜZEYİ (`docs/95` Faz 3). Üstteki sağlayıcı-düzeyi
        // uçlar kaldırılmadı — onlar aynı verinin "varsayılan bağlantı"
        // kısayolu. Silme ucu bilerek yok: kapatmak silmek değildir.
        'GET|api/admin/connections||App\Http\Controllers\PlatformAdmin\ListProviderConnectionsController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/connections||App\Http\Controllers\PlatformAdmin\StoreProviderConnectionController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:20,1,verified',
        'PUT|api/admin/connections/{connection}||App\Http\Controllers\PlatformAdmin\UpdateProviderConnectionController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:20,1,verified',
        // Uyumluluk yoklaması (`docs/95` Faz 3): dışarıya gerçek bir ağ
        // çağrısı yaptığı için daha sıkı hız sınırı taşır.
        'POST|api/admin/connections/{connection}/probe||App\Http\Controllers\PlatformAdmin\ProbeProviderConnectionController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:10,1,verified',
        'POST|api/admin/connections/{connection}/{state}||App\Http\Controllers\PlatformAdmin\SetProviderConnectionStateController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:20,1,verified',
        /*
            RESTORAN TARAFININ SİPARİŞ YOLLARI (FF-179, `docs/115` S4/S5/S6).

            Altı imza, üç ekran: garson kuyruğu, mutfak monitörü, sipariş
            ayarları ve geçmiş. Hepsi `auth:sanctum,verified` altında.

            MİSAFİRİN GÖNDERME UCU BU LİSTEDE YOKTUR ve olmamalı: o yol
            oturum açmaz, masasını karekoddan çözer ve `routes/web.php`
            üzerinden gider. Aynı dosyaya konsaydı, bu gruba bir gün eklenen
            `auth:sanctum` masadaki hiç kimsenin sipariş verememesiyle
            sonuçlanırdı.
        */
        'GET|api/workspaces/{workspace}/locations/{location}/orders/pending||App\Http\Controllers\Ordering\ListPendingOrdersController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/locations/{location}/orders/kitchen||App\Http\Controllers\Ordering\ListKitchenOrdersController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/locations/{location}/orders/history||App\Http\Controllers\Ordering\ListOrderHistoryController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/locations/{location}/orders/{order}/status||App\Http\Controllers\Ordering\ChangeOrderStatusController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/locations/{location}/ordering||App\Http\Controllers\Ordering\ShowOrderingSwitchController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/locations/{location}/ordering||App\Http\Controllers\Ordering\UpdateOrderingSwitchController|api,auth:sanctum,verified',
        /*
            PUAN YÜZEYİ (`docs/116` P5/P6).

            ÜÇ YOL VAR VE ÜÇÜ DE PUANI SİLMEZ. Listede `DELETE .../ratings`
            diye bir satır YOKTUR ve bu bir eksiklik değil: sahip puana
            yanıt verebilir, kaldıramaz (`docs/116` §4). `/reply` altındaki
            PUT ve DELETE sahibin KENDİ cümlesine dokunur, kimsenin ölçümüne
            değil — donmuş imza listesi bu ayrımı da donduruyor.

            Misafirin oy verme ucu burada YOKTUR, sipariş ucuyla aynı
            gerekçeyle: o yol oturum açmaz ve `routes/web.php`'den gider.
        */
        'GET|api/workspaces/{workspace}/menus/{menu}/ratings||App\Http\Controllers\Rating\ListMenuRatingsController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/ratings/products/{product}/reply||App\Http\Controllers\Rating\UpdateRatingReplyController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/ratings/products/{product}/reply||App\Http\Controllers\Rating\DeleteRatingReplyController|api,auth:sanctum,verified',
    ];

    /**
     * Expected per-domain module files that routes/api.php must delegate to
     * once the flat route file is split into a modular structure. None of
     * these exist yet — the flat routes/api.php still defines every route
     * inline, so this test intentionally fails (RED) until the modular
     * extraction lands.
     */
    private const EXPECTED_MODULE_FILES = [
        'routes/api/auth.php',
        'routes/api/webhooks.php',
        // Marka formunun referans verisi (ülke, saat dilimi, para birimi).
        // Kiracıya bağlı olmadığı için tenancy'den önce yükleniyor.
        'routes/api/reference.php',
        'routes/api/tenancy.php',
        'routes/api/menu-catalog.php',
        'routes/api/publication.php',
        'routes/api/qr-destination.php',
        'routes/api/analytics.php',
        'routes/api/security.php',
        'routes/api/billing.php',
        'routes/api/media.php',
        'routes/api/team.php',
        'routes/api/workspace-audit.php',
        'routes/api/platform-admin.php',
        /*
            SİPARİŞ YÜZEYİ (FF-179, `docs/115` S4/S5/S6). Listenin SONUNDA ve
            bilerek: kuyruk, mutfak monitörü ve şalter mevcut hiçbir yolu
            gölgelemez, dolayısıyla daha erken yüklenmeleri için bir sebep
            yok — ve sona eklemek dondurulmuş imza listesini ortasından
            kaydırmaz.
        */
        'routes/api/ordering.php',
        /*
            PUAN YÜZEYİ (`docs/116` P5/P6). Sipariş dosyasıyla aynı gerekçe
            listenin sonunda tutuyor: hiçbir mevcut yolu gölgelemiyor ve
            sona eklemek dondurulmuş imza listesini ortasından kaydırmıyor.
        */
        'routes/api/rating.php',
    ];

    #[Test]
    public function module_route_files_are_expected_and_exist(): void
    {
        foreach (self::EXPECTED_MODULE_FILES as $relativePath) {
            $this->assertFileExists(
                base_path($relativePath),
                "Expected modular route file [{$relativePath}] to exist under routes/api/.",
            );
        }
    }

    #[Test]
    public function api_loader_has_explicit_ordered_requires_and_no_controller_imports(): void
    {
        $loaderPath = base_path('routes/api.php');
        $this->assertFileExists($loaderPath);

        $contents = file_get_contents($loaderPath);
        $this->assertNotFalse($contents);

        preg_match_all('/require\s+(?:__DIR__\s*\.\s*)?[\'"]([^\'"]+)[\'"]\s*;/', $contents, $matches);
        $requiredPaths = $matches[1] ?? [];

        $this->assertNotEmpty(
            $requiredPaths,
            'routes/api.php must be a thin loader that explicitly requires each modular route file.',
        );

        $normalizedRequires = array_map(
            static fn (string $path): string => ltrim(str_replace('/api/', 'api/', $path), './'),
            $requiredPaths,
        );

        $expectedOrder = array_map(
            static fn (string $path): string => str_replace('routes/', '', $path),
            self::EXPECTED_MODULE_FILES,
        );

        $this->assertSame(
            $expectedOrder,
            $normalizedRequires,
            'routes/api.php must require every expected module file, in the same fixed order every time.',
        );

        $this->assertDoesNotMatchRegularExpression(
            '/use\s+App\\\\Http\\\\Controllers\\\\/',
            $contents,
            'routes/api.php must be a thin loader with no direct controller imports; controller wiring belongs to the per-domain modules.',
        );
    }

    #[Test]
    public function frozen_api_route_signatures_match_current_behaviour(): void
    {
        $signatures = $this->registeredApiRouteSignatures();

        $this->assertSame(self::FROZEN_ROUTE_SIGNATURES, $signatures);
    }

    #[Test]
    public function public_billing_callback_and_webhook_routes_have_no_auth_middleware(): void
    {
        $publicPaths = [
            'api/webhooks/iyzico-sandbox',
            'api/billing/iyzico-sandbox/callback',
        ];

        foreach ($publicPaths as $uri) {
            $route = Route::getRoutes()->match(
                Request::create('/'.$uri, 'POST'),
            );

            $middleware = $route->gatherMiddleware();

            $this->assertNotContains('auth:sanctum', $middleware, "[{$uri}] must remain publicly reachable without auth:sanctum.");
            $this->assertNotContains('verified', $middleware, "[{$uri}] must remain publicly reachable without email verification.");
        }
    }

    #[Test]
    public function protected_throttled_and_platform_admin_routes_keep_their_middleware_boundary(): void
    {
        $protected = Route::getRoutes()->match(
            Request::create('/api/workspaces', 'GET'),
        );
        $this->assertContains('auth:sanctum', $protected->gatherMiddleware());
        $this->assertContains('verified', $protected->gatherMiddleware());

        $throttled = Route::getRoutes()->match(
            Request::create('/api/workspaces', 'POST'),
        );
        $this->assertContains('throttle:5,1', $throttled->gatherMiddleware());
        $this->assertContains('auth:sanctum', $throttled->gatherMiddleware());

        $platformAdmin = Route::getRoutes()->match(
            Request::create('/api/admin/plans', 'GET'),
        );
        $this->assertContains('App\Http\Middleware\EnsurePlatformSuperAdmin', $platformAdmin->gatherMiddleware());
        $this->assertContains('auth:sanctum', $platformAdmin->gatherMiddleware());
        $this->assertContains('verified', $platformAdmin->gatherMiddleware());
    }

    /**
     * @return list<string>
     */
    private function registeredApiRouteSignatures(): array
    {
        $signatures = [];

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();

            if (! str_starts_with($uri, 'api/')) {
                continue;
            }

            $method = $route->methods()[0] ?? 'GET';
            if ($method === 'HEAD') {
                continue;
            }

            $middleware = $route->gatherMiddleware();
            sort($middleware);

            $name = $route->getName() ?? '';
            $action = $route->getActionName();

            $signatures[] = sprintf('%s|%s|%s|%s|%s', $method, $uri, $name, $action, implode(',', $middleware));
        }

        return $signatures;
    }
}
