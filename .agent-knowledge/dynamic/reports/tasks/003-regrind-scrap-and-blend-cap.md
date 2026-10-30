# 003 — Regrind Scrap Recycling & Blend Cap Engine

Date: 2026-10-30

## Checklist

- [x] Schema migration adding regrind attributes to `items`, `material_lots`, and `staging_request_items`.
- [x] Model enhancements on `Item` (`allow_regrind`, `max_regrind_percentage`), `MaterialLot` (`lot_type`, `parent_lot_id`, `regrind_generation`, `regrind_source_machine`), and `StagingRequestItem`.
- [x] `RegrindService` handling runner scrap intake, generation 1 thermal limits, barcode generation (`REG-`), and double-entry receipt ledger transactions.
- [x] Enhanced `FefoAllocationService` with mathematical dual-lot blend cap enforcement (strictly capping regrind proportion to configured item percentage e.g. 15.0%, and falling back to virgin resin).
- [x] Handheld Scanner REST API endpoints (`POST /regrind/intake`, `GET /regrind/stock`) with ZPL and QR label vectors.
- [x] Filament v3 interface updates in `ItemResource` and `MaterialLotResource` with custom badges and configuration inputs.
- [x] Automated test suite (`Tests\Feature\RegrindAndBlendCapTest`) passing with 8 tests and 40 assertions on MariaDB 11.8 (`WMS_DB_TEST`).
- [x] Full regression test suite passing: 30 tests (161 assertions) with 100% green status.
- [x] Laravel Pint code formatting executed cleanly.

## Change Description

Implemented the Regrind Scrap Recycling & Blend Cap Engine (Epic 3 / US-008) for discrete plastic injection molding:
1. **Database Schema:** Created migration `2026_11_01_000010_add_regrind_columns_to_tables.php` adding regrind governance columns to `items`, `material_lots`, and `staging_request_items`.
2. **Domain Service (`RegrindService`):**
   - Implemented `intakeRegrind` for granulator stations.
   - Enforces thermal cycling limit: attempts to regrind an already reground lot (`parentLot->isRegrind()`) are rejected with `DomainException("Multi-heat thermal cycling degradation prohibited")`.
   - Records double-entry receipt movements via `InventoryMovementService::recordReceipt`.
3. **Blend Cap Allocation Engine (`FefoAllocationService`):**
   - Enforces blend cap ratio during staging allocation: allocates up to $\min(\text{available}, \text{required} \times \frac{\text{cap}}{100})$ of `REGRIND` material in FEFO order.
   - Strictly allocates remaining requirement from `VIRGIN` lots in FEFO order.
   - Fully excludes regrind lots if item has `allow_regrind = false`.
   - Safely falls back to 100% virgin polymer if zero regrind stock is available.
4. **Scanner REST API (`ScannerRegrindController`):**
   - Added `POST /api/v1/scanner/regrind/intake` generating GS1 QR Code and ZPL II thermal label code.
   - Added `GET /api/v1/scanner/regrind/stock` displaying available unreserved regrind inventory by polymer SKU.
5. **Back-Office UI:**
   - Filament `ItemResource` configured with toggle and blend cap percentage slider.
   - Filament `MaterialLotResource` enriched with `lot_type` badges, machine origin, and lot type filters.

## Modified Files

- `database/migrations/2026_11_01_000010_add_regrind_columns_to_tables.php` — add
- `app/Models/Item.php` — edit (added regrind fields, casts, helpers)
- `app/Models/MaterialLot.php` — edit (added regrind fields, constants, relations)
- `app/Models/StagingRequestItem.php` — edit (added regrind fields)
- `app/Services/RegrindService.php` — add
- `app/Services/FefoAllocationService.php` — edit (added blend cap dual-allocation algorithm)
- `app/Http/Controllers/Api/ScannerRegrindController.php` — add
- `routes/api.php` — edit (registered regrind routes)
- `routes/web.php` — edit (redirected root to admin)
- `app/Filament/Resources/ItemResource.php` — edit (added regrind settings and table columns)
- `app/Filament/Resources/MaterialLotResource.php` — edit (added lot_type badges and filters)
- `database/seeders/ItemCatalogSeeder.php` — edit (configured PP resin with 15% regrind cap)
- `tests/Feature/RegrindAndBlendCapTest.php` — add
- `tests/Feature/ExampleTest.php` — edit (updated assertRedirect)
- `.agent-knowledge/dynamic/workflows/003-regrind-scrap-and-blend-cap.md` — add

## Review

- Result: Regrind recycling and blend cap engine fully implemented and mathematically verified. Prevents brittle molded parts by strictly capping regrind proportion and blocking multi-heat degradation.
- Verification: Executed `php artisan test` on MariaDB 11.8 (`WMS_DB_TEST`), passing all 30 tests and 161 assertions. Verified routes via `php artisan route:list`.
- Follow-up: Ready to proceed to Dedicated Rugged Handheld Scanner Web UI or Visual Bin Topology.

## UAT (User Acceptance Test) Verification

| # | Test | Result |
| --- | --- | --- |
| 1 | Database migration adds regrind attributes to items, material lots, and staging items | Pass |
| 2 | Regrind intake records lot with `lot_type = REGRIND` and creates double-entry receipt movement | Pass |
| 3 | Intake rejected if Item SKU has `allow_regrind = false` | Pass |
| 4 | Generation 2 regrind blocked with DomainException ("Multi-heat thermal cycling degradation prohibited") | Pass |
| 5 | FEFO staging allocation caps regrind at exact configured percentage (15.0%) and allocates remainder from virgin | Pass |
| 6 | Staging allocation gracefully falls back to 100% virgin polymer when no regrind is available | Pass |
| 7 | Items with regrind disabled strictly exclude regrind lots even if regrind expires sooner | Pass |
| 8 | Handheld scanner API `POST /regrind/intake` returns 201 with QR SVG and ZPL label payload | Pass |
| 9 | Handheld scanner API `GET /regrind/stock` lists real-time unreserved regrind stock | Pass |
| 10 | Filament Item and Material Lot resources render regrind badges, settings, and filters | Pass |
