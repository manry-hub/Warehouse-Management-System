<?php

namespace App\Filament\Widgets;

use App\Models\CycleCountOrder;
use App\Models\DeliveryOrder;
use App\Models\InventoryMovement;
use App\Models\Item;
use App\Models\MaterialLot;
use App\Models\QualityQuarantine;
use App\Models\Silo;
use App\Models\TransferOrder;
use App\Models\WarehouseBin;
use Filament\Widgets\Widget;

class ModuleLauncherWidget extends Widget
{
    protected static string $view = 'filament.widgets.module-launcher-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    public function getViewData(): array
    {
        // 1. Heatmap telemetry
        $totalBins = WarehouseBin::count();
        $occupiedBins = WarehouseBin::whereHas('balances', fn ($q) => $q->where('quantity_on_hand', '>', 0))->count();
        $blockedBins = WarehouseBin::where('is_blocked', true)->count();

        // 2. Traceability & Quarantine
        $activeQuarantines = QualityQuarantine::where('status', QualityQuarantine::STATUS_ACTIVE)->count();
        $totalLots = MaterialLot::count();

        // 3. Multi-Warehouse Transfers
        $inTransitTransfers = TransferOrder::where('status', TransferOrder::STATUS_IN_TRANSIT)->count();
        $totalTransfers = TransferOrder::count();

        // 4. Silo Farm Telemetry
        $totalSilos = Silo::count();
        $criticalSilos = Silo::where('status', Silo::STATUS_CRITICAL)
            ->orWhere(function ($q) {
                $q->whereNotNull('reorder_threshold_kg')
                    ->whereColumn('current_weight_kg', '<=', 'reorder_threshold_kg');
            })->count();
        $avgSiloLevel = $totalSilos > 0 ? round((float) Silo::avg('current_fill_percentage'), 1) : 0;

        // 5. Blind Stock Opname
        $activeAudits = CycleCountOrder::whereIn('status', [
            CycleCountOrder::STATUS_IN_PROGRESS,
            CycleCountOrder::STATUS_REVIEW,
        ])->count();

        // 6. Outbound Delivery Orders & Gate Passes
        $pendingGateOut = DeliveryOrder::whereIn('status', [
            DeliveryOrder::STATUS_LOADED,
            DeliveryOrder::STATUS_DISPATCHED,
        ])->whereNull('gate_pass_number')->count();
        $clearedGatePasses = DeliveryOrder::whereNotNull('gate_pass_number')->count();

        // 7. Inventory Movements Ledger
        $totalMovements = InventoryMovement::count();

        // 8. Items & Regrind
        $totalItems = Item::where('is_active', true)->count();
        $regrindLots = MaterialLot::where('lot_type', MaterialLot::TYPE_REGRIND)->count();

        return [
            'heatmap' => [
                'total_bins' => $totalBins,
                'occupied_bins' => $occupiedBins,
                'blocked_bins' => $blockedBins,
            ],
            'genealogy' => [
                'active_quarantines' => $activeQuarantines,
                'total_lots' => $totalLots,
            ],
            'transfers' => [
                'in_transit' => $inTransitTransfers,
                'total' => $totalTransfers,
            ],
            'silos' => [
                'total' => $totalSilos,
                'critical' => $criticalSilos,
                'avg_level' => $avgSiloLevel,
            ],
            'audits' => [
                'active' => $activeAudits,
            ],
            'shipping' => [
                'pending_gate_out' => $pendingGateOut,
                'cleared_gate_passes' => $clearedGatePasses,
            ],
            'movements' => [
                'total' => $totalMovements,
            ],
            'catalog' => [
                'total_items' => $totalItems,
                'regrind_lots' => $regrindLots,
            ],
        ];
    }
}
