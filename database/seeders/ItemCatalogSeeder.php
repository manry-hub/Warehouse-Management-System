<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'sku' => 'RAW-PP-INJ-503',
                'name' => 'Polypropylene Copolymer Prime Grade (PP 503)',
                'category' => Item::CATEGORY_RAW_RESIN,
                'unit_of_measure' => 'KG',
                'standard_pack_qty' => 25.00,
                'minimum_safety_stock' => 1000.00,
                'shelf_life_days' => 730, // 2 years
                'allow_regrind' => true,
                'max_regrind_percentage' => 15.00,
                'is_active' => true,
            ],
            [
                'sku' => 'RAW-ABS-HI-121',
                'name' => 'ABS High Impact Injection Grade',
                'category' => Item::CATEGORY_RAW_RESIN,
                'unit_of_measure' => 'KG',
                'standard_pack_qty' => 25.00,
                'minimum_safety_stock' => 750.00,
                'shelf_life_days' => 365, // 1 year
                'is_active' => true,
            ],
            [
                'sku' => 'RAW-PC-OPT-300',
                'name' => 'Polycarbonate Optical Grade Clear',
                'category' => Item::CATEGORY_RAW_RESIN,
                'unit_of_measure' => 'KG',
                'standard_pack_qty' => 25.00,
                'minimum_safety_stock' => 500.00,
                'shelf_life_days' => 365,
                'is_active' => true,
            ],
            [
                'sku' => 'MB-BLK-01',
                'name' => 'Carbon Black Masterbatch 40% Concentration',
                'category' => Item::CATEGORY_MASTERBATCH,
                'unit_of_measure' => 'KG',
                'standard_pack_qty' => 25.00,
                'minimum_safety_stock' => 200.00,
                'shelf_life_days' => 1095, // 3 years
                'is_active' => true,
            ],
            [
                'sku' => 'MB-WHT-02',
                'name' => 'Titanium Dioxide White Masterbatch 50%',
                'category' => Item::CATEGORY_MASTERBATCH,
                'unit_of_measure' => 'KG',
                'standard_pack_qty' => 25.00,
                'minimum_safety_stock' => 200.00,
                'shelf_life_days' => 1095,
                'is_active' => true,
            ],
            [
                'sku' => 'PART-BZL-FR-LH',
                'name' => 'Front Bezel Left Hand - Automotive Tier 1',
                'category' => Item::CATEGORY_FINISHED_GOOD,
                'unit_of_measure' => 'PCS',
                'standard_pack_qty' => 50.00, // 50 pcs per carton
                'minimum_safety_stock' => 200.00,
                'shelf_life_days' => null,
                'is_active' => true,
            ],
            [
                'sku' => 'PART-BZL-FR-RH',
                'name' => 'Front Bezel Right Hand - Automotive Tier 1',
                'category' => Item::CATEGORY_FINISHED_GOOD,
                'unit_of_measure' => 'PCS',
                'standard_pack_qty' => 50.00,
                'minimum_safety_stock' => 200.00,
                'shelf_life_days' => null,
                'is_active' => true,
            ],
            [
                'sku' => 'BOX-CTN-50',
                'name' => 'Heavy Duty Double-Wall Corrugated Carton 50-pack',
                'category' => Item::CATEGORY_PACKAGING,
                'unit_of_measure' => 'PCS',
                'standard_pack_qty' => 100.00,
                'minimum_safety_stock' => 500.00,
                'shelf_life_days' => null,
                'is_active' => true,
            ],
        ];

        foreach ($items as $data) {
            Item::firstOrCreate(['sku' => $data['sku']], $data);
        }
    }
}
