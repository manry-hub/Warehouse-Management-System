@extends('scanner.layout')

@section('title', 'Scanner Launcher')

@section('content')
<div class="space-y-3 pb-4">
    <!-- Active Shift Status Bar (Grayish, borderless) -->
    <div class="p-3.5 rounded-xl flex items-center justify-between shadow-sm" style="background-color: #18181b; border: none;">
        <div class="flex items-center space-x-2.5">
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
            <div>
                <span class="text-xs font-bold text-white uppercase tracking-wider">Plant WH-MAIN</span>
                <p class="text-[10px] text-gray-400">Scanner Ready • Laser Listening</p>
            </div>
        </div>
        <div class="text-right">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold text-amber-400" style="background-color: rgba(245, 158, 11, 0.15);">
                Shift A
            </span>
        </div>
    </div>

    <!-- Mode Cards Grid (High contrast, solid grayish fills, oversized for gloved hands) -->
    <div class="grid grid-cols-2 gap-2.5">

        <!-- 1. Dock Receiving -->
        <a href="{{ route('scanner.receiving') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-sky-400" style="background-color: rgba(14, 165, 233, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 1</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-sky-300">Dock Receiving</h3>
                <p class="text-[10px] text-gray-400">PO intake & label print</p>
            </div>
        </a>

        <!-- 2. Guided Putaway -->
        <a href="{{ route('scanner.putaway') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-indigo-400" style="background-color: rgba(99, 102, 241, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 2</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-indigo-300">Guided Putaway</h3>
                <p class="text-[10px] text-gray-400">Rack location transfer</p>
            </div>
        </a>

        <!-- 3. Machine Staging -->
        <a href="{{ route('scanner.staging') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-emerald-400" style="background-color: rgba(16, 185, 129, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 3</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-emerald-300">Machine Staging</h3>
                <p class="text-[10px] text-gray-400">FEFO hopper feed</p>
            </div>
        </a>

        <!-- 4. Regrind Scrap Intake -->
        <a href="{{ route('scanner.regrind') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-amber-400" style="background-color: rgba(245, 158, 11, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 4</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-amber-300">Regrind Recycling</h3>
                <p class="text-[10px] text-gray-400">Granulated scrap return</p>
            </div>
        </a>

        <!-- 5. Blind Stock Opname -->
        <a href="{{ route('scanner.cycle-count') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-purple-400" style="background-color: rgba(168, 85, 247, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 5</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-purple-300">Blind Cycle Count</h3>
                <p class="text-[10px] text-gray-400">Stock opname audit</p>
            </div>
        </a>

        <!-- 6. Outbound Truck Loading -->
        <a href="{{ route('scanner.shipping') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-rose-400" style="background-color: rgba(244, 63, 94, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 6</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-rose-300">Truck Loading</h3>
                <p class="text-[10px] text-gray-400">DO carton verification</p>
            </div>
        </a>

        <!-- 7. Hopper Feeding & Verification -->
        <a href="{{ route('scanner.hopper') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-cyan-400" style="background-color: rgba(6, 182, 212, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 7</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-cyan-300">Hopper Feed</h3>
                <p class="text-[10px] text-gray-400">Recipe verify & return</p>
            </div>
        </a>

        <!-- 8. Security Gate Pass -->
        <a href="{{ route('scanner.gate-pass') }}" class="group relative flex flex-col justify-between p-4 rounded-2xl active:scale-[0.98] transition-all shadow-md min-h-[115px]" style="background-color: #1e1e24; border: none;">
            <div class="flex items-start justify-between">
                <div class="p-2.5 rounded-xl text-teal-400" style="background-color: rgba(20, 184, 166, 0.15);">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                    </svg>
                </div>
                <span class="text-[9px] font-bold text-gray-400 px-2 py-0.5 rounded" style="background-color: #141417;">MODE 8</span>
            </div>
            <div class="mt-2.5">
                <h3 class="text-sm font-bold text-white group-hover:text-teal-300">Security Gate</h3>
                <p class="text-[10px] text-gray-400">Driver e-sign clearance</p>
            </div>
        </a>

    </div>

    <!-- Quick Audio Test Bar (Grayish, borderless) -->
    <div class="pt-3 flex items-center justify-center space-x-3 text-xs text-gray-400">
        <span>Audio Test:</span>
        <button type="button" onclick="window.ScannerAudio.playSuccess()" class="px-3 py-1.5 rounded-lg text-emerald-300 active:scale-95 transition" style="background-color: rgba(6, 78, 59, 0.6); border: none;">
            Chime (OK)
        </button>
        <button type="button" onclick="window.ScannerAudio.playError()" class="px-3 py-1.5 rounded-lg text-rose-300 active:scale-95 transition" style="background-color: rgba(159, 18, 57, 0.6); border: none;">
            Buzz (Error)
        </button>
    </div>
</div>
@endsection
