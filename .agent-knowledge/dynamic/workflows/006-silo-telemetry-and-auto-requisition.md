# Implementation Workflow 006: Bulk Resin Silo Ultrasonic/Radar Level Telemetry & Auto-Requisition Engine

**Status:** `COMPLETE`  
**Target Outcome:** Real-time ingestion of industrial radar/ultrasonic silo distance readings, accurate conical + cylindrical mass conversion, automated procurement requisition drafting with de-duplication, and visual Filament gauge monitoring.  
**Owner:** Core Logistics & IoT Team  
**References:**
- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md) (Post-MVP Priority #2)
- [ADR-002](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/ADR-002-silo-telemetry-and-replenishment.md)
- [SPEC-002](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/SPEC-002-silo-telemetry-ingestion-api.md)
- [DESIGN.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/DESIGN.md)

---

## 1. Objective and Scope

### Objective
Provide discrete plastic injection molding facilities with continuous, accurate visibility into bulk resin silos, eliminating unexpected production halts caused by polymer stockouts and preventing silo overfills during bulk pneumatic tanker discharge.

### In Scope
- Database migrations: `silos`, `silo_telemetry_readings`, `purchase_requisitions`, and `SILO` warehouse zone type.
- Mathematical geometry service (`SiloGeometryService.php`) calculating conical hopper + cylindrical barrel volume, mass via bulk density ($kg/m^3$), and fill percentage.
- Telemetry ingestion service & API endpoint (`SiloTelemetryService.php` and `POST /api/v1/telemetry/silo-reading`).
- Automated replenishment engine generating `PurchaseRequisition` drafts with idempotency/de-duplication guards.
- CLI simulation command (`php artisan wms:simulate-silo-telemetry`) for testing and demonstration.
- Filament visual level gauge widget and management resource.
- Automated feature and unit tests.

### Out of Scope
- Hardware Modbus RS-485 serial communication (handled by external IoT edge gateway).
- Direct ERP webhook dispatch to resin suppliers (drafts are approved within WMS).

---

## 2. Staged Execution Plan

### Stage 1: Data Architecture & Geometric Math Engine
**Objective:** Establish database schema for silos, telemetry history, and purchase requisitions, and implement analytical volume-to-mass calculations.
1. Create migration `2026_11_01_000011_create_silo_and_requisition_tables.php`.
2. Define models `Silo`, `SiloTelemetryReading`, and `PurchaseRequisition` with UUIDs and relationships.
3. Add `TYPE_SILO = 'SILO'` to `WarehouseZone`.
4. Implement `SiloGeometryService.php` with conical hopper + cylindrical volume formulas.

**Exit Criteria:**
- [x] Migrations run cleanly on MariaDB 11.8 (`WMS_DB` and `WMS_DB_TEST`).
- [x] Unit tests verify geometry calculations against standard analytical models.

### Stage 2: Telemetry Ingestion API & Auto-Requisition Engine
**Objective:** Provide secure REST endpoint for level sensors and trigger de-duplicated purchase requisitions when stock drops below threshold.
1. Implement `SiloTelemetryService.php` to handle reading intake, calculation, status updates, and requisition drafting.
2. Implement `SiloTelemetryController.php` with validation and API authentication.
3. Implement automated `PurchaseRequisition` generation with idempotency checks.
4. Implement Artisan command `wms:simulate-silo-telemetry`.

**Exit Criteria:**
- [x] API endpoint ingests telemetry, logs reading, updates silo status, and responds with computed metrics.
- [x] Reorder threshold triggers a single `DRAFT` purchase requisition without duplicates on successive readings.

### Stage 3: Filament Monitoring & Visual Cylinder Gauge
**Objective:** Render industrial visual level gauges and purchase requisition controls in Filament portal following `DESIGN.md`.
1. Create `SiloResource.php` with list and detail views.
2. Implement custom Blade level gauge component showing vertical cylinder fill, percentage markers, KPI cards, and status styling.
3. Create `PurchaseRequisitionResource.php` for procurement review and status progression.
4. Seed realistic bulk silos in `RealisticOperationsSeeder.php` (e.g. `SILO-PP-01`, `SILO-ABS-01`, `SILO-PC-01`).

**Exit Criteria:**
- [x] Silo resource and live cylinder gauges render without CSS layout defects.
- [x] Operators can view silo levels and initiate simulated telemetry pings.

### Stage 4: Verification, Test Suite, and Task Report
**Objective:** Validate all invariants, run test suite, and document results.
1. Create `tests/Feature/SiloTelemetryTest.php` covering math calculations, API ingestion, requisition triggering, and idempotency.
2. Execute full automated test suite on MariaDB.
3. Run Laravel Pint style checks.
4. Create task report `.agent-knowledge/dynamic/reports/tasks/006-silo-telemetry-and-auto-requisition.md`.

**Exit Criteria:**
- [x] All tests pass on MariaDB (`WMS_DB_TEST`).
- [x] Task report records all changes and verification results.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- MariaDB 11.8 InnoDB database.
- Existing `WarehouseZone`, `WarehouseBin`, and `Item` catalog.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| :--- | :--- | :--- |
| **Duplicate Requisition Spam** | Rapid periodic sensor readings could generate dozens of duplicate purchase orders. | Strict idempotency guard: check for existing non-fulfilled, non-cancelled requisitions for the same silo/item. |
| **Division by Zero in Cone Math** | Silo configured with `cone_height_m = 0`. | Geometry service checks if cone height is zero and falls back to pure cylinder calculation. |
| **Sensor Jitter / Outliers** | Spurious distance readings could trigger false alerts. | Clamp calculated material height between $0$ and total height $H$; flag readings exceeding physical bounds. |

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| :--- | :--- | :--- |
| **Geometry Math Accuracy** | Unit tests | Conical + cylindrical volume math matches analytical values within 0.01% tolerance. |
| **API Ingestion & Auth** | Feature test | Valid token accepted; invalid token returns 401; missing fields return 422. |
| **Replenishment Trigger** | Feature test | Reading below reorder threshold generates `PurchaseRequisition` in `DRAFT` status. |
| **PR De-duplication** | Feature test | Second low reading does NOT create a second open requisition. |
| **Filament Visuals** | Feature test & browser check | Gauge renders cleanly with correct color-coding and metrics. |

---

## 5. Completion and Handoff

- [ ] All staged exit criteria and acceptance criteria are met.
- [ ] Relevant living documentation, ADRs, and specifications are updated or linked.
- [ ] The task report records actual changes and verification results.
