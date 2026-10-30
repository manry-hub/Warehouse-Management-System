@extends('scanner.layout')

@section('title', 'Hopper Staging')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Header Card -->
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <span class="text-xs font-bold text-cyan-400 uppercase tracking-wider">Mode 7: Hopper Feed & Recipe Verification</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Injection Line Staging</span>
        </div>
        <p class="text-[11px] text-gray-300">
            Verify resin compatibility with active work order recipe before pouring into machine hoppers, or return unconsumed partial bags.
        </p>
    </div>

    <!-- Mode Selector: Stage vs Return -->
    <!-- Mode Selector: Stage vs Return (Touch targets >= 48px) -->
    <div class="flex space-x-2">
        <button id="tab-stage" onclick="switchTab('stage')" class="flex-1 min-h-[48px] py-3 rounded-xl font-bold text-xs sm:text-sm uppercase tracking-wider transition shadow active:scale-95" style="background-color: #06b6d4; color: #000; border: none;">
            Stage to Hopper
        </button>
        <button id="tab-return" onclick="switchTab('return')" class="flex-1 min-h-[48px] py-3 rounded-xl font-bold text-xs sm:text-sm uppercase tracking-wider transition shadow text-gray-400 active:scale-95" style="background-color: #1e1e24; border: none;">
            Partial Return
        </button>
    </div>

    <!-- Section 1: Stage to Hopper Form -->
    <div id="section-stage" class="space-y-3">
        <div class="rounded-2xl p-4 sm:p-5 space-y-4 shadow-md" style="background-color: #1e1e24; border: none;">
            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1.5 uppercase tracking-wide">Target Injection Machine</label>
                <div class="grid grid-cols-3 gap-2.5">
                    @foreach(['IMM-01', 'IMM-02', 'IMM-03', 'IMM-04', 'IMM-05', 'IMM-06'] as $imm)
                        <button type="button" onclick="selectMachine('{{ $imm }}')" class="imm-btn min-h-[48px] py-3 rounded-xl text-sm font-mono font-bold text-gray-300 transition active:scale-95" style="background-color: #27272a; border: none;">
                            {{ $imm }}
                        </button>
                    @endforeach
                </div>
                <input type="hidden" id="selected-machine" value="IMM-01">
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1.5 uppercase tracking-wide">Raw Material Lot Barcode</label>
                <div class="flex space-x-2">
                    <input 
                        type="text" 
                        id="raw-lot-input" 
                        placeholder="Scan or enter lot (e.g. LOT-PP-...)" 
                        class="laser-focus flex-1 h-13 px-4 rounded-xl text-white font-mono text-base focus:outline-none"
                        style="background-color: #141417; border: 1px solid #3f3f46;"
                    >
                    <button type="button" onclick="verifyRecipe()" class="min-h-[52px] px-5 rounded-xl font-bold text-xs sm:text-sm text-white uppercase shadow active:scale-95 transition" style="background-color: #0891b2; border: none;">
                        Verify
                    </button>
                </div>
            </div>

            <!-- Verification Feedback Banner -->
            <div id="verification-banner" class="hidden p-3.5 rounded-xl text-xs sm:text-sm space-y-1"></div>

            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1.5 uppercase tracking-wide">Quantity to Stage (KG)</label>
                <input 
                    type="number" 
                    id="stage-qty-input" 
                    step="0.01" 
                    value="25.00" 
                    class="laser-focus w-full h-13 px-4 rounded-xl text-white font-mono text-base font-bold text-cyan-400 focus:outline-none"
                    style="background-color: #141417; border: 1px solid #3f3f46;"
                >
            </div>

            <button type="button" onclick="submitHopperStage()" id="submit-stage-btn" class="w-full min-h-[54px] py-3.5 rounded-xl font-black text-sm uppercase tracking-wider text-black transition shadow-lg active:scale-[0.98]" style="background-color: #06b6d4; border: none;">
                Authorize & Stage to Hopper
            </button>
        </div>
    </div>

    <!-- Section 2: Partial Return Form -->
    <div id="section-return" class="hidden space-y-3">
        <div class="rounded-2xl p-4 sm:p-5 space-y-4 shadow-md" style="background-color: #1e1e24; border: none;">
            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1.5 uppercase tracking-wide">Hopper Transaction Code</label>
                <input 
                    type="text" 
                    id="return-tx-code" 
                    placeholder="Scan or enter HOP-... code" 
                    class="laser-focus w-full h-13 px-4 rounded-xl text-white font-mono text-base focus:outline-none"
                    style="background-color: #141417; border: 1px solid #3f3f46;"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1.5 uppercase tracking-wide">Actual Weighed Returned Weight (KG)</label>
                <input 
                    type="number" 
                    id="return-weight-input" 
                    step="0.01" 
                    placeholder="e.g. 14.50" 
                    class="laser-focus w-full h-13 px-4 rounded-xl text-white font-mono text-base font-bold text-cyan-400 focus:outline-none"
                    style="background-color: #141417; border: 1px solid #3f3f46;"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-300 mb-1.5 uppercase tracking-wide">Target Raw Material Bin (Optional)</label>
                <input 
                    type="text" 
                    id="return-bin-code" 
                    placeholder="Leave empty for auto-assign in ZONE-RM" 
                    class="laser-focus w-full h-13 px-4 rounded-xl text-white font-mono text-base focus:outline-none"
                    style="background-color: #141417; border: 1px solid #3f3f46;"
                >
            </div>

            <button type="button" onclick="submitHopperReturn()" id="submit-return-btn" class="w-full min-h-[54px] py-3.5 rounded-xl font-black text-sm uppercase tracking-wider text-black transition shadow-lg active:scale-[0.98]" style="background-color: #10b981; border: none;">
                Deposit Return & Generate Label
            </button>
        </div>
    </div>
</div>

<script>
let currentTab = 'stage';

function switchTab(tab) {
    currentTab = tab;
    const stageBtn = document.getElementById('tab-stage');
    const returnBtn = document.getElementById('tab-return');
    const stageSec = document.getElementById('section-stage');
    const returnSec = document.getElementById('section-return');

    if (tab === 'stage') {
        stageBtn.style.backgroundColor = '#06b6d4';
        stageBtn.style.color = '#000';
        returnBtn.style.backgroundColor = '#1e1e24';
        returnBtn.style.color = '#9ca3af';
        stageSec.classList.remove('hidden');
        returnSec.classList.add('hidden');
    } else {
        returnBtn.style.backgroundColor = '#10b981';
        returnBtn.style.color = '#000';
        stageBtn.style.backgroundColor = '#1e1e24';
        stageBtn.style.color = '#9ca3af';
        stageSec.classList.add('hidden');
        returnSec.classList.remove('hidden');
    }
}

function selectMachine(code) {
    document.getElementById('selected-machine').value = code;
    document.querySelectorAll('.imm-btn').forEach(btn => {
        if (btn.innerText.trim() === code) {
            btn.style.backgroundColor = '#06b6d4';
            btn.style.color = '#000';
        } else {
            btn.style.backgroundColor = '#27272a';
            btn.style.color = '#d1d5db';
        }
    });
}
selectMachine('IMM-01');

async function verifyRecipe() {
    const machine = document.getElementById('selected-machine').value;
    const lot = document.getElementById('raw-lot-input').value.trim();
    const banner = document.getElementById('verification-banner');

    if (!lot) {
        alert('Please scan or enter a lot number.');
        return;
    }

    try {
        const response = await fetch('/api/v1/scanner/hopper/verify', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ machineCode: machine, lotNumber: lot }),
        });

        const data = await response.json();
        banner.classList.remove('hidden');

        if (response.ok && data.compatible) {
            window.ScannerAudio?.playSuccess();
            banner.style.backgroundColor = 'rgba(16, 185, 129, 0.2)';
            banner.style.border = '1px solid #10b981';
            banner.innerHTML = `<span class="font-bold text-emerald-400">✓ VERIFIED COMPATIBLE</span><br><span class="text-gray-300">Material: ${data.material_code} (${data.material_name})<br>Order: ${data.order_number}<br>Stock: ${data.quantity_on_hand} kg</span>`;
        } else {
            window.ScannerAudio?.playError();
            banner.style.backgroundColor = 'rgba(239, 68, 68, 0.2)';
            banner.style.border = '1px solid #ef4444';
            banner.innerHTML = `<span class="font-bold text-rose-400">✗ RECIPE REJECTED</span><br><span class="text-gray-300">${data.message}</span>`;
        }
    } catch (e) {
        alert('Connection error: ' + e.message);
    }
}

async function submitHopperStage() {
    const machine = document.getElementById('selected-machine').value;
    const lot = document.getElementById('raw-lot-input').value.trim();
    const qty = parseFloat(document.getElementById('stage-qty-input').value);

    if (!lot || isNaN(qty) || qty <= 0) {
        alert('Enter valid lot and quantity.');
        return;
    }

    try {
        const response = await fetch('/api/v1/scanner/hopper/stage', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ machineCode: machine, lotNumber: lot, quantityKg: qty }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            window.ScannerAudio?.playSuccess();
            alert(`SUCCESS: Staged to machine ${machine}!\nTransaction: ${data.data.transactionCode}`);
            document.getElementById('raw-lot-input').value = '';
            document.getElementById('verification-banner').classList.add('hidden');
        } else {
            window.ScannerAudio?.playError();
            alert('STAGING FAILED: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}

async function submitHopperReturn() {
    const tx = document.getElementById('return-tx-code').value.trim();
    const weight = parseFloat(document.getElementById('return-weight-input').value);
    const bin = document.getElementById('return-bin-code').value.trim();

    if (!tx || isNaN(weight) || weight <= 0) {
        alert('Enter transaction code and valid weight.');
        return;
    }

    try {
        const response = await fetch('/api/v1/scanner/hopper/return', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({ transactionCode: tx, returnedWeightKg: weight, targetBinCode: bin || null }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            window.ScannerAudio?.playSuccess();
            alert(`RETURN COMPLETED!\nReturned: ${data.data.returned_weight_kg} kg to bin ${data.data.target_bin}\nReturn Label: ${data.data.return_barcode}`);
            document.getElementById('return-tx-code').value = '';
            document.getElementById('return-weight-input').value = '';
        } else {
            window.ScannerAudio?.playError();
            alert('RETURN FAILED: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Error: ' + e.message);
    }
}
</script>
@endsection
