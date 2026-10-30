# Architecture Plan — Warehouse Management System (WMS)

> **Status:** `PLANNING` — New Project Mode  
> **Output:** `.future/docs/dev/sdd/ARCHITECTURE.md`  
> **Purpose:** Technical blueprint for system design, components, data flows, security, deployment, and architectural decisions for the Laravel 11 + Filament v3 Warehouse Management System.

---

## Project Type Declaration

| Item | Value |
|------|-------|
| Project Type | Monolith with Modular Service Layer & REST/Webhook Gateway |
| Git Location | `wms-app/` — Standalone Repository (New Project) |

---

## 1. System Design

### Architecture Pattern

**Modular Monolith (Domain-Driven Design within Laravel)**

**Rationale:**
1. **High Transactional Integrity (ACID):** Warehouse operations (e.g., reserving raw resin lots, decrementing inventory during staging handover, moving stock between bins) require immediate consistency with zero tolerance for phantom inventory or race conditions. A monolithic relational boundary using MariaDB (InnoDB engine) with database row-level locking (`lockForUpdate()`) provides deterministic consistency.
2. **Operational Simplicity:** Avoids distributed microservice orchestration overhead, distributed tracing, and eventual consistency lag for shop-floor logistics.
3. **Dual Operation Interface:** Combines rich server-rendered TALL-stack dashboards (Tailwind, Alpine.js, Laravel, Livewire via **Filament v3**) for desktop warehouse supervisors with lightweight RESTful API endpoints for industrial Android handheld barcode scanners (Zebra/Honeywell).
4. **Decoupled Asynchronous Integration:** Communicates with the external NestJS MES via resilient asynchronous webhook event queues and Redis jobs, ensuring warehouse operations never freeze if the shop floor network fluctuates.

### High-Level Diagram

```text
+---------------------------------------------------------------------------------------------------+
|                                  External Clients & Actors                                        |
|  - Warehouse Supervisors & Managers (Desktop Web Browser - Filament v3 Admin)                     |
|  - Inbound / Outbound Handlers (Rugged Industrial Handheld Barcode Scanners / Zebra PDA)         |
|  - External NestJS MES Core API (Production Orders, Hopper Requests, Packaging Verification)      |
+---------------------------------------------------------------------------------------------------+
                                            │
                                            ▼ (HTTPS / REST API / Livewire WebSocket)
+---------------------------------------------------------------------------------------------------+
|                                     Nginx Reverse Proxy / TLS                                     |
+---------------------------------------------------------------------------------------------------+
                                            │
                                            ▼
+---------------------------------------------------------------------------------------------------+
|                                 Laravel 11 Application Monolith                                   |
|  ┌─────────────────────────────────────────────────────────────────────────────────────────────┐  |
|  │ Presentation & Transport Layer                                                              │  |
|  │  - Filament v3 Admin & Resource Panels (Inbound, Bins, FG, Dispatch, Stock Opname)          │  |
|  │  - REST API Controllers (/api/v1/inbound, /api/v1/staging, /api/v1/shipping)                │  |
|  │  - Webhook Ingestion Controller (/api/v1/webhooks/mes)                                      │  |
|  │  - Authentication: Laravel Sanctum (Tokens for Scanners/MES) & Session Guard (Web Panel)    │  |
|  └─────────────────────────────────────────────────────────────────────────────────────────────┘  |
|  ┌─────────────────────────────────────────────────────────────────────────────────────────────┐  |
|  │ Core Domain & Service Layer                                                                 │  |
|  │  - Inbound & Lot Allocation Service (COA validation, Heat #, GS1 QR Label Generation)       │  |
|  │  - Location & Bin Capacity Engine (Zoning, Rack limits, Putaway guidance)                   │  |
|  │  - FEFO / FIFO Picking & Reservation Engine (Pessimistic Locking, Expiry Prioritization)    │  |
|  │  - MES Material Dispatch Gateway (Outbound sync to MES /materials/lots)                     │  |
|  │  - Finished Goods Handover Service (Packaging reconciliation & FG rack storage)             │  |
|  │  - Outbound Pick-Pack-Ship Engine (Delivery Order PDF generation, Truck scan verification)  │  |
|  └─────────────────────────────────────────────────────────────────────────────────────────────┘  |
|                                            │
|                       ┌────────────────────┴────────────────────┐
|                       ▼                                         ▼
|  +─────────────────────────────────────────+  +────────────────────────────────────────────────+
|  |       Relational Database (MariaDB)     |  |               Redis Cache & Queue              |
|  |  - Transactional Inventory Balances     |  |  - Laravel Horizon Job Queues                  |
|  |  - Bin Locations & Lot Genealogy        |  |  - Webhook Retry & Resilient MES Dispatch      |
|  |  - Immutable Movement Audit Logs        |  |  - Real-time Scanner Rate Limiting & Lock Caches|
|  +─────────────────────────────────────────+  +────────────────────────────────────────────────+
+---------------------------------------------------------------------------------------------------+
                                            ▲
                                            │ (HTTP Webhook / REST Events)
+---------------------------------------------------------------------------------------------------+
|                        Manufacturing Execution System (MES - NestJS Level 3)                       |
|  - Receives Staged Lots -> Enables Machine Hopper Load                                            |
|  - Emits 'packaging.pallet_sealed' -> Triggers WMS Finished Goods Receiving                       |
+---------------------------------------------------------------------------------------------------+
```

---

## 2. Component Breakdown

| Component | Type | Responsibility | Technology |
|-----------|------|----------------|------------|
| `WMS Web Portal (Filament)` | UI Panel | Back-office management for warehouse managers, inventory valuation, stock adjustments, bin map configuration, and Delivery Order printing. | Laravel 11, Filament v3, Livewire 3, Tailwind CSS |
| `Scanner Mobile / API Gateway` | REST API | Headless API consumed by rugged handheld barcode/QR scanners (Zebra TC26, Honeywell) for high-speed scan validation, putaway, and picking. | Laravel Sanctum, Form Requests, Resources |
| `Inbound Receiving Engine` | Domain Service | Validates PO arrivals, registers supplier heat numbers, checks COA status, and dispatches ZPL/PDF label printing jobs. | Laravel Service, DomPDF / Barcode Service |
| `Bin Topology & Putaway Engine` | Domain Service | Models warehouse spatial coordinates (Zone-Aisle-Rack-Shelf-Bin) and evaluates weight/volume capacity rules for safe putaway. | Eloquent ORM, Spatial/Hierarchical logic |
| `FEFO/FIFO Allocation Engine` | Domain Service | Determines optimal lots for production based on oldest expiry date and lowest lot ID; enforces atomic stock reservations with `lockForUpdate()`. | Laravel Service, MariaDB InnoDB transactions |
| `MES Integration Gateway` | Integration Service | Asynchronously dispatches staged material events to the NestJS MES API and processes incoming finished goods webhooks. | Laravel HTTP Client, Guzzle, Redis Queues, Horizon |
| `Outbound & Shipping Engine` | Domain Service | Manages picklists, scan-to-truck verification manifests, and generates official Delivery Order documents. | Laravel Service, PDF Engine |
| `Inventory Ledger & Audit Trail` | Domain Engine | Maintains an append-only double-entry stock movement ledger (`inventory_movements`) ensuring complete auditable balance history. | Eloquent Event Observers, MariaDB InnoDB |
| `Queue & Background Worker` | Background Worker | Handles asynchronous background tasks: label printing, webhook retries, PDF exports, and hourly stock reconciliation. | Laravel Horizon, Redis Worker |

---

## 3. Routing & Middleware Strategy

### Monolith & Management Web App

- **Route Group:** `/admin` (Handled by Filament Admin Panel).
- **Authentication:** Standard Stateful Web Session Guard (`web`) with `auth` middleware.
- **Access Control:** Role-Based Access Control via `Spatie/laravel-permission` integrated into Filament Resource policies:
  - `Warehouse Manager`: Full CRUD, stock override, master data, Delivery Order approval.
  - `Receiver`: Inbound and PO intake only.
  - `Staging Handler`: Picklist execution and MES material dispatch only.
  - `FG Clerk`: Finished goods receipt and pallet putaway only.
  - `Shipping Clerk`: Truck loading and Delivery Order generation only.

### Handheld Scanner & External Integration API

- **Route Prefix:** `/api/v1`
- **Authentication:** Stateless Bearer Token via **Laravel Sanctum**:
  - Industrial Scanners authenticate via long-lived device tokens.
  - NestJS MES communicates using a dedicated integration service token (`abilities: ['mes:sync']`).
- **Middleware Pipeline:**
  - `throttle:api`: Rate limiting (120 requests/minute per device).
  - `auth:sanctum`: Token verification and scope checks.
  - `EnsureJsonHeader`: Enforces `Accept: application/json`.
- **Webhook Endpoint:**
  - `POST /api/v1/webhooks/mes`: Protected by HMAC SHA-256 signature verification (`X-MES-Signature`) to guarantee authenticity of messages originating from NestJS.

---

## 4. Data Flow

### 4.1 Inbound Raw Material Receiving & Putaway

```text
[Supplier Truck Unloading]
       │
       ▼ (Receive PO & Inspect Bags/Pallets)
[Inbound Receiver: Web Form / Scanner Form]
       │
       ├──► [Validate PO Number & Supplier Identity]
       ├──► [Record Vendor Lot / Heat Number & Attach COA]
       ├──► [Generate Raw Material Lot (Status: QA_HOLD)]
       ▼
[Print Barcode / QR Pallet Labels (Code128 / DataMatrix)]
       │
       ▼ (QA Inspector Approves Resin: Status ──► AVAILABLE)
[Guided Putaway Scan: Scan Pallet QR ──► Scan Target Bin Barcode]
       │
       ▼ (Execute inside DB Transaction)
[Update Bin Allocation & Append to inventory_movements Ledger]
```

### 4.2 MES Material Staging Handover (Shop Floor Replenishment)

```text
[MES Production Order Scheduled (Needs 500kg Polypropylene PP-503)]
       │
       ▼ (MES Webhook or WMS Cron Dispatch)
[WMS Generates Staging Picklist]
       │
       ├──► [Apply FEFO Algorithm: Select Oldest Expiring Available Lots]
       ├──► [Reserve Stock via lockForUpdate() to prevent concurrent double-pick]
       ▼
[Material Handler scans Pallet/Bag at Warehouse Exit Door]
       │
       ▼
[WMS Updates Location: STORAGE_BIN ──► STAGING_BUFFER]
       │
       ▼ (Asynchronous Queue Job via Redis)
[WMS Integration Client calls MES: POST /api/v1/materials/lots]
       │
       ▼
[MES Ingests Lot ID & Heat # ──► Shop Floor Operators can load Hopper]
```

### 4.3 Finished Goods Inbound Handover from MES Packaging Station

```text
[MES Packaging Station: Box/Pallet Sealed & Barcoded]
       │
       ▼ (MES Gateway emits 'packaging.unit_created' Webhook)
[POST /api/v1/webhooks/mes (WMS Ingestion Controller)]
       │
       ├──► [Verify HMAC Signature]
       ├──► [Record pending finished_goods_receipt with MES Carton UUID]
       ▼
[FG Warehouse Clerk: Scans Pallet Barcode at FG Warehouse Gate]
       │
       ├──► [Verify Carton Serial matches MES Handover Record]
       ├──► [Prompt Putaway: Scan Target Rack Bin e.g. FG-RACK-02-B]
       ▼
[Update inventory_balances for Finished Goods SKU & Mark Handover Completed]
```

### 4.4 Outbound Shipping & Delivery Orders

```text
[Approved Delivery Order / Customer Shipping Schedule]
       │
       ▼
[Generate Outbound Picklist (Optimized S-Shape Warehouse Route)]
       │
       ▼ (Picker retrieves Pallets from FG Bins)
[Scan Pallet Barcodes to Confirm Pick]
       │
       ▼ (Truck Arrives at Shipping Bay)
[Shipping Clerk: Scan-to-Truck Verification (Scan each box into Manifest)]
       │
       ├──► [If All Items Match DO: Status ──► DISPATCHED]
       ├──► [Deduct Stock from inventory_balances]
       ├──► [Log to inventory_movements Ledger with Transaction Type: SHIPMENT]
       ▼
[Generate & Print Standardized PDF Delivery Order]
```

---

## 5. Security Plan

| Area | Strategy |
|------|----------|
| **Authentication** | Dual mode: Web session guard for Filament desktop users (Bcrypt/Argon2id passwords, remember tokens, optional 2FA); Laravel Sanctum bearer tokens for handheld scanners and API clients. |
| **Authorization** | Strict Role-Based Access Control (RBAC) powered by `spatie/laravel-permission`. Resource policies restrict actions at the model level (e.g. only `Warehouse Manager` can execute inventory adjustments). |
| **CSRF Protection** | Native Laravel CSRF tokens verified on all state-mutating web routes; Sanctum API routes protected against CSRF via stateless token validation. |
| **XSS Prevention** | Automatic Blade and Livewire contextual HTML escaping; strict sanitization of user-entered free-text notes. |
| **SQL Injection** | Enforced 100% via Eloquent ORM and PDO prepared statement bindings; raw queries are strictly forbidden. |
| **File Upload Security** | Inbound COA documents (PDF/images) strictly validated for MIME type (`application/pdf`, `image/jpeg`), sanitized filenames, stored on private disks, and served only via signed URLs. |
| **Audit Trail** | Append-only `inventory_movements` table acts as a financial-grade ledger. Every stock alteration records `user_id`, `source_bin_id`, `destination_bin_id`, `quantity`, `reference_type`, and `reference_id`. |

---

## 6. Deployment Architecture

```text
[Development / Testing] ──► [Docker: PHP 8.3-FPM + Nginx + MariaDB 11.4 + Redis]
                        ──► [Artisan Serve / Vite Dev Server]
                        ──► [Mock MES Webhook Tester]
```

| Environment | Server | Domain | Branch |
|------------|--------|--------|--------|
| **Local Development** | Docker Compose on Local Workstation | `http://localhost:8000` | `feature/*`, `develop` |
| **Staging / QA** | Dockerized Ubuntu Server on Factory LAN | `http://wms-staging.factory.local` | `develop` |
| **Production** | Redundant Linux Host on Factory Subnet / HTTPS | `https://wms.factory.local` | `main` |

---

## 7. Key Decisions

| # | Decision | Rationale | ADR |
|---|----------|-----------|-----|
| 1 | Laravel 11 with Filament v3 Framework | Fast development speed, robust enterprise administrative tooling, native support for Livewire, and seamless API generation. | [`ADR-001-laravel-filament-wms-architecture.md`](file:///home/ughway/Debiancode/Myprojects/Manufacturing-Execution-System/.future/docs/dev/notes/ADR-001-laravel-filament-wms-architecture.md) |
| 2 | Double-Entry Inventory Ledger (`inventory_movements`) | Ensures every stock increase or decrease is tied to an immutable transaction record, preventing unexplainable stock discrepancies. | Included in Architecture & Database Plan |
| 3 | Pessimistic Locking (`lockForUpdate()`) for Picking | Eliminates race conditions when multiple material handlers or automated schedulers attempt to pick the same resin lot simultaneously. | Included in Architecture & Database Plan |
| 4 | Decoupled Asynchronous Webhook Integration with MES | Protects WMS from network timeouts or temporary maintenance downtime of the NestJS MES API. | [`SPEC-001-mes-wms-interface-contract.md`](file:///home/ughway/Debiancode/Myprojects/Manufacturing-Execution-System/.future/docs/dev/notes/SPEC-001-mes-wms-interface-contract.md) |
