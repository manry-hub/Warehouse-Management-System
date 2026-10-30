# Product Requirements Document — Warehouse Management System (WMS)

> **Status:** `PLANNING` — New Project Mode  
> **Purpose:** Single source of truth for product vision, user stories, and planned features for the standalone Laravel-based Warehouse Management System (WMS).

---

## 1. Product Vision

### Elevator Pitch

**Smart WMS (Warehouse Management System) is an enterprise-grade warehouse management platform built with Laravel 11 and Filament v3, designed specifically to govern raw material logistics (resin pellets, masterbatch, additives), staging buffers, and finished goods distribution for discrete injection molding manufacturing, integrating seamlessly with the shop-floor MES (Manufacturing Execution System).**

### Problem Statement

In discrete plastic injection molding facilities, warehouse operations struggle with major logistical gaps:
1. **Traceability Blind Spots:** Incoming raw material lots (resin bags, gaylords, masterbatch) lack standardized bin-level traceability, making vendor heat numbers and Certificate of Analysis (COA) data hard to trace back during quality audits or customer recalls.
2. **Shop Floor Material Starvation & Stock-outs:** Staging hoppers and injection machines frequently experience micro-delays due to uncoordinated material requisition between warehouse handlers and shop floor line operators.
3. **Mismatched Finished Goods Handover:** Molded and packaged parts transferred from the packaging station lack automated handover verification, leading to discrepancy between MES packed counts and actual warehouse bin storage.
4. **FIFO / FEFO Violations:** Plastic resin granules and pigments with sensitive shelf lives or drying requirements are prone to improper dispatching without strict First-Expired, First-Out (FEFO) rules.
5. **Paper-Driven Dispatching:** Shipping orders, truck picklists, and Delivery Orders (DO) are handled manually, causing truck loading delays and fulfillment errors.

### Target Users

| Role | Description | Primary Need |
|------|-------------|--------------|
| `Warehouse Manager` | Head of warehouse operations & supply chain | Real-time inventory valuation, warehouse capacity utilization, bin fill-rates, and dispatch SLA oversight. |
| `Inbound Material Receiver` | Receiving clerk at the unloading dock | Inspecting POs, recording vendor lots/COA, printing GS1-128/QR pallet labels, and assigning putaway bins. |
| `Material Handler / Staging Clerk` | Shop floor replenishment handler | Picking raw materials (resin/masterbatch) via FIFO/FEFO picklists, issuing material to MES hoppers, and tracking regrind return. |
| `Finished Goods Clerk` | Warehouse personnel in FG zone | Scanning incoming pallets/cartons from MES packaging terminals, verifying carton counts, and assigning rack locations. |
| `Outbound Dispatcher / Shipping Clerk` | Shipping dock coordinator | Picking orders based on Sales/Delivery Orders, verifying truck loading with handheld barcode scanners, and issuing Delivery Orders (DO). |
| `Quality Assurance Inspector` | Raw material incoming QA inspector | Sampling incoming raw lots, recording moisture content/melt flow index (MFI), and marking lots as `APPROVED`, `HOLD`, or `REJECTED`. |
| `System Administrator` | Technical & platform administrator | Managing user roles, printer configs, API tokens, webhook endpoints, and sync logs with the NestJS MES. |

---

## 2. User Stories / Use Cases

### Epic 1: Inbound Logistics & Raw Material Receiving

| ID | User Story | Priority | Acceptance Criteria |
|----|-----------|----------|-------------------|
| `US-001` | As an Inbound Material Receiver, I want to receive raw material shipments against Purchase Orders so that stock is accurately accounted for. | `P0` | System validates PO numbers, records received bag/pallet quantities, records vendor lot/heat number, and attaches digital COA PDFs. |
| `US-002` | As an Inbound Receiver, I want to automatically generate and print barcode/QR labels upon receipt so that all bags and pallets are identifiable. | `P0` | Labels include Lot Number, Material SKU, Vendor Code, Received Date, Expiry Date, and unique Pallet/Bag UID. |
| `US-003` | As a QA Inspector, I want to place received lots on QA Hold until lab inspection passes so that unverified resins are never dispatched to machines. | `P0` | Newly received resin defaults to `QA_HOLD`; only authorized QA users can release status to `AVAILABLE` or reject to `QUARANTINE`. |

### Epic 2: Warehouse Bin & Location Management

| ID | User Story | Priority | Acceptance Criteria |
|----|-----------|----------|-------------------|
| `US-004` | As a Warehouse Manager, I want to model a multi-zone warehouse hierarchy (Zone, Aisle, Rack, Shelf, Bin) so that physical inventory locations are clearly visualized. | `P0` | Support zone types: `RAW_MATERIAL`, `STAGING_BUFFER`, `FINISHED_GOODS`, `PACKAGING_SUPPLIES`, and `QUARANTINE_CAGE`. |
| `US-005` | As a Material Handler, I want guided putaway recommendations so that materials are stored in optimal and safe bin locations. | `P1` | System suggests available bins matching weight/volume capacity and segregates non-compatible chemical grades. |

### Epic 3: Shop Floor Material Issue & MES Integration

| ID | User Story | Priority | Acceptance Criteria |
|----|-----------|----------|-------------------|
| `US-006` | As a Material Handler, I want to receive automated material request picklists triggered by MES production schedules so that resin is staged ahead of runs. | `P0` | WMS receives production schedule demands from MES; generates picklist enforcing strict FEFO/FIFO allocation. |
| `US-007` | As a Material Handler, I want to scan material bags during staging handover to synchronize lot availability with MES. | `P0` | Handover scan fires API/webhook to MES `POST /api/v1/materials/lots`, enabling immediate operator hopper loading in MES. |
| `US-008` | As a Material Handler, I want to process regrind (recycled runner scrap) returns from the shop floor so that regrind material is tracked with proper blend ratios. | `P1` | Record regrind lot tied to the original parent production order with standard 10–20% blend cap. |

### Epic 4: Finished Goods Inbound & Reconciliation

| ID | User Story | Priority | Acceptance Criteria |
|----|-----------|----------|-------------------|
| `US-009` | As a Finished Goods Clerk, I want to receive pallet handovers from MES packaging stations by scanning barcode aggregations. | `P0` | System verifies pallet/carton serial against MES `packaging_units`; updates status to `FG_RECEIVED` and assigns FG rack bin. |
| `US-010` | As a Warehouse Manager, I want real-time reconciliation between MES packed quantity and FG warehouse stored quantity. | `P1` | Flag count discrepancies ($> 0\%$) for supervisor sign-off before closing production batch. |

### Epic 5: Outbound Fulfillment, Picking & Shipping

| ID | User Story | Priority | Acceptance Criteria |
|----|-----------|----------|-------------------|
| `US-011` | As an Outbound Dispatcher, I want to create picklists for approved Delivery Orders so that pickers gather the correct customer parts. | `P0` | Generates picklists ordered by optimal warehouse travel route with item SKU, bin coordinates, and lot numbers. |
| `US-012` | As a Shipping Clerk, I want scan-to-verify loading on outgoing trucks so that no incorrect pallets or quantities are shipped. | `P0` | Handheld scan validates each carton/pallet against DO; blocks truck manifest finalization if mismatched. |
| `US-013` | As an Outbound Dispatcher, I want to generate and print legal Delivery Orders (DO) and packing slips. | `P0` | Generates standardized PDF Delivery Orders with driver details, vehicle plate, recipient address, and digital signatures. |

### Epic 6: Stock Opname & Inventory Cycle Counting

| ID | User Story | Priority | Acceptance Criteria |
|----|-----------|----------|-------------------|
| `US-014` | As a Warehouse Manager, I want to execute periodic blind cycle counts so that physical inventory matches digital records. | `P1` | Supports blind counts where counters submit quantities without seeing expected numbers; reports variance delta. |

---

## 3. Feature List

### MVP (Minimum Viable Product)

| # | Feature | Description | Epic |
|---|---------|-------------|------|
| 1 | Inbound PO Receiving & Lot Intake | Record raw material receipt against PO, vendor lot, heat #, and initial bag/pallet counts. | Epic 1 |
| 2 | QR/Barcode Label Generator & Print | Generate GS1-compatible QR/Barcode labels for raw material pallets, bags, and finished goods boxes. | Epic 1, 4 |
| 3 | Warehouse Bin Topology Manager | Visual CRUD for Warehouse, Zone, Aisle, Rack, Shelf, and Bin with dimension & weight constraints. | Epic 2 |
| 4 | FEFO/FIFO Guided Material Picking | Algorithm assigning oldest/first-expiring available lots for production dispatch. | Epic 3 |
| 5 | Bi-directional MES Integration Gateway | Webhook and REST API client/server synchronizing lots and finished goods with the NestJS MES. | Epic 3, 4 |
| 6 | Finished Goods Handover Ingestion | Barcode scanning module to receive sealed cartons and pallets from MES Packaging Stations. | Epic 4 |
| 7 | Outbound Delivery Order (DO) | Pick, pack, truck scan verification, and PDF Delivery Order generation with vehicle & driver metadata. | Epic 5 |
| 8 | Filament v3 Administrative Portal | Comprehensive role-based back-office dashboard for inventory valuation, stock movements, and audit trails. | All Epics |

### Post-MVP / Future

| # | Feature | Description | Priority |
|---|---------|-------------|----------|
| 1 | Mobile PWA / Scanner Camera Mode | Dedicated Flutter or Quagga/ZXing-powered mobile UI for rugged Android barcode terminals (Zebra/Honeywell). | Medium |
| 2 | Automated Silo Level Sensor Ingestion | Ingestion of radar/ultrasonic silo levels via MQTT to trigger automated PO requisitions. | Low |
| 3 | Multi-Warehouse Cross-Docking | Transfer orders and inventory movements across multiple distant manufacturing sites. | Low |
| 4 | 3D Warehouse Bin Digital Twin | Interactive Three.js/WebGL representation of rack fill-rates and hot spots. | Low |

---

## 4. Constraints & Assumptions

### Constraints

- **Technology Stack:** Must be developed with **Laravel 11**, PHP 8.3+, and **Filament PHP v3** as the primary admin & operations panel.
- **Relational Integrity:** High-concurrency warehouse transactions (picking, reservation, putaway) must execute inside ACID database transactions with pessimistic locking (`lockForUpdate()`) to prevent double-allocation of stock.
- **Barcode Compatibility:** Barcode system must support standard 1D (Code 128) and 2D (QR Code / DataMatrix) readable by both handheld industrial 2D imagers and mobile device cameras.
- **Sub-Second Latency:** Inbound and outbound scan validation responses must return in $< 300\text{ms}$ on the local factory LAN.

### Assumptions

- The existing NestJS MES system is accessible via factory LAN or private VPC and exposes authenticated REST/Webhook endpoints.
- Raw material suppliers provide standard packing units (e.g., 25 kg bags, 500 kg gaylords, or bulk tanker octabins) with identifiable manufacturer lot/batch numbers.
- Warehouse staff have access to desktop terminals or rugged Android barcode scanners connected via 5GHz enterprise Wi-Fi.

---

## 5. Success Metrics

| Metric | Target | Measurement Method |
|--------|--------|--------------------|
| Raw Material Staging Lead Time | $< 15$ minutes from MES call | Time elapsed between MES production order dispatch and WMS staging scan confirmation. |
| Inventory Accuracy (Stock Opname) | $\ge 99.2\%$ | $\frac{\text{Physical Count Counted}}{\text{Digital System Recorded}} \times 100$ during monthly cycle counts. |
| Finished Goods Reconciliation Error | $0\%$ unverified delta | Zero discrepancy between MES packed cartons and WMS stored finished goods. |
| Inbound Receiving to Storage Cycle | $< 30$ minutes per truckload | Timestamp from PO check-in to completed putaway bin confirmation. |
| Shipping Loading Accuracy | $100\%$ scan match | Zero incorrect pallets or items loaded on outgoing shipping trucks. |

---

## 6. Out of Scope

**The following will not be built in this version:**

- General Ledger (GL) accounting, invoicing, and tax calculations (delegated to ERP / Akaunting).
- Machine telemetry, PLC signal ingestion, and real-time OEE calculations (handled exclusively by NestJS MES).
- Fleet GPS tracking and external delivery truck routing optimization.
- E-commerce consumer checkout or retail POS payment gateway processing.
