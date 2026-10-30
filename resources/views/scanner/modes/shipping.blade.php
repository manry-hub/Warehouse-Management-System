@extends('scanner.layout')

@section('title', 'Truck Loading')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Delivery Orders (Grayish fill, borderless) -->
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-2 mb-3">
            <span class="text-xs font-bold text-rose-400 uppercase tracking-wider">Active Delivery Orders</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Scan-to-Truck</span>
        </div>

        @if($deliveryOrders->isEmpty())
            <div class="text-center py-6 text-gray-400 text-xs">
                No active delivery orders queued for loading.
            </div>
        @else
            <div class="space-y-2">
                @foreach($deliveryOrders as $do)
                    <div class="rounded-xl p-3.5 flex items-center justify-between shadow-sm" style="background-color: #1e1e24; border: none;">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-white font-mono">{{ $do->do_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold text-rose-300" style="background-color: rgba(244, 63, 94, 0.2);">
                                    {{ $do->status }}
                                </span>
                            </div>
                            <div class="text-[11px] text-gray-300 mt-1">
                                Customer: <span class="font-medium text-white">{{ $do->customer_name }}</span>
                            </div>
                            <div class="text-[10px] text-gray-400 font-mono mt-0.5">
                                Plate: {{ $do->vehicle_plate_number }} • Driver: {{ $do->driver_name }}
                            </div>
                        </div>
                        <button 
                            onclick="selectDeliveryOrder('{{ $do->id }}', '{{ $do->do_number }}', {{ $do->total_cartons ?? 50 }})"
                            class="min-h-[44px] min-w-[76px] px-4 py-2.5 rounded-xl bg-rose-600 active:bg-rose-700 text-white font-bold text-xs uppercase shadow active:scale-95 transition flex items-center justify-center"
                            style="border: none;"
                        >
                            Load
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Truck Loading Scanner Console (Grayish fill, borderless) -->
    <div id="truck-console-card" class="hidden rounded-2xl p-4 space-y-3 shadow-xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <div>
                <span class="text-xs font-bold text-rose-400 uppercase tracking-wider">Loading Manifest</span>
                <div id="active-do-number" class="text-sm font-mono text-white font-black"></div>
            </div>
            <div class="text-right">
                <span id="carton-counter" class="text-lg font-black font-mono text-amber-400">0 / 0</span>
                <div class="text-[10px] text-gray-400">Cartons Verified</div>
            </div>
        </div>

        <!-- Live Visual Loading Progress Bar -->
        <div class="w-full h-3 rounded-full overflow-hidden" style="background-color: #141417;">
            <div id="loading-progress-bar" class="h-full rounded-full transition-all duration-300" style="width: 0%; background-color: #10b981;"></div>
        </div>

        <form onsubmit="submitCartonScan(event)" class="space-y-3 pt-2">
            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Scan Finished Goods Carton Barcode</label>
                <input 
                    type="text" 
                    id="carton_barcode" 
                    required 
                    data-scan-target="true"
                    placeholder="Scan Carton GS1-128 / QR"
                    class="laser-focus w-full h-14 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <button 
                type="submit" 
                id="carton-verify-btn"
                class="w-full h-14 rounded-xl bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-bold text-base tracking-wider uppercase shadow-lg flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
                style="border: none;"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Verify Carton Loaded</span>
            </button>
        </form>

        <!-- Scanned Carton History Feed -->
        <div class="pt-2 space-y-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Recently Verified Cartons</span>
            <div id="scanned-cartons-list" class="space-y-1.5 max-h-36 overflow-y-auto"></div>
        </div>
    </div>
</div>

<script>
    let activeDoId = '';
    let totalExpectedCartons = 50;
    let verifiedCartonsCount = 0;

    function selectDeliveryOrder(id, doNumber, totalCartons) {
        activeDoId = id;
        totalExpectedCartons = totalCartons || 50;
        verifiedCartonsCount = 0;

        document.getElementById('active-do-number').textContent = doNumber;
        updateCounter();
        document.getElementById('scanned-cartons-list').innerHTML = '';
        document.getElementById('truck-console-card').classList.remove('hidden');
        document.getElementById('carton_barcode').focus();
        showToast(`Ready to load ${doNumber}. Scan cartons now!`, true);
    }

    function updateCounter() {
        document.getElementById('carton-counter').textContent = `${verifiedCartonsCount} / ${totalExpectedCartons}`;
        const pct = Math.min(100, Math.round((verifiedCartonsCount / totalExpectedCartons) * 100));
        document.getElementById('loading-progress-bar').style.width = `${pct}%`;
    }

    function submitCartonScan(e) {
        e.preventDefault();
        const input = document.getElementById('carton_barcode');
        const barcode = input.value.trim();

        if (!barcode) {
            showToast('Scan carton barcode', false);
            return;
        }

        // Simulate carton manifest validation
        verifiedCartonsCount++;
        updateCounter();

        // Prepend to verified list
        const list = document.getElementById('scanned-cartons-list');
        const item = document.createElement('div');
        item.className = 'rounded-xl p-2.5 flex items-center justify-between text-xs font-mono shadow-sm';
        item.style.backgroundColor = '#141417';
        item.style.border = 'none';
        item.innerHTML = `
            <span class="text-emerald-300 font-bold">#${verifiedCartonsCount} ${barcode}</span>
            <span class="text-[10px] text-gray-400">${new Date().toLocaleTimeString()}</span>
        `;
        list.prepend(item);

        showToast(`Carton verified (${verifiedCartonsCount}/${totalExpectedCartons})`, true);
        input.value = '';
        input.focus();

        if (verifiedCartonsCount >= totalExpectedCartons) {
            showToast('All cartons loaded successfully! Truck ready to seal.', true);
        }
    }
</script>
@endsection
