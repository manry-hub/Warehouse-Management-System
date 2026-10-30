# Task Report: 011 — Dynamic Min-Max Forward-Pick Bin Replenishment Engine

Date: 2026-10-30

## Checklist

- [x] Stage 1: Database Migrations & Domain Models (`warehouse_bins` extensions, `replenishment_tasks`)
- [x] Stage 2: Domain Service & Artisan Command (`ReplenishmentService`, `EvaluateReplenishmentsCommand`, `InventoryMovementService::recordReplenishment`)
- [x] Stage 3: Filament Admin Task Management Resource (`ReplenishmentTaskResource`, `WarehouseBinResource` pick-face parameters)
- [x] Stage 4: Rugged Scanner PWA Forklift Mode 10 (`ScannerReplenishmentController`, `replenishment.blade.php`, layout drawer and dashboard launcher chips)
- [x] Stage 5: Verification & Comprehensive Automated Tests (79 tests / 548 assertions passing on MariaDB 11.8)

## Change Description

Implemented Package B: Dynamic Min-Max Forward-Pick Bin Replenishment Engine to automate inventory top-off from high-bay bulk reserve storage to forward-pick bins, preventing order picker stockouts.
1. **Database Schema:** Created migration `2026_11_01_000015_create_replenishment_tables.php` extending `warehouse_bins` (`is_pick_face`, `min_stock_kg`, `max_stock_kg`) and creating `replenishment_tasks` table.
2. **Deficit Evaluation Daemon:** Implemented `ReplenishmentService::evaluateDeficits()` scanning forward-pick bins, calculating deficits against max thresholds, auto-selecting unquarantined FEFO bulk lots, and locking `quantity_reserved`.
3. **Double-Entry Movement Invariant:** Added `InventoryMovementService::recordReplenishment()` executing `InventoryMovement::TYPE_REPLENISHMENT`, relieving bulk reservations and crediting pick faces with zero balance discrepancy.
4. **Artisan CLI Command:** Built `php artisan wms:evaluate-replenishments` for automated scheduling and manual dispatcher execution.
5. **Filament Admin Management:** Built `ReplenishmentTaskResource` in `Fulfillment & Shipping` with priority badges, status filters, and "Scan Pick Face Deficits" header action.
6. **Rugged Mobile Scanner PWA Mode 10:** Built `/scanner/replenishment` for forklift operators featuring prioritized mission queues, two-step scan verification (Source Bulk Bin + Lot $\to$ Target Pick Bin), and acoustic feedback.

## Modified Files

- `database/migrations/2026_11_01_000015_create_replenishment_tables.php` — add
- `app/Models/ReplenishmentTask.php` — add
- `app/Models/WarehouseBin.php` — edit (added pick face fields and relation)
- `app/Models/InventoryMovement.php` — edit (added `TYPE_REPLENISHMENT`)
- `app/Services/ReplenishmentService.php` — add
- `app/Services/InventoryMovementService.php` — edit (added `recordReplenishment`)
- `app/Console/Commands/EvaluateReplenishmentsCommand.php` — add
- `app/Filament/Resources/ReplenishmentTaskResource.php` — add
- `app/Filament/Resources/ReplenishmentTaskResource/Pages/ListReplenishmentTasks.php` — add
- `app/Filament/Resources/ReplenishmentTaskResource/Pages/CreateReplenishmentTask.php` — add
- `app/Filament/Resources/ReplenishmentTaskResource/Pages/EditReplenishmentTask.php` — add
- `app/Filament/Resources/WarehouseBinResource.php` — edit (added pick face section and column)
- `app/Http/Controllers/Api/ScannerReplenishmentController.php` — add
- `app/Http/Controllers/ScannerWebController.php` — edit (added `replenishment`)
- `resources/views/scanner/modes/replenishment.blade.php` — add
- `resources/views/scanner/dashboard.blade.php` — edit (added Mode 10 card)
- `resources/views/scanner/layout.blade.php` — edit (added Mode 10 to drawer)
- `app/Filament/Widgets/ModuleLauncherWidget.php` — edit (added replenishment stats)
- `resources/views/filament/widgets/module-launcher-widget.blade.php` — edit (added Replenishment launcher card)
- `routes/api.php` — edit (added replenishment endpoints)
- `routes/web.php` — edit (added `/scanner/replenishment` route)
- `tests/Feature/ReplenishmentTest.php` — add

## Review

- Result: Complete
- Verification: 79 tests passing (548 assertions) across entire test suite; Pint passed with 0 violations.
- Follow-up: Ready for user walkthrough and git commit upon request.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Forward-pick bin deficit calculation | Pass |
| 2 | Automated FEFO bulk lot selection and reservation | Pass |
| 3 | Duplicate replenishment task prevention | Pass |
| 4 | Forklift scanner 2-step transfer confirmation (rejects wrong bulk bin, lot, target bin; confirms valid transfer) | Pass |
| 5 | Double-entry movement debit/credit integrity | Pass |
