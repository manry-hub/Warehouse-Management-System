@extends('scanner.layout')

@section('title', 'Scanner Launcher')

@section('content')
<div class="space-y-3 pb-4">
    <!-- Active Shift Status Bar -->
    <div class="bg-gradient-to-r from-slate-800 to-slate-800/80 border border-slate-700/80 rounded-xl p-3 flex items-center justify-between shadow-sm">
        <div class="flex items-center space-x-2.5">
            <div class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-pulse"></div>
            <div>
                <span class="text-xs font-bold text-white uppercase tracking-wider">Plant WH-MAIN</span>
                <p class="text-[10px] text-slate-400">Scanner Ready • Wedge Listening</p>
            </div>
        </div>
        <div class="text-right">
            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-sky-500/20 text-sky-400 border border-sky-500/30">
                Shift A
            </span>
        </div>
    </div>

    <!-- Mode Cards Grid (High contrast, oversized for gloved hands) -->
    <div class="grid grid-cols-2 gap-2.5">

        <!-- 1. Dock Receiving -->
        <a href="{{ route('scanner.receiving') }}" class="group relative flex flex-col justify-between p-3.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 active:border-sky-500 active:scale-[0.98] transition-all shadow-md min-h-[110px]">
            <div class="flex items-start justify-between">
                <div class="p-2 rounded-lg bg-sky-500/10 text-sky-400 group-hover:bg-sky-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-900/60 px-1.5 py-0.5 rounded">MODE 1</span>
            </div>
            <div class="mt-2">
                <h3 class="text-sm font-bold text-white group-hover:text-sky-300">Dock Receiving</h3>
                <p class="text-[10px] text-slate-400">PO intake & label print</p>
            </div>
        </a>

        <!-- 2. Guided Putaway -->
        <a href="{{ route('scanner.putaway') }}" class="group relative flex flex-col justify-between p-3.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 active:border-indigo-500 active:scale-[0.98] transition-all shadow-md min-h-[110px]">
            <div class="flex items-start justify-between">
                <div class="p-2 rounded-lg bg-indigo-500/10 text-indigo-400 group-hover:bg-indigo-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                    </svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-900/60 px-1.5 py-0.5 rounded">MODE 2</span>
            </div>
            <div class="mt-2">
                <h3 class="text-sm font-bold text-white group-hover:text-indigo-300">Guided Putaway</h3>
                <p class="text-[10px] text-slate-400">Rack location transfer</p>
            </div>
        </a>

        <!-- 3. Machine Staging -->
        <a href="{{ route('scanner.staging') }}" class="group relative flex flex-col justify-between p-3.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 active:border-emerald-500 active:scale-[0.98] transition-all shadow-md min-h-[110px]">
            <div class="flex items-start justify-between">
                <div class="p-2 rounded-lg bg-emerald-500/10 text-emerald-400 group-hover:bg-emerald-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                    </svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-900/60 px-1.5 py-0.5 rounded">MODE 3</span>
            </div>
            <div class="mt-2">
                <h3 class="text-sm font-bold text-white group-hover:text-emerald-300">Machine Staging</h3>
                <p class="text-[10px] text-slate-400">FEFO hopper replenishment</p>
            </div>
        </a>

        <!-- 4. Regrind Scrap Intake -->
        <a href="{{ route('scanner.regrind') }}" class="group relative flex flex-col justify-between p-3.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 active:border-amber-500 active:scale-[0.98] transition-all shadow-md min-h-[110px]">
            <div class="flex items-start justify-between">
                <div class="p-2 rounded-lg bg-amber-500/10 text-amber-400 group-hover:bg-amber-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-900/60 px-1.5 py-0.5 rounded">MODE 4</span>
            </div>
            <div class="mt-2">
                <h3 class="text-sm font-bold text-white group-hover:text-amber-300">Regrind Recycling</h3>
                <p class="text-[10px] text-slate-400">Granulated runner scrap</p>
            </div>
        </a>

        <!-- 5. Blind Stock Opname -->
        <a href="{{ route('scanner.cycle-count') }}" class="group relative flex flex-col justify-between p-3.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 active:border-purple-500 active:scale-[0.98] transition-all shadow-md min-h-[110px]">
            <div class="flex items-start justify-between">
                <div class="p-2 rounded-lg bg-purple-500/10 text-purple-400 group-hover:bg-purple-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                    </svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-900/60 px-1.5 py-0.5 rounded">MODE 5</span>
            </div>
            <div class="mt-2">
                <h3 class="text-sm font-bold text-white group-hover:text-purple-300">Blind Cycle Count</h3>
                <p class="text-[10px] text-slate-400">Stock opname audit</p>
            </div>
        </a>

        <!-- 6. Outbound Truck Loading -->
        <a href="{{ route('scanner.shipping') }}" class="group relative flex flex-col justify-between p-3.5 rounded-xl bg-slate-800 hover:bg-slate-750 border border-slate-700 active:border-rose-500 active:scale-[0.98] transition-all shadow-md min-h-[110px]">
            <div class="flex items-start justify-between">
                <div class="p-2 rounded-lg bg-rose-500/10 text-rose-400 group-hover:bg-rose-500/20">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                    </svg>
                </div>
                <span class="text-[10px] font-bold text-slate-400 bg-slate-900/60 px-1.5 py-0.5 rounded">MODE 6</span>
            </div>
            <div class="mt-2">
                <h3 class="text-sm font-bold text-white group-hover:text-rose-300">Truck Loading</h3>
                <p class="text-[10px] text-slate-400">DO carton verification</p>
            </div>
        </a>

    </div>

    <!-- Quick Audio Test Bar -->
    <div class="pt-2 flex items-center justify-center space-x-3 text-xs text-slate-400">
        <span>Hardware Test:</span>
        <button type="button" onclick="window.ScannerAudio.playSuccess()" class="px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-emerald-400 active:bg-slate-700">
            Chime (OK)
        </button>
        <button type="button" onclick="window.ScannerAudio.playError()" class="px-2.5 py-1 rounded-md bg-slate-800 border border-slate-700 text-rose-400 active:bg-slate-700">
            Buzz (Error)
        </button>
    </div>
</div>
@endsection
