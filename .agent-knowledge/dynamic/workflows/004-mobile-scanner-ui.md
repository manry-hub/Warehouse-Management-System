# Implementation Workflow 004: Rugged Mobile Handheld Scanner Web UI / PWA

**Status:** `COMPLETE`  
**Target Outcome:** Production-ready, high-contrast, mobile-first Web Application and Progressive Web App (PWA) tailored specifically for 4.7"–5.5" industrial Android barcode terminals (Zebra TC21/TC26, Honeywell EDA51/ScanPal) and warehouse material handlers wearing gloves. Features hardware laser wedge interception, client-side synthesized Web Audio feedback, tactile vibration, and dedicated workflows for Inbound Receiving, Guided Putaway, Machine Staging, Regrind Intake, Blind Cycle Counting, and Outbound Truck Loading.  
**Owner:** `Mobile Logistics UX & Industrial Systems Team`  
**References:**

- [PRD.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/PRD.md) (Section 3 Post-MVP #1, Epics 1–6)
- [ARCHITECTURE.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/docs/dev/sdd/ARCHITECTURE.md)
- [001-wms-core-implementation.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/001-wms-core-implementation.md)
- [002-blind-cycle-counting.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/002-blind-cycle-counting.md)
- [003-regrind-scrap-and-blend-cap.md](file:///home/ughway/Debiancode/Myprojects/Warehouse-Management-System/.agent-knowledge/dynamic/workflows/003-regrind-scrap-and-blend-cap.md)

---

## 1. Objective and Scope

### Objective

Deliver a rugged, high-speed mobile web client that transforms Zebra and Honeywell Android handheld barcode terminals into purpose-built factory tools. The interface must prioritize high visual contrast, oversized 48px+ touch targets for gloved fingers, sub-100ms hardware scanner keycode interception, instant synthesized audio/haptic feedback, and zero-clutter task-focused views.

### In Scope

- **PWA Foundation & Assets:**
  - Web App Manifest (`manifest.json`) and service worker for standalone Android home screen installation.
  - High-contrast industrial UI theme using responsive Tailwind CSS.
- **Client-Side Hardware Integration:**
  - **Hardware Laser Wedge Interceptor:** Global keydown listener capturing hardware laser scan bursts ($< 35\text{ms}$ inter-character delay) and auto-routing to active inputs without requiring manual on-screen taps.
  - **Synthesized Web Audio API Sound Engine:** Zero-latency dual-frequency chime on successful scan (880Hz $\rightarrow$ 1760Hz), and harsh buzzer tone on error/rejection (220Hz $\rightarrow$ 110Hz).
  - **Haptic Vibration API:** Tactile pulse confirmation for noisy factory environments.
- **Dedicated Operational Modes:**
  1. 📦 **Inbound Dock Receiving:** PO lookup, vendor lot & heat recording, printable GS1-128 QR & ZPL thermal label dispatch.
  2. 📍 **Guided Putaway:** Recommends optimal rack bin coordinates; scan-to-confirm bin transfer.
  3. ⚙️ **Shop-Floor Staging & Replenishment:** Active picklists, barcode validation enforcing FEFO and regrind blend caps, bin transfer to IMM buffer.
  4. ♻️ **Granulator Scrap Intake:** Granulator station screen recording runner scrap box with weight, parent batch link, and instant regrind label print.
  5. 📋 **Blind Stock Opname:** Blind count sheets with concealed book stock, barcode scan submission, and live item progress counter.
  6. 🚚 **Truck Loading Manifest:** Scan-to-verify cartons against Delivery Order with live loading progress bar and audio mismatch alerts.
- **Authentication & Session Routing:**
  - Mobile operator badge sign-in (`/scanner/login`) using Sanctum API tokens with persistent device storage.
  - Route groups under `/scanner`.
- **Automated Verification Harness:**
  - Feature tests verifying scanner web routes, authentication redirections, and API interaction payloads.

### Out of Scope

- Native Android Java/Kotlin APK compilation (the solution is a standalone Progressive Web Application running in Chrome / Zebra Enterprise Browser).
- Bluetooth low-energy peripheral direct GATT pairing (handled natively by the Android OS host).

### Assumptions and Open Questions

- Handheld terminals run modern Android with Chromium-based browsers (Chrome 100+ or Enterprise Browser).
- Barcode scanners are configured in "Keyboard Wedge" mode with Enter suffix (the universal industry standard).

---

## 2. Staged Execution Plan

### Stage 1: PWA Shell, Layout, and Audio Engine

**Objective:** Build the rugged mobile shell, PWA manifest, and client-side audio/wedge scanner engine.

1. Create PWA manifest `public/scanner-manifest.json` and icons.
2. Build mobile base layout `resources/views/scanner/layout.blade.php`:
   - Viewport meta tags preventing accidental mobile pinch-zoom (`user-scalable=no`).
   - Web Audio API sound synthesizer module (`playSuccessSound()`, `playErrorSound()`).
   - Vibration module (`navigator.vibrate`).
   - Laser scanner keystroke buffer listener.
3. Build mobile authentication page `resources/views/scanner/login.blade.php`:
   - Follow modern web guidance: semantic `<form>`, `type="email"`, `autocomplete="username"`, `autocomplete="current-password"`, `enterkeyhint="go"`, minimum 48px touch targets.
4. Build mobile mode launcher `resources/views/scanner/dashboard.blade.php`:
   - Grid of 6 large cards with intuitive icons and active badge counts.

**Exit Criteria:**

- [x] Navigating to `/scanner` loads the mobile UI with clean responsive rendering on mobile viewport emulation.
- [x] Audio chime and buzz trigger properly on demand without console errors.

---

### Stage 2: Inbound Dock Receiving & Guided Putaway Views

**Objective:** Implement mobile views for PO intake and warehouse rack putaway.

1. Implement `resources/views/scanner/modes/receiving.blade.php`:
   - Form for PO intake, SKU selector, vendor heat #, net weight.
   - Dynamic modal displaying generated GS1 QR Code and ZPL stream.
2. Implement `resources/views/scanner/modes/putaway.blade.php`:
   - Putaway assistant: suggests optimal bin coordinates (Aisle, Rack, Shelf).
   - Scan input field to scan destination bin barcode with instant audio confirmation.

**Exit Criteria:**

- [x] Operator can receive a lot, view generated label, and execute putaway transfer from mobile interface.

---

### Stage 3: Shop-Floor Staging Replenishment & Regrind Granulator Intake Views

**Objective:** Implement mobile views for machine hopper replenishment and runner scrap return.

1. Implement `resources/views/scanner/modes/staging.blade.php`:
   - Displays active MES replenishment requests.
   - Barcode scan input for lot: automatically validates against FEFO rules and regrind blend caps; highlights green on match or sounds low buzz on rejection.
2. Implement `resources/views/scanner/modes/regrind.blade.php`:
   - Granulator scrap intake: SKU selector, weight input, machine tag (`IMM-01` to `IMM-12`), instant regrind lot creation and label generation.

**Exit Criteria:**

- [x] Operator can fulfill staging requests and intake regrind scrap through mobile views with immediate visual/auditory feedback.

---

### Stage 4: Blind Stock Opname & Truck Loading Verification Views

**Objective:** Implement mobile views for blind cycle counting and outbound truck manifest loading.

1. Implement `resources/views/scanner/modes/cycle-count.blade.php`:
   - Selects active assigned count order.
   - Displays blind checklist (Bin & SKU listed, but system book quantity strictly hidden!).
   - Scan bin + lot barcode, enter physical count, and submit.
2. Implement `resources/views/scanner/modes/shipping.blade.php`:
   - Selects Delivery Order.
   - Scan carton barcodes with live carton counter (e.g., "15 / 50 loaded").
   - Blocks or sounds alarm on mismatched carton.

**Exit Criteria:**

- [x] Operator can complete a blind cycle count and scan-verify outgoing truck cartons.

---

### Stage 5: Routing, Controllers & Automated Verification

**Objective:** Connect all web routes, implement `ScannerWebController`, and write automated test suites.

1. Implement `ScannerWebController`:
   - Manages web session authentication, token bootstrapping, and view rendering.
2. Register web routes in `routes/web.php` under `/scanner`.
3. Write `Tests\Feature\ScannerWebUiTest`:
   - Verifies all mobile scanner routes render 200 OK.
   - Verifies redirect to login when unauthenticated.
   - Verifies PWA manifest is accessible and valid JSON.
4. Run full test suite (`php artisan test`) against MariaDB 11.8.
5. Format code with Laravel Pint.

**Exit Criteria:**

- [x] 100% of test suites pass on MariaDB (`WMS_DB_TEST`).
- [x] Zero linting or Pint styling errors.

---

## 3. Dependencies, Risks, and Rollback

### Dependencies

- Handheld device browser supporting modern JavaScript (AudioContext, Fetch API).
- Sanctum / Web authentication guards.

### Risks and Mitigations

| Risk | Impact | Mitigation |
| --- | --- | --- |
| **Accidental Keystroke Loss** | High: Fast barcode scanner drops characters on slow web view. | High-speed global keydown event buffer accumulates characters and fires event upon Enter keypress. |
| **Noisy Factory Floor Inaudibility** | Medium: Operator cannot hear phone beep over IMM machines. | Combine high-gain dual-tone audio with tactile device vibration (`navigator.vibrate([150, 50, 150])`). |
| **Pinch-Zoom Disruption** | Medium: Operator accidentally zooms into input on double tap. | Enforce `touch-action: manipulation` and `maximum-scale=1.0, user-scalable=no` viewport settings. |

### Rollback or Recovery

- Scanner web views and routes are non-intrusive additions under `/scanner` that do not modify existing backend APIs or Filament admin panels.

---

## 4. Verification and Acceptance Criteria

| Check | Method | Acceptance Criteria |
| --- | --- | --- |
| **Mobile Web Routes** | HTTP Feature Test | `/scanner`, `/scanner/login`, `/scanner/dashboard`, and 6 mode routes return HTTP 200. |
| **PWA Manifest** | HTTP Test / Browser | `/scanner-manifest.json` returns valid JSON with `display: standalone`. |
| **Audio Synthesis** | Browser Execution | `AudioContext` initializes on first user touch and generates chime/buzz sounds without errors. |
| **Barcode Wedge Listener** | JavaScript simulation | Fast sequence of keydown events followed by Enter triggers form submission. |
| **Touch Ergonomics** | CSS inspection | All interactive buttons have minimum height $\ge 48\text{px}$ and large typography. |

---

## 5. Completion and Handoff

- [x] All staged exit criteria and acceptance criteria are met.
- [x] Task report `004-mobile-scanner-ui.md` created under `.agent-knowledge/dynamic/reports/tasks/`.
- [x] Living documentation updated.

**Next Transition:** Subsystem ready for factory deployment on shop-floor Zebra TC26 and Honeywell EDA51 mobile computers.
