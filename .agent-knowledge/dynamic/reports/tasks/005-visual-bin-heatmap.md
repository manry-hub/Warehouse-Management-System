# 005 — 2D Visual Warehouse Bin Topology & Capacity Heatmap

Date: 2026-10-30

## Checklist

- [x] Build `WarehouseHeatmapService` to compute real-time bin weight utilization, status classification, zone KPI rollups, and natural hierarchical topology
- [x] Implement Filament v3 Custom Page `WarehouseHeatmapPage` registered under `Warehouse Topology`
- [x] Build interactive Blade elevation view with zone KPI cards, capacity legend, industrial rack bay containers, vertical top-to-bottom tiers, and bin tiles
- [x] Build slide-over inspection drawer displaying real-time stored inventory lots, SKU tags, regrind generation, machine origin, FEFO dates, and headroom
- [x] Implement direct bin lock/block action from drawer with instant notification and visual state refresh
- [x] Add header action button on `ListWarehouseBins` table linking directly to the visual heatmap
- [x] Implement comprehensive automated test suite `Tests\Feature\WarehouseHeatmapTest`
- [x] Format all code with Laravel Pint

## Change Description

Implemented an interactive, real-time 2D Warehouse Bin Topology & Capacity Heatmap directly embedded into the Filament v3 administrative portal. This provides plant managers and logistics supervisors with a high-contrast visual rack elevation interface to replace static tabular listings.

Key architectural features include:
1. **Dynamic Capacity Calculation:** Automatically aggregates inventory lot weights per bin, comparing against configured `max_weight_kg` and `max_volume_cbm` constraints, classifying bins into `EMPTY` (0%), `OPTIMAL` (1–50%), `WARNING` (51–85%), `CRITICAL` (>85%), `BLOCKED`, and `QUARANTINE`.
2. **Spatial 2D Topology:** Organizes bins by Warehouse $\rightarrow$ Zone $\rightarrow$ Aisle $\rightarrow$ Bay $\rightarrow$ Shelves (vertical tiers sorted top to bottom, mimicking physical racks) $\rightarrow$ Bin positions (left to right).
3. **Livewire Reactivity & Filter Controls:** Operators can filter by Warehouse, Zone, SKU text, and occupancy status with instant client-side updates.
4. **Slide-over Inspection Drawer:** Clicking any bin reveals detailed coordinates, weight headroom gauges, and a complete breakdown of stored `InventoryBalance` records with lot numbers, regrind generations, machine origins, and FEFO expiry dates.
5. **Instant Operational Lock:** One-click bin toggle to block/unblock a damaged or reserved slot directly from the drawer.

## Modified Files

- `app/Services/WarehouseHeatmapService.php` — add
- `app/Filament/Pages/WarehouseHeatmapPage.php` — add
- `resources/views/filament/pages/warehouse-heatmap.blade.php` — add (revised with native `<x-filament::modal slide-over>` and borderless grayish fills)
- `tailwind.config.js` — edit
- `DESIGN.md` — add
- `.agent-knowledge/dynamic/reports/LESSON.md` — edit
- `app/Filament/Resources/WarehouseBinResource/Pages/ListWarehouseBins.php` — edit
- `tests/Feature/WarehouseHeatmapTest.php` — add
- `.agent-knowledge/dynamic/workflows/005-visual-bin-heatmap.md` — add
- `.agent-knowledge/dynamic/reports/tasks/005-visual-bin-heatmap.md` — add

## Review

- Result: 2D Visual Warehouse Heatmap successfully delivered and integrated into Filament v3 portal.
- Verification: 6 new feature tests (54 assertions) and full test suite with 43 tests (252 assertions) passing green on MariaDB 11.8 (`WMS_DB_TEST`). Laravel Pint code style checks passed cleanly.
- Follow-up: None. Ready for Workflow 006 (Silo Ultrasonic/Radar Level Telemetry Ingestion).

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Zone KPI calculations accurately sum total bins, occupancy %, stored weight kg, and available headroom | Pass |
| 2 | Bin fill percentage and statuses (EMPTY, OPTIMAL, WARNING, CRITICAL, BLOCKED) evaluate accurately | Pass |
| 3 | 2D rack elevation renders vertical shelf tiers descending (top tier first) and bin positions ascending | Pass |
| 4 | SKU search and occupancy status filters correctly isolate candidate rack bays and bins | Pass |
| 5 | Selecting a bin tile opens slide-over drawer displaying stored lots, regrind generations, and machine origin | Pass |
| 6 | Toggling bin lock updates `is_blocked` in MariaDB, dispatches Filament notification, and updates UI | Pass |
| 7 | Filament custom page `/admin/visual-heatmap` returns HTTP 200 for authenticated Warehouse Managers | Pass |

## Revision (Heatmap Visual Overhaul & Warehouse-Level View)

- Simplified navigation controls to filter solely per Warehouse facility, rendering all facility zones in a unified floor plan.
- Overhauled heatmap color palette to vibrant solid thermal heat colors (0% dark gray, 1-25% emerald, 26-50% vivid green, 51-70% bright yellow, 71-85% orange, >85% fiery red, locked purple, quarantine amber) with bold centered numbers matching real warehouse floorplan blueprints.
- Added 2D Floor Plan Matrix mode alongside Rack Bay Elevation mode.
- Upgraded top summary metrics to native Filament 3.x `Stat::make()` widgets.
- Removed all white borders, rings, and outlines (`!ring-0 !border-0`) across sections, cards, and modal elements.
- Fixed dark-mode slide-over drawer white glare boxes on spatial coordinates and stored lots by using pure dark containers.
