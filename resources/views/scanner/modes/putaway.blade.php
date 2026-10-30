@extends('scanner.layout')

@section('title', 'Guided Putaway')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Step 1: Scan Lot -->
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shadow-md">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700/80 mb-3">
            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">Step 1: Scan Pallet / Lot</span>
            <span class="text-[10px] text-slate-400 bg-slate-900 px-2 py-0.5 rounded">Laser Wedge</span>
        </div>

        <div class="flex space-x-2">
            <input 
                type="text" 
                id="lot_number" 
                data-scan-target="true"
                placeholder="Scan Lot (e.g. LOT-PP-...)"
                class="laser-focus flex-1 h-13 px-3.5 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base uppercase focus:outline-none focus:border-indigo-500"
            >
            <button 
                onclick="queryPutaway()" 
                class="px-4 h-13 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-bold text-xs uppercase"
            >
                Find Bin
            </button>
        </div>
    </div>

    <!-- Step 2: Suggested Bin Destination (Hidden until lot queried) -->
    <div id="suggestion-card" class="hidden bg-slate-800 border-2 border-indigo-500/60 rounded-xl p-4 space-y-3 shadow-lg">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-indigo-300 uppercase tracking-wider">Recommended Rack Bin</span>
            <span id="zone-badge" class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30"></span>
        </div>

        <!-- Giant Bin Coordinate Badge -->
        <div class="bg-slate-900 border border-slate-700 rounded-xl p-4 text-center">
            <div id="suggested-bin-code" class="text-2xl font-black text-amber-400 font-mono tracking-wider"></div>
            <div id="bin-topology-details" class="text-xs text-slate-400 mt-1"></div>
        </div>

        <!-- Step 3: Scan Target Bin to Confirm -->
        <div class="pt-2 border-t border-slate-700 space-y-2">
            <label class="block text-xs font-bold text-slate-300 uppercase">
                Step 2: Scan Physical Bin Barcode to Confirm
            </label>
            <input 
                type="text" 
                id="confirm_bin_code" 
                placeholder="Scan Bin Barcode on Rack"
                class="laser-focus w-full h-13 px-3.5 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base uppercase focus:outline-none focus:border-emerald-500"
            >
            <button 
                onclick="confirmPutaway()" 
                id="confirm-btn"
                class="w-full h-14 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-base tracking-wider uppercase shadow-lg shadow-emerald-600/30 flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
            >
                <span>Confirm Bin Putaway</span>
            </button>
        </div>
    </div>
</div>

<script>
    let currentLot = '';
    let suggestedBin = '';

    async function queryPutaway() {
        const lotInput = document.getElementById('lot_number');
        const lot = lotInput.value.trim();
        if (!lot) {
            showToast('Scan or enter a lot number first.', false);
            return;
        }

        try {
            const res = await fetch(`/api/v1/scanner/putaway/suggest?lot_number=${encodeURIComponent(lot)}`, {
                headers: { 'Accept': 'application/json' }
            });
            const data = await res.json();

            if (res.ok && data.success) {
                currentLot = lot;
                suggestedBin = data.data.suggested_bin.bin_code;
                document.getElementById('suggested-bin-code').textContent = suggestedBin;
                document.getElementById('bin-topology-details').textContent = 
                    `Aisle ${data.data.suggested_bin.aisle} • Rack ${data.data.suggested_bin.rack} • Shelf ${data.data.suggested_bin.shelf}`;
                document.getElementById('zone-badge').textContent = data.data.suggested_bin.zone;
                document.getElementById('suggestion-card').classList.remove('hidden');
                document.getElementById('confirm_bin_code').focus();
                showToast(`Optimal Bin: ${suggestedBin}`, true);
            } else {
                showToast(data.message || 'No available bin found', false);
            }
        } catch (err) {
            showToast('Network error querying putaway', false);
        }
    }

    async function confirmPutaway() {
        const binScanned = document.getElementById('confirm_bin_code').value.trim();
        if (!binScanned) {
            showToast('Please scan the physical bin barcode.', false);
            return;
        }

        try {
            const res = await fetch('/api/v1/scanner/putaway/confirm', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    lot_number: currentLot,
                    destination_bin_code: binScanned
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Putaway Confirmed to ${binScanned}!`, true);
                document.getElementById('suggestion-card').classList.add('hidden');
                document.getElementById('lot_number').value = '';
                document.getElementById('confirm_bin_code').value = '';
                document.getElementById('lot_number').focus();
            } else {
                showToast(data.message || 'Putaway mismatch or error', false);
            }
        } catch (err) {
            showToast('Network error during putaway', false);
        }
    }
</script>
@endsection
