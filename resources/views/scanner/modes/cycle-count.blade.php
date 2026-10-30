@extends('scanner.layout')

@section('title', 'Blind Cycle Count')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Cycle Count Orders -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shadow-md">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700/80 mb-3">
            <span class="text-xs font-bold text-purple-400 uppercase tracking-wider">Active Audit Orders</span>
            <span class="text-[10px] text-slate-400 bg-slate-900 px-2 py-0.5 rounded">Blind Secrecy</span>
        </div>

        @if($countOrders->isEmpty())
            <div class="text-center py-6 text-slate-400 text-xs">
                No active cycle count orders assigned.
            </div>
        @else
            <div class="space-y-2">
                @foreach($countOrders as $order)
                    <div class="bg-slate-900 border border-slate-700/80 rounded-xl p-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-purple-500/20 text-purple-300 border border-purple-500/30">
                                    {{ $order->count_type }}
                                </span>
                                <span class="text-xs font-bold text-white font-mono">{{ $order->count_number }}</span>
                            </div>
                            <div class="text-[11px] text-slate-400 mt-1">
                                Scope: {{ $order->zone ? $order->zone->name : 'Whole Warehouse' }}
                            </div>
                        </div>
                        <button 
                            onclick="loadCountSheet('{{ $order->id }}', '{{ $order->count_number }}')"
                            class="px-3 py-2 rounded-lg bg-purple-600 active:bg-purple-700 text-white font-bold text-xs uppercase"
                        >
                            Open Sheet
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Blind Count Execution Form -->
    <div id="count-execution-card" class="hidden bg-slate-800 border-2 border-purple-500/60 rounded-xl p-4 space-y-3 shadow-xl">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700">
            <div>
                <span class="text-xs font-bold text-purple-300 uppercase tracking-wider">Physical Count Entry</span>
                <div id="active-order-title" class="text-xs font-mono text-white font-bold"></div>
            </div>
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                Book Stock Hidden
            </span>
        </div>

        <form onsubmit="submitPhysicalCount(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Scan Bin Barcode</label>
                <input 
                    type="text" 
                    id="scan_bin_code" 
                    required 
                    data-scan-target="true"
                    placeholder="Scan Bin (e.g. RM-A-01-11)"
                    class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base uppercase focus:outline-none focus:border-purple-500"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Scan Lot Barcode</label>
                <input 
                    type="text" 
                    id="scan_cc_lot_number" 
                    required 
                    placeholder="Scan Material Lot Barcode"
                    class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base uppercase focus:outline-none focus:border-purple-500"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Observed Count (KG / PCS)</label>
                <input 
                    type="number" 
                    step="0.01" 
                    id="physical_count_qty" 
                    required 
                    placeholder="Physical bags/weight counted"
                    class="laser-focus w-full h-13 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-xl font-bold text-purple-300 focus:outline-none focus:border-purple-500"
                >
            </div>

            <button 
                type="submit" 
                id="submit-count-btn"
                class="w-full h-14 rounded-xl bg-purple-600 hover:bg-purple-500 active:bg-purple-700 text-white font-bold text-base tracking-wider uppercase shadow-lg shadow-purple-600/30 flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
            >
                <span>Record Physical Count</span>
            </button>
        </form>

        <!-- Blind Items Checklist -->
        <div class="pt-3 border-t border-slate-700">
            <h4 class="text-xs font-bold text-slate-300 uppercase mb-2">Checklist Items</h4>
            <div id="blind-items-list" class="space-y-1.5 max-h-48 overflow-y-auto"></div>
        </div>
    </div>
</div>

<script>
    let activeCountOrderId = '';

    async function loadCountSheet(id, num) {
        activeCountOrderId = id;
        document.getElementById('active-order-title').textContent = num;
        document.getElementById('count-execution-card').classList.remove('hidden');

        try {
            const res = await fetch(`/api/v1/scanner/cycle-counts/${id}/sheet`);
            const data = await res.json();

            if (res.ok && data.success) {
                renderChecklist(data.data.items);
                document.getElementById('scan_bin_code').focus();
                showToast(`Loaded sheet for ${num}`, true);
            }
        } catch (e) {
            showToast('Failed to load count sheet', false);
        }
    }

    function renderChecklist(items) {
        const container = document.getElementById('blind-items-list');
        container.innerHTML = '';

        items.forEach(item => {
            const isDone = item.status === 'COUNTED';
            const row = document.createElement('div');
            row.className = isDone 
                ? 'p-2 rounded-lg bg-emerald-950/40 border border-emerald-500/30 flex items-center justify-between text-xs'
                : 'p-2 rounded-lg bg-slate-900 border border-slate-700/80 flex items-center justify-between text-xs';

            row.innerHTML = `
                <div>
                    <span class="font-bold text-white font-mono">${item.bin_code}</span>
                    <span class="text-slate-400">• ${item.item_sku}</span>
                    <div class="text-[10px] text-slate-400 font-mono">${item.lot_number}</div>
                </div>
                <div>
                    <span class="px-2 py-0.5 rounded text-[10px] font-bold ${isDone ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-700 text-slate-300'}">
                        ${item.status}
                    </span>
                </div>
            `;
            container.appendChild(row);
        });
    }

    async function submitPhysicalCount(e) {
        e.preventDefault();
        const btn = document.getElementById('submit-count-btn');
        btn.disabled = true;

        try {
            const res = await fetch(`/api/v1/scanner/cycle-counts/${activeCountOrderId}/count`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    bin_code: document.getElementById('scan_bin_code').value.trim(),
                    lot_number: document.getElementById('scan_cc_lot_number').value.trim(),
                    counted_quantity: parseFloat(document.getElementById('physical_count_qty').value)
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast('Count Recorded Blindly!', true);
                document.getElementById('scan_cc_lot_number').value = '';
                document.getElementById('physical_count_qty').value = '';
                loadCountSheet(activeCountOrderId, document.getElementById('active-order-title').textContent);
            } else {
                showToast(data.message || 'Count submission error', false);
            }
        } catch (err) {
            showToast('Network error submitting count', false);
        } finally {
            btn.disabled = false;
        }
    }
</script>
@endsection
