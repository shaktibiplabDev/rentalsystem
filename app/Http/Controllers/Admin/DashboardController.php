<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rental;
use App\Models\Customer;
use App\Models\Vehicle;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get all shops (users with role 'user')
        $shops = User::where('role', 'user')->orderBy('created_at', 'desc')->get();
        
        // Basic totals
        $totalShops = $shops->count();
        $totalWallet = $shops->sum('wallet_balance');
        
        // Rental stats
        $totalRentals = Rental::count();
        $activeRentals = Rental::where('status', 'active')->count();
        $totalRevenue = Rental::where('status', 'completed')->sum('total_price') ?? 0;
        
        // Verification stats (from your actual data)
        $totalVerifications = Rental::whereNotNull('verification_completed_at')->count();
        $freshVerifications = Rental::where('is_verification_cached', false)->count();
        $cachedVerifications = Rental::where('is_verification_cached', true)->count();
        
        // Platform profit: ₹1 per fresh, ₹3 per cached
        $platformProfit = ($freshVerifications * 1) + ($cachedVerifications * 3);
        
        // Vehicle stats
        $totalVehicles = Vehicle::count();
        $availableVehicles = Vehicle::where('status', 'available')->count();
        $onRentVehicles = Vehicle::where('status', 'on_rent')->count();
        
        // Growth (last 30 days vs previous 30 days)
        $last30Days = Rental::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $prev30Days = Rental::whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])->count();
        $growth = $prev30Days > 0 ? round(($last30Days - $prev30Days) / $prev30Days * 100, 1) : ($last30Days > 0 ? 100 : 0);
        
        // Top customers
        $topCustomers = Customer::withCount('rentals')->orderBy('rentals_count', 'desc')->limit(5)->get();
        
        return view('admin.dashboard', compact(
            'shops', 'totalShops', 'totalWallet', 'totalRentals', 'activeRentals',
            'totalRevenue', 'totalVerifications', 'freshVerifications', 'cachedVerifications',
            'platformProfit', 'totalVehicles', 'availableVehicles', 'onRentVehicles',
            'growth', 'topCustomers'
        ));
    }
}