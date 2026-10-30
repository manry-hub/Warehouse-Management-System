<x-filament-widgets::widget class="fi-wi-module-launcher">
    <style>
        .command-center-banner {
            background-color: #18181b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.2);
        }
        .module-grid {
            display: grid;
            gap: 1.5rem;
            grid-template-columns: 1fr;
        }
        @media (min-width: 640px) {
            .module-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
        @media (min-width: 1280px) {
            .module-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }
        .module-item-card {
            background-color: #18181b;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 0.75rem;
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 290px;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.2);
            transition: all 0.2s ease-in-out;
        }
        .module-item-card:hover {
            border-color: rgba(245, 158, 11, 0.3);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.4), 0 0 15px -3px rgba(245, 158, 11, 0.1);
        }
        .btn-launch-primary {
            background-color: #f59e0b;
            color: #09090b;
            font-weight: 700;
            border: none;
            border-radius: 0.5rem;
            padding: 0.625rem 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            transition: all 0.15s ease-in-out;
            width: 100%;
            text-align: center;
        }
        .btn-launch-primary:hover {
            background-color: #fbbf24;
            transform: scale(1.01);
        }
    </style>

    <div class="space-y-6">
        {{-- Section Header Banner --}}
        <div class="command-center-banner flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex items-center space-x-2">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-amber-500"></span>
                    </span>
                    <span class="text-xs font-mono font-bold uppercase tracking-wider text-amber-400">
                        Operational Flight Deck • Real-Time Synchronization
                    </span>
                </div>
                <h2 class="text-xl font-bold tracking-tight text-white">
                    Core Operations & Module Command Center
                </h2>
                <p class="text-sm text-zinc-400 max-w-2xl leading-relaxed">
                    Direct access to visual rack topology, quality traceability, mobile shop-floor scanners, and logistics dispatchers.
                </p>
            </div>
            <div class="shrink-0 flex items-center">
                <a 
                    href="/scanner/dashboard" 
                    target="_blank" 
                    class="btn-launch-primary whitespace-nowrap shadow-md py-3 px-5 text-xs font-extrabold tracking-wider"
                    style="width: auto;"
                >
                    <svg class="w-4 h-4 shrink-0 text-black" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                    </svg>
                    <span>Launch Scanner PWA</span>
                    <svg class="w-3.5 h-3.5 shrink-0 text-black/70" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                    </svg>
                </a>
            </div>
        </div>

        {{-- 8 Module Cards Grid --}}
        <div class="module-grid">

            {{-- 1. Visual 2D Heatmap --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-amber-500/10 text-amber-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-amber-400">
                            {{ $heatmap['occupied_bins'] }} / {{ $heatmap['total_bins'] }} BINS
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Visual 2D Heatmap</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Spatial rack elevation, occupancy heat thresholds, and slide-over lot inspection.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/visual-heatmap" class="btn-launch-primary">
                        <span>Open Heatmap &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/admin/warehouse-bins" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>All Bins</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <a href="/admin/warehouses" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Warehouses</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- 2. Lot Genealogy & Recall --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-rose-500/10 text-rose-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-rose-400">
                            {{ $genealogy['active_quarantines'] }} QUARANTINES
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Lot Genealogy & Recall</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Bi-directional forward/backward traceability tree with 1-click atomic mass quarantine.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/lot-genealogy" class="btn-launch-primary">
                        <span>Launch Genealogy &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/admin/material-lots" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Lots ({{ $genealogy['total_lots'] }})</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <span class="text-emerald-400 font-bold">IATF 16949</span>
                    </div>
                </div>
            </div>

            {{-- 3. Rugged Scanner PWA --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-sky-500/10 text-sky-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-sky-400">
                            8 MODES READY
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Rugged Scanner PWA</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Industrial barcode terminal: PO intake, hopper staging, regrind, cycle count, gate out.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/scanner/dashboard" target="_blank" class="btn-launch-primary">
                        <span>Open Scanner App &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/scanner/hopper" target="_blank" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Hopper Feed</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <a href="/scanner/gate-pass" target="_blank" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Gate Pass</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- 4. Transfers & Cross-Dock --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-indigo-500/10 text-indigo-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-indigo-400">
                            {{ $transfers['in_transit'] }} IN-TRANSIT
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Transfers & Cross-Dock</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Moving freight tracking via WH-TRANSIT with lean putaway bypass to shipping staging bays.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/transfer-orders" class="btn-launch-primary">
                        <span>Transfer Orders &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/admin/transfer-orders/create" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>+ New Transfer</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <span class="text-zinc-500">Total: {{ $transfers['total'] }}</span>
                    </div>
                </div>
            </div>

            {{-- 5. Bulk Silo Farm --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-cyan-500/10 text-cyan-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-cyan-400">
                            {{ $silos['total'] }} SILOS ({{ $silos['avg_level'] }}%)
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Bulk Silo Farm</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Radar telemetry, conical volume geometry, and automatic purchase requisition engine.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/silos" class="btn-launch-primary">
                        <span>Monitor Silos &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/admin/purchase-requisitions" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Requisitions</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <span class="{{ $silos['critical'] > 0 ? 'text-rose-400 font-bold' : 'text-emerald-400' }}">
                            {{ $silos['critical'] }} Alerts
                        </span>
                    </div>
                </div>
            </div>

            {{-- 6. Blind Cycle Counting --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-purple-500/10 text-purple-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-purple-400">
                            {{ $audits['active'] }} ACTIVE AUDITS
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Blind Cycle Counting</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Frozen snapshot audits, blind counter mobile sheets, and atomic double-entry adjustments.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/cycle-count-orders" class="btn-launch-primary">
                        <span>Audit Orders &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/admin/cycle-count-orders/create" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>+ New Opname</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <a href="/scanner/cycle-count" target="_blank" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Scanner Sheet</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- 7. Delivery Orders & Gate Pass --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-emerald-500/10 text-emerald-400">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-emerald-400">
                            {{ $shipping['pending_gate_out'] }} AWAITING GATE
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Delivery Orders & Gate Pass</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        SSCC-18 pallet containerization, JSON ASN generation, and mobile glass driver e-signature.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/delivery-orders" class="btn-launch-primary">
                        <span>Manage Orders &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/scanner/gate-pass" target="_blank" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Gate Pass</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <span class="text-emerald-400 font-bold">{{ $shipping['cleared_gate_passes'] }} Cleared</span>
                    </div>
                </div>
            </div>

            {{-- 8. Item Catalog & Scrap --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-amber-500/10 text-amber-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-amber-400">
                            {{ $catalog['regrind_lots'] }} REGRIND LOTS
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Item Catalog & Scrap</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Resin & masterbatch catalog, runner scrap regrind intake, and 10–20% blend caps.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/items" class="btn-launch-primary">
                        <span>View Catalog &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/admin/inventory-movements" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Ledger ({{ $movements['total'] }})</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <a href="/scanner/regrind" target="_blank" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Recycle Intake</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                    </div>
                </div>
            </div>

            {{-- 9. Wave & Batch Picking --}}
            <div class="module-item-card">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-2.5 rounded-lg bg-orange-500/10 text-orange-500">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                            </svg>
                        </div>
                        <span class="px-2.5 py-1 rounded-md text-[11px] font-bold font-mono bg-zinc-900 border border-zinc-800 text-orange-400">
                            {{ $waves['active'] ?? 0 }} ACTIVE WAVES
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-white tracking-tight">Wave & Batch Picking</h3>
                    <p class="text-xs text-zinc-400 mt-1.5 leading-relaxed line-clamp-2">
                        Serpentine S-Path aisle routing, 4-tote multi-order carts, and barcode-validated drops.
                    </p>
                </div>
                <div class="mt-6 pt-4 border-t border-zinc-800 space-y-3">
                    <a href="/admin/picking-waves" class="btn-launch-primary" style="background-color: #f97316;">
                        <span>Manage Waves &rarr;</span>
                    </a>
                    <div class="flex items-center justify-between text-xs text-zinc-400 px-1 font-mono">
                        <a href="/scanner/batch-picking" target="_blank" class="hover:text-amber-400 transition flex items-center gap-1">
                            <span>Scanner Cart</span>
                            <span class="text-zinc-600">↗</span>
                        </a>
                        <span class="text-zinc-700">•</span>
                        <span class="text-zinc-300 font-bold">Total: {{ $waves['total'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-filament-widgets::widget>
