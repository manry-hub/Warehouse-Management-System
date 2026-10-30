<x-filament-panels::page>
    @php
        $silo = $this->record;
        $fillPct = (float) $silo->current_fill_percentage;
        $currentWeight = (float) $silo->current_weight_kg;
        $maxCap = (float) $silo->max_capacity_kg;
        $totalHeight = (float) $silo->total_height_m;
        $currentDistance = (float) ($silo->current_distance_m ?? 0);
        $materialHeight = max(0, $totalHeight - $currentDistance);
        $reorderThreshold = (float) $silo->reorder_threshold_kg;
        $criticalThreshold = (float) $silo->critical_threshold_kg;
        $headroom = max(0, $maxCap - $currentWeight);

        // Fill color styling
        $fillGradient = match ($silo->status) {
            'CRITICAL' => 'from-rose-600/80 to-rose-900/90',
            'WARNING' => 'from-amber-500/80 to-amber-800/90',
            default => 'from-emerald-500/80 to-emerald-800/90',
        };

        $badgeColor = match ($silo->status) {
            'CRITICAL' => 'bg-rose-950/60 text-rose-400',
            'WARNING' => 'bg-amber-950/60 text-amber-400',
            default => 'bg-emerald-950/60 text-emerald-400',
        };

        $readings = $silo->readings()->take(10)->get();
        $requisitions = $silo->purchaseRequisitions()->take(5)->get();
    @endphp

    <div class="space-y-6">
        <!-- Top Status Banner -->
        <div class="rounded-xl p-6" style="background-color: #18181b;">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="text-2xl font-bold text-white tracking-tight">{{ $silo->code }}</h2>
                        <span class="px-2.5 py-1 text-xs font-semibold rounded-md {{ $badgeColor }}">
                            {{ $silo->status }}
                        </span>
                        <span class="px-2.5 py-1 text-xs font-mono font-medium rounded-md text-zinc-300" style="background-color: #27272a;">
                            {{ $silo->item->sku }} — {{ $silo->item->name }}
                        </span>
                    </div>
                    <p class="text-sm text-zinc-400 mt-1">
                        {{ $silo->name }} • Linked Bin: <span class="font-mono text-amber-400">{{ $silo->bin->bin_code }}</span> • Bulk Density: <span class="font-mono">{{ $silo->bulk_density_kg_m3 }} kg/m³</span>
                    </p>
                </div>
                <div class="flex items-center gap-4 text-right">
                    <div>
                        <div class="text-xs uppercase tracking-wider text-zinc-500">Last Telemetry Reading</div>
                        <div class="text-sm font-mono text-zinc-300">
                            {{ $silo->last_telemetry_at ? $silo->last_telemetry_at->format('Y-m-d H:i:s') : 'No readings yet' }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Grid: Cylinder Gauge (Left) and Detailed KPIs / Tables (Right) -->
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <!-- Left: Silo Vessel Visualizer -->
            <div class="lg:col-span-4 rounded-xl p-6 flex flex-col items-center justify-between" style="background-color: #18181b;">
                <div class="w-full flex justify-between items-center mb-4">
                    <span class="text-xs uppercase font-semibold text-zinc-400 tracking-wider">Vertical Vessel Gauge</span>
                    <span class="text-xs font-mono text-amber-400 font-bold">{{ $fillPct }}% Fill</span>
                </div>

                <!-- Vessel Drawing -->
                <div class="relative w-48 h-96 flex flex-col items-center">
                    <!-- Top Radar Sensor Horn -->
                    <div class="w-16 h-6 rounded-t-lg flex items-center justify-center text-xs font-mono text-zinc-400 shadow-md" style="background-color: #27272a;">
                        <x-heroicon-o-signal class="w-4 h-4 text-amber-400 animate-pulse" />
                    </div>

                    <!-- Sensor Ullage Beam -->
                    <div class="w-1 bg-amber-400/30 flex-grow" style="height: {{ max(10, 100 - $fillPct) }}%;"></div>

                    <!-- Silo Cylindrical Body Outer Shell -->
                    <div class="relative w-44 h-72 rounded-t-xl overflow-hidden shadow-inner flex flex-col justify-end" style="background-color: #0e0e11; border-top: 4px solid #27272a; border-left: 2px solid #27272a; border-right: 2px solid #27272a;">
                        <!-- Level Graduation Ticks -->
                        <div class="absolute inset-y-0 left-2 flex flex-col justify-between py-2 text-[10px] font-mono text-zinc-600 pointer-events-none select-none z-10">
                            <span>100% ({{ $totalHeight }}m)</span>
                            <span>75%</span>
                            <span>50%</span>
                            <span>25%</span>
                            <span>0%</span>
                        </div>

                        <!-- Reorder Point Line Marker -->
                        @php
                            $reorderPct = $maxCap > 0 ? ($reorderThreshold / $maxCap) * 100 : 25;
                        @endphp
                        <div class="absolute left-0 right-0 z-20 flex items-center pointer-events-none" style="bottom: {{ $reorderPct }}%;">
                            <div class="w-full border-t border-dashed border-amber-500/70"></div>
                            <span class="absolute right-1 -top-3 text-[9px] font-mono text-amber-400 bg-black/60 px-1 rounded">REORDER</span>
                        </div>

                        <!-- Liquid / Polymer Pellets Dynamic Fill Layer -->
                        <div class="w-full bg-gradient-to-t {{ $fillGradient }} transition-all duration-700 ease-out relative" style="height: {{ $fillPct }}%;">
                            <div class="absolute inset-x-0 top-0 h-2 bg-white/20"></div>
                            <div class="absolute inset-x-0 bottom-2 text-center text-white/90 text-xs font-bold font-mono tracking-wider drop-shadow-md">
                                {{ number_format($currentWeight, 0) }} kg
                            </div>
                        </div>
                    </div>

                    <!-- Bottom Conical Hopper -->
                    <div class="w-44 h-16 flex items-center justify-center relative overflow-hidden" style="background-color: #18181b;">
                        <svg viewBox="0 0 100 40" preserveAspectRatio="none" class="w-full h-full">
                            <polygon points="0,0 100,0 60,40 40,40" fill="#27272a" />
                            @if($fillPct > 5)
                                <polygon points="0,0 100,0 60,40 40,40" fill="currentColor" class="text-zinc-800" />
                            @endif
                        </svg>
                    </div>

                    <!-- Bottom Discharge Rotary Valve -->
                    <div class="w-10 h-6 rounded-b-md flex items-center justify-center text-[10px] font-mono text-zinc-400" style="background-color: #27272a;">
                        VALVE
                    </div>
                </div>

                <div class="w-full mt-4 pt-4 border-t border-zinc-800/40 text-center">
                    <p class="text-xs text-zinc-400">
                        Geometry: <span class="font-mono text-zinc-300">{{ $silo->diameter_m }}m &Oslash; &times; {{ $totalHeight }}m H</span> (Cone: <span class="font-mono text-zinc-300">{{ $silo->cone_height_m }}m</span>)
                    </p>
                </div>
            </div>

            <!-- Right: KPIs and Operational Tables -->
            <div class="lg:col-span-8 space-y-6">
                <!-- KPI Cards Grid -->
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="p-4 rounded-xl" style="background-color: #18181b;">
                        <div class="text-xs font-medium text-zinc-400">Stored Mass</div>
                        <div class="text-xl font-bold font-mono text-white mt-1">{{ number_format($currentWeight, 0) }} <span class="text-xs font-sans text-zinc-400">kg</span></div>
                        <div class="text-xs text-zinc-500 mt-1 font-mono">({{ round($currentWeight / 1000, 2) }} MT)</div>
                    </div>

                    <div class="p-4 rounded-xl" style="background-color: #18181b;">
                        <div class="text-xs font-medium text-zinc-400">Fill Level</div>
                        <div class="text-xl font-bold font-mono text-white mt-1">{{ $fillPct }}%</div>
                        <div class="text-xs text-zinc-500 mt-1 font-mono">of {{ number_format($maxCap, 0) }} kg</div>
                    </div>

                    <div class="p-4 rounded-xl" style="background-color: #18181b;">
                        <div class="text-xs font-medium text-zinc-400">Available Headroom</div>
                        <div class="text-xl font-bold font-mono text-amber-400 mt-1">{{ number_format($headroom, 0) }} <span class="text-xs font-sans text-zinc-400">kg</span></div>
                        <div class="text-xs text-zinc-500 mt-1">Tanker intake space</div>
                    </div>

                    <div class="p-4 rounded-xl" style="background-color: #18181b;">
                        <div class="text-xs font-medium text-zinc-400">Ullage Distance</div>
                        <div class="text-xl font-bold font-mono text-white mt-1">{{ $currentDistance }} <span class="text-xs font-sans text-zinc-400">m</span></div>
                        <div class="text-xs text-zinc-500 mt-1 font-mono">Bed: {{ round($materialHeight, 2) }}m</div>
                    </div>
                </div>

                <!-- Recent Telemetry History -->
                <div class="rounded-xl p-5" style="background-color: #18181b;">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold text-white tracking-wide uppercase">Recent Telemetry Readings</h3>
                        <span class="text-xs text-zinc-400 font-mono">Live Ingestion Stream</span>
                    </div>

                    @if($readings->isEmpty())
                        <div class="py-8 text-center text-sm text-zinc-500">
                            No telemetry readings recorded yet for this silo.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-zinc-500 uppercase border-b border-zinc-800/60">
                                        <th class="py-2">Recorded At</th>
                                        <th class="py-2">Transmitter</th>
                                        <th class="py-2 text-right">Distance (m)</th>
                                        <th class="py-2 text-right">Volume (m³)</th>
                                        <th class="py-2 text-right">Mass (kg)</th>
                                        <th class="py-2 text-right">Fill %</th>
                                        <th class="py-2 text-right">Temp (°C)</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800/40 font-mono">
                                    @foreach($readings as $reading)
                                        <tr class="hover:bg-zinc-800/30">
                                            <td class="py-2.5 text-zinc-300 font-sans">{{ $reading->recorded_at->format('Y-m-d H:i:s') }}</td>
                                            <td class="py-2.5 text-zinc-400">{{ $reading->sensor_type }}</td>
                                            <td class="py-2.5 text-right text-zinc-200">{{ $reading->distance_m }}</td>
                                            <td class="py-2.5 text-right text-zinc-400">{{ $reading->volume_m3 }}</td>
                                            <td class="py-2.5 text-right font-bold text-white">{{ number_format((float) $reading->weight_kg, 1) }}</td>
                                            <td class="py-2.5 text-right {{ (float) $reading->fill_percentage < 20 ? 'text-rose-400' : 'text-emerald-400' }}">
                                                {{ $reading->fill_percentage }}%
                                            </td>
                                            <td class="py-2.5 text-right text-zinc-400">{{ $reading->temperature_c ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <!-- Active Purchase Requisitions -->
                <div class="rounded-xl p-5" style="background-color: #18181b;">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-sm font-semibold text-white tracking-wide uppercase">Automated Reorder & Purchase Requisitions</h3>
                        <span class="text-xs text-zinc-400">Reorder Point: <strong class="text-amber-400 font-mono">{{ number_format($reorderThreshold, 0) }} kg</strong></span>
                    </div>

                    @if($requisitions->isEmpty())
                        <div class="py-6 text-center text-sm text-zinc-500">
                            No purchase requisitions open for this silo.
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-zinc-500 uppercase border-b border-zinc-800/60">
                                        <th class="py-2">PR Number</th>
                                        <th class="py-2">Requested Qty</th>
                                        <th class="py-2">Requester</th>
                                        <th class="py-2">Status</th>
                                        <th class="py-2">Created</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-800/40 font-mono">
                                    @foreach($requisitions as $pr)
                                        <tr class="hover:bg-zinc-800/30">
                                            <td class="py-2.5 font-bold text-amber-400">{{ $pr->pr_number }}</td>
                                            <td class="py-2.5 text-white">{{ number_format((float) $pr->requested_quantity_kg, 0) }} kg</td>
                                            <td class="py-2.5 text-zinc-400 font-sans">{{ $pr->requested_by_type }}</td>
                                            <td class="py-2.5">
                                                <span class="px-2 py-0.5 rounded text-[11px] font-semibold {{ $pr->status === 'DRAFT' ? 'bg-amber-950/60 text-amber-400' : 'bg-emerald-950/60 text-emerald-400' }}">
                                                    {{ $pr->status }}
                                                </span>
                                            </td>
                                            <td class="py-2.5 text-zinc-400 font-sans">{{ $pr->created_at->format('Y-m-d H:i') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
