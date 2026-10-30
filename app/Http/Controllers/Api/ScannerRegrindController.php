<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\WarehouseBin;
use App\Services\BarcodeLabelService;
use App\Services\RegrindService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerRegrindController extends Controller
{
    public function __construct(
        protected RegrindService $regrindService,
        protected BarcodeLabelService $labelService
    ) {}

    /**
     * Intake granulated runner scrap/regrind box from shop-floor IMM station.
     */
    public function intake(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'item_sku' => 'required|string|exists:items,sku',
            'quantity_kg' => 'required|numeric|min:0.1',
            'bin_code' => 'required|string|exists:warehouse_bins,bin_code',
            'parent_lot_number' => 'nullable|string|exists:material_lots,lot_number',
            'production_order' => 'nullable|string|max:100',
            'machine_code' => 'nullable|string|max:50',
        ]);

        $item = Item::where('sku', $validated['item_sku'])->firstOrFail();
        $bin = WarehouseBin::where('bin_code', $validated['bin_code'])->firstOrFail();

        $parentLot = null;
        if (! empty($validated['parent_lot_number'])) {
            $parentLot = MaterialLot::where('lot_number', $validated['parent_lot_number'])->firstOrFail();
        }

        try {
            $regrindLot = $this->regrindService->intakeRegrind(
                item: $item,
                quantityKg: (float) $validated['quantity_kg'],
                destinationBin: $bin,
                parentLot: $parentLot,
                productionOrder: $validated['production_order'] ?? null,
                machineCode: $validated['machine_code'] ?? null,
                user: $request->user(),
                status: MaterialLot::STATUS_AVAILABLE
            );

            $qrSvg = $this->labelService->generateQrCodeSvg($regrindLot->lot_number);
            $zplStream = $this->labelService->generateZplLabel($regrindLot, $bin->bin_code, (float) $validated['quantity_kg']);

            return response()->json([
                'success' => true,
                'message' => "Regrind lot [{$regrindLot->lot_number}] successfully received and deposited into bin [{$bin->bin_code}].",
                'data' => [
                    'lot_id' => $regrindLot->id,
                    'lot_number' => $regrindLot->lot_number,
                    'lot_type' => $regrindLot->lot_type,
                    'item_sku' => $item->sku,
                    'item_name' => $item->name,
                    'quantity_kg' => (float) $validated['quantity_kg'],
                    'bin_code' => $bin->bin_code,
                    'parent_lot_number' => $parentLot?->lot_number,
                    'machine_code' => $regrindLot->regrind_source_machine,
                    'generation' => $regrindLot->regrind_generation,
                    'qr_svg' => $qrSvg,
                    'zpl_stream' => $zplStream,
                ],
            ], 201);
        } catch (DomainException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Get real-time available regrind inventory breakdown across the plant.
     */
    public function availableStock(): JsonResponse
    {
        $regrindItems = Item::where('allow_regrind', true)
            ->get()
            ->map(function (Item $item) {
                $availableKg = $this->regrindService->getAvailableRegrindStock($item);

                return [
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'allow_regrind' => $item->allow_regrind,
                    'max_regrind_percentage' => (float) $item->max_regrind_percentage,
                    'available_regrind_kg' => $availableKg,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $regrindItems,
        ]);
    }
}
