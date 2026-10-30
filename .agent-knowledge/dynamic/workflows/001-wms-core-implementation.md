# Implementation Workflow 001: Warehouse Management System (WMS) Core Implementation

**Status:** `COMPLETE`  
**Target Outcome:** Production-ready core backend and administrative interface for the Laravel 11 WMS, encompassing spatial bin topology, append-only double-entry inventory ledger, FEFO picking engine with pessimistic locking, Filament v3 back-office portal, rugged scanner REST API, and bi-directional integration gateway to the NestJS MES.  
**Owner:** `Systems Integration & Supply Chain IT Team`  
**References:**

- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md)
- [ARCHITECTURE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/ARCHITECTURE.md)
- [DATABASE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/DATABASE.md)
- [TECHNOLOGYSTACK.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/TECHNOLOGYSTACK.md)
- [SPEC-001-mes-wms-interface-contract.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/notes/SPEC-001-mes-wms-interface-contract.md)
- [ADR-001-laravel-filament-wms-architecture.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/notes/ADR-001-laravel-filament-wms-architecture.md)

---

## 1. Objective and Scope

### Objective

Deliver a robust, highly transactional Warehouse Management System (WMS) tailored for discrete plastic injection molding logistics. The system must eliminate phantom inventory via an immutable double-entry movement ledger, enforce strict FEFO lot allocation under high concurrency, provide back-office management via Filament v3, supply lightweight API endpoints for handheld barcode scanners, and synchronize seamlessly with the shop-floor NestJS MES.

### In Scope

- **Core Package Stack Installation:** Filament v3, Laravel Sanctum, Spatie Permission, Barcode/QR generator, and DomPDF.
- **Relational Schema & Migrations:** Warehouses, Zones, Bins, Items, Material Lots, double-entry `inventory_movements` ledger, Delivery Orders (DO), and Transactional Outbox for MES events.
- **Domain Services:**
  - `InventoryMovementService`: Double-entry atomic ledger transfers with mathematical zero-sum enforcement and non-negative stock guards.
  - `FefoAllocationService`: Pessimistic locking (`lockForUpdate()`) to prevent race conditions during concurrent pick operations.
  - `LotStateMachine`: Enforces `QA_HOLD` $\rightarrow$ `AVAILABLE` / `QUARANTINE` based on COA verification and expiration dates.
  - `DeliveryOrderService`: Outbound dispatch, truck manifest scan verification, and PDF delivery order generation.
- **Filament v3 Admin Portal:** Full resource management for Warehouses, Bins, Inventory Catalog, Lots (with COA view/download), Movement Ledger (read-only audit trail), and Outbound Shipping.
- **Scanner REST API:** Mobile endpoints for PO receiving, guided putaway, FEFO pick confirmation, and truck scan verification.
- **MES Integration Gateway:**
  - Outbound dispatch to MES `POST /api/v1/materials/lots` using the Transactional Outbox pattern.
  - Inbound webhook receiver `POST /api/v1/webhooks/mes` with HMAC SHA-256 signature verification and idempotency guarantees for `packaging.unit_created`.
- **Verification Harness:** Pest PHP unit, integration, and parallel concurrency tests verifying zero-sum ledger invariants and lock safety.

### Out of Scope

- Native Android APK compilation (focus is on REST API contracts consumed by scanner browsers or native shells).
- Direct PLC / OPC UA machine communication (handled exclusively by the shop-floor NestJS MES).
- Financial general ledger accounting and ERP invoice creation.

### Assumptions and Open Questions

- **PHP Runtime:** PHP 8.2+ (verified local CLI is PHP 8.5.12).
- **Database Engine:** Development environment can run on SQLite/MariaDB; migrations must support standard relational foreign keys, UUIDs, and transactions.
- **MES Endpoint:** The MES is running or accessible via network at the configured `MES_BASE_URL` with a shared secret for HMAC verification.

---

## 2. Staged Execution Plan

### Stage 1: Dependency Integration & Foundation Setup

**Objective:** Install and configure all required composer packages, database connection, and application boilerplate.

1. Install primary dependencies via Composer:
   - `filament/filament:^3.2`
   - `laravel/sanctum:^4.0`
   - `spatie/laravel-permission:^6.0`
   - `simplesoftwareio/simple-qrcode:^4.2`
   - `barryvdh/laravel-dompdf:^3.0`
2. Run Filament panel installer (`php artisan filament:install --panels`) to register the AdminPanelProvider.
3. Configure authentication guards (`web` for Filament sessions, `sanctum` for scanner REST API).
4. Update `.env.example` and environment configuration for MES webhook secret, integration token, and default printer settings.

**Exit Criteria:**

- [x] `composer check` / `php artisan --version` executes cleanly with all packages loaded.
- [x] Filament Admin panel provider is registered and serves `/admin` login route.

---

### Stage 2: Database Schema & Migration Architecture

**Objective:** Implement strict relational schema migrations with UUIDv7 primary keys, compound indexes, and foreign key integrity.

1. Create migration `create_warehouse_topology_tables`:
   - `warehouses` (code, name, address, is_active)
   - `warehouse_zones` (warehouse_id, code, name, zone_type: RAW_MATERIAL, STAGING_BUFFER, FINISHED_GOODS, QUARANTINE)
   - `warehouse_bins` (zone_id, bin_code, aisle, rack, shelf, bin, max_weight_kg, max_volume_cbm, is_blocked)
2. Create migration `create_item_catalog_tables`:
   - `items` (sku, name, category: RAW_RESIN, MASTERBATCH, PACKAGING, FINISHED_GOOD, uom, standard_pack_qty, safety_stock, shelf_life_days)
3. Create migration `create_material_lots_tables`:
   - `material_lots` (lot_number, item_id, vendor_code, vendor_lot_number, vendor_heat_number, manufactured_date, expiry_date, coa_file_path, status: QA_HOLD, AVAILABLE, EXPIRED, QUARANTINE, qc_inspected_at, qc_inspected_by)
4. Create migration `create_inventory_movements_ledger_table`:
   - `inventory_movements` (id, transaction_uuid, movement_type, item_id, lot_id, source_bin_id [nullable for inbound], destination_bin_id [nullable for scrap/dispatch], quantity, uom, reference_type, reference_id, executed_by, notes, created_at)
5. Create migration `create_outbound_shipping_tables`:
   - `delivery_orders` (do_number, customer_name, vehicle_plate, driver_name, status, dispatched_at)
   - `delivery_order_items` (delivery_order_id, item_id, lot_id, quantity_ordered, quantity_loaded)
6. Create migration `create_integration_outbox_table`:
   - `integration_outbox` (id, event_type, payload, status: PENDING, PROCESSED, FAILED, attempts, last_error, processed_at)
7. Create database seeders for realistic warehouse topology (Zones `ZONE-RM`, `ZONE-STG`, `ZONE-FG`, `ZONE-QC`, sample bins, standard resin items, and default roles).

**Exit Criteria:**

- [x] `php artisan migrate:fresh --seed` runs with zero SQL or foreign key constraint errors.
- [x] Database contains complete seed data for test picking and receiving workflows.

---

### Stage 3: Domain Service Layer (Ledger, FEFO, & Quality FSM)

**Objective:** Build high-integrity domain services enforcing ACID double-entry invariants and concurrency locks.

1. Implement `InventoryMovementService`:
   - Method `recordTransfer(string $type, Item $item, MaterialLot $lot, ?WarehouseBin $from, ?WarehouseBin $to, float $qty, ...)`
   - Enforce database transactional boundary with immediate non-negative balance verification on source bin.
   - Calculate materialized balances on-demand or via ledger balance aggregate queries.
2. Implement `FefoAllocationService`:
   - Query available lots sorted by `expiry_date ASC, created_at ASC`.
   - Apply `lockForUpdate()` on candidate lot balances to prevent concurrent race conditions.
   - Generate structured picklists matching order or staging requirements.
3. Implement `LotQualityService`:
   - Status transitions with validation guards (e.g. preventing dispatch of lots in `QA_HOLD` or `QUARANTINE`).
   - COA moisture and Melt Flow Index (MFI) metric inspection logging.
4. Implement `DeliveryOrderService`:
   - Manage Delivery Order lifecycle (Draft -> Picking -> Loaded -> Dispatched).
   - Generate PDF Delivery Order documents using DomPDF matching standard international logistics format.

**Exit Criteria:**

- [x] Unit tests pass for `InventoryMovementService` confirming balanced debit/credit records.
- [x] FEFO picker successfully prioritizes oldest expiration lots and respects QA Hold blocks.

---

### Stage 4: Filament v3 Admin Management Portal

**Objective:** Construct operational dashboards, data tables, and action wizards for warehouse management.

1. Implement **Warehouse Topology Resources**:
   - `WarehouseResource`, `WarehouseZoneResource`, and `WarehouseBinResource` with capacity indicators and active status toggles.
2. Implement **Catalog & Lot Resources**:
   - `ItemResource` with stock metrics.
   - `MaterialLotResource` with badge indicators for quality states, COA file viewer, and action modal for QA Approval/Quarantine.
3. Implement **Audit & Ledger Resources**:
   - `InventoryMovementResource`: Read-only, highly searchable audit log displaying historical stock flows with transaction correlation IDs.
4. Implement **Outbound Dispatch Resources**:
   - `DeliveryOrderResource`: Multi-step form for order creation, item allocation, scan reconciliation overview, and one-click "Print Delivery Order PDF" action.
5. Implement Role-Based Access Control (RBAC):
   - Filament Shield / Spatie permissions configuring roles: `Warehouse Manager`, `Inbound Receiver`, `Material Handler`, `QA Inspector`, and `Shipping Clerk`.

**Exit Criteria:**

- [x] Administrator can log in to `/admin`, navigate all resources, create an item, intake a lot, inspect stock movements, and download a Delivery Order PDF.

---

### Stage 5: Rugged Handheld Scanner REST API

**Objective:** Provide lightweight, high-speed RESTful JSON endpoints secured by Sanctum for industrial mobile terminals (Zebra/Honeywell).

1. Scanner Authentication:
   - `POST /api/v1/scanner/login`: Issues Sanctum personal access token tied to the material handler's badge.
2. Inbound Receiving Module:
   - `POST /api/v1/scanner/inbound/receive`: Validates PO, accepts vendor lot details, auto-generates internal lot barcode, and returns printable QR payload.
3. Guided Putaway Module:
   - `GET /api/v1/scanner/putaway/suggest`: Recommends optimal bin based on zone, weight, and volume constraints.
   - `POST /api/v1/scanner/putaway/confirm`: Confirms scan of physical bin barcode and moves stock from Inbound Dock to Rack Bin.
4. Shop Floor Staging & Picking Module:
   - `GET /api/v1/scanner/picking/active-orders`: Fetches pending replenishment requests.
   - `POST /api/v1/scanner/picking/scan`: Validates scanned lot against FEFO picklist, deducts warehouse rack bin, deposits to machine staging buffer bin, and queues MES outbound sync.
5. Outbound Truck Loading Module:
   - `POST /api/v1/scanner/shipping/scan-truck`: Validates scanned carton/pallet barcode against Delivery Order manifest.

**Exit Criteria:**

- [x] All scanner API endpoints return standard JSON responses with structured validation errors on barcode mismatches.
- [x] End-to-end scanner flow (Receive -> Putaway -> Pick -> Ship) succeeds in Postman / HTTP client tests.

---

### Stage 6: MES Integration Gateway & Transactional Outbox

**Objective:** Establish fault-tolerant, bi-directional communication between the Laravel WMS and the NestJS MES per `SPEC-001`.

1. Implement Outbound Dispatch Sync:
   - Hook into staging pick confirmation to record a `materials.lot_dispatched` event in `integration_outbox`.
   - Implement `ProcessOutboxJob` running on Laravel Horizon/Queue to dispatch `POST /api/v1/materials/lots` to MES Core API with Bearer token authentication and exponential retry.
2. Implement Inbound Webhook Receiver:
   - Endpoint: `POST /api/v1/webhooks/mes`
   - Implement `VerifyMesWebhookSignature` middleware validating HMAC SHA-256 header (`X-MES-Signature`).
   - Enforce idempotency via `X-MES-Event-Id` cache or database deduplication.
   - Handle `packaging.unit_created` payload: Automatically create incoming Finished Goods inventory record in `ZONE-FG` with carton barcode and part number.

**Exit Criteria:**

- [x] Webhook receiver accepts valid signed payloads and returns `200 OK`.
- [x] Duplicate event replay returns `200 OK` without creating duplicate stock.
- [x] Invalid HMAC signature is rejected with HTTP 401 Unauthorized.

---

### Stage 7: Automated Concurrency Testing & Hardening

**Objective:** Rigorously verify system invariants, data integrity, and high-concurrency race conditions.

1. Concurrency & Race-Condition Tests (Pest PHP):
   - Simulate simultaneous pick attempts on the exact same lot in parallel threads; verify pessimistic locks prevent over-allocation.
2. Double-Entry Zero-Sum Invariant Tests:
   - Run automated mathematical checks across all movements verifying $\sum(\text{debit}) - \sum(\text{credit}) = 0$.
3. End-to-End Flow Tests:
   - Test full lifecycle: PO Inbound $\rightarrow$ QA Release $\rightarrow$ Putaway $\rightarrow$ FEFO Pick $\rightarrow$ MES Outbox Dispatch $\rightarrow$ FG Webhook Receive $\rightarrow$ Delivery Order Shipping.

**Exit Criteria:**

- [x] All Pest test suites pass with 100% green status.
- [x] Concurrency test proves zero race conditions under simulated load.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- NestJS MES endpoint availability and agreed HMAC secret key (`MES_WEBHOOK_SECRET`).
- PHP GD or Imagick extension installed for QR code rendering (`ext-gd` or `ext-imagick`).

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| **Race Conditions during Picking** | High: Two pickers allocate the same resin bag for different machines. | Use MariaDB `SELECT ... FOR UPDATE` row locks inside `DB::transaction()` during lot allocation. |
| **MES Network Outage** | Medium: Shop-floor Wi-Fi or MES downtime prevents lot sync. | Transactional Outbox pattern stores events locally; background Horizon workers retry up to 5 times with exponential backoff. |
| **Scanner Wi-Fi Packet Duplication** | Medium: Handheld terminal re-sends scan request on retry. | Implement client-side idempotency keys on all mutation endpoints (`X-Idempotency-Key`). |
| **Unapproved Resin Dispatched** | Critical: Bad moisture or uninspected polymer causes molded part defects. | Strict database-level and service-level checks blocking any lot without status `AVAILABLE`. |

### Rollback or Recovery

- Database migrations are fully reversible with corresponding `down()` methods.
- Integration outbox preserves failed payload history and stack traces in `last_error` for audit and manual replay without data loss.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| **Database Migrations** | `php artisan migrate:fresh --seed` | Clean execution; all tables, foreign keys, and indexes established. |
| **Ledger Invariant** | Automated Pest Test | $\sum(\text{debit}) - \sum(\text{credit}) = \text{physical stock}$; zero unaccounted inventory drift. |
| **Pessimistic Concurrency** | Parallel HTTP / Pest script | No negative balances; exactly available quantity allocated, remainder receives 409 Conflict. |
| **FEFO Allocation** | Pest Test Suite | Oldest expiry lot allocated first; lots on `QA_HOLD` or `QUARANTINE` skipped. |
| **Filament Admin Panel** | Manual / Browser Inspection | Resources render cleanly, CRUD operations function, PDF Delivery Order downloads with correct layout. |
| **MES Webhook Signature** | HTTP Feature Test | Valid HMAC-SHA256 accepted; altered payload or invalid secret returns 401 Unauthorized. |
| **Webhook Idempotency** | HTTP Feature Test | Re-transmitting identical `eventId` returns 200 OK without incrementing inventory. |
| **Scanner API Contracts** | Postman / Pest HTTP Test | Endpoints return JSON contracts with standard validation error envelopes. |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Living documentation under `.agent-knowledge/dynamic/docs/dev/` updated with final route contracts and schema adjustments.
- [x] Initial numbered task report (`.agent-knowledge/dynamic/reports/tasks/001-wms-core-implementation.md`) created recording completed execution and test evidence.

**Next Transition:** Core WMS backend, domain services, back-office panel, scanner API, and MES gateway fully operational and verified. Ready for handheld scanner terminal testing and staging deployments.
