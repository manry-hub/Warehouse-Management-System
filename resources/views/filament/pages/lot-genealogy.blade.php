<x-filament-panels::page>
    <div class="space-y-6" style="color: #f4f4f5;">
        {{-- Search & Traversal Toolbar --}}
        <div style="background-color: #18181b; border-radius: 0.875rem; padding: 1.25rem; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div class="md:col-span-2">
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa; margin-bottom: 0.35rem;">
                        Scan or Enter Identifier
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input 
                            type="text" 
                            wire:model.defer="searchQuery" 
                            placeholder="e.g. CARTON-..., LOT-PP-..., or DO-2026..." 
                        />
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa; margin-bottom: 0.35rem;">
                        Traversal Direction
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="direction">
                            <option value="BACKWARD">Backward (Carton/DO &rarr; Raw Resin & Heats)</option>
                            <option value="FORWARD">Forward (Raw Resin &rarr; FG Cartons & Customer DOs)</option>
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="flex items-center gap-2">
                    <x-filament::button wire:click="runTrace" color="primary" class="w-full">
                        Run Traceability Graph
                    </x-filament::button>
                </div>
            </div>
        </div>

        {{-- Results Area --}}
        @if ($traceResult)
            <div class="space-y-6">
                {{-- Top Action Banner --}}
                <div class="flex items-center justify-between p-4 rounded-xl" style="background-color: #1e1e24; border: none;">
                    <div>
                        <span class="text-xs font-bold uppercase tracking-wider text-primary-400">
                            {{ $traceResult['direction'] }} TRACEABILITY GRAPH
                        </span>
                        <h2 class="text-lg font-black font-mono text-white mt-0.5">
                            Target: {{ $traceResult['target'] }}
                        </h2>
                    </div>

                    <div class="flex items-center gap-3">
                        <x-filament::button color="danger" size="sm" wire:click="openQuarantineModal">
                            <x-heroicon-o-shield-exclamation class="w-4 h-4 mr-1 inline" />
                            Trigger IATF Quarantine
                        </x-filament::button>
                    </div>
                </div>

                @if ($traceResult['direction'] === 'BACKWARD')
                    @php
                        $traces = isset($traceResult['traces']) ? $traceResult['traces'] : [$traceResult['trace']];
                    @endphp

                    @foreach ($traces as $idx => $t)
                        <div class="p-5 rounded-2xl space-y-4" style="background-color: #18181b;">
                            {{-- FG & Production Node --}}
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-xl" style="background-color: #141417;">
                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Finished Good</span>
                                    <div class="text-sm font-bold font-mono text-white">{{ $t['finished_good']['part_number'] }}</div>
                                    <div class="text-xs text-gray-300">{{ $t['finished_good']['part_name'] }}</div>
                                    <span class="inline-block mt-1 px-2 py-0.5 rounded text-[10px] font-bold text-emerald-400" style="background-color: rgba(16, 185, 129, 0.15);">
                                        Status: {{ $t['finished_good']['status'] }}
                                    </span>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Production Work Order</span>
                                    <div class="text-sm font-bold font-mono text-white">{{ $t['production_context']['order_number'] ?? 'N/A' }}</div>
                                    <div class="text-xs text-gray-400">
                                        Machine: <span class="text-white">{{ $t['production_context']['machine_code'] ?? 'N/A' }}</span> • Mold: <span class="text-white">{{ $t['production_context']['mold_code'] ?? 'N/A' }}</span>
                                    </div>
                                </div>

                                <div>
                                    <span class="text-[10px] font-bold text-gray-400 uppercase">Carton / Lot Barcode</span>
                                    <div class="text-sm font-mono font-bold text-amber-400">{{ $t['finished_good']['lot_number'] }}</div>
                                    <div class="text-xs text-gray-400">Mfg Date: {{ $t['finished_good']['manufactured_date'] ?? 'N/A' }}</div>
                                </div>
                            </div>

                            {{-- Raw Materials Upstream --}}
                            <div>
                                <h3 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                                    Upstream Raw Resins & Heats ({{ count($t['raw_materials']) }})
                                </h3>

                                @if (empty($t['raw_materials']))
                                    <div class="text-xs text-gray-400 p-3 rounded-lg" style="background-color: #141417;">
                                        No staging records explicitly mapped to this order.
                                    </div>
                                @else
                                    <div class="overflow-x-auto">
                                        <table class="w-full text-left text-xs" style="background-color: #141417; border-radius: 0.75rem;">
                                            <thead class="text-[11px] text-gray-400 uppercase" style="background-color: #1e1e24;">
                                                <tr>
                                                    <th class="p-3">Lot Number</th>
                                                    <th class="p-3">Material SKU</th>
                                                    <th class="p-3">Type</th>
                                                    <th class="p-3">Vendor Code</th>
                                                    <th class="p-3">Vendor Heat #</th>
                                                    <th class="p-3">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-zinc-800 text-gray-200">
                                                @foreach ($t['raw_materials'] as $rm)
                                                    <tr>
                                                        <td class="p-3 font-mono font-bold text-primary-400">{{ $rm['lot_number'] }}</td>
                                                        <td class="p-3 font-mono">{{ $rm['material_code'] }}</td>
                                                        <td class="p-3">{{ $rm['lot_type'] }}</td>
                                                        <td class="p-3">{{ $rm['vendor_code'] ?? '-' }}</td>
                                                        <td class="p-3 font-mono text-amber-300">{{ $rm['vendor_heat_number'] ?? '-' }}</td>
                                                        <td class="p-3">
                                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold" style="background-color: {{ $rm['status'] === 'AVAILABLE' ? 'rgba(16,185,129,0.15)' : 'rgba(239,68,68,0.2)' }}; color: {{ $rm['status'] === 'AVAILABLE' ? '#34d399' : '#f87171' }};">
                                                                {{ $rm['status'] }}
                                                            </span>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach

                @else
                    {{-- FORWARD TRACE VIEW --}}
                    <div class="p-5 rounded-2xl space-y-4" style="background-color: #18181b;">
                        {{-- Raw Material Root Node --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 p-4 rounded-xl" style="background-color: #141417;">
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Suspect Raw Material Lot</span>
                                <div class="text-sm font-bold font-mono text-amber-400">{{ $traceResult['raw_material']['lot_number'] }}</div>
                                <div class="text-xs text-gray-300">{{ $traceResult['raw_material']['material_code'] }} ({{ $traceResult['raw_material']['material_name'] }})</div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Vendor & Heat</span>
                                <div class="text-xs text-white">Vendor: <span class="font-mono">{{ $traceResult['raw_material']['vendor_code'] ?? '-' }}</span></div>
                                <div class="text-xs text-white">Heat #: <span class="font-mono text-amber-300">{{ $traceResult['raw_material']['vendor_heat'] ?? '-' }}</span></div>
                            </div>
                            <div>
                                <span class="text-[10px] font-bold text-gray-400 uppercase">Current Warehouse Balance</span>
                                <div class="text-sm font-mono font-bold text-white">{{ $traceResult['raw_material']['remaining_quantity_kg'] }} KG</div>
                                <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold text-rose-400" style="background-color: rgba(244,63,94,0.15);">
                                    Status: {{ $traceResult['raw_material']['status'] }}
                                </span>
                            </div>
                        </div>

                        {{-- Impacted Warehouse Stock --}}
                        <div>
                            <h3 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                                Downstream Molded Cartons in Warehouse ({{ count($traceResult['impacted_warehouse_stock']) }})
                            </h3>
                            @if (empty($traceResult['impacted_warehouse_stock']))
                                <div class="text-xs text-gray-400 p-3 rounded-lg" style="background-color: #141417;">
                                    No active stock found in warehouse bins.
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs" style="background-color: #141417; border-radius: 0.75rem;">
                                        <thead class="text-[11px] text-gray-400 uppercase" style="background-color: #1e1e24;">
                                            <tr>
                                                <th class="p-3">Carton Lot</th>
                                                <th class="p-3">Part SKU</th>
                                                <th class="p-3">Bin Location</th>
                                                <th class="p-3">Zone</th>
                                                <th class="p-3">Quantity</th>
                                                <th class="p-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-800 text-gray-200">
                                            @foreach ($traceResult['impacted_warehouse_stock'] as $ws)
                                                <tr>
                                                    <td class="p-3 font-mono font-bold text-white">{{ $ws['lot_number'] }}</td>
                                                    <td class="p-3 font-mono text-primary-400">{{ $ws['part_sku'] }}</td>
                                                    <td class="p-3 font-mono text-amber-300">{{ $ws['bin_code'] }}</td>
                                                    <td class="p-3">{{ $ws['zone_type'] }}</td>
                                                    <td class="p-3 font-mono">{{ $ws['quantity_on_hand'] }}</td>
                                                    <td class="p-3">
                                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold" style="background-color: {{ $ws['status'] === 'QUARANTINE' ? 'rgba(239,68,68,0.2)' : 'rgba(16,185,129,0.15)' }}; color: {{ $ws['status'] === 'QUARANTINE' ? '#f87171' : '#34d399' }};">
                                                            {{ $ws['status'] }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        {{-- Shipped Customer Delivery Orders --}}
                        <div>
                            <h3 class="text-xs font-bold text-gray-300 uppercase tracking-wider mb-2">
                                Dispatched Customer Shipments ({{ count($traceResult['customer_shipments']) }})
                            </h3>
                            @if (empty($traceResult['customer_shipments']))
                                <div class="text-xs text-gray-400 p-3 rounded-lg" style="background-color: #141417;">
                                    None dispatched to external customer trucks yet.
                                </div>
                            @else
                                <div class="overflow-x-auto">
                                    <table class="w-full text-left text-xs" style="background-color: #141417; border-radius: 0.75rem;">
                                        <thead class="text-[11px] text-gray-400 uppercase" style="background-color: #1e1e24;">
                                            <tr>
                                                <th class="p-3">DO Number</th>
                                                <th class="p-3">Customer</th>
                                                <th class="p-3">Truck Plate</th>
                                                <th class="p-3">Driver</th>
                                                <th class="p-3">Gate Pass #</th>
                                                <th class="p-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-zinc-800 text-gray-200">
                                            @foreach ($traceResult['customer_shipments'] as $cs)
                                                <tr>
                                                    <td class="p-3 font-mono font-bold text-primary-400">{{ $cs['do_number'] }}</td>
                                                    <td class="p-3">{{ $cs['customer_name'] }}</td>
                                                    <td class="p-3 font-mono text-white">{{ $cs['vehicle_plate_number'] }}</td>
                                                    <td class="p-3">{{ $cs['driver_name'] }}</td>
                                                    <td class="p-3 font-mono text-teal-300">{{ $cs['gate_pass_number'] ?? 'PENDING' }}</td>
                                                    <td class="p-3 font-bold text-[10px] text-amber-400">{{ $cs['status'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Quarantine Execution Modal --}}
        @if ($showQuarantineModal)
            <div class="fixed inset-0 z-50 flex items-center justify-center p-4" style="background-color: rgba(0,0,0,0.75);">
                <div class="w-full max-w-md p-6 rounded-2xl space-y-4 shadow-2xl" style="background-color: #18181b;">
                    <div class="flex items-center space-x-2 text-rose-500">
                        <x-heroicon-o-shield-exclamation class="w-6 h-6" />
                        <h3 class="text-base font-bold text-white uppercase tracking-wider">Execute IATF Quarantine</h3>
                    </div>

                    <p class="text-xs text-gray-300">
                        This action will immediately lock all active inventory in the blast radius of 
                        <strong class="font-mono text-amber-400">{{ $searchQuery }}</strong>, transitioning status to <span class="text-rose-400 font-bold">QUARANTINE</span> and blocking truck picking/dispatch.
                    </p>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 mb-1">Audit Root Cause / Reason</label>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model.defer="quarantineReason" placeholder="e.g. Degraded MFI moisture test failure" />
                        </x-filament::input.wrapper>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 mb-1">Recall Severity</label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.defer="quarantineSeverity">
                                <option value="CRITICAL">CRITICAL (Immediate Production Halt & Recall)</option>
                                <option value="MAJOR">MAJOR (Quarantine Remaining Stock)</option>
                                <option value="MINOR">MINOR (Quality Hold for Re-inspection)</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <x-filament::button color="gray" wire:click="closeQuarantineModal">
                            Cancel
                        </x-filament::button>
                        <x-filament::button color="danger" wire:click="executeQuarantine">
                            Confirm Mass Quarantine
                        </x-filament::button>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
