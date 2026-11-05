# 016 — Redis Queue, Concurrency Load Testing, and SKIP LOCKED Allocation

Date: 2026-11-05

## Checklist

- [x] Configure Redis 7.2 queue backend with `predis/predis` and test asynchronous dispatch.
- [x] Create multi-worker systemd unit templates and local daemon supervisor script.
- [x] Build multi-process picker allocation benchmark harness (`wms:benchmark-allocation`).
- [x] Capture baseline blocking lock latency metrics on live Oracle Database 21c XE.
- [x] Implement non-blocking `SELECT ... FOR UPDATE OF inventory_balances.id SKIP LOCKED` in `FifoAllocationService`.
- [x] Achieve measurable latency reduction (53% faster median latency, +14.6% throughput gain) with 0 deadlocks.
- [x] Build automated invariant auditor script (`scripts/verify-inventory-invariants.php`) asserting 0 defects across 8 core invariants.
- [x] Expand feature test suite and pass 100% of tests on Oracle 21c XE.

## Change Description

Migrated background queue processing from synchronous to Redis via `predis/predis` on `127.0.0.1:6379`. Authored production systemd service templates (`deploy/systemd/wms-worker@.service`, `wms-scheduler.service`, `wms-scheduler.timer`) and a development process supervisor (`scripts/wms-daemon.sh`). Built an automated benchmark command (`wms:benchmark-allocation`) to instrument concurrent transaction latencies ($p_{50}, p_{90}, p_{99}$) on Oracle Database 21c XE. Refactored `FifoAllocationService` with a hybrid non-blocking `FOR UPDATE OF inventory_balances.id SKIP LOCKED` fast-path and contention fallback, eliminating picker thread convoying while preserving regrind blend caps and strict FIFO receipt ordering. Established an automated pre-go-live invariant auditor script (`scripts/verify-inventory-invariants.php`) validating zero double-allocation, zero negative stock, and double-entry mass conservation.

## Modified Files

- `composer.json`, `composer.lock` — added `predis/predis` v3.7.1.
- `.env`, `.env.example` — configured Redis queue connection and client.
- `deploy/systemd/wms-worker@.service` — production multi-instance queue worker unit.
- `deploy/systemd/wms-scheduler.service`, `deploy/systemd/wms-scheduler.timer` — production scheduler timer.
- `scripts/wms-daemon.sh` — local process supervisor for queue workers and scheduler.
- `app/Console/Commands/BenchmarkAllocationCommand.php` — multi-process concurrency benchmark harness.
- `app/Services/FifoAllocationService.php` — refactored with hybrid `SKIP LOCKED` fast path and contention fallback.
- `scripts/verify-inventory-invariants.php` — automated pre-go-live inventory invariant auditor.
- `tests/Feature/ConcurrencyAndInvariantsTest.php` — expanded with `SKIP LOCKED` and balance restoration assertions.
- `.agent-knowledge/dynamic/workflows/016-redis-queue-load-testing-and-skip-locked-allocation.md` — workflow execution record.
- `.agent-knowledge/dynamic/docs/notes/ADR-010-redis-queue-and-skip-locked-concurrency.md` — architectural decision record.
- `.agent-knowledge/dynamic/docs/notes/SPEC-010-concurrent-allocation-and-queue-contract.md` — concurrency and queue specification.

## Review

- Result: All staged milestones successfully delivered and verified against live Oracle Database 21c XE and Redis 7.2.
- Verification:
  - `php artisan tinker --execute="Redis::ping()"`: Returned `PONG`.
  - `php artisan queue:work redis --once`: Dequeued and processed `ProcessOutboxJob` in 14.24ms.
  - `php artisan wms:benchmark-allocation --workers=10 --requests=5 --release`: 100% success rate, 0 deadlocks (`ORA-00060`), 0 timeouts (`ORA-00054`), 74.29 tx/sec.
  - `./scripts/verify-inventory-invariants.php`: 8 of 8 invariants passed with 0 defects detected.
  - `php artisan test`: 96 tests (676 assertions) passed with 100% pass rate.
  - `./vendor/bin/pint --test`: All files passed code style checks.
- Follow-up: None.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Redis queue dispatches and consumes jobs asynchronously without database locking. | Pass (`ProcessOutboxJob: 14.24ms DONE`) |
| 2 | High-concurrency benchmark measures transaction latencies without `ORA-00060` deadlocks. | Pass (`wms:benchmark-allocation`: 0 deadlocks across 50 and 100 parallel requests) |
| 3 | `SKIP LOCKED` reduces median latency and eliminates thread convoying. | Pass ($p_{50}$ reduced from 68.5ms to 37.5ms at 10 workers; 179.6ms to 83.1ms at 25 workers) |
| 4 | FIFO receipt ordering, regrind priority, and blend cap strictness are preserved. | Pass (`ConcurrencyAndInvariantsTest`, `RegrindAndBlendCapTest`) |
| 5 | Pre-go-live auditor mathematically confirms zero negative stock, zero over-reservation, and mass conservation. | Pass (`scripts/verify-inventory-invariants.php`: 8/8 passed) |
