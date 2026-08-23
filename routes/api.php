<?php

declare(strict_types=1);

use App\Http\Controllers\Analytics\ShowAnalyticsSummaryController;
use App\Http\Controllers\Auth\AuthenticatedUserController;
use App\Http\Controllers\Billing\ListPlansController;
use App\Http\Controllers\Billing\ReceiveIyzicoSandboxCallbackController;
use App\Http\Controllers\Billing\ReceiveIyzicoSandboxWebhookController;
use App\Http\Controllers\Billing\ShowIyzicoSandboxSessionController;
use App\Http\Controllers\Billing\ShowSubscriptionController;
use App\Http\Controllers\Billing\StoreIyzicoSandboxSessionController;
use App\Http\Controllers\Media\DeleteMediaController;
use App\Http\Controllers\Media\ListMediaController;
use App\Http\Controllers\Media\StoreMediaController;
use App\Http\Controllers\MenuCatalog\ShowMenuController;
use App\Http\Controllers\MenuCatalog\StoreCategoryController;
use App\Http\Controllers\MenuCatalog\StoreMenuController;
use App\Http\Controllers\MenuCatalog\StoreMenuItemController;
use App\Http\Controllers\MenuCatalog\StoreProductController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemAllergensController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemPriceController;
use App\Http\Controllers\MenuCatalog\UpdateMenuItemVisibilityController;
use App\Http\Controllers\PlatformAdmin\ActivateManagedPlanController;
use App\Http\Controllers\PlatformAdmin\ListManagedPlansController;
use App\Http\Controllers\PlatformAdmin\ListManagedWorkspacesController;
use App\Http\Controllers\PlatformAdmin\ShowManagedSubscriptionController;
use App\Http\Controllers\PlatformAdmin\StoreManagedPlanController;
use App\Http\Controllers\PlatformAdmin\StoreManualPaymentController;
use App\Http\Controllers\Publication\ShowCurrentPublicationController;
use App\Http\Controllers\Publication\StorePublicationController;
use App\Http\Controllers\QrDestination\DisableQrCodeController;
use App\Http\Controllers\QrDestination\ExportQrCodePdfController;
use App\Http\Controllers\QrDestination\ExportQrCodePngController;
use App\Http\Controllers\QrDestination\ExportQrCodeSvgController;
use App\Http\Controllers\QrDestination\ListQrCodesController;
use App\Http\Controllers\QrDestination\StoreBulkQrCodesController;
use App\Http\Controllers\QrDestination\StoreQrCodeController;
use App\Http\Controllers\Security\ShowBackupRestoreEvidenceController;
use App\Http\Controllers\Security\ShowTenantIsolationEvidenceController;
use App\Http\Controllers\Team\AcceptTeamInvitationController;
use App\Http\Controllers\Team\CancelTeamInvitationController;
use App\Http\Controllers\Team\ListTeamInvitationsController;
use App\Http\Controllers\Team\ListTeamMembersController;
use App\Http\Controllers\Team\RemoveTeamMemberController;
use App\Http\Controllers\Team\StoreTeamInvitationController;
use App\Http\Controllers\Tenancy\CreateWorkspaceController;
use App\Http\Controllers\Tenancy\CurrentWorkspaceContextController;
use App\Http\Controllers\Tenancy\ListLocationsController;
use App\Http\Controllers\Tenancy\ListWorkspacesController;
use App\Http\Controllers\Tenancy\ShowBrandController;
use App\Http\Controllers\Tenancy\ShowLocationController;
use App\Http\Controllers\Tenancy\StoreBrandController;
use App\Http\Controllers\Tenancy\StoreLocationController;
use App\Http\Controllers\Tenancy\SwitchWorkspaceContextController;
use App\Http\Controllers\Tenancy\UpdateBrandController;
use App\Http\Controllers\Tenancy\UpdateLocationController;
use App\Http\Middleware\EnsurePlatformSuperAdmin;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->get('/user', AuthenticatedUserController::class);

Route::post('/webhooks/iyzico-sandbox', ReceiveIyzicoSandboxWebhookController::class);
Route::post('/billing/iyzico-sandbox/callback', ReceiveIyzicoSandboxCallbackController::class);

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces', CreateWorkspaceController::class)->middleware('throttle:5,1');
    Route::get('/workspaces', ListWorkspacesController::class);
    Route::put('/workspace-context', SwitchWorkspaceContextController::class);
    Route::get('/workspace-context', CurrentWorkspaceContextController::class);

    Route::post('/workspaces/{workspace}/brand', StoreBrandController::class);
    Route::get('/workspaces/{workspace}/brand', ShowBrandController::class);
    Route::put('/workspaces/{workspace}/brand', UpdateBrandController::class);

    Route::post('/workspaces/{workspace}/brand/locations', StoreLocationController::class);
    Route::get('/workspaces/{workspace}/brand/locations', ListLocationsController::class);
    Route::get('/workspaces/{workspace}/brand/locations/{location}', ShowLocationController::class);
    Route::put('/workspaces/{workspace}/brand/locations/{location}', UpdateLocationController::class);

    Route::post('/workspaces/{workspace}/brand/locations/{location}/menu', StoreMenuController::class);
    Route::get('/workspaces/{workspace}/brand/locations/{location}/menu', ShowMenuController::class);
    Route::post('/workspaces/{workspace}/menu/{menu}/categories', StoreCategoryController::class);
    Route::post('/workspaces/{workspace}/menu-categories/{category}/products', StoreProductController::class);
    Route::post('/workspaces/{workspace}/menu-categories/{category}/menu-items', StoreMenuItemController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/allergens', UpdateMenuItemAllergensController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/price', UpdateMenuItemPriceController::class);
    Route::put('/workspaces/{workspace}/menu-items/{menuItem}/visibility', UpdateMenuItemVisibilityController::class);

    Route::post('/workspaces/{workspace}/menu/{menu}/publications', StorePublicationController::class);
    Route::get('/workspaces/{workspace}/menu/{menu}/publications/current', ShowCurrentPublicationController::class);

    Route::post('/workspaces/{workspace}/brand/locations/{location}/qr-codes', StoreQrCodeController::class);
    Route::post('/workspaces/{workspace}/brand/locations/{location}/tables/bulk', StoreBulkQrCodesController::class)->middleware('throttle:5,1');
    Route::get('/workspaces/{workspace}/brand/locations/{location}/qr-codes', ListQrCodesController::class);
    Route::put('/workspaces/{workspace}/qr-codes/{qrCode}/disable', DisableQrCodeController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.png', ExportQrCodePngController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.svg', ExportQrCodeSvgController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.pdf', ExportQrCodePdfController::class);

    Route::get('/workspaces/{workspace}/brand/locations/{location}/analytics/summary', ShowAnalyticsSummaryController::class);

    Route::get('/workspaces/{workspace}/security/evidence/tenant-isolation', ShowTenantIsolationEvidenceController::class);
    Route::get('/workspaces/{workspace}/security/evidence/backup-restore', ShowBackupRestoreEvidenceController::class);

    Route::get('/workspaces/{workspace}/plans', ListPlansController::class);
    Route::get('/workspaces/{workspace}/subscription', ShowSubscriptionController::class);

    Route::get('/workspaces/{workspace}/iyzico-sandbox/session', ShowIyzicoSandboxSessionController::class);
    Route::post('/workspaces/{workspace}/iyzico-sandbox/session', StoreIyzicoSandboxSessionController::class);

    Route::post('/workspaces/{workspace}/media', StoreMediaController::class);
    Route::get('/workspaces/{workspace}/media', ListMediaController::class);
    Route::delete('/workspaces/{workspace}/media/{media}', DeleteMediaController::class);

    Route::get('/workspaces/{workspace}/team/members', ListTeamMembersController::class);
    Route::delete('/workspaces/{workspace}/team/members/{member}', RemoveTeamMemberController::class)->middleware('throttle:5,1');

    Route::get('/workspaces/{workspace}/team/invitations', ListTeamInvitationsController::class);
    Route::post('/workspaces/{workspace}/team/invitations', StoreTeamInvitationController::class)->middleware('throttle:5,1');
    Route::delete('/workspaces/{workspace}/team/invitations/{invitation}', CancelTeamInvitationController::class)->middleware('throttle:5,1');
    Route::post('/invitations/accept/{token}', AcceptTeamInvitationController::class)->middleware('throttle:5,1');

    Route::middleware(EnsurePlatformSuperAdmin::class)->group(function () {
        Route::get('/admin/plans', ListManagedPlansController::class);
        Route::post('/admin/plans', StoreManagedPlanController::class);
        Route::post('/admin/plans/{plan}/activate', ActivateManagedPlanController::class);

        Route::get('/admin/workspaces', ListManagedWorkspacesController::class);
        Route::get('/admin/workspaces/{workspace}/subscription', ShowManagedSubscriptionController::class);
        Route::post('/admin/workspaces/{workspace}/manual-payments', StoreManualPaymentController::class)->middleware('throttle:5,1');
    });
});
