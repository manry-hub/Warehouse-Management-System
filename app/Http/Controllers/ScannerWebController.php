<?php

namespace App\Http\Controllers;

use App\Models\CycleCountOrder;
use App\Models\DeliveryOrder;
use App\Models\Item;
use App\Models\StagingRequest;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ScannerWebController extends Controller
{
    /**
     * Root scanner gateway.
     */
    public function index(): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('scanner.dashboard');
        }

        return redirect()->route('login');
    }

    /**
     * Mobile login interface.
     */
    public function login(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('scanner.dashboard');
        }

        return view('scanner.login');
    }

    /**
     * Authenticate mobile handheld operator.
     */
    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, true)) {
            $request->session()->regenerate();

            return redirect()->intended(route('scanner.dashboard'))
                ->with('success', 'Terminal authorized. Welcome, '.Auth::user()->name);
        }

        return back()->withErrors([
            'email' => 'Invalid operator badge credentials.',
        ])->onlyInput('email');
    }

    /**
     * Sign out mobile session.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('success', 'Terminal session terminated.');
    }

    /**
     * Operator mode selection launcher.
     */
    public function dashboard(): View
    {
        return view('scanner.dashboard');
    }

    /**
     * Mode 1: Dock Receiving.
     */
    public function receiving(): View
    {
        $items = Item::where('is_active', true)
            ->whereIn('category', [Item::CATEGORY_RAW_RESIN, Item::CATEGORY_MASTERBATCH])
            ->orderBy('sku', 'asc')
            ->get();

        $warehouses = Warehouse::where('is_active', true)->get();

        return view('scanner.modes.receiving', compact('items', 'warehouses'));
    }

    /**
     * Mode 2: Guided Putaway.
     */
    public function putaway(): View
    {
        return view('scanner.modes.putaway');
    }

    /**
     * Mode 3: Machine Staging & Replenishment.
     */
    public function staging(): View
    {
        $stagingRequests = StagingRequest::whereIn('status', [
            StagingRequest::STATUS_PENDING,
            StagingRequest::STATUS_ALLOCATED,
        ])
            ->orderBy('requested_at', 'desc')
            ->get();

        return view('scanner.modes.staging', compact('stagingRequests'));
    }

    /**
     * Mode 4: Regrind Granulator Intake.
     */
    public function regrind(): View
    {
        $items = Item::where('is_active', true)
            ->where('allow_regrind', true)
            ->orderBy('sku', 'asc')
            ->get();

        return view('scanner.modes.regrind', compact('items'));
    }

    /**
     * Mode 5: Blind Stock Opname.
     */
    public function cycleCount(): View
    {
        $countOrders = CycleCountOrder::whereIn('status', [
            CycleCountOrder::STATUS_IN_PROGRESS,
            CycleCountOrder::STATUS_REVIEW,
        ])
            ->with(['zone', 'warehouse'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('scanner.modes.cycle-count', compact('countOrders'));
    }

    /**
     * Mode 6: Truck Loading Manifest.
     */
    public function shipping(): View
    {
        $deliveryOrders = DeliveryOrder::whereIn('status', [
            DeliveryOrder::STATUS_DRAFT,
            DeliveryOrder::STATUS_PICKING,
            DeliveryOrder::STATUS_LOADED,
        ])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('scanner.modes.shipping', compact('deliveryOrders'));
    }

    /**
     * Mode 7: Machine Hopper Staging & Recipe Verification.
     */
    public function hopper(): View
    {
        return view('scanner.modes.hopper');
    }

    /**
     * Mode 8: Outbound Security Gate Pass Clearance.
     */
    public function gatePass(): View
    {
        $pendingOrders = DeliveryOrder::whereIn('status', [
            DeliveryOrder::STATUS_LOADED,
            DeliveryOrder::STATUS_DISPATCHED,
        ])
            ->whereNull('gate_pass_number')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('scanner.modes.gate-pass', compact('pendingOrders'));
    }
}
