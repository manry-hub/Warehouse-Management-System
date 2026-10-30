<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\HopperTransaction;
use App\Models\InventoryBalance;
use App\Models\MaterialLot;
use App\Models\QualityQuarantine;
use App\Models\StagingRequest;
use App\Models\StagingRequestItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class LotGenealogyService
{
    /**
     * Backward Traceability: From a finished good carton/lot or delivery order back to raw resin vendor lots, heat numbers, and silo batches.
     *
     * @return array<string, mixed>
     */
    public function traceBackward(string $identifier): array
    {
        $identifier = trim($identifier);

        // Check if identifier is a Delivery Order DO Number
        $deliveryOrder = DeliveryOrder::where('do_number', $identifier)->first();
        if ($deliveryOrder) {
            $cartonLots = MaterialLot::whereHas('deliveryOrderItems', function ($q) use ($deliveryOrder) {
                $q->where('delivery_order_id', $deliveryOrder->id);
            })->get();

            if ($cartonLots->isEmpty()) {
                // Fallback to matching carton barcodes
                $barcodes = DeliveryOrderItem::where('delivery_order_id', $deliveryOrder->id)
                    ->pluck('carton_barcode')
                    ->filter();
                $cartonLots = MaterialLot::whereIn('lot_number', $barcodes)->get();
            }

            $traces = [];
            foreach ($cartonLots as $lot) {
                $traces[] = $this->traceCartonBackward($lot);
            }

            return [
                'direction' => 'BACKWARD',
                'query_type' => 'DELIVERY_ORDER',
                'target' => $identifier,
                'delivery_order' => [
                    'id' => $deliveryOrder->id,
                    'do_number' => $deliveryOrder->do_number,
                    'customer_name' => $deliveryOrder->customer_name,
                    'status' => $deliveryOrder->status,
                    'vehicle_plate_number' => $deliveryOrder->vehicle_plate_number,
                    'driver_name' => $deliveryOrder->driver_name,
                    'gate_pass_number' => $deliveryOrder->gate_pass_number,
                ],
                'finished_goods_count' => count($traces),
                'traces' => $traces,
            ];
        }

        // Otherwise resolve carton or finished good lot
        $lot = MaterialLot::with(['item'])->where('lot_number', $identifier)->first();
        if (! $lot) {
            throw new RuntimeException("Lot or Delivery Order [{$identifier}] not found in traceability records.");
        }

        return [
            'direction' => 'BACKWARD',
            'query_type' => 'CARTON_LOT',
            'target' => $identifier,
            'trace' => $this->traceCartonBackward($lot),
        ];
    }

    /**
     * Trace an individual finished good lot backward to raw materials and production runs.
     *
     * @return array<string, mixed>
     */
    protected function traceCartonBackward(MaterialLot $fgLot): array
    {
        $orderNumber = $fgLot->vendor_lot_number ?? $fgLot->coa_metrics['orderNumber'] ?? null;
        $machineCode = $fgLot->coa_metrics['machineId'] ?? null;
        $moldCode = $fgLot->coa_metrics['moldCode'] ?? null;

        $rawLots = collect();

        // 1. Look up StagingRequestItems matching this order number
        if ($orderNumber) {
            $stagedRawLotIds = StagingRequestItem::whereHas('stagingRequest', function ($q) use ($orderNumber) {
                $q->where('mes_production_order_id', $orderNumber)
                    ->orWhere('request_number', $orderNumber);
            })->pluck('material_lot_id')->filter();

            if ($stagedRawLotIds->isNotEmpty()) {
                $rawLots = $rawLots->merge(MaterialLot::with('item')->whereIn('id', $stagedRawLotIds)->get());
            }

            // 2. Look up HopperTransactions matching this order or machine
            $hopperRawLotIds = HopperTransaction::where('production_order_number', $orderNumber)
                ->pluck('material_lot_id')
                ->filter();

            if ($hopperRawLotIds->isNotEmpty()) {
                $rawLots = $rawLots->merge(MaterialLot::with('item')->whereIn('id', $hopperRawLotIds)->get());
            }
        }

        // 3. If parent lot exists (regrind), include parent lot
        if ($fgLot->parent_lot_id) {
            $parent = MaterialLot::with('item')->find($fgLot->parent_lot_id);
            if ($parent) {
                $rawLots->push($parent);
            }
        }

        $rawLots = $rawLots->unique('id');

        // Look up outbound shipping records
        $shippedItems = DeliveryOrderItem::with('deliveryOrder')
            ->where('material_lot_id', $fgLot->id)
            ->orWhere('carton_barcode', $fgLot->lot_number)
            ->get();

        return [
            'finished_good' => [
                'id' => $fgLot->id,
                'lot_number' => $fgLot->lot_number,
                'part_number' => $fgLot->item->sku,
                'part_name' => $fgLot->item->name,
                'status' => $fgLot->status,
                'manufactured_date' => $fgLot->manufactured_date?->toDateString(),
            ],
            'production_context' => [
                'order_number' => $orderNumber,
                'machine_code' => $machineCode,
                'mold_code' => $moldCode,
            ],
            'raw_materials' => $rawLots->map(function (MaterialLot $raw) {
                return [
                    'id' => $raw->id,
                    'lot_number' => $raw->lot_number,
                    'material_code' => $raw->item->sku,
                    'material_name' => $raw->item->name,
                    'lot_type' => $raw->lot_type,
                    'vendor_code' => $raw->vendor_code,
                    'vendor_lot_number' => $raw->vendor_lot_number,
                    'vendor_heat_number' => $raw->vendor_heat_number,
                    'expiry_date' => $raw->expiry_date?->toDateString(),
                    'status' => $raw->status,
                    'coa_metrics' => $raw->coa_metrics,
                ];
            })->values()->toArray(),
            'customer_shipments' => $shippedItems->map(function (DeliveryOrderItem $item) {
                return [
                    'do_number' => $item->deliveryOrder?->do_number,
                    'customer_name' => $item->deliveryOrder?->customer_name,
                    'vehicle_plate_number' => $item->deliveryOrder?->vehicle_plate_number,
                    'driver_name' => $item->deliveryOrder?->driver_name,
                    'gate_pass_number' => $item->deliveryOrder?->gate_pass_number,
                    'status' => $item->deliveryOrder?->status,
                    'quantity_loaded' => (float) $item->quantity_loaded,
                ];
            })->values()->toArray(),
        ];
    }

    /**
     * Forward Traceability: From a suspect raw resin lot to all downstream production orders, finished goods, warehouse bins, and customer deliveries.
     *
     * @return array<string, mixed>
     */
    public function traceForward(string $rawLotNumber): array
    {
        $rawLot = MaterialLot::with('item')->where('lot_number', trim($rawLotNumber))->first();
        if (! $rawLot) {
            throw new RuntimeException("Raw material lot [{$rawLotNumber}] not found.");
        }

        // 1. Find all production orders that staged or consumed this raw lot
        $stagedOrderNumbers = StagingRequest::whereHas('items', function ($q) use ($rawLot) {
            $q->where('material_lot_id', $rawLot->id);
        })->pluck('mes_production_order_id')->filter()->unique();

        $hopperOrderNumbers = HopperTransaction::where('material_lot_id', $rawLot->id)
            ->pluck('production_order_number')
            ->filter()
            ->unique();

        $allOrderNumbers = $stagedOrderNumbers->merge($hopperOrderNumbers)->unique()->values();

        // 2. Find all finished good lots produced by these orders
        $impactedFgLots = collect();
        if ($allOrderNumbers->isNotEmpty()) {
            $fgLots = MaterialLot::with(['item', 'inventoryBalances.bin.zone'])
                ->where(function ($q) use ($allOrderNumbers) {
                    $q->whereIn('vendor_lot_number', $allOrderNumbers);
                    foreach ($allOrderNumbers as $order) {
                        $q->orWhere('coa_metrics->orderNumber', $order);
                    }
                })->get();

            $impactedFgLots = $impactedFgLots->merge($fgLots);
        }

        // Also check if any child regrind lots reference this parent
        $childRegrindLots = MaterialLot::with('item')->where('parent_lot_id', $rawLot->id)->get();
        if ($childRegrindLots->isNotEmpty()) {
            $childOrders = $childRegrindLots->pluck('parent_production_order')->filter();
            if ($childOrders->isNotEmpty()) {
                $moreFg = MaterialLot::with(['item', 'inventoryBalances.bin.zone'])
                    ->whereIn('vendor_lot_number', $childOrders)
                    ->get();
                $impactedFgLots = $impactedFgLots->merge($moreFg);
            }
        }

        $impactedFgLots = $impactedFgLots->unique('id');

        // 3. Find current warehouse stock of impacted finished goods
        $warehouseStock = [];
        foreach ($impactedFgLots as $fg) {
            foreach ($fg->inventoryBalances as $balance) {
                if ($balance->quantity_on_hand > 0) {
                    $warehouseStock[] = [
                        'lot_number' => $fg->lot_number,
                        'part_sku' => $fg->item->sku,
                        'bin_code' => $balance->bin->bin_code,
                        'zone_type' => $balance->bin->zone->zone_type,
                        'quantity_on_hand' => (float) $balance->quantity_on_hand,
                        'status' => $fg->status,
                    ];
                }
            }
        }

        // 4. Find all outbound customer shipments containing impacted finished goods
        $fgLotIds = $impactedFgLots->pluck('id');
        $fgBarcodes = $impactedFgLots->pluck('lot_number');

        $deliveryItems = DeliveryOrderItem::with('deliveryOrder')
            ->where(function ($q) use ($fgLotIds, $fgBarcodes) {
                $q->whereIn('material_lot_id', $fgLotIds)
                    ->orWhereIn('carton_barcode', $fgBarcodes);
            })->get();

        $customerShipments = $deliveryItems->map(function ($item) {
            return [
                'do_number' => $item->deliveryOrder?->do_number,
                'customer_name' => $item->deliveryOrder?->customer_name,
                'vehicle_plate_number' => $item->deliveryOrder?->vehicle_plate_number,
                'driver_name' => $item->deliveryOrder?->driver_name,
                'gate_pass_number' => $item->deliveryOrder?->gate_pass_number,
                'status' => $item->deliveryOrder?->status,
                'carton_barcode' => $item->carton_barcode,
                'quantity_loaded' => (float) $item->quantity_loaded,
            ];
        })->values()->toArray();

        // 5. Remaining raw stock in warehouse
        $remainingRawBalance = InventoryBalance::where('material_lot_id', $rawLot->id)->sum('quantity_on_hand');

        return [
            'direction' => 'FORWARD',
            'raw_material' => [
                'id' => $rawLot->id,
                'lot_number' => $rawLot->lot_number,
                'material_code' => $rawLot->item->sku,
                'material_name' => $rawLot->item->name,
                'vendor_code' => $rawLot->vendor_code,
                'vendor_lot' => $rawLot->vendor_lot_number,
                'vendor_heat' => $rawLot->vendor_heat_number,
                'status' => $rawLot->status,
                'remaining_quantity_kg' => (float) $remainingRawBalance,
            ],
            'associated_production_orders' => $allOrderNumbers->toArray(),
            'impacted_finished_goods_count' => $impactedFgLots->count(),
            'impacted_warehouse_stock' => $warehouseStock,
            'customer_shipments_count' => count($customerShipments),
            'customer_shipments' => $customerShipments,
        ];
    }

    /**
     * Atomically quarantine all material lots in the blast radius of a suspect raw material or finished good.
     */
    public function quarantineImpactedStock(
        string $identifier,
        string $reason,
        ?int $userId = null,
        string $severity = QualityQuarantine::SEVERITY_MAJOR
    ): QualityQuarantine {
        return DB::transaction(function () use ($identifier, $reason, $userId, $severity) {
            $targetLot = MaterialLot::where('lot_number', trim($identifier))->first();
            if (! $targetLot) {
                throw new RuntimeException("Material lot [{$identifier}] not found for quarantine execution.");
            }

            // Gather all lots in blast radius
            $lotsToQuarantine = collect([$targetLot]);

            if ($targetLot->item->isRawMaterial() || $targetLot->lot_type === MaterialLot::TYPE_REGRIND) {
                // Forward blast radius
                $forwardTrace = $this->traceForward($targetLot->lot_number);
                $orders = $forwardTrace['associated_production_orders'];

                if (! empty($orders)) {
                    $fgLots = MaterialLot::where(function ($q) use ($orders) {
                        $q->whereIn('vendor_lot_number', $orders);
                        foreach ($orders as $order) {
                            $q->orWhere('coa_metrics->orderNumber', $order);
                        }
                    })->get();

                    $lotsToQuarantine = $lotsToQuarantine->merge($fgLots);
                }
            } else {
                // Backward blast radius
                $backwardTrace = $this->traceCartonBackward($targetLot);
                $rawLots = $backwardTrace['raw_materials'];
                if (! empty($rawLots)) {
                    $rawIds = collect($rawLots)->pluck('id');
                    $lotsToQuarantine = $lotsToQuarantine->merge(MaterialLot::whereIn('id', $rawIds)->get());
                }
            }

            $uniqueLots = $lotsToQuarantine->unique('id');

            // Mass quarantine update
            MaterialLot::whereIn('id', $uniqueLots->pluck('id'))
                ->update(['status' => MaterialLot::STATUS_QUARANTINE]);

            // Calculate total impacted quantity across active balances
            $totalImpactedQty = InventoryBalance::whereIn('material_lot_id', $uniqueLots->pluck('id'))
                ->sum('quantity_on_hand');

            $quarantineCode = 'QAR-'.date('Ymd').'-'.strtoupper(Str::random(4));

            $quarantine = QualityQuarantine::create([
                'quarantine_code' => $quarantineCode,
                'initiator_id' => $userId,
                'trigger_lot_id' => $targetLot->id,
                'reason' => $reason,
                'severity' => $severity,
                'impacted_lots_count' => $uniqueLots->count(),
                'impacted_total_quantity' => (float) $totalImpactedQty,
                'status' => QualityQuarantine::STATUS_ACTIVE,
            ]);

            Log::warning("QUALITY QUARANTINE EXECUTED [{$quarantineCode}]: Quarantined {$uniqueLots->count()} lots ({$totalImpactedQty} units). Reason: {$reason}");

            return $quarantine;
        });
    }
}
