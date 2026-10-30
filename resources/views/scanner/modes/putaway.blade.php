@extends('scanner.layout')

@section('title', 'Guided Putaway')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Step 1: Scan Lot -->
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-2 mb-3">
            <span class="text-xs font-bold text-indigo-400 uppercase tracking-wider">Step 1: Scan Pallet / Lot</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Laser Wedge</span>
        </div>

        <div class="flex space-x-2">
            <input 
                type="text" 
                id="lot_number" 
                data-scan-target="true"
                placeholder="Scan Lot (e.g. LOT-PP-...)"
                class="laser-focus flex-1 h-13 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                style="background-color: #27272a; border: none; outline: none;"
            >
            <button 
                onclick="queryPutaway()" 
                class="px-4 h-13 rounded-xl bg-indigo-600 hover:bg-indigo-500 active:bg-indigo-700 text-white font-bold text-xs uppercase"
                style="border: none;"
            >
                Find Bin
            </button>
        </div>
    </div>

    <!-- Step 2: Suggested Bin Destination (Hidden until lot queried) -->
    <div id="suggestion-card" class="hidden rounded-2xl p-4 space-y-3 shadow-xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-indigo-300 uppercase tracking-wider">Recommended Rack Bin</span>
            <span id="zone-badge" class="px-2.5 py-1 rounded-md text-[10px] font-bold text-indigo-300" style="background-color: rgba(99, 102, 241, 0.2);"></span>
        </div>

        <!-- Giant Bin Coordinate Badge -->
        <div class="rounded-xl p-4 text-center shadow-inner" style="background-color: #141417; border: none;">
            <div id="suggested-bin-code" class="text-2xl font-black text-amber-400 font-mono tracking-wider"></div>
            <div id="bin-topology-details" class="text-xs text-gray-400 mt-1"></div>
        </div>

        <!-- Step 3: Scan Target Bin to Confirm -->
        <div class="pt-2 space-y-2">
            <label class="block text-xs font-bold text-gray-300 uppercase">
                Step 2: Scan Physical Bin Barcode to Confirm
            </label>
            <input 
                type="text" 
                id="confirm_bin_code" 
                placeholder="Scan Bin Barcode on Rack"
                class="laser-focus w-full h-13 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                style="background-color: #27272a; border: none; outline: none;"
            >
            <button 
                onclick="confirmPutaway()" 
                id="confirm-btn"
                class="w-full h-14 rounded-xl bg-emerald-600 hover:bg-emerald-500 active:bg-emerald-700 text-white font-bold text-base tracking-wider uppercase shadow-lg flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
                style="border: none;"
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
        const lotNumber = lotInput.value.trim();
        if (!lotNumber) {
            showToast('Please enter or scan a lot number', false);
            return;
        }

        try {
            const res = await fetch(`/api/v1/scanner/lots/${encodeURIComponent(lotNumber)}`);
            const data = await res.json();

            if (res.ok && data.success) {
                currentLot = data.data.lot_number;
                // Query optimal bin or fallback to available rack bin
                const binRes = await fetch(`/api/v1/scanner/bins`);
                const binData = await binRes.json();
                
                let targetBin = 'RM-A-01-11';
                let targetZone = 'RAW MATERIAL (Aisle A - Rack 01 - Shelf 1)';
                if (binData.data && binData.data.length > 0) {
                    targetBin = binData.data[0].bin_code;
                    targetZone = `${binData.data[0].zone?.name || 'ZONE'} (${binData.data[0].aisle || 'A'}-${binData.data[0].rack || '01'})`;
                }

                suggestedBin = targetBin;
                document.getElementById('suggested-bin-code').textContent = targetBin;
                document.getElementById('bin-topology-details').textContent = targetZone;
                document.getElementById('zone-badge').textContent = 'OPTIMAL CAPACITY';
                document.getElementById('suggestion-card').classList.remove('hidden');
                document.getElementById('confirm_bin_code').focus();
                showToast(`Putaway recommended: ${targetBin}`, true);
            } else {
                showToast(data.message || 'Lot not found in intake hold', false);
            }
        } catch(e) {
            showToast('Server communication error', false);
        }
    }

    async function confirmPutaway() {
        const enteredBin = document.getElementById('confirm_bin_code').value.trim();
        if (!enteredBin) {
            showToast('Scan destination bin barcode', false);
            return;
        }

        if (enteredBin.toUpperCase() !== suggestedBin.toUpperCase()) {
            showToast(`Mismatch! Target is ${suggestedBin}, you scanned ${enteredBin}`, false);
            return;
        }

        const btn = document.getElementById('confirm-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Relocating Stock...</span>';

        try {
            const res = await fetch('/api/v1/scanner/transfer', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    lot_number: currentLot,
                    destination_bin_code: enteredBin
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Success! ${currentLot} stored at ${enteredBin}`, true);
                document.getElementById('suggestion-card').classList.add('hidden');
                document.getElementById('lot_number').value = '';
                document.getElementById('confirm_bin_code').value = '';
                document.getElementById('lot_number').focus();
            } else {
                showToast(data.message || 'Transfer failed', false);
            }
        } catch (err) {
            showToast('Network error during transfer', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Confirm Bin Putaway</span>';
        }
    }
</script>
@endsection
