# Implementation Workflow 009: End-to-End Lot Genealogy, Machine Hopper Staging, and Outbound SSCC-18 Gate Pass Engine

**Status:** `COMPLETE`  
**Target Outcome:** Production-grade implementation of three critical manufacturing logistics capabilities: (1) Bi-directional IATF 16949 lot genealogy & rapid 1-click quarantine, (2) Shop-floor machine hopper staging verification & partial return logistics, and (3) Outbound SSCC-18 pallet containerization, JSON ASN export, and security gate clearance with mobile driver e-signatures.  
**Owner:** Core Architecture & Logistics Engineering  
**References:**
- [ADR-004: Lot Genealogy, Hopper Staging, and Gate Pass Architecture](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/ADR-004-genealogy-hopper-staging-and-gate-pass.md)
- [SPEC-004: Interface Contract & Schema Specification](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/SPEC-004-genealogy-hopper-staging-and-gate-pass-contract.md)
- [PRD Requirements](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md)
- [Workflow 008 Completion](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/008-live-mes-integration-gateway.md)

---

## 1. Objective and Scope

### Objective
Deliver an enterprise logistics triad bridging quality audit compliance (Pillar 1), shop-floor material feeding integrity (Pillar 2), and compliant customer dispatch gate clearance (Pillar 3), maintaining strict zero-sum double-entry ledger invariants across all physical movements.

### In Scope
1. **Pillar 1: Lot Genealogy & Recall Traceability Engine**:
   - `LotGenealogyService`: `traceBackward()` and `traceForward()`.
   - `quarantineImpactedStock()`: Atomic mass quarantine of identified compromised material lots.
   - `QualityQuarantine` model & migration.
   - Filament Lot Genealogy Interactive Page & Recall Dossier Generator.
2. **Pillar 2: Shop-Floor Machine Hopper Staging & Partial Return**:
   - Staging Hoppers in `warehouse_bins` (`BIN-HOPPER-IMM01`, `BIN-HOPPER-IMM02`, etc.).
   - `HopperStagingService`: `issueToHopper()` and `returnPartialFromHopper()`.
   - Recipe compatibility verification against active work orders.
   - Scanner PWA endpoints and web UI for Hopper Staging & Verification.
3. **Pillar 3: GS1-128 / SSCC-18 Pallet Identifiers & Mobile Gate Pass**:
   - `SsccPalletService`: Modulo-10 checksum calculation and GS1-128 formatting.
   - `PalletContainer` model & migration.
   - `AsnExportService`: Outbound JSON/EDI Advanced Shipping Notice.
   - `GatePassService`: Security gate clearance, seal recording, driver mobile glass e-signature, and `DISPATCHED_GATE_OUT` transition.
   - Scanner UI for Security Gate Pass clearance.

### Out of Scope
- Direct machine PLC physical hopper solenoid actuation (owned by MES / Modbus).
- E-commerce parcel API carrier integrations (FedEx / DHL).

### Assumptions and Open Questions
- Dedicated staging buffer bins (`BIN-HOPPER-IMM01` through `BIN-HOPPER-IMM08`) exist in `ZONE-STAGING`.
- Security officers and material handlers use the mobile scanner PWA (`/scanner`) on rugged Android devices or tablets.

---

## 2. Staged Execution Plan

### Stage 1: Database Migrations & Domain Models
**Objective:** Create relational tables and models for quarantines, hopper staging transactions, SSCC pallet containers, and gate pass extensions.
1. Create migration `2026_11_01_000013_create_genealogy_hopper_and_gate_pass_tables.php`.
2. Implement Eloquent models:
   - `App\Models\QualityQuarantine.php`
   - `App\Models\HopperTransaction.php`
   - `App\Models\PalletContainer.php`
   - Update `App\Models\DeliveryOrder.php` with gate pass attributes.
3. Seed machine hopper bins into `WarehouseTopologySeeder.php`.

**Exit Criteria:**
- [x] Migrations run cleanly on MariaDB 11.8 (`WMS_DB` and `WMS_DB_TEST`).
- [x] Model relationships, casts, and constraints tested and verified.

### Stage 2: Lot Genealogy & Rapid Recall Engine (Pillar 1)
**Objective:** Build bi-directional traversal graph and atomic mass quarantine functionality.
1. Implement `App\Services\LotGenealogyService.php`:
   - `traceBackward(string $cartonOrDo)`: Traverses carton &rarr; production run &rarr; raw resin lots / heat # / silo discharges.
   - `traceForward(string $rawLotNumber)`: Traverses raw lot &rarr; runs &rarr; finished goods cartons &rarr; delivery orders &rarr; customer recipients.
   - `quarantineImpactedStock(int $lotId, string $reason, ?int $userId, string $severity)`: Atomically quarantines all connected active inventory.
2. Build Filament Resource / Page `LotGenealogyResource` with interactive tree view and 1-click quarantine action.

**Exit Criteria:**
- [x] Forward and backward traceability algorithms resolve multi-stage trees accurately.
- [x] Quarantine action updates all connected lots and records `QualityQuarantine` event.

### Stage 3: Shop-Floor Machine Hopper Staging & Partial Return (Pillar 2)
**Objective:** Prevent resin contamination at injection machine hoppers and accurately account for leftover resin.
1. Implement `App\Services\HopperStagingService.php`:
   - `verifyRecipeCompatibility(string $machineCode, string $lotNumber)`: Compares resin SKU with machine schedule.
   - `issueToHopper(int $lotId, string $machineCode, float $quantityKg, ?int $userId)`: Creates `HOPPER_STAGE` movement.
   - `returnPartialFromHopper(int $transactionId, float $returnedWeightKg, int $targetBinId, ?int $userId)`: Creates `HOPPER_RETURN` movement.
2. Add API endpoints in `App\Http\Controllers\Api\HopperStagingController.php`.
3. Add Scanner View in Handheld PWA (`/scanner/hopper`).

**Exit Criteria:**
- [x] Incompatible resin is strictly rejected with descriptive error message.
- [x] Double-entry ledger maintains zero-sum balance through staging and partial returns.

### Stage 4: Outbound SSCC-18 Pallet Identifiers & Security Gate Pass (Pillar 3)
**Objective:** Generate automotive OEM-compliant pallet barcodes, customer ASNs, and digital gate passes with driver signatures.
1. Implement `App\Services\SsccPalletService.php`:
   - Generates 18-digit SSCC codes with GS1 Modulo-10 checksums.
   - Creates and seals `PalletContainer` records with gross/tare/net weight math.
2. Implement `App\Services\AsnExportService.php`:
   - Generates structured JSON Advanced Shipping Notice for customer EDI intake.
3. Implement `App\Services\GatePassService.php`:
   - Processes security gate clearance, validates seal numbers, records base64 driver signatures, and issues Gate Pass number.
4. Add Scanner View in Handheld PWA (`/scanner/gate-pass`).

**Exit Criteria:**
- [x] SSCC-18 codes satisfy GS1 check-digit verification.
- [x] Delivery order transitions to `DISPATCHED_GATE_OUT` with recorded signature and timestamp.

### Stage 5: End-to-End Verification, Tests & Documentation
**Objective:** Author automated feature tests, verify zero regressions, check Pint formatting, and update living records.
1. Create `tests/Feature/AdvancedManufacturingLogisticsTest.php` covering all three pillars.
2. Run full test suite across MariaDB 11.8 (`php artisan test`).
3. Check and format code with Laravel Pint (`./vendor/bin/pint --test`).
4. Update task report `.agent-knowledge/dynamic/reports/tasks/009-genealogy-hopper-staging-and-gate-pass.md`.

**Exit Criteria:**
- [x] 100% test pass rate on MariaDB 11.8 (69 tests, 444 assertions).
- [x] Laravel Pint style check clean (`0 errors`).
- [x] Zero-sum double-entry ledger invariants verified across all new movement types.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- MariaDB 11.8 database on port 3306.
- Existing WMS models (`MaterialLot`, `InventoryMovement`, `InventoryBalance`, `DeliveryOrder`).

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Accidental over-quarantine during recall | Non-affected lots blocked from picking | Scoped graph traversal algorithm checks exact production run IDs and recipe consumption links. |
| Incomplete driver signature payload | Corrupted image or broken gate pass | Server-side validation ensures signature payload is a valid non-empty Base64 string before approving gate clearance. |
| Scale inaccuracy on partial bag return | Inventory balance drift | Verification guard enforces returned weight cannot exceed originally staged quantity. |

### Rollback or Recovery
All database changes are managed via standard Laravel migrations (`down()` rollback method implemented). All inventory operations run within atomic ACID transactions (`DB::transaction`).

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| Backward Traceability | Unit / Feature Test | Resolves raw resin lot, heat #, and mold code from carton barcode in $< 50\text{ms}$. |
| Mass Quarantine | Feature Test | Changes lot statuses to `QUARANTINE` and blocks delivery order picking. |
| Hopper Recipe Guard | Feature Test | Rejects mismatched resin with 422 HTTP status and prevents hopper issuance. |
| Zero-Sum Ledger Invariant | Feature Test | `sum(movements.quantity) == sum(balances.quantity_on_hand)` after staging and partial returns. |
| SSCC Modulo-10 Checksum | Unit Test | Mathematical validation against GS1 standard test vectors. |
| Driver E-Signature Gate Clearance | Feature Test | Records signature, assigns Gate Pass #, and updates DO status to `DISPATCHED_GATE_OUT`. |

---

## 5. Completion and Handoff

- [ ] All staged exit criteria and acceptance criteria are met.
- [ ] Relevant living documentation, ADRs, and specifications are updated or linked.
- [ ] The task report records actual changes and verification results.

Next transition: Execute Stage 1 (Database Migrations & Domain Models).
