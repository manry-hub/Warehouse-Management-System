<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\ItemCatalogSeeder;
use Database\Seeders\RoleAndPermissionSeeder;
use Database\Seeders\WarehouseTopologySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScannerWebUiTest extends TestCase
{
    use RefreshDatabase;

    protected User $handlerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleAndPermissionSeeder::class);
        $this->seed(WarehouseTopologySeeder::class);
        $this->seed(ItemCatalogSeeder::class);

        $this->handlerUser = User::where('email', 'handler@wms.local')->firstOrFail();
    }

    public function test_unauthenticated_scanner_redirects_to_login(): void
    {
        $response = $this->get('/scanner/dashboard');
        $response->assertRedirect('/scanner/login');

        $rootResponse = $this->get('/scanner');
        $rootResponse->assertRedirect('/scanner/login');
    }

    public function test_operator_can_authenticate_via_web_form(): void
    {
        $response = $this->post('/scanner/login', [
            'email' => 'handler@wms.local',
            'password' => 'password',
        ]);

        $response->assertRedirect('/scanner/dashboard');
        $this->assertAuthenticatedAs($this->handlerUser);
    }

    public function test_invalid_credentials_rejected(): void
    {
        $response = $this->post('/scanner/login', [
            'email' => 'handler@wms.local',
            'password' => 'wrong-pass',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_operator_can_access_dashboard(): void
    {
        $response = $this->actingAs($this->handlerUser)->get('/scanner/dashboard');

        $response->assertStatus(200)
            ->assertSee('Plant WH-MAIN')
            ->assertSee('Dock Receiving')
            ->assertSee('Guided Putaway')
            ->assertSee('Machine Staging')
            ->assertSee('Regrind Recycling')
            ->assertSee('Blind Cycle Count')
            ->assertSee('Truck Loading');
    }

    public function test_all_six_scanner_mode_views_render_successfully(): void
    {
        $modes = [
            '/scanner/receiving' => 'Inbound Dock Receiving',
            '/scanner/putaway' => 'Guided Putaway',
            '/scanner/staging' => 'Machine Staging',
            '/scanner/regrind' => 'Regrind Scrap Intake',
            '/scanner/cycle-count' => 'Blind Cycle Count',
            '/scanner/shipping' => 'Truck Loading',
        ];

        foreach ($modes as $uri => $expectedTitle) {
            $resp = $this->actingAs($this->handlerUser)->get($uri);
            $resp->assertStatus(200)
                ->assertSee($expectedTitle);
        }
    }

    public function test_pwa_manifest_is_accessible_and_valid(): void
    {
        $response = $this->get('/scanner-manifest.json');

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Smart WMS Mobile Scanner',
                'short_name' => 'WMS Scanner',
                'display' => 'standalone',
                'start_url' => '/scanner',
            ]);
    }

    public function test_operator_logout_terminates_session(): void
    {
        $response = $this->actingAs($this->handlerUser)->post('/scanner/logout');

        $response->assertRedirect('/scanner/login');
        $this->assertGuest();
    }
}
