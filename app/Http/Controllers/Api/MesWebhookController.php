<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\InventoryMovementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MesWebhookController extends Controller
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    public function handle(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'eventId' => 'required|string',
            'eventType' => 'required|string',
            'timestamp' => 'required|string',
            'data' => 'required|array',
            'data.cartonBarcode' => 'required|string',
            'data.orderNumber' => 'required|string',
            'data.partNumber' => 'required|string',
            'data.quantityPacked' => 'required|numeric|min:1',
            'data.machineId' => 'nullable|string',
            'data.moldCode' => 'nullable|string',
            'data.unitOfMeasure' => 'nullable|string',
        ]);

        $eventId = $validated['eventId'];

        // Enforce idempotency: check if eventId has already been recorded
        $cacheKey = "mes_event_processed:{$eventId}";
        if (Cache::has($cacheKey)) {
            return response()->json([
                'success' => true,
                'message' => 'Duplicate webhook event received; safely ignored (Idempotent).',
                'eventId' => $eventId,
                'idempotent' => true,
            ], 200);
        }

        // Also check database ledger directly for bulletproof idempotency across cache clears
        $existingMovement = InventoryMovement::where('reference_type', 'MES_PACKAGING_EVENT')
            ->where('reference_id', $eventId)
            ->first();

        if ($existingMovement) {
            Cache::put($cacheKey, true, now()->addDays(7));

            return response()->json([
                'success' => true,
                'message' => 'Event already transacted in ledger (Idempotent).',
                'eventId' => $eventId,
                'idempotent' => true,
            ], 200);
        }

        if ($validated['eventType'] === 'packaging.unit_created') {
            $data = $validated['data'];

            // Match item or create if new finished good
            $item = Item::where('sku', $data['partNumber'])->first();
            if (! $item) {
                $item = Item::create([
                    'sku' => $data['partNumber'],
                    'name' => "Molded Part {$data['partNumber']}",
                    'category' => Item::CATEGORY_FINISHED_GOOD,
                    'unit_of_measure' => $data['unitOfMeasure'] ?? 'PCS',
                    'standard_pack_qty' => (float) $data['quantityPacked'],
                    'is_active' => true,
                ]);
            }

            // Find an open bin in Finished Goods zone
            $fgBin = WarehouseBin::whereHas('zone', function ($q) {
                $q->where('zone_type', WarehouseZone::TYPE_FINISHED_GOODS);
            })->where('is_blocked', false)->first();

            if (! $fgBin) {
                return response()->json([
                    'success' => false,
                    'message' => 'No available bins found in Finished Goods zone.',
                ], 500);
            }

            // Create Lot entry for this finished goods carton
            $lot = MaterialLot::firstOrCreate(
                ['lot_number' => $data['cartonBarcode']],
                [
                    'item_id' => $item->id,
                    'vendor_lot_number' => $data['orderNumber'],
                    'vendor_heat_number' => $data['moldCode'] ?? null,
                    'manufactured_date' => now()->toDateString(),
                    'status' => MaterialLot::STATUS_AVAILABLE,
                    'coa_metrics' => [
                        'machineId' => $data['machineId'] ?? null,
                        'moldCode' => $data['moldCode'] ?? null,
                        'orderNumber' => $data['orderNumber'],
                    ],
                ]
            );

            // Record intake in inventory ledger
            $movement = $this->movementService->recordFinishedGoodsIntake(
                item: $item,
                lot: $lot,
                fgBin: $fgBin,
                quantity: (float) $data['quantityPacked'],
                cartonBarcode: $data['cartonBarcode'],
                mesEventId: $eventId
            );

            // Mark event processed in cache for 7 days
            Cache::put($cacheKey, true, now()->addDays(7));

            return response()->json([
                'success' => true,
                'message' => 'Finished goods carton received and stored.',
                'data' => [
                    'eventId' => $eventId,
                    'cartonBarcode' => $data['cartonBarcode'],
                    'assignedBin' => $fgBin->bin_code,
                    'quantity' => (float) $data['quantityPacked'],
                    'transactionUuid' => $movement->transaction_uuid,
                ],
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => "Event type [{$validated['eventType']}] acknowledged.",
        ]);
    }
}
