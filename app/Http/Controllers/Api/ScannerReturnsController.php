<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CustomerRma;
use App\Models\WarehouseBin;
use App\Services\CustomerRmaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ScannerReturnsController extends Controller
{
    public function __construct(
        protected CustomerRmaService $rmaService
    ) {}

    /**
     * Get pending Customer RMAs awaiting dock check-in.
     */
    public function pending(): JsonResponse
    {
        $rmas = CustomerRma::whereNotIn('status', [CustomerRma::STATUS_RESOLVED, CustomerRma::STATUS_CANCELLED])
            ->with(['items.item', 'items.lot', 'deliveryOrder'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'rmas' => $rmas->map(fn ($rma) => [
                'id' => $rma->id,
                'rma_number' => $rma->rma_number,
                'customer_name' => $rma->customer_name,
                'defect_reason' => $rma->defect_reason,
                'status' => $rma->status,
                'total_cartons' => $rma->total_cartons,
                'received_cartons' => $rma->received_cartons,
                'do_number' => $rma->deliveryOrder->do_number ?? null,
                'items' => $rma->items->map(fn ($item) => [
                    'id' => $item->id,
                    'sku' => $item->item->item_code ?? 'N/A',
                    'name' => $item->item->name ?? 'N/A',
                    'carton_barcode' => $item->carton_barcode,
                    'quantity_returned' => (float) $item->quantity_returned,
                    'quantity_received' => (float) $item->quantity_received,
                ]),
            ]),
        ]);
    }

    /**
     * Check in an incoming returned carton at the dock into quarantine bin.
     */
    public function receiveCarton(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'rma_id' => 'required|uuid',
            'carton_barcode' => 'required|string|max:100',
            'quarantine_bin_code' => 'required|string',
            'quantity' => 'required|numeric|min:0.001',
        ]);

        $rma = CustomerRma::findOrFail($validated['rma_id']);

        $quarantineBin = WarehouseBin::where('bin_code', $validated['quarantine_bin_code'])->first();
        if (! $quarantineBin) {
            throw ValidationException::withMessages([
                'quarantine_bin_code' => "Quarantine bin '{$validated['quarantine_bin_code']}' not found in warehouse topology.",
            ]);
        }

        $result = $this->rmaService->receiveCarton(
            rma: $rma,
            cartonBarcode: $validated['carton_barcode'],
            quarantineBin: $quarantineBin,
            quantity: (float) $validated['quantity'],
            user: $request->user()
        );

        return response()->json([
            'success' => true,
            'message' => $result['message'],
            'received_cartons' => $result['received_cartons'],
            'total_cartons' => $result['total_cartons'],
            'movement_uuid' => $result['movement_uuid'],
        ]);
    }
}
