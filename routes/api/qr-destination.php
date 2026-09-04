<?php

declare(strict_types=1);

use App\Http\Controllers\QrDestination\DisableQrCodeController;
use App\Http\Controllers\QrDestination\EnableQrCodeController;
use App\Http\Controllers\QrDestination\ExportQrCardController;
use App\Http\Controllers\QrDestination\ExportQrCodePdfController;
use App\Http\Controllers\QrDestination\ExportQrCodePngController;
use App\Http\Controllers\QrDestination\ExportQrCodeSvgController;
use App\Http\Controllers\QrDestination\ExportQrPrintSheetController;
use App\Http\Controllers\QrDestination\ListQrCodesController;
use App\Http\Controllers\QrDestination\RetargetQrCodeController;
use App\Http\Controllers\QrDestination\StoreBulkQrCodesController;
use App\Http\Controllers\QrDestination\StoreQrCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'verified'])->group(function () {
    Route::post('/workspaces/{workspace}/brand/locations/{location}/qr-codes', StoreQrCodeController::class);
    Route::post('/workspaces/{workspace}/brand/locations/{location}/tables/bulk', StoreBulkQrCodesController::class)->middleware('throttle:5,1');
    Route::get('/workspaces/{workspace}/brand/locations/{location}/qr-codes', ListQrCodesController::class);
    Route::put('/workspaces/{workspace}/qr-codes/{qrCode}/disable', DisableQrCodeController::class);
    // Kapatmanın KARŞILIĞI: yanlışlıkla kapatılan bir kod masadaki kâğıdı
    // kalıcı olarak öldürmemeli (`docs/81`).
    Route::put('/workspaces/{workspace}/qr-codes/{qrCode}/enable', EnableQrCodeController::class);
    // Basılı kodun hedefi taşınır; token DEĞİŞMEZ.
    Route::put('/workspaces/{workspace}/qr-codes/{qrCode}/destination', RetargetQrCodeController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.png', ExportQrCodePngController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.svg', ExportQrCodeSvgController::class);
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/export.pdf', ExportQrCodePdfController::class);
    /*
        MASADAKİ KART (FF-120) — tek kodun eski `export.pdf` ucundan AYRIDIR:
        o, A4'ün ortasına konan çıplak bir kare (duvara asılacak afiş); bu ise
        kesilip pleksiglasa girecek, marka kimliği taşıyan bir kart.

        Biçim ADRESTEDİR, sorguda değil: `card.svg` ile `card.pdf` iki ayrı
        çıktıdır ve önbellek de onları ayrı görmelidir.
    */
    Route::get('/workspaces/{workspace}/qr-codes/{qrCode}/card.{format}', ExportQrCardController::class)
        ->where('format', 'svg|pdf');
    /*
        BASILABİLİR DESTE (`docs/104` Döngü 8) — tek kodun kâğıdından ayrı bir
        iş: kesilip masalara dağıtılacak kartlar. Her kart bir PNG üretir,
        dolayısıyla istek pahalıdır ve kendi hız sınırını taşır.
    */
    Route::get('/workspaces/{workspace}/brand/locations/{location}/qr-codes/print.pdf', ExportQrPrintSheetController::class)->middleware('throttle:10,1');
});
