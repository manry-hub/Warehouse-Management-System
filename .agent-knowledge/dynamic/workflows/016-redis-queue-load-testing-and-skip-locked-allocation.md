# Implementation Workflow 016: Redis Queue, Concurrency Load Testing, and SKIP LOCKED Allocation

**Status:** `COMPLETE`  
**Target Outcome:** `Deploy Redis 7.2 queue backend, configure systemd unit templates, benchmark picker transaction latencies under concurrency, implement Oracle SKIP LOCKED FIFO allocation, and verify zero double-allocation/negative inventory invariants.`  
**Owner:** `Logistics Systems & Transaction Engineering Team`  
**References:**

- `.agent-knowledge/dynamic/docs/dev/PRD.md`
- `.agent-knowledge/dynamic/docs/notes/ADR-009-fifo-lot-allocation-by-receipt-time.md`
- `.agent-knowledge/dynamic/docs/notes/SPEC-009-fifo-lot-allocation-contract.md`
- `concurrency_redis_queue_and_fifo_plan.md`

---

## 1. Objective and Scope

### Objective

Transition the application from synchronous job processing to an asynchronous Redis-backed queue with production systemd supervision, benchmark transactional latency and lock contention across concurrent mobile pickers on Oracle Database 21c XE, refactor lot allocation to non-blocking `SELECT ... FOR UPDATE OF inventory_balances.id SKIP LOCKED` while strictly preserving regrind priority and FIFO receipt ordering, and establish automated invariant verification guaranteeing zero inventory drift.

### In Scope

- Spin up a dedicated Redis 7.2 Docker container on `127.0.0.1:6379` using the cached `redis:7.2-alpine` image.
- Install `predis/predis` and configure Laravel `.env` and `config/queue.php` for Redis queue drivers.
- Author production systemd unit files (`deploy/systemd/wms-worker@.service`, `wms-scheduler.service`, `wms-scheduler.timer`) and provide a development supervisor (`scripts/wms-daemon.sh`).
- Build an automated load testing benchmark harness (`app/Console/Commands/BenchmarkAllocationCommand.php`) measuring transaction latencies ($p_{50}, p_{90}, p_{99}$), throughput (ops/sec), and Oracle lock contention.
- Refactor `FifoAllocationService` to utilize `FOR UPDATE OF inventory_balances.id SKIP LOCKED` with fallback protection, strictly preserving regrind blend caps and receipt-time ordering.
- Build an automated invariant verification script (`scripts/verify-inventory-invariants.php`) and comprehensive feature test suite asserting conservation of mass, zero negative stock, and zero double-allocation under 50 concurrent transactions.

### Out of Scope

- Cloud-hosted managed Redis clusters (AWS ElastiCache, Redis Enterprise).
- Structural alterations to business models (`MaterialLot`, `InventoryBalance`, `Item`).
- Front-end Filament UI aesthetic changes.

### Assumptions and Open Questions

- Oracle Database 21c XE container (`oracle-xe`) remains healthy and accessible at `127.0.0.1:1521`.
- The host system runs Docker with cached `redis:7.2-alpine`.
- Non-blocking `SKIP LOCKED` behavior permits workers to allocate the earliest available *unlocked* lot when competing workers are actively locking earlier lots.

---

## 2. Staged Execution Plan

### Stage 1: Redis Queue Infrastructure & Daemon Management

**Objective:** Enable high-throughput asynchronous job execution backed by Redis with systemd unit definitions.

1. Start `wms-redis` container listening on `127.0.0.1:6379`.
2. Install `predis/predis` via Composer.
3. Update `.env` and `config/queue.php` with Redis queue and client settings.
4. Create production systemd unit files in `deploy/systemd/` for multi-worker and scheduler daemons.
5. Author and verify local supervisor script `scripts/wms-daemon.sh` with process tracking.
6. Verify job dispatch and consumption via `ProcessOutboxJob`.

**Exit Criteria:**

- [x] Redis container is running and responds to `PING`.
- [x] Jobs dispatched to Redis queue are processed cleanly by `php artisan queue:work redis --once`.
- [x] Systemd unit files and `scripts/wms-daemon.sh` pass validation.

### Stage 2: High-Concurrency Allocation Load Testing Harness

**Objective:** Implement an automated benchmark command to establish baseline latency and lock wait metrics under concurrent picker transactions.

1. Create `app/Console/Commands/BenchmarkAllocationCommand.php` (`wms:benchmark-allocation`).
2. Instrument execution time measuring $p_{50}$, $p_{90}$, $p_{99}$, and $\max$ transaction latency across parallel processes.
3. Record Oracle wait metrics and lock contention errors (`ORA-00060`, `ORA-00054`).
4. Execute baseline benchmark with existing pessimistic blocking locks (`lockForUpdate`).

**Exit Criteria:**

- [x] `php artisan wms:benchmark-allocation` executes cleanly across 10, 20, and 50 parallel workers.
- [x] Baseline performance report is captured with latency percentiles.

### Stage 3: `SKIP LOCKED` Implementation & Benchmark Comparison

**Objective:** Eliminate picker thread convoying by integrating Oracle 21c `SKIP LOCKED` while preserving FIFO and regrind constraints.

1. Refactor `app/Services/FifoAllocationService.php` to use `FOR UPDATE OF inventory_balances.id SKIP LOCKED`.
2. Ensure Regrind Step 1 and Virgin Step 2 preserve regrind blend caps and receipt-time ordering.
3. Re-run benchmark harness with `SKIP LOCKED` and compare against baseline blocking locks.
4. Record performance gains and trade-off analysis in `ADR-010`.

**Exit Criteria:**

- [x] All unit and feature tests pass with 100% success rate on Oracle 21c.
- [x] Benchmark demonstrates significant reduction in $p_{99}$ latency and zero `ORA-00060` deadlocks.

### Stage 4: Invariant Verification, Edge-Case Hardening & Go-Live Readiness

**Objective:** Mathematically prove zero double-allocation, zero negative inventory, and complete mass conservation.

1. Implement `scripts/verify-inventory-invariants.php` auditing physical vs. reserved balances.
2. Expand `tests/Feature/ConcurrencyAndInvariantsTest.php` with high-concurrency race condition tests.
3. Verify zero orphan reservations or balance discrepancies under random cancellations and rollbacks.
4. Record SPEC-010, update living documentation, and complete task report.

**Exit Criteria:**

- [x] `php scripts/verify-inventory-invariants.php` outputs 0 defects.
- [x] Concurrency test suite passes 100% of assertions.
- [x] `./vendor/bin/pint --test` passes code style checks.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- Docker engine on host with access to port 6379.
- Composer package repository access for `predis/predis`.
- Oracle Database 21c XE container running.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Worker thread stalls on common resin SKU | High latency for mobile pickers | Implement `FOR UPDATE OF inventory_balances.id SKIP LOCKED` to allow picking from next eligible unlocked lot. |
| Host PHP 8.5 lacks ext-redis | Deployment failure | Utilize pure PHP `predis/predis` package fully supported by Laravel. |
| Invariant breach under concurrent allocation | Stock inaccuracies, negative quantities | Wrap allocations in atomic transactions with zero-sum ledger postings and post-check guards. |

### Rollback or Recovery

If Redis container is offline, queue driver can immediately revert to `QUEUE_CONNECTION=database` or `sync` via `.env`. If `SKIP LOCKED` causes unwanted lot skipping in low-volume bins, a configuration toggle (`WMS_ALLOCATION_SKIP_LOCKED=false`) allows immediate fallback to standard `FOR UPDATE`.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| Redis Connection | `redis-cli ping` / `php artisan tinker` | Returns `PONG` / connects cleanly. |
| Redis Queue Processing | `php artisan queue:work redis --once` | Dispatched jobs are dequeued and completed without errors. |
| Concurrency Latency | `wms:benchmark-allocation --workers=20` | Measure $p_{50}, p_{90}, p_{99}$; 0 deadlocks (`ORA-00060`). |
| Invariant Integrity | `php scripts/verify-inventory-invariants.php` | 0 negative quantities, 0 over-allocations, mass conservation holds. |
| Test Suite | `php artisan test` | 100% pass rate across entire suite. |
| Code Formatting | `./vendor/bin/pint --test` | 0 formatting violations. |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Relevant living documentation, ADRs, and specifications are updated or linked.
- [x] The task report records actual changes and verification results.

Next transition: None. All requirements delivered and verified.
