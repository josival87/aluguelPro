<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Charge;
use App\Models\Client;
use App\Models\Lease;
use App\Models\Property;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $month = now()->startOfMonth();
        $monthCharges = Charge::query()->whereDate('reference_month', $month);
        $totalProperties = Property::count();
        $metrics = [
            'new_leases' => Lease::whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'received' => (clone $monthCharges)->where('status', 'paid')->sum('amount'),
            'receivable' => (clone $monthCharges)->where('status', 'open')->sum('amount'),
            'properties' => $totalProperties,
            'leases' => Lease::whereIn('status', ['active', 'awaiting_completion', 'awaiting_signatures'])->count(),
            'clients' => Client::count(),
            'occupancy' => $totalProperties ? round(Property::where('status', 'rented')->count() / $totalProperties * 100, 1) : 0,
        ];
        $pendingLeases = Lease::with('client', 'property')->where('status', 'awaiting_completion')->latest()->limit(6)->get();
        $upcoming = Charge::with('lease.property', 'client')->where('status', 'open')->orderBy('due_date')->limit(7)->get();
        return view('admin.dashboard', compact('metrics', 'pendingLeases', 'upcoming'));
    }
}
