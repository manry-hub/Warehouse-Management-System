<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Services\AsnExportService;
use App\Services\GatePassService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GatePassController extends Controller
{
    public function __construct(
        protected GatePassService $gatePassService,
        protected AsnExportService $asnService
    ) {}

    public function clear(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'deliveryOrderNumber' => 'required|string',
            'sealNumber' => 'required|string',
            'driverSignature' => 'required|string',
            'verifiedPlate' => 'nullable|string',
            'verifiedDriver' => 'nullable|string',
        ]);

        try {
            $deliveryOrder = $this->gatePassService->clearGatePass(
                doNumber: $validated['deliveryOrderNumber'],
                sealNumber: $validated['sealNumber'],
                driverSignature: $validated['driverSignature'],
                verifiedPlate: $validated['verifiedPlate'] ?? null,
                verifiedDriver: $validated['verifiedDriver'] ?? null,
                securityOfficerId: $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Gate Pass {$deliveryOrder->gate_pass_number} issued. Truck cleared for departure.",
                'data' => [
                    'gatePassNumber' => $deliveryOrder->gate_pass_number,
                    'deliveryOrderNumber' => $deliveryOrder->do_number,
                    'customerName' => $deliveryOrder->customer_name,
                    'vehiclePlateNumber' => $deliveryOrder->vehicle_plate_number,
                    'driverName' => $deliveryOrder->driver_name,
                    'sealNumber' => $deliveryOrder->seal_number,
                    'status' => $deliveryOrder->status,
                    'gateOutAt' => $deliveryOrder->gate_out_at?->toIso8601String(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function exportAsn(string $doNumber): JsonResponse
    {
        try {
            $asn = $this->asnService->generateAsn($doNumber);

            return response()->json([
                'success' => true,
                'data' => $asn,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 404);
        }
    }

    public function pending(): JsonResponse
    {
        $orders = DeliveryOrder::with('items')
            ->whereIn('status', [DeliveryOrder::STATUS_LOADED, DeliveryOrder::STATUS_DISPATCHED])
            ->whereNull('gate_pass_number')
            ->latest()
            ->limit(15)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $orders->map(fn ($o) => [
                'doNumber' => $o->do_number,
                'customerName' => $o->customer_name,
                'vehiclePlateNumber' => $o->vehicle_plate_number,
                'driverName' => $o->driver_name,
                'totalCartons' => $o->total_cartons,
                'status' => $o->status,
            ]),
        ]);
    }
}
