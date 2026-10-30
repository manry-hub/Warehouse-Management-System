<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);

        $this->admin = User::where('email', 'admin@wms.local')->firstOrFail();
    }

    public function test_unauthenticated_user_redirected_to_filament_login(): void
    {
        $response = $this->get('/admin');
        $response->assertRedirect('/admin/login');
    }

    public function test_filament_dashboard_renders_kpi_overview_and_all_module_launcher_ctas(): void
    {
        $this->actingAs($this->admin);

        $response = $this->get('/admin');
        $response->assertStatus(200);

        // 1. Assert KPI Overview Stats
        $response->assertSee('Total Inventory On-Hand');
        $response->assertSee('Warehouse Occupancy');
        $response->assertSee('Pending Deliveries');
        $response->assertSee('Quality Quarantines');

        // 2. Assert Module Command Center Header
        $response->assertSee('Core Operations & Module Command Center', false);
        $response->assertSee('Launch Scanner PWA');

        // 3. Assert All 8 Module Launcher Cards and CTAs
        $response->assertSee('Visual 2D Heatmap');
        $response->assertSee('Open Heatmap &rarr;', false);

        $response->assertSee('Lot Genealogy & Recall', false);
        $response->assertSee('Launch Genealogy &rarr;', false);

        $response->assertSee('Rugged Scanner PWA');
        $response->assertSee('Open Scanner App &rarr;', false);

        $response->assertSee('Transfers & Cross-Dock', false);
        $response->assertSee('Transfer Orders &rarr;', false);

        $response->assertSee('Bulk Silo Farm');
        $response->assertSee('Monitor Silos &rarr;', false);

        $response->assertSee('Blind Cycle Counting');
        $response->assertSee('Audit Orders &rarr;', false);

        $response->assertSee('Delivery Orders & Gate Pass', false);
        $response->assertSee('Manage Orders &rarr;', false);

        $response->assertSee('Item Catalog & Scrap', false);
        $response->assertSee('View Catalog &rarr;', false);
    }
}
