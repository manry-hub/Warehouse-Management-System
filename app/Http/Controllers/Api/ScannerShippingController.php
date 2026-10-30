<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Services\DeliveryOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScannerShippingController extends Controller
{
    public function __construct(
        protected DeliveryOrderService $deliveryOrderService
    ) {}

    /**
     * Handheld scan verification for loading cartons/pallets onto the outbound truck.
     */
    public function scanTruckCarton(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'do_number' => 'required|string|exists:delivery_orders,do_number',
            'carton_barcode' => 'required|string',
        ]);

        $order = DeliveryOrder::where('do_number', $validated['do_number'])->firstOrFail();

        if ($order->status === DeliveryOrder::STATUS_DISPATCHED) {
            return response()->json([
                'success' => false,
                'message' => "Delivery Order {$order->do_number} has already been dispatched.",
            ], 422);
        }

        $result = $this->deliveryOrderService->scanCarton($order, $validated['carton_barcode']);

        $statusCode = $result['success'] ? 200 : 422;

        return response()->json($result, $statusCode);
    }
}
