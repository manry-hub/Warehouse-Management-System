# Implementation Workflow 015: FEFO to FIFO Lot Allocation

**Status:** COMPLETED  
**Target Outcome:** Automatic picking and replenishment allocate eligible lots by earliest receipt time.  
**Owner:** Core Architecture & Logistics Engineering  
**References:**

- ADR-009 and SPEC-009
- ADR-005, ADR-006, SPEC-005, and SPEC-006

---

## 1. Objective and Scope

### Objective

Replace FEFO ordering with FIFO ordering based on a durable lot receipt timestamp.

### In Scope

- `material_lots.received_at` schema, model, backfill, and index.
- Picking, replenishment, scanner receipt, regrind intake, UI wording, and tests.

### Out of Scope

- Changing the existing quality-state or expiry eligibility rules.
- Changing manual lot assignments already stored on order lines.

### Assumptions and Open Questions

- Regrind retains its existing blend-cap priority; FIFO applies inside each allocation stage.

---

## 2. Staged Execution Plan

### Stage 1: Establish FIFO Data

**Objective:** Make receipt time available and auditable for all lots.

1. Add and backfill `received_at`, then index the FIFO lookup path.
2. Populate it from server-side inbound receipt and regrind intake; expose it in lot administration.

**Exit Criteria:**

- [x] Existing lots retain their historical creation time as receipt time.
- [x] New lots have a server-controlled or required administrative receipt time.

### Stage 2: Switch Allocation Behavior

**Objective:** Use FIFO in every automatic lot-selection path.

1. Rename the allocation service and replace expiry ordering with receipt-time ordering plus stable tie-breaker.
2. Update replenishment selection and all user-visible FEFO terminology.

**Exit Criteria:**

- [x] Picking and replenishment select the earliest eligible receipt.

### Stage 3: Verify and Document

**Objective:** Prove the new order and preserve the decision record.

1. Run targeted feature tests, the full test suite, formatting, and migration checks.
2. Update the task report with actual results.

**Exit Criteria:**

- [x] Automated tests cover FIFO, expiry exclusion, QA exclusion, and regrind priority.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- Database migration support for the configured SQLite test database and Oracle deployment target.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| Incorrect historic FIFO order | Wrong lot is selected | Backfill from immutable `created_at`; review before production migration. |
| Competing reservations | Over-allocation | Preserve transaction and `lockForUpdate()` behavior. |

### Rollback or Recovery

Deploy code and migration together. Roll back the migration only after restoring the FEFO implementation; existing `received_at` values are derived from timestamps and do not alter quantity balances.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| FIFO allocation | Feature test | Earlier receipt wins even when it expires later (Pass). |
| Eligibility | Feature test | Expired and QA-hold lots remain unavailable (Pass). |
| Replenishment | Feature test | Earliest eligible bulk receipt is reserved (Pass). |
| Regression | PHPUnit and Pint | Targeted and full checks pass (Pass, 94/94 tests, 662 assertions). |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Relevant living documentation, ADRs, and specifications are updated or linked.
- [x] The task report records actual changes and verification results.

Next transition: Workflow 015 completed and verified on Oracle 21c XE container.
