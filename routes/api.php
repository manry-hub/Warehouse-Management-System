<?php

use App\Http\Controllers\Api\MesWebhookController;
use App\Http\Controllers\Api\ScannerAuthController;
use App\Http\Controllers\Api\ScannerInboundController;
use App\Http\Controllers\Api\ScannerPickingController;
use App\Http\Controllers\Api\ScannerShippingController;
use App\Http\Middleware\VerifyMesWebhookSignature;
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
    });

    // Inbound MES Integration Webhook (secured by HMAC SHA-256)
    Route::post('/webhooks/mes', [MesWebhookController::class, 'handle'])
        ->middleware(VerifyMesWebhookSignature::class);
});
