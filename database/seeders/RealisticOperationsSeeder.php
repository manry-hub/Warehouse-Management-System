<?php

namespace Database\Seeders;

use App\Models\CycleCountItem;
use App\Models\CycleCountOrder;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryBalance;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\StagingRequest;
use App\Models\StagingRequestItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\InventoryMovementService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class RealisticOperationsSeeder extends Seeder
{
    public function run(): void
    {
        $movementService = app(InventoryMovementService::class);

        // 1. Identify Existing Users
        $admin = User::where('email', 'admin@wms.local')->first() ?? User::first();
        $handler = User::where('email', 'handler@wms.local')->first() ?? $admin;

        // 2. Identify Warehouse and Zones
        $warehouse = Warehouse::where('code', 'WH-MAIN')->first();
        if (! $warehouse) {
            return;
        }

        $zoneRm = WarehouseZone::where('warehouse_id', $warehouse->id)->where('code', 'ZONE-RM')->first();
        $zoneStg = WarehouseZone::where('warehouse_id', $warehouse->id)->where('code', 'ZONE-STG')->first();
        $zoneFg = WarehouseZone::where('warehouse_id', $warehouse->id)->where('code', 'ZONE-FG')->first();
        $zoneQc = WarehouseZone::where('warehouse_id', $warehouse->id)->where('code', 'ZONE-QC')->first();

        // 3. Fetch Master Items
        $itemPp = Item::where('sku', 'RAW-PP-INJ-503')->first();
        $itemAbs = Item::where('sku', 'RAW-ABS-HI-121')->first();
        $itemPc = Item::where('sku', 'RAW-PC-OPT-300')->first();
        $itemMbBlk = Item::where('sku', 'MB-BLK-01')->first();
        $itemMbWht = Item::where('sku', 'MB-WHT-02')->first();
        $itemBzlLh = Item::where('sku', 'PART-BZL-FR-LH')->first();
        $itemBzlRh = Item::where('sku', 'PART-BZL-FR-RH')->first();
        $itemCtn = Item::where('sku', 'BOX-CTN-50')->first();

        if (! $itemPp || ! $itemAbs || ! $itemPc || ! $itemBzlLh || ! $itemBzlRh) {
            return;
        }

        // -------------------------------------------------------------
        // STEP 1: Seed Virgin & Regrind Material Lots
        // -------------------------------------------------------------
        $lotsData = [
            // Prime PP Lots from Lotte Chemical (FEFO staggered expiry)
            [
                'lot_number' => 'LOT-LT-260801',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemPp->id,
                'vendor_code' => 'VEND-LOTTE',
                'vendor_lot_number' => 'LC-503-8812',
                'vendor_heat_number' => 'HEAT-4401',
                'manufactured_date' => Carbon::now()->subMonths(3),
                'expiry_date' => Carbon::now()->addMonths(6), // Expiring sooner
                'status' => MaterialLot::STATUS_AVAILABLE,
                'coa_metrics' => ['mfi_g10min' => 12.5, 'density_gcm3' => 0.905, 'moisture_pct' => 0.02],
            ],
            [
                'lot_number' => 'LOT-LT-260915',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemPp->id,
                'vendor_code' => 'VEND-LOTTE',
                'vendor_lot_number' => 'LC-503-9020',
                'vendor_heat_number' => 'HEAT-4488',
                'manufactured_date' => Carbon::now()->subMonths(1),
                'expiry_date' => Carbon::now()->addMonths(18), // Fresher lot
                'status' => MaterialLot::STATUS_AVAILABLE,
                'coa_metrics' => ['mfi_g10min' => 12.8, 'density_gcm3' => 0.905, 'moisture_pct' => 0.015],
            ],
            // High-Impact ABS from LG Chem
            [
                'lot_number' => 'LOT-LG-260710',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemAbs->id,
                'vendor_code' => 'VEND-LG',
                'vendor_lot_number' => 'LG-HI121-771',
                'vendor_heat_number' => 'HEAT-8820',
                'manufactured_date' => Carbon::now()->subMonths(4),
                'expiry_date' => Carbon::now()->addMonths(8),
                'status' => MaterialLot::STATUS_AVAILABLE,
                'coa_metrics' => ['mfi_g10min' => 18.2, 'density_gcm3' => 1.04, 'impact_izod' => 24.5],
            ],
            // Optical Clear PC from Covestro
            [
                'lot_number' => 'LOT-COV-260812',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemPc->id,
                'vendor_code' => 'VEND-COV',
                'vendor_lot_number' => 'COV-OPT300-44',
                'vendor_heat_number' => 'HEAT-9911',
                'manufactured_date' => Carbon::now()->subMonths(2),
                'expiry_date' => Carbon::now()->addMonths(10),
                'status' => MaterialLot::STATUS_AVAILABLE,
                'coa_metrics' => ['mfi_g10min' => 9.5, 'density_gcm3' => 1.20, 'transmittance_pct' => 89.2],
            ],
            // Masterbatches
            [
                'lot_number' => 'LOT-CLAR-BLK-01',
                'lot_type' => MaterialLot::TYPE_MASTERBATCH,
                'item_id' => $itemMbBlk->id,
                'vendor_code' => 'VEND-CLARIANT',
                'vendor_lot_number' => 'CB-40-109',
                'vendor_heat_number' => 'HEAT-2201',
                'manufactured_date' => Carbon::now()->subMonths(5),
                'expiry_date' => Carbon::now()->addYears(2),
                'status' => MaterialLot::STATUS_AVAILABLE,
            ],
            [
                'lot_number' => 'LOT-CLAR-WHT-02',
                'lot_type' => MaterialLot::TYPE_MASTERBATCH,
                'item_id' => $itemMbWht->id,
                'vendor_code' => 'VEND-CLARIANT',
                'vendor_lot_number' => 'TD-50-204',
                'vendor_heat_number' => 'HEAT-2202',
                'manufactured_date' => Carbon::now()->subMonths(2),
                'expiry_date' => Carbon::now()->addYears(2),
                'status' => MaterialLot::STATUS_AVAILABLE,
            ],
            // Quarantined Raw Resin Lot (Excessive moisture failure)
            [
                'lot_number' => 'LOT-QC-OFFSPEC-01',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemPc->id,
                'vendor_code' => 'VEND-COV',
                'vendor_lot_number' => 'COV-FAIL-09',
                'vendor_heat_number' => 'HEAT-9900',
                'manufactured_date' => Carbon::now()->subWeeks(2),
                'expiry_date' => Carbon::now()->addMonths(11),
                'status' => MaterialLot::STATUS_QUARANTINE,
                'coa_metrics' => ['mfi_g10min' => 14.1, 'moisture_pct' => 0.09, 'qc_reason' => 'Moisture threshold exceeded (>0.02%)'],
            ],
        ];

        $lots = [];
        foreach ($lotsData as $data) {
            $lots[$data['lot_number']] = MaterialLot::firstOrCreate(
                ['lot_number' => $data['lot_number']],
                $data
            );
        }

        // Granulated Regrind Scrap Lots (Generation 1, recycled runner scrap)
        $parentLot = $lots['LOT-LT-260801'];
        $regrindLotsData = [
            [
                'lot_number' => 'REGRIND-PP-IMM01-01',
                'lot_type' => MaterialLot::TYPE_REGRIND,
                'item_id' => $itemPp->id,
                'parent_lot_id' => $parentLot->id,
                'parent_production_order' => 'WO-2026-081',
                'regrind_generation' => 1,
                'regrind_source_machine' => 'IMM-01',
                'manufactured_date' => Carbon::now()->subDays(5),
                'expiry_date' => Carbon::now()->addMonths(4),
                'status' => MaterialLot::STATUS_AVAILABLE,
            ],
            [
                'lot_number' => 'REGRIND-PP-IMM03-02',
                'lot_type' => MaterialLot::TYPE_REGRIND,
                'item_id' => $itemPp->id,
                'parent_lot_id' => $parentLot->id,
                'parent_production_order' => 'WO-2026-083',
                'regrind_generation' => 1,
                'regrind_source_machine' => 'IMM-03',
                'manufactured_date' => Carbon::now()->subDays(2),
                'expiry_date' => Carbon::now()->addMonths(4),
                'status' => MaterialLot::STATUS_AVAILABLE,
            ],
        ];

        foreach ($regrindLotsData as $data) {
            $lots[$data['lot_number']] = MaterialLot::firstOrCreate(
                ['lot_number' => $data['lot_number']],
                $data
            );
        }

        // Finished Goods Packaging Lots
        $fgLotsData = [
            [
                'lot_number' => 'FG-BZL-LH-26101',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemBzlLh->id,
                'parent_production_order' => 'WO-2026-077',
                'manufactured_date' => Carbon::now()->subDays(3),
                'status' => MaterialLot::STATUS_AVAILABLE,
            ],
            [
                'lot_number' => 'FG-BZL-RH-26102',
                'lot_type' => MaterialLot::TYPE_VIRGIN,
                'item_id' => $itemBzlRh->id,
                'parent_production_order' => 'WO-2026-078',
                'manufactured_date' => Carbon::now()->subDays(2),
                'status' => MaterialLot::STATUS_AVAILABLE,
            ],
        ];

        foreach ($fgLotsData as $data) {
            $lots[$data['lot_number']] = MaterialLot::firstOrCreate(
                ['lot_number' => $data['lot_number']],
                $data
            );
        }

        // -------------------------------------------------------------
        // STEP 2: Stock Rack Bins via Zero-Sum InventoryMovementService
        // -------------------------------------------------------------
        // Helper closure to safely record receipt only if bin balance is currently empty
        $stockBin = function (WarehouseBin $bin, Item $item, MaterialLot $lot, float $qty, string $notes) use ($movementService, $admin) {
            $existing = InventoryBalance::where('bin_id', $bin->id)->where('quantity_on_hand', '>', 0)->first();
            if (! $existing) {
                $movementService->recordReceipt(
                    item: $item,
                    lot: $lot,
                    destinationBin: $bin,
                    quantity: $qty,
                    referenceId: 'INITIAL-STOCK-'.strtoupper(Str::random(6)),
                    user: $admin,
                    notes: $notes
                );
            }
        };

        // Zone RM: Fill states to create realistic heatmaps
        // Bin 1: RM-A-01-11 -> Warning State (1,200 KG / 1,500 KG = 80%)
        $binA0111 = WarehouseBin::where('bin_code', 'RM-A-01-11')->first();
        if ($binA0111) {
            $stockBin($binA0111, $itemPp, $lots['LOT-LT-260801'], 1200.0, 'Prime PP 503 resin stock on pallet');
        }

        // Bin 2: RM-A-01-12 -> Optimal State (600 KG / 1,500 KG = 40%)
        $binA0112 = WarehouseBin::where('bin_code', 'RM-A-01-12')->first();
        if ($binA0112) {
            $stockBin($binA0112, $itemPp, $lots['REGRIND-PP-IMM01-01'], 600.0, 'Regrind PP Gen 1 flakes');
        }

        // Bin 3: RM-A-01-21 -> Critical State (1,425 KG / 1,500 KG = 95%)
        $binA0121 = WarehouseBin::where('bin_code', 'RM-A-01-21')->first();
        if ($binA0121) {
            $stockBin($binA0121, $itemPp, $lots['LOT-LT-260915'], 1425.0, 'Heavy virgin PP resin pallet');
        }

        // Bin 4: RM-A-01-22 -> Leave Empty (0%) for contrast

        // Bin 5: RM-A-02-11 -> Optimal ABS (750 KG / 1,500 KG = 50%)
        $binA0211 = WarehouseBin::where('bin_code', 'RM-A-02-11')->first();
        if ($binA0211) {
            $stockBin($binA0211, $itemAbs, $lots['LOT-LG-260710'], 750.0, 'LG Chem ABS resin bags');
        }

        // Bin 6: RM-A-02-12 -> Optimal Optical PC (500 KG / 1,500 KG = 33%)
        $binA0212 = WarehouseBin::where('bin_code', 'RM-A-02-12')->first();
        if ($binA0212) {
            $stockBin($binA0212, $itemPc, $lots['LOT-COV-260812'], 500.0, 'Covestro Optical Clear PC');
        }

        // Bin 7: RM-A-02-21 -> Masterbatch Stock (400 KG / 1,500 KG = 26.7%)
        $binA0221 = WarehouseBin::where('bin_code', 'RM-A-02-21')->first();
        if ($binA0221) {
            $stockBin($binA0221, $itemMbBlk, $lots['LOT-CLAR-BLK-01'], 400.0, 'Clariant Black MB 40%');
        }

        // Bin 8: RM-A-02-22 -> Simulate Maintenance Blocked Bin
        $binA0222 = WarehouseBin::where('bin_code', 'RM-A-02-22')->first();
        if ($binA0222 && ! $binA0222->is_blocked) {
            $binA0222->is_blocked = true;
            $binA0222->save();
        }

        // Aisle B: Stock Masterbatch & fresh PP
        $binB0111 = WarehouseBin::where('bin_code', 'RM-B-01-11')->first();
        if ($binB0111) {
            $stockBin($binB0111, $itemMbWht, $lots['LOT-CLAR-WHT-02'], 350.0, 'Clariant White MB 50%');
        }

        $binB0112 = WarehouseBin::where('bin_code', 'RM-B-01-12')->first();
        if ($binB0112) {
            $stockBin($binB0112, $itemPp, $lots['REGRIND-PP-IMM03-02'], 450.0, 'Regrind PP Gen 1 from IMM-03');
        }

        // Zone STG: Shop Floor Machine Buffers
        $stgImm01 = WarehouseBin::where('bin_code', 'STG-IMM-01')->first();
        if ($stgImm01) {
            $stockBin($stgImm01, $itemPp, $lots['LOT-LT-260801'], 200.0, 'Staged material buffer at IMM-01 hopper');
        }

        $stgImm03 = WarehouseBin::where('bin_code', 'STG-IMM-03')->first();
        if ($stgImm03) {
            $stockBin($stgImm03, $itemAbs, $lots['LOT-LG-260710'], 150.0, 'Active ABS staging for IMM-03');
        }

        // Zone FG: Finished Goods Automotive Bezels
        $binFg0101 = WarehouseBin::where('bin_code', 'FG-A-01-01')->first();
        if ($binFg0101) {
            $stockBin($binFg0101, $itemBzlLh, $lots['FG-BZL-LH-26101'], 500.0, '10 Cartons Front Bezel LH (50 pcs/carton)');
        }

        $binFg0102 = WarehouseBin::where('bin_code', 'FG-A-01-02')->first();
        if ($binFg0102) {
            $stockBin($binFg0102, $itemBzlRh, $lots['FG-BZL-RH-26102'], 600.0, '12 Cartons Front Bezel RH (50 pcs/carton)');
        }

        // Zone QC: Active Quarantined Lot
        $binQc01 = WarehouseBin::where('bin_code', 'QC-HOLD-01')->first();
        if ($binQc01) {
            $stockBin($binQc01, $itemPc, $lots['LOT-QC-OFFSPEC-01'], 500.0, 'Off-spec high moisture lot quarantined in Cage 01');
        }

        // -------------------------------------------------------------
        // STEP 3: Seed MES Shop-Floor Staging Demands
        // -------------------------------------------------------------
        if ($binA0111 && $stgImm01) {
            // Demand 1: IMM-01 Pending Replenishment
            $req1 = StagingRequest::firstOrCreate(
                ['request_number' => 'STG-REQ-2026-001'],
                [
                    'mes_production_order_id' => 'MES-PO-8812',
                    'target_machine_code' => 'IMM-01',
                    'status' => 'PENDING',
                    'requested_at' => Carbon::now()->subMinutes(45),
                ]
            );

            StagingRequestItem::firstOrCreate(
                ['staging_request_id' => $req1->id, 'item_id' => $itemPp->id],
                [
                    'material_lot_id' => $lots['LOT-LT-260801']->id,
                    'quantity_requested' => 100.00,
                    'quantity_picked' => 0.00,
                    'source_bin_id' => $binA0111->id,
                    'staging_bin_id' => $stgImm01->id,
                    'status' => 'PENDING',
                ]
            );

            // Demand 2: IMM-04 Pending Replenishment
            $stgImm04 = WarehouseBin::where('bin_code', 'STG-IMM-04')->first();
            $req2 = StagingRequest::firstOrCreate(
                ['request_number' => 'STG-REQ-2026-002'],
                [
                    'mes_production_order_id' => 'MES-PO-8819',
                    'target_machine_code' => 'IMM-04',
                    'status' => 'PENDING',
                    'requested_at' => Carbon::now()->subMinutes(15),
                ]
            );

            if ($stgImm04 && $binA0212) {
                StagingRequestItem::firstOrCreate(
                    ['staging_request_id' => $req2->id, 'item_id' => $itemPc->id],
                    [
                        'material_lot_id' => $lots['LOT-COV-260812']->id,
                        'quantity_requested' => 75.00,
                        'quantity_picked' => 0.00,
                        'source_bin_id' => $binA0212->id,
                        'staging_bin_id' => $stgImm04->id,
                        'status' => 'PENDING',
                    ]
                );
            }
        }

        // -------------------------------------------------------------
        // STEP 4: Seed Delivery Orders (DO)
        // -------------------------------------------------------------
        // Past Dispatched DO: PT Astra Honda Motor
        $do1 = DeliveryOrder::firstOrCreate(
            ['do_number' => 'DO-2026-1001'],
            [
                'customer_name' => 'PT Astra Honda Motor - Plant Karawang',
                'shipping_address' => 'Kawasan Industri Indotaisei Sektor 1A Blok B, Karawang',
                'vehicle_plate_number' => 'B 9481 KXR',
                'driver_name' => 'Budi Santoso',
                'status' => 'DISPATCHED',
                'total_cartons' => 20,
                'dispatched_at' => Carbon::now()->subDays(2),
            ]
        );

        if ($binFg0101) {
            DeliveryOrderItem::firstOrCreate(
                ['delivery_order_id' => $do1->id, 'item_id' => $itemBzlLh->id],
                [
                    'material_lot_id' => $lots['FG-BZL-LH-26101']->id,
                    'carton_barcode' => 'CTN-BZL-LH-001',
                    'quantity_ordered' => 500.0,
                    'quantity_loaded' => 500.0,
                    'is_verified_scanned' => true,
                    'source_bin_id' => $binFg0101->id,
                ]
            );
        }

        // Active Queued DO: PT Toyota Motor Manufacturing Indonesia (For Truck Loading Scan Mode)
        $do2 = DeliveryOrder::firstOrCreate(
            ['do_number' => 'DO-2026-1002'],
            [
                'customer_name' => 'PT Toyota Motor Manufacturing Indonesia - Karawang Plant 2',
                'shipping_address' => 'Kawasan Industri KIIC Lot CC-1, Telukjambe, Karawang',
                'vehicle_plate_number' => 'B 9102 TKO',
                'driver_name' => 'Ahmad Suhendra',
                'status' => 'LOADED',
                'total_cartons' => 24,
                'dispatched_at' => null,
            ]
        );

        if ($binFg0102) {
            DeliveryOrderItem::firstOrCreate(
                ['delivery_order_id' => $do2->id, 'item_id' => $itemBzlRh->id],
                [
                    'material_lot_id' => $lots['FG-BZL-RH-26102']->id,
                    'carton_barcode' => 'CTN-BZL-RH-001',
                    'quantity_ordered' => 600.0,
                    'quantity_loaded' => 0.0,
                    'is_verified_scanned' => false,
                    'source_bin_id' => $binFg0102->id,
                ]
            );
        }

        // -------------------------------------------------------------
        // STEP 5: Seed Cycle Count Orders (Stock Opname)
        // -------------------------------------------------------------
        // Active Blind Cycle Count Order assigned to Handler for testing
        if ($binA0111 && $binA0112) {
            $ccOrder = CycleCountOrder::firstOrCreate(
                ['count_number' => 'CC-2026-001'],
                [
                    'warehouse_id' => $warehouse->id,
                    'zone_id' => $zoneRm->id,
                    'count_type' => 'BLIND',
                    'status' => 'IN_PROGRESS',
                    'assigned_to' => $handler->id,
                    'snapshot_at' => Carbon::now()->subHours(2),
                    'notes' => 'Monthly scheduled blind audit for Raw Polymer Aisle A',
                ]
            );

            // Blind count items (snapshot recorded)
            CycleCountItem::firstOrCreate(
                [
                    'cycle_count_order_id' => $ccOrder->id,
                    'bin_id' => $binA0111->id,
                    'item_id' => $itemPp->id,
                    'material_lot_id' => $lots['LOT-LT-260801']->id,
                ],
                [
                    'recorded_quantity' => 1200.00,
                    'counted_quantity' => null, // Hidden from counter
                    'variance_quantity' => null,
                    'status' => 'PENDING',
                ]
            );

            CycleCountItem::firstOrCreate(
                [
                    'cycle_count_order_id' => $ccOrder->id,
                    'bin_id' => $binA0112->id,
                    'item_id' => $itemPp->id,
                    'material_lot_id' => $lots['REGRIND-PP-IMM01-01']->id,
                ],
                [
                    'recorded_quantity' => 600.00,
                    'counted_quantity' => null,
                    'variance_quantity' => null,
                    'status' => 'PENDING',
                ]
            );
        }
    }
}
