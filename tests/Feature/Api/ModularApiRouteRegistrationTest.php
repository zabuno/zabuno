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
        'POST|api/workspaces/{workspace}/ai-imports/batch/apply||App\Http\Controllers\Ai\ApplyBulkMenuAiImportController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/ai-imports/{artifact}||App\Http\Controllers\Ai\ShowMenuAiImportController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/ai-imports/{artifact}/apply||App\Http\Controllers\Ai\ApplyMenuAiImportController|api,auth:sanctum,throttle:10,1,verified',
        'POST|api/workspaces/{workspace}/menu-items/{menuItem}/description-drafts||App\Http\Controllers\Ai\StoreProductDescriptionDraftController|api,auth:sanctum,throttle:6,1,verified',
        'POST|api/workspaces/{workspace}/description-drafts/{artifact}/apply||App\Http\Controllers\Ai\ApplyProductDescriptionDraftController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/menu/duplicate-candidates||App\Http\Controllers\Ai\ShowDuplicateProductCandidatesController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/ai/availability||App\Http\Controllers\Ai\ShowAiAvailabilityController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu-categories/{category}/item-order||App\Http\Controllers\MenuCatalog\ReorderMenuItemsController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/menu/{menu}/category-order||App\Http\Controllers\MenuCatalog\ReorderCategoriesController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/publications||App\Http\Controllers\Publication\StorePublicationController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/menu/{menu}/publications/current||App\Http\Controllers\Publication\ShowCurrentPublicationController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/menu/{menu}/publications||App\Http\Controllers\Publication\ListPublicationsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/menu/{menu}/publications/{publication}/restore||App\Http\Controllers\Publication\RestorePublicationController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand/locations/{location}/qr-codes||App\Http\Controllers\QrDestination\StoreQrCodeController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/brand/locations/{location}/tables/bulk||App\Http\Controllers\QrDestination\StoreBulkQrCodesController|api,auth:sanctum,throttle:5,1,verified',
        'GET|api/workspaces/{workspace}/brand/locations/{location}/qr-codes||App\Http\Controllers\QrDestination\ListQrCodesController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/qr-codes/{qrCode}/disable||App\Http\Controllers\QrDestination\DisableQrCodeController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/qr-codes/{qrCode}/enable||App\Http\Controllers\QrDestination\EnableQrCodeController|api,auth:sanctum,verified',
        'PUT|api/workspaces/{workspace}/qr-codes/{qrCode}/destination||App\Http\Controllers\QrDestination\RetargetQrCodeController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/export.png||App\Http\Controllers\QrDestination\ExportQrCodePngController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/export.svg||App\Http\Controllers\QrDestination\ExportQrCodeSvgController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/qr-codes/{qrCode}/export.pdf||App\Http\Controllers\QrDestination\ExportQrCodePdfController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/brand/locations/{location}/analytics/summary||App\Http\Controllers\Analytics\ShowAnalyticsSummaryController|api,auth:sanctum,verified',
        // Markanın TAMAMI (`docs/68`): iki şubesi olan bir işletme bütünü
        // göremiyordu ve toplamı bulmak için şubeleri tek tek gezmek
        // zorundaydı.
        'GET|api/workspaces/{workspace}/analytics/summary||App\Http\Controllers\Analytics\ShowAnalyticsSummaryController|api,auth:sanctum,verified',
        // MENÜ MÜHENDİSLİĞİ (`docs/84`, P1-08): "menün 214 kez açıldı" menüyü
        // değiştirmek için hiçbir şey söylemiyordu.
        'GET|api/workspaces/{workspace}/analytics/menu-engineering||App\Http\Controllers\Analytics\ShowMenuEngineeringController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/security/evidence/tenant-isolation||App\Http\Controllers\Security\ShowTenantIsolationEvidenceController|api,auth:sanctum,verified',
        'GET|api/workspaces/{workspace}/security/evidence/backup-restore||App\Http\Controllers\Security\ShowBackupRestoreEvidenceController|api,auth:sanctum,verified',
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
        'GET|api/workspaces/{workspace}/team/members||App\Http\Controllers\Team\ListTeamMembersController|api,auth:sanctum,verified',
        'DELETE|api/workspaces/{workspace}/team/members/{member}||App\Http\Controllers\Team\RemoveTeamMemberController|api,auth:sanctum,throttle:5,1,verified',
        'POST|api/workspaces/{workspace}/team/members/{member}/transfer-ownership||App\Http\Controllers\Team\TransferWorkspaceOwnershipController|api,auth:sanctum,throttle:5,1,verified',
        // Yanlış verilmiş bir rolü düzeltmek, üyeyi silip yeniden davet
        // etmeyi gerektirmemeli (`docs/83`).
        'PUT|api/workspaces/{workspace}/team/members/{member}/role||App\Http\Controllers\Team\UpdateTeamMemberRoleController|api,auth:sanctum,throttle:10,1,verified',
        'GET|api/workspaces/{workspace}/team/invitations||App\Http\Controllers\Team\ListTeamInvitationsController|api,auth:sanctum,verified',
        'POST|api/workspaces/{workspace}/team/invitations||App\Http\Controllers\Team\StoreTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        'DELETE|api/workspaces/{workspace}/team/invitations/{invitation}||App\Http\Controllers\Team\CancelTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        'POST|api/invitations/accept/{token}||App\Http\Controllers\Team\AcceptTeamInvitationController|api,auth:sanctum,throttle:5,1,verified',
        'GET|api/admin/plans||App\Http\Controllers\PlatformAdmin\ListManagedPlansController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/plans||App\Http\Controllers\PlatformAdmin\StoreManagedPlanController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/plans/{plan}/activate||App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'GET|api/admin/workspaces||App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'GET|api/admin/workspaces/{workspace}/subscription||App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,verified',
        'POST|api/admin/workspaces/{workspace}/manual-payments||App\Http\Controllers\PlatformAdmin\StoreManualPaymentController|App\Http\Middleware\EnsurePlatformSuperAdmin,api,auth:sanctum,throttle:5,1,verified',
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
        'routes/api/platform-admin.php',
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
