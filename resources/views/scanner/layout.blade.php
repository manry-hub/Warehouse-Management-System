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
        /* Touch and scanner ergonomics complying with LAYOUT-MOBILE.md */
        * {
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            box-sizing: border-box;
        }
        html, body {
            background-color: #09090b;
            color: #f4f4f5;
            min-height: 100dvh;
            overflow-x: hidden;
        }
        /* Enforce >=16px font on inputs to prevent mobile auto-zoom */
        input, select, textarea, button {
            font-size: 16px !important;
        }
        .laser-focus:focus {
            outline: none;
            box-shadow: 0 0 0 2px #f59e0b, 0 0 14px rgba(245, 158, 11, 0.45);
        }
        /* Mobile safe-area utilities */
        .pt-safe {
            padding-top: max(0.75rem, env(safe-area-inset-top));
        }
        .pb-safe {
            padding-bottom: max(0.75rem, env(safe-area-inset-bottom));
        }
    </style>
    @stack('styles')
</head>
<body class="min-h-[100dvh] flex flex-col font-sans select-none antialiased bg-[#09090b] text-zinc-100">

    <!-- Top App Bar (Sticky, touch-friendly, safe-area aware) -->
    <header class="sticky top-0 z-30 flex-shrink-0 px-3.5 py-2.5 sm:py-3 flex items-center justify-between shadow-md pt-safe bg-[#141417]/95 backdrop-blur-md border-b border-zinc-800/60">
        <div class="flex items-center space-x-2.5 min-w-0">
            @hasSection('back_url')
                <a href="@yield('back_url')" class="min-w-[44px] min-h-[44px] p-2.5 -ml-1 rounded-xl text-gray-200 active:scale-95 transition flex items-center justify-center bg-[#27272f]" aria-label="Back">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
            @else
                <div class="min-w-[40px] min-h-[40px] p-2 rounded-xl text-amber-400 flex items-center justify-center" style="background-color: rgba(245, 158, 11, 0.15);">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
                    </svg>
                </div>
            @endif
            <div class="min-w-0">
                <h1 class="text-sm font-bold text-white tracking-wide truncate max-w-[190px] sm:max-w-xs">
                    @yield('title', 'Smart WMS')
                </h1>
                <div class="flex items-center space-x-1.5 text-[10px] text-emerald-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span class="font-mono">Online • Laser Listening</span>
                </div>
            </div>
        </div>

        <div class="flex items-center space-x-2 shrink-0">
            @auth
                <div class="text-right hidden sm:block">
                    <div class="text-xs font-semibold text-gray-200 truncate max-w-[120px]">{{ auth()->user()->name }}</div>
                    <div class="text-[10px] text-gray-400 font-mono">Shift Operator</div>
                </div>
                <form method="POST" action="{{ route('scanner.logout') }}" class="inline">
                    @csrf
                    <button type="submit" class="min-w-[44px] min-h-[44px] p-2.5 rounded-xl text-rose-400 active:scale-95 transition flex items-center justify-center" style="background-color: rgba(244, 63, 94, 0.15);" title="Sign Out">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                    </button>
                </form>
            @endauth
        </div>
    </header>

    <!-- Main Viewport (pb-24 reserves space for mobile bottom nav) -->
    <main class="flex-1 overflow-y-auto px-3 sm:px-4 py-3 pb-24 max-w-4xl mx-auto w-full">
        <!-- Toast / Flash Banner -->
        <div id="scanner-toast" class="hidden mb-3 p-3.5 rounded-xl text-sm font-medium transition-all duration-200 flex items-center justify-between shadow-lg" style="border: none;">
            <span id="toast-message" class="flex-1 pr-2"></span>
            <button onclick="hideToast()" class="min-w-[44px] min-h-[44px] -mr-2 text-gray-300 hover:text-white flex items-center justify-center text-xl font-bold">&times;</button>
        </div>

        @if(session('success'))
            <div class="mb-3 p-3.5 rounded-xl text-xs font-semibold text-emerald-300 shadow-md bg-emerald-950/80 border border-emerald-800/50">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-3 p-3.5 rounded-xl text-xs font-semibold text-rose-200 shadow-md bg-rose-950/80 border border-rose-800/50">
                {{ session('error') }}
            </div>
        @endif

        @yield('content')
    </main>

    <!-- Fixed Mobile Bottom Navigation Bar (R-03: thumb reachable, safe area padding) -->
    @auth
    <nav class="fixed bottom-0 inset-x-0 z-40 bg-[#121214]/95 backdrop-blur-md border-t border-zinc-800/80 px-2 py-1 shadow-2xl flex items-center justify-around" style="padding-bottom: max(0.5rem, env(safe-area-inset-bottom));">
        {{-- Tab 1: Launcher --}}
        <a 
            href="{{ route('scanner.dashboard') }}" 
            class="min-h-[48px] min-w-[56px] flex flex-col items-center justify-center px-2 py-1 rounded-xl active:scale-95 transition-all {{ request()->routeIs('scanner.dashboard') ? 'text-amber-400 font-bold' : 'text-zinc-400 hover:text-zinc-200' }}"
        >
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
            </svg>
            <span class="text-[10px] tracking-tight">Launcher</span>
        </a>

        {{-- Tab 2: Dock Receive --}}
        <a 
            href="{{ route('scanner.receiving') }}" 
            class="min-h-[48px] min-w-[56px] flex flex-col items-center justify-center px-2 py-1 rounded-xl active:scale-95 transition-all {{ request()->routeIs('scanner.receiving') ? 'text-sky-400 font-bold' : 'text-zinc-400 hover:text-zinc-200' }}"
        >
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
            </svg>
            <span class="text-[10px] tracking-tight">Receive</span>
        </a>

        {{-- Tab 3: Guided Putaway --}}
        <a 
            href="{{ route('scanner.putaway') }}" 
            class="min-h-[48px] min-w-[56px] flex flex-col items-center justify-center px-2 py-1 rounded-xl active:scale-95 transition-all {{ request()->routeIs('scanner.putaway') ? 'text-indigo-400 font-bold' : 'text-zinc-400 hover:text-zinc-200' }}"
        >
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
            </svg>
            <span class="text-[10px] tracking-tight">Putaway</span>
        </a>

        {{-- Tab 4: Hopper Feed --}}
        <a 
            href="{{ route('scanner.hopper') }}" 
            class="min-h-[48px] min-w-[56px] flex flex-col items-center justify-center px-2 py-1 rounded-xl active:scale-95 transition-all {{ request()->routeIs('scanner.hopper') ? 'text-cyan-400 font-bold' : 'text-zinc-400 hover:text-zinc-200' }}"
        >
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
            </svg>
            <span class="text-[10px] tracking-tight">Hopper</span>
        </a>

        {{-- Tab 5: Quick Mode Switcher Drawer Trigger --}}
        <button 
            type="button" 
            onclick="toggleModeDrawer()" 
            class="min-h-[48px] min-w-[56px] flex flex-col items-center justify-center px-2 py-1 rounded-xl active:scale-95 transition-all text-zinc-400 hover:text-amber-400"
            aria-label="All Modes Menu"
        >
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <span class="text-[10px] tracking-tight">All Modes</span>
        </button>
    </nav>

    {{-- Slide-up Quick Mode Switcher Drawer --}}
    <div id="mode-drawer" class="fixed inset-0 z-50 hidden">
        <!-- Backdrop overlay -->
        <div onclick="toggleModeDrawer()" class="absolute inset-0 bg-black/75 backdrop-blur-sm transition-opacity"></div>
        
        <!-- Drawer Content Panel -->
        <div class="absolute bottom-0 inset-x-0 bg-[#18181b] rounded-t-3xl p-5 border-t border-zinc-800 shadow-2xl max-w-lg mx-auto transform transition-transform" style="padding-bottom: max(1.5rem, env(safe-area-inset-bottom));">
            <div class="w-12 h-1.5 bg-zinc-700 rounded-full mx-auto mb-4"></div>
            
            <div class="flex items-center justify-between pb-3 mb-3 border-b border-zinc-800">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider">Fast Mode Switcher</h3>
                <button onclick="toggleModeDrawer()" class="min-w-[44px] min-h-[44px] -mr-2 text-zinc-400 hover:text-white flex items-center justify-center text-lg">&times;</button>
            </div>

            <div class="grid grid-cols-2 gap-2.5">
                <a href="{{ route('scanner.receiving') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-sky-500/15 text-sky-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Dock Receiving</span>
                </a>

                <a href="{{ route('scanner.putaway') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-indigo-500/15 text-indigo-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Guided Putaway</span>
                </a>

                <a href="{{ route('scanner.staging') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-emerald-500/15 text-emerald-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Machine Staging</span>
                </a>

                <a href="{{ route('scanner.regrind') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-amber-500/15 text-amber-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Regrind Scrap</span>
                </a>

                <a href="{{ route('scanner.cycle-count') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-purple-500/15 text-purple-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Stock Opname</span>
                </a>

                <a href="{{ route('scanner.shipping') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-rose-500/15 text-rose-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Truck Loading</span>
                </a>

                <a href="{{ route('scanner.hopper') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-cyan-500/15 text-cyan-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Hopper Feed</span>
                </a>

                <a href="{{ route('scanner.gate-pass') }}" class="flex items-center space-x-2.5 p-3 rounded-xl bg-[#222228] active:scale-95 transition text-left">
                    <span class="p-2 rounded-lg bg-teal-500/15 text-teal-400 shrink-0">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
                    </span>
                    <span class="text-xs font-bold text-white">Security Gate</span>
                </a>
            </div>

            <div class="mt-4 pt-3 border-t border-zinc-800 text-center">
                <a href="/admin" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-amber-400 font-bold hover:underline">
                    <span>Open Desktop Admin Console</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>
    </div>
    @endauth

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
            toast.style.backgroundColor = isSuccess ? 'rgba(6, 78, 59, 0.95)' : 'rgba(159, 18, 57, 0.95)';
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

        // 4. Mode Drawer Toggle
        function toggleModeDrawer() {
            const drawer = document.getElementById('mode-drawer');
            if (!drawer) return;
            drawer.classList.toggle('hidden');
        }
    </script>
    @stack('scripts')
</body>
</html>
