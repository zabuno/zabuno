<?php

declare(strict_types=1);

use App\Http\Controllers\Security\ShowBackupRestoreEvidenceController;
use App\Http\Controllers\Security\ShowHostCapabilityEvidenceController;
use App\Http\Controllers\Security\ShowReleaseAttestationController;
use App\Http\Controllers\Security\ShowTenantIsolationEvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/security/evidence/tenant-isolation', ShowTenantIsolationEvidenceController::class);
    Route::get('/workspaces/{workspace}/security/evidence/backup-restore', ShowBackupRestoreEvidenceController::class);
    // FF-63 (`docs/98`): host yeteneği kanıtı 2026-08-26'dan beri yazılıyordu,
    // okuyan uç hiç olmamıştı. İnsan tanıklıkları (QR saha taraması, RPO/RTO
    // kararı, ASVS raporu) aynı okuma sözleşmesiyle, `kind` ayrımıyla.
    Route::get('/workspaces/{workspace}/security/evidence/host-capability', ShowHostCapabilityEvidenceController::class);
    Route::get('/workspaces/{workspace}/security/evidence/attestations/{key}', ShowReleaseAttestationController::class);
});
