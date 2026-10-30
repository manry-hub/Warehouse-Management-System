# Technology Stack — Warehouse Management System (WMS)

> **Status:** `PLANNING` — New Project Mode  
> **Output:** `.future/docs/dev/sdd/TECHNOLOGYSTACK.md`  
> **Purpose:** Canonical list of technologies, frameworks, versions, and their specific architectural roles in the standalone WMS system.

---

## 1. Core Technology Matrix

| Layer | Technology | Version | Role |
|-------|------------|---------|------|
| **Runtime Environment** | PHP | `8.3.x` | Modern backend runtime with typed class properties, readonly classes, and JIT performance. |
| **Backend Framework** | [Laravel](https://laravel.com/) | `11.x` | Core web framework providing routing, Eloquent ORM, service container, events, and job queues. |
| **Admin & Operational UI** | [Filament PHP](https://filamentphp.com/) | `v3.x` | Full-stack TALL administration suite providing responsive data tables, forms, widgets, and action modals. |
| **Reactive Frontend Layer** | [Livewire](https://livewire.laravel.com/) | `v3.x` | Dynamic, real-time UI components without writing complex custom JavaScript state management. |
| **CSS Framework** | [Tailwind CSS](https://tailwindcss.com/) | `v3.4.x` | Utility-first styling framework integrated natively with Filament and custom scanner templates. |
| **Primary Database** | [MariaDB](https://mariadb.org/) | `11.4.x (LTS)` | ACID-compliant relational database engine with InnoDB storage, native UUID data types, row-level locking, and high-concurrency transaction throughput. |
| **Cache & Queue Broker** | [Redis](https://redis.io/) | `7.2.x` | In-memory key-value store powering Laravel Horizon queue workers, caching bin lookups, and session storage. |
| **Queue Management** | [Laravel Horizon](https://laravel.com/docs/horizon) | `v5.x` | Dashboard and code-driven configuration for Redis-powered queue workers, retries, and throughput monitoring. |
| **API Authentication** | [Laravel Sanctum](https://laravel.com/docs/sanctum) | `v4.x` | Lightweight token authentication for industrial handheld barcode terminals and external MES integrations. |
| **Access Control (RBAC)** | [Spatie Laravel Permission](https://spatie.be/docs/laravel-permission) | `v6.x` | Granular role and permission management integrated directly into Filament resource policies. |
| **Barcode / QR Generation** | [Simple QrCode (BaconQrCode)](https://github.com/SimpleSoftwareIO/simple-qrcode) / `picqer/php-barcode-generator` | `v4.x` / `v2.x` | Server-side generation of standard Code 128 barcodes and GS1-compliant 2D QR codes for pallet/bag labels. |
| **Document & PDF Engine** | [Barryvdh Laravel DomPDF](https://github.com/barryvdh/laravel-dompdf) | `v3.x` | Automated generation of printable Delivery Orders (DO), picklists, and receipt vouchers. |
| **Web Server / Reverse Proxy** | [Nginx](https://nginx.org/) | `1.26.x (Alpine)` | High-performance reverse proxy terminating TLS, serving static assets, and proxying PHP-FPM requests. |
| **Containerization** | [Docker & Docker Compose](https://www.docker.com/) | `Compose v2.x` | Local reproducible development environment and production multi-container deployment. |
| **Testing Framework** | [Pest PHP](https://pestphp.com/) | `v3.x` | Expressive and modern PHP testing framework for unit, integration, and HTTP feature test suites. |

---

## 2. Rationale & Selection Criteria

### 2.1 Backend: Laravel 11 + PHP 8.3
- **Ecosystem Maturity:** Laravel is the world's most robust and widely used PHP framework, offering out-of-the-box transactional database handling, database seeders, scheduled jobs, and a clean configuration architecture.
- **Enterprise Longevity:** Long-Term Support (LTS) compatibility, extensive security patch history, and abundant developer availability in the manufacturing IT sector.

### 2.2 Operational Dashboard: Filament v3
- **Unrivaled Velocity:** Eliminates months of repetitive frontend scaffolding for back-office enterprise features (data grids, multi-step wizards, filtering, search, export to Excel/PDF).
- **Native Livewire 3 Integration:** Enables reactive updates (e.g. real-time bin occupancy counters, incoming MES notifications) without maintaining a separate single-page application (SPA) client build pipeline.

### 2.3 Storage: MariaDB 11.4 LTS
- **Industrial Concurrency:** MariaDB's InnoDB storage engine with robust Multi-Version Concurrency Control (MVCC) and deterministic row-level locking (`SELECT ... FOR UPDATE`) guarantees that concurrent pick operations never allocate the same resin lot or bin balance twice.
- **Native UUID & JSON Capabilities:** MariaDB 10.7+ and 11.x feature native 128-bit `UUID` data types (compact binary storage with instant string representation) providing superior indexing speed for distributed keys, alongside JSON schema validation functions for vendor Certificate of Analysis (COA) test metrics.
- **Dedicated Laravel 11 Driver:** Laravel 11 introduces a first-class, dedicated `mariadb` database driver configuration, unlocking MariaDB-specific optimizations and syntax out-of-the-box.
