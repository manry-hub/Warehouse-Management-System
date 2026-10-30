@extends('scanner.layout')

@section('title', 'Blind Cycle Count')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Active Cycle Count Orders (Grayish fill, borderless) -->
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-2 mb-3">
            <span class="text-xs font-bold text-purple-400 uppercase tracking-wider">Active Audit Orders</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Blind Secrecy</span>
        </div>

        @if($countOrders->isEmpty())
            <div class="text-center py-6 text-gray-400 text-xs">
                No active cycle count orders assigned.
            </div>
        @else
            <div class="space-y-2">
                @foreach($countOrders as $order)
                    <div class="rounded-xl p-3.5 flex items-center justify-between shadow-sm" style="background-color: #1e1e24; border: none;">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-black text-purple-300" style="background-color: rgba(168, 85, 247, 0.2);">
                                    {{ $order->count_type }}
                                </span>
                                <span class="text-xs font-bold text-white font-mono">{{ $order->count_number }}</span>
                            </div>
                            <div class="text-[11px] text-gray-400 mt-1">
                                Scope: {{ $order->zone ? $order->zone->name : 'Whole Warehouse' }}
                            </div>
                        </div>
                        <button 
                            onclick="loadCountSheet('{{ $order->id }}', '{{ $order->count_number }}')"
                            class="px-3.5 py-2 rounded-xl bg-purple-600 active:bg-purple-700 text-white font-bold text-xs uppercase shadow"
                            style="border: none;"
                        >
                            Open Sheet
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Active Blind Count Execution Form (Grayish fill, borderless) -->
    <div id="count-execution-card" class="hidden rounded-2xl p-4 space-y-3 shadow-xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <div>
                <span class="text-xs font-bold text-purple-300 uppercase tracking-wider">Physical Count Entry</span>
                <div id="active-order-title" class="text-xs font-mono text-white font-bold"></div>
            </div>
            <span class="px-2.5 py-1 rounded-md text-[10px] font-bold text-amber-300" style="background-color: rgba(245, 158, 11, 0.2);">
                Book Stock Hidden
            </span>
        </div>

        <form onsubmit="submitPhysicalCount(event)" class="space-y-3">
            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Scan Bin Barcode</label>
                <input 
                    type="text" 
                    id="count_bin_code" 
                    required 
                    data-scan-target="true"
                    placeholder="Scan Rack Bin (e.g. RM-A-01-11)"
                    class="laser-focus w-full h-13 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Scan Lot Barcode</label>
                <input 
                    type="text" 
                    id="count_lot_number" 
                    required 
                    placeholder="Scan Material Lot QR"
                    class="laser-focus w-full h-13 px-3.5 rounded-xl text-white font-mono text-base uppercase"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <div>
                <label class="block text-xs font-bold text-gray-300 uppercase mb-1.5">Physical Counted Quantity</label>
                <input 
                    type="number" 
                    step="0.01" 
                    id="counted_qty" 
                    required 
                    placeholder="0.00"
                    class="laser-focus w-full h-14 px-3.5 rounded-xl text-white font-mono text-xl font-black text-purple-400 text-center"
                    style="background-color: #27272a; border: none; outline: none;"
                >
            </div>

            <button 
                type="submit" 
                id="submit-count-btn"
                class="w-full h-14 rounded-xl bg-purple-600 hover:bg-purple-500 active:bg-purple-700 text-white font-bold text-base tracking-wider uppercase shadow-lg flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
                style="border: none;"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                <span>Record Physical Count</span>
            </button>
        </form>

        <!-- Blind Count Task Items Progress -->
        <div class="pt-3 space-y-2">
            <span class="text-xs font-bold text-gray-400 uppercase tracking-wider block">Assigned Items to Audit</span>
            <div id="blind-sheet-items" class="space-y-2 max-h-48 overflow-y-auto"></div>
        </div>
    </div>
</div>

<script>
    let activeOrderId = '';

    async function loadCountSheet(orderId, orderNumber) {
        activeOrderId = orderId;
        document.getElementById('active-order-title').textContent = orderNumber;
        
        try {
            const res = await fetch(`/api/v1/scanner/cycle-counts/${orderId}/sheet`);
            const data = await res.json();

            if (res.ok && data.success) {
                const container = document.getElementById('blind-sheet-items');
                container.innerHTML = '';

                data.data.items.forEach(item => {
                    const row = document.createElement('div');
                    row.className = 'rounded-xl p-3 flex items-center justify-between text-xs font-mono shadow-sm';
                    row.style.backgroundColor = '#141417';
                    row.style.border = 'none';
                    
                    const statusBadge = item.is_counted 
                        ? '<span class="text-[9px] font-bold px-2 py-0.5 rounded text-emerald-300" style="background-color: rgba(6, 78, 59, 0.6);">COUNTED</span>'
                        : '<span class="text-[9px] font-bold px-2 py-0.5 rounded text-gray-400" style="background-color: #27272a;">PENDING</span>';

                    row.innerHTML = `
                        <div>
                            <div class="font-bold text-white">${item.bin_code} &bull; ${item.sku}</div>
                            <div class="text-[10px] text-gray-400">Lot: ${item.lot_number}</div>
                        </div>
                        <div>${statusBadge}</div>
                    `;
                    container.appendChild(row);
                });

                document.getElementById('count-execution-card').classList.remove('hidden');
                document.getElementById('count_bin_code').focus();
                showToast(`Loaded sheet ${orderNumber}. Blind secrecy active!`, true);
            } else {
                showToast(data.message || 'Failed loading sheet', false);
            }
        } catch(e) {
            showToast('Network error loading audit sheet', false);
        }
    }

    async function submitPhysicalCount(e) {
        e.preventDefault();
        const binCode = document.getElementById('count_bin_code').value.trim();
        const lotNumber = document.getElementById('count_lot_number').value.trim();
        const countedQty = parseFloat(document.getElementById('counted_qty').value);

        const btn = document.getElementById('submit-count-btn');
        btn.disabled = true;
        btn.innerHTML = '<span>Saving Count...</span>';

        try {
            const res = await fetch(`/api/v1/scanner/cycle-counts/${activeOrderId}/record`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    bin_code: binCode,
                    lot_number: lotNumber,
                    counted_quantity: countedQty
                })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                showToast(`Count recorded for ${lotNumber}!`, true);
                document.getElementById('count_bin_code').value = '';
                document.getElementById('count_lot_number').value = '';
                document.getElementById('counted_qty').value = '';
                loadCountSheet(activeOrderId, document.getElementById('active-order-title').textContent);
            } else {
                showToast(data.message || 'Record rejected', false);
            }
        } catch(err) {
            showToast('Server communication failed', false);
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<span>Record Physical Count</span>';
        }
    }
</script>
@endsection
