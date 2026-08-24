<?php

declare(strict_types=1);

use App\Http\Controllers\Security\ShowBackupRestoreEvidenceController;
use App\Http\Controllers\Security\ShowTenantIsolationEvidenceController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::get('/workspaces/{workspace}/security/evidence/tenant-isolation', ShowTenantIsolationEvidenceController::class);
    Route::get('/workspaces/{workspace}/security/evidence/backup-restore', ShowBackupRestoreEvidenceController::class);
});
