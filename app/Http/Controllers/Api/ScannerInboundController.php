<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\BarcodeLabelService;
use App\Services\InventoryMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerInboundController extends Controller
{
    public function __construct(
        protected InventoryMovementService $movementService,
        protected BarcodeLabelService $labelService
    ) {}

    /**
     * Intake raw material at inbound dock.
     */
    public function receive(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'po_number' => 'required|string',
            'sku' => 'required|string|exists:items,sku',
            'vendor_code' => 'required|string',
            'vendor_lot_number' => 'required|string',
            'vendor_heat_number' => 'nullable|string',
            'quantity_kg' => 'required|numeric|min:0.01',
            'expiry_date' => 'nullable|date',
            'dock_bin_code' => 'nullable|string|exists:warehouse_bins,bin_code',
        ]);

        $item = Item::where('sku', $validated['sku'])->firstOrFail();

        // Default to first bin in raw material zone if dock bin not specified
        if (! empty($validated['dock_bin_code'])) {
            $bin = WarehouseBin::where('bin_code', $validated['dock_bin_code'])->firstOrFail();
        } else {
            $bin = WarehouseBin::whereHas('zone', function ($q) {
                $q->where('zone_type', WarehouseZone::TYPE_RAW_MATERIAL);
            })->where('is_blocked', false)->firstOrFail();
        }

        // Generate standardized internal lot number
        $dateStr = now()->format('Ymd');
        $randomSeq = str_pad((string) rand(1, 999), 3, '0', STR_PAD_LEFT);
        $internalLotNumber = "LOT-{$item->sku}-{$dateStr}-{$randomSeq}";

        $lot = MaterialLot::create([
            'lot_number' => $internalLotNumber,
            'item_id' => $item->id,
            'vendor_code' => $validated['vendor_code'],
            'vendor_lot_number' => $validated['vendor_lot_number'],
            'vendor_heat_number' => $validated['vendor_heat_number'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? now()->addDays($item->shelf_life_days ?? 730),
            'status' => MaterialLot::STATUS_QA_HOLD, // QA Hold enforced upon intake
        ]);

        // Record receipt into ledger
        $movement = $this->movementService->recordReceipt(
            item: $item,
            lot: $lot,
            destinationBin: $bin,
            quantity: (float) $validated['quantity_kg'],
            referenceId: $validated['po_number'],
            user: $request->user(),
            notes: 'Inbound scanned via Zebra Scanner'
        );

        $gs1 = $this->labelService->formatGs1String($lot, (float) $validated['quantity_kg']);
        $qrSvg = $this->labelService->generateQrCodeSvg($gs1, 160);
        $zpl = $this->labelService->generateZplLabel($lot, $bin->bin_code, (float) $validated['quantity_kg']);

        return response()->json([
            'success' => true,
            'message' => 'Material successfully received into QA Hold.',
            'data' => [
                'lot_number' => $lot->lot_number,
                'status' => $lot->status,
                'item_sku' => $item->sku,
                'assigned_bin' => $bin->bin_code,
                'quantity_kg' => (float) $validated['quantity_kg'],
                'gs1_string' => $gs1,
                'qr_code_svg' => $qrSvg,
                'zpl_payload' => $zpl,
            ],
        ], 201);
    }

    /**
     * Recommend a putaway bin based on warehouse topology rules.
     */
    public function suggestPutaway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lot_number' => 'required|string|exists:material_lots,lot_number',
        ]);

        $lot = MaterialLot::where('lot_number', $validated['lot_number'])->with('item')->firstOrFail();

        // Target zone depending on status
        $targetZoneType = ($lot->status === MaterialLot::STATUS_QUARANTINE)
            ? WarehouseZone::TYPE_QUARANTINE
            : WarehouseZone::TYPE_RAW_MATERIAL;

        $suggestedBin = WarehouseBin::whereHas('zone', function ($q) use ($targetZoneType) {
            $q->where('zone_type', $targetZoneType);
        })
            ->where('is_blocked', false)
            ->first();

        if (! $suggestedBin) {
            return response()->json([
                'success' => false,
                'message' => 'No available bins found in target zone.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'suggested_bin' => [
                'bin_code' => $suggestedBin->bin_code,
                'aisle' => $suggestedBin->aisle,
                'rack' => $suggestedBin->rack,
                'shelf' => $suggestedBin->shelf,
                'max_weight_kg' => $suggestedBin->max_weight_kg,
            ],
        ]);
    }

    /**
     * Confirm putaway transfer scan.
     */
    public function confirmPutaway(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lot_number' => 'required|string|exists:material_lots,lot_number',
            'source_bin_code' => 'required|string|exists:warehouse_bins,bin_code',
            'destination_bin_code' => 'required|string|exists:warehouse_bins,bin_code',
            'quantity_kg' => 'required|numeric|min:0.01',
        ]);

        $lot = MaterialLot::where('lot_number', $validated['lot_number'])->firstOrFail();
        $sourceBin = WarehouseBin::where('bin_code', $validated['source_bin_code'])->firstOrFail();
        $destBin = WarehouseBin::where('bin_code', $validated['destination_bin_code'])->firstOrFail();

        $movement = $this->movementService->recordPutaway(
            lot: $lot,
            sourceBin: $sourceBin,
            destinationBin: $destBin,
            quantity: (float) $validated['quantity_kg'],
            user: $request->user(),
            notes: 'Putaway scan confirmed'
        );

        return response()->json([
            'success' => true,
            'message' => "Stock successfully transferred from {$sourceBin->bin_code} to {$destBin->bin_code}.",
            'transaction_uuid' => $movement->transaction_uuid,
        ]);
    }
}
