<?php

namespace App\Services;

use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\WarehouseBin;
use DomainException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RegrindService
{
    public function __construct(
        protected InventoryMovementService $movementService
    ) {}

    /**
     * Intake granulated runner scrap/regrind from the shop floor into warehouse inventory.
     */
    public function intakeRegrind(
        Item $item,
        float $quantityKg,
        WarehouseBin $destinationBin,
        ?MaterialLot $parentLot = null,
        ?string $productionOrder = null,
        ?string $machineCode = null,
        ?User $user = null,
        string $status = MaterialLot::STATUS_AVAILABLE
    ): MaterialLot {
        if ($quantityKg <= 0) {
            throw new InvalidArgumentException('Regrind intake quantity must be greater than zero.');
        }

        if (! $item->allowsRegrind()) {
            throw new DomainException("Item SKU [{$item->sku}] is not configured to allow regrind material.");
        }

        // Multi-heat thermal cycling protection:
        // Do not allow regrinding of an already reground lot (Gen >= 1)
        if ($parentLot && $parentLot->isRegrind()) {
            throw new DomainException(
                "Cannot regrind parent lot [{$parentLot->lot_number}] because it is already a REGRIND lot. Multi-heat thermal cycling degradation prohibited."
            );
        }

        return DB::transaction(function () use (
            $item,
            $quantityKg,
            $destinationBin,
            $parentLot,
            $productionOrder,
            $machineCode,
            $user,
            $status
        ) {
            $lotNumber = $this->generateRegrindLotNumber($item);

            // Regrind has a shorter recommended shelf-life due to atmospheric moisture exposure
            $shelfDays = $item->shelf_life_days ? (int) floor($item->shelf_life_days / 2) : 180;
            $expiryDate = now()->addDays($shelfDays)->toDateString();

            $regrindLot = MaterialLot::create([
                'lot_number' => $lotNumber,
                'lot_type' => MaterialLot::TYPE_REGRIND,
                'item_id' => $item->id,
                'parent_lot_id' => $parentLot?->id,
                'parent_production_order' => $productionOrder,
                'regrind_generation' => 1,
                'regrind_source_machine' => $machineCode,
                'vendor_code' => 'INTERNAL-REGRIND',
                'vendor_lot_number' => $parentLot?->lot_number ?? ($productionOrder ?? 'SHOP-FLOOR-SCRAP'),
                'vendor_heat_number' => $parentLot?->vendor_heat_number,
                'manufactured_date' => now()->toDateString(),
                'expiry_date' => $expiryDate,
                'status' => $status,
                'qc_inspected_at' => $status === MaterialLot::STATUS_AVAILABLE ? now() : null,
                'qc_inspected_by' => $status === MaterialLot::STATUS_AVAILABLE ? $user?->id : null,
            ]);

            // Record inbound movement (null -> destinationBin)
            $this->movementService->recordReceipt(
                item: $item,
                lot: $regrindLot,
                destinationBin: $destinationBin,
                quantity: $quantityKg,
                referenceId: $productionOrder ?? "GRANULATOR-{$regrindLot->lot_number}",
                user: $user,
                notes: sprintf(
                    'Regrind runner scrap intake (Machine: %s, Parent Order: %s)',
                    $machineCode ?? 'N/A',
                    $productionOrder ?? 'N/A'
                )
            );

            return $regrindLot;
        });
    }

    /**
     * Get aggregate available unreserved regrind quantity for a given resin SKU.
     */
    public function getAvailableRegrindStock(Item $item): float
    {
        return (float) InventoryBalance::query()
            ->join('material_lots', 'inventory_balances.material_lot_id', '=', 'material_lots.id')
            ->join('warehouse_bins', 'inventory_balances.bin_id', '=', 'warehouse_bins.id')
            ->join('warehouse_zones', 'warehouse_bins.zone_id', '=', 'warehouse_zones.id')
            ->where('inventory_balances.item_id', $item->id)
            ->where('material_lots.lot_type', MaterialLot::TYPE_REGRIND)
            ->where('material_lots.status', MaterialLot::STATUS_AVAILABLE)
            ->where(function ($query) {
                $query->whereNull('material_lots.expiry_date')
                    ->orWhere('material_lots.expiry_date', '>=', now()->toDateString());
            })
            ->where('warehouse_bins.is_blocked', false)
            ->where('warehouse_zones.zone_type', '!=', 'QUARANTINE')
            ->sum(DB::raw('inventory_balances.quantity_on_hand - inventory_balances.quantity_reserved'));
    }

    /**
     * Generate unique regrind lot barcode: REG-{SKU}-{YYYYMMDD}-{SEQ}
     */
    protected function generateRegrindLotNumber(Item $item): string
    {
        $dateStr = date('Ymd');
        $prefix = "REG-{$item->sku}-{$dateStr}-";

        $count = MaterialLot::where('lot_number', 'like', "{$prefix}%")->count() + 1;

        return $prefix.str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }
}
