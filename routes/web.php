<?php

use App\Http\Controllers\ScannerWebController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/scanner-manifest.json', function () {
    return response()->json(json_decode(file_get_contents(public_path('scanner-manifest.json')), true));
});

// Rugged Mobile Handheld Scanner Web Application (Zebra TC26 / Honeywell EDA51)
Route::prefix('scanner')->group(function () {
    Route::get('/', [ScannerWebController::class, 'index'])->name('scanner.index');
    Route::get('/login', [ScannerWebController::class, 'login'])->name('login');
    Route::post('/login', [ScannerWebController::class, 'authenticate'])->name('scanner.login.submit');
    Route::post('/logout', [ScannerWebController::class, 'logout'])->name('scanner.logout');

    Route::middleware('auth')->name('scanner.')->group(function () {
        Route::get('/dashboard', [ScannerWebController::class, 'dashboard'])->name('dashboard');
        Route::get('/receiving', [ScannerWebController::class, 'receiving'])->name('receiving');
        Route::get('/putaway', [ScannerWebController::class, 'putaway'])->name('putaway');
        Route::get('/staging', [ScannerWebController::class, 'staging'])->name('staging');
        Route::get('/regrind', [ScannerWebController::class, 'regrind'])->name('regrind');
        Route::get('/cycle-count', [ScannerWebController::class, 'cycleCount'])->name('cycle-count');
        Route::get('/shipping', [ScannerWebController::class, 'shipping'])->name('shipping');
        Route::get('/hopper', [ScannerWebController::class, 'hopper'])->name('hopper');
        Route::get('/gate-pass', [ScannerWebController::class, 'gatePass'])->name('gate-pass');
        Route::get('/batch-picking', [ScannerWebController::class, 'batchPicking'])->name('batch-picking');
        Route::get('/replenishment', [ScannerWebController::class, 'replenishment'])->name('replenishment');
        Route::get('/returns', [ScannerWebController::class, 'returns'])->name('returns');
    });
});
