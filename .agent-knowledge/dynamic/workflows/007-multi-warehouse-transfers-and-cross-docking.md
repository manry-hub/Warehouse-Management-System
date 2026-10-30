# Implementation Workflow 007: Multi-Warehouse Transfers & Cross-Docking Engine

**Status:** `COMPLETE`  
**Target Outcome:** Multi-facility transfer order lifecycle, virtual `WH-TRANSIT` in-transit double-entry inventory ledger movements, and lean cross-docking putaway bypass engine directly routing inbound lots to outbound shipping staging.  
**Owner:** Core Logistics & Multi-Facility Architecture Team  
**References:**
- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md) (Post-MVP Priority #3)
- [ADR-003](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/ADR-003-multi-warehouse-transfers-and-cross-docking.md)
- [SPEC-003](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/SPEC-003-transfer-order-and-cross-docking-contract.md)
- [DESIGN.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/DESIGN.md)

---

## 1. Objective and Scope

### Objective
Provide discrete plastic injection molding enterprises with robust multi-facility logistics capabilities, ensuring end-to-end ledger traceability for inventory travelling between plants on public highways while accelerating outbound shipping turnaround via lean cross-docking putaway bypass.

### In Scope
- Database migrations for `transfer_orders` and `transfer_order_items`.
- Seed virtual in-transit warehouse `WH-TRANSIT` (`ZONE-INTRANSIT`, `BIN-TRANSIT`) and regional distribution center `WH-RDC`.
- Domain service `TransferOrderService.php` handling approval, dispatch truck loading, and destination receipt.
- Domain service `CrossDockService.php` evaluating inbound lots against open delivery orders, issuing direct staging directives, and reserving inventory.
- Movement types `TRANSFER_OUT` and `TRANSFER_IN` in `InventoryMovementService.php`.
- Filament portal: `TransferOrderResource.php` with status timeline, waybill printing, and cross-docking monitoring.
- Mobile scanner endpoints & view: Transfer Dispatch and Inbound Cross-Dock Receiving.
- Comprehensive automated test suite validating zero-sum ledger conservation and cross-docking reservations.

### Out of Scope
- GPS vehicle telematics / satellite temperature sensors.
- Automated carrier rate calculation / freight billing.

---

## 2. Staged Execution Plan

### Stage 1: Data Architecture & Virtual In-Transit Topology
**Objective:** Create database tables and establish multi-warehouse topology (`WH-MAIN`, `WH-RDC`, `WH-TRANSIT`).
1. Create migration `2026_11_01_000012_create_transfer_orders_table.php`.
2. Define models `TransferOrder` and `TransferOrderItem`.
3. Add `TYPE_IN_TRANSIT = 'IN_TRANSIT'` to `WarehouseZone`.
4. Update `WarehouseTopologySeeder.php` to seed `WH-TRANSIT` and `WH-RDC`.

**Exit Criteria:**
- [x] Migrations run cleanly on MariaDB 11.8 (`WMS_DB` and `WMS_DB_TEST`).
- [x] Models and relationships function with UUID keys.

### Stage 2: Transfer Order & Cross-Docking Domain Engines
**Objective:** Implement double-entry inventory ledger movements for transfer dispatch/receipt and lean cross-docking bypass matching.
1. Extend `InventoryMovement` with constants `TYPE_TRANSFER_OUT = 'TRANSFER_OUT'` and `TYPE_TRANSFER_IN = 'TRANSFER_IN'`.
2. Implement `TransferOrderService.php` (`approve`, `dispatch`, `receive`).
3. Implement `CrossDockService.php` (`detectAndAllocateCrossDock`).
4. Support cross-dock allocation directly into `delivery_orders`.

**Exit Criteria:**
- [x] Dispatch deducts from origin bin and credits `BIN-TRANSIT` without changing total system balance.
- [x] Receipt clears `BIN-TRANSIT` and routes either to destination storage bin or cross-docks directly to shipping staging.

### Stage 3: Filament Management & Mobile Handheld Integration
**Objective:** Deliver web UI for logistics managers and mobile terminal views for dock operators following `DESIGN.md`.
1. Create `TransferOrderResource.php` with line items, truck details, and action buttons.
2. Embed cross-dock alert badge and indicator in Filament.
3. Add mobile scanner endpoint / quick action for transfer receiving with cross-dock popups.
4. Update `RealisticOperationsSeeder.php` with sample active and completed transfer orders.

**Exit Criteria:**
- [x] Filament users can create, approve, dispatch, and receive transfer orders.
- [x] Cross-dock alert displays clearly on matches.

### Stage 4: Verification, Test Suite, and Task Report
**Objective:** Validate all invariants, run test suite, and document results.
1. Create `tests/Feature/MultiWarehouseTransferAndCrossDockTest.php`.
2. Execute full automated test suite on MariaDB (`WMS_DB_TEST`).
3. Run Laravel Pint style checks.
4. Create task report `.agent-knowledge/dynamic/reports/tasks/007-multi-warehouse-transfers-and-cross-docking.md`.

**Exit Criteria:**
- [x] All tests pass on MariaDB (`WMS_DB_TEST`).
- [x] Task report records all changes and verification results.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- MariaDB 11.8 InnoDB database.
- Existing `InventoryMovementService`, `Warehouse`, `WarehouseBin`, `MaterialLot`, `DeliveryOrder`.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| :--- | :--- | :--- |
| **Inventory Inbalance during Transit** | Stock in transit lost or double-counted. | Strict atomic database transactions using `WH-TRANSIT` virtual warehouse ledger. |
| **Cross-Docking Over-Allocation** | Same delivery order allocated multiple times. | Check `allocated_quantity < quantity` within DB lock before creating reservation. |

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| :--- | :--- | :--- |
| **Zero-Sum Ledger Across Transfers** | Feature test | Origin minus, Transit plus on dispatch; Transit minus, Destination plus on receipt. Net change is 0. |
| **Cross-Docking Bypass** | Feature test | Inbound lot matching pending delivery order is staged directly to staging dock and reserved. |
| **Filament Workflow** | Livewire test | Status transitions cleanly through draft, approved, in-transit, and completed. |

---

## 5. Completion and Handoff

- [ ] All staged exit criteria and acceptance criteria are met.
- [ ] Relevant living documentation, ADRs, and specifications are updated or linked.
- [ ] The task report records actual changes and verification results.
