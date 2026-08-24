<?php

declare(strict_types=1);

use App\Http\Controllers\QrDestination\DisableQrCodeController;
use App\Http\Controllers\QrDestination\ExportQrCodePdfController;
use App\Http\Controllers\QrDestination\ExportQrCodePngController;
use App\Http\Controllers\QrDestination\ExportQrCodeSvgController;
use App\Http\Controllers\QrDestination\ListQrCodesController;
use App\Http\Controllers\QrDestination\StoreBulkQrCodesController;
use App\Http\Controllers\QrDestination\StoreQrCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/brand/locations/{location}/qr-codes', StoreQrCodeController::class);
    Route::post('/workspaces/{workspace}/brand/locations/{location}/tables/bulk', StoreBulkQrCodesController::class)->middleware('throttle:5,1');
    Route::get('/workspaces/{workspace}/brand/locations/{location}/qr-codes', ListQrCodesController::class);
    Route::put('/workspaces/{workspace}/qr-codes/{qrCode}/disable', DisableQrCodeController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.png', ExportQrCodePngController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.svg', ExportQrCodeSvgController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.pdf', ExportQrCodePdfController::class);
});
