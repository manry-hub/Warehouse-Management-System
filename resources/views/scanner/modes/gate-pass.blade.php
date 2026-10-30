@extends('scanner.layout')

@section('title', 'Security Gate Clearance')
@section('back_url', route('scanner.dashboard'))

@section('content')
<div class="space-y-4">
    <!-- Header Card -->
    <div class="rounded-2xl p-4 shadow-md" style="background-color: #18181b; border: none;">
        <div class="flex items-center justify-between pb-2 mb-2">
            <span class="text-xs font-bold text-teal-400 uppercase tracking-wider">Mode 8: Security Gate Clearance</span>
            <span class="text-[10px] text-gray-400 px-2.5 py-1 rounded-md" style="background-color: #27272a;">Factory Gate Out</span>
        </div>
        <p class="text-[11px] text-gray-300">
            Verify truck seal, record driver glass e-signature, and clear delivery orders for factory departure (*Surat Jalan Digital*).
        </p>
    </div>

    <!-- Active Delivery Orders Queued for Gate Departure -->
    <div class="rounded-2xl p-4 shadow-md space-y-3" style="background-color: #1e1e24; border: none;">
        <span class="text-xs font-bold text-gray-300 uppercase tracking-wider">Pending Outbound Trucks</span>

        @if($pendingOrders->isEmpty())
            <div class="text-center py-6 text-gray-400 text-xs">
                No delivery orders currently awaiting security gate clearance.
            </div>
        @else
            <div class="space-y-2">
                @foreach($pendingOrders as $order)
                    <div class="rounded-xl p-3 flex items-center justify-between shadow-sm" style="background-color: #141417; border: none;">
                        <div>
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold font-mono text-white">{{ $order->do_number }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold text-teal-300" style="background-color: rgba(20, 184, 166, 0.2);">
                                    {{ $order->status }}
                                </span>
                            </div>
                            <div class="text-[11px] text-gray-300 mt-1">
                                Customer: <span class="font-medium text-white">{{ $order->customer_name }}</span>
                            </div>
                            <div class="text-[10px] text-gray-400 font-mono mt-0.5">
                                Plate: {{ $order->vehicle_plate_number }} • Driver: {{ $order->driver_name }}
                            </div>
                        </div>
                        <button 
                            onclick="selectGateOrder('{{ $order->do_number }}', '{{ $order->vehicle_plate_number }}', '{{ $order->driver_name }}')"
                            class="px-3.5 py-2 rounded-xl text-black font-bold text-xs uppercase shadow"
                            style="background-color: #14b8a6; border: none;"
                        >
                            Inspect
                        </button>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Gate Clearance Form (Modal/Console) -->
    <div id="gate-console-card" class="hidden rounded-2xl p-4 space-y-3 shadow-xl" style="background-color: #1e1e24; border: none;">
        <div class="flex items-center justify-between pb-2 mb-1">
            <span class="text-xs font-bold text-teal-400 uppercase tracking-wider">Gate Checkpoint Details</span>
            <span id="active-do-pill" class="text-xs font-mono font-bold text-white px-2.5 py-0.5 rounded" style="background-color: #27272a;"></span>
        </div>

        <div>
            <label class="block text-[11px] font-bold text-gray-300 mb-1">Verified Truck Plate Number</label>
            <input 
                type="text" 
                id="gate-plate-input" 
                class="w-full px-3.5 py-2.5 rounded-xl text-white font-mono text-xs focus:outline-none"
                style="background-color: #141417; border: 1px solid #3f3f46;"
            >
        </div>

        <div>
            <label class="block text-[11px] font-bold text-gray-300 mb-1">Verified Driver Name</label>
            <input 
                type="text" 
                id="gate-driver-input" 
                class="w-full px-3.5 py-2.5 rounded-xl text-white text-xs focus:outline-none"
                style="background-color: #141417; border: 1px solid #3f3f46;"
            >
        </div>

        <div>
            <label class="block text-[11px] font-bold text-gray-300 mb-1">Container Security Seal Number</label>
            <input 
                type="text" 
                id="gate-seal-input" 
                placeholder="Scan or enter seal (e.g. SEAL-8891)" 
                class="w-full px-3.5 py-2.5 rounded-xl text-white font-mono text-xs focus:outline-none"
                style="background-color: #141417; border: 1px solid #3f3f46;"
            >
        </div>

        <!-- Touchscreen Driver Signature Pad -->
        <div>
            <div class="flex items-center justify-between mb-1">
                <label class="text-[11px] font-bold text-gray-300">Driver Signature (Sign on glass)</label>
                <button type="button" onclick="clearSignature()" class="text-[10px] text-gray-400 hover:text-white underline">
                    Clear Pad
                </button>
            </div>
            <div class="rounded-xl overflow-hidden" style="background-color: #09090b; border: 1px solid #3f3f46;">
                <canvas id="signature-pad" width="340" height="120" class="w-full h-28 cursor-crosshair touch-none"></canvas>
            </div>
        </div>

        <button 
            type="button" 
            onclick="submitGateClearance()" 
            id="clear-gate-btn" 
            class="w-full py-3 rounded-xl font-black text-xs uppercase tracking-wider text-black transition shadow-lg mt-2" 
            style="background-color: #14b8a6; border: none;"
        >
            Authorize Gate Out & Issue Gate Pass
        </button>
    </div>
</div>

<script>
let activeDoNumber = null;
let canvas, ctx;
let drawing = false;
let hasSigned = false;

document.addEventListener('DOMContentLoaded', () => {
    canvas = document.getElementById('signature-pad');
    if (!canvas) return;
    ctx = canvas.getContext('2d');
    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.strokeStyle = '#38bdf8'; // Sky blue signature stroke

    function getCoords(e) {
        const rect = canvas.getBoundingClientRect();
        const clientX = e.touches ? e.touches[0].clientX : e.clientX;
        const clientY = e.touches ? e.touches[0].clientY : e.clientY;
        return {
            x: (clientX - rect.left) * (canvas.width / rect.width),
            y: (clientY - rect.top) * (canvas.height / rect.height)
        };
    }

    function startDraw(e) {
        e.preventDefault();
        drawing = true;
        const p = getCoords(e);
        ctx.beginPath();
        ctx.moveTo(p.x, p.y);
    }

    function draw(e) {
        if (!drawing) return;
        e.preventDefault();
        const p = getCoords(e);
        ctx.lineTo(p.x, p.y);
        ctx.stroke();
        hasSigned = true;
    }

    function stopDraw(e) {
        if (!drawing) return;
        e.preventDefault();
        drawing = false;
        ctx.closePath();
    }

    canvas.addEventListener('mousedown', startDraw);
    canvas.addEventListener('mousemove', draw);
    window.addEventListener('mouseup', stopDraw);

    canvas.addEventListener('touchstart', startDraw, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    window.addEventListener('touchend', stopDraw);
});

function clearSignature() {
    if (!ctx) return;
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    hasSigned = false;
}

function selectGateOrder(doNumber, plate, driver) {
    activeDoNumber = doNumber;
    document.getElementById('active-do-pill').innerText = doNumber;
    document.getElementById('gate-plate-input').value = plate;
    document.getElementById('gate-driver-input').value = driver;
    document.getElementById('gate-seal-input').value = 'SEAL-' + Math.floor(1000 + Math.random() * 9000);
    clearSignature();
    document.getElementById('gate-console-card').classList.remove('hidden');
    document.getElementById('gate-console-card').scrollIntoView({ behavior: 'smooth' });
}

async function submitGateClearance() {
    if (!activeDoNumber) {
        alert('Please select a delivery order.');
        return;
    }

    const seal = document.getElementById('gate-seal-input').value.trim();
    const plate = document.getElementById('gate-plate-input').value.trim();
    const driver = document.getElementById('gate-driver-input').value.trim();

    if (!seal) {
        alert('Please enter or scan the security seal number.');
        return;
    }

    if (!hasSigned) {
        alert('Please have the driver sign on the signature pad.');
        return;
    }

    const signatureData = canvas.toDataURL('image/png');

    try {
        const response = await fetch('/api/v1/scanner/gate-pass/clear', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                deliveryOrderNumber: activeDoNumber,
                sealNumber: seal,
                driverSignature: signatureData,
                verifiedPlate: plate,
                verifiedDriver: driver,
            }),
        });

        const data = await response.json();
        if (response.ok && data.success) {
            window.ScannerAudio?.playSuccess();
            alert(`GATE CLEARANCE APPROVED!\nGate Pass: ${data.data.gatePassNumber}\nStatus: DISPATCHED_GATE_OUT\nDeparture Time: ${data.data.gateOutAt}`);
            window.location.reload();
        } else {
            window.ScannerAudio?.playError();
            alert('GATE PASS FAILED: ' + (data.message || 'Unknown error'));
        }
    } catch (e) {
        alert('Network Error: ' + e.message);
    }
}
</script>
@endsection
