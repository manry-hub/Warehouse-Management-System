@extends('scanner.layout')

@section('title', 'Truck Loading')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Delivery Orders -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shadow-md">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700/80 mb-3">
            <span class="text-xs font-bold text-rose-400 uppercase tracking-wider">Active Delivery Orders</span>
            <span class="text-[10px] text-slate-400 bg-slate-900 px-2 py-0.5 rounded">Scan-to-Truck</span>
        </div>

        @if($deliveryOrders->isEmpty())
            <div class="text-center py-6 text-slate-400 text-xs">
                No active delivery orders queued for loading.
            </div>
        @else
            <div class="space-y-2">
                @foreach($deliveryOrders as $do)
                    <div class="bg-slate-900 border border-slate-700/80 rounded-xl p-3 flex items-center justify-between">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-white font-mono">{{ $do->do_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                                    {{ $do->status }}
                                </span>
                            </div>
                            <div class="text-[11px] text-slate-300 mt-1">
                                Customer: <span class="font-medium text-slate-200">{{ $do->customer_name }}</span>
                            </div>
                            <div class="text-[10px] text-slate-400 font-mono mt-0.5">
                                Plate: {{ $do->vehicle_plate_number }} • Driver: {{ $do->driver_name }}
                            </div>
                        </div>
                        <button 
                            onclick="selectDeliveryOrder('{{ $do->id }}', '{{ $do->do_number }}', {{ $do->total_cartons ?? 50 }})"
                            class="px-3 py-2 rounded-lg bg-rose-600 active:bg-rose-700 text-white font-bold text-xs uppercase shadow"
                        >
                            Load
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Truck Loading Scanner Console -->
    <div id="truck-console-card" class="hidden bg-slate-800 border-2 border-rose-500/60 rounded-xl p-4 space-y-3 shadow-xl">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700">
            <div>
                <span class="text-xs font-bold text-rose-400 uppercase tracking-wider">Loading Manifest</span>
                <div id="active-do-number" class="text-sm font-mono text-white font-black"></div>
            </div>
            <div class="text-right">
                <span id="carton-counter" class="text-lg font-black font-mono text-amber-400">0 / 0</span>
                <div class="text-[10px] text-slate-400">Cartons Verified</div>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="w-full bg-slate-900 rounded-full h-3 overflow-hidden border border-slate-700">
            <div id="loading-progress-bar" class="bg-gradient-to-r from-amber-500 to-emerald-500 h-3 rounded-full transition-all duration-300 w-0"></div>
        </div>

        <!-- Barcode Scan Input -->
        <form onsubmit="scanCarton(event)" class="space-y-3 pt-2">
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">
                    Scan Carton / Pallet Barcode
                </label>
                <input 
                    type="text" 
                    id="carton_barcode" 
                    required 
                    data-scan-target="true"
                    placeholder="Scan Carton UID..."
                    class="laser-focus w-full h-14 px-3.5 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-lg font-bold uppercase focus:outline-none focus:border-rose-500"
                >
            </div>

            <button 
                type="submit" 
                id="verify-scan-btn"
                class="w-full h-14 rounded-xl bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-bold text-base tracking-wider uppercase shadow-lg shadow-rose-600/30 flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Verify & Load Carton</span>
            </button>
        </form>
    </div>
</div>

<script>
    let activeDoId = '';
    let totalCartonsExpected = 0;
    let cartonsLoaded = 0;

    function selectDeliveryOrder(id, num, total) {
        activeDoId = id;
        totalCartonsExpected = total || 25;
        cartonsLoaded = 0;
        document.getElementById('active-do-number').textContent = num;
        updateProgress();
        document.getElementById('truck-console-card').classList.remove('hidden');
        document.getElementById('carton_barcode').focus();
        showToast(`Loading truck for ${num}`, true);
    }

    function updateProgress() {
        document.getElementById('carton-counter').textContent = `${cartonsLoaded} / ${totalCartonsExpected}`;
        const pct = Math.min(100, Math.round((cartonsLoaded / totalCartonsExpected) * 100));
        document.getElementById('loading-progress-bar').style.width = `${pct}%`;
    }

    async function scanCarton(e) {
        e.preventDefault();
        const input = document.getElementById('carton_barcode');
        const barcode = input.value.trim();
        if (!barcode) return;

        try {
            const res = await fetch('/api/v1/scanner/shipping/scan-truck', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    delivery_order_id: activeDoId,
                    carton_barcode: barcode
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                cartonsLoaded++;
                updateProgress();
                showToast(`Carton Verified! [${cartonsLoaded}/${totalCartonsExpected}]`, true);
                input.value = '';
                input.focus();
            } else {
                showToast(data.message || 'Carton Mismatch! Reject from truck.', false);
            }
        } catch (err) {
            showToast('Scan verification error', false);
        }
    }
</script>
@endsection
