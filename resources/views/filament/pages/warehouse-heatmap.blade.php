<x-filament-panels::page>
    @php
        $getBinStyle = function (array $bin): array {
            $fill = (float) $bin['fill_percentage'];
            $isBlocked = (bool) $bin['is_blocked'];
            $isQuarantine = (bool) $bin['is_quarantine'];

            if ($isBlocked) {
                return [
                    'style' => 'background-color: #7e22ce; color: #ffffff; border: none;',
                    'badge_style' => 'background-color: rgba(0,0,0,0.35); color: #ffffff;',
                    'label' => 'LOCKED',
                    'is_empty' => false,
                ];
            }
            if ($isQuarantine) {
                return [
                    'style' => 'background-color: #b45309; color: #ffffff; border: none;',
                    'badge_style' => 'background-color: rgba(0,0,0,0.35); color: #ffffff;',
                    'label' => 'QA HOLD',
                    'is_empty' => false,
                ];
            }
            if ($fill <= 0) {
                return [
                    'style' => 'background-color: #27272a; color: #71717a; border: none;',
                    'badge_style' => 'background-color: rgba(255,255,255,0.06); color: #71717a;',
                    'label' => 'EMPTY',
                    'is_empty' => true,
                ];
            }
            if ($fill <= 25) {
                return [
                    'style' => 'background-color: #059669; color: #ffffff; border: none;',
                    'badge_style' => 'background-color: rgba(0,0,0,0.25); color: #ffffff;',
                    'label' => $bin['fill_percentage'] . '%',
                    'is_empty' => false,
                ];
            }
            if ($fill <= 50) {
                return [
                    'style' => 'background-color: #10b981; color: #022c22; border: none;',
                    'badge_style' => 'background-color: rgba(0,0,0,0.18); color: #022c22;',
                    'label' => $bin['fill_percentage'] . '%',
                    'is_empty' => false,
                ];
            }
            if ($fill <= 70) {
                return [
                    'style' => 'background-color: #facc15; color: #422006; border: none;',
                    'badge_style' => 'background-color: rgba(0,0,0,0.18); color: #422006;',
                    'label' => $bin['fill_percentage'] . '%',
                    'is_empty' => false,
                ];
            }
            if ($fill <= 85) {
                return [
                    'style' => 'background-color: #f97316; color: #ffffff; border: none;',
                    'badge_style' => 'background-color: rgba(0,0,0,0.25); color: #ffffff;',
                    'label' => $bin['fill_percentage'] . '%',
                    'is_empty' => false,
                ];
            }
            return [
                'style' => 'background-color: #ef4444; color: #ffffff; border: none; box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);',
                'badge_style' => 'background-color: rgba(0,0,0,0.3); color: #ffffff;',
                'label' => $bin['fill_percentage'] . '%',
                'is_empty' => false,
            ];
        };
    @endphp

    <div class="space-y-6">
        {{-- Warehouse Selector Toolbar (Border-free) --}}
        <x-filament::section class="!ring-0 !border-0 shadow-none dark:!ring-0 dark:!border-0">
            <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4">
                <div class="max-w-md w-full">
                    <label class="fi-fo-field-wrp-label inline-flex items-center gap-x-2 text-sm font-semibold leading-6 text-gray-950 dark:text-white mb-1.5">
                        <x-filament::icon icon="heroicon-m-building-office-2" class="w-4 h-4 text-amber-500" />
                        Selected Warehouse Facility
                    </label>
                    <x-filament::input.wrapper class="!ring-0 !border-0">
                        <x-filament::input.select wire:model.live="selectedWarehouseId">
                            @foreach ($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div class="flex items-center gap-2">
                    <div class="inline-flex rounded-lg shadow-sm bg-gray-900 p-0.5 border-0 ring-0">
                        <button
                            type="button"
                            wire:click="setViewMode('matrix')"
                            class="px-3 py-1.5 text-xs font-bold rounded-md transition border-0 ring-0 {{ $viewMode === 'matrix' ? 'bg-amber-500 text-black shadow' : 'text-gray-400 hover:text-white' }}"
                        >
                            🗺️ Floor Plan Matrix
                        </button>
                        <button
                            type="button"
                            wire:click="setViewMode('elevation')"
                            class="px-3 py-1.5 text-xs font-bold rounded-md transition border-0 ring-0 {{ $viewMode === 'elevation' ? 'bg-amber-500 text-black shadow' : 'text-gray-400 hover:text-white' }}"
                        >
                            🏢 Rack Bay Elevation
                        </button>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Facility KPI Summary Cards via Native Filament Stat Components --}}
        @if ($stats = $this->getStats())
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-4">
                @foreach ($stats as $stat)
                    {{ $stat }}
                @endforeach
            </div>
        @endif

        {{-- Heatmap Thermal Gradient Legend Bar (Real Vivid Heatmap Palette, Seamless Borderless) --}}
        <x-filament::section compact class="!ring-0 !border-0 shadow-none dark:!ring-0 dark:!border-0">
            <div class="flex flex-wrap items-center gap-2 text-xs">
                <span class="text-xs font-bold uppercase tracking-wider text-gray-400 mr-1">
                    Heatmap Scale:
                </span>
                <span style="background-color: #27272a; color: #a1a1aa;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-gray-400"></span> Empty (0%)
                </span>
                <span style="background-color: #059669; color: #ffffff;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold shadow-sm inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-white"></span> Low (1% – 25%)
                </span>
                <span style="background-color: #10b981; color: #022c22;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold shadow-sm inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-black"></span> Optimal (26% – 50%)
                </span>
                <span style="background-color: #facc15; color: #422006;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold shadow-sm inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-amber-950"></span> Moderate (51% – 70%)
                </span>
                <span style="background-color: #f97316; color: #ffffff;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold shadow-sm inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-white"></span> High (71% – 85%)
                </span>
                <span style="background-color: #ef4444; color: #ffffff;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-black shadow-md inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-white"></span> Critical (>85%)
                </span>
                <span style="background-color: #7e22ce; color: #ffffff;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold shadow-sm inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-white"></span> Blocked / Locked
                </span>
                <span style="background-color: #b45309; color: #ffffff;" class="px-2.5 py-1 rounded-md font-mono text-[11px] font-bold shadow-sm inline-flex items-center gap-1.5 border-0">
                    <span class="w-2 h-2 rounded-full bg-white"></span> Quarantine / QA Hold
                </span>
            </div>
        </x-filament::section>

        {{-- Entire Warehouse Heatmap Topology (All Zones Rendered) --}}
        @php $zonesTopology = $this->warehouseTopology; @endphp

        @if (empty($zonesTopology))
            <x-filament::section class="!ring-0 !border-0 shadow-none dark:!ring-0 dark:!border-0">
                <div class="text-center py-12">
                    <x-filament::icon icon="heroicon-o-squares-2x2" class="mx-auto h-12 w-12 text-gray-500" />
                    <h3 class="mt-3 text-sm font-semibold text-gray-200">No Storage Bins in Warehouse</h3>
                    <p class="mt-1 text-sm text-gray-400">This warehouse facility has no configured zones or storage bins.</p>
                </div>
            </x-filament::section>
        @else
            <div class="space-y-8">
                @foreach ($zonesTopology as $zoneData)
                    <div class="space-y-4 rounded-2xl bg-gray-950/60 p-5 border-0 ring-0">
                        {{-- Zone Sector Header --}}
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-3 border-b border-gray-800/60 gap-2">
                            <div class="flex items-center gap-3">
                                <span class="font-mono text-sm font-black text-amber-400 px-3 py-1 rounded-lg bg-amber-500/15 border-0">
                                    {{ $zoneData['zone_code'] }}
                                </span>
                                <div>
                                    <h2 class="text-base font-extrabold text-white tracking-wide">
                                        {{ $zoneData['zone_name'] }}
                                    </h2>
                                    <p class="text-xs text-gray-400 font-mono mt-0.5">
                                        {{ $zoneData['metrics']['occupied_bins'] }} of {{ $zoneData['metrics']['total_bins'] }} Bins Occupied &bull; 
                                        {{ $zoneData['metrics']['occupancy_rate_pct'] }}% Occupancy &bull; 
                                        {{ number_format($zoneData['metrics']['total_stored_weight_kg'], 1) }} kg Stored
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <x-filament::badge :color="match($zoneData['zone_type']) {
                                    'FINISHED_GOODS' => 'success',
                                    'RAW_MATERIALS' => 'primary',
                                    'QUARANTINE' => 'warning',
                                    'STAGING' => 'info',
                                    'SILO' => 'purple',
                                    default => 'gray',
                                }">
                                    {{ $zoneData['zone_type'] }}
                                </x-filament::badge>
                            </div>
                        </div>

                        {{-- Zone Aisles & Racks Grid --}}
                        @if ($viewMode === 'matrix')
                            {{-- MODE A: 2D Spatial Floor Plan Matrix (Like Image 1 reference) --}}
                            <div class="space-y-6 pt-1">
                                @foreach ($zoneData['aisles'] as $aisleKey => $aisleData)
                                    <x-filament::section
                                        class="!ring-0 !border-0 shadow-none dark:!ring-0 dark:!border-0"
                                        :heading="'Aisle ' . $aisleKey . ' — Floor Matrix'"
                                        :description="count($aisleData['racks']) . ' Rack Bay Lane(s)'"
                                    >
                                        <x-slot name="headerEnd">
                                            <x-filament::badge color="primary">
                                                Lane {{ $aisleKey }}
                                            </x-filament::badge>
                                        </x-slot>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 pt-2">
                                            @foreach ($aisleData['racks'] as $rackKey => $rackData)
                                                <div class="rounded-xl bg-gray-900/80 p-3.5 space-y-3 border-0 ring-0">
                                                    <div class="flex items-center justify-between border-b border-gray-800/40 pb-2">
                                                        <span class="font-bold text-xs uppercase text-gray-200 tracking-wider">
                                                            Bay {{ $rackKey }}
                                                        </span>
                                                        <span class="font-mono text-[10px] text-gray-500">
                                                            Aisle {{ $aisleKey }} / Rack {{ $rackKey }}
                                                        </span>
                                                    </div>

                                                    {{-- Vertical Shelves in Bay --}}
                                                    <div class="space-y-2.5">
                                                        @foreach ($rackData['shelves'] as $shelfKey => $shelfData)
                                                            <div class="space-y-1">
                                                                <div class="text-[10px] font-mono font-bold uppercase text-gray-400 px-0.5">
                                                                    Tier {{ $shelfKey }}
                                                                </div>

                                                                <div class="grid grid-cols-2 gap-2">
                                                                    @foreach ($shelfData['bins'] as $bin)
                                                                        @php $theme = $getBinStyle($bin); @endphp
                                                                        <div
                                                                            wire:click="selectBin('{{ $bin['id'] }}')"
                                                                            style="{{ $theme['style'] }}"
                                                                            class="cursor-pointer rounded-lg p-2.5 transition transform hover:scale-[1.03] hover:shadow-lg relative overflow-hidden flex flex-col justify-between min-h-[95px] border-0 @if($selectedBinId === $bin['id']) ring-2 ring-amber-400 @else ring-0 @endif"
                                                                        >
                                                                            <div class="flex items-center justify-between gap-1">
                                                                                <span class="font-mono font-bold text-[11px] tracking-tight">
                                                                                    {{ $bin['bin_code'] }}
                                                                                </span>
                                                                                <span style="{{ $theme['badge_style'] }}" class="text-[9px] px-1 py-0.5 rounded font-mono font-bold border-0">
                                                                                    {{ $theme['label'] }}
                                                                                </span>
                                                                            </div>

                                                                            {{-- Center Big Value like Image 1 --}}
                                                                            <div class="my-1 text-center">
                                                                                @if (! $theme['is_empty'])
                                                                                    <div class="text-lg font-black tracking-tight leading-none">
                                                                                        {{ number_format($bin['stored_weight_kg'], 0) }} <span class="text-[10px] font-bold opacity-80">kg</span>
                                                                                    </div>
                                                                                    <div class="text-[9px] font-mono opacity-80 mt-0.5">
                                                                                        of {{ number_format($bin['max_weight_kg'], 0) }} kg
                                                                                    </div>
                                                                                @else
                                                                                    <div class="text-xs font-semibold opacity-60 py-1.5">
                                                                                        0 kg
                                                                                    </div>
                                                                                @endif
                                                                            </div>

                                                                            {{-- Bottom SKU pills --}}
                                                                            @if (!empty($bin['active_skus']))
                                                                                <div class="flex flex-wrap items-center gap-1">
                                                                                    @foreach (array_slice($bin['active_skus'], 0, 1) as $sku)
                                                                                        <span class="text-[9px] font-bold px-1 py-0.5 rounded bg-black/20 truncate max-w-full border-0">
                                                                                            {{ $sku }}
                                                                                        </span>
                                                                                    @endforeach
                                                                                    @if (count($bin['active_skus']) > 1)
                                                                                        <span class="text-[9px] font-black opacity-80">+{{ count($bin['active_skus']) - 1 }}</span>
                                                                                    @endif
                                                                                </div>
                                                                            @else
                                                                                <div class="text-[9px] opacity-40 font-mono text-right">
                                                                                    Pos {{ $bin['bin'] }}
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </x-filament::section>
                                @endforeach
                            </div>
                        @else
                            {{-- MODE B: Rack Bay Elevation View --}}
                            <div class="space-y-6 pt-1">
                                @foreach ($zoneData['aisles'] as $aisleKey => $aisleData)
                                    <x-filament::section
                                        collapsible
                                        class="!ring-0 !border-0 shadow-none dark:!ring-0 dark:!border-0"
                                        :heading="'Aisle ' . $aisleKey"
                                        :description="count($aisleData['racks']) . ' Rack Bay(s)'"
                                    >
                                        <x-slot name="headerEnd">
                                            <x-filament::badge color="primary">
                                                Aisle {{ $aisleKey }}
                                            </x-filament::badge>
                                        </x-slot>

                                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6 pt-2">
                                            @foreach ($aisleData['racks'] as $rackKey => $rackData)
                                                <x-filament::section
                                                    compact
                                                    class="!ring-0 !border-0 shadow-none dark:!ring-0 dark:!border-0"
                                                    :heading="'Bay ' . $rackKey"
                                                    :description="'Aisle ' . $aisleKey . ' / Rack ' . $rackKey"
                                                >
                                                    <div class="space-y-4">
                                                        @foreach ($rackData['shelves'] as $shelfKey => $shelfData)
                                                            <div class="rounded-lg bg-gray-950/80 p-3 border-0 ring-0">
                                                                <div class="flex items-center justify-between mb-2">
                                                                    <span class="text-xs font-bold uppercase tracking-wider text-gray-400">
                                                                        Tier {{ $shelfKey }}
                                                                    </span>
                                                                </div>

                                                                <div class="grid grid-cols-2 gap-3">
                                                                    @foreach ($shelfData['bins'] as $bin)
                                                                        @php $theme = $getBinStyle($bin); @endphp
                                                                        <div
                                                                            wire:click="selectBin('{{ $bin['id'] }}')"
                                                                            style="{{ $theme['style'] }}"
                                                                            class="cursor-pointer rounded-lg p-3 transition transform hover:scale-[1.02] hover:shadow-lg relative overflow-hidden flex flex-col justify-between min-h-[110px] border-0 @if($selectedBinId === $bin['id']) ring-2 ring-amber-400 @else ring-0 @endif"
                                                                        >
                                                                            <div class="flex items-center justify-between gap-1 mb-1">
                                                                                <span class="font-mono font-bold text-xs tracking-wider">
                                                                                    {{ $bin['bin_code'] }}
                                                                                </span>
                                                                                <span style="{{ $theme['badge_style'] }}" class="text-[10px] px-1.5 py-0.5 rounded font-mono font-bold border-0">
                                                                                    {{ $theme['label'] }}
                                                                                </span>
                                                                            </div>

                                                                            {{-- Center Big Value like Image 1 --}}
                                                                            <div class="my-1.5 text-center">
                                                                                @if (! $theme['is_empty'])
                                                                                    <div class="text-xl font-black tracking-tight leading-none">
                                                                                        {{ number_format($bin['stored_weight_kg'], 0) }} <span class="text-xs font-bold opacity-80">kg</span>
                                                                                    </div>
                                                                                    <div class="text-[10px] font-mono opacity-80 mt-0.5">
                                                                                        of {{ number_format($bin['max_weight_kg'], 0) }} kg
                                                                                    </div>
                                                                                @else
                                                                                    <div class="text-xs font-medium opacity-60 italic py-2">
                                                                                        Available (0 kg)
                                                                                    </div>
                                                                                @endif
                                                                            </div>

                                                                            {{-- Bottom SKU pills --}}
                                                                            @if (!empty($bin['active_skus']))
                                                                                <div class="flex flex-wrap items-center gap-1 mt-1">
                                                                                    @foreach (array_slice($bin['active_skus'], 0, 2) as $sku)
                                                                                        <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-black/20 truncate max-w-[120px] border-0">
                                                                                            {{ $sku }}
                                                                                        </span>
                                                                                    @endforeach
                                                                                    @if (count($bin['active_skus']) > 2)
                                                                                        <span class="text-[9px] font-black opacity-80">+{{ count($bin['active_skus']) - 2 }}</span>
                                                                                    @endif
                                                                                </div>
                                                                            @else
                                                                                <div class="text-[10px] opacity-40 font-mono text-right">
                                                                                    Tier {{ $bin['shelf'] }} &bull; Pos {{ $bin['bin'] }}
                                                                                </div>
                                                                            @endif
                                                                        </div>
                                                                    @endforeach
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </x-filament::section>
                                            @endforeach
                                        </div>
                                    </x-filament::section>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Slide-Over Bin Detail Drawer (Modal completely borderless & dark) --}}
        <x-filament::modal id="bin-modal" slide-over width="lg" :close-by-clicking-away="true">
            @if ($activeBinDetails)
                <x-slot name="heading">
                    <div class="flex items-center gap-2">
                        <span class="font-mono text-xl font-black text-white">
                            {{ $activeBinDetails['bin_code'] }}
                        </span>
                        <x-filament::badge :color="match($activeBinDetails['status']) {
                            'OPTIMAL' => 'success',
                            'WARNING' => 'warning',
                            'CRITICAL' => 'danger',
                            'BLOCKED' => 'purple',
                            'QUARANTINE' => 'amber',
                            default => 'gray',
                        }">
                            {{ $activeBinDetails['status'] }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                <x-slot name="description">
                    <span class="text-gray-400">
                        {{ $activeBinDetails['warehouse_code'] }} &bull; {{ $activeBinDetails['zone_name'] }}
                    </span>
                </x-slot>

                {{-- Modal Body Content (Completely border-free, dark cards) --}}
                <div class="space-y-5 pt-1">
                    {{-- Spatial Coordinates --}}
                    <div class="rounded-xl bg-gray-950/80 p-4 border-0 ring-0 space-y-2">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">Spatial Coordinates</h4>
                        <div class="grid grid-cols-4 gap-2 text-center font-mono text-xs">
                            <div class="rounded-lg bg-gray-900 p-2.5 text-center border-0 ring-0">
                                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Aisle</div>
                                <div class="font-black text-base text-white mt-0.5">{{ $activeBinDetails['aisle'] }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-900 p-2.5 text-center border-0 ring-0">
                                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Rack</div>
                                <div class="font-black text-base text-white mt-0.5">{{ $activeBinDetails['rack'] }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-900 p-2.5 text-center border-0 ring-0">
                                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Tier</div>
                                <div class="font-black text-base text-white mt-0.5">{{ $activeBinDetails['shelf'] }}</div>
                            </div>
                            <div class="rounded-lg bg-gray-900 p-2.5 text-center border-0 ring-0">
                                <div class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">Position</div>
                                <div class="font-black text-base text-white mt-0.5">{{ $activeBinDetails['bin'] }}</div>
                            </div>
                        </div>
                    </div>

                    {{-- Capacity Progress Meter --}}
                    <div class="rounded-xl bg-gray-950/80 p-4 border-0 ring-0 space-y-3">
                        <div class="flex justify-between items-center text-sm font-semibold">
                            <span class="text-gray-300">Payload Weight Utilization</span>
                            <x-filament::badge :color="$activeBinDetails['fill_percentage'] > 85 ? 'danger' : ($activeBinDetails['fill_percentage'] > 50 ? 'warning' : 'success')">
                                {{ $activeBinDetails['fill_percentage'] }}%
                            </x-filament::badge>
                        </div>

                        <div class="w-full bg-gray-800 h-2.5 rounded-full overflow-hidden border-0">
                            <div
                                class="h-full rounded-full @if($activeBinDetails['fill_percentage'] > 85) bg-danger-500 @elseif($activeBinDetails['fill_percentage'] > 50) bg-warning-500 @else bg-success-500 @endif"
                                style="width: {{ min(100, $activeBinDetails['fill_percentage']) }}%"
                            ></div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-xs font-mono pt-1 text-center">
                            <div class="rounded-lg bg-gray-900 p-2 border-0 ring-0">
                                <span class="text-gray-400 block text-[10px]">Stored:</span>
                                <span class="font-bold text-white text-sm">{{ number_format($activeBinDetails['stored_weight_kg'], 1) }} kg</span>
                            </div>
                            <div class="rounded-lg bg-gray-900 p-2 border-0 ring-0">
                                <span class="text-gray-400 block text-[10px]">Max Cap:</span>
                                <span class="font-bold text-white text-sm">{{ number_format($activeBinDetails['max_weight_kg'], 0) }} kg</span>
                            </div>
                            <div class="rounded-lg bg-gray-900 p-2 border-0 ring-0">
                                <span class="text-gray-400 block text-[10px]">Headroom:</span>
                                <span class="font-bold text-emerald-400 text-sm">{{ number_format($activeBinDetails['headroom_kg'], 1) }} kg</span>
                            </div>
                        </div>
                    </div>

                    {{-- Stored Lots & Balances Table (Completely border-free, dark cards) --}}
                    <div class="rounded-xl bg-gray-950/80 p-4 border-0 ring-0 space-y-3">
                        <h4 class="text-xs font-bold uppercase tracking-wider text-gray-400">
                            Stored Inventory Lots ({{ count($activeBinDetails['balances']) }})
                        </h4>

                        @if (empty($activeBinDetails['balances']))
                            <div class="text-center py-6 text-gray-400 text-xs italic">
                                Bin is currently empty. No active inventory lots recorded.
                            </div>
                        @else
                            <div class="space-y-3">
                                @foreach ($activeBinDetails['balances'] as $balance)
                                    <div class="rounded-lg bg-gray-900 p-3.5 space-y-2.5 border-0 ring-0">
                                        <div class="flex items-center justify-between gap-2">
                                            <div>
                                                <span class="font-bold text-sm text-white">{{ $balance['item_sku'] }}</span>
                                                <span class="text-xs text-gray-400 ml-1">({{ $balance['item_name'] }})</span>
                                            </div>
                                            <x-filament::badge :color="match($balance['status']) {
                                                'AVAILABLE' => 'success',
                                                'QA_HOLD' => 'warning',
                                                default => 'danger',
                                            }" size="xs">
                                                {{ $balance['status'] }}
                                            </x-filament::badge>
                                        </div>

                                        <div class="flex items-center justify-between text-xs font-mono text-gray-400">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span>Lot: <strong class="text-white">{{ $balance['lot_number'] }}</strong></span>
                                                @if ($balance['lot_type'] === 'REGRIND')
                                                    <x-filament::badge color="info" size="xs">
                                                        REGRIND (Gen {{ $balance['regrind_generation'] }})
                                                    </x-filament::badge>
                                                    @if ($balance['regrind_machine'])
                                                        <x-filament::badge color="purple" size="xs">
                                                            {{ $balance['regrind_machine'] }}
                                                        </x-filament::badge>
                                                    @endif
                                                @endif
                                            </div>
                                            @if ($balance['expiry_date'])
                                                <span class="text-[10px] text-gray-400">Exp: {{ $balance['expiry_date'] }}</span>
                                            @endif
                                        </div>

                                        <div class="grid grid-cols-3 gap-2 text-xs font-mono pt-2 border-t border-gray-800/40 text-center">
                                            <div>
                                                <span class="text-[10px] text-gray-400 block">On Hand:</span>
                                                <span class="font-bold text-white">{{ number_format($balance['quantity_on_hand'], 1) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-gray-400 block">Reserved:</span>
                                                <span class="font-semibold text-amber-400">{{ number_format($balance['quantity_reserved'], 1) }}</span>
                                            </div>
                                            <div>
                                                <span class="text-[10px] text-gray-400 block">Available:</span>
                                                <span class="font-bold text-emerald-400">{{ number_format($balance['available_quantity'], 1) }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Modal Footer Actions --}}
                <x-slot name="footer">
                    <div class="flex items-center justify-between gap-3 w-full">
                        <x-filament::button
                            :color="$activeBinDetails['is_blocked'] ? 'success' : 'danger'"
                            :icon="$activeBinDetails['is_blocked'] ? 'heroicon-m-lock-open' : 'heroicon-m-lock-closed'"
                            wire:click="toggleBlocked('{{ $activeBinDetails['id'] }}')">
                            @if ($activeBinDetails['is_blocked'])
                                Unblock Bin Slot
                            @else
                                Lock / Block Bin Slot
                            @endif
                        </x-filament::button>

                        <x-filament::button color="gray" wire:click="closeDrawer">
                            Close
                        </x-filament::button>
                    </div>
                </x-slot>
            @endif
        </x-filament::modal>
    </div>
</x-filament-panels::page>
