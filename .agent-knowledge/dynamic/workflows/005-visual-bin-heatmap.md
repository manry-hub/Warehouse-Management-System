# Implementation Workflow 005: 2D Visual Warehouse Bin Topology & Capacity Heatmap

**Status:** `COMPLETE`  
**Target Outcome:** Production-grade interactive 2D Warehouse Bin Topology & Capacity Heatmap embedded natively into the Filament v3 Admin Panel (`Warehouse Topology` navigation). Provides plant managers and logistics supervisors with real-time visual rack elevation views, color-coded weight and volume utilization heatmaps, one-click bin inspection drawers showing stored inventory lots and FEFO dates, and instant bin blocking controls.  
**Owner:** `Logistics Engineering & Filament Admin Team`  
**References:**

- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md) (Core Scope #3 Warehouse Bin Topology Manager, Post-MVP #4 Rack Fill-Rates)
- [ARCHITECTURE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/ARCHITECTURE.md) (Subsystems & Filament Portal)
- [001-wms-core-implementation.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/001-wms-core-implementation.md)
- [003-regrind-scrap-and-blend-cap.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/003-regrind-scrap-and-blend-cap.md)

---

## 1. Objective and Scope

### Objective

Deliver an intuitive, real-time 2D visual representation of factory warehouse storage racks, staging buffers, and quarantine cages. Replace static tabular bin listings with visual rack elevations that immediately communicate capacity bottlenecks, empty slots, over-capacity risks, and quarantine holds in discrete plastic injection molding operations.

### In Scope

- **Domain Topology & Metrics Engine (`WarehouseHeatmapService`):**
  - Real-time computation of weight utilization ($(\text{stored\_kg} / \text{max\_weight\_kg}) \times 100$) and volume fill rate.
  - Bin status classification: `EMPTY` (0%), `OPTIMAL` (1–50%), `WARNING` (51–85%), `CRITICAL` (>85% or overweight), `BLOCKED`, and `QUARANTINE`.
  - Hierarchical spatial grouping: `Warehouse` $\rightarrow$ `Zone` $\rightarrow$ `Aisle` $\rightarrow$ `Rack Bay` $\rightarrow$ `Shelf (Vertical Level)` $\rightarrow$ `Bin (Position)`.
  - Zone-level KPI rollups: Total Bins, Occupancy Rate %, Total Stored Weight vs Total Maximum Capacity, Available Headroom (KG), and Blocked Slot Count.
- **Filament v3 Custom Heatmap Page (`WarehouseHeatmapPage`):**
  - Integrated into the Filament navigation under `Warehouse Topology`.
  - Interactive Livewire controls: Warehouse selector, Zone selector, SKU filter, and Status filter (All, Empty, Occupied, Critical, Blocked).
  - Zone KPI summary cards and color-coded status legend.
  - 2D rack elevation matrix rendering industrial shelving with top-to-bottom tiers and left-to-right positions.
- **Interactive Bin Detail Drawer / Modal:**
  - Click-to-inspect any bin tile without leaving the page.
  - Comprehensive inspection details: Bin coordinates, capacity gauges, and table of active `InventoryBalance` records (SKU, Lot #, Regrind vs Virgin badge, Expiry date, On-hand / Reserved / Available quantities).
  - Quick-action toggle: Block / Unblock bin directly from the inspection drawer with Filament notification.
- **Automated Verification Suite:**
  - Automated feature tests (`WarehouseHeatmapTest`) verifying calculation accuracy, filtering, Livewire reactivity, and bin state changes.

### Out of Scope

- 3D WebGL / Three.js digital twin rendering (Post-MVP #4).
- Automated AGV/AMR robot physical path routing.

### Assumptions and Open Questions

- In raw material zones, resin weight is measured in KG directly from `InventoryBalance.quantity_on_hand`. For finished goods and packaging, standard pack weights are converted to KG.
- Shelf racks follow vertical numbering where higher shelf numbers represent higher tiers.

---

## 2. Staged Execution Plan

### Stage 1: Domain Heatmap & Capacity Calculation Service

**Objective:** Build the core calculation engine that computes bin fill rates, status badges, and zone metrics.

1. Implement `App\Services\WarehouseHeatmapService`:
   - `getZoneMetrics(WarehouseZone $zone): array`
   - `getZoneTopology(WarehouseZone $zone, array $filters = []): array`
   - `getBinDetails(string $binId): array`
   - `toggleBinBlocked(string $binId): WarehouseBin`
2. Test calculations with unit/service checks against MariaDB.

**Exit Criteria:**

- [x] `WarehouseHeatmapService` accurately computes weight percentages, correctly flags empty, warning, critical, and blocked states, and returns structured aisle/rack/shelf hierarchy.

---

### Stage 2: Filament Custom Page & Interactive Livewire Controller

**Objective:** Implement the Filament v3 Page class with Livewire reactive state management.

1. Create `App\Filament\Pages\WarehouseHeatmapPage`:
   - Registered under `Warehouse Topology` navigation group with `heroicon-o-squares-2x2`.
   - Livewire properties: `$selectedWarehouseId`, `$selectedZoneId`, `$filterSku`, `$filterStatus`, `$selectedBinId`.
   - Livewire actions: `selectZone()`, `selectBin()`, `toggleBlocked()`, `closeDrawer()`.

**Exit Criteria:**

- [x] Page renders in Filament Admin panel at `/admin/visual-heatmap`.
- [x] Switching zones updates the topology and KPI metrics dynamically.

---

### Stage 3: Blade Elevation View & Interactive Drawer

**Objective:** Build the high-contrast 2D rack grid and slide-over inspection drawer.

1. Implement `resources/views/filament/pages/warehouse-heatmap.blade.php`:
   - Zone KPI summary widgets (Total Bins, Occupancy %, Stored Weight, Headroom).
   - Capacity legend bar with color-coded chips.
   - 2D Bay & Shelf grid:
     - Rack bay container with header (`Aisle A - Rack 01`).
     - Vertical shelf rows sorted descending (top shelf at top, ground shelf at bottom).
     - Bin tiles with fill-rate percentage, weight label, and active SKU chips.
   - Slide-over inspection drawer / modal:
     - Header with Bin Code, Zone, and Blocked status badge.
     - Progress bar indicating weight capacity utilization.
     - Stored lots table with SKU, lot number, quantities, and FEFO expiry.
     - Interactive action button to Block/Unblock the bin with immediate visual feedback.

**Exit Criteria:**

- [x] Rack bays render vertically organized shelves with correct color gradients.
- [x] Clicking a bin tile opens the detail drawer with real-time lot balances.
- [x] Toggling bin lock updates the database and refreshes the bin tile immediately.

---

### Stage 4: Automated Testing & Verification

**Objective:** Validate all aspects through automated feature tests.

1. Create `tests/Feature/WarehouseHeatmapTest.php`:
   - Verify `WarehouseHeatmapService` computations (empty, partial, critical >85%, blocked).
   - Verify Filament page loads with HTTP 200 for authenticated Warehouse Managers.
   - Verify Livewire component state transitions and bin block toggle.
2. Run full test suite (`php artisan test`) against MariaDB (`WMS_DB_TEST`).
3. Run `vendor/bin/pint` to maintain code standards.

**Exit Criteria:**

- [x] 100% of test assertions pass.
- [x] Zero linting or Pint formatting issues.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- Filament v3 panel and Livewire v3 runtime.
- Existing `WarehouseZone`, `WarehouseBin`, and `InventoryBalance` Eloquent models.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| **High Query Count (N+1) on Large Rack Grids** | Medium: Slow render if querying balances per bin individually. | Eager load `balances.lot`, `balances.item`, and `zone` with single aggregated query. |
| **Overweight Bin Safety Violations** | High: Structural rack failure if operator overloads heavy steel molds or resin pallets. | Visual critical alert (>85% amber, >100% flashing red) and explicit headroom indicator in KG. |

### Rollback or Recovery

- Custom page and service are additive. If disabled, existing `WarehouseBinResource` tabular views remain completely unaffected.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| **Heatmap Page Render** | HTTP Feature Test | `/admin/visual-heatmap` returns HTTP 200 for authenticated manager. |
| **Capacity Math** | Unit Test | Bin with 1,200 KG in 1,500 KG bin computes 80.0% fill rate and status `WARNING`. |
| **Blocked Bin State** | Livewire Test | Calling `toggleBlocked` updates bin `is_blocked` flag and returns `BLOCKED` status. |
| **Lot Drilldown** | Component Test | Selecting bin returns active `InventoryBalance` records with SKU and lot numbers. |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Task report `005-visual-bin-heatmap.md` created under `.agent-knowledge/dynamic/reports/tasks/`.
- [x] Living documentation updated.

**Next Transition:** Plant supervisors can visually inspect warehouse fill rates, spot congestion, and control bin availability.