@extends('scanner.layout')

@section('title', 'Machine Staging')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Staging Demands from MES (Grayish, borderless) -->
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-2 mb-3">
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">MES Production Demands</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">FEFO Enforced</span>
        </div>

        @if($stagingRequests->isEmpty())
            <div class="text-center py-6 text-gray-400 text-xs">
                No active replenishment requests. All machines supplied!
            </div>
        @else
            <div class="space-y-2">
                @foreach($stagingRequests as $req)
                    <div class="rounded-xl p-3.5 flex items-center justify-between shadow-sm" style="background-color: #1e1e24; border: none;">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black text-emerald-300" style="background-color: rgba(16, 185, 129, 0.2);">
                                    {{ $req->target_machine_code }}
                                </span>
                                <span class="text-xs font-bold text-white font-mono">{{ $req->request_number }}</span>
                            </div>
                            <div class="text-[11px] text-gray-300 mt-1">
                                Order: <span class="font-mono text-sky-400">{{ $req->mes_production_order_id }}</span>
                            </div>
                        </div>
                        <button 
                            onclick="selectRequest('{{ $req->id }}', '{{ $req->request_number }}', '{{ $req->target_machine_code }}')"
                            class="px-3.5 py-2 rounded-xl bg-emerald-600 active:bg-emerald-700 text-white font-bold text-xs uppercase shadow"
                            style="border: none;"
                        >
                            Select
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Scan Verification Card (Visible when request selected) -->
    <div id="scan-pick-card" class="hidden rounded-2xl p-4 space-y-3 shadow-xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Pick Scan Verification</span>
            <span id="active-target-machine" class="text-xs font-black text-amber-400 font-mono"></span>
        </div>

        <form onsubmit="submitPickScan(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Scan Resin Lot Barcode</label>
                <input 
                    type="text" 
                    id="scan_lot_number" 
                    required 
                    data-scan-target="true"
                    placeholder="Scan Lot QR / Barcode"
                    class="laser-focus w-full h-13 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Dispatch Weight (KG)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        id="pick_weight_kg" 
                        required 
                        value="50.00"
                        class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-base font-bold text-emerald-400"
                        style="background-color: #27272a; border: none; outline: none;"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Destination Buffer</label>
                    <input 
                        type="text" 
                        id="target_buffer_bin" 
                        readonly 
                        class="w-full h-12 px-3.5 rounded-xl text-amber-300 font-mono text-sm"
                        style="background-color: #141417; border: none;"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                id="pick-submit-btn"
                class="w-full h-14 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-base tracking-wider uppercase shadow-lg flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
                style="border: none;"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Verify & Stage to Machine</span>
            </button>
        </form>
    </div>
</div>

<script>
    let activeRequestId = '';
    let activeMachineCode = '';

    function selectRequest(id, reqNumber, machineCode) {
        activeRequestId = id;
        activeMachineCode = machineCode;
        document.getElementById('active-target-machine').textContent = `${machineCode} (${reqNumber})`;
        document.getElementById('target_buffer_bin').value = `STG-${machineCode}`;
        document.getElementById('scan-pick-card').classList.remove('hidden');
        document.getElementById('scan_lot_number').focus();
        showToast(`Selected ${machineCode}. Scan allocated lot now!`, true);
    }

    async function submitPickScan(e) {
        e.preventDefault();
        const lotNumber = document.getElementById('scan_lot_number').value.trim();
        const weightKg = parseFloat(document.getElementById('pick_weight_kg').value);
        const destinationBin = document.getElementById('target_buffer_bin').value;

        if (!lotNumber) {
            showToast('Please scan a lot barcode', false);
            return;
        }

        const btn = document.getElementById('pick-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Checking FEFO & Blend Cap...</span>';

        try {
            const res = await fetch('/api/v1/scanner/transfer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    lot_number: lotNumber,
                    destination_bin_code: destinationBin,
                    quantity_kg: weightKg
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Verified! Lot ${lotNumber} staged to ${activeMachineCode}`, true);
                document.getElementById('scan_lot_number').value = '';
                document.getElementById('scan-pick-card').classList.add('hidden');
            } else {
                showToast(data.message || 'FEFO Violation / Lot not permitted!', false);
            }
        } catch(err) {
            showToast('Communication error with staging server', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Verify & Stage to Machine</span>';
        }
    }
</script>
@endsection
