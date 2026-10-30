@extends('scanner.layout')

@section('title', 'Dock Receiving')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-3 mb-3">
            <span class="text-xs font-bold text-sky-400 uppercase tracking-wider">Inbound Dock Receiving</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Scan PO or Enter</span>
        </div>

        <form id="receiving-form" onsubmit="submitReceiving(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">PO Number / Manifest</label>
                <input 
                    type="text" 
                    id="po_number" 
                    required 
                    data-scan-target="true"
                    placeholder="e.g. PO-2026-8801"
                    class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Material SKU</label>
                    <select id="sku" required class="laser-focus w-full h-12 px-3 rounded-xl text-white text-sm" style="background-color: #27272a; border: none; outline: none;">
                        @foreach($items as $item)
                            <option value="{{ $item->sku }}">{{ $item->sku }} ({{ $item->unit_of_measure }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Vendor Code</label>
                    <input 
                        type="text" 
                        id="vendor_code" 
                        required 
                        value="VEND-LOTTE"
                        placeholder="VEND-001"
                        class="laser-focus w-full h-12 px-3.5 rounded-xl text-white text-sm"
                        style="background-color: #27272a; border: none; outline: none;"
                    >
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Heat / Batch #</label>
                    <input 
                        type="text" 
                        id="vendor_heat_number" 
                        placeholder="HEAT-9921"
                        class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-sm"
                        style="background-color: #27272a; border: none; outline: none;"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Quantity (KG)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        id="quantity_kg" 
                        required 
                        value="500.00"
                        class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-base font-bold text-sky-400"
                        style="background-color: #27272a; border: none; outline: none;"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                id="submit-btn"
                class="w-full h-14 mt-3 rounded-xl bg-sky-600 hover:bg-sky-500 active:bg-sky-700 text-white font-bold text-base tracking-wider uppercase shadow-lg flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
                style="border: none;"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Receive & Generate Barcode</span>
            </button>
        </form>
    </div>

    <!-- Label Preview Modal (Populated on Receive) -->
    <div id="label-modal" class="hidden rounded-2xl p-4 space-y-3 shadow-2xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider">Lot Generated (QA Hold)</span>
            <button onclick="closeModal()" class="text-gray-400 hover:text-white p-1 text-lg">&times;</button>
        </div>
        <div class="text-center p-4 rounded-xl flex flex-col items-center justify-center shadow-inner" style="background-color: #ffffff;">
            <div id="qr-container" class="w-40 h-40 flex items-center justify-center"></div>
            <div id="lot-number-text" class="text-xs font-mono font-black text-black mt-2"></div>
        </div>
        <div class="flex space-x-2 pt-1">
            <button onclick="printZpl()" class="flex-1 h-12 rounded-xl bg-emerald-600 active:bg-emerald-700 text-white font-bold text-xs uppercase flex items-center justify-center space-x-1" style="border: none;">
                <span>Print ZPL Thermal</span>
            </button>
            <button onclick="closeModal()" class="px-5 h-12 rounded-xl text-white font-bold text-xs uppercase" style="background-color: #27272a; border: none;">
                Next
            </button>
        </div>
    </div>
</div>

<script>
    let activeZpl = '';

    async function submitReceiving(e) {
        e.preventDefault();
        const btn = document.getElementById('submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Processing Intake...</span>';

        try {
            const res = await fetch('/api/v1/scanner/inbound/receive', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    po_number: document.getElementById('po_number').value.trim(),
                    sku: document.getElementById('sku').value,
                    vendor_code: document.getElementById('vendor_code').value.trim(),
                    vendor_heat_number: document.getElementById('vendor_heat_number').value.trim(),
                    quantity_kg: parseFloat(document.getElementById('quantity_kg').value)
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Received ${data.data.lot_number} into Dock!`, true);
                activeZpl = data.data.zpl_stream;
                document.getElementById('qr-container').innerHTML = data.data.qr_svg;
                document.getElementById('lot-number-text').textContent = data.data.lot_number;
                document.getElementById('label-modal').classList.remove('hidden');
                document.getElementById('po_number').value = '';
            } else {
                showToast(data.message || 'Intake failed', false);
            }
        } catch (err) {
            showToast('Network / Server Error', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Receive & Generate Barcode</span>';
        }
    }

    function closeModal() {
        document.getElementById('label-modal').classList.add('hidden');
        document.getElementById('po_number').focus();
    }

    function printZpl() {
        showToast('ZPL sent to thermal printer on port 9100', true);
    }
</script>
@endsection
