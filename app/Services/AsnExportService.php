<?php

namespace App\Services;

use App\Models\DeliveryOrder;
use RuntimeException;

class AsnExportService
{
    /**
     * Generate an Advanced Shipping Notice (ASN) payload for a Delivery Order.
     *
     * @return array<string, mixed>
     */
    public function generateAsn(string $doNumber): array
    {
        $deliveryOrder = DeliveryOrder::with([
            'items.item',
            'items.lot',
            'pallets.item',
            'securityOfficer',
        ])->where('do_number', trim($doNumber))->first();

        if (! $deliveryOrder) {
            throw new RuntimeException("Delivery Order [{$doNumber}] not found for ASN generation.");
        }

        $palletsData = $deliveryOrder->pallets->map(function ($pallet) {
            return [
                'sscc' => $pallet->sscc_code,
                'gs1_formatted' => "(00){$pallet->sscc_code}",
                'item_sku' => $pallet->item->sku,
                'item_name' => $pallet->item->name,
                'carton_count' => $pallet->carton_count,
                'gross_weight_kg' => (float) $pallet->gross_weight_kg,
                'net_weight_kg' => (float) $pallet->net_weight_kg,
                'tare_weight_kg' => (float) $pallet->tare_weight_kg,
                'status' => $pallet->status,
            ];
        })->toArray();

        $itemsData = $deliveryOrder->items->map(function ($item) {
            return [
                'part_number' => $item->item->sku,
                'part_name' => $item->item->name,
                'carton_barcode' => $item->carton_barcode,
                'lot_number' => $item->lot?->lot_number ?? $item->carton_barcode,
                'vendor_heat_number' => $item->lot?->vendor_heat_number,
                'quantity_ordered' => (float) $item->quantity_ordered,
                'quantity_shipped' => (float) $item->quantity_loaded,
                'unit_of_measure' => $item->item->unit_of_measure ?? 'PCS',
                'is_verified' => (bool) $item->is_verified_scanned,
            ];
        })->toArray();

        $totalLoadedQuantity = $deliveryOrder->items->sum('quantity_loaded');

        return [
            'asn_version' => '2.0-GS1',
            'transmission_id' => 'ASN-'.date('Ymd')."-{$deliveryOrder->do_number}",
            'generated_at' => now()->toIso8601String(),
            'shipment' => [
                'delivery_order_number' => $deliveryOrder->do_number,
                'gate_pass_number' => $deliveryOrder->gate_pass_number,
                'customer_name' => $deliveryOrder->customer_name,
                'destination_address' => $deliveryOrder->shipping_address,
                'status' => $deliveryOrder->status,
                'dispatched_at' => $deliveryOrder->dispatched_at?->toIso8601String(),
                'gate_out_at' => $deliveryOrder->gate_out_at?->toIso8601String(),
            ],
            'transport' => [
                'carrier' => 'PLANT_LOGISTICS',
                'vehicle_plate_number' => $deliveryOrder->vehicle_plate_number,
                'driver_name' => $deliveryOrder->driver_name,
                'seal_number' => $deliveryOrder->seal_number,
                'security_officer' => $deliveryOrder->securityOfficer?->name,
                'driver_signature_captured' => ! empty($deliveryOrder->driver_signature),
            ],
            'summary' => [
                'total_cartons' => (int) $deliveryOrder->total_cartons,
                'total_pallets' => count($palletsData),
                'total_units_shipped' => (float) $totalLoadedQuantity,
            ],
            'pallets' => $palletsData,
            'items' => $itemsData,
        ];
    }
}
