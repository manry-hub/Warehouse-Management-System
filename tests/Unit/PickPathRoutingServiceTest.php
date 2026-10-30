<?php

namespace Tests\Unit;

use App\Models\WarehouseBin;
use App\Services\PickPathRoutingService;
use PHPUnit\Framework\TestCase;

class PickPathRoutingServiceTest extends TestCase
{
    public function test_serpentine_routing_sorts_odd_aisles_ascending_and_even_aisles_descending(): void
    {
        $service = new PickPathRoutingService;

        // Create mock bins:
        // Aisle A (odd, index 0): Racks 3, 1, 2
        // Aisle B (even, index 1): Racks 1, 3, 2
        $binA3 = new WarehouseBin(['bin_code' => 'A-03-01', 'aisle' => 'A', 'rack' => '3', 'shelf' => '1']);
        $binA1 = new WarehouseBin(['bin_code' => 'A-01-01', 'aisle' => 'A', 'rack' => '1', 'shelf' => '1']);
        $binA2 = new WarehouseBin(['bin_code' => 'A-02-01', 'aisle' => 'A', 'rack' => '2', 'shelf' => '1']);

        $binB1 = new WarehouseBin(['bin_code' => 'B-01-01', 'aisle' => 'B', 'rack' => '1', 'shelf' => '1']);
        $binB3 = new WarehouseBin(['bin_code' => 'B-03-01', 'aisle' => 'B', 'rack' => '3', 'shelf' => '1']);
        $binB2 = new WarehouseBin(['bin_code' => 'B-02-01', 'aisle' => 'B', 'rack' => '2', 'shelf' => '1']);

        $unoptimized = collect([$binB1, $binA3, $binB3, $binA1, $binB2, $binA2]);

        $sorted = $service->sortSerpentine($unoptimized);

        $codes = $sorted->pluck('bin_code')->all();

        // Expected Serpentine Order:
        // Aisle A ascending: A-01-01 -> A-02-01 -> A-03-01
        // Aisle B descending: B-03-01 -> B-02-01 -> B-01-01
        $this->assertEquals([
            'A-01-01',
            'A-02-01',
            'A-03-01',
            'B-03-01',
            'B-02-01',
            'B-01-01',
        ], $codes);

        // Check pick sequence assigned
        $sequences = $sorted->pluck('pick_sequence')->all();
        $this->assertEquals([1, 2, 3, 4, 5, 6], $sequences);
    }

    public function test_efficiency_gain_calculation(): void
    {
        $service = new PickPathRoutingService;

        $binA1 = new WarehouseBin(['bin_code' => 'A-01-01', 'aisle' => 'A', 'rack' => '1', 'shelf' => '1']);
        $binA5 = new WarehouseBin(['bin_code' => 'A-05-01', 'aisle' => 'A', 'rack' => '5', 'shelf' => '1']);
        $binB1 = new WarehouseBin(['bin_code' => 'B-01-01', 'aisle' => 'B', 'rack' => '1', 'shelf' => '1']);
        $binB5 = new WarehouseBin(['bin_code' => 'B-05-01', 'aisle' => 'B', 'rack' => '5', 'shelf' => '1']);

        // Chaotic path: A1 -> B5 -> A5 -> B1
        $chaotic = collect([$binA1, $binB5, $binA5, $binB1]);
        // Serpentine path: A1 -> A5 -> B5 -> B1
        $serpentine = collect([$binA1, $binA5, $binB5, $binB1]);

        $metrics = $service->calculateEfficiencyGain($chaotic, $serpentine);

        $this->assertGreaterThan(0, $metrics['meters_saved']);
        $this->assertGreaterThan(0, $metrics['percent_savings']);
    }
}
