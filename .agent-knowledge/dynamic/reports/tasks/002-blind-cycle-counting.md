# 002 — Blind Cycle Counting & Stock Opname

Date: 2026-10-30

## Checklist

- [x] Relational schema migration for `cycle_count_orders` and `cycle_count_items` with UUIDv7/v4 primary keys and compound indexes.
- [x] Eloquent models `CycleCountOrder` and `CycleCountItem` with status FSM, casts, relationships, and variance helpers.
- [x] Double-entry `recordAdjustment` method in `InventoryMovementService` with pessimistic row locking (`lockForUpdate()`) and zero-sum balance enforcement.
- [x] `CycleCountService` domain engine managing order creation, balance snapshotting, blind count recording, variance delta calculation, recount requests, supervisor approval, and atomic ledger adjustment posting.
- [x] Mobile Handheld Scanner REST API endpoints (`/active`, `/{id}/sheet`, `/{id}/count`) with strict blind count secrecy (omitting book balance and variance).
- [x] Filament v3 `CycleCountOrderResource` and `ItemsRelationManager` with discrepancy indicators (Surplus, Shortage, Match), start snapshot action, recount trigger, and one-click adjustment posting.
- [x] Automated test suite (`Tests\Feature\CycleCountTest`) with 8 tests (57 assertions) passing on MariaDB 11.8 (`WMS_DB_TEST`).
- [x] Full regression test suite passing (22 tests, 120 assertions) with 100% green status.
- [x] Laravel Pint code formatting executed cleanly.

## Change Description

Implemented the full Blind Cycle Counting and Stock Opname subsystem (Epic 6 / US-014) for discrete injection molding inventory auditing:
1. **Database Schema:** Created migration `2026_11_01_000009_create_cycle_count_tables.php` defining `cycle_count_orders` and `cycle_count_items`. Added unique constraint `uq_cc_order_bin_item_lot` and compound status/warehouse indexes.
2. **Double-Entry Ledger Adjustments:** Extended `InventoryMovementService` with `recordAdjustment()`. Positive variances (surplus) record movements with `source_bin = null` and `destination_bin = $bin`, atomically incrementing `inventory_balances.quantity_on_hand`. Negative variances (shortage/shrinkage) record movements with `source_bin = $bin` and `destination_bin = null`, atomically decrementing balances with non-negative underflow guards (`InsufficientStockException`).
3. **Domain Engine:** Implemented `CycleCountService` to freeze inventory snapshots upon count initiation, capture physical counts blindly, compute variances, provide recount reset workflows, enforce supervisor authorization gates before adjustment posting, and wrap ledger commits in database transactions.
4. **Blind Privacy Protection:** Implemented `ScannerCycleCountController` where the count sheet payload strictly strips `recorded_quantity` and `variance_quantity`, completely eliminating counter confirmation bias on mobile terminals.
5. **Back-Office Reconciliation:** Built Filament v3 `CycleCountOrderResource` with custom badges, live discrepancy comparison tables, filterable variance views, and execution actions for snapshots, recounts, approvals, and ledger posting.

## Modified Files

- `database/migrations/2026_11_01_000009_create_cycle_count_tables.php` — add
- `app/Models/CycleCountOrder.php` — add
- `app/Models/CycleCountItem.php` — add
- `app/Services/InventoryMovementService.php` — edit (added `recordAdjustment`)
- `app/Services/CycleCountService.php` — add
- `app/Http/Controllers/Api/ScannerCycleCountController.php` — add
- `routes/api.php` — edit (registered scanner cycle count routes)
- `app/Filament/Resources/CycleCountOrderResource.php` — add
- `app/Filament/Resources/CycleCountOrderResource/Pages/ListCycleCountOrders.php` — add
- `app/Filament/Resources/CycleCountOrderResource/Pages/CreateCycleCountOrder.php` — add
- `app/Filament/Resources/CycleCountOrderResource/Pages/ViewCycleCountOrder.php` — add
- `app/Filament/Resources/CycleCountOrderResource/Pages/EditCycleCountOrder.php` — add
- `app/Filament/Resources/CycleCountOrderResource/RelationManagers/ItemsRelationManager.php` — add
- `tests/Feature/CycleCountTest.php` — add
- `.agent-knowledge/dynamic/workflows/002-blind-cycle-counting.md` — add

## Review

- Result: Blind Cycle Counting and Stock Opname subsystem fully operational, providing tamper-resistant inventory audits and mathematically balanced ledger adjustments.
- Verification: Executed `php artisan test` on MariaDB 11.8 (`WMS_DB_TEST`), passing all 22 tests and 120 assertions. Verified route registration via `php artisan route:list`.
- Follow-up: Ready to proceed with Regrind Scrap Recycling & Blend Cap Engine (Epic 3 / US-008) or Rugged Handheld Scanner Web UI.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Database migration on MariaDB 11.8 creates `cycle_count_orders` and `cycle_count_items` with UUID keys | Pass |
| 2 | Count initiation freezes balance snapshot and transitions order from `DRAFT` to `IN_PROGRESS` | Pass |
| 3 | Mobile scanner count sheet endpoint excludes `recorded_quantity` to prevent counter bias | Pass |
| 4 | Mobile scanner records physical count scans and accurately computes variance deltas | Pass |
| 5 | Exact match count transitions order status to `COUNTED`; discrepancies transition to `REVIEW` | Pass |
| 6 | Item recount request resets status to `RECOUNT_REQUESTED` and returns order to `IN_PROGRESS` | Pass |
| 7 | Unapproved count order cannot post ledger adjustments (Supervisor Gate enforced) | Pass |
| 8 | Adjustment posting commits double-entry `ADJUSTMENT` movements and updates `inventory_balances` | Pass |
| 9 | Negative adjustment blocked with `InsufficientStockException` if concurrent stock dropped | Pass |
| 10 | Filament v3 `CycleCountOrderResource` and `ItemsRelationManager` render with full action controls | Pass |
