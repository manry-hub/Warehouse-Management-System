# Implementation Workflow 010: Wave, Batch, and Pick-Path Routing Optimization

**Status:** `COMPLETE`  
**Target Outcome:** Production-grade implementation of Wave Picking, Batch Picking with Multi-Tote Segregation, and Serpentine S-Path Routing Optimization across Filament Admin and Rugged Mobile Scanner PWA.  
**Owner:** Core Architecture & Logistics Engineering  
**References:**
- [ADR-005: Wave, Batch, and Pick-Path Routing Architecture](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/ADR-005-wave-batch-picking-and-path-routing.md)
- [SPEC-005: Wave, Batch Picking, and S-Path Routing Interface Contract](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/SPEC-005-wave-batch-picking-and-routing-contract.md)
- [PRD Requirements](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md)
- [Workflow 009: Genealogy, Hopper Staging & Gate Pass](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/009-genealogy-hopper-staging-and-gate-pass.md)

---

## 1. Objective and Scope

### Objective
Upgrade warehouse order fulfillment from discrete single-order picking to consolidated Wave & Batch picking guided by an algorithmic S-Shape Serpentine pick-path router, cutting travel transit times and eliminating shipment sorting errors.

### In Scope
1. **Database Migrations & Eloquent Models:**
   - `picking_waves`, `picking_wave_orders`, `picking_batches`, `picking_batch_items`.
   - Models: `PickingWave`, `PickingWaveOrder`, `PickingBatch`, `PickingBatchItem`.
2. **Domain Services:**
   - `PickPathRoutingService`: Serpentine coordinate calculation (odd aisles ascending, even descending).
   - `WavePickingService`: Order consolidation, FEFO reservation, wave status FSM.
   - `BatchPickingService`: Multi-tote batch generation (up to 4 orders/totes), pick validation, double-entry inventory ledger deduction via `InventoryMovementService`.
3. **Filament Admin Management:**
   - `PickingWaveResource`: Wave creation, grouping of pending delivery orders, batch generation action, S-path visual route timeline.
4. **Rugged Scanner PWA Terminal:**
   - Mode 9 or upgraded Batch Pick interface with step-by-step guidance: (1) Bin scan $\to$ (2) Lot scan $\to$ (3) Put-to-Tote scan.
   - Audio-haptic sensory feedback for correct tote drops.
5. **Automated Verification:**
   - Unit tests for S-path ordering logic.
   - Feature tests for Wave lifecycle, multi-tote batch picking, and double-entry movement debit/credit assertions on MariaDB 11.8.

### Out of Scope
- Automated robotic crane AS/RS hardware communication.
- Automated conveyor sortation put-wall diverters.

### Assumptions and Open Questions
- Warehouse bins adhere to aisle-rack-shelf formatting (e.g. `A-01-02-B` where Aisle = `A`, Rack = `01`, Shelf = `02`, Position = `B`).
- Default batch cart supports up to 4 orders (totes `TOTE-01` through `TOTE-04`).

---

## 2. Staged Execution Plan

### Stage 1: Database Migrations & Domain Models
**Objective:** Establish relational persistence for waves, wave orders, batches, and batch items.
1. Create migration `2026_11_01_000014_create_wave_and_batch_picking_tables.php`.
2. Implement Eloquent models with relationships, casts, and scopes:
   - `App\Models\PickingWave.php`
   - `App\Models\PickingWaveOrder.php`
   - `App\Models\PickingBatch.php`
   - `App\Models\PickingBatchItem.php`
3. Run migrations on MariaDB 11.8.

**Exit Criteria:**
- [x] Database migrations execute cleanly.
- [x] Model relationships and status enums unit-tested.

### Stage 2: Pick-Path Routing & Wave Services
**Objective:** Implement serpentine S-path routing and wave consolidation logic.
1. Implement `App\Services\PickPathRoutingService.php`:
   - `sortSerpentine(Collection $items)`
   - `calculateEstimatedTravelDistance(Collection $items)`
2. Implement `App\Services\WavePickingService.php`:
   - `createWave(array $deliveryOrderIds, array $attributes)`
   - `releaseWave(PickingWave $wave)`
   - `cancelWave(PickingWave $wave)`
3. Implement `App\Services\BatchPickingService.php`:
   - `generateBatches(PickingWave $wave, int $maxOrdersPerBatch = 4)`
   - `confirmItemPick(...)` with double-entry ledger execution.

**Exit Criteria:**
- [x] Serpentine routing sorts alternating aisles correctly.
- [x] Wave release allocates lots via FEFO and creates batch items.
- [x] Double-entry ledger maintains zero discrepancy.

### Stage 3: Filament Admin Wave Management Resource
**Objective:** Provide warehouse dispatchers with full wave orchestration capabilities in Filament.
1. Create `App\Filament\Resources\PickingWaveResource.php` with:
   - Wave list table, priority badges, completion progress bars.
   - "Create Wave from Orders" wizard modal.
   - "Release Wave & Generate Batches" header action.
   - Relation managers or custom view for Batches & S-Path Pick Sequences.

**Exit Criteria:**
- [x] Dispatchers can create and release waves from Filament.
- [x] Batch items display in optimal S-path pick order.

### Stage 4: Rugged Scanner PWA Batch Pick Execution
**Objective:** Deliver an industrial, high-speed mobile UI for multi-tote batch picking.
1. Add REST API endpoints in `routes/api.php` under `/v1/scanner/batch-picking/*`.
2. Implement `App\Http\Controllers\Api\ScannerBatchPickingController.php`.
3. Add PWA frontend view in `resources/views/scanner/batch-picking.blade.php` and controller method in `ScannerWebController.php`.
4. Register the new capability in `ModuleLauncherWidget.php` and `Dashboard.php`.

**Exit Criteria:**
- [x] Scanner operators receive ordered picks with target tote display.
- [x] Mis-scanned bin, lot, or tote triggers auditory warning and prevents incorrect drop.

### Stage 5: Verification & Quality Assurance
**Objective:** Run exhaustive automated test suite and ensure zero regressions.
1. Write Feature tests in `tests/Feature/WaveBatchPickingTest.php`.
2. Run Laravel Pint style checks.
3. Execute full test suite on MariaDB 11.8.

**Exit Criteria:**
- [x] 100% passing tests.
- [x] Clean Pint formatting.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- Existing `DeliveryOrder`, `Item`, `MaterialLot`, `WarehouseBin`, and `InventoryMovementService`.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Bin names not parsing into aisle/rack | Pick-path falls back to random order | `PickPathRoutingService` includes robust regex parser with alphabetical fallback. |
| Operator drops item into wrong tote | Customer gets wrong items | Handheld scanner enforces mandatory `scanned_tote` barcode read before confirming pick. |

### Rollback or Recovery
Database migration has a clean `down()` method dropping batch and wave tables without affecting base delivery orders or inventory ledger tables.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| Migration Integrity | `php artisan migrate` | Clean execution on MariaDB 11.8. |
| Serpentine S-Path Logic | Unit test | Aisle A asc, Aisle B desc, Aisle C asc verified. |
| Zero-Sum Double-Entry Ledger | Feature test | `InventoryBalance` matches `InventoryMovement` sums. |
| Multi-Tote Scanner Flow | Feature test | Scanning wrong tote returns 422; correct tote confirms pick. |
| Full Suite Pass | `php artisan test` | All previous + new tests pass. |

---

## 5. Completion and Handoff

- [ ] All staged exit criteria and acceptance criteria are met.
- [ ] Relevant living documentation, ADRs, and specifications are updated or linked.
- [ ] The task report records actual changes and verification results.
