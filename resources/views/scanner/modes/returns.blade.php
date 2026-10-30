@extends('scanner.layout')

@section('title', 'Customer Returns Intake')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- RMA Selection and Summary -->
    <div class="rounded-2xl p-4 shadow-md bg-[#18181b] border border-white/5">
        <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-800">
            <span class="text-xs font-bold text-rose-400 uppercase tracking-wider font-mono">Dock RMA Intake</span>
            <span class="text-[10px] text-zinc-400 px-2.5 py-1 rounded-md bg-zinc-800 font-mono">Quarantine Dock</span>
        </div>

        @if($pendingRmas->isEmpty())
            <div class="text-center py-8 text-zinc-500 text-xs">
                No pending customer RMAs awaiting receipt. All returns are checked in!
            </div>
        @else
            <div class="space-y-3">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Select Pending RMA</label>
                    <select id="selected-rma-id" onchange="onRmaChange()" class="laser-focus w-full h-12 px-3.5 rounded-xl text-white text-sm bg-zinc-800 border-none outline-none font-mono">
                        @foreach($pendingRmas as $rma)
                            <option value="{{ $rma->id }}" 
                                    data-customer="{{ $rma->customer_name }}"
                                    data-defect="{{ $rma->defect_reason }}"
                                    data-total="{{ $rma->total_cartons }}"
                                    data-received="{{ $rma->received_cartons }}"
                                    data-rma-number="{{ $rma->rma_number }}">
                                {{ $rma->rma_number }} — {{ $rma->customer_name }} ({{ $rma->received_cartons }}/{{ $rma->total_cartons }} Cartons)
                            </option>
                        @endforeach
                    </select>
                </div>

                <!-- Active RMA Info Card -->
                <div id="rma-info-card" class="rounded-xl p-3 bg-[#1e1e24] border border-white/5">
                    <div class="flex items-center justify-between">
                        <span id="card-rma-number" class="text-xs font-mono font-bold text-rose-400">RMA-XXXX</span>
                        <span id="card-rma-progress" class="text-[11px] font-mono px-2 py-0.5 rounded bg-zinc-800 text-amber-300 font-bold">0 / 0</span>
                    </div>
                    <div class="text-xs text-white font-medium mt-1">
                        Customer: <span id="card-rma-customer" class="text-zinc-300 font-bold">Customer Name</span>
                    </div>
                    <div class="text-[11px] text-zinc-400 font-mono mt-0.5">
                        Defect: <span id="card-rma-defect" class="text-rose-300">Defect Reason</span>
                    </div>
                </div>

                <!-- Carton Scan Form -->
                <form id="returns-receive-form" onsubmit="submitCartonIntake(event)" class="space-y-3 pt-2">
                    <div>
                        <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Quarantine Destination Bin</label>
                        <select id="quarantine_bin_code" required class="laser-focus w-full h-12 px-3.5 rounded-xl text-white text-sm bg-zinc-800 border-none outline-none font-mono">
                            @foreach($quarantineBins as $bin)
                                <option value="{{ $bin->bin_code }}" {{ str_contains($bin->bin_code, 'QUARANTINE') ? 'selected' : '' }}>
                                    {{ $bin->bin_code }} ({{ $bin->warehouse->name ?? 'WH' }})
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Scan Returned Carton Barcode</label>
                        <div class="relative">
                            <input 
                                type="text" 
                                id="carton_barcode" 
                                required 
                                autofocus
                                data-scan-target="true"
                                placeholder="Scan or type CART-..."
                                class="laser-focus w-full h-12 pl-3.5 pr-12 rounded-xl text-white font-mono text-base uppercase bg-zinc-800 border-none outline-none"
                            >
                            <button 
                                type="button" 
                                onclick="focusCartonInput()" 
                                class="absolute right-1.5 top-1.5 bottom-1.5 w-10 flex items-center justify-center rounded-lg bg-zinc-700 text-zinc-300 active:scale-95 transition"
                                title="Laser Aim"
                            >
                                <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m0 14v1m8-8h-1M5 12H4m15.364-6.364l-.707.707M6.343 17.657l-.707.707m12.728 0l-.707-.707M6.343 6.343l-.707-.707"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Return Quantity</label>
                        <input 
                            type="number" 
                            step="0.001" 
                            id="return_quantity" 
                            value="1" 
                            required 
                            class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-base bg-zinc-800 border-none outline-none"
                        >
                    </div>

                    <button 
                        type="submit" 
                        id="btn-submit-carton"
                        class="w-full min-h-[52px] rounded-xl bg-rose-600 hover:bg-rose-500 active:bg-rose-700 text-white font-black text-sm uppercase tracking-wider shadow-lg active:scale-[0.98] transition flex items-center justify-center space-x-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                        </svg>
                        <span>Check-In to Quarantine</span>
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Live Status Console -->
    <div id="status-console" class="hidden rounded-xl p-3.5 bg-zinc-900 border border-zinc-800 font-mono text-xs text-zinc-300">
        <div class="flex items-center space-x-2">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <span id="status-console-text">Waiting for carton scan...</span>
        </div>
    </div>
</div>

<script>
    function onRmaChange() {
        const select = document.getElementById('selected-rma-id');
        if (!select || !select.selectedOptions.length) return;
        const opt = select.selectedOptions[0];

        document.getElementById('card-rma-number').innerText = opt.dataset.rmaNumber || '';
        document.getElementById('card-rma-customer').innerText = opt.dataset.customer || '';
        document.getElementById('card-rma-defect').innerText = opt.dataset.defect || '';
        document.getElementById('card-rma-progress').innerText = `${opt.dataset.received} / ${opt.dataset.total} Cartons`;
    }

    function focusCartonInput() {
        const input = document.getElementById('carton_barcode');
        if (input) {
            input.focus();
            input.select();
        }
    }

    async function submitCartonIntake(e) {
        e.preventDefault();
        const rmaSelect = document.getElementById('selected-rma-id');
        const rmaId = rmaSelect.value;
        const quarantineBinCode = document.getElementById('quarantine_bin_code').value;
        const cartonBarcode = document.getElementById('carton_barcode').value.trim();
        const quantity = parseFloat(document.getElementById('return_quantity').value);

        if (!rmaId || !cartonBarcode || !quarantineBinCode || isNaN(quantity) || quantity <= 0) {
            window.ScannerAudio?.playError?.();
            alert('Please check all input fields.');
            return;
        }

        const btn = document.getElementById('btn-submit-carton');
        btn.disabled = true;
        btn.innerText = 'Checking in...';

        try {
            const res = await fetch('/api/v1/scanner/returns/receive-carton', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    rma_id: rmaId,
                    quarantine_bin_code: quarantineBinCode,
                    carton_barcode: cartonBarcode,
                    quantity: quantity
                })
            });

            const data = await res.json();

            if (res.ok && data.success) {
                window.ScannerAudio?.playSuccess?.();
                if (navigator.vibrate) navigator.vibrate([100, 50, 100]);

                const consoleEl = document.getElementById('status-console');
                const consoleText = document.getElementById('status-console-text');
                consoleEl.classList.remove('hidden');
                consoleText.innerText = `Checked in carton ${cartonBarcode} into ${quarantineBinCode} (${data.received_cartons}/${data.total_cartons})`;

                // Update select option dataset
                const opt = rmaSelect.selectedOptions[0];
                opt.dataset.received = data.received_cartons;
                document.getElementById('card-rma-progress').innerText = `${data.received_cartons} / ${data.total_cartons} Cartons`;

                // Clear barcode and re-focus
                document.getElementById('carton_barcode').value = '';
                focusCartonInput();
            } else {
                window.ScannerAudio?.playError?.();
                alert(data.message || 'Carton check-in failed.');
            }
        } catch (err) {
            window.ScannerAudio?.playError?.();
            alert('Network or server error: ' + err.message);
        } finally {
            btn.disabled = false;
            btn.innerHTML = `<svg class="w-5 h-5 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>Check-In to Quarantine`;
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        onRmaChange();
        focusCartonInput();
    });
</script>
@endsection
