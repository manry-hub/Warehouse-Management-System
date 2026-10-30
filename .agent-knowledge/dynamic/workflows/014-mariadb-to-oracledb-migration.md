# Implementation Workflow 014: MariaDB to OracleDB Migration

**Status:** `COMPLETE`  
**Target Outcome:** `Migrate database connection, schema, and seed data from MariaDB to Oracle Database 21c Express Edition container (oracle-xe) without business logic refactoring.`  
**Owner:** `Software Engineer`  
**References:**

- `mariadb_to_oracledb_migration_plan.md`
- `config/database.php`
- `.env`

---

## 1. Objective and Scope

### Objective

Transition the Warehouse Management System from MariaDB to Oracle Database 21c XE running in Docker (`oracle-xe`), configure Laravel's OCI8 database driver, remove incompatible migration clauses, and execute migrations with complete domain seeders.

### In Scope

- Starting the existing Docker container `oracle-xe` and verifying port 1521 listener.
- Setting up Oracle Instant Client & PHP OCI8 extension on host PHP 8.5.
- Installing and configuring `yajra/laravel-oci8` in Laravel.
- Updating `.env` to connect directly to Oracle as user `SYSTEM` with SID `XE`.
- Removing unsupported `->after()` column modifier clauses from migrations.
- Executing `php artisan migrate:fresh --seed` against Oracle DB.
- Verifying domain models, test suite, and Filament admin panel.

### Out of Scope

- Refactoring business logic, controllers, or services (confirmed 100% ANSI SQL compatible).
- Altering UUID primary keys to database sequences (models use client-side `HasUuids`).

### Assumptions and Open Questions

- User provided connection string: `url="jdbc:oracle:thin:@127.0.0.1:1521:XE" user=SYSTEM password=Sorry.240106`.
- Docker container `oracle-xe` (`container-registry.oracle.com/database/express:21.3.0-xe`) exists and will be started.
- All initial reference and mock transactional data will be initialized via `php artisan db:seed`.

---

## 2. Staged Execution Plan

### Stage 1: Oracle Container and PHP OCI8 Driver Setup

**Objective:** Ensure Oracle 21c is running and PHP 8.5 can connect via OCI8.

1. Start `oracle-xe` Docker container (`docker start oracle-xe`) and verify status.
2. Install Oracle Instant Client and compile/enable PHP 8.5 `oci8` extension.
3. Verify `php -m | grep oci8`.

**Exit Criteria:**

- [x] Oracle container is healthy and listening on 1521.
- [x] `php -m` lists `oci8`.

### Stage 2: Driver Installation & Laravel Configuration

**Objective:** Install Yajra OCI8 package and configure Laravel database connections.

1. Add `yajra/laravel-oci8` to `composer.json` and install.
2. Add `oracle` connection configuration block in `config/database.php`.
3. Configure `.env` with Oracle connection credentials.

**Exit Criteria:**

- [x] `yajra/laravel-oci8` installed in `vendor/`.
- [x] Database config recognizes `oracle` connection.

### Stage 3: Migration Fixes & Schema/Seed Execution

**Objective:** Clean migration syntax and build Oracle schema + seed data.

1. Remove `->after()` from `2026_11_01_000010_add_regrind_columns_to_tables.php`.
2. Remove `->after()` from `2026_11_01_000013_create_genealogy_hopper_and_gate_pass_tables.php`.
3. Remove `->after()` from `2026_11_01_000015_create_replenishment_tables.php`.
4. Run `php artisan migrate:fresh --seed --force`.

**Exit Criteria:**

- [x] All 21 migrations execute successfully without errors.
- [x] Seeders (`RoleAndPermissionSeeder`, `WarehouseTopologySeeder`, `ItemCatalogSeeder`, `RealisticOperationsSeeder`) populate tables.

### Stage 4: Verification and Quality Assurance

**Objective:** Verify application functionality on Oracle.

1. Run test suite against Oracle database.
2. Verify Filament dashboard and domain operations.

**Exit Criteria:**

- [x] Feature tests pass on Oracle connection.
- [x] Task report completed.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- Docker daemon running on host.
- Oracle Instant Client packages.
- Composer.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| PHP 8.5 OCI8 extension build complexity | Blocks connection | Use PECL or instant client SDK headers matching PHP 8.5 |
| Identifier length limit in Oracle | Syntax error | Oracle 21c natively supports 128 bytes; set `ORA_MAX_NAME_LEN=128` |

### Rollback or Recovery

If rollback is needed, revert `.env` to `DB_CONNECTION=mariadb` and restore previous migration files.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| Oracle Container | `docker ps` | Status Up, port 1521 mapped |
| PHP OCI8 | `php -m` | `oci8` in module list |
| DB Connectivity | `artisan tinker` | PDO/OCI connection returns valid connection |
| Schema Migration | `artisan migrate:status` | All migrations marked Ran |
| Data Seeding | `artisan tinker` | Record counts match expected seeded volume |
| Automated Tests | `artisan test` | Domain feature tests pass |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] The task report records actual changes and verification results.
