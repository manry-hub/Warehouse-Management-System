# Implementation Workflow 002: Blind Cycle Counting & Stock Opname

**Status:** `COMPLETE`  
**Target Outcome:** Production-ready Blind Cycle Counting and Stock Opname subsystem (Epic 6 / US-014), providing snapshot-based blind counting, variance threshold analysis, supervisor approvals, automated double-entry ledger adjustments with pessimistic locking, Filament v3 reconciliation interfaces, and mobile scanner API endpoints.  
**Owner:** `Logistics Engineering & Inventory Audit Team`  
**References:**

- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md) (Epic 6 / US-014)
- [ARCHITECTURE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/ARCHITECTURE.md)
- [DATABASE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/DATABASE.md)
- [001-wms-core-implementation.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/001-wms-core-implementation.md)

---

## 1. Objective and Scope

### Objective

Deliver an enterprise-grade Stock Opname and Blind Cycle Counting engine for the Laravel 11 WMS that allows warehouse operators to verify physical inventory against digital balances without operational bias. The system must freeze balance snapshots upon count initiation, capture physical barcode scans blindly, compute quantity and percentage variances, enforce supervisor authorization gates for discrepancies, and record atomic double-entry `ADJUSTMENT` ledger transactions into `inventory_movements` and `inventory_balances`.

### In Scope

- **Relational Schema & Migrations:**
  - `cycle_count_orders` (order number, warehouse, target zone, count type, status, snapshot timestamp, assignment, approval metadata).
  - `cycle_count_items` (order reference, bin, item, material lot, recorded quantity snapshot, counted physical quantity, variance delta, status).
- **Core Domain Services:**
  - `CycleCountService`: Full lifecycle management (Draft $\rightarrow$ In Progress snapshot $\rightarrow$ Blind Count entry $\rightarrow$ Review/Recount $\rightarrow$ Supervisor Approval $\rightarrow$ Double-Entry Posting).
  - Extension of `InventoryMovementService`: Method `recordAdjustment` to apply positive (`ADJUSTMENT_IN`) and negative (`ADJUSTMENT_OUT`) transactions with strict MariaDB row locking (`lockForUpdate()`).
- **Filament v3 Management Interface:**
  - `CycleCountOrderResource`: Comprehensive resource for managing audit orders, viewing variance breakdown tables with colored indicators (surplus, shortage, match), triggering recount requests, and executing one-click adjustment posting.
- **Handheld Scanner REST API:**
  - `GET /api/v1/scanner/cycle-counts/active`: List open count orders assigned to current mobile terminal operator.
  - `GET /api/v1/scanner/cycle-counts/{id}/sheet`: Blind count items (bin barcode, item SKU, lot barcode, without disclosing recorded system quantity).
  - `POST /api/v1/scanner/cycle-counts/{id}/count`: Submit physical barcode scan and counted quantity.
- **Automated Verification Harness:**
  - Feature & unit test suite validating blind count secrecy, variance math, supervisor authorization, atomic ledger posting, and concurrency lock safety on MariaDB 11.8.

### Out of Scope

- General ledger dollar write-offs and tax depreciation reports (managed by upstream ERP).
- Automated drone / RFID portal autonomous continuous scanning (scanner barcodes and web entry only).

### Assumptions and Open Questions

- Database engine is MariaDB 11.8+ on `127.0.0.1:3306` with native UUID columns and InnoDB transactional row locking.
- All quantities and variances use decimal precision (`12, 3`) matching the existing inventory ledger.
- Blind count sheets strictly exclude `recorded_quantity` from API responses to mobile scanners to prevent confirmation bias.

---

## 2. Staged Execution Plan

### Stage 1: Relational Schema & Migration Architecture

**Objective:** Define and migrate tables for cycle count orders and items with UUID keys, foreign key constraints, and performance indexes.

1. Create migration `2026_11_01_000009_create_cycle_count_tables.php`:
   - `cycle_count_orders`: `id` (UUID), `count_number` (string unique), `warehouse_id`, `zone_id` (nullable), `count_type` (`BLIND`, `ADHOC`, `SCHEDULED`), `status` (`DRAFT`, `IN_PROGRESS`, `COUNTED`, `REVIEW`, `APPROVED`, `POSTED`, `CANCELLED`), `assigned_to` (user), `approved_by` (user), `snapshot_at`, `completed_at`, `posted_at`, `notes`.
   - `cycle_count_items`: `id` (UUID), `cycle_count_order_id`, `bin_id`, `item_id`, `material_lot_id`, `recorded_quantity`, `counted_quantity` (nullable), `variance_quantity` (nullable), `status` (`PENDING`, `COUNTED`, `RECOUNT_REQUESTED`, `APPROVED`), `counted_at`, `counter_notes`.
2. Implement Eloquent models `CycleCountOrder` and `CycleCountItem` with UUID traits, relationships, and status helper methods.
3. Run migrations on MariaDB (`WMS_DB` and `WMS_DB_TEST`).

**Exit Criteria:**

- [x] `php artisan migrate` executes cleanly on MariaDB 11.8 with all foreign key constraints and compound indexes active.

---

### Stage 2: Domain Services & Double-Entry Ledger Adjustments

**Objective:** Build `CycleCountService` and extend `InventoryMovementService` to handle balance snapshots, blind count submissions, discrepancy calculations, and atomic posting.

1. Extend `InventoryMovementService` with `recordAdjustment(WarehouseBin $bin, Item $item, MaterialLot $lot, float $varianceQty, string $referenceId, ?User $user, ?string $notes)`:
   - For positive variance ($>0$): create `ADJUSTMENT` movement from `null` to `$bin`, incrementing `quantity_on_hand` with `lockForUpdate()`.
   - For negative variance ($<0$): create `ADJUSTMENT` movement from `$bin` to `null`, decrementing `quantity_on_hand` with `lockForUpdate()`, throwing `InsufficientStockException` if stock dropped below the decrement.
2. Implement `CycleCountService`:
   - `createOrder(...)`: initialize order in `DRAFT`.
   - `startCount(CycleCountOrder $order)`: snapshot existing non-zero `inventory_balances` within target warehouse/zone into `cycle_count_items`, record `snapshot_at`, and transition to `IN_PROGRESS`.
   - `recordCount(CycleCountOrder $order, string $binId, string $lotId, float $countedQty, ?User $user, ?string $notes)`: update item, calculate `variance_quantity = counted_quantity - recorded_quantity`, check if all items are counted to transition to `COUNTED` or `REVIEW`.
   - `requestRecount(CycleCountItem $item)`: reset counted status to `RECOUNT_REQUESTED`.
   - `approveOrder(CycleCountOrder $order, User $supervisor)`: validate supervisor authority and approve order.
   - `postAdjustments(CycleCountOrder $order, User $supervisor)`: wrap in `DB::transaction()`, iterate through items with non-zero variance, execute `recordAdjustment`, set order to `POSTED` and record `posted_at`.

**Exit Criteria:**

- [x] Unit tests pass demonstrating balance snapshotting, discrepancy calculation, and atomic ledger posting.
- [x] Balanced double-entry movements created in `inventory_movements` matching net physical variance.

---

### Stage 3: Handheld Scanner REST API

**Objective:** Implement dedicated API endpoints for mobile scanner terminals enabling blind counting on shop floor and warehouse racks.

1. Implement `CycleCountApiController`:
   - `GET /api/v1/scanner/cycle-counts/active`: Returns active count orders assigned to the authenticated user.
   - `GET /api/v1/scanner/cycle-counts/{id}/sheet`: Returns list of bins and lots to count. **Crucial:** `recorded_quantity` is omitted from the JSON resource to enforce blind counting.
   - `POST /api/v1/scanner/cycle-counts/{id}/count`: Receives barcode scan (`bin_code`, `lot_number`, `counted_quantity`) and updates the count sheet item.
2. Register routes in `routes/api.php` under `auth:sanctum` middleware.

**Exit Criteria:**

- [x] Scanner API tests pass: recorded quantity is verified absent from scanner response payload.
- [x] Scan submissions correctly update count items and recalculate variance.

---

### Stage 4: Filament v3 Admin Reconciliation Interface

**Objective:** Provide warehouse managers and supervisors with an interactive UI to schedule counts, track progress, inspect variances, request recounts, and post ledger adjustments.

1. Implement `CycleCountOrderResource` with:
   - Form schema: Warehouse selector, Zone selector, Count Type, Assigned Operator, Notes.
   - Table columns: Count Number, Warehouse, Zone, Status badge, Items Count, Variance Count, Snapshot At, Assigned To.
   - Custom View page (`ViewCycleCountOrder`) with embedded live discrepancy table displaying:
     - Bin, Item SKU, Material Lot, Recorded Qty, Counted Qty, Variance Qty, Variance Status badge (Shortage / Surplus / Match).
   - Header & Table Actions:
     - `Start Count` (triggers snapshot).
     - `Recount Item` (flags specific lot for recount).
     - `Approve Order` (for supervisors).
     - `Post Adjustments` (confirms and executes ledger posting with confirmation modal).
2. Register permissions for `Warehouse Manager` and `QA Inspector`.

**Exit Criteria:**

- [x] Manager can initiate count order, view items, observe variance calculations, and execute ledger adjustments from Filament.

---

### Stage 5: Comprehensive Automated Testing & Verification

**Objective:** Rigorously verify all cycle counting behaviors, authorization gates, and edge cases.

1. Write `Tests\Feature\CycleCountTest`:
   - Blind snapshot creation from active inventory balances.
   - Secrecy check: Mobile API excludes `recorded_quantity`.
   - Discrepancy calculations (positive, negative, exact match).
   - Approval and ledger posting: verifies `inventory_movements` entries and updated `inventory_balances`.
   - Negative adjustment guard: prevents posting if balance went below zero due to concurrent transactions.
2. Run full test suite (`php artisan test`) against MariaDB 11.8.
3. Code formatting with Laravel Pint.

**Exit Criteria:**

- [x] 100% of test suites pass on MariaDB (`WMS_DB_TEST`).
- [x] Zero linting or Pint styling errors.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- MariaDB 11.8 running on `127.0.0.1:3306`.
- `InventoryMovementService` and existing `inventory_movements` and `inventory_balances` tables.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| **Concurrent Stock Movement during Count** | Medium: Stock moves between snapshot and posting, causing inaccurate adjustments. | Snapshot captures balance at $T_0$; adjustments apply exact delta ($counted - recorded$), and `lockForUpdate()` prevents underflow. Optional bin freeze flag. |
| **Confirmation Bias by Counter** | High: Counter copies expected quantity rather than counting physical bags. | Mobile API endpoint strictly omits `recorded_quantity` from payload. |
| **Accidental Over-Adjustment** | Critical: Supervisor posts unverified massive discrepancy. | Enforce supervisor approval step before posting; order status FSM blocks posting unapproved orders. |

### Rollback or Recovery

- Database migrations include complete `down()` methods dropping `cycle_count_items` and `cycle_count_orders`.
- If an adjustment posting fails midway, the entire operation is rolled back via `DB::transaction()`.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| **Migration Execution** | `php artisan migrate` | `cycle_count_orders` and `cycle_count_items` created cleanly on MariaDB. |
| **Blind Privacy** | Automated Feature Test | API endpoint `GET /api/v1/scanner/cycle-counts/{id}/sheet` never exposes `recorded_quantity`. |
| **Variance Calculation** | Unit Test | $counted - recorded$ correctly calculates surplus ($>0$) and shrinkage ($<0$). |
| **Double-Entry Ledger Integrity** | Automated Integration Test | Posting creates `ADJUSTMENT` entries in `inventory_movements` and correctly adjusts `inventory_balances`. |
| **Supervisor Gate** | Feature Test | Non-approved count orders cannot be posted. |
| **Filament Panel Navigation** | Browser / Resource check | `CycleCountOrderResource` displays cleanly in Filament navigation with full action controls. |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Task report `002-blind-cycle-counting.md` created under `.agent-knowledge/dynamic/reports/tasks/`.
- [x] Living documentation updated.

**Next Transition:** Subsystem fully verified on MariaDB 11.8 with 100% test pass rate. Ready for next subsystem implementation (e.g. Regrind Scrap Recycling & Blend Ratio Governance or Handheld Scanner Web UI).
