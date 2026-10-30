<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use Illuminate\Database\Seeder;

class WarehouseTopologySeeder extends Seeder
{
    public function run(): void
    {
        $warehouse = Warehouse::firstOrCreate(
            ['code' => 'WH-MAIN'],
            [
                'name' => 'Main Plastic Injection Plant Warehouse',
                'address' => 'Cikarang Industrial Estate Block B-12, West Java',
                'is_active' => true,
            ]
        );

        $zones = [
            [
                'code' => 'ZONE-RM',
                'name' => 'Raw Polymer Resin Storage',
                'zone_type' => WarehouseZone::TYPE_RAW_MATERIAL,
                'is_temperature_controlled' => false,
            ],
            [
                'code' => 'ZONE-STG',
                'name' => 'Shop Floor Machine Staging Buffer',
                'zone_type' => WarehouseZone::TYPE_STAGING_BUFFER,
                'is_temperature_controlled' => false,
            ],
            [
                'code' => 'ZONE-FG',
                'name' => 'Finished Goods Distribution Area',
                'zone_type' => WarehouseZone::TYPE_FINISHED_GOODS,
                'is_temperature_controlled' => false,
            ],
            [
                'code' => 'ZONE-QC',
                'name' => 'Quality Hold & Quarantine Cage',
                'zone_type' => WarehouseZone::TYPE_QUARANTINE,
                'is_temperature_controlled' => true,
            ],
            [
                'code' => 'ZONE-SILO',
                'name' => 'Bulk Resin Outdoor Silo Farm',
                'zone_type' => WarehouseZone::TYPE_SILO,
                'is_temperature_controlled' => false,
            ],
        ];

        foreach ($zones as $zoneData) {
            $zone = WarehouseZone::firstOrCreate(
                ['warehouse_id' => $warehouse->id, 'code' => $zoneData['code']],
                $zoneData
            );

            // Generate representative bins per zone
            if ($zone->code === 'ZONE-RM') {
                foreach (['A', 'B'] as $aisle) {
                    for ($rack = 1; $rack <= 2; $rack++) {
                        for ($shelf = 1; $shelf <= 2; $shelf++) {
                            for ($binNum = 1; $binNum <= 2; $binNum++) {
                                $binCode = sprintf('RM-%s-%02d-%02d', $aisle, $rack, ($shelf * 10) + $binNum);
                                WarehouseBin::firstOrCreate(
                                    ['bin_code' => $binCode],
                                    [
                                        'zone_id' => $zone->id,
                                        'aisle' => $aisle,
                                        'rack' => (string) $rack,
                                        'shelf' => (string) $shelf,
                                        'bin' => (string) $binNum,
                                        'max_weight_kg' => 1500.00,
                                        'max_volume_cbm' => 2.50,
                                        'is_blocked' => false,
                                    ]
                                );
                            }
                        }
                    }
                }
            } elseif ($zone->code === 'ZONE-STG') {
                for ($m = 1; $m <= 6; $m++) {
                    $binCode = sprintf('STG-IMM-%02d', $m);
                    WarehouseBin::firstOrCreate(
                        ['bin_code' => $binCode],
                        [
                            'zone_id' => $zone->id,
                            'aisle' => 'STG',
                            'rack' => 'FLOOR',
                            'shelf' => '1',
                            'bin' => (string) $m,
                            'max_weight_kg' => 1000.00,
                            'max_volume_cbm' => 2.00,
                            'is_blocked' => false,
                        ]
                    );
                }
            } elseif ($zone->code === 'ZONE-SILO') {
                for ($s = 1; $s <= 3; $s++) {
                    $binCode = sprintf('SILO-BIN-%02d', $s);
                    WarehouseBin::firstOrCreate(
                        ['bin_code' => $binCode],
                        [
                            'zone_id' => $zone->id,
                            'aisle' => 'SILO',
                            'rack' => sprintf('BAY-%02d', $s),
                            'shelf' => '1',
                            'bin' => '1',
                            'max_weight_kg' => 60000.00,
                            'max_volume_cbm' => 120.00,
                            'is_blocked' => false,
                        ]
                    );
                }
            } elseif ($zone->code === 'ZONE-FG') {
                for ($rack = 1; $rack <= 2; $rack++) {
                    for ($shelf = 1; $shelf <= 2; $shelf++) {
                        $binCode = sprintf('FG-A-%02d-%02d', $rack, $shelf);
                        WarehouseBin::firstOrCreate(
                            ['bin_code' => $binCode],
                            [
                                'zone_id' => $zone->id,
                                'aisle' => 'A',
                                'rack' => (string) $rack,
                                'shelf' => (string) $shelf,
                                'bin' => '1',
                                'max_weight_kg' => 2000.00,
                                'max_volume_cbm' => 3.50,
                                'is_blocked' => false,
                            ]
                        );
                    }
                }
            } elseif ($zone->code === 'ZONE-QC') {
                for ($q = 1; $q <= 4; $q++) {
                    $binCode = sprintf('QC-HOLD-%02d', $q);
                    WarehouseBin::firstOrCreate(
                        ['bin_code' => $binCode],
                        [
                            'zone_id' => $zone->id,
                            'aisle' => 'QC',
                            'rack' => 'CAGE',
                            'shelf' => '1',
                            'bin' => (string) $q,
                            'max_weight_kg' => 1000.00,
                            'max_volume_cbm' => 2.00,
                            'is_blocked' => false,
                        ]
                    );
                }
            } elseif ($zone->code === 'ZONE-SILO') {
                for ($s = 1; $s <= 3; $s++) {
                    $binCode = sprintf('SILO-BIN-%02d', $s);
                    WarehouseBin::firstOrCreate(
                        ['bin_code' => $binCode],
                        [
                            'zone_id' => $zone->id,
                            'aisle' => 'SILO',
                            'rack' => sprintf('BAY-%02d', $s),
                            'shelf' => '1',
                            'bin' => '1',
                            'max_weight_kg' => 60000.00,
                            'max_volume_cbm' => 120.00,
                            'is_blocked' => false,
                        ]
                    );
                }
            }
        }
    }
}
