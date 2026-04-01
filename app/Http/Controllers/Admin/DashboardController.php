<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;

class DashboardController extends Controller
{
    public function index()
    {
        $totalUsers = User::count();
        $totalVehicles = Vehicle::count();
        $totalRentals = Rental::count();
        $activeRentals = Rental::where('status', 'active')->count();
        $totalEarnings = Rental::where('status', 'completed')->sum('total_price');

        return view('admin.dashboard', compact(
            'totalUsers',
            'totalVehicles',
            'totalRentals',
            'activeRentals',
            'totalEarnings'
        ));
    }
}