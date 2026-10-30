# Implementation Workflow 012: Returns Management (Customer RMA & Supplier RTV)

**Status:** `COMPLETE`  
**Target Outcome:** Production-grade implementation of Customer Return Merchandise Authorizations (RMA) and Supplier Return-to-Vendor (RTV) operations across Filament Admin and Rugged Mobile Scanner PWA.  
**Owner:** Core Architecture & Logistics Engineering  
**References:**
- [ADR-007: Comprehensive Returns Management Architecture](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/ADR-007-returns-management-rma-and-rtv.md)
- [SPEC-007: Returns Management Contract](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/notes/SPEC-007-returns-management-contract.md)
- [Workflow 011: Dynamic Min-Max Bin Replenishment](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/011-dynamic-min-max-bin-replenishment.md)

---

## 1. Objective and Scope

### Objective
Provide comprehensive returns management covering downstream customer defect returns (RMA with quarantine receiving and disposition) and upstream supplier raw material returns (RTV with debit memo recording and outbound inventory write-off).

### In Scope
1. **Database Schema & Migrations:**
   - Create tables `customer_rmas`, `customer_rma_items`, and `supplier_rtvs`.
   - Models: `CustomerRma`, `CustomerRmaItem`, `SupplierRtv`.
2. **Domain Services:**
   - `CustomerRmaService`: Creation, scanner dock receiving into quarantine, quality disposition execution (`RESTOCK`, `SCRAP_REGRIND`, `SCRAP_DISPOSAL`).
   - `SupplierRtvService`: Creation, approval, physical outbound dispatch, and vendor debit note logging.
   - `InventoryMovementService`: Movement types `CUSTOMER_RETURN`, `RETURN_TO_VENDOR`, and `SCRAP`.
3. **Filament Admin Management:**
   - `CustomerRmaResource` under *Quality & Traceability* with inspection disposition modal action.
   - `SupplierRtvResource` under *Quality & Traceability* with approval and dispatch actions.
4. **Rugged Scanner PWA Terminal:**
   - Mode 11: **Returns Receiving** (`/scanner/returns`).
   - Mobile intake of returned cartons into quarantine bays.
5. **Testing & Quality Assurance:**
   - Feature tests covering RMA receiving, disposition routing, RTV dispatch, and ledger invariants on MariaDB 11.8.

### Out of Scope
- Direct banking API wire transfers for debit/credit balances.

---

## 2. Staged Execution Plan

### Stage 1: Database Migrations & Domain Models
**Objective:** Create relational tables and Eloquent models for customer RMAs and supplier RTVs.
1. Create migration `2026_11_01_000016_create_returns_management_tables.php`.
2. Implement models: `App\Models\CustomerRma.php`, `App\Models\CustomerRmaItem.php`, `App\Models\SupplierRtv.php`.
3. Run migration on MariaDB 11.8.

**Exit Criteria:**
- [x] Database migration executed cleanly.
- [x] Model relationships, casts, and constants verified.

### Stage 2: Domain Services & Movement Extensions
**Objective:** Build business logic for RMA intake/disposition and RTV dispatch with double-entry movements.
1. Add movement types `TYPE_CUSTOMER_RETURN`, `TYPE_RETURN_TO_VENDOR`, and `TYPE_SCRAP` in `InventoryMovement.php`.
2. Add methods `recordCustomerReturn()`, `recordReturnToVendor()`, and `recordScrap()` to `InventoryMovementService.php`.
3. Implement `App\Services\CustomerRmaService.php` and `App\Services\SupplierRtvService.php`.

**Exit Criteria:**
- [x] RMA intake credits quarantine bin.
- [x] Disposition restock credits active bin; scrap write-offs clear stock.
- [x] RTV dispatch decrements quarantine stock with zero variance.

### Stage 3: Filament Admin Returns Resources
**Objective:** Provide quality and logistics managers with full returns governance.
1. Implement `CustomerRmaResource.php` with disposition modal action.
2. Implement `SupplierRtvResource.php` with approve and dispatch actions.

**Exit Criteria:**
- [x] Managers can inspect RMAs, execute dispositions, and dispatch RTVs.

### Stage 4: Rugged Scanner PWA Returns Mode 11
**Objective:** Equip receiving dock operators with mobile scanner returns intake.
1. Implement `App\Http\Controllers\Api\ScannerReturnsController.php` with API routes.
2. Create view `resources/views/scanner/modes/returns.blade.php`.
3. Register Mode 11 in `ScannerWebController.php`, dashboard, and drawer.

**Exit Criteria:**
- [x] Dock operators scan returned cartons against RMA into quarantine.

### Stage 5: Verification & Quality Assurance
**Objective:** Run exhaustive automated test suite and ensure zero regressions.
1. Write Feature tests in `tests/Feature/ReturnsManagementTest.php`.
2. Run Laravel Pint style checks.
3. Run full test suite on MariaDB 11.8.

**Exit Criteria:**
- [x] 100% passing tests (84 tests, 586 assertions).
- [x] 0 Pint violations.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- Existing `DeliveryOrder`, `Item`, `MaterialLot`, `WarehouseBin`, `InventoryMovementService`.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Returned defect parts placed in active bins by mistake | Factory re-uses defective parts | Scanner enforces mandatory intake into `ZONE-QUARANTINE` only. |
| Vendor debit not tracked | Financial loss on bad resin | Supplier RTV requires mandatory `debit_memo_number` before dispatch. |

### Rollback or Recovery
Database migration has clean `down()` dropping RMA and RTV tables.
