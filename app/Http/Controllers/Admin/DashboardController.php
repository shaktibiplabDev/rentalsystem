<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rental;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index()
    {
        // Get all shops (users with role 'user')
        $shops = User::where('role', 'user')->orderBy('created_at', 'desc')->get();
        
        // Enhance shops with their rental stats
        foreach ($shops as $shop) {
            $shopRentals = Rental::where('user_id', $shop->id)->get();
            $shop->total_rentals = $shopRentals->count();
            $shop->active_rentals = $shopRentals->where('status', 'active')->count();
            $shop->completed_rentals = $shopRentals->where('status', 'completed')->count();
            $shop->verifications = $shopRentals->whereNotNull('verification_completed_at')->count();
            $shop->fresh_verifications = $shopRentals->where('is_verification_cached', false)->count();
            $shop->cached_verifications = $shopRentals->where('is_verification_cached', true)->count();
            $shop->total_income = $shopRentals->sum('total_price');
            $shop->profit_from_verifications = ($shop->fresh_verifications * 1) + ($shop->cached_verifications * 3);
            $shop->recent_rentals = $shopRentals->sortByDesc('created_at')->take(3);
        }
        
        // Platform totals
        $totalShops = $shops->count();
        $totalWallet = $shops->sum('wallet_balance');
        $totalRentals = Rental::count();
        $activeRentals = Rental::where('status', 'active')->count();
        $completedRentals = Rental::where('status', 'completed')->count();
        $totalRevenue = Rental::where('status', 'completed')->sum('total_price') ?? 0;
        
        // Verification stats
        $totalVerifications = Rental::whereNotNull('verification_completed_at')->count();
        $freshVerifications = Rental::where('is_verification_cached', false)->count();
        $cachedVerifications = Rental::where('is_verification_cached', true)->count();
        $platformProfit = ($freshVerifications * 1) + ($cachedVerifications * 3);
        
        // Vehicle stats
        $totalVehicles = Vehicle::count();
        $availableVehicles = Vehicle::where('status', 'available')->count();
        $onRentVehicles = Vehicle::where('status', 'on_rent')->count();
        
        // Wallet stats
        $totalWalletCredits = WalletTransaction::where('type', 'credit')->where('status', 'completed')->sum('amount') ?? 0;
        $totalWalletDebits = WalletTransaction::where('type', 'debit')->where('status', 'completed')->sum('amount') ?? 0;
        
        // Growth
        $last30Days = Rental::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $prev30Days = Rental::whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])->count();
        $growth = $prev30Days > 0 ? round(($last30Days - $prev30Days) / $prev30Days * 100, 1) : ($last30Days > 0 ? 100 : 0);
        
        // Top customers
        $topCustomers = Customer::withCount('rentals')->orderBy('rentals_count', 'desc')->limit(5)->get();
        
        // Default selected shop (first one)
        $selectedShop = $shops->first();
        
        return view('admin.dashboard', compact(
            'shops', 'selectedShop', 'totalShops', 'totalWallet', 'totalRentals',
            'activeRentals', 'completedRentals', 'totalRevenue', 'totalVerifications',
            'freshVerifications', 'cachedVerifications', 'platformProfit',
            'totalVehicles', 'availableVehicles', 'onRentVehicles',
            'totalWalletCredits', 'totalWalletDebits', 'growth', 'topCustomers'
        ));
    }
}