# Implementation Workflow 003: Regrind Scrap Recycling & Blend Cap Engine

**Status:** `COMPLETE`  
**Target Outcome:** Production-grade runner scrap regrind recycling and blend cap governance engine (Epic 3 / US-008), enabling granulator intake of chopped runners, traceability link to parent production runs, strict blend cap enforcement (10–20% maximum regrind ratio) in the FEFO material staging picker, mobile scanner intake endpoints, and Filament back-office tracking.  
**Owner:** `Materials Management & Quality Engineering Team`  
**References:**

- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md) (Epic 3 / US-008)
- [ARCHITECTURE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/ARCHITECTURE.md)
- [DATABASE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/DATABASE.md)
- [001-wms-core-implementation.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/001-wms-core-implementation.md)
- [002-blind-cycle-counting.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/002-blind-cycle-counting.md)

---

## 1. Objective and Scope

### Objective

Implement an industrial-grade Regrind Recycling and Blend Ratio Engine tailored for discrete plastic injection molding. The system must track granulated sprues, runners, and rejected part regrind returned from the shop floor, link every regrind batch to its parent production run and virgin material grade, prevent over-cycling by capping thermal generations (Gen 1 only), enforce strict recipe blend limits (e.g. 15% maximum regrind blended with 85% virgin polymer) during FEFO staging allocation, and provide scanner intake for shop-floor granulator stations.

### In Scope

- **Relational Schema & Migrations:**
  - Enhance `items` table: `allow_regrind` (boolean), `max_regrind_percentage` (decimal: e.g. 15.00%).
  - Enhance `material_lots` table: `lot_type` (`VIRGIN`, `REGRIND`, `MASTERBATCH`), `parent_lot_id` (nullable FK to `material_lots`), `parent_production_order` (string e.g. `PO-MES-9921`), `regrind_generation` (tinyint: default 1), `regrind_source_machine` (string e.g. `IMM-04`).
- **Domain Services & Blend Optimization:**
  - `RegrindService`: Regrind lot creation, generation guard (prohibiting regrinding of Gen $\ge 1$ material), granulator bin intake, and thermal history traceability.
  - Enhanced `FefoAllocationService`: Dual-lot allocation algorithm:
    - Calculates maximum permissible regrind quantity: $\min(\text{available\_regrind}, \text{target\_demand} \times \frac{\text{cap\_pct}}{100})$.
    - Allocates regrind in FEFO order up to the blend cap.
    - Allocates the remaining required quantity from virgin lots in FEFO order.
    - Enforces polymer and color compatibility between regrind and virgin resin.
    - Ensures regrind lots must pass QA inspection (`AVAILABLE`) before allocation.
- **Handheld Scanner REST API:**
  - `POST /api/v1/scanner/regrind/intake`: Granulator operator scans drum/box, records weight in KG, links to MES production order, auto-generates `REG-` lot barcode, and returns printable label vector.
  - `GET /api/v1/scanner/regrind/available`: Lists approved regrind inventory grouped by polymer SKU.
- **Filament v3 Back-Office Integration:**
  - Item catalog: toggle regrind allowance and configure custom blend caps per resin SKU.
  - Material Lots resource: badge indicator for `VIRGIN` vs `REGRIND (Gen 1)`, modal view linking to parent virgin lot and source machine.
  - Staging Requests: picklist view displaying blended allocation (Virgin KG + Regrind KG) with calculated blend percentage.
- **Automated Verification Harness:**
  - Pest/PHPUnit tests for regrind intake, blend cap boundary math (e.g. exactly 15%, under cap, zero regrind available fallback, over-cap block), generation restriction, and concurrent balance locks.

### Out of Scope

- Physical gravimetric blender machine PLC integration (blending feeder screw control is handled on the shop-floor IMM/doser).
- Third-party post-consumer plastic scrap (PCR) compounding / pelletizing extruder plant controls.

### Assumptions and Open Questions

- Database engine is MariaDB 11.8 on `127.0.0.1:3306`.
- Regrind is stored in standard gaylords, octabins, or bags and assigned standard storage bins in `ZONE-RM` or `ZONE-STG`.
- Maximum standard blend ratio defaults to 15.0% unless customized on the specific Item SKU.

---

## 2. Staged Execution Plan

### Stage 1: Relational Schema & Migration Architecture

**Objective:** Add regrind attributes to items, material lots, and staging allocation records.

1. Create migration `2026_11_01_000010_add_regrind_columns_to_tables.php`:
   - `items`: `allow_regrind` (bool default false), `max_regrind_percentage` (decimal 5,2 default 15.00).
   - `material_lots`: `lot_type` (varchar 30 default 'VIRGIN'), `parent_lot_id` (foreignUuid nullable), `parent_production_order` (varchar 100 nullable), `regrind_generation` (unsignedTinyInteger default 1), `regrind_source_machine` (varchar 50 nullable).
   - `staging_request_items`: `is_regrind` (bool default false), `blend_percentage` (decimal 5,2 nullable).
2. Update Eloquent models `Item`, `MaterialLot`, and `StagingRequestItem` with constants, fillables, casts, and relations (`parentLot()`, `regrindLots()`).
3. Run migrations on MariaDB (`WMS_DB` and `WMS_DB_TEST`).

**Exit Criteria:**

- [x] `php artisan migrate` executes cleanly on MariaDB 11.8 without constraint errors.

---

### Stage 2: Regrind Domain Service & Blend Cap Allocation Engine

**Objective:** Build `RegrindService` and update `FefoAllocationService` to enforce mathematical blend caps.

1. Implement `RegrindService`:
   - `intakeRegrind(Item $item, float $quantityKg, WarehouseBin $destinationBin, ?MaterialLot $parentLot = null, ?string $productionOrder = null, ?string $machineCode = null, ?User $user = null)`:
     - Validates item allows regrind (`$item->allow_regrind`).
     - Enforces thermal generation limit: if parent lot is already `REGRIND`, throw `DomainException("Cannot regrind Generation 1 regrind material. Multi-heat degradation prohibited.")`.
     - Generates regrind lot barcode: `REG-{SKU}-{YYYYMMDD}-{SEQ}`.
     - Defaults lot status to `QA_HOLD` (or configurable auto-release if plant policy permits).
     - Records inbound double-entry receipt movement via `InventoryMovementService::recordReceipt`.
2. Enhance `FefoAllocationService`:
   - Update `allocateForStaging(Item $item, float $requestedQuantityKg, WarehouseBin $stagingBin)`:
     - Check if `$item->allow_regrind` is true.
     - Calculate maximum regrind cap: $\text{capQty} = \text{round}(requestedQuantity \times \frac{max\_regrind\_percentage}{100}, 3)$.
     - Query available `REGRIND` lots in FEFO order (`status = AVAILABLE`), locking balances.
     - Allocate up to $\text{capQty}$ of regrind stock.
     - Allocate remainder from `VIRGIN` lots in FEFO order (`status = AVAILABLE`), locking balances.
     - If insufficient virgin resin to fulfill the rest, throw `InsufficientStockException`.
     - Return detailed allocation manifest indicating virgin lots, regrind lots, and the resulting blend percentage.

**Exit Criteria:**

- [x] Unit tests prove blend cap is never exceeded even when abundant regrind is available.
- [x] Virgin-only fallback works when no regrind is in stock.
- [x] Gen $\ge 2$ regrind attempts are rejected.

---

### Stage 3: Handheld Scanner REST API for Granulator Stations

**Objective:** Provide shop-floor granulator operators with fast scan intake endpoints.

1. Implement `ScannerRegrindController`:
   - `POST /api/v1/scanner/regrind/intake`: Accepts `item_sku`, `quantity_kg`, `bin_code`, `parent_lot_number` (optional), `production_order_id` (optional), `machine_code` (optional).
   - Auto-generates label payload (ZPL stream and QR Code SVG) for thermal drum/box labeling.
   - `GET /api/v1/scanner/regrind/stock`: Summary of available regrind inventory by resin SKU.
2. Register routes in `routes/api.php` under `auth:sanctum`.

**Exit Criteria:**

- [x] Scanner API tests pass: intake creates regrind lot, deposits stock into bin, and generates valid label vector.

---

### Stage 4: Filament v3 Admin Interface Enhancements

**Objective:** Enable warehouse supervisors to configure blend caps and track recycled polymer utilization.

1. Enhance `ItemResource`:
   - Add "Regrind Recycling Settings" fieldset with `allow_regrind` toggle and `max_regrind_percentage` slider/input (0% to 50%, default 15%).
2. Enhance `MaterialLotResource`:
   - Add column `lot_type` with badges (`VIRGIN` in blue, `REGRIND` in amber).
   - Display `regrind_generation` and source machine `regrind_source_machine`.
   - Add action "Convert Scrap to Regrind" directly from back office.
3. Update `WarehouseTopologySeeder` and `ItemCatalogSeeder` to enable regrind for standard Polypropylene resin.

**Exit Criteria:**

- [x] Item resource allows managing regrind settings.
- [x] Material lots clearly distinguish regrind from virgin materials.

---

### Stage 5: Comprehensive Automated Testing & Verification

**Objective:** Rigorously verify blend cap arithmetic, multi-generation protection, and concurrency locks.

1. Write `Tests\Feature\RegrindAndBlendCapTest`:
   - Intake regrind lot and verify parent lot/machine link.
   - Prohibit second-generation regrind intake.
   - Test allocation when regrind is available: verify regrind allocated does not exceed 15% cap.
   - Test allocation when requested quantity is smaller than a single bag: verify proportion.
   - Test allocation when zero regrind is available: verify 100% virgin allocation.
   - Test scanner API intake endpoint.
2. Run full test suite (`php artisan test`) against MariaDB 11.8.
3. Code formatting with Laravel Pint.

**Exit Criteria:**

- [x] 100% of test suites pass on MariaDB (`WMS_DB_TEST`).
- [x] Zero linting or Pint styling errors.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- MariaDB 11.8 on `127.0.0.1:3306`.
- `InventoryMovementService` and `FefoAllocationService`.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| **Excessive Regrind Brittleness** | Critical: Dispatched blend $>20\%$ causes molded car parts to snap. | Hard limit enforced inside `FefoAllocationService` based on `Item::max_regrind_percentage`. |
| **Degraded Polymer (Re-regrinding)** | High: Regrinding already-reground plastic causes thermal breakdown. | Enforce `regrind_generation == 1` check; parent lot of type `REGRIND` throws `DomainException`. |
| **Contaminated Regrind Dispatched** | High: Metal or foreign dust in regrind clogs injection nozzle. | Newly received regrind defaults to `QA_HOLD` until QA clearance. |

### Rollback or Recovery

- Database migration has clean `down()` method dropping added columns.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| **Migration Execution** | `php artisan migrate` | Columns added cleanly on MariaDB 11.8. |
| **Blend Cap Upper Bound** | Unit Test | $\frac{\text{Allocated Regrind}}{\text{Total Allocated}} \le \frac{\text{max\_regrind\_percentage}}{100}$ under all circumstances. |
| **Generation 2 Block** | Unit Test | Attempting to intake regrind from a regrind parent lot throws `DomainException`. |
| **Virgin Fallback** | Unit Test | When no regrind is available, 100% virgin resin is allocated without failure. |
| **Scanner Intake API** | Feature Test | `POST /api/v1/scanner/regrind/intake` returns 201 with label payload and updates stock ledger. |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Task report `003-regrind-scrap-and-blend-cap.md` created under `.agent-knowledge/dynamic/reports/tasks/`.
- [x] Living documentation updated.

**Next Transition:** Regrind engine complete and fully verified on MariaDB 11.8. Ready for next subsystem implementation (e.g. Dedicated Rugged Handheld Scanner Web UI or Visual Bin Topology).
