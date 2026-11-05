# 015 — FEFO to FIFO lot allocation

Date: 2026-11-05

## Checklist

- [x] Add an auditable FIFO receipt timestamp to material lots.
- [x] Select eligible picking and replenishment lots by FIFO receipt time.
- [x] Preserve expiry and quality eligibility controls and regrind blend-cap behavior.
- [x] Replace FEFO terminology in code, UI, tests, and affected living documentation.

## Change Description

Replaced automatic FEFO lot ordering with FIFO ordering based on `material_lots.received_at`. Existing lots are backfilled from `created_at`; new inbound and regrind lots receive a server timestamp. Allocation and replenishment keep transactions, row locks, quality/expiry filtering, and regrind priority, while ordering candidates by receipt time and a stable balance-ID tie-breaker.

## Modified Files

- `database/migrations/2026_11_05_000017_add_received_at_to_material_lots_table.php` — add, backfill, constrain, and index receipt time.
- `app/Services/FifoAllocationService.php` — renamed FEFO service and FIFO candidate ordering.
- `app/Services/ReplenishmentService.php` — FIFO reserve selection.
- `app/Models/MaterialLot.php`, inbound/regrind services, and Filament resources — persist and display receipt time.
- `tests/Feature/*`, ADR-009, SPEC-009, and affected living docs — FIFO coverage and documentation.

## Review

- Result: Implemented and fully verified against Oracle Database 21c XE container (`oracle-xe`, `XEPDB1`, `WMS_USER`).
- Fix: Resolved Oracle-specific grammar limitation in `ReplenishmentService` where `lockForUpdate()->first()` stripped inner query ordering during `ROWNUM = 1` wrapping, causing `ORA-00904` / arbitrary candidate selection; switched to `lockForUpdate()->get()->first()`.
- Verification: 
  - `php artisan migrate:status`: All 22 migrations ran cleanly.
  - `php artisan migrate:fresh --seed`: Complete drop, migration, and seeding of all 4 seeders executed without error.
  - `php artisan test`: 100% pass rate across entire suite (94 tests, 662 assertions).
  - `./vendor/bin/pint --test`: All files passed code style checks.
- Follow-up: None.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Older eligible receipt is selected before a later lot with earlier expiry. | Pass (`WmsCoreDomainTest::test_fifo_allocation_prioritizes_earliest_receipt_and_skips_qa_hold`) |
| 2 | Expired and QA-hold lots remain excluded from allocation. | Pass (`WmsCoreDomainTest`, `RegrindAndBlendCapTest`) |
| 3 | Replenishment reserves the earliest eligible receipt. | Pass (`ReplenishmentTest::test_evaluate_deficits_reserves_the_earliest_received_eligible_bulk_lot`) |
