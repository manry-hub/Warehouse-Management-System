# Database Plan — Warehouse Management System (WMS)

> **Status:** `PLANNING` — New Project Mode  
> **Output:** `.future/docs/dev/sdd/DATABASE.md`  
> **Purpose:** Database blueprint: ERD, schema definitions, relationships, indexing strategies, and migration roadmap for the standalone Laravel WMS.

---

## 1. Database Topology

| Connection | DB Engine | Database Name | Purpose |
|------------|-----------|---------------|---------|
| `mariadb` (default) | MariaDB 11.4 LTS (InnoDB) | `wms_db` | Primary transactional store for catalog, bin topology, lot records, balance ledgers, and shipping documents. |
| `redis` | Redis 7.2 | DB 0 / DB 1 | Caching bin coordinates, session state, rate limiters, and Laravel Horizon queues. |

---

## 2. Table Schemas

### `warehouses`
Stores primary physical warehouse buildings.

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique UUIDv7 identifier |
| `code` | `varchar(30)` | NOT NULL, UNIQUE | UNIQUE | e.g. `WH-MAIN`, `WH-OVERFLOW` |
| `name` | `varchar(150)` | NOT NULL | | Human-readable facility name |
| `address` | `text` | nullable | | Physical address |
| `is_active` | `boolean` | DEFAULT true | INDEX | Active status flag |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `warehouse_zones`
Functional zones within a warehouse (Raw Materials, Finished Goods, Staging, Quarantine).

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique UUIDv7 identifier |
| `warehouse_id` | `uuid` | FK -> `warehouses.id`, ON DELETE CASCADE | INDEX | Parent warehouse |
| `code` | `varchar(30)` | NOT NULL | INDEX | e.g. `ZONE-RM`, `ZONE-FG`, `ZONE-STG`, `ZONE-QC` |
| `name` | `varchar(100)` | NOT NULL | | e.g. "Raw Resin Storage" |
| `zone_type` | `varchar(40)` | NOT NULL | INDEX | Enum: `RAW_MATERIAL`, `FINISHED_GOODS`, `STAGING_BUFFER`, `QUARANTINE` |
| `is_temperature_controlled` | `boolean` | DEFAULT false | | For hygroscopic materials |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `warehouse_bins`
Granular physical storage coordinates (Aisle - Rack - Shelf - Bin).

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique UUIDv7 identifier |
| `zone_id` | `uuid` | FK -> `warehouse_zones.id`, ON DELETE CASCADE | INDEX | Parent zone |
| `bin_code` | `varchar(50)` | NOT NULL, UNIQUE | UNIQUE | Scannable Barcode, e.g. `RM-A-01-03` |
| `aisle` | `varchar(10)` | NOT NULL | INDEX | Aisle coordinate |
| `rack` | `varchar(10)` | NOT NULL | | Rack coordinate |
| `shelf` | `varchar(10)` | NOT NULL | | Shelf tier coordinate |
| `bin` | `varchar(10)` | NOT NULL | | Position coordinate |
| `max_weight_kg` | `decimal(10,2)` | nullable | | Maximum load capacity in kg |
| `max_volume_cbm`| `decimal(10,2)` | nullable | | Maximum volume capacity in cubic meters |
| `is_blocked` | `boolean` | DEFAULT false | INDEX | Blocked for maintenance/repair |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `items`
Catalog of all trackable articles (Raw resin, masterbatch, packaging cartons, finished molded goods).

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique UUIDv7 identifier |
| `sku` | `varchar(100)` | NOT NULL, UNIQUE | UNIQUE | Stock Keeping Unit, matches MES part numbers |
| `name` | `varchar(200)` | NOT NULL | INDEX | Item name |
| `category` | `varchar(50)` | NOT NULL | INDEX | Enum: `RAW_RESIN`, `MASTERBATCH`, `PACKAGING`, `FINISHED_GOOD` |
| `unit_of_measure` | `varchar(20)` | NOT NULL | | e.g. `KG`, `PCS`, `BAG_25KG`, `BOX` |
| `standard_pack_qty` | `decimal(10,2)` | DEFAULT 1.00 | | Default quantity per packing unit |
| `minimum_safety_stock` | `decimal(10,2)` | DEFAULT 0.00 | | Reorder threshold level |
| `shelf_life_days` | `integer` | nullable | | Shelf life in days for FEFO calculation |
| `is_active` | `boolean` | DEFAULT true | INDEX | Active catalog item flag |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `material_lots`
Batch/lot-level tracking for raw materials and finished goods with genealogy properties.

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique UUIDv7 identifier |
| `lot_number` | `varchar(100)` | NOT NULL, UNIQUE | UNIQUE | Unique internal lot barcode |
| `item_id` | `uuid` | FK -> `items.id`, ON DELETE RESTRICT | INDEX | Master item reference |
| `supplier_id` | `uuid` | nullable | INDEX | Reference to vendor |
| `vendor_lot_number` | `varchar(100)` | nullable | INDEX | Supplier's manufacturer batch # |
| `vendor_heat_number` | `varchar(100)` | nullable | INDEX | Smelt/heat number from resin plant |
| `manufactured_date` | `date` | nullable | | Date synthesized/molded |
| `expiry_date` | `date` | nullable | INDEX | Critical for FEFO picking order |
| `coa_file_path` | `varchar(255)` | nullable | | Encrypted/private path to COA PDF |
| `status` | `varchar(30)` | DEFAULT 'QA_HOLD' | INDEX | Enum: `QA_HOLD`, `AVAILABLE`, `EXPIRED`, `QUARANTINE` |
| `qc_inspected_at` | `timestamp` | nullable | | Timestamp of QC clearance |
| `qc_inspected_by` | `uuid` | nullable | | User ID of inspecting QA inspector |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `inventory_balances`
Real-time aggregated stock balance per item, lot, and specific bin coordinate.

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique UUIDv7 identifier |
| `bin_id` | `uuid` | FK -> `warehouse_bins.id`, ON DELETE RESTRICT | INDEX | Current physical bin |
| `item_id` | `uuid` | FK -> `items.id`, ON DELETE RESTRICT | INDEX | Master item SKU |
| `material_lot_id` | `uuid` | FK -> `material_lots.id`, ON DELETE RESTRICT | INDEX | Specific material lot |
| `quantity_on_hand` | `decimal(12,3)` | NOT NULL, DEFAULT 0 | | Physical quantity in bin |
| `quantity_reserved`| `decimal(12,3)` | NOT NULL, DEFAULT 0 | | Allocated for picking / staging |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `inventory_movements` (Immutable Financial-Grade Ledger)
Append-only log of every single stock movement, receipt, pick, or adjustment.

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `bigint unsigned` | PK, auto-increment | PRIMARY | High-throughput sequential audit ID |
| `transaction_uuid` | `uuid` | NOT NULL | INDEX | Groups multi-line atomic movements |
| `movement_type` | `varchar(50)` | NOT NULL | INDEX | Enum: `RECEIPT`, `PUTAWAY`, `STAGING_ISSUE`, `FG_INTAKE`, `SHIPMENT`, `ADJUSTMENT` |
| `item_id` | `uuid` | FK -> `items.id` | INDEX | Transacted item |
| `material_lot_id` | `uuid` | FK -> `material_lots.id` | INDEX | Transacted lot |
| `source_bin_id` | `uuid` | nullable, FK -> `warehouse_bins.id` | INDEX | Null on external receipt |
| `destination_bin_id` | `uuid` | nullable, FK -> `warehouse_bins.id` | INDEX | Null on external shipment |
| `quantity` | `decimal(12,3)` | NOT NULL | | Transacted quantity (always positive) |
| `reference_type` | `varchar(100)` | nullable | | e.g. `App\Models\DeliveryOrder`, `MES_WORK_ORDER` |
| `reference_id` | `varchar(100)` | nullable | INDEX | Associated document/order ID |
| `user_id` | `uuid` | nullable | INDEX | Executing handler/user |
| `notes` | `text` | nullable | | Audit comments or scrap reasons |
| `created_at` | `timestamp` | NOT NULL | INDEX | Transaction timestamp |

### `staging_requests` & `staging_request_items`
Material replenishment orders issued to shop floor hoppers for MES production orders.

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Staging order UUID |
| `request_number` | `varchar(50)` | NOT NULL, UNIQUE | UNIQUE | e.g. `STG-202610-001` |
| `mes_production_order_id`| `varchar(100)` | NOT NULL | INDEX | Reference to MES order number |
| `target_machine_code` | `varchar(50)` | NOT NULL | INDEX | Target injection machine e.g. `IMM-01` |
| `status` | `varchar(30)` | DEFAULT 'PENDING' | INDEX | Enum: `PENDING`, `PICKED`, `DISPATCHED_TO_MES`, `COMPLETED` |
| `requested_at` | `timestamp` | NOT NULL | | Timestamp initiated |
| `dispatched_at` | `timestamp` | nullable | | Handover scan timestamp |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `delivery_orders` & `delivery_order_items`
Official outgoing shipments dispatched to customers.

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Delivery Order UUID |
| `do_number` | `varchar(50)` | NOT NULL, UNIQUE | UNIQUE | Official Delivery Order Number |
| `customer_name` | `varchar(200)` | NOT NULL | INDEX | Recipient corporate client |
| `shipping_address`| `text` | NOT NULL | | Destination warehouse/plant |
| `vehicle_plate_number` | `varchar(30)` | NOT NULL | | Truck license plate |
| `driver_name` | `varchar(150)` | NOT NULL | | Driver legal name |
| `status` | `varchar(30)` | DEFAULT 'DRAFT' | INDEX | Enum: `DRAFT`, `PICKING`, `LOADED`, `DISPATCHED`, `CANCELLED` |
| `total_cartons` | `integer` | DEFAULT 0 | | Aggregate carton count |
| `dispatched_at` | `timestamp` | nullable | INDEX | Departure timestamp |
| `created_at` | `timestamp` | NOT NULL | | Standard audit timestamp |
| `updated_at` | `timestamp` | NOT NULL | | Standard audit timestamp |

### `cycle_count_orders` & `cycle_count_items`
Periodic stock opname and blind cycle counting subsystem (Epic 6 / US-014).

| Column | Type | Constraints | Index | Notes |
|--------|------|-------------|-------|-------|
| `id` | `uuid` | PK | PRIMARY | Unique Order UUID |
| `count_number` | `varchar(50)` | NOT NULL, UNIQUE | UNIQUE | e.g. `CC-202610-0001` |
| `warehouse_id` | `uuid` | FK -> `warehouses.id`, ON DELETE CASCADE | INDEX | Target facility |
| `zone_id` | `uuid` | FK -> `warehouse_zones.id`, nullable | INDEX | Specific target zone or null for facility-wide |
| `count_type` | `varchar(30)` | DEFAULT 'BLIND' | | Enum: `BLIND`, `ADHOC`, `SCHEDULED` |
| `status` | `varchar(30)` | DEFAULT 'DRAFT' | INDEX | Enum: `DRAFT`, `IN_PROGRESS`, `COUNTED`, `REVIEW`, `APPROVED`, `POSTED`, `CANCELLED` |
| `assigned_to` | `foreignId` | FK -> `users.id`, nullable | | Assigned counter |
| `approved_by` | `foreignId` | FK -> `users.id`, nullable | | Approving supervisor |
| `snapshot_at` | `timestamp` | nullable | | Timestamp balance snapshot was frozen |
| `completed_at` | `timestamp` | nullable | | Timestamp physical count completed |
| `posted_at` | `timestamp` | nullable | | Timestamp ledger adjustments committed |
| `notes` | `text` | nullable | | Audit instructions/notes |

`cycle_count_items`:
- `id` (UUID PK), `cycle_count_order_id` (FK), `bin_id` (FK), `item_id` (FK), `material_lot_id` (FK)
- `recorded_quantity` (Snapshot balance, concealed during blind counts)
- `counted_quantity` (Observed physical count from mobile scanner)
- `variance_quantity` (Computed delta: $counted - recorded$)
- `status` (`PENDING`, `COUNTED`, `RECOUNT_REQUESTED`, `APPROVED`)
- Compound unique index: `[cycle_count_order_id, bin_id, item_id, material_lot_id]`

---

## 3. Relationships Summary

| Table A | Relation | Table B | Foreign Key |
|---------|----------|---------|-------------|
| `warehouses` | `hasMany` | `warehouse_zones` | `warehouse_id` |
| `warehouse_zones` | `hasMany` | `warehouse_bins` | `zone_id` |
| `items` | `hasMany` | `material_lots` | `item_id` |
| `items` | `hasMany` | `inventory_balances` | `item_id` |
| `warehouse_bins` | `hasMany` | `inventory_balances` | `bin_id` |
| `material_lots` | `hasMany` | `inventory_balances` | `material_lot_id` |
| `items` | `hasMany` | `inventory_movements` | `item_id` |
| `material_lots` | `hasMany` | `inventory_movements` | `material_lot_id` |
| `staging_requests` | `hasMany` | `staging_request_items` | `staging_request_id` |
| `delivery_orders` | `hasMany` | `delivery_order_items` | `delivery_order_id` |
| `cycle_count_orders` | `hasMany` | `cycle_count_items` | `cycle_count_order_id` |
| `warehouse_bins` | `hasMany` | `cycle_count_items` | `bin_id` |
| `items` | `hasMany` | `cycle_count_items` | `item_id` |
| `material_lots` | `hasMany` | `cycle_count_items` | `material_lot_id` |

---

## 4. Indexing Strategy

| Table | Index | Columns | Type | Reason |
|-------|-------|---------|------|--------|
| `inventory_balances` | `idx_bal_bin_item_lot` | `bin_id, item_id, material_lot_id` | UNIQUE | Prevents duplicate balance rows for the same SKU & Lot inside a single Bin |
| `material_lots` | `idx_lots_item_status_exp` | `item_id, status, expiry_date` | BTREE | Core index for high-speed FEFO/FIFO picking queries |
| `inventory_movements` | `idx_mov_ref_created` | `reference_id, created_at` | BTREE | Speeds up traceability audits and document movement histories |
| `warehouse_bins` | `idx_bin_code_unique` | `bin_code` | UNIQUE | Instant sub-second lookup upon handheld barcode scan |
| `staging_requests` | `idx_stg_mes_status` | `mes_production_order_id, status` | BTREE | Rapid lookup when MES queries replenishment status |

---

## 5. Migration Plan

| # | Migration File | Target Table | Domain | Notes |
|---|----------------|--------------|--------|-------|
| 1 | `2026_11_01_000001_create_warehouses_table.php` | `warehouses` | Core Topology | Primary physical warehouse entities |
| 2 | `2026_11_01_000002_create_warehouse_zones_table.php` | `warehouse_zones` | Core Topology | Zones (Raw, FG, Staging, QA) |
| 3 | `2026_11_01_000003_create_warehouse_bins_table.php` | `warehouse_bins` | Core Topology | Granular bin coordinates & barcodes |
| 4 | `2026_11_01_000004_create_items_table.php` | `items` | Catalog | Resins, masterbatches, packaging, and FG |
| 5 | `2026_11_01_000005_create_material_lots_table.php` | `material_lots` | Inventory | Lots, heat numbers, expiry, and QA status |
| 6 | `2026_11_01_000006_create_inventory_balances_table.php` | `inventory_balances` | Inventory | Real-time bin-level stock balances |
| 7 | `2026_11_01_000007_create_inventory_movements_table.php` | `inventory_movements` | Ledger | Immutable double-entry audit ledger |
| 8 | `2026_11_01_000008_create_staging_requests_table.php` | `staging_requests` | Operations | MES shop-floor replenishment requests |
| 9 | `2026_11_01_000009_create_delivery_orders_table.php` | `delivery_orders` | Outbound | Delivery Orders & customer pick-pack manifests |

---

## 6. Seed Data

| Seeder | Purpose | Data Source |
|--------|---------|-------------|
| `WarehouseTopologySeeder` | Seeds Main Plant Warehouse with 4 Zones (Raw Resin, Staging, Finished Goods, QA) and 60 test Bin coordinates. | Hardcoded fixtures matching injection plant layouts |
| `ItemCatalogSeeder` | Populates common injection molding materials: Polypropylene (PP), ABS, Polycarbonate (PC), Black/White Masterbatch, and Molded Automotive Bezel parts. | Standard manufacturing catalog fixtures |
| `RoleAndPermissionSeeder` | Creates Spatie roles: `Warehouse Manager`, `Inbound Receiver`, `Staging Handler`, `FG Clerk`, and `Shipping Clerk`. | RBAC baseline specifications |
| `InitialInventorySeeder` | Seeds initial stock of raw resin lots with realistic vendor heat numbers and future expiry dates for immediate demo testing. | Synthetic test dataset |
