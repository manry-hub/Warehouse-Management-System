<?php

use App\Http\Controllers\Api\GatePassController;
use App\Http\Controllers\Api\HopperStagingController;
use App\Http\Controllers\Api\MesWebhookController;
use App\Http\Controllers\Api\ScannerAuthController;
use App\Http\Controllers\Api\ScannerBatchPickingController;
use App\Http\Controllers\Api\ScannerCycleCountController;
use App\Http\Controllers\Api\ScannerInboundController;
use App\Http\Controllers\Api\ScannerPickingController;
use App\Http\Controllers\Api\ScannerRegrindController;
use App\Http\Controllers\Api\ScannerReplenishmentController;
use App\Http\Controllers\Api\ScannerShippingController;
use App\Http\Controllers\Api\SiloTelemetryController;
use App\Http\Controllers\Api\TraceabilityController;
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

        // Multi-Tote Batch & S-Path Wave Picking
        Route::get('/batch-picking/active', [ScannerBatchPickingController::class, 'activeBatches']);
        Route::get('/batch-picking/{id}/tasks', [ScannerBatchPickingController::class, 'batchTasks']);
        Route::post('/batch-picking/confirm', [ScannerBatchPickingController::class, 'confirmItem']);

        // Forklift Min-Max Forward-Pick Replenishment
        Route::get('/replenishments/pending', [ScannerReplenishmentController::class, 'pending']);
        Route::post('/replenishments/{id}/start', [ScannerReplenishmentController::class, 'start']);
        Route::post('/replenishments/{id}/confirm', [ScannerReplenishmentController::class, 'confirm']);

        // Shop-Floor Machine Hopper Staging & Partial Return
        Route::post('/hopper/verify', [HopperStagingController::class, 'verify']);
        Route::post('/hopper/stage', [HopperStagingController::class, 'stage']);
        Route::post('/hopper/return', [HopperStagingController::class, 'returnPartial']);
        Route::get('/hopper/active', [HopperStagingController::class, 'active']);

        // Outbound Truck Loading
        Route::post('/shipping/scan-truck', [ScannerShippingController::class, 'scanTruckCarton']);

        // Outbound Security Gate Clearance
        Route::get('/gate-pass/pending', [GatePassController::class, 'pending']);
        Route::post('/gate-pass/clear', [GatePassController::class, 'clear']);

        // Regrind Recycling & Granulator Intake
        Route::post('/regrind/intake', [ScannerRegrindController::class, 'intake']);
        Route::get('/regrind/stock', [ScannerRegrindController::class, 'availableStock']);

        // Stock Opname & Blind Cycle Counting
        Route::get('/cycle-counts/active', [ScannerCycleCountController::class, 'activeOrders']);
        Route::get('/cycle-counts/{id}/sheet', [ScannerCycleCountController::class, 'countSheet']);
        Route::post('/cycle-counts/{id}/count', [ScannerCycleCountController::class, 'recordCount']);
    });

    // Lot Genealogy & Recall Traceability API
    Route::prefix('traceability')->group(function () {
        Route::get('/backward/{identifier}', [TraceabilityController::class, 'backward']);
        Route::get('/forward/{lotNumber}', [TraceabilityController::class, 'forward']);
        Route::post('/quarantine', [TraceabilityController::class, 'quarantine'])->middleware('auth:sanctum');
    });

    // Advanced Shipping Notice (ASN) Export
    Route::get('/delivery-orders/{doNumber}/asn', [GatePassController::class, 'exportAsn']);

    // Inbound MES Integration Webhook (secured by HMAC SHA-256)
    Route::post('/webhooks/mes', [MesWebhookController::class, 'handle'])
        ->middleware(VerifyMesWebhookSignature::class);

    // Silo Telemetry Ingestion (secured by X-Telemetry-Key or Sanctum)
    Route::post('/telemetry/silo-reading', [SiloTelemetryController::class, 'store'])
        ->middleware(VerifySiloTelemetryApiKey::class);
});
