@extends('scanner.layout')

@section('title', 'Machine Staging')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Staging Demands from MES -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shadow-md">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700/80 mb-3">
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">MES Production Demands</span>
            <span class="text-[10px] text-slate-400 bg-slate-900 px-2 py-0.5 rounded">FEFO Enforced</span>
        </div>

        @if($stagingRequests->isEmpty())
            <div class="text-center py-6 text-slate-400 text-xs">
                No active replenishment requests. All machines supplied!
            </div>
        @else
            <div class="space-y-2">
                @foreach($stagingRequests as $req)
                    <div class="bg-slate-900 border border-slate-700/80 rounded-xl p-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">
                                    {{ $req->target_machine_code }}
                                </span>
                                <span class="text-xs font-bold text-white font-mono">{{ $req->request_number }}</span>
                            </div>
                            <div class="text-[11px] text-slate-300 mt-1">
                                Order: <span class="font-mono text-sky-400">{{ $req->mes_production_order_id }}</span>
                            </div>
                        </div>
                        <button 
                            onclick="selectRequest('{{ $req->id }}', '{{ $req->request_number }}', '{{ $req->target_machine_code }}')"
                            class="px-3 py-2 rounded-lg bg-emerald-600 active:bg-emerald-700 text-white font-bold text-xs uppercase shadow"
                        >
                            Select
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Scan Verification Card (Visible when request selected) -->
    <div id="scan-pick-card" class="hidden bg-slate-800 border-2 border-emerald-500/60 rounded-xl p-4 space-y-3 shadow-xl">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700">
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Pick Scan Verification</span>
            <span id="active-target-machine" class="text-xs font-black text-amber-400 font-mono"></span>
        </div>

        <form onsubmit="submitPickScan(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Scan Resin Lot Barcode</label>
                <input 
                    type="text" 
                    id="scan_lot_number" 
                    required 
                    data-scan-target="true"
                    placeholder="Scan Bag Barcode (LOT-...)"
                    class="laser-focus w-full h-13 px-3.5 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base uppercase focus:outline-none focus:border-emerald-500"
                >
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Source Rack Bin</label>
                    <input 
                        type="text" 
                        id="source_bin_code" 
                        required 
                        value="RM-A-01-11"
                        placeholder="RM-A-01-11"
                        class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-sm uppercase focus:outline-none focus:border-emerald-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Staging Buffer Bin</label>
                    <input 
                        type="text" 
                        id="staging_bin_code" 
                        required 
                        value="STG-BUF-01"
                        placeholder="STG-BUF-01"
                        class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-sm uppercase focus:outline-none focus:border-emerald-500"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Quantity (KG)</label>
                <input 
                    type="number" 
                    step="0.01" 
                    id="pick_quantity_kg" 
                    required 
                    value="25.00"
                    class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base font-bold text-emerald-400 focus:outline-none focus:border-emerald-500"
                >
            </div>

            <button 
                type="submit" 
                id="pick-submit-btn"
                class="w-full h-14 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-base tracking-wider uppercase shadow-lg shadow-emerald-600/30 flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
            >
                <span>Confirm Pick & Dispatch to MES</span>
            </button>
        </form>
    </div>
</div>

<script>
    let activeRequestId = '';

    function selectRequest(id, reqNum, machine) {
        activeRequestId = id;
        document.getElementById('active-target-machine').textContent = `${machine} (${reqNum})`;
        document.getElementById('scan-pick-card').classList.remove('hidden');
        document.getElementById('scan_lot_number').focus();
        showToast(`Replenishment target: ${machine}`, true);
    }

    async function submitPickScan(e) {
        e.preventDefault();
        const btn = document.getElementById('pick-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Verifying FEFO & Syncing MES...</span>';

        try {
            const res = await fetch('/api/v1/scanner/picking/scan', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    staging_request_id: activeRequestId,
                    lot_number: document.getElementById('scan_lot_number').value.trim(),
                    source_bin_code: document.getElementById('source_bin_code').value.trim(),
                    staging_bin_code: document.getElementById('staging_bin_code').value.trim(),
                    quantity_kg: parseFloat(document.getElementById('pick_quantity_kg').value)
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Staging Dispatched! Event queued for MES.`, true);
                document.getElementById('scan_lot_number').value = '';
                document.getElementById('scan_lot_number').focus();
            } else {
                showToast(data.message || 'Pick scan failed validation', false);
            }
        } catch (err) {
            showToast('Network error during staging scan', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Confirm Pick & Dispatch to MES</span>';
        }
    }
</script>
@endsection
