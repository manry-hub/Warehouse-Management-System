@extends('scanner.layout')

@section('title', 'Scanner Sign In')

@section('content')
<div class="max-w-md mx-auto pt-6 px-1 flex flex-col justify-center">

    <!-- Industrial Terminal Header -->
    <div class="text-center mb-6">
        <div class="inline-flex items-center justify-center w-16 h-16 rounded-2xl bg-sky-500/10 border border-sky-500/20 text-sky-400 mb-3 shadow-inner">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z" />
            </svg>
        </div>
        <h2 class="text-xl font-black text-white tracking-wide uppercase">Handheld Terminal</h2>
        <p class="text-xs text-slate-400 mt-1">Smart Warehouse Management System</p>
    </div>

    <!-- Login Form adhering to modern-web-guidance autofill-sign-in-form -->
    <div class="bg-slate-800 border border-slate-700/80 rounded-2xl p-5 shadow-xl">
        <form method="POST" action="{{ route('scanner.login.submit') }}" class="space-y-4">
            @csrf

            <!-- Operator Email / Badge ID -->
            <div>
                <label for="email" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Operator Email / Badge
                </label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    value="{{ old('email', 'handler@wms.local') }}" 
                    autocomplete="username" 
                    inputmode="email" 
                    spellcheck="false" 
                    required 
                    enterkeyhint="next"
                    placeholder="handler@wms.local"
                    class="laser-focus w-full h-13 px-3.5 rounded-xl bg-slate-900 border border-slate-600 text-white placeholder-slate-500 font-medium focus:outline-none focus:border-sky-500 transition-colors"
                >
                @error('email')
                    <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <!-- Password / PIN -->
            <div>
                <label for="password" class="block text-xs font-bold uppercase tracking-wider text-slate-300 mb-1.5">
                    Security Password
                </label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    value="password"
                    autocomplete="current-password" 
                    required 
                    enterkeyhint="go"
                    placeholder="••••••••"
                    class="laser-focus w-full h-13 px-3.5 rounded-xl bg-slate-900 border border-slate-600 text-white placeholder-slate-500 font-medium focus:outline-none focus:border-sky-500 transition-colors"
                >
                @error('password')
                    <span class="text-xs text-rose-400 mt-1 block">{{ $message }}</span>
                @enderror
            </div>

            <!-- Submit Button (Large touch target) -->
            <button 
                type="submit" 
                class="w-full h-14 mt-2 rounded-xl bg-sky-600 hover:bg-sky-500 active:bg-sky-700 text-white font-bold text-base tracking-wider uppercase shadow-lg shadow-sky-600/30 flex items-center justify-center space-x-2 transition-all active:scale-[0.98]"
            >
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                </svg>
                <span>Authorize Terminal</span>
            </button>
        </form>
    </div>

    <!-- Quick Operator Profiles for Factory Testing -->
    <div class="mt-6 text-center">
        <p class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-2">Preset Badges</p>
        <div class="grid grid-cols-2 gap-2">
            <button type="button" onclick="fillCreds('handler@wms.local', 'password')" class="px-2.5 py-2 rounded-lg bg-slate-800 border border-slate-700 hover:border-slate-600 text-left text-slate-300 active:bg-slate-700">
                <div class="text-xs font-bold text-white">Floor Handler</div>
                <div class="text-[10px] text-slate-400">handler@wms.local</div>
            </button>
            <button type="button" onclick="fillCreds('admin@wms.local', 'password')" class="px-2.5 py-2 rounded-lg bg-slate-800 border border-slate-700 hover:border-slate-600 text-left text-slate-300 active:bg-slate-700">
                <div class="text-xs font-bold text-white">Manager</div>
                <div class="text-[10px] text-slate-400">admin@wms.local</div>
            </button>
        </div>
    </div>
</div>

<script>
    function fillCreds(email, pass) {
        document.getElementById('email').value = email;
        document.getElementById('password').value = pass;
        if (window.ScannerAudio) window.ScannerAudio.playSuccess();
    }
</script>
@endsection
