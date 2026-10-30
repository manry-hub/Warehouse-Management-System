<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HopperTransaction;
use App\Services\HopperStagingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HopperStagingController extends Controller
{
    public function __construct(
        protected HopperStagingService $hopperService
    ) {}

    public function verify(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machineCode' => 'required|string',
            'lotNumber' => 'required|string',
            'productionOrder' => 'nullable|string',
        ]);

        $result = $this->hopperService->verifyRecipeCompatibility(
            machineCode: $validated['machineCode'],
            lotNumber: $validated['lotNumber'],
            productionOrder: $validated['productionOrder'] ?? null
        );

        $status = $result['compatible'] ? 200 : 422;

        return response()->json($result, $status);
    }

    public function stage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'machineCode' => 'required|string',
            'lotNumber' => 'required|string',
            'quantityKg' => 'required|numeric|min:0.001',
            'productionOrder' => 'nullable|string',
        ]);

        try {
            $transaction = $this->hopperService->issueToHopper(
                lotNumber: $validated['lotNumber'],
                machineCode: $validated['machineCode'],
                quantityKg: (float) $validated['quantityKg'],
                userId: $request->user()?->id,
                orderNumber: $validated['productionOrder'] ?? null
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully staged {$transaction->quantity_staged_kg} kg to machine {$transaction->machine_code}.",
                'data' => [
                    'transactionCode' => $transaction->transaction_code,
                    'machineCode' => $transaction->machine_code,
                    'lotNumber' => $validated['lotNumber'],
                    'quantityStagedKg' => (float) $transaction->quantity_staged_kg,
                    'status' => $transaction->status,
                    'stagedAt' => $transaction->staged_at->toIso8601String(),
                ],
            ], 201);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function returnPartial(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'transactionCode' => 'required|string',
            'returnedWeightKg' => 'required|numeric|min:0.001',
            'targetBinCode' => 'nullable|string',
        ]);

        try {
            $result = $this->hopperService->returnPartialFromHopper(
                transactionCodeOrId: $validated['transactionCode'],
                returnedWeightKg: (float) $validated['returnedWeightKg'],
                targetBinCode: $validated['targetBinCode'] ?? null,
                userId: $request->user()?->id
            );

            return response()->json([
                'success' => true,
                'message' => "Successfully returned {$result['returned_weight_kg']} kg to bin {$result['target_bin']}.",
                'data' => $result,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    public function active(): JsonResponse
    {
        $transactions = HopperTransaction::with(['materialLot.item', 'hopperBin'])
            ->whereIn('status', [HopperTransaction::STATUS_STAGED, HopperTransaction::STATUS_CONSUMING])
            ->latest('staged_at')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $transactions->map(fn ($t) => [
                'transactionCode' => $t->transaction_code,
                'machineCode' => $t->machine_code,
                'hopperBin' => $t->hopperBin->bin_code,
                'lotNumber' => $t->materialLot->lot_number,
                'materialCode' => $t->materialLot->item->sku,
                'quantityStagedKg' => (float) $t->quantity_staged_kg,
                'quantityReturnedKg' => (float) $t->quantity_returned_kg,
                'stagedAt' => $t->staged_at->toIso8601String(),
            ]),
        ]);
    }
}
