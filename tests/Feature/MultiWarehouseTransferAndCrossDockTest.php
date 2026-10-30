<?php

namespace Tests\Feature;

use App\Filament\Resources\TransferOrderResource\Pages\ListTransferOrders;
use App\Models\DeliveryOrder;
use App\Models\DeliveryOrderItem;
use App\Models\InventoryBalance;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\TransferOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseBin;
use App\Models\WarehouseZone;
use App\Services\CrossDockService;
use App\Services\InventoryMovementService;
use App\Services\TransferOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MultiWarehouseTransferAndCrossDockTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Warehouse $originWh;

    private Warehouse $destWh;

    private Warehouse $transitWh;

    private WarehouseBin $originBin;

    private WarehouseBin $transitBin;

    private WarehouseBin $destStorageBin;

    private WarehouseBin $destStagingBin;

    private Item $itemPart;

    private MaterialLot $lot;

    private TransferOrderService $transferService;

    private CrossDockService $crossDockService;

    private InventoryMovementService $movementService;

    protected function setUp(): void
    {
        parent::setUp();

        $role = Role::firstOrCreate(['name' => 'Warehouse Manager', 'guard_name' => 'web']);
        $this->manager = User::factory()->create([
            'email' => 'manager.xfer@wms.local',
        ]);
        $this->manager->assignRole($role);

        // 1. Warehouses
        $this->originWh = Warehouse::create([
            'code' => 'WH-ORIGIN',
            'name' => 'Main Manufacturing Plant',
            'is_active' => true,
        ]);

        $this->destWh = Warehouse::create([
            'code' => 'WH-DEST',
            'name' => 'Regional Distribution Hub',
            'is_active' => true,
        ]);

        $this->transitWh = Warehouse::create([
            'code' => 'WH-TRANSIT',
            'name' => 'In-Transit Moving Freight',
            'is_active' => true,
        ]);

        // 2. Zones & Bins
        $originZone = WarehouseZone::create([
            'warehouse_id' => $this->originWh->id,
            'code' => 'ZONE-ORIGIN-FG',
            'name' => 'Origin FG Storage',
            'zone_type' => WarehouseZone::TYPE_FINISHED_GOODS,
        ]);

        $this->originBin = WarehouseBin::create([
            'zone_id' => $originZone->id,
            'bin_code' => 'ORIGIN-BIN-01',
            'aisle' => 'A',
            'rack' => '1',
            'shelf' => '1',
            'bin' => '1',
            'max_weight_kg' => 5000.00,
        ]);

        $transitZone = WarehouseZone::create([
            'warehouse_id' => $this->transitWh->id,
            'code' => 'ZONE-INTRANSIT',
            'name' => 'Transit Freight',
            'zone_type' => WarehouseZone::TYPE_IN_TRANSIT,
        ]);

        $this->transitBin = WarehouseBin::create([
            'zone_id' => $transitZone->id,
            'bin_code' => 'BIN-TRANSIT',
            'aisle' => 'TR',
            'rack' => 'TR',
            'shelf' => '1',
            'bin' => '1',
            'max_weight_kg' => 999999.00,
        ]);

        $destStorageZone = WarehouseZone::create([
            'warehouse_id' => $this->destWh->id,
            'code' => 'ZONE-DEST-FG',
            'name' => 'Dest FG Storage',
            'zone_type' => WarehouseZone::TYPE_FINISHED_GOODS,
        ]);

        $this->destStorageBin = WarehouseBin::create([
            'zone_id' => $destStorageZone->id,
            'bin_code' => 'DEST-RACK-01',
            'aisle' => 'A',
            'rack' => '1',
            'shelf' => '1',
            'bin' => '1',
            'max_weight_kg' => 5000.00,
        ]);

        $destStagingZone = WarehouseZone::create([
            'warehouse_id' => $this->destWh->id,
            'code' => 'ZONE-DEST-STG',
            'name' => 'Dest Outbound Staging',
            'zone_type' => WarehouseZone::TYPE_STAGING_BUFFER,
        ]);

        $this->destStagingBin = WarehouseBin::create([
            'zone_id' => $destStagingZone->id,
            'bin_code' => 'DEST-STG-BAY-01',
            'aisle' => 'STG',
            'rack' => 'BAY',
            'shelf' => '1',
            'bin' => '1',
            'max_weight_kg' => 5000.00,
        ]);

        // 3. Item & Initial Stock
        $this->itemPart = Item::create([
            'sku' => 'PART-TEST-BZL',
            'name' => 'Front Bumper Bezel Test',
            'category' => Item::CATEGORY_FINISHED_GOOD,
            'unit_of_measure' => 'PCS',
            'standard_pack_qty' => 50.00,
            'minimum_safety_stock' => 100.00,
            'shelf_life_days' => 720,
            'is_active' => true,
        ]);

        $this->lot = MaterialLot::create([
            'lot_number' => 'LOT-FG-TEST-001',
            'item_id' => $this->itemPart->id,
            'initial_quantity' => 500.00,
            'current_quantity' => 500.00,
            'status' => MaterialLot::STATUS_AVAILABLE,
            'received_date' => now()->subDays(5),
            'expiry_date' => now()->addDays(365),
        ]);

        // Seed initial balance at origin bin
        InventoryBalance::create([
            'bin_id' => $this->originBin->id,
            'item_id' => $this->itemPart->id,
            'material_lot_id' => $this->lot->id,
            'quantity_on_hand' => 500.00,
            'quantity_reserved' => 0.00,
        ]);

        $this->movementService = app(InventoryMovementService::class);
        $this->crossDockService = app(CrossDockService::class);
        $this->transferService = app(TransferOrderService::class);
    }

    public function test_transfer_order_creation_and_approval(): void
    {
        // 1. Rejects identical origin and destination
        $this->expectException(InvalidArgumentException::class);
        $this->transferService->createOrder([
            'origin_warehouse_id' => $this->originWh->id,
            'destination_warehouse_id' => $this->originWh->id,
        ], []);
    }

    public function test_transfer_order_creation_success_and_approval(): void
    {
        $order = $this->transferService->createOrder(
            data: [
                'origin_warehouse_id' => $this->originWh->id,
                'destination_warehouse_id' => $this->destWh->id,
                'notes' => 'Stock rebalance to regional hub',
            ],
            items: [
                [
                    'item_id' => $this->itemPart->id,
                    'material_lot_id' => $this->lot->id,
                    'origin_bin_id' => $this->originBin->id,
                    'quantity' => 200.00,
                ],
            ],
            user: $this->manager
        );

        $this->assertEquals(TransferOrder::STATUS_DRAFT, $order->status);
        $this->assertCount(1, $order->items);

        $this->transferService->approveOrder($order);
        $this->assertEquals(TransferOrder::STATUS_APPROVED, $order->status);
    }

    public function test_transfer_order_dispatch_moves_inventory_to_virtual_transit_bin(): void
    {
        $order = $this->transferService->createOrder(
            data: [
                'origin_warehouse_id' => $this->originWh->id,
                'destination_warehouse_id' => $this->destWh->id,
            ],
            items: [
                [
                    'item_id' => $this->itemPart->id,
                    'material_lot_id' => $this->lot->id,
                    'origin_bin_id' => $this->originBin->id,
                    'quantity' => 200.00,
                ],
            ],
            user: $this->manager
        );
        $this->transferService->approveOrder($order);

        // Pre-dispatch system total
        $preDispatchTotal = InventoryBalance::sum('quantity_on_hand');
        $this->assertEquals(500.00, (float) $preDispatchTotal);

        $this->transferService->dispatchOrder(
            order: $order,
            carrierName: 'PT Pos Logistik Shuttle',
            plateNumber: 'B 1234 XYZ',
            waybillNumber: 'BOL-TEST-001',
            user: $this->manager
        );

        // Check order state
        $order->refresh();
        $this->assertEquals(TransferOrder::STATUS_IN_TRANSIT, $order->status);
        $this->assertNotNull($order->dispatched_at);
        $this->assertEquals('B 1234 XYZ', $order->vehicle_plate_number);

        // Origin balance decremented: 500 - 200 = 300
        $originBal = InventoryBalance::where('bin_id', $this->originBin->id)->first();
        $this->assertEquals(300.00, (float) $originBal->quantity_on_hand);

        // Transit balance incremented: 200
        $transitBal = InventoryBalance::where('bin_id', $this->transitBin->id)->first();
        $this->assertEquals(200.00, (float) $transitBal->quantity_on_hand);

        // Mathematical Ledger Invariant: Total system stock MUST remain 500
        $postDispatchTotal = InventoryBalance::sum('quantity_on_hand');
        $this->assertEquals(500.00, (float) $postDispatchTotal);

        // Movement logged
        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_TRANSFER_OUT,
            'source_bin_id' => $this->originBin->id,
            'destination_bin_id' => $this->transitBin->id,
            'quantity' => 200.00,
            'reference_id' => $order->order_number,
        ]);
    }

    public function test_transfer_order_destination_receipt_standard_putaway(): void
    {
        $order = $this->transferService->createOrder(
            data: [
                'origin_warehouse_id' => $this->originWh->id,
                'destination_warehouse_id' => $this->destWh->id,
            ],
            items: [
                [
                    'item_id' => $this->itemPart->id,
                    'material_lot_id' => $this->lot->id,
                    'origin_bin_id' => $this->originBin->id,
                    'quantity' => 200.00,
                ],
            ],
            user: $this->manager
        );
        $this->transferService->approveOrder($order);
        $this->transferService->dispatchOrder(
            order: $order,
            carrierName: 'Carrier 01',
            plateNumber: 'B 9999 AA',
            waybillNumber: 'BOL-002',
            user: $this->manager
        );

        // Receive at destination (standard rack putaway)
        $result = $this->transferService->receiveOrder(
            order: $order,
            customBinMappings: [$this->itemPart->id => $this->destStorageBin->id],
            user: $this->manager
        );

        $order->refresh();
        $this->assertEquals(TransferOrder::STATUS_COMPLETED, $order->status);
        $this->assertNotNull($order->received_at);
        $this->assertCount(0, $result['cross_docks']);

        // Transit balance cleared: 0
        $transitBal = InventoryBalance::where('bin_id', $this->transitBin->id)->first();
        $this->assertEquals(0.00, (float) $transitBal->quantity_on_hand);

        // Destination rack credited: 200
        $destBal = InventoryBalance::where('bin_id', $this->destStorageBin->id)->first();
        $this->assertEquals(200.00, (float) $destBal->quantity_on_hand);

        // Total system stock remains 500
        $this->assertEquals(500.00, (float) InventoryBalance::sum('quantity_on_hand'));

        $this->assertDatabaseHas('inventory_movements', [
            'movement_type' => InventoryMovement::TYPE_TRANSFER_IN,
            'source_bin_id' => $this->transitBin->id,
            'destination_bin_id' => $this->destStorageBin->id,
            'quantity' => 200.00,
        ]);
    }

    public function test_cross_docking_putaway_bypass_routes_directly_to_staging_dock_and_reserves_inventory(): void
    {
        // 1. Setup pending Delivery Order at destination waiting for PART-TEST-BZL
        $deliveryOrder = DeliveryOrder::create([
            'do_number' => 'DO-XDOCK-TEST-001',
            'customer_name' => 'PT Astra Honda Motor',
            'shipping_address' => 'Sunter Plant 3, Jakarta',
            'vehicle_plate_number' => 'B 9876 KDA',
            'driver_name' => 'Budi Santoso',
            'status' => DeliveryOrder::STATUS_DRAFT,
            'total_cartons' => 2,
        ]);

        DeliveryOrderItem::create([
            'delivery_order_id' => $deliveryOrder->id,
            'item_id' => $this->itemPart->id,
            'carton_barcode' => 'CTN-XDOCK-01',
            'quantity_ordered' => 150.00,
            'quantity_loaded' => 0.00,
            'is_verified_scanned' => false,
        ]);

        // 2. Dispatch Transfer of 150 pcs from origin
        $order = $this->transferService->createOrder(
            data: [
                'origin_warehouse_id' => $this->originWh->id,
                'destination_warehouse_id' => $this->destWh->id,
            ],
            items: [
                [
                    'item_id' => $this->itemPart->id,
                    'material_lot_id' => $this->lot->id,
                    'origin_bin_id' => $this->originBin->id,
                    'quantity' => 150.00,
                ],
            ],
            user: $this->manager
        );
        $this->transferService->approveOrder($order);
        $this->transferService->dispatchOrder(
            order: $order,
            carrierName: 'Carrier Express',
            plateNumber: 'B 5555 ZZ',
            waybillNumber: 'BOL-XDOCK-01',
            user: $this->manager
        );

        // 3. Receive Transfer Order -> triggers Cross-Dock bypass!
        $result = $this->transferService->receiveOrder(order: $order, user: $this->manager);

        // Verify cross-dock detected
        $this->assertCount(1, $result['cross_docks']);
        $this->assertEquals('DO-XDOCK-TEST-001', $result['cross_docks'][0]['delivery_order']);
        $this->assertEquals($this->destStagingBin->bin_code, $result['cross_docks'][0]['staging_bin']);

        // Stock must be in staging bay, NOT standard storage rack
        $stagingBal = InventoryBalance::where('bin_id', $this->destStagingBin->id)->first();
        $this->assertNotNull($stagingBal);
        $this->assertEquals(150.00, (float) $stagingBal->quantity_on_hand);
        $this->assertEquals(150.00, (float) $stagingBal->quantity_reserved); // Reserved for the DO!

        // Destination rack must have 0
        $rackBal = InventoryBalance::where('bin_id', $this->destStorageBin->id)->first();
        $this->assertNull($rackBal);

        // Delivery order line is matched
        $doItem = DeliveryOrderItem::where('delivery_order_id', $deliveryOrder->id)->first();
        $this->assertTrue($doItem->is_verified_scanned);
        $this->assertEquals($this->lot->id, $doItem->material_lot_id);
        $this->assertEquals($this->destStagingBin->id, $doItem->source_bin_id);

        // Delivery order status promoted to PICKING
        $deliveryOrder->refresh();
        $this->assertEquals(DeliveryOrder::STATUS_PICKING, $deliveryOrder->status);
    }

    public function test_filament_transfer_order_resource_renders_and_executes_actions(): void
    {
        $this->actingAs($this->manager);

        $order = TransferOrder::create([
            'order_number' => 'XFER-TEST-LIVEWIRE-01',
            'origin_warehouse_id' => $this->originWh->id,
            'destination_warehouse_id' => $this->destWh->id,
            'status' => TransferOrder::STATUS_DRAFT,
        ]);

        Livewire::test(ListTransferOrders::class)
            ->assertSuccessful()
            ->assertSee('XFER-TEST-LIVEWIRE-01')
            ->callTableAction('approve', $order);

        $order->refresh();
        $this->assertEquals(TransferOrder::STATUS_APPROVED, $order->status);
    }
}
