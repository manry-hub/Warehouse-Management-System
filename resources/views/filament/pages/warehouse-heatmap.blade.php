<x-filament-panels::page>
    <div class="space-y-6" style="color: #f4f4f5;">
        {{-- Control Toolbar & Filter Bar --}}
        <div style="background-color: #18181b; border-radius: 0.875rem; padding: 1.25rem; border: none; box-shadow: 0 1px 3px rgba(0,0,0,0.3);">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa; margin-bottom: 0.35rem;">
                        Warehouse
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="selectedWarehouseId">
                            @foreach ($this->warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->code }} — {{ $wh->name }}</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa; margin-bottom: 0.35rem;">
                        Zone Area
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input.select wire:model.live="selectedZoneId">
                            @foreach ($this->zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->code }} ({{ $zone->name }})</option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>

                <div>
                    <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa; margin-bottom: 0.35rem;">
                        Search SKU / Lot
                    </label>
                    <x-filament::input.wrapper>
                        <x-filament::input type="text" wire:model.live.debounce.300ms="filterSku" placeholder="e.g. PP-HOMO, LOT-..." />
                    </x-filament::input.wrapper>
                </div>

                <div class="flex items-center gap-2">
                    <div class="flex-1">
                        <label style="display: block; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa; margin-bottom: 0.35rem;">
                            Occupancy Filter
                        </label>
                        <x-filament::input.wrapper>
                            <x-filament::input.select wire:model.live="filterStatus">
                                <option value="ALL">Show All Bins</option>
                                <option value="OCCUPIED">Occupied Only (>0 kg)</option>
                                <option value="EMPTY">Empty Only (0 kg)</option>
                                <option value="OPTIMAL">Optimal (1% – 50%)</option>
                                <option value="WARNING">Warning (51% – 85%)</option>
                                <option value="CRITICAL">Critical (>85%)</option>
                                <option value="BLOCKED">Blocked Only</option>
                            </x-filament::input.select>
                        </x-filament::input.wrapper>
                    </div>

                    @if ($filterSku || $filterStatus !== 'ALL')
                        <x-filament::button color="gray" size="sm" wire:click="resetFilters" style="align-self: flex-end; margin-bottom: 2px;">
                            Reset
                        </x-filament::button>
                    @endif
                </div>
            </div>
        </div>

        {{-- Zone KPI Summary Cards (Grayish fills, borderless) --}}
        @if ($metrics = $this->zoneMetrics)
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                <div style="background-color: #1e1e24; border-radius: 0.75rem; padding: 1rem; border: none;">
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa;">Total Bins</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: #f4f4f5; margin-top: 0.25rem;">{{ $metrics['total_bins'] }}</div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">Configured slots</div>
                </div>

                <div style="background-color: #1e1e24; border-radius: 0.75rem; padding: 1rem; border: none;">
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa;">Occupancy</div>
                    <div style="font-size: 1.75rem; font-weight: 900; color: #34d399; margin-top: 0.25rem;">{{ $metrics['occupancy_rate_pct'] }}%</div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">{{ $metrics['occupied_bins'] }} / {{ $metrics['total_bins'] }} occupied</div>
                </div>

                <div style="background-color: #1e1e24; border-radius: 0.75rem; padding: 1rem; border: none;">
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa;">Weight Utilization</div>
                    <div style="font-size: 1.75rem; font-weight: 900; margin-top: 0.25rem; color: @if($metrics['weight_utilization_pct'] > 85) #fb7185 @elseif($metrics['weight_utilization_pct'] > 50) #fbbf24 @else #60a5fa @endif;">
                        {{ $metrics['weight_utilization_pct'] }}%
                    </div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">Of total payload cap</div>
                </div>

                <div style="background-color: #1e1e24; border-radius: 0.75rem; padding: 1rem; border: none;">
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa;">Stored Weight</div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #f4f4f5; margin-top: 0.35rem;">
                        {{ number_format($metrics['total_stored_weight_kg'], 1) }} <span style="font-size: 0.75rem; font-weight: 400; color: #a1a1aa;">kg</span>
                    </div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">Cap: {{ number_format($metrics['total_max_weight_kg'], 0) }} kg</div>
                </div>

                <div style="background-color: #1e1e24; border-radius: 0.75rem; padding: 1rem; border: none;">
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa;">Free Headroom</div>
                    <div style="font-size: 1.35rem; font-weight: 800; color: #34d399; margin-top: 0.35rem;">
                        {{ number_format($metrics['available_headroom_kg'], 1) }} <span style="font-size: 0.75rem; font-weight: 400; color: #a1a1aa;">kg</span>
                    </div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">{{ $metrics['empty_bins'] }} slots fully empty</div>
                </div>

                <div style="background-color: #1e1e24; border-radius: 0.75rem; padding: 1rem; border: none;">
                    <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #a1a1aa;">Blocked Slots</div>
                    <div style="font-size: 1.75rem; font-weight: 900; margin-top: 0.25rem; color: @if($metrics['blocked_bins'] > 0) #c084fc @else #71717a @endif;">
                        {{ $metrics['blocked_bins'] }}
                    </div>
                    <div style="font-size: 0.75rem; color: #71717a; margin-top: 0.25rem;">Locked from picking</div>
                </div>
            </div>
        @endif

        {{-- Heatmap Legend Bar (Grayish fill, borderless) --}}
        <div style="background-color: #18181b; border-radius: 0.75rem; padding: 0.75rem 1rem; border: none; display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem; font-size: 0.75rem;">
            <span style="color: #a1a1aa; text-transform: uppercase; font-size: 0.65rem; font-weight: 700; margin-right: 0.5rem;">Legend:</span>
            <span style="background-color: #27272a; color: #d4d4d8; padding: 0.3rem 0.65rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background-color: #71717a;"></span> Empty (0%)
            </span>
            <span style="background-color: rgba(6, 78, 59, 0.7); color: #6ee7b7; padding: 0.3rem 0.65rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background-color: #10b981;"></span> Optimal (1% – 50%)
            </span>
            <span style="background-color: rgba(120, 53, 15, 0.7); color: #fde68a; padding: 0.3rem 0.65rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background-color: #f59e0b;"></span> Warning (51% – 85%)
            </span>
            <span style="background-color: rgba(159, 18, 57, 0.8); color: #fecdd3; padding: 0.3rem 0.65rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background-color: #f43f5e;"></span> Critical (>85%)
            </span>
            <span style="background-color: rgba(88, 28, 135, 0.7); color: #e9d5ff; padding: 0.3rem 0.65rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background-color: #a855f7;"></span> Blocked
            </span>
            <span style="background-color: rgba(113, 63, 18, 0.7); color: #fef08a; padding: 0.3rem 0.65rem; border-radius: 0.375rem; display: inline-flex; align-items: center; gap: 0.4rem;">
                <span style="width: 0.5rem; height: 0.5rem; border-radius: 9999px; background-color: #eab308;"></span> Quarantine / QA Hold
            </span>
        </div>

        {{-- 2D Visual Rack Grid Layout --}}
        @php $topology = $this->topology; @endphp

        @if (empty($topology))
            <div style="background-color: #18181b; border-radius: 1rem; padding: 3rem; text-align: center; border: none;">
                <svg class="mx-auto h-12 w-12 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <h3 style="margin-top: 0.75rem; font-size: 0.875rem; font-weight: 600; color: #e4e4e7;">No Bins Found</h3>
                <p style="margin-top: 0.25rem; font-size: 0.8rem; color: #a1a1aa;">No storage locations match your current zone or filter criteria.</p>
            </div>
        @else
            <div class="space-y-8">
                @foreach ($topology as $aisleKey => $aisleData)
                    <div style="background-color: #141417; border-radius: 1rem; padding: 1.25rem; border: none; margin-bottom: 1.5rem;">
                        {{-- Aisle Header --}}
                        <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.75rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="background-color: rgba(245, 158, 11, 0.15); color: #fbbf24; padding: 0.25rem 0.75rem; border-radius: 0.5rem; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em;">
                                    Aisle {{ $aisleKey }}
                                </span>
                                <span style="font-size: 0.75rem; color: #71717a;">
                                    {{ count($aisleData['racks']) }} Rack Bay(s)
                                </span>
                            </div>
                        </div>

                        {{-- Racks in Aisle --}}
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                            @foreach ($aisleData['racks'] as $rackKey => $rackData)
                                <div style="background-color: #1a1a1f; border-radius: 0.75rem; padding: 1rem; border: none; display: flex; flex-col; justify-content: space-between;">
                                    {{-- Rack Bay Header --}}
                                    <div style="display: flex; align-items: center; justify-content: space-between; padding-bottom: 0.5rem; margin-bottom: 0.5rem;">
                                        <div style="font-size: 0.75rem; font-weight: 700; color: #f4f4f5; text-transform: uppercase;">
                                            Bay {{ $rackKey }}
                                        </div>
                                        <div style="font-size: 0.65rem; color: #71717a; font-family: monospace;">
                                            Aisle {{ $aisleKey }} / Rack {{ $rackKey }}
                                        </div>
                                    </div>

                                    {{-- Vertical Shelves (Top to Bottom) --}}
                                    <div class="space-y-3" style="width: 100%;">
                                        @foreach ($rackData['shelves'] as $shelfKey => $shelfData)
                                            <div style="background-color: #222228; border-radius: 0.5rem; padding: 0.75rem; border: none; margin-bottom: 0.75rem;">
                                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                                                    <span style="font-size: 0.65rem; font-weight: 700; color: #a1a1aa; text-transform: uppercase; letter-spacing: 0.05em;">
                                                        Tier {{ $shelfKey }}
                                                    </span>
                                                </div>

                                                {{-- Bins in Shelf (Grayish / Tinted Fills, No Harsh Border) --}}
                                                <div class="grid grid-cols-2 gap-2">
                                                    @foreach ($shelfData['bins'] as $bin)
                                                        @php
                                                            $status = $bin['status'];
                                                            $cardBg = match($status) {
                                                                'EMPTY' => '#27272f',
                                                                'OPTIMAL' => 'rgba(6, 78, 59, 0.55)',
                                                                'WARNING' => 'rgba(120, 53, 15, 0.55)',
                                                                'CRITICAL' => 'rgba(159, 18, 57, 0.65)',
                                                                'BLOCKED' => 'rgba(88, 28, 135, 0.55)',
                                                                'QUARANTINE' => 'rgba(113, 63, 18, 0.55)',
                                                                default => '#27272f'
                                                            };

                                                            $textColor = match($status) {
                                                                'EMPTY' => '#d4d4d8',
                                                                'OPTIMAL' => '#a7f3d0',
                                                                'WARNING' => '#fde68a',
                                                                'CRITICAL' => '#fecdd3',
                                                                'BLOCKED' => '#e9d5ff',
                                                                'QUARANTINE' => '#fef08a',
                                                                default => '#d4d4d8'
                                                            };

                                                            $badgeBg = match($status) {
                                                                'EMPTY' => '#3f3f46',
                                                                'OPTIMAL' => '#065f46',
                                                                'WARNING' => '#92400e',
                                                                'CRITICAL' => '#e11d48',
                                                                'BLOCKED' => '#6b21a8',
                                                                'QUARANTINE' => '#854d0e',
                                                                default => '#3f3f46'
                                                            };
                                                        @endphp

                                                        <div wire:click="selectBin('{{ $bin['id'] }}')"
                                                             style="background-color: {{ $cardBg }}; color: {{ $textColor }}; border-radius: 0.5rem; padding: 0.75rem; border: none; cursor: pointer; transition: transform 0.15s ease; @if($selectedBinId === $bin['id']) outline: 2px solid #f59e0b; @endif"
                                                             class="hover:-translate-y-0.5">
                                                            <div style="display: flex; align-items: center; justify-content: space-between;">
                                                                <span style="font-family: monospace; font-weight: 800; font-size: 0.75rem; color: #f4f4f5;">{{ $bin['bin_code'] }}</span>
                                                                <span style="font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 0.25rem; background-color: {{ $badgeBg }}; color: #ffffff;">
                                                                    @if ($status === 'BLOCKED')
                                                                        LOCKED
                                                                    @elseif ($status === 'EMPTY')
                                                                        EMPTY
                                                                    @else
                                                                        {{ $bin['fill_percentage'] }}%
                                                                    @endif
                                                                </span>
                                                            </div>

                                                            {{-- Weight & Progress Meter --}}
                                                            <div style="margin-top: 0.5rem;">
                                                                <div style="width: 100%; background-color: #18181b; height: 5px; border-radius: 9999px; overflow: hidden;">
                                                                    <div style="height: 100%; border-radius: 9999px; width: {{ min(100, $bin['fill_percentage']) }}%; background-color: @if($status === 'CRITICAL') #f43f5e @elseif($status === 'WARNING') #f59e0b @elseif($status === 'OPTIMAL') #10b981 @elseif($status === 'BLOCKED') #a855f7 @else #71717a @endif;"></div>
                                                                </div>
                                                                <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.65rem; margin-top: 0.35rem; color: #a1a1aa; font-family: monospace;">
                                                                    <span>{{ number_format($bin['stored_weight_kg'], 0) }} kg</span>
                                                                    <span style="color: #71717a;">/ {{ number_format($bin['max_weight_kg'], 0) }} kg</span>
                                                                </div>
                                                            </div>

                                                            {{-- SKU tags --}}
                                                            @if (!empty($bin['active_skus']))
                                                                <div style="margin-top: 0.5rem; display: flex; flex-wrap: wrap; gap: 0.25rem;">
                                                                    @foreach (array_slice($bin['active_skus'], 0, 2) as $sku)
                                                                        <span style="font-size: 0.6rem; font-weight: 600; padding: 0.15rem 0.35rem; border-radius: 0.25rem; background-color: rgba(255,255,255,0.1); color: #f4f4f5; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                                                            {{ $sku }}
                                                                        </span>
                                                                    @endforeach
                                                                    @if (count($bin['active_skus']) > 2)
                                                                        <span style="font-size: 0.55rem; color: #a1a1aa; font-weight: 700; align-self: center;">+{{ count($bin['active_skus']) - 2 }}</span>
                                                                    @endif
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
                    </div>
                @endforeach
            </div>
        @endif

        {{-- Slide-Over Bin Detail Drawer via Native Filament Modal Component --}}
        <x-filament::modal id="bin-modal" slide-over width="lg" :close-by-clicking-away="true">
            @if ($activeBinDetails)
                <x-slot name="heading">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-family: monospace; font-size: 1.25rem; font-weight: 800; color: #f4f4f5;">
                            {{ $activeBinDetails['bin_code'] }}
                        </span>
                        <x-filament::badge :color="match($activeBinDetails['status']) {
                            'OPTIMAL' => 'success',
                            'WARNING' => 'warning',
                            'CRITICAL' => 'danger',
                            'BLOCKED' => 'purple',
                            'QUARANTINE' => 'warning',
                            default => 'gray',
                        }">
                            {{ $activeBinDetails['status'] }}
                        </x-filament::badge>
                    </div>
                </x-slot>

                <x-slot name="description">
                    <span style="color: #a1a1aa;">
                        {{ $activeBinDetails['warehouse_code'] }} &bull; {{ $activeBinDetails['zone_name'] }}
                    </span>
                </x-slot>

                {{-- Modal Body Content --}}
                <div class="space-y-6" style="padding-top: 0.5rem; color: #f4f4f5;">
                    {{-- Spatial Coordinates --}}
                    <div style="display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.5rem; text-align: center; padding: 0.75rem; background-color: #1e1e24; border-radius: 0.75rem; font-family: monospace; font-size: 0.75rem; border: none;">
                        <div>
                            <div style="font-size: 0.65rem; text-transform: uppercase; color: #a1a1aa;">Aisle</div>
                            <div style="font-weight: 700; color: #f4f4f5;">{{ $activeBinDetails['aisle'] }}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.65rem; text-transform: uppercase; color: #a1a1aa;">Rack</div>
                            <div style="font-weight: 700; color: #f4f4f5;">{{ $activeBinDetails['rack'] }}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.65rem; text-transform: uppercase; color: #a1a1aa;">Shelf Tier</div>
                            <div style="font-weight: 700; color: #f4f4f5;">{{ $activeBinDetails['shelf'] }}</div>
                        </div>
                        <div>
                            <div style="font-size: 0.65rem; text-transform: uppercase; color: #a1a1aa;">Position</div>
                            <div style="font-weight: 700; color: #f4f4f5;">{{ $activeBinDetails['bin'] }}</div>
                        </div>
                    </div>

                    {{-- Capacity Progress Meter --}}
                    <div style="padding: 1rem; background-color: #1e1e24; border-radius: 0.75rem; border: none;">
                        <div style="display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem;">
                            <span style="color: #d4d4d8;">Payload Weight Utilization</span>
                            <span style="color: @if($activeBinDetails['fill_percentage'] > 85) #fb7185 @elseif($activeBinDetails['fill_percentage'] > 50) #fbbf24 @else #34d399 @endif; font-weight: 800;">
                                {{ $activeBinDetails['fill_percentage'] }}%
                            </span>
                        </div>

                        <div style="width: 100%; background-color: #141417; height: 8px; border-radius: 9999px; overflow: hidden; margin-bottom: 0.75rem;">
                            <div style="height: 100%; border-radius: 9999px; width: {{ min(100, $activeBinDetails['fill_percentage']) }}%; background-color: @if($activeBinDetails['fill_percentage'] > 85) #f43f5e @elseif($activeBinDetails['fill_percentage'] > 50) #f59e0b @else #10b981 @endif;"></div>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.5rem; font-size: 0.75rem; font-family: monospace;">
                            <div>
                                <span style="color: #71717a; display: block; font-size: 0.65rem;">Stored:</span>
                                <span style="font-weight: 700; color: #f4f4f5;">{{ number_format($activeBinDetails['stored_weight_kg'], 1) }} kg</span>
                            </div>
                            <div>
                                <span style="color: #71717a; display: block; font-size: 0.65rem;">Max Cap:</span>
                                <span style="font-weight: 700; color: #f4f4f5;">{{ number_format($activeBinDetails['max_weight_kg'], 0) }} kg</span>
                            </div>
                            <div>
                                <span style="color: #71717a; display: block; font-size: 0.65rem;">Headroom:</span>
                                <span style="font-weight: 700; color: #34d399;">{{ number_format($activeBinDetails['headroom_kg'], 1) }} kg</span>
                            </div>
                        </div>
                    </div>

                    {{-- Stored Lots & Balances Table --}}
                    <div class="space-y-3">
                        <h3 style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #a1a1aa;">
                            Stored Inventory Lots ({{ count($activeBinDetails['balances']) }})
                        </h3>

                        @if (empty($activeBinDetails['balances']))
                            <div style="padding: 1.5rem; text-align: center; background-color: #1e1e24; border-radius: 0.75rem; color: #71717a; font-size: 0.75rem; border: none;">
                                Bin is currently empty. No active inventory lots recorded.
                            </div>
                        @else
                            <div class="space-y-2">
                                @foreach ($activeBinDetails['balances'] as $balance)
                                    <div style="padding: 0.85rem; background-color: #1e1e24; border-radius: 0.75rem; border: none; margin-bottom: 0.5rem;">
                                        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.35rem;">
                                            <div>
                                                <span style="font-weight: 700; font-size: 0.8rem; color: #f4f4f5;">{{ $balance['item_sku'] }}</span>
                                                <span style="font-size: 0.7rem; color: #a1a1aa; margin-left: 0.25rem;">({{ $balance['item_name'] }})</span>
                                            </div>
                                            <span style="font-size: 0.65rem; font-weight: 700; padding: 0.15rem 0.4rem; border-radius: 0.25rem;
                                                @if($balance['status'] === 'AVAILABLE') background-color: rgba(6, 78, 59, 0.7); color: #6ee7b7;
                                                @elseif($balance['status'] === 'QA_HOLD') background-color: rgba(120, 53, 15, 0.7); color: #fde68a;
                                                @else background-color: rgba(159, 18, 57, 0.7); color: #fecdd3; @endif">
                                                {{ $balance['status'] }}
                                            </span>
                                        </div>

                                        <div style="display: flex; align-items: center; justify-content: space-between; font-size: 0.75rem; color: #a1a1aa; font-family: monospace; margin-bottom: 0.5rem;">
                                            <div style="display: flex; align-items: center; gap: 0.35rem;">
                                                <span>Lot: <strong style="color: #f4f4f5;">{{ $balance['lot_number'] }}</strong></span>
                                                @if ($balance['lot_type'] === 'REGRIND')
                                                    <span style="font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.35rem; background-color: rgba(19, 78, 74, 0.7); color: #5eead4; border-radius: 0.25rem;">
                                                        REGRIND (Gen {{ $balance['regrind_generation'] }})
                                                    </span>
                                                    @if ($balance['regrind_machine'])
                                                        <span style="font-size: 0.6rem; font-weight: 700; padding: 0.15rem 0.35rem; background-color: rgba(88, 28, 135, 0.7); color: #e9d5ff; border-radius: 0.25rem;">
                                                            {{ $balance['regrind_machine'] }}
                                                        </span>
                                                    @endif
                                                @endif
                                            </div>
                                            @if ($balance['expiry_date'])
                                                <span style="font-size: 0.65rem; color: #71717a;">Exp: {{ $balance['expiry_date'] }}</span>
                                            @endif
                                        </div>

                                        <div style="display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.25rem; font-size: 0.75rem; font-family: monospace; padding-top: 0.35rem; border-top: 1px solid #27272a;">
                                            <div>
                                                <span style="font-size: 0.6rem; color: #71717a; display: block;">On Hand:</span>
                                                <span style="font-weight: 700; color: #f4f4f5;">{{ number_format($balance['quantity_on_hand'], 1) }}</span>
                                            </div>
                                            <div>
                                                <span style="font-size: 0.6rem; color: #71717a; display: block;">Reserved:</span>
                                                <span style="color: #fbbf24;">{{ number_format($balance['quantity_reserved'], 1) }}</span>
                                            </div>
                                            <div>
                                                <span style="font-size: 0.6rem; color: #71717a; display: block;">Available:</span>
                                                <span style="font-weight: 700; color: #34d399;">{{ number_format($balance['available_quantity'], 1) }}</span>
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
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; width: 100%;">
                        <x-filament::button
                            :color="$activeBinDetails['is_blocked'] ? 'success' : 'danger'"
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
