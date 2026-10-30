# Implementation Workflow 011: Dynamic Min-Max Forward-Pick Bin Replenishment Engine

**Status:** `COMPLETE`  
**Target Outcome:** Production-grade implementation of automated min-max forward-pick bin replenishment, deficit evaluation daemon, Filament task management, and rugged mobile scanner forklift terminal.  
**Owner:** Core Architecture & Logistics Engineering  
**References:**
- [ADR-006: Dynamic Min-Max Forward-Pick Bin Replenishment Architecture](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/ADR-006-dynamic-min-max-bin-replenishment.md)
- [SPEC-006: Bin Replenishment Contract](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/SPEC-006-bin-replenishment-contract.md)
- [Workflow 010: Wave, Batch, and Pick-Path Routing Optimization](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/010-wave-batch-and-pick-path-optimization.md)

---

## 1. Objective and Scope

### Objective
Automate internal warehouse inventory replenishment by calculating forward-pick bin deficits, automatically reserving FEFO bulk lots, and dispatching prioritized transport tasks to mobile forklift operators.

### In Scope
1. **Database Schema & Migrations:**
   - Add `is_pick_face`, `min_stock_kg`, and `max_stock_kg` to `warehouse_bins`.
   - Create `replenishment_tasks` table.
   - Model: `App\Models\ReplenishmentTask.php`.
2. **Domain Service & Artisan Command:**
   - `App\Services\ReplenishmentService.php`: Deficit calculation, duplicate prevention guard, FEFO bulk lot reservation, task assignment, and confirmation.
   - `InventoryMovementService::recordReplenishment()`: Double-entry movement with reservation relief.
   - Artisan command: `wms:evaluate-replenishments`.
3. **Filament Admin Management:**
   - `App\Filament\Resources\ReplenishmentTaskResource.php`: Priority badges, source/target bin indicators, manual deficit evaluation trigger action.
   - Update `WarehouseBinResource` with pick-face min/max form fields.
4. **Rugged Mobile Scanner PWA:**
   - Add Mode 10: **Forklift Replenishment** (`/scanner/replenishment`).
   - Two-step barcode validation (Source Bulk Bin + Lot $\to$ Target Pick Bin).
   - Audio-haptic sensory alerts.
5. **Testing & Quality Assurance:**
   - Feature tests covering deficit triggering, priority assignment, duplicate prevention, and double-entry ledger balance updates on MariaDB 11.8.

### Out of Scope
- Automated robotic crane direct PLC hardware communication.

---

## 2. Staged Execution Plan

### Stage 1: Database Migrations & Domain Models
**Objective:** Add replenishment fields to `warehouse_bins` and create `replenishment_tasks` table.
1. Create migration `2026_11_01_000015_create_replenishment_tables.php`.
2. Implement Eloquent model `App\Models\ReplenishmentTask.php` and update `WarehouseBin.php`.
3. Run migration on MariaDB 11.8.

**Exit Criteria:**
- [x] Database migration executed cleanly.
- [x] Model relationships, scopes, and casts verified.

### Stage 2: Replenishment Domain Service & Artisan Command
**Objective:** Implement automated deficit detection, FEFO bulk reservation, and double-entry movement ledger execution.
1. Implement `App\Services\ReplenishmentService.php`:
   - `evaluateDeficits()`
   - `assignTask()`
   - `confirmReplenishment()`
2. Add `recordReplenishment()` to `InventoryMovementService.php` and `TYPE_REPLENISHMENT` to `InventoryMovement.php`.
3. Create Artisan command `App\Console\Commands\EvaluateReplenishmentsCommand.php`.

**Exit Criteria:**
- [x] Deficit evaluation correctly identifies below-minimum pick bins.
- [x] Duplicate tasks prevented for identical bin & item.
- [x] Double-entry movement strictly balances without discrepancies.

### Stage 3: Filament Admin Task Management
**Objective:** Provide warehouse dispatchers with replenishment monitoring and manual evaluation controls in Filament.
1. Implement `App\Filament\Resources\ReplenishmentTaskResource.php`.
2. Update `WarehouseBinResource.php` form schema with pick-face fields.

**Exit Criteria:**
- [x] Dispatchers can view pending replenishment tasks, filter by priority, and trigger manual evaluation.

### Stage 4: Rugged Scanner PWA Forklift Terminal
**Objective:** Deliver an industrial mobile interface for forklift operators to execute directed bin-to-bin replenishments.
1. Implement `App\Http\Controllers\Api\ScannerReplenishmentController.php` with API routes.
2. Add scanner frontend view `resources/views/scanner/modes/replenishment.blade.php`.
3. Update `ScannerWebController.php`, `scanner/dashboard.blade.php`, and layout drawer with Mode 10.

**Exit Criteria:**
- [x] Forklift operators can accept tasks, scan source bulk bin & lot, scan target pick bin, and confirm replenishment.

### Stage 5: Verification & Quality Assurance
**Objective:** Ensure zero regressions across entire test suite.
1. Write Feature tests in `tests/Feature/ReplenishmentTest.php`.
2. Run Laravel Pint style checks.
3. Run full test suite on MariaDB 11.8.

**Exit Criteria:**
- [x] 100% passing tests.
- [x] 0 Pint violations.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- Existing `WarehouseBin`, `Item`, `MaterialLot`, `InventoryBalance`, `InventoryMovementService`.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Concurrent pickers deplete bulk lot while replenishment is pending | Forklift driver finds empty bulk bin | System automatically increments `quantity_reserved` on bulk balance upon task creation. |
| Repetitive evaluation creates duplicate tasks | Warehouse flooded with redundant tasks | Idempotent duplicate check checks for open (`PENDING`, `ASSIGNED`, `IN_TRANSIT`) tasks before spawning new one. |

### Rollback or Recovery
Migration has clean `down()` dropping `replenishment_tasks` and removing columns from `warehouse_bins`.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| Migration Integrity | `php artisan migrate` | Clean execution on MariaDB 11.8. |
| Deficit Calculation | Feature test | Creates task with deficit = `max_stock_kg - effective_stock`. |
| FEFO Bulk Lot Reservation | Feature test | Selects earliest unexpired lot and sets `quantity_reserved`. |
| Forklift Scan Validation | Feature test | Rejects mismatched bins/lots with 422; confirms valid transfer. |
| Zero Discrepancy Ledger | Feature test | Debits bulk balance, credits pick face balance, zero net variance. |
