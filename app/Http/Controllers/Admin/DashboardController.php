<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rental;

class DashboardController extends Controller
{
    public function index()
    {
        // Get all shops (users with role 'user')
        $shops = User::where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate totals
        $totalWallet = $shops->sum('wallet_balance');
        $totalRentals = Rental::count();
        $totalRevenue = Rental::where('status', 'completed')->sum('total_price');

        return view('admin.dashboard', compact('shops', 'totalWallet', 'totalRentals', 'totalRevenue'));
    }
}