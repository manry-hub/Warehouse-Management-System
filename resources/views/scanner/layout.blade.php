<!DOCTYPE html>
<html lang="en" class="h-full" style="background-color: #09090b; color: #f4f4f5;">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#09090b">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <title>@yield('title', 'Scanner Terminal') - Smart WMS</title>
    
    <link rel="manifest" href="/scanner-manifest.json">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#f59e0b',
                            600: '#d97706',
                            700: '#b45309',
                        }
                    }
                }
            }
        }
    </script>
    
    <style>
        /* Touch and scanner ergonomics */
        * {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            border-color: transparent;
        }
        body {
            background-color: #09090b;
            color: #f4f4f5;
        }
        input, select, button {
            font-size: 16px !important; /* Prevents mobile auto-zoom */
        }
        .laser-focus:focus {
            outline: none;
            box-shadow: 0 0 0 2px #f59e0b, 0 0 12px rgba(245, 158, 11, 0.4);
        }
    </style>
    @stack('styles')
</head>
<body class="h-full flex flex-col font-sans select-none antialiased" style="background-color: #09090b;">

    <!-- Top App Bar (Borderless, subtle grayish fill) -->
    <header class="flex-shrink-0 px-3.5 py-3 flex items-center justify-between shadow-md" style="background-color: #141417; border: none;">
        <div class="flex items-center space-x-2.5">
            @hasSection('back_url')
                <a href="@yield('back_url')" class="p-2 -ml-1 rounded-xl text-gray-200 active:scale-95 transition" style="background-color: #27272f;">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
            @else
                <div class="p-2 rounded-xl text-amber-400" style="background-color: rgba(245, 158, 11, 0.15);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
            @endif
            <div>
                <h1 class="text-sm font-bold text-white tracking-wide truncate max-w-[180px]">
                    @yield('title', 'Smart WMS')
                </h1>
                <div class="flex items-center space-x-1.5 text-[10px] text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Online Terminal</span>
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-2">
            @auth
                <div class="text-right">
                    <div class="text-xs font-semibold text-gray-200 truncate max-w-[100px]">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-gray-400">Operator</div>
                </div>
                <form method="POST" action="{{ route('scanner.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="p-2 rounded-xl text-rose-400 active:scale-95 transition" style="background-color: rgba(244, 63, 94, 0.15);" title="Sign Out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
            @endauth
        </div>
    </header>

    <!-- Main Viewport -->
    <main class="flex-1 overflow-y-auto px-3 py-3">
        <!-- Toast / Flash Banner (Borderless) -->
        <div id="scanner-toast" class="hidden mb-3 p-3.5 rounded-xl text-sm font-medium transition-all duration-200 flex items-center justify-between shadow-lg" style="border: none;">
            <span id="toast-message"></span>
            <button onclick="hideToast()" class="text-gray-400 hover:text-white ml-2 text-base font-bold">&times;</button>
        </div>

        @if(session('success'))
            <div class="mb-3 p-3.5 rounded-xl text-xs font-semibold text-emerald-300 shadow-md" style="background-color: rgba(6, 78, 59, 0.7); border: none;">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-3 p-3.5 rounded-xl text-xs font-semibold text-rose-200 shadow-md" style="background-color: rgba(159, 18, 57, 0.7); border: none;">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Client-Side Audio & Barcode Interceptor -->
    <script>
        // 1. Web Audio API Sound Synthesizer
        let audioCtx = null;
        function getAudioContext() {
            if (!audioCtx) {
                const AudioContextClass = window.AudioContext || window.webkitAudioContext;
                if (AudioContextClass) {
                    audioCtx = new AudioContextClass();
                }
            }
            if (audioCtx && audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            return audioCtx;
        }

        window.ScannerAudio = {
            playSuccess: function() {
                try {
                    const ctx = getAudioContext();
                    if (!ctx) return;
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(1760, ctx.currentTime + 0.08);
                    
                    gain.gain.setValueAtTime(0.3, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.12);
                    
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.12);
                    
                    if (navigator.vibrate) navigator.vibrate(100);
                } catch(e) { console.error('Audio error', e); }
            },

            playError: function() {
                try {
                    const ctx = getAudioContext();
                    if (!ctx) return;
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    
                    osc.type = 'sawtooth';
                    osc.frequency.setValueAtTime(220, ctx.currentTime);
                    osc.frequency.exponentialRampToValueAtTime(110, ctx.currentTime + 0.25);
                    
                    gain.gain.setValueAtTime(0.4, ctx.currentTime);
                    gain.gain.exponentialRampToValueAtTime(0.01, ctx.currentTime + 0.28);
                    
                    osc.start(ctx.currentTime);
                    osc.stop(ctx.currentTime + 0.28);
                    
                    if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
                } catch(e) { console.error('Audio error', e); }
            }
        };

        // 2. Hardware Laser Wedge Keydown Buffer
        let scanBuffer = '';
        let lastKeyTime = Date.now();

        window.addEventListener('keydown', function(e) {
            const now = Date.now();
            const elapsed = now - lastKeyTime;
            lastKeyTime = now;

            if (e.key === 'Enter') {
                if (scanBuffer.length >= 3 && elapsed < 80) {
                    const scannedBarcode = scanBuffer.trim();
                    scanBuffer = '';
                    handleBarcodeScanned(scannedBarcode);
                    e.preventDefault();
                    return;
                }
                scanBuffer = '';
            } else if (e.key.length === 1) {
                if (elapsed > 120) {
                    scanBuffer = '';
                }
                scanBuffer += e.key;
            }
        });

        function handleBarcodeScanned(barcode) {
            window.ScannerAudio.playSuccess();
            window.dispatchEvent(new CustomEvent('barcode-scan', { detail: { barcode: barcode } }));
            
            const target = document.querySelector('[data-scan-target="true"]') || document.activeElement;
            if (target && (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA')) {
                target.value = barcode;
                target.dispatchEvent(new Event('input', { bubbles: true }));
                target.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }

        // 3. Toast Helper
        function showToast(message, isSuccess = true) {
            const toast = document.getElementById('scanner-toast');
            const msg = document.getElementById('toast-message');
            if (!toast || !msg) return;

            msg.textContent = message;
            toast.style.backgroundColor = isSuccess ? 'rgba(6, 78, 59, 0.85)' : 'rgba(159, 18, 57, 0.85)';
            toast.style.color = isSuccess ? '#6ee7b7' : '#fecdd3';
            toast.classList.remove('hidden');

            if (isSuccess) {
                window.ScannerAudio.playSuccess();
            } else {
                window.ScannerAudio.playError();
            }

            setTimeout(hideToast, 4000);
        }

        function hideToast() {
            const toast = document.getElementById('scanner-toast');
            if (toast) toast.classList.add('hidden');
        }
    </script>
    @stack('scripts')
</body>
</html>
