<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IntegrationOutbox;
use App\Models\MaterialLot;
use App\Models\StagingRequest;
use App\Models\WarehouseBin;
use App\Services\InventoryMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ScannerPickingController extends Controller
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * List pending staging requests triggered by MES production schedules.
     */
    public function activeRequests(): JsonResponse
    {
        $requests = StagingRequest::whereIn('status', [StagingRequest::STATUS_PENDING, StagingRequest::STATUS_ALLOCATED])
            ->with(['items.item', 'items.sourceBin', 'items.stagingBin'])
            ->orderBy('requested_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $requests,
        ]);
    }

    /**
     * Confirm physical scan of resin bag/gaylord during staging handover.
     */
    public function confirmPick(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'staging_request_id' => 'required|uuid|exists:staging_requests,id',
            'lot_number' => 'required|string|exists:material_lots,lot_number',
            'source_bin_code' => 'required|string|exists:warehouse_bins,bin_code',
            'staging_bin_code' => 'required|string|exists:warehouse_bins,bin_code',
            'quantity_kg' => 'required|numeric|min:0.01',
        ]);

        $stagingRequest = StagingRequest::findOrFail($validated['staging_request_id']);
        $lot = MaterialLot::where('lot_number', $validated['lot_number'])->with('item')->firstOrFail();

        // Enforce quality guard: resin must be AVAILABLE
        if ($lot->status !== MaterialLot::STATUS_AVAILABLE) {
            return response()->json([
                'success' => false,
                'message' => "Lot [{$lot->lot_number}] has status [{$lot->status}]. Cannot dispatch unapproved or quarantined resin to machines.",
            ], 422);
        }

        $sourceBin = WarehouseBin::where('bin_code', $validated['source_bin_code'])->firstOrFail();
        $stagingBin = WarehouseBin::where('bin_code', $validated['staging_bin_code'])->firstOrFail();

        // Record staging issue in inventory ledger
        $movement = $this->movementService->recordStagingIssue(
            lot: $lot,
            sourceBin: $sourceBin,
            stagingBin: $stagingBin,
            quantity: (float) $validated['quantity_kg'],
            mesOrderId: $stagingRequest->mes_production_order_id,
            user: $request->user()
        );

        // Update staging request status
        $stagingRequest->status = StagingRequest::STATUS_DISPATCHED_TO_MES;
        $stagingRequest->dispatched_at = now();
        $stagingRequest->save();

        // Stage Outbox event for asynchronous MES gateway dispatch per SPEC-001
        $outboxEvent = IntegrationOutbox::create([
            'event_uuid' => (string) Str::uuid(),
            'event_type' => 'materials.lot_dispatched',
            'payload' => [
                'lotNumber' => $lot->lot_number,
                'materialCode' => $lot->item->sku,
                'materialName' => $lot->item->name,
                'vendorCode' => $lot->vendor_code,
                'vendorHeatNumber' => $lot->vendor_heat_number,
                'quantityKg' => (float) $validated['quantity_kg'],
                'packageCount' => (int) ceil($validated['quantity_kg'] / ($lot->item->standard_pack_qty ?: 25.0)),
                'packageType' => 'BAG_25KG',
                'expiryDate' => $lot->expiry_date?->toIso8601String(),
                'targetMachineCode' => $stagingRequest->target_machine_code,
                'targetOrderId' => $stagingRequest->mes_production_order_id,
                'dispatchedBy' => $request->user()?->id ?? 'wms_handler_user_uuid',
            ],
            'status' => IntegrationOutbox::STATUS_PENDING,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Material successfully staged and queued for MES sync.',
            'transaction_uuid' => $movement->transaction_uuid,
            'outbox_event_uuid' => $outboxEvent->event_uuid,
        ]);
    }
}
