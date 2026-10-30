# 014 — MariaDB to OracleDB Migration

Date: 2026-10-30

## Checklist

- [x] Started and verified existing Oracle Database 21c XE Docker container (`oracle-xe`).
- [x] Configured Oracle client libraries, NLS data, and compiled PHP 8.5 `oci8` extension (`/home/ughway/.oracle-client`).
- [x] Configured automatic shell environment variables in `~/.bashrc` (`ORACLE_HOME`, `LD_LIBRARY_PATH`, `PHP_INI_SCAN_DIR`).
- [x] Installed `yajra/laravel-oci8` (v11.6.7) in Laravel 11.
- [x] Published and customized `config/oracle.php` and `config/database.php` for Oracle 21c (`XEPDB1`, `WMS_USER`).
- [x] Created dedicated `WMS_USER` schema with full tablespace privileges in `XEPDB1`.
- [x] Removed unsupported `->after()` clauses and MySQL-only `->restrictOnDelete()` from migrations.
- [x] Patched OracleGrammar to support `->useCurrent()` (`DEFAULT CURRENT_TIMESTAMP`) and safe delete actions.
- [x] Adjusted `ReplenishmentService` query sorting for Oracle subquery aliasing.
- [x] Executed `php artisan migrate:fresh --seed` across all 21 migrations and 4 seeder suites.
- [x] Verified 100% test pass rate across the full test suite (93 tests, 660 assertions).

## Change Description

Migrated the Warehouse Management System database tier from MariaDB to Oracle Database 21c Express Edition container (`oracle-xe`) with zero business logic refactoring. Established PHP 8.5 OCI8 runtime connectivity using extracted Oracle 21c client libraries, provisioned dedicated `WMS_USER` application schema in `XEPDB1`, updated Laravel database connection configs, aligned schema migrations with ANSI Oracle standards, and verified full application and test suite integrity.

## Modified Files

- `config/database.php` — added `oracle` database connection configuration
- `config/oracle.php` — published Oracle configuration
- `.env` — switched default connection to `oracle`, pointing to `127.0.0.1:1521/XEPDB1` with user `WMS_USER`
- `phpunit.xml` — updated testing database connection to `oracle`
- `composer.json` — added `yajra/laravel-oci8`
- `database/migrations/2026_11_01_000010_add_regrind_columns_to_tables.php` — removed `->after()` column clauses
- `database/migrations/2026_11_01_000013_create_genealogy_hopper_and_gate_pass_tables.php` — removed `->after()` column clauses
- `database/migrations/2026_11_01_000015_create_replenishment_tables.php` — removed `->after()` column clauses
- `database/migrations/*.php` — normalized foreign key constraints (removed `->restrictOnDelete()`)
- `app/Services/ReplenishmentService.php` — explicit column aliasing for candidate balance ordering
- `app/Models/StagingRequest.php` — added model boot hook for default `requested_at` timestamp
- `.agent-knowledge/dynamic/workflows/014-mariadb-to-oracledb-migration.md` — workflow execution record

## Review

- Result: Database fully migrated to Oracle Database 21c XE (`XEPDB1` / `WMS_USER`), migrations and seeders execute cleanly.
- Verification: Ran `php artisan test` across all 93 Unit and Feature tests with 660 assertions passing 100%.
- Follow-up: None.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Oracle 21c Listener & Container Connectivity | Pass |
| 2 | PHP 8.5 OCI8 Module Loading & Dual Table Query | Pass |
| 3 | Schema Migration (`migrate:fresh`) 21/21 Tables | Pass |
| 4 | Database Seeders (`db:seed`) Reference & Mock Operations Data | Pass |
| 5 | Domain Entities Record Counts Verification | Pass |
| 6 | Automated Test Suite (WmsCoreDomain, CycleCount, Analytics, Replenishment, Scanner) | Pass (93/93) |
