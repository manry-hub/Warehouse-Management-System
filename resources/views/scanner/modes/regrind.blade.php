@extends('scanner.layout')

@section('title', 'Regrind Scrap Intake')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-3 mb-3">
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Granulator Runner Scrap</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Gen 1 Only</span>
        </div>

        <form onsubmit="submitRegrind(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Target Polymer SKU</label>
                <select id="regrind_sku" required class="laser-focus w-full h-12 px-3 rounded-xl text-white text-sm" style="background-color: #27272a; border: none; outline: none;">
                    @foreach($items as $item)
                        <option value="{{ $item->sku }}">{{ $item->sku }} (Max {{ $item->max_regrind_percentage }}%)</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Source IMM</label>
                    <select id="machine_code" required class="laser-focus w-full h-12 px-3 rounded-xl text-white text-sm" style="background-color: #27272a; border: none; outline: none;">
                        @for($i = 1; $i <= 12; $i++)
                            <option value="IMM-{{ sprintf('%02d', $i) }}">IMM-{{ sprintf('%02d', $i) }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Net Weight (KG)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        id="regrind_weight" 
                        required 
                        value="35.00"
                        class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-base font-bold text-amber-400"
                        style="background-color: #27272a; border: none; outline: none;"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Parent Virgin Lot (Optional)</label>
                <input 
                    type="text" 
                    id="parent_lot_number" 
                    data-scan-target="true"
                    placeholder="Scan Parent Lot Barcode"
                    class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-sm uppercase"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Production Order</label>
                    <input 
                        type="text" 
                        id="prod_order" 
                        value="WO-2026-001"
                        placeholder="WO-..."
                        class="laser-focus w-full h-12 px-3.5 rounded-xl text-white font-mono text-sm"
                        style="background-color: #27272a; border: none; outline: none;"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Intake Granulator Bin</label>
                    <input 
                        type="text" 
                        id="dest_bin" 
                        readonly 
                        value="RM-A-01-11"
                        class="w-full h-12 px-3.5 rounded-xl text-amber-300 font-mono text-sm"
                        style="background-color: #141417; border: none;"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                id="regrind-btn"
                class="w-full h-14 mt-3 rounded-xl bg-amber-500 hover:bg-amber-400 active:bg-amber-600 text-slate-950 font-black text-base tracking-wider uppercase shadow-lg flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
                style="border: none;"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Intake Regrind & Print QR</span>
            </button>
        </form>
    </div>

    <!-- Regrind Label Modal (Grayish fill, borderless) -->
    <div id="regrind-modal" class="hidden rounded-2xl p-4 space-y-3 shadow-2xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Regrind Lot Generated (Gen 1)</span>
            <button onclick="closeRegrindModal()" class="text-gray-400 hover:text-white p-1 text-lg">&times;</button>
        </div>
        <div class="text-center p-4 rounded-xl flex flex-col items-center justify-center shadow-inner" style="background-color: #ffffff;">
            <div id="regrind-qr-container" class="w-40 h-40 flex items-center justify-center"></div>
            <div id="regrind-lot-text" class="text-xs font-mono font-black text-black mt-2"></div>
        </div>
        <div class="flex space-x-2 pt-1">
            <button onclick="printZpl()" class="flex-1 h-12 rounded-xl bg-amber-500 text-slate-950 font-black text-xs uppercase flex items-center justify-center space-x-1" style="border: none;">
                <span>Print Thermal ZPL</span>
            </button>
            <button onclick="closeRegrindModal()" class="px-5 h-12 rounded-xl text-white font-bold text-xs uppercase" style="background-color: #27272a; border: none;">
                Done
            </button>
        </div>
    </div>
</div>

<script>
    async function submitRegrind(e) {
        e.preventDefault();
        const btn = document.getElementById('regrind-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Granulating Scrap...</span>';

        try {
            const res = await fetch('/api/v1/scanner/regrind/intake', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    item_sku: document.getElementById('regrind_sku').value,
                    source_machine: document.getElementById('machine_code').value,
                    weight_kg: parseFloat(document.getElementById('regrind_weight').value),
                    parent_lot_number: document.getElementById('parent_lot_number').value.trim() || null,
                    production_order_number: document.getElementById('prod_order').value.trim() || null,
                    destination_bin_code: document.getElementById('dest_bin').value.trim()
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Regrind Lot ${data.data.lot_number} generated!`, true);
                document.getElementById('regrind-qr-container').innerHTML = data.data.qr_svg;
                document.getElementById('regrind-lot-text').textContent = data.data.lot_number;
                document.getElementById('regrind-modal').classList.remove('hidden');
                document.getElementById('parent_lot_number').value = '';
            } else {
                showToast(data.message || 'Regrind rejected (Check Gen 2 guard)', false);
            }
        } catch(err) {
            showToast('Regrind intake server communication failed', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Intake Regrind & Print QR</span>';
        }
    }

    function closeRegrindModal() {
        document.getElementById('regrind-modal').classList.add('hidden');
        document.getElementById('parent_lot_number').focus();
    }

    function printZpl() {
        showToast('ZPL stream sent to granulator thermal printer', true);
    }
</script>
@endsection
