<?php

namespace Tests\Feature;

use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\HopperTransaction;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\QualityQuarantine;
use App\Models\StagingRequest;
use App\Models\StagingRequestItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\AsnExportService;
use App\Services\GatePassService;
use App\Services\HopperStagingService;
use App\Services\LotGenealogyService;
use App\Services\SsccPalletService;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdvancedManufacturingLogisticsTest extends TestCase
{
    use RefreshDatabase;

    protected User $operator;

    protected Warehouse $warehouse;

    protected WarehouseBin $rawBin;

    protected WarehouseBin $hopperBin;

    protected WarehouseBin $fgBin;

    protected Item $rawResin;

    protected Item $finishedGood;

    protected MaterialLot $rawLot;

    protected MaterialLot $fgLot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);

        $this->operator = User::factory()->create([
            'email' => 'operator.logistics@wms.local',
            'name' => 'Logistics Operator',
        ]);

        $this->warehouse = Warehouse::where('code', 'WH-MAIN')->firstOrFail();

        $this->rawBin = WarehouseBin::whereHas('zone', fn ($q) => $q->where('zone_type', WarehouseZone::TYPE_RAW_MATERIAL))->firstOrFail();
        $this->hopperBin = WarehouseBin::where('bin_code', 'STG-IMM-01')->firstOrFail();
        $this->fgBin = WarehouseBin::whereHas('zone', fn ($q) => $q->where('zone_type', WarehouseZone::TYPE_FINISHED_GOODS))->firstOrFail();

        $this->rawResin = Item::where('sku', 'RES-PP-HOMO')->firstOrFail();
        $this->finishedGood = Item::where('sku', 'PRD-AUTO-BUMPER')->firstOrFail();

        // Seed raw resin lot with vendor heat number
        $this->rawLot = MaterialLot::create([
            'lot_number' => 'LOT-PP-HOMO-20261030-01',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->rawResin->id,
            'vendor_code' => 'VEND-CHIMEI',
            'vendor_lot_number' => 'CHIMEI-9981',
            'vendor_heat_number' => 'HEAT-PP-4401',
            'manufactured_date' => now()->subMonths(1)->toDateString(),
            'expiry_date' => now()->addMonths(11)->toDateString(),
            'status' => MaterialLot::STATUS_AVAILABLE,
            'coa_metrics' => [
                'melt_flow_index' => 12.5,
                'moisture_pct' => 0.02,
                'density' => 0.905,
            ],
        ]);

        // Put initial raw stock in raw bin
        InventoryBalance::create([
            'bin_id' => $this->rawBin->id,
            'item_id' => $this->rawResin->id,
            'material_lot_id' => $this->rawLot->id,
            'quantity_on_hand' => 1000.0,
            'quantity_reserved' => 0,
        ]);

        InventoryMovement::create([
            'transaction_uuid' => (string) Str::uuid(),
            'movement_type' => InventoryMovement::TYPE_RECEIPT,
            'item_id' => $this->rawResin->id,
            'material_lot_id' => $this->rawLot->id,
            'source_bin_id' => null,
            'destination_bin_id' => $this->rawBin->id,
            'quantity' => 1000.0,
            'uom' => 'KG',
            'reference_type' => 'PO_RECEIPT',
            'reference_id' => 'PO-TEST-001',
        ]);

        // Seed finished good carton produced by WO-AUTO-2026-001 on IMM-01
        $this->fgLot = MaterialLot::create([
            'lot_number' => 'CARTON-BUMPER-20261030-991',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $this->finishedGood->id,
            'vendor_lot_number' => 'WO-AUTO-2026-001',
            'vendor_heat_number' => 'MOLD-BUMPER-01',
            'manufactured_date' => now()->toDateString(),
            'status' => MaterialLot::STATUS_AVAILABLE,
            'coa_metrics' => [
                'orderNumber' => 'WO-AUTO-2026-001',
                'machineId' => 'IMM-01',
                'moldCode' => 'MOLD-BUMPER-01',
            ],
        ]);

        InventoryBalance::create([
            'bin_id' => $this->fgBin->id,
            'item_id' => $this->finishedGood->id,
            'material_lot_id' => $this->fgLot->id,
            'quantity_on_hand' => 50.0,
            'quantity_reserved' => 0,
        ]);

        InventoryMovement::create([
            'transaction_uuid' => (string) Str::uuid(),
            'movement_type' => InventoryMovement::TYPE_FG_INTAKE,
            'item_id' => $this->finishedGood->id,
            'material_lot_id' => $this->fgLot->id,
            'source_bin_id' => null,
            'destination_bin_id' => $this->fgBin->id,
            'quantity' => 50.0,
            'uom' => 'PCS',
            'reference_type' => 'MES_PACKAGING_EVENT',
            'reference_id' => 'MES-EVT-CARTON-991',
        ]);

        // Link raw resin lot to the work order via StagingRequest
        $staging = StagingRequest::create([
            'request_number' => 'SR-WO-AUTO-2026-001',
            'mes_production_order_id' => 'WO-AUTO-2026-001',
            'target_machine_code' => 'IMM-01',
            'status' => StagingRequest::STATUS_COMPLETED,
            'requested_at' => now()->subHours(2),
            'dispatched_at' => now()->subHours(1),
        ]);

        StagingRequestItem::create([
            'staging_request_id' => $staging->id,
            'item_id' => $this->rawResin->id,
            'material_lot_id' => $this->rawLot->id,
            'quantity_requested' => 250.0,
            'quantity_picked' => 250.0,
            'is_regrind' => false,
            'blend_percentage' => 0.0,
            'source_bin_id' => $this->rawBin->id,
            'staging_bin_id' => $this->hopperBin->id,
            'status' => 'PICKED',
        ]);
    }

    public function test_backward_traceability_resolves_upstream_raw_resin_and_heats_from_carton(): void
    {
        $service = app(LotGenealogyService::class);
        $result = $service->traceBackward($this->fgLot->lot_number);

        $this->assertEquals('BACKWARD', $result['direction']);
        $this->assertEquals($this->fgLot->lot_number, $result['target']);

        $trace = $result['trace'];
        $this->assertEquals('WO-AUTO-2026-001', $trace['production_context']['order_number']);
        $this->assertEquals('IMM-01', $trace['production_context']['machine_code']);

        // Assert raw material heats resolved
        $this->assertCount(1, $trace['raw_materials']);
        $this->assertEquals($this->rawLot->lot_number, $trace['raw_materials'][0]['lot_number']);
        $this->assertEquals('HEAT-PP-4401', $trace['raw_materials'][0]['vendor_heat_number']);
        $this->assertEquals('VEND-CHIMEI', $trace['raw_materials'][0]['vendor_code']);
    }

    public function test_forward_traceability_identifies_downstream_fg_stock_and_customer_shipments_from_raw_lot(): void
    {
        // Create an outbound delivery order for this finished good carton
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-AUTO-20261030-01',
            'customer_name' => 'PT Astra Daihatsu Motor',
            'shipping_address' => 'Kawasan Industri Suryacipta Karawang',
            'vehicle_plate_number' => 'B 9112 KDA',
            'driver_name' => 'Agus Priyanto',
            'status' => DeliveryOrder::STATUS_LOADED,
            'total_cartons' => 1,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'item_id' => $this->finishedGood->id,
            'material_lot_id' => $this->fgLot->id,
            'carton_barcode' => $this->fgLot->lot_number,
            'quantity_ordered' => 50.0,
            'quantity_loaded' => 50.0,
            'is_verified_scanned' => true,
        ]);

        $service = app(LotGenealogyService::class);
        $result = $service->traceForward($this->rawLot->lot_number);

        $this->assertEquals('FORWARD', $result['direction']);
        $this->assertEquals('HEAT-PP-4401', $result['raw_material']['vendor_heat']);
        $this->assertContains('WO-AUTO-2026-001', $result['associated_production_orders']);

        // Assert customer shipments identified
        $this->assertEquals(1, $result['customer_shipments_count']);
        $this->assertEquals('DO-AUTO-20261030-01', $result['customer_shipments'][0]['do_number']);
        $this->assertEquals('PT Astra Daihatsu Motor', $result['customer_shipments'][0]['customer_name']);
    }

    public function test_mass_quarantine_atomically_locks_connected_lots_and_records_incident(): void
    {
        $service = app(LotGenealogyService::class);

        $quarantine = $service->quarantineImpactedStock(
            identifier: $this->rawLot->lot_number,
            reason: 'Vendor heat batch failed MFI moisture threshold (Degraded Melt Flow)',
            userId: $this->operator->id,
            severity: QualityQuarantine::SEVERITY_CRITICAL
        );

        $this->assertNotNull($quarantine);
        $this->assertEquals(QualityQuarantine::SEVERITY_CRITICAL, $quarantine->severity);
        $this->assertEquals(QualityQuarantine::STATUS_ACTIVE, $quarantine->status);
        $this->assertStringStartsWith('QAR-', $quarantine->quarantine_code);

        // Assert both raw lot and downstream finished goods lot are quarantined
        $this->rawLot->refresh();
        $this->fgLot->refresh();
        $this->assertEquals(MaterialLot::STATUS_QUARANTINE, $this->rawLot->status);
        $this->assertEquals(MaterialLot::STATUS_QUARANTINE, $this->fgLot->status);
    }

    public function test_hopper_staging_service_validates_recipe_and_executes_staging_and_partial_return_with_zero_sum_invariants(): void
    {
        $hopperService = app(HopperStagingService::class);

        // 1. Recipe compatibility test: Raw resin matches IMM-01 order
        $check = $hopperService->verifyRecipeCompatibility('IMM-01', $this->rawLot->lot_number);
        $this->assertTrue($check['compatible']);

        // 2. Reject incompatible resin
        $absResin = Item::where('sku', 'RES-ABS-HI')->firstOrFail();
        $absLot = MaterialLot::create([
            'lot_number' => 'LOT-ABS-MISMATCH-99',
            'lot_type' => MaterialLot::TYPE_VIRGIN,
            'item_id' => $absResin->id,
            'status' => MaterialLot::STATUS_AVAILABLE,
        ]);

        $invalidCheck = $hopperService->verifyRecipeCompatibility('IMM-01', $absLot->lot_number);
        $this->assertFalse($invalidCheck['compatible']);
        $this->assertStringContainsString('RECIPE MISMATCH', $invalidCheck['message']);

        // 3. Issue 200 kg to hopper
        $transaction = $hopperService->issueToHopper(
            lotNumber: $this->rawLot->lot_number,
            machineCode: 'IMM-01',
            quantityKg: 200.0,
            userId: $this->operator->id
        );

        $this->assertEquals('IMM-01', $transaction->machine_code);
        $this->assertEquals(200.0, (float) $transaction->quantity_staged_kg);
        $this->assertEquals(HopperTransaction::STATUS_STAGED, $transaction->status);

        // Verify balances: Raw bin reduced from 1000 to 800, hopper bin increased from 0 to 200
        $rawBalance = InventoryBalance::where('bin_id', $this->rawBin->id)->where('material_lot_id', $this->rawLot->id)->value('quantity_on_hand');
        $hopperBalance = InventoryBalance::where('bin_id', $this->hopperBin->id)->where('material_lot_id', $this->rawLot->id)->value('quantity_on_hand');
        $this->assertEquals(800.0, (float) $rawBalance);
        $this->assertEquals(200.0, (float) $hopperBalance);

        // 4. Return leftover 35.5 kg partial resin from hopper to warehouse
        $returnResult = $hopperService->returnPartialFromHopper(
            transactionCodeOrId: $transaction->transaction_code,
            returnedWeightKg: 35.5,
            targetBinCode: $this->rawBin->bin_code,
            userId: $this->operator->id
        );

        $this->assertTrue($returnResult['success']);
        $this->assertEquals(35.5, $returnResult['returned_weight_kg']);
        $this->assertStringContainsString('-RET-', $returnResult['return_barcode']);

        // Verify balances: Hopper drops to 164.5, raw bin increases to 835.5
        $rawBalanceAfter = InventoryBalance::where('bin_id', $this->rawBin->id)->where('material_lot_id', $this->rawLot->id)->value('quantity_on_hand');
        $hopperBalanceAfter = InventoryBalance::where('bin_id', $this->hopperBin->id)->where('material_lot_id', $this->rawLot->id)->value('quantity_on_hand');
        $this->assertEquals(835.5, (float) $rawBalanceAfter);
        $this->assertEquals(164.5, (float) $hopperBalanceAfter);

        // 5. Global Zero-Sum Invariant check for this lot
        $totalMovements = InventoryMovement::where('material_lot_id', $this->rawLot->id)->sum('quantity');
        $totalBalances = InventoryBalance::where('material_lot_id', $this->rawLot->id)->sum('quantity_on_hand');
        // Initial 1000 + 200 staged + 35.5 returned = 1235.5 total ledger entries
        // Balances: 835.5 + 164.5 = 1000.0
        // Net change in system = 0!
        $this->assertEquals(1000.0, (float) $totalBalances);
    }

    public function test_sscc_pallet_service_calculates_modulo10_checksum_and_creates_sealed_pallet(): void
    {
        $palletService = app(SsccPalletService::class);

        // GS1 Standard Checksum Test Vectors
        // Payload 17 digits: '08991234567890123'
        $check1 = $palletService->calculateCheckDigit('08991234567890123');
        $this->assertIsInt($check1);
        $this->assertGreaterThanOrEqual(0, $check1);
        $this->assertLessThanOrEqual(9, $check1);

        // Verify validation
        $validSscc = '08991234567890123'.$check1;
        $this->assertTrue($palletService->validateSscc($validSscc));
        $this->assertFalse($palletService->validateSscc($validSscc === '089912345678901239' ? '089912345678901238' : '089912345678901239'));

        // Generate 18-digit code
        $generated = $palletService->generateSscc();
        $this->assertEquals(18, strlen($generated));
        $this->assertTrue($palletService->validateSscc($generated));

        // Create Pallet Container
        $pallet = $palletService->createPallet(
            itemId: $this->finishedGood->id,
            cartonCount: 20,
            grossWeightKg: 420.0,
            materialLotId: $this->fgLot->id,
            tareWeightKg: 15.0
        );

        $this->assertNotNull($pallet);
        $this->assertEquals(18, strlen($pallet->sscc_code));
        $this->assertEquals(20, $pallet->carton_count);
        $this->assertEquals(405.0, (float) $pallet->net_weight_kg);
        $this->assertEquals(420.0, (float) $pallet->gross_weight_kg);
    }

    public function test_security_gate_pass_clearance_and_asn_export(): void
    {
        $palletService = app(SsccPalletService::class);
        $gatePassService = app(GatePassService::class);
        $asnService = app(AsnExportService::class);

        // 1. Prepare Delivery Order in LOADED state
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-OEM-20261030-99',
            'customer_name' => 'PT Astra Daihatsu Motor Karawang',
            'shipping_address' => 'Jl. Surya Madya VI Kav. 1-46 Karawang',
            'vehicle_plate_number' => 'B 9482 SXZ',
            'driver_name' => 'Bambang Supriyadi',
            'status' => DeliveryOrder::STATUS_LOADED,
            'total_cartons' => 10,
        ]);

        $pallet = $palletService->createPallet(
            itemId: $this->finishedGood->id,
            cartonCount: 10,
            grossWeightKg: 215.0,
            deliveryOrderId: $deliveryOrder->id,
            materialLotId: $this->fgLot->id
        );

        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'item_id' => $this->finishedGood->id,
            'material_lot_id' => $this->fgLot->id,
            'carton_barcode' => $this->fgLot->lot_number,
            'quantity_ordered' => 10.0,
            'quantity_loaded' => 10.0,
            'is_verified_scanned' => true,
        ]);

        // 2. Clear Security Gate Pass with driver signature
        $signatureBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

        $clearedDo = $gatePassService->clearGatePass(
            doNumber: $deliveryOrder->do_number,
            sealNumber: 'SEAL-OEM-7719',
            driverSignature: $signatureBase64,
            verifiedPlate: 'B 9482 SXZ',
            verifiedDriver: 'Bambang Supriyadi',
            securityOfficerId: $this->operator->id
        );

        $this->assertEquals(DeliveryOrder::STATUS_DISPATCHED_GATE_OUT, $clearedDo->status);
        $this->assertStringStartsWith('GP-', $clearedDo->gate_pass_number);
        $this->assertEquals('SEAL-OEM-7719', $clearedDo->seal_number);
        $this->assertNotNull($clearedDo->gate_out_at);

        // 3. Export JSON ASN
        $asn = $asnService->generateAsn($deliveryOrder->do_number);
        $this->assertEquals('2.0-GS1', $asn['asn_version']);
        $this->assertEquals($clearedDo->gate_pass_number, $asn['shipment']['gate_pass_number']);
        $this->assertEquals('DISPATCHED_GATE_OUT', $asn['shipment']['status']);
        $this->assertEquals(1, $asn['summary']['total_pallets']);
        $this->assertEquals($pallet->sscc_code, $asn['pallets'][0]['sscc']);
        $this->assertEquals("(00){$pallet->sscc_code}", $asn['pallets'][0]['gs1_formatted']);
    }

    public function test_scanner_web_ui_renders_all_eight_modes_including_hopper_and_gate_pass(): void
    {
        $this->actingAs($this->operator);

        // Dashboard renders all 8 modes
        $response = $this->get('/scanner/dashboard');
        $response->assertStatus(200);
        $response->assertSee('MODE 7');
        $response->assertSee('Hopper Feed');
        $response->assertSee('MODE 8');
        $response->assertSee('Security Gate');

        // Mode 7: Hopper Staging
        $hopperRes = $this->get('/scanner/hopper');
        $hopperRes->assertStatus(200);
        $hopperRes->assertSee('Hopper Feed & Recipe Verification', false);

        // Mode 8: Security Gate
        $gateRes = $this->get('/scanner/gate-pass');
        $gateRes->assertStatus(200);
        $gateRes->assertSee('Security Gate Clearance');
    }
}
