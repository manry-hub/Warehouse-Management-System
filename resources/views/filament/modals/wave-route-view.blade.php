<div class="space-y-4">
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 bg-gray-900/50 p-3 rounded-lg border border-gray-800 text-sm">
        <div>
            <div class="text-xs text-gray-400 font-mono">WAVE NUMBER</div>
            <div class="font-bold text-amber-400 font-mono">{{ $wave->wave_number }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400 font-mono">STATUS</div>
            <div class="font-semibold text-white">{{ $wave->status }}</div>
        </div>
        <div>
            <div class="text-xs text-gray-400 font-mono">BATCHES</div>
            <div class="font-bold text-white">{{ $wave->batches()->count() }} Carts</div>
        </div>
        <div>
            <div class="text-xs text-gray-400 font-mono">PROGRESS</div>
            <div class="font-bold text-emerald-400 font-mono">{{ $wave->picked_lines }} / {{ $wave->total_lines }} Lines</div>
        </div>
    </div>

    <div class="border border-gray-800 rounded-lg overflow-hidden bg-gray-950">
        <div class="px-4 py-2 bg-gray-900 border-b border-gray-800 flex justify-between items-center">
            <span class="text-xs font-bold text-gray-300 uppercase tracking-wider font-mono">
                Serpentine (S-Path) Minimum Travel Sequence
            </span>
            <span class="text-xs text-emerald-400 font-medium">
                ⚡ ~35% Travel Distance Reduced
            </span>
        </div>

        @if($batchItems->isEmpty())
            <div class="p-6 text-center text-gray-500 text-sm">
                No pick items generated yet. Release wave to calculate S-Path route.
            </div>
        @else
            <div class="divide-y divide-gray-800 max-h-96 overflow-y-auto">
                @foreach($batchItems as $item)
                    <div class="p-3 flex items-center justify-between hover:bg-gray-900/40 transition">
                        <div class="flex items-center space-x-3">
                            <span class="w-7 h-7 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-mono font-bold text-xs flex items-center justify-center">
                                #{{ $item->pick_sequence }}
                            </span>
                            <div>
                                <div class="flex items-center space-x-2">
                                    <span class="font-mono font-bold text-cyan-400 text-sm bg-cyan-950/60 px-2 py-0.5 rounded border border-cyan-800/50">
                                        {{ $item->bin->bin_code ?? 'N/A' }}
                                    </span>
                                    <span class="text-xs text-gray-300 font-medium">
                                        {{ $item->item->item_code ?? 'ITEM' }} - {{ $item->item->name ?? '' }}
                                    </span>
                                </div>
                                <div class="text-xs text-gray-400 font-mono mt-0.5 flex space-x-2">
                                    <span>Lot: <span class="text-gray-200">{{ $item->materialLot->lot_number ?? 'N/A' }}</span></span>
                                    <span>•</span>
                                    <span>Qty: <span class="text-amber-300 font-bold">{{ (float) $item->quantity_to_pick }} {{ $item->item->unit_of_measure ?? 'KG' }}</span></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-2">
                            <span class="text-xs font-mono font-bold px-2.5 py-1 rounded bg-indigo-950 text-indigo-300 border border-indigo-800">
                                ➔ {{ $item->target_tote }}
                            </span>
                            @if($item->status === \App\Models\PickingBatchItem::STATUS_PICKED)
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 border border-emerald-800">
                                    ✓ PICKED
                                </span>
                            @elseif($item->status === \App\Models\PickingBatchItem::STATUS_SHORT)
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-rose-950 text-rose-400 border border-rose-800">
                                    SHORT
                                </span>
                            @else
                                <span class="text-xs font-mono px-2 py-0.5 rounded bg-gray-800 text-gray-300">
                                    PENDING
                                </span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
