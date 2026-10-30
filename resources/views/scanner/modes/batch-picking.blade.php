@extends('scanner.layout')

@section('title', 'Batch & Wave Pick')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Batch Selection (Visible when choosing a batch) -->
    <div id="batch-selection-container" class="{{ $activeBatches->isNotEmpty() ? '' : 'hidden' }} rounded-2xl p-4 shadow-md bg-[#18181b]">
        <div class="flex items-center justify-between pb-2 mb-3 border-b border-zinc-800">
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider font-mono">Assigned Picking Batches</span>
            <span class="text-[10px] text-zinc-400 px-2 py-0.5 rounded bg-zinc-800 font-mono">S-Path Routing</span>
        </div>

        @if($activeBatches->isEmpty())
            <div class="text-center py-8 text-zinc-500 text-xs">
                No released picking batches available. Warehouse dispatchers will release new waves.
            </div>
        @else
            <div class="space-y-2.5">
                @foreach($activeBatches as $batch)
                    <div class="rounded-xl p-3.5 bg-[#1e1e24] shadow-sm flex items-center justify-between border border-white/5">
                        <div class="min-w-0 pr-2">
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold text-amber-300 bg-amber-500/20 border border-amber-500/30">
                                    {{ $batch->cart_code ?? 'CART-01' }}
                                </span>
                                <span class="text-xs font-bold text-white font-mono truncate">{{ $batch->batch_number }}</span>
                            </div>
                            <div class="text-[11px] text-zinc-400 mt-1 font-mono">
                                Wave: <span class="text-cyan-400">{{ $batch->pickingWave->wave_number ?? 'N/A' }}</span>
                                • Picks: <span class="text-zinc-200">{{ $batch->completed_picks }}/{{ $batch->total_picks }}</span>
                            </div>
                        </div>
                        <button 
                            type="button"
                            onclick="loadBatch('{{ $batch->id }}')"
                            class="min-h-[48px] min-w-[80px] px-4 py-2 rounded-xl bg-amber-500 active:bg-amber-600 text-zinc-950 font-black text-xs uppercase shadow active:scale-95 transition flex items-center justify-center shrink-0"
                        >
                            Start
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Pick Task Guidance Terminal (Visible when batch is actively running) -->
    <div id="batch-execution-container" class="hidden space-y-3">
        <!-- Route & Tote Target Header -->
        <div class="rounded-2xl p-4 bg-gradient-to-r from-amber-950/60 to-zinc-900 border border-amber-500/30 shadow-xl">
            <div class="flex items-center justify-between mb-2 pb-2 border-b border-amber-500/20">
                <div class="flex items-center space-x-2">
                    <span id="task-cart-code" class="px-2.5 py-1 rounded-lg text-xs font-mono font-black text-amber-300 bg-amber-500/20 border border-amber-500/40">
                        CART-01
                    </span>
                    <span id="task-step-counter" class="text-xs font-mono font-bold text-zinc-300">
                        Pick #1
                    </span>
                </div>
                <div class="text-right">
                    <span class="text-[10px] text-zinc-400 uppercase font-mono block">Put into Tote</span>
                    <span id="task-target-tote" class="text-sm font-mono font-black text-emerald-400 bg-emerald-950/80 px-2 py-0.5 rounded border border-emerald-500/40 animate-pulse">
                        TOTE-01
                    </span>
                </div>
            </div>

            <!-- Target Location & Item Display -->
            <div class="space-y-1.5 pt-1">
                <div class="flex items-baseline justify-between">
                    <span class="text-[10px] uppercase font-mono text-zinc-400 tracking-wider">Navigate to Bin:</span>
                    <span id="task-bin-code" class="text-lg sm:text-xl font-mono font-black text-cyan-400 tracking-wider">
                        A-01-02-B
                    </span>
                </div>
                <div class="bg-black/40 rounded-xl p-2.5 border border-white/5 space-y-1">
                    <div class="flex justify-between items-center text-xs">
                        <span id="task-item-sku" class="font-mono font-bold text-amber-300">RESIN-PP-01</span>
                        <span id="task-quantity" class="font-mono font-black text-white text-sm">25.0 KG</span>
                    </div>
                    <div id="task-item-name" class="text-[11px] text-zinc-300 truncate">Polypropylene Resin Grade A</div>
                    <div class="text-[10px] text-zinc-400 font-mono">
                        FEFO Lot: <span id="task-lot-number" class="text-zinc-200 font-bold">LOT-20261030-001</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Form Card -->
        <div class="rounded-2xl p-4 bg-[#1e1e24] shadow-xl border border-white/5 space-y-3">
            <div class="flex items-center justify-between pb-1 border-b border-zinc-800">
                <span class="text-xs font-bold text-zinc-300 uppercase tracking-wider font-mono">Scan Verification</span>
                <span id="task-scan-status" class="text-[10px] font-mono text-amber-400">Step 1: Scan Bin</span>
            </div>

            <form onsubmit="submitTaskConfirm(event)" class="space-y-3">
                <input type="hidden" id="current_batch_item_id" value="">
                <input type="hidden" id="expected_bin_code" value="">
                <input type="hidden" id="expected_lot_number" value="">
                <input type="hidden" id="expected_tote_code" value="">

                <!-- 1. Bin Scan Input -->
                <div>
                    <label class="block text-xs font-bold text-zinc-300 uppercase mb-1">1. Confirm Bin Barcode</label>
                    <input 
                        type="text" 
                        id="scan_bin_input" 
                        required 
                        data-scan-target="true"
                        placeholder="Scan bin e.g. A-01-02-B" 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-zinc-700 text-white font-mono text-base focus:border-cyan-400 focus:ring-1 focus:ring-cyan-400 uppercase"
                        autocomplete="off"
                    >
                </div>

                <!-- 2. Lot Barcode Input -->
                <div>
                    <label class="block text-xs font-bold text-zinc-300 uppercase mb-1">2. Confirm Material Lot Barcode</label>
                    <input 
                        type="text" 
                        id="scan_lot_input" 
                        required 
                        placeholder="Scan resin lot barcode" 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-zinc-700 text-white font-mono text-base focus:border-amber-400 focus:ring-1 focus:ring-amber-400"
                        autocomplete="off"
                    >
                </div>

                <!-- 3. Target Tote Drop Scan Input -->
                <div>
                    <label class="block text-xs font-bold text-emerald-300 uppercase mb-1">3. Scan Drop Tote Barcode</label>
                    <input 
                        type="text" 
                        id="scan_tote_input" 
                        required 
                        placeholder="Scan tote e.g. TOTE-01" 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-emerald-600/60 text-emerald-300 font-mono text-base focus:border-emerald-400 focus:ring-1 focus:ring-emerald-400 uppercase"
                        autocomplete="off"
                    >
                </div>

                <!-- Quantity Confirmation -->
                <div>
                    <label class="block text-xs font-bold text-zinc-300 uppercase mb-1">Picked Quantity</label>
                    <input 
                        type="number" 
                        step="0.001" 
                        id="scan_quantity_input" 
                        required 
                        class="w-full min-h-[48px] px-3.5 rounded-xl bg-black/60 border border-zinc-700 text-white font-mono text-base focus:border-cyan-400"
                    >
                </div>

                <!-- Action Button -->
                <button 
                    type="submit" 
                    id="submit-confirm-btn"
                    class="w-full min-h-[52px] rounded-xl bg-emerald-600 active:bg-emerald-700 text-white font-black text-sm uppercase tracking-wider shadow-lg active:scale-[0.98] transition flex items-center justify-center space-x-2 mt-4"
                >
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                    <span>Confirm Pick & Put-to-Tote</span>
                </button>
            </form>
        </div>

        <!-- Back to Batches Button -->
        <button 
            type="button" 
            onclick="backToBatchList()"
            class="w-full min-h-[44px] rounded-xl bg-zinc-800 text-zinc-300 font-bold text-xs uppercase tracking-wider active:scale-95 transition"
        >
            Switch Batch Cart
        </button>
    </div>
</div>

<script>
let currentActiveBatchId = null;

async function loadBatch(batchId) {
    currentActiveBatchId = batchId;
    try {
        const res = await fetch(`/api/v1/scanner/batch-picking/${batchId}/tasks`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await res.json();
        if (!data.success) {
            window.showToast(data.message || 'Error loading batch tasks', 'error');
            return;
        }

        renderBatchExecution(data);
    } catch (e) {
        window.showToast('Network error loading batch tasks', 'error');
    }
}

function renderBatchExecution(data) {
    document.getElementById('batch-selection-container').classList.add('hidden');
    document.getElementById('batch-execution-container').classList.remove('hidden');

    const batch = data.batch;
    const task = data.current_task;

    if (!task) {
        window.showToast('Batch complete! All orders picked.', 'success');
        window.ScannerAudio.playSuccess();
        setTimeout(() => location.reload(), 1500);
        return;
    }

    document.getElementById('task-cart-code').innerText = batch.cart_code || 'CART-01';
    document.getElementById('task-step-counter').innerText = `Pick #${task.pick_sequence} of ${batch.total_picks}`;
    document.getElementById('task-target-tote').innerText = task.target_tote;
    document.getElementById('task-bin-code').innerText = task.bin_code;
    document.getElementById('task-item-sku').innerText = task.item_sku;
    document.getElementById('task-item-name').innerText = task.item_name;
    document.getElementById('task-lot-number').innerText = task.lot_number;
    document.getElementById('task-quantity').innerText = `${task.quantity_to_pick} ${task.uom}`;

    document.getElementById('current_batch_item_id').value = task.id;
    document.getElementById('expected_bin_code').value = task.bin_code;
    document.getElementById('expected_lot_number').value = task.lot_number;
    document.getElementById('expected_tote_code').value = task.target_tote;
    document.getElementById('scan_quantity_input').value = task.quantity_to_pick;

    // Reset inputs
    document.getElementById('scan_bin_input').value = '';
    document.getElementById('scan_lot_input').value = '';
    document.getElementById('scan_tote_input').value = '';
    document.getElementById('scan_bin_input').focus();
}

function backToBatchList() {
    document.getElementById('batch-execution-container').classList.add('hidden');
    document.getElementById('batch-selection-container').classList.remove('hidden');
}

async function submitTaskConfirm(event) {
    event.preventDefault();

    const itemId = document.getElementById('current_batch_item_id').value;
    const scannedBin = document.getElementById('scan_bin_input').value.trim();
    const scannedLot = document.getElementById('scan_lot_input').value.trim();
    const scannedTote = document.getElementById('scan_tote_input').value.trim();
    const quantity = parseFloat(document.getElementById('scan_quantity_input').value);

    const submitBtn = document.getElementById('submit-confirm-btn');
    submitBtn.disabled = true;

    try {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const res = await fetch('/api/v1/scanner/batch-picking/confirm', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken || '',
            },
            body: JSON.stringify({
                batch_item_id: itemId,
                scanned_bin: scannedBin,
                scanned_lot: scannedLot,
                scanned_tote: scannedTote,
                quantity: quantity
            })
        });

        const data = await res.json();

        if (!res.ok || !data.success) {
            window.ScannerAudio.playError();
            window.showToast(data.message || 'Validation error during pick', 'error');
            submitBtn.disabled = false;
            return;
        }

        window.ScannerAudio.playSuccess();
        window.showToast(data.message, 'success');

        if (data.batch_completed) {
            window.showToast('Batch cart full and complete! Bring cart to packaging/staging.', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            // Load next task
            loadBatch(currentActiveBatchId);
        }
    } catch (e) {
        window.ScannerAudio.playError();
        window.showToast('Network error during confirmation', 'error');
    } finally {
        submitBtn.disabled = false;
    }
}
</script>
@endsection
