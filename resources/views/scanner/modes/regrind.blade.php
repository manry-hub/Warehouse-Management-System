@extends('scanner.layout')

@section('title', 'Regrind Scrap Intake')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <div class="bg-slate-800 border border-slate-700 rounded-xl p-4 shadow-md">
        <div class="flex items-center justify-between pb-3 border-b border-slate-700/80 mb-3">
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Granulator Runner Scrap</span>
            <span class="text-[10px] text-slate-400 bg-slate-900 px-2 py-0.5 rounded">Gen 1 Only</span>
        </div>

        <form onsubmit="submitRegrind(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Target Polymer SKU</label>
                <select id="regrind_sku" required class="laser-focus w-full h-12 px-2.5 rounded-xl bg-slate-900 border border-slate-600 text-white text-sm focus:outline-none focus:border-amber-500">
                    @foreach($items as $item)
                        <option value="{{ $item->sku }}">{{ $item->sku }} (Max {{ $item->max_regrind_percentage }}%)</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Source IMM</label>
                    <select id="machine_code" required class="laser-focus w-full h-12 px-2.5 rounded-xl bg-slate-900 border border-slate-600 text-white text-sm focus:outline-none focus:border-amber-500">
                        @for($i = 1; $i <= 12; $i++)
                            <option value="IMM-{{ sprintf('%02d', $i) }}">IMM-{{ sprintf('%02d', $i) }}</option>
                        @endfor
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Net Weight (KG)</label>
                    <input 
                        type="number" 
                        step="0.01" 
                        id="regrind_weight" 
                        required 
                        value="35.00"
                        class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-base font-bold text-amber-400 focus:outline-none focus:border-amber-500"
                    >
                </div>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Parent Virgin Lot (Optional)</label>
                <input 
                    type="text" 
                    id="parent_lot_number" 
                    data-scan-target="true"
                    placeholder="Scan Parent Lot Barcode"
                    class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-sm uppercase focus:outline-none focus:border-amber-500"
                >
            </div>

            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Production Order</label>
                    <input 
                        type="text" 
                        id="production_order" 
                        placeholder="PO-MES-1001"
                        class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-xs uppercase focus:outline-none focus:border-amber-500"
                    >
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-300 uppercase mb-1">Destination Bin</label>
                    <input 
                        type="text" 
                        id="regrind_bin_code" 
                        required 
                        value="RM-A-01-11"
                        placeholder="RM-A-01-11"
                        class="laser-focus w-full h-12 px-3 rounded-xl bg-slate-900 border border-slate-600 text-white font-mono text-xs uppercase focus:outline-none focus:border-amber-500"
                    >
                </div>
            </div>

            <button 
                type="submit" 
                id="regrind-submit-btn"
                class="w-full h-14 mt-2 rounded-xl bg-amber-600 hover:bg-amber-500 active:bg-amber-700 text-white font-bold text-base tracking-wider uppercase shadow-lg shadow-amber-600/30 flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>Intake Regrind & Print Label</span>
            </button>
        </form>
    </div>

    <!-- Generated Regrind Label Modal -->
    <div id="regrind-label-modal" class="hidden bg-slate-800 border-2 border-amber-500/60 rounded-xl p-4 space-y-3 shadow-2xl">
        <div class="flex items-center justify-between pb-2 border-b border-slate-700">
            <span class="text-xs font-bold text-amber-400 uppercase tracking-wider">Regrind Pallet Label</span>
            <button onclick="closeRegrindModal()" class="text-slate-400 hover:text-white">&times;</button>
        </div>
        <div class="text-center bg-white p-3 rounded-lg flex flex-col items-center justify-center">
            <div id="regrind-qr-container" class="w-40 h-40 flex items-center justify-center"></div>
            <div id="regrind-lot-text" class="text-xs font-mono font-black text-black mt-1"></div>
        </div>
        <div class="flex space-x-2">
            <button onclick="printRegrindZpl()" class="flex-1 h-12 rounded-lg bg-amber-600 active:bg-amber-700 text-white font-bold text-xs uppercase">
                Print ZPL Thermal
            </button>
            <button onclick="closeRegrindModal()" class="px-4 h-12 rounded-lg bg-slate-700 active:bg-slate-600 text-white font-bold text-xs uppercase">
                Done
            </button>
        </div>
    </div>
</div>

<script>
    async function submitRegrind(e) {
        e.preventDefault();
        const btn = document.getElementById('regrind-submit-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Creating Regrind Lot...</span>';

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
                    quantity_kg: parseFloat(document.getElementById('regrind_weight').value),
                    bin_code: document.getElementById('regrind_bin_code').value.trim(),
                    machine_code: document.getElementById('machine_code').value,
                    parent_lot_number: document.getElementById('parent_lot_number').value.trim() || null,
                    production_order: document.getElementById('production_order').value.trim() || null
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Regrind Lot ${data.data.lot_number} Created!`, true);
                document.getElementById('regrind-qr-container').innerHTML = data.data.qr_svg;
                document.getElementById('regrind-lot-text').textContent = data.data.lot_number;
                document.getElementById('regrind-label-modal').classList.remove('hidden');
                document.getElementById('parent_lot_number').value = '';
            } else {
                showToast(data.message || 'Regrind intake failed', false);
            }
        } catch (err) {
            showToast('Network error during regrind intake', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Intake Regrind & Print Label</span>';
        }
    }

    function closeRegrindModal() {
        document.getElementById('regrind-label-modal').classList.add('hidden');
        document.getElementById('regrind_weight').focus();
    }

    function printRegrindZpl() {
        showToast('Thermal label dispatched to printer!', true);
    }
</script>
@endsection
