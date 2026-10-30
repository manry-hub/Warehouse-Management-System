<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\MaterialLot;
use App\Models\SupplierRtv;
use App\Models\User;
use App\Models\WarehouseBin;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierRtvService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Create a formal Supplier Return-to-Vendor (RTV) record.
     */
    public function createRtv(array $data): SupplierRtv
    {
        $quantity = (float) ($data['quantity_kg'] ?? 0);
        if ($quantity <= 0) {
            throw ValidationException::withMessages(['quantity_kg' => 'Return quantity must be strictly greater than 0 kg.']);
        }

        $lot = MaterialLot::findOrFail($data['material_lot_id']);
        $bin = WarehouseBin::findOrFail($data['warehouse_bin_id']);

        // Check bin balance for this lot
        $balance = InventoryBalance::where('bin_id', $bin->id)
            ->where('material_lot_id', $lot->id)
            ->first();

        $availableQty = $balance ? (float) $balance->quantity_on_hand : 0;
        if ($availableQty < $quantity) {
            throw ValidationException::withMessages([
                'quantity_kg' => "Bin {$bin->bin_code} has only {$availableQty} kg for lot {$lot->lot_number}, requested {$quantity} kg.",
            ]);
        }

        return DB::transaction(function () use ($data, $lot, $bin, $quantity) {
            $rtvNumber = sprintf(
                'RTV-%s-%03d',
                now()->format('Ymd'),
                SupplierRtv::whereDate('created_at', today())->count() + 1
            );

            return SupplierRtv::create([
                'rtv_number' => $rtvNumber,
                'vendor_code' => $data['vendor_code'] ?? ($lot->vendor_code ?? 'VND-GEN'),
                'vendor_name' => $data['vendor_name'] ?? 'Supplier',
                'po_number' => $data['po_number'] ?? null,
                'material_lot_id' => $lot->id,
                'warehouse_bin_id' => $bin->id,
                'quantity_kg' => $quantity,
                'reason' => $data['reason'] ?? 'Defective raw material quarantine',
                'status' => SupplierRtv::STATUS_DRAFT,
            ]);
        });
    }

    /**
     * Approve an RTV draft for outbound logistics dispatch.
     */
    public function approveRtv(SupplierRtv $rtv): SupplierRtv
    {
        if ($rtv->status !== SupplierRtv::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'status' => "RTV {$rtv->rtv_number} is in status {$rtv->status} and cannot be approved.",
            ]);
        }

        $rtv->status = SupplierRtv::STATUS_APPROVED;
        $rtv->save();

        return $rtv;
    }

    /**
     * Execute physical outbound dispatch of returned raw material to vendor.
     */
    public function dispatchRtv(SupplierRtv $rtv, string $debitMemoNumber, ?User $user = null): array
    {
        if ($rtv->status !== SupplierRtv::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'status' => "RTV {$rtv->rtv_number} must be APPROVED prior to dispatch. Current status: {$rtv->status}",
            ]);
        }

        if (trim($debitMemoNumber) === '') {
            throw ValidationException::withMessages([
                'debit_memo_number' => 'Debit memo reference number is required for RTV dispatch.',
            ]);
        }

        return DB::transaction(function () use ($rtv, $debitMemoNumber, $user) {
            $lot = $rtv->materialLot;
            $bin = $rtv->warehouseBin;

            $movement = $this->movementService->recordReturnToVendor(
                lot: $lot,
                sourceBin: $bin,
                quantity: (float) $rtv->quantity_kg,
                rtvNumber: $rtv->rtv_number,
                user: $user,
                notes: "RTV {$rtv->rtv_number} dispatched to vendor {$rtv->vendor_name}. Debit Memo: {$debitMemoNumber}"
            );

            $rtv->status = SupplierRtv::STATUS_DISPATCHED;
            $rtv->debit_memo_number = $debitMemoNumber;
            $rtv->dispatched_at = now();
            $rtv->dispatched_by = $user?->id;
            $rtv->save();

            return [
                'status' => 'success',
                'message' => "RTV {$rtv->rtv_number} successfully dispatched. Debit Memo: {$debitMemoNumber}",
                'rtv_number' => $rtv->rtv_number,
                'movement_uuid' => $movement->transaction_uuid,
            ];
        });
    }
}
