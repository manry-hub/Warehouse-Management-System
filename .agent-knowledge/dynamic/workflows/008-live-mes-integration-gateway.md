# Implementation Workflow 008: Live Bi-Directional MES-WMS Integration Gateway & Synchronization

**Status:** `COMPLETE`  
**Target Outcome:** Operational bi-directional integration between Laravel WMS and NestJS MES, featuring automated JWT service authentication, catalog harmonization, exact DTO translation for outbound raw material lot registration, outbox queue processing daemon, and inbound finished goods packaging webhook handling.  
**Owner:** Core Systems Integration & Architecture Team  
**References:**
- [SPEC-001: Bi-Directional MES-WMS Interface Contract](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/notes/SPEC-001-mes-wms-interface-contract.md)
- [ADR-001: Laravel 11 + Filament v3 WMS Architecture](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/notes/ADR-001-laravel-filament-wms-architecture.md)
- Companion MES Source: `~/Debiancode/Myprojects/Manufacturing-Execution-System`
- MES Schema: `~/Debiancode/Myprojects/Manufacturing-Execution-System/backend/prisma/schema.prisma`
- MES Materials Controller: `~/Debiancode/Myprojects/Manufacturing-Execution-System/backend/src/modules/materials/materials.controller.ts`
- MES Packaging Module: `~/Debiancode/Myprojects/Manufacturing-Execution-System/backend/src/modules/packaging/packaging.service.ts`

---

## 1. Objective and Scope

### Objective
Establish seamless, live, error-free bi-directional communication between the Laravel 11 WMS monolith and the companion NestJS Manufacturing Execution System (MES), ensuring real-time shop-floor raw material availability and automated finished goods warehouse intake upon packaging completion.

### In Scope
1. **Catalog & Master Data Harmonization**:
   - Align material SKUs and finished product part numbers in WMS with MES seed records (`RES-PP-HOMO`, `RES-ABS-HI`, `MB-BLK-01`, `RES-PP-REGRIND`, `PRD-AUTO-BUMPER`, `PRD-DOOR-TRIM`, `PRD-HVAC-DUCT`).
2. **Dynamic Service Authentication**:
   - Implement `MesAuthService.php` in WMS to automatically authenticate against MES `POST /api/v1/auth/login` (`admin` / `password123`), caching JWT access tokens with TTL.
3. **Outbound Material Registration Gateway (`MesGatewayService.php`)**:
   - Resolve or auto-register material codes in MES via `GET /api/v1/materials` and `POST /api/v1/materials`.
   - Strictly conform payload to MES `CreateMaterialLotDto` (`forbidNonWhitelisted: true`):
     ```json
     {
       "materialId": "<uuid>",
       "lotNumber": "<lot_number>",
       "supplierLot": "<vendor_heat_number>",
       "initialQtyKg": 500.0,
       "expirationDate": "2027-10-30T00:00:00.000Z"
     }
     ```
4. **Outbox Worker & Background Synchronization**:
   - Enhance `ProcessOutboxJob.php` and provide an Artisan command `wms:sync-mes-outbox` with exponential retry policies.
5. **MES Outbound Webhook Integration (Companion Project)**:
   - Add outbound webhook dispatcher to MES `packaging.service.ts` upon `packagingUnit.create` to hit WMS `POST /api/v1/webhooks/mes` with HMAC SHA-256 signatures (`X-MES-Signature`, `X-MES-Event-Id`).
6. **Automated Integration & Mock Test Suite**:
   - Feature tests covering token acquisition, DTO translation, outbox processing, and end-to-end receipt of packaging webhooks.

### Out of Scope
- Direct OPC UA / Euromap 77 PLC machine communication in WMS (owned exclusively by MES).
- Financial ledger invoicing and ERP purchasing sync.

### Assumptions and Open Questions
- MES runs on port 3000 (`http://localhost:3000` / `http://127.0.0.1:3000`).
- MES database uses Microsoft SQL Server (`MES_DB`).
- Shared HMAC secret key configured in both environments: `MES_WEBHOOK_SECRET=test-mes-secret-key`.

---

## 2. Staged Execution Plan

### Stage 1: Master Catalog Harmonization & Service Authentication
**Objective:** Align material/product SKUs between systems and establish authenticated JWT handshake from WMS to MES.
1. Update WMS `ItemCatalogSeeder.php` and `RealisticOperationsSeeder.php` with MES products (`PRD-AUTO-BUMPER`, `PRD-DOOR-TRIM`, `PRD-HVAC-DUCT`) and resins (`RES-PP-HOMO`, `RES-ABS-HI`, `RES-PP-REGRIND`).
2. Implement `App\Services\MesAuthService.php` in WMS:
   - Authenticates against `POST /api/v1/auth/login` using configured credentials.
   - Caches `accessToken` in Laravel Cache with a safe TTL (e.g. 7 hours).
   - Re-authenticates on 401 Unauthorized.

**Exit Criteria:**
- [x] WMS seeds contain all MES material and product SKUs.
- [x] Unit/feature test verifies `MesAuthService` obtains and caches a valid JWT token from MES.

### Stage 2: Outbox DTO Translation & Outbound Dispatcher
**Objective:** Transform staged material lots into MES's exact `CreateMaterialLotDto` schema and dispatch via outbox queue.
1. Implement `App\Services\MesGatewayService.php` in WMS:
   - Resolves MES material UUID by material code (queries `GET /api/v1/materials` with JWT, creates material if missing).
   - Formats payload to match `CreateMaterialLotDto` (`materialId`, `lotNumber`, `supplierLot`, `initialQtyKg`, `expirationDate`).
   - Dispatches `POST /api/v1/materials/lots`.
2. Refactor `App\Jobs\ProcessOutboxJob.php` to use `MesGatewayService`.
3. Add CLI command `php artisan wms:sync-mes-outbox {--limit=50}`.

**Exit Criteria:**
- [x] Outbox processor succeeds against MES DTO validation without `forbidNonWhitelisted` rejection.
- [x] Database record in `integration_outbox` transitions from `PENDING` to `PROCESSED`.

### Stage 3: MES Inbound Packaging Webhook Dispatcher
**Objective:** Equip the companion MES packaging service with outbound webhook delivery to WMS upon carton creation.
1. Inspect and update `~/Debiancode/Myprojects/Manufacturing-Execution-System/backend/src/modules/packaging/packaging.service.ts`:
   - Inject HTTP service or lightweight fetch client.
   - On `createPackagingUnit`, sign payload with HMAC SHA-256 (`crypto.createHmac('sha256', secret)`).
   - Send `POST http://localhost:8000/api/v1/webhooks/mes` with `X-MES-Signature` and `X-MES-Event-Id`.
   - Log delivery status non-blockingly.

**Exit Criteria:**
- [x] Creating a packaging unit in MES automatically notifies WMS.
- [x] WMS `MesWebhookController` verifies signature, logs carton in `material_lots`, and deposits it in `ZONE-FG`.

### Stage 4: End-to-End Verification, Tests & Documentation
**Objective:** Validate all integration paths with feature tests and update project records.
1. Create `tests/Feature/MesLiveIntegrationTest.php` testing token management, outbox translation, and webhook intake.
2. Run full test suites on both WMS (MariaDB) and MES.
3. Check code style with Laravel Pint.
4. Update task report `.agent-knowledge/dynamic/reports/tasks/008-live-mes-integration-gateway.md`.

**Exit Criteria:**
- [x] Complete WMS test suite passing (62 tests, 377 assertions on MariaDB 11.8).
- [x] Companion MES packaging test suite passing (5 tests on vitest).
- [x] Laravel Pint style check clean (`0 errors`).
- [x] Task report updated and verified.

**Exit Criteria:**
- [ ] 100% test suite pass rate on MariaDB (`WMS_DB_TEST`).
- [ ] Zero lint/format errors.
- [ ] Task report completed.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies
- Laravel 11 WMS running on PHP 8.5 / MariaDB 11.8.
- NestJS MES backend in `~/Debiancode/Myprojects/Manufacturing-Execution-System`.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| :--- | :--- | :--- |
| **MES Offline or Network Partition** | Outbox jobs fail to deliver staged lots. | Exponential backoff retry in `ProcessOutboxJob`, preserving state in `integration_outbox` table. |
| **Token Expiry during Dispatch** | HTTP 401 Unauthorized on batch sync. | `MesAuthService` automatically clears cached token on 401, re-authenticates, and retries once. |
| **Duplicate Webhook Delivery** | Repeated packaging webhooks could double-count stock. | WMS enforces strict idempotency on `eventId` via Redis/cache and `InventoryMovement` unique index. |

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| :--- | :--- | :--- |
| **MES JWT Authentication** | Integration test | `MesAuthService::getAccessToken()` returns valid JWT and caches it. |
| **Material Lot Registration** | Feature test | Outbox job dispatches `CreateMaterialLotDto` to MES and receives 201 Created. |
| **Packaging Webhook Receipt** | Feature test | Signed webhook creates lot in `ZONE-FG`, updates balance, and responds with 200 OK. |
| **Idempotency Defense** | Feature test | Duplicate webhook returns 200 OK without double-crediting inventory balances. |

---

## 5. Completion and Handoff

- [ ] All staged exit criteria and acceptance criteria are met.
- [ ] Master catalogs in WMS and MES are aligned.
- [ ] Task report records actual changes and verification results.
