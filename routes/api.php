<?php

use App\Http\Controllers\Api\MesWebhookController;
use App\Http\Controllers\Api\ScannerAuthController;
use App\Http\Controllers\Api\ScannerCycleCountController;
use App\Http\Controllers\Api\ScannerInboundController;
use App\Http\Controllers\Api\ScannerPickingController;
use App\Http\Controllers\Api\ScannerRegrindController;
use App\Http\Controllers\Api\ScannerShippingController;
use App\Http\Controllers\Api\SiloTelemetryController;
use App\Http\Middleware\VerifyMesWebhookSignature;
use App\Http\Middleware\VerifySiloTelemetryApiKey;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    // Handheld scanner authentication
    Route::post('/scanner/auth/login', [ScannerAuthController::class, 'login']);

    // Handheld barcode terminal authenticated routes
    Route::middleware('auth:sanctum')->prefix('scanner')->group(function () {
        // Inbound & Putaway
        Route::post('/inbound/receive', [ScannerInboundController::class, 'receive']);
        Route::get('/putaway/suggest', [ScannerInboundController::class, 'suggestPutaway']);
        Route::post('/putaway/confirm', [ScannerInboundController::class, 'confirmPutaway']);

        // Staging Replenishment Picking
        Route::get('/picking/active-orders', [ScannerPickingController::class, 'activeRequests']);
        Route::post('/picking/scan', [ScannerPickingController::class, 'confirmPick']);

        // Outbound Truck Loading
        Route::post('/shipping/scan-truck', [ScannerShippingController::class, 'scanTruckCarton']);

        // Regrind Recycling & Granulator Intake
        Route::post('/regrind/intake', [ScannerRegrindController::class, 'intake']);
        Route::get('/regrind/stock', [ScannerRegrindController::class, 'availableStock']);

        // Stock Opname & Blind Cycle Counting
        Route::get('/cycle-counts/active', [ScannerCycleCountController::class, 'activeOrders']);
        Route::get('/cycle-counts/{id}/sheet', [ScannerCycleCountController::class, 'countSheet']);
        Route::post('/cycle-counts/{id}/count', [ScannerCycleCountController::class, 'recordCount']);
    });

    // Inbound MES Integration Webhook (secured by HMAC SHA-256)
    Route::post('/webhooks/mes', [MesWebhookController::class, 'handle'])
        ->middleware(VerifyMesWebhookSignature::class);

    // Silo Telemetry Ingestion (secured by X-Telemetry-Key or Sanctum)
    Route::post('/telemetry/silo-reading', [SiloTelemetryController::class, 'store'])
        ->middleware(VerifySiloTelemetryApiKey::class);
});
