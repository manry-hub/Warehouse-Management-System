<?php

namespace Tests\Feature;

use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\FefoAllocationService;
use App\Services\InventoryMovementService;
use App\Services\RegrindService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RegrindAndBlendCapTest extends TestCase
{
    use RefreshDatabase;

    protected RegrindService $regrindService;

    protected FefoAllocationService $fefoService;

    protected InventoryMovementService $movementService;

    protected Warehouse $warehouse;

    protected WarehouseZone $zoneRm;

    protected WarehouseBin $bin1;

    protected WarehouseBin $bin2;

    protected Item $ppResin;

    protected Item $pcClear;

    protected User $handlerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);

        $this->regrindService = app(RegrindService::class);
        $this->fefoService = app(FefoAllocationService::class);
        $this->movementService = app(InventoryMovementService::class);

        $this->warehouse = Warehouse::where('code', 'WH-MAIN')->firstOrFail();
        $this->zoneRm = WarehouseZone::where('code', 'ZONE-RM')->firstOrFail();
        $this->bin1 = WarehouseBin::where('bin_code', 'RM-A-01-11')->firstOrFail();
        $this->bin2 = WarehouseBin::where('bin_code', 'RM-A-01-12')->firstOrFail();

        $this->ppResin = Item::where('sku', 'RAW-PP-INJ-503')->firstOrFail();
        $this->pcClear = Item::where('sku', 'RAW-PC-OPT-300')->firstOrFail();

        $this->handlerUser = User::where('email', 'handler@wms.local')->firstOrFail();
    }

    public function test_intake_regrind_creates_lot_and_ledger_receipt(): void
    {
        // 1. Create a parent virgin lot
        $parentVirginLot = MaterialLot::create([
            'lot_number' => 'LOT-VIRGIN-001',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->ppResin->id,
            'vendor_code' => 'VEND-LOTTE',
            'vendor_heat_number' => 'HEAT-PP-771',
            'manufactured_date' => now()->subDays(5),
            'expiry_date' => now()->addYear(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        // 2. Intake 50 KG regrind from IMM-04 machine
        $regrindLot = $this->regrindService->intakeRegrind(
            item: $this->ppResin,
            quantityKg: 50.0,
            destinationBin: $this->bin1,
            parentLot: $parentVirginLot,
            productionOrder: 'PO-MES-9921',
            machineCode: 'IMM-04',
            user: $this->handlerUser,
            status: MaterialLot::STATUS_AVAILABLE
        );

        $this->assertNotNull($regrindLot);
        $this->assertEquals(MaterialLot::TYPE_REGRIND, $regrindLot->lot_type);
        $this->assertTrue($regrindLot->isRegrind());
        $this->assertEquals(1, $regrindLot->regrind_generation);
        $this->assertEquals('IMM-04', $regrindLot->regrind_source_machine);
        $this->assertEquals($parentVirginLot->id, $regrindLot->parent_lot_id);
        $this->assertEquals('HEAT-PP-771', $regrindLot->vendor_heat_number);

        // Verify balance updated
        $balance = InventoryBalance::where('bin_id', $this->bin1->id)
            ->where('material_lot_id', $regrindLot->id)
            ->firstOrFail();
        $this->assertEquals(50.0, (float) $balance->quantity_on_hand);

        // Verify double-entry movement
        $movement = InventoryMovement::where('material_lot_id', $regrindLot->id)
            ->where('movement_type', InventoryMovement::TYPE_RECEIPT)
            ->firstOrFail();
        $this->assertNull($movement->source_bin_id);
        $this->assertEquals($this->bin1->id, $movement->destination_bin_id);
        $this->assertEquals(50.0, (float) $movement->quantity);
    }

    public function test_cannot_intake_regrind_for_item_that_disallows_regrind(): void
    {
        $this->assertFalse($this->pcClear->allowsRegrind());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('is not configured to allow regrind material');

        $this->regrindService->intakeRegrind(
            item: $this->pcClear,
            quantityKg: 25.0,
            destinationBin: $this->bin1
        );
    }

    public function test_cannot_regrind_already_reground_lot_generation_two_guard(): void
    {
        // Generation 1 regrind lot
        $gen1Lot = MaterialLot::create([
            'lot_number' => 'REG-GEN-1',
            'lot_type' => MaterialLot::TYPE_REGRIND,
            'item_id' => $this->ppResin->id,
            'regrind_generation' => 1,
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Multi-heat thermal cycling degradation prohibited');

        // Attempting to regrind an already reground lot must fail
        $this->regrindService->intakeRegrind(
            item: $this->ppResin,
            quantityKg: 25.0,
            destinationBin: $this->bin1,
            parentLot: $gen1Lot
        );
    }

    public function test_fefo_allocation_enforces_max_regrind_blend_cap(): void
    {
        // PP Resin: allow_regrind = true, max_regrind_percentage = 15.00%
        $this->assertTrue($this->ppResin->allowsRegrind());
        $this->assertEquals(15.0, (float) $this->ppResin->max_regrind_percentage);

        // 1. Create 500 KG available REGRIND stock in Bin 1
        $regrindLot = MaterialLot::create([
            'lot_number' => 'REG-PP-TEST-001',
            'lot_type' => MaterialLot::TYPE_REGRIND,
            'item_id' => $this->ppResin->id,
            'expiry_date' => now()->addMonths(3),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $this->movementService->recordReceipt(
            item: $this->ppResin,
            lot: $regrindLot,
            destinationBin: $this->bin1,
            quantity: 500.0
        );

        // 2. Create 2,000 KG available VIRGIN stock in Bin 2
        $virginLot = MaterialLot::create([
            'lot_number' => 'LOT-VIRGIN-TEST-001',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->ppResin->id,
            'expiry_date' => now()->addMonths(6),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $this->movementService->recordReceipt(
            item: $this->ppResin,
            lot: $virginLot,
            destinationBin: $this->bin2,
            quantity: 2000.0
        );

        // 3. Request 1,000 KG staging allocation
        // Blend cap is 15% -> Max regrind = 150 KG, Virgin = 850 KG
        $allocations = $this->fefoService->allocate($this->ppResin, 1000.0);

        $totalAllocated = array_sum(array_column($allocations, 'quantity'));
        $this->assertEquals(1000.0, $totalAllocated);

        $regrindAlloc = collect($allocations)->where('is_regrind', true)->sum('quantity');
        $virginAlloc = collect($allocations)->where('is_regrind', false)->sum('quantity');

        // Strictly verify that regrind DOES NOT exceed 150 KG (15.0%)
        $this->assertEquals(150.0, $regrindAlloc);
        $this->assertEquals(850.0, $virginAlloc);
        $this->assertEquals(15.0, round(($regrindAlloc / $totalAllocated) * 100, 2));
    }

    public function test_fefo_allocation_falls_back_to_virgin_when_regrind_zero_or_insufficient(): void
    {
        // Virgin lot of 1,000 KG
        $virginLot = MaterialLot::create([
            'lot_number' => 'LOT-VIRGIN-ONLY-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->ppResin->id,
            'expiry_date' => now()->addYear(),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $this->movementService->recordReceipt(
            item: $this->ppResin,
            lot: $virginLot,
            destinationBin: $this->bin2,
            quantity: 1000.0
        );

        // Zero regrind in warehouse, request 400 KG
        $allocations = $this->fefoService->allocate($this->ppResin, 400.0);

        $this->assertCount(1, $allocations);
        $this->assertEquals(400.0, $allocations[0]['quantity']);
        $this->assertFalse($allocations[0]['is_regrind']);
        $this->assertEquals(MaterialLot::TYPE_VIRGIN, $allocations[0]['lot_type']);
    }

    public function test_fefo_allocation_strictly_excludes_regrind_when_item_disallows_regrind(): void
    {
        // PC Clear item: allow_regrind = false
        $this->assertFalse($this->pcClear->allowsRegrind());

        // Create regrind lot manually in database
        $pcRegrind = MaterialLot::create([
            'lot_number' => 'REG-PC-DISALLOWED',
            'lot_type' => MaterialLot::TYPE_REGRIND,
            'item_id' => $this->pcClear->id,
            'expiry_date' => now()->addMonths(2),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $this->movementService->recordReceipt(
            item: $this->pcClear,
            lot: $pcRegrind,
            destinationBin: $this->bin1,
            quantity: 500.0
        );

        // Create virgin PC lot
        $pcVirgin = MaterialLot::create([
            'lot_number' => 'LOT-PC-VIRGIN',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->pcClear->id,
            'expiry_date' => now()->addMonths(12),
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);
        $this->movementService->recordReceipt(
            item: $this->pcClear,
            lot: $pcVirgin,
            destinationBin: $this->bin2,
            quantity: 500.0
        );

        // Allocate 200 KG: must come 100% from virgin lot even though regrind expires sooner!
        $allocations = $this->fefoService->allocate($this->pcClear, 200.0);

        $this->assertCount(1, $allocations);
        $this->assertEquals($pcVirgin->id, $allocations[0]['lot_id']);
        $this->assertFalse($allocations[0]['is_regrind']);
    }

    public function test_scanner_regrind_intake_api_endpoint(): void
    {
        Sanctum::actingAs($this->handlerUser, ['scanner:access']);

        $response = $this->postJson('/api/v1/scanner/regrind/intake', [
            'item_sku' => $this->ppResin->sku,
            'quantity_kg' => 75.0,
            'bin_code' => $this->bin1->bin_code,
            'machine_code' => 'IMM-02',
            'production_order' => 'PO-2026-IMM02-A',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'data' => [
                    'item_sku' => $this->ppResin->sku,
                    'quantity_kg' => 75.0,
                    'bin_code' => $this->bin1->bin_code,
                    'machine_code' => 'IMM-02',
                    'lot_type' => MaterialLot::TYPE_REGRIND,
                    'generation' => 1,
                ],
            ]);

        $data = $response->json('data');
        $this->assertStringStartsWith('REG-', $data['lot_number']);
        $this->assertStringContainsString('<svg', $data['qr_svg']);
        $this->assertStringContainsString('^XA', $data['zpl_stream']);
    }

    public function test_scanner_regrind_stock_api_endpoint(): void
    {
        Sanctum::actingAs($this->handlerUser, ['scanner:access']);

        // Intake 60 KG of PP regrind
        $this->regrindService->intakeRegrind(
            item: $this->ppResin,
            quantityKg: 60.0,
            destinationBin: $this->bin1,
            status: MaterialLot::STATUS_AVAILABLE
        );

        $response = $this->getJson('/api/v1/scanner/regrind/stock');

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $data = collect($response->json('data'));
        $ppStock = $data->firstWhere('sku', $this->ppResin->sku);

        $this->assertNotNull($ppStock);
        $this->assertEquals(60.0, (float) $ppStock['available_regrind_kg']);
        $this->assertEquals(15.0, (float) $ppStock['max_regrind_percentage']);
    }
}
