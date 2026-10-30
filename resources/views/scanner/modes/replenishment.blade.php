@extends('scanner.layout')

@section('title', 'Forklift Replenishment')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Missions Queue (Visible when choosing a mission) -->
    <div id="mission-queue-container" class="rounded-2xl p-4 shadow-md bg-[#18181b]">
        <div class="flex items-center justify-between pb-2 mb-3 border-b border-zinc-800">
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider font-mono">Pick-Face Deficit Missions</span>
            <span class="text-[10px] text-zinc-400 px-2 py-0.5 rounded bg-zinc-800 font-mono">Min-Max Top-Off</span>
        </div>

        @if($pendingTasks->isEmpty())
            <div class="text-center py-8 text-zinc-500 text-xs">
                No active pick-face deficits. All forward bins meet safety stock levels!
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($pendingTasks as $task)
                    <div class="rounded-xl p-3.5 bg-[#1e1e24] shadow-sm flex items-center justify-between border border-white/5">
                        <div class="min-w-0 pr-2">
                            <div class="flex items-center space-x-2">
                                @if($task->priority === \App\Models\ReplenishmentTask::PRIORITY_CRITICAL)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-black text-rose-300 bg-rose-500/20 border border-rose-500/30 animate-pulse">
                                        CRITICAL STOCKOUT
                                    </span>
                                @elseif($task->priority === \App\Models\ReplenishmentTask::PRIORITY_URGENT)
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold text-amber-300 bg-amber-500/20 border border-amber-500/30">
                                        URGENT
                                    </span>
                                @else
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold text-sky-300 bg-sky-500/20 border border-sky-500/30">
                                        NORMAL
                                    </span>
                                @endif
                                <span class="text-xs font-bold text-white font-mono">{{ $task->task_number }}</span>
                            </div>
                            <div class="text-[11px] text-zinc-300 mt-1.5 font-mono">
                                ➔ Target: <span class="text-cyan-400 font-bold">{{ $task->targetBin->bin_code ?? 'N/A' }}</span>
                                • <span class="text-amber-300 font-bold">{{ (float) $task->requested_quantity_kg }} KG</span>
                            </div>
                            <div class="text-[10px] text-zinc-400 font-mono mt-0.5 truncate">
                                Source: {{ $task->sourceBin->bin_code ?? 'BULK' }} • {{ $task->item->item_code ?? 'SKU' }}
                            </div>
                        </div>
                        <button 
                            type="button"
                            onclick="selectMission('{{ $task->id }}', '{{ $task->task_number }}', '{{ $task->sourceBin->bin_code ?? '' }}', '{{ $task->targetBin->bin_code ?? '' }}', '{{ $task->materialLot->lot_number ?? '' }}', '{{ $task->item->item_code ?? '' }}', '{{ $task->item->name ?? '' }}', '{{ (float) $task->requested_quantity_kg }}')"
                            class="min-h-[48px] min-w-[80px] px-4 py-2 rounded-xl bg-cyan-600 active:bg-cyan-700 text-white font-black text-xs uppercase shadow active:scale-95 transition flex items-center justify-center shrink-0"
                        >
                            Accept
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Replenishment Execution Terminal (Visible when mission accepted) -->
    <div id="mission-execution-container" class="hidden space-y-3">
        <!-- Mission Header Card -->
        <div class="rounded-2xl p-4 bg-gradient-to-r from-cyan-950/70 to-zinc-900 border border-cyan-500/30 shadow-xl">
            <div class="flex items-center justify-between mb-2 pb-2 border-b border-cyan-500/20">
                <span id="active-task-number" class="text-xs font-mono font-black text-cyan-300 tracking-wider">
                    RPL-20261030-0001
                </span>
                <span class="text-[10px] font-mono text-zinc-400 uppercase">
                    Forklift Mission
                </span>
            </div>

            <!-- Route Visualization: Bulk -> Pick Face -->
            <div class="grid grid-cols-2 gap-2 my-2">
                <div class="bg-black/50 p-2.5 rounded-xl border border-white/5">
                    <span class="text-[9px] font-mono uppercase text-zinc-400 block">1. Fetch From Bulk:</span>
                    <span id="active-source-bin" class="text-sm font-mono font-black text-amber-400 block mt-0.5">RM-B-01-01</span>
                    <span id="active-lot-number" class="text-[10px] font-mono text-zinc-300 block truncate">LOT-20261030-001</span>
                </div>
                <div class="bg-black/50 p-2.5 rounded-xl border border-white/5">
                    <span class="text-[9px] font-mono uppercase text-zinc-400 block">2. Deposit To Pick Face:</span>
                    <span id="active-target-bin" class="text-sm font-mono font-black text-emerald-400 block mt-0.5">RM-A-01-22</span>
                    <span id="active-item-sku" class="text-[10px] font-mono text-cyan-300 block truncate">RESIN-PP-01</span>
                </div>
            </div>

            <div class="flex justify-between items-center text-xs font-mono text-zinc-300 pt-1">
                <span>Top-Off Deficit:</span>
                <span id="active-quantity" class="text-base font-black text-white font-mono">200.0 KG</span>
            </div>
        </div>

        <!-- Verification Form Card -->
        <div class="rounded-2xl p-4 bg-[#1e1e24] shadow-xl border border-white/5 space-y-3">
            <div class="flex items-center justify-between pb-1 border-b border-zinc-800">
                <span class="text-xs font-bold text-zinc-300 uppercase tracking-wider font-mono">Barcode Validation</span>
                <span class="text-[10px] font-mono text-cyan-400">Reach-Truck Terminal</span>
            </div>

            <form onsubmit="submitReplenishmentConfirm(event)" class="space-y-3">
                <input type="hidden" id="current_mission_id" value="">
                <input type="hidden" id="expected_source_bin" value="">
                <input type="hidden" id="expected_lot_number" value="">
                <input type="hidden" id="expected_target_bin" value="">

                <!-- 1. Source Bulk Bin Scan -->
                <div>
                    <label class="block text-xs font-bold text-amber-300 uppercase mb-1">1. Scan Source Bulk Bin</label>
                    <input 
                        type="text" 
                        id="scan_source_bin" 
                        required 
                        data-scan-target="true"
                        placeholder="Scan bulk bin e.g. RM-B-01-01" 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-amber-600/60 text-amber-300 font-mono text-base focus:border-amber-400 uppercase"
                        autocomplete="off"
                    >
                </div>

                <!-- 2. Resin Lot Barcode Scan -->
                <div>
                    <label class="block text-xs font-bold text-zinc-300 uppercase mb-1">2. Scan Material Lot Barcode</label>
                    <input 
                        type="text" 
                        id="scan_lot_number" 
                        required 
                        placeholder="Scan virgin/regrind lot barcode" 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-zinc-700 text-white font-mono text-base focus:border-cyan-400"
                        autocomplete="off"
                    >
                </div>

                <!-- 3. Target Pick Face Bin Scan -->
                <div>
                    <label class="block text-xs font-bold text-emerald-300 uppercase mb-1">3. Scan Destination Pick Bin</label>
                    <input 
                        type="text" 
                        id="scan_target_bin" 
                        required 
                        placeholder="Scan pick face e.g. RM-A-01-22" 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-emerald-600/60 text-emerald-300 font-mono text-base focus:border-emerald-400 uppercase"
                        autocomplete="off"
                    >
                </div>

                <!-- 4. Quantity Input -->
                <div>
                    <label class="block text-xs font-bold text-zinc-300 uppercase mb-1">Quantity Restocked (KG)</label>
                    <input 
                        type="number" 
                        step="0.001" 
                        id="scan_replenished_qty" 
                        required 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-zinc-700 text-white font-mono text-base focus:border-cyan-400"
                    >
                </div>

                <!-- Confirm Action -->
                <button 
                    type="submit" 
                    id="submit-replenish-btn"
                    class="w-full min-h-[52px] rounded-xl bg-emerald-600 active:bg-emerald-700 text-white font-black text-sm uppercase tracking-wider shadow-lg active:scale-[0.98] transition flex items-center justify-center space-x-2 mt-4"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Confirm Restock Transfer</span>
                </button>
            </form>
        </div>

        <button 
            type="button" 
            onclick="backToQueue()"
            class="w-full min-h-[44px] rounded-xl bg-zinc-800 text-zinc-300 font-bold text-xs uppercase tracking-wider active:scale-95 transition"
        >
            Switch Mission
        </button>
    </div>
</div>

<script>
function selectMission(id, number, source, target, lot, sku, name, qty) {
    document.getElementById('current_mission_id').value = id;
    document.getElementById('active-task-number').innerText = number;
    document.getElementById('active-source-bin').innerText = source;
    document.getElementById('active-target-bin').innerText = target;
    document.getElementById('active-lot-number').innerText = lot;
    document.getElementById('active-item-sku').innerText = sku + ' - ' + name;
    document.getElementById('active-quantity').innerText = `${qty} KG`;

    document.getElementById('expected_source_bin').value = source;
    document.getElementById('expected_lot_number').value = lot;
    document.getElementById('expected_target_bin').value = target;
    document.getElementById('scan_replenished_qty').value = qty;

    document.getElementById('scan_source_bin').value = '';
    document.getElementById('scan_lot_number').value = '';
    document.getElementById('scan_target_bin').value = '';

    document.getElementById('mission-queue-container').classList.add('hidden');
    document.getElementById('mission-execution-container').classList.remove('hidden');

    document.getElementById('scan_source_bin').focus();

    // Notify backend task is in-transit
    fetch(`/api/v1/scanner/replenishments/${id}/start`, {
        method: 'POST',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        }
    }).catch(console.error);
}

function backToQueue() {
    document.getElementById('mission-execution-container').classList.add('hidden');
    document.getElementById('mission-queue-container').classList.remove('hidden');
}

async function submitReplenishmentConfirm(event) {
    event.preventDefault();

    const id = document.getElementById('current_mission_id').value;
    const sourceBin = document.getElementById('scan_source_bin').value.trim();
    const lotNumber = document.getElementById('scan_lot_number').value.trim();
    const targetBin = document.getElementById('scan_target_bin').value.trim();
    const quantity = parseFloat(document.getElementById('scan_replenished_qty').value);

    const btn = document.getElementById('submit-replenish-btn');
    btn.disabled = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch(`/api/v1/scanner/replenishments/${id}/confirm`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                scanned_source_bin: sourceBin,
                scanned_lot_number: lotNumber,
                scanned_target_bin: targetBin,
                quantity_kg: quantity
            })
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            window.ScannerAudio.playError();
            window.showToast(data.message || 'Replenishment validation failed', 'error');
            btn.disabled = false;
            return;
        }

        window.ScannerAudio.playSuccess();
        window.showToast(data.message, 'success');
        setTimeout(() => location.reload(), 1500);
    } catch (e) {
        window.ScannerAudio.playError();
        window.showToast('Network error during confirmation', 'error');
        btn.disabled = false;
    }
}
</script>
@endsection
