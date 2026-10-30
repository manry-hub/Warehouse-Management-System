# 001 — Warehouse Management System (WMS) Core Implementation

Date: 2026-10-30

## Checklist

- [x] Install core packages: Filament v3, Laravel Sanctum, Spatie Permission, BaconQrCode, Picqer Barcode Generator, and Barryvdh DomPDF.
- [x] Configure Filament Admin panel provider and database connection.
- [x] Implement database migrations: Warehouses, Zones, Bins, Items, Lots, Balances, Movements Ledger, Staging, Delivery Orders, and Integration Outbox.
- [x] Implement Eloquent models with UUIDv7 primary keys and explicit relationships.
- [x] Populate seeders with realistic plastic injection plant warehouse topology, items, and role permissions.
- [x] Build core domain services: `InventoryMovementService`, `FefoAllocationService`, `LotQualityService`, `BarcodeLabelService`, and `DeliveryOrderService`.
- [x] Build Filament v3 back-office management resources (`WarehouseResource`, `WarehouseBinResource`, `ItemResource`, `MaterialLotResource`, `InventoryMovementResource`, `DeliveryOrderResource`).
- [x] Implement Sanctum-secured REST API for industrial handheld barcode scanners (Inbound, Putaway, Picking, Truck Loading).
- [x] Implement bi-directional NestJS MES Integration Gateway (HMAC-SHA256 webhook receiver with idempotency deduplication and `ProcessOutboxJob`).
- [x] Verify mathematical zero-sum ledger invariants and concurrency allocation safety via automated Pest test suites.

## Change Description

Implemented the complete operational and transactional core of the standalone Warehouse Management System (WMS) in Laravel 11 and Filament v3:
1. **Append-Only Double-Entry Inventory Ledger:** All physical receipts, internal putaway transfers, machine hopper staging issues, finished goods intakes, and customer shipments are recorded as immutable journal entries in `inventory_movements`, with real-time aggregates in `inventory_balances`.
2. **Pessimistic FEFO Picking Engine:** `FefoAllocationService` queries lots ordered by earliest expiration date, locks candidate rows atomically with `lockForUpdate()`, and enforces strict guards skipping unapproved lots (`QA_HOLD`, `QUARANTINE`, or expired).
3. **Lot Quality FSM:** Governs QA Hold, Melt Flow Index (MFI) & moisture COA metrics recording, and release to available.
4. **Filament v3 Management Panel:** Comprehensive back-office interface for managers with status badges, COA viewers, QR/ZPL label modals, and streamed Delivery Order PDF generation.
5. **Rugged Scanner API:** Lightweight JSON API secured by Laravel Sanctum personal access tokens for Zebra/Honeywell barcode scanners.
6. **MES Gateway & Outbox:** Robust HMAC-SHA256 webhook ingestion for `packaging.unit_created` events with replay idempotency, alongside a queued `ProcessOutboxJob` delivering staged lots to the MES Core API.

## Modified Files

- `composer.json` — edited (added core packages, advisory policy, platform overrides)
- `bootstrap/app.php` — edited (registered api routes)
- `config/services.php` — edited (registered MES integration credentials)
- `database/migrations/*` — added (13 migrations for topology, items, lots, ledger, orders, outbox)
- `app/Models/*` — added (12 Eloquent models)
- `app/Exceptions/InsufficientStockException.php` — added
- `app/Exceptions/InvalidLotStateException.php` — added
- `app/Services/InventoryMovementService.php` — added
- `app/Services/FefoAllocationService.php` — added
- `app/Services/LotQualityService.php` — added
- `app/Services/BarcodeLabelService.php` — added
- `app/Services/DeliveryOrderService.php` — added
- `app/Filament/Resources/*` — added (Filament v3 management resources)
- `app/Http/Controllers/Api/*` — added (Scanner and MES webhook controllers)
- `app/Http/Middleware/VerifyMesWebhookSignature.php` — added
- `app/Jobs/ProcessOutboxJob.php` — added
- `routes/api.php` — added
- `resources/views/pdf/delivery-order.blade.php` — added
- `tests/Feature/*` — added (Core domain, scanner API, MES webhook, MES outbox, concurrency & invariants)

## Review

- Result: Core WMS architecture, MariaDB 11.8 relational store, and operational modules fully implemented and verified.
- Database: Primary database connected to MariaDB 11.8 on `127.0.0.1:3306` (`WMS_DB`), test database configured on `WMS_DB_TEST`.
- Verification: 14 test cases with 63 assertions passing with 100% success rate (`php artisan test`) against native MariaDB InnoDB with strict UUID validation. Laravel Pint formatting applied.
- Follow-up: None. Ready for end-user testing or physical terminal deployment.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Inbound receipt & putaway creates balanced double-entry ledger | Pass |
| 2 | FEFO picking prioritizes earliest expiry and skips QA Hold lots | Pass |
| 3 | Lot Quality FSM enforces inspection metrics and guards transitions | Pass |
| 4 | Delivery Order PDF generation and scan-to-truck verification | Pass |
| 5 | Handheld scanner Sanctum login and barcode intake API | Pass |
| 6 | Handheld scanner staging pick API rejects unapproved lots | Pass |
| 7 | MES webhook verifies HMAC-SHA256 signature and rejects tampered data | Pass |
| 8 | MES webhook enforces idempotency on duplicate `eventId` delivery | Pass |
| 9 | Outbox queued job dispatches staged lots to MES Core API | Pass |
| 10 | Mathematical zero-sum ledger invariant ($\sum\text{debit} - \sum\text{credit} - \text{stock} = 0$) | Pass |
| 11 | Concurrency stress: successive allocations prevent negative stock or over-reservation | Pass |
