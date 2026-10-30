# Design Guidance (DESIGN.md)

This document defines the visual design system, UI architecture, design tokens, and ergonomics for the **Warehouse Management System (WMS)**. It serves as the single source of truth for UI/UX consistency across the Filament v3 Admin Portal and the Rugged Mobile Scanner PWA.

---

## 1. Product Character & Design Principles

### Product Character
- **Domain:** Discrete Plastic Injection Molding Warehouse & Logistics Monolith.
- **Audience:**
  1. *Back-Office / Plant Management:* Warehouse supervisors, logistics coordinators, inventory planners, and QA managers operating on desktop and industrial tablets.
  2. *Shop-Floor & Warehouse Handlers:* Forklift drivers, material handlers, and granulator operators wearing gloves, using rugged 4.7"–5.5" Android mobile computers (Zebra TC21/TC26, Honeywell EDA51).
- **Aesthetic Direction:** Modern industrial dark theme, high visual data density, immediate spatial feedback, and low-latency tactile/auditory cues.

### Core Principles
1. **Spatial Clarity:** Digital layouts must mirror physical factory racks, aisles, silos, and machine hoppers. Never force an operator to decipher abstract tables when spatial elevation grids provide instant comprehension.
2. **High Visual Contrast:** Factory ambient lighting ranges from dimly lit warehouse aisles to bright fluorescent plant floors. All text and status indicators must maintain strict WCAG AAA / AA contrast ratios.
3. **Ergonomic Gloves-First Interaction:** Industrial handheld scanner interfaces enforce minimum $\ge 48\text{px}$ touch targets, single-action wedge barcode interception, and zero pinch-zoom accidents.
4. **Instant Multi-Modal Feedback:** Every barcode scan, bin lock, or stock adjustment delivers immediate triple feedback: visual badge transition, synthesized Web Audio chime/buzz, and tactile vibration.

---

## 2. Current Appearance (Evidenced by Source Code & Observed UI)

### 2.1 Theme & Color Palette

#### Primary & Neutral Palette (Filament Admin Panel)
- **Primary Brand Accent:** **Amber** (`Color::Amber` in `app/Providers/Filament/AdminPanelProvider.php`).
  - `primary-500` (`#f59e0b`): Active sidebar navigation pills, focus rings, primary action buttons.
  - `primary-600` (`#d97706`): Hover states for primary buttons.
  - `primary-950/40`: Subtle active indicator backdrops in dark mode.
- **Neutral Dark Backgrounds:**
  - Page Canvas (`dark:bg-gray-950` / `#09090b`): Deep neutral base for the admin portal.
  - Card & Container Surface (`dark:bg-gray-900` / `#121214`): Elevated container backgrounds for toolbars, metric cards, and rack bays.
  - Input & Recessed Surfaces (`dark:bg-gray-800` / `#1e1e24`): Form controls, select dropdowns, search inputs.
  - Structural Borders (`dark:border-gray-800` / `#27272a`): 1px borders providing structure without visual clutter.
- **Text & Foreground:**
  - Primary Headings & Bold Metrics: `dark:text-gray-100` (`#f4f4f5`).
  - Secondary Labels & Meta: `dark:text-gray-400` (`#a1a1aa`).
  - Muted Subtitles & Disabled Text: `dark:text-gray-500` (`#71717a`).

#### Operational Status Tokens (Bin Heatmap & Inventory Lots)
| Status | Semantic Meaning | Foreground Token | Background Token |
| --- | --- | --- | --- |
| **OPTIMAL** | Safe capacity ($1\% - 50\%$), Lot Available | Emerald-400 (`#34d399`) | Emerald-950/60 (`#022c22`) / Emerald border |
| **WARNING** | High fill ($51\% - 85\%$), QA Hold | Amber-400 (`#fbbf24`) | Amber-950/60 (`#451a03`) / Amber border |
| **CRITICAL** | Over capacity ($> 85\%$), Expired | Rose-400 (`#fb7185`) | Rose-950/60 (`#4c0519`) / Rose pulsing border |
| **BLOCKED** | Locked slot, damaged rack | Purple-400 (`#c084fc`) | Purple-950/60 (`#3b0764`) / Purple border |
| **QUARANTINE**| Contaminated resin, QC hold cage | Yellow-400 (`#facc15`) | Yellow-950/60 (`#422006`) / Hazard border |
| **EMPTY** | $0$ KG stored, fully available | Slate-400 (`#94a3b8`) | Slate-900/80 (`#0f172a`) / Slate border |

### 2.2 Typography Scale

- **Typeface:** `Figtree` (via Tailwind font-sans default) with system fallbacks (`system-ui`, `-apple-system`, `sans-serif`).
- **Monospace Typeface:** `ui-monospace`, `SFMono-Regular`, `Menlo`, `Monaco`, `Consolas` (`font-mono`) used universally for:
  - Bin Codes (e.g., `RM-A-01-21`, `STG-IMM-04`, `QC-HOLD-01`)
  - Lot Numbers (e.g., `LOT-20261101-001`)
  - Machine Identifiers (`IMM-01` through `IMM-12`)
  - Exact Metric Weights (`1,250.00 kg / 1,500.00 kg`)
- **Type Scale:**
  - `text-2xl` (`font-black`): Top-level KPI counts (`4`, `93.3%`, `1,650.0 kg`).
  - `text-xl` (`font-bold`): Page titles, bin inspection drawer headers.
  - `text-sm` (`font-semibold`): Form inputs, select dropdowns, table column headers.
  - `text-xs` (`font-medium uppercase tracking-wider`): Field labels, KPI category titles.
  - `text-[10px]` / `text-[9px]` (`font-bold font-mono`): Bin badges, lot status chips, tier indicators.

### 2.3 Spacing, Borders & Elevation

- **Card Containers:** `p-4` or `p-5`, `rounded-xl` or `rounded-2xl`, with solid grayish background fills (`bg-gray-800/60` to `bg-gray-900`) and **no harsh wireframe borders**.
- **Inner Controls & Bin Cards:** `rounded-lg` or `rounded-xl`, borderless (`border-0`), differentiated through elevated grayish and tinted fills.
- **Elevation & Contrast:** Surface fill contrast over stroke lines. Deep dark canvas (`#09090b`) with layered grayish cards (`#1f2937` / `#27272a`) creates soft, modern industrial hierarchy without wireframe clutter.
- **Gaps:** Standard `gap-3` between metric cards, `gap-6` between rack bays, `gap-2` between shelf positions.

### 2.4 Layout & Navigation Patterns

#### Filament Desktop Admin Portal
1. **Collapsible Left Sidebar:**
   - Grouped logically by operational domains:
     - `Dashboard`
     - `Fulfillment & Shipping` (`Delivery Orders`)
     - `Audit & Ledger` (`Inventory Movements`)
     - `Inventory & Lots` (`Items`, `Material Lots`)
     - `Warehouse Topology` (`Warehouses`, `Warehouse Bins`, `Visual Bin Topology & Heatmap`)
     - `Inventory & Audits` (`Cycle Count Orders`)
   - Active navigation item rendered as an amber pill badge with glowing icon.
2. **Top Application Bar:**
   - Brand title `Laravel` on top left, operator avatar badge on top right.
3. **2D Visual Rack Grid Layout:**
   - **Aisle Header Container:** Groups multiple rack bays by aisle letter (`Aisle A`, `Aisle B`, `Aisle QC`).
   - **Rack Bay:** Industrial steel-frame representation with bay name (`Bay 01`, `Bay CAGE`).
   - **Vertical Shelf Tiers:** Arranged descending from top to ground level (`Tier 2` top shelf, `Tier 1` floor shelf), matching the physical warehouse elevation view.
   - **Bin Cards:** High-density tiles with bin code, fill percentage badge, weight progress bar, and active SKU tags.
4. **Slide-Over Inspection Drawer:**
   - Slide-over panel on the right side of the screen (`max-w-lg`) allowing deep inspection of stored lots without leaving the spatial map.

---

## 3. User Direction

- **Mode Selected:** **Existing Project** (primary evidence derived from the implemented Filament v3 portal, dark theme, and uploaded UI screenshot).
- **Visual Reference:** Deep dark mode with amber brand accents, matching the Filament v3 design tokens shown in the uploaded screenshot.
- **Surface Styling (User Directive):** **"No wireframe borders, use grayish backgrounds."** Do not use stark line borders on dark backgrounds. All toolbars, KPI metric cards, rack containers, and bin cards must be borderless with subtle, elevated grayish background fills (`bg-gray-800` / `bg-zinc-800`).
- **Language Standard:** Strict English exclusively across all UI labels, form placeholders, badges, notifications, and code comments.
- **Consistency Requirement:** Custom Blade pages must feel 100% native to Filament v3, seamlessly inheriting theme tokens, dark mode styles, and responsive behaviors.

---

## 4. Visual Inconsistencies & Approved Proposals

Based on the visual audit of the user's uploaded screenshot of the **Visual Bin Topology & Heatmap** (`ZONE-QC`), the following visual discrepancies were observed and targeted for refinement:

### Inconsistency 1: Stark White Bin Cards & Legend in Dark Mode
- **Observed Behavior:** In the uploaded screenshot, the Legend container and the bin rows (`QC-HOLD-01` to `QC-HOLD-04`) render with a light white background (`bg-slate-100` / `bg-white`) inside a dark page, resulting in unreadable low contrast for white text and breaking the dark mode immersion.
- **Root Cause:** Standard Tailwind `bg-white` and `bg-slate-100` utility classes were not overridden by Filament's dark mode selector (`.dark`) due to missing pre-compiled panel classes.
- **Approved Target Proposal:**
  - Replace hardcoded light utilities with explicit dark palette classes:
    - Legend container: `bg-gray-900 border border-gray-800`
    - Rack bays: `bg-gray-900/60 border border-gray-800`
    - Empty bin cards: `bg-gray-950 border border-gray-800 text-gray-400 hover:border-gray-700`
    - Status cards: saturated dark tint backgrounds (`bg-emerald-950/40`, `bg-amber-950/40`, `bg-rose-950/40`, `bg-yellow-950/40`) with crisp high-contrast text (`text-emerald-300`, `text-amber-300`, `text-rose-200`, `text-yellow-200`).

### Inconsistency 2: Rack Bay Container Contrast
- **Observed Behavior:** In `Bay CAGE`, the rack bay and tier headers lacked visual definition against the dark canvas.
- **Approved Target Proposal:**
  - Add industrial frame borders (`border-gray-800` with subtle zinc accents) and bold tier markers (`Tier 1`, `Tier 2`) with amber tier badges for immediate aisle recognition.

---

## 5. Rugged Mobile Scanner UI Conventions (`/scanner`)

The mobile client follows complementary ergonomics tailored to rugged handhelds:
- **Surface Styling:** **Zero wireframe borders** with solid, elevated grayish surface cards (`#18181b`, `#1e1e24`, `#27272a`) on deep AMOLED black canvas (`#09090b`), optimizing battery and readability in noisy industrial environments.
- **Viewport:** Fixed `maximum-scale=1.0, user-scalable=no` preventing accidental pinch-zooming during fast scanning.
- **Touch Target Minimum:** All interactive buttons and touch cards have a minimum height of $\ge 48\text{px}$ (up to $56\text{px}$ for primary scan confirmation actions).
- **Key Interception:** Global keyboard wedge buffer listening for hardware laser scans ($< 35\text{ms}$ delay terminated by Enter).
- **Sound & Haptics:**
  - Success Scan: Synthesized $880\text{Hz} \rightarrow 1760\text{Hz}$ ascending chime + 100ms vibration.
  - Error / Reject Scan: Synthesized $220\text{Hz} \rightarrow 110\text{Hz}$ descending buzz + double vibration pulse (`[200, 100, 200]`).
