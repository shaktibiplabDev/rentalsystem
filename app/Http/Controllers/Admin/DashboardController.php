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
        
        // Enhance shops with their rental stats
        foreach ($shops as $shop) {
            $shopRentals = Rental::where('user_id', $shop->id)->get();
            $verifications = $shopRentals->whereNotNull('verification_completed_at');
            $shop->total_rentals = $shopRentals->count();
            $shop->active_rentals = $shopRentals->where('status', 'active')->count();
            $shop->completed_rentals = $shopRentals->where('status', 'completed')->count();
            $shop->verifications = $verifications->count();
            $shop->fresh_verifications = $verifications->where('is_verification_cached', false)->count();
            $shop->cached_verifications = $verifications->where('is_verification_cached', true)->count();
            $shop->total_income = $shopRentals->sum('total_price');
            // Platform profit from this shop (₹1 per fresh, ₹3 per cached)
            $shop->platform_profit = ($shop->fresh_verifications * 1) + ($shop->cached_verifications * 3);
            $shop->recent_rentals = $shopRentals->sortByDesc('created_at')->take(5);
            $shop->fleet_vehicles = Vehicle::where('user_id', $shop->id)->get();
        }
        
        // Platform totals (ONLY platform revenue from verifications)
        $totalShops = $shops->count();
        $totalWallet = $shops->sum('wallet_balance');
        $totalRentals = Rental::count();
        $activeRentals = Rental::where('status', 'active')->count();
        
        // PLATFORM REVENUE = profit from verifications (NOT shop earnings)
        $totalVerifications = Rental::whereNotNull('verification_completed_at')->count();
        $freshVerifications = Rental::where('is_verification_cached', false)->count();
        $cachedVerifications = Rental::where('is_verification_cached', true)->count();
        $platformRevenue = ($freshVerifications * 1) + ($cachedVerifications * 3);
        
        // Vehicle stats
        $totalVehicles = Vehicle::count();
        $availableVehicles = Vehicle::where('status', 'available')->count();
        $onRentVehicles = Vehicle::where('status', 'on_rent')->count();
        
        // Growth in platform revenue (not shop earnings)
        $last30DaysRevenue = Rental::where('created_at', '>=', Carbon::now()->subDays(30))
            ->get()
            ->sum(function($r) {
                return $r->is_verification_cached ? 3 : ($r->verification_completed_at ? 1 : 0);
            });
        $prev30DaysRevenue = Rental::whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])
            ->get()
            ->sum(function($r) {
                return $r->is_verification_cached ? 3 : ($r->verification_completed_at ? 1 : 0);
            });
        $growth = $prev30DaysRevenue > 0 ? round(($last30DaysRevenue - $prev30DaysRevenue) / $prev30DaysRevenue * 100, 1) : ($last30DaysRevenue > 0 ? 100 : 0);
        
        // Top customers (by number of rentals)
        $topCustomers = Customer::withCount('rentals')->orderBy('rentals_count', 'desc')->limit(5)->get()->map(function($customer) {
            if ($customer->address) {
                $parts = explode(',', $customer->address);
                $customer->city = count($parts) > 1 ? trim($parts[count($parts)-2]) : trim($parts[0]);
            } else {
                $customer->city = '—';
            }
            return $customer;
        });
        
        // Default selected shop (first one)
        $selectedShop = $shops->first();
        
        return view('admin.dashboard', compact(
            'shops', 'selectedShop', 'totalShops', 'totalWallet', 'totalRentals',
            'activeRentals', 'platformRevenue', 'totalVerifications',
            'freshVerifications', 'cachedVerifications', 'totalVehicles',
            'availableVehicles', 'onRentVehicles', 'growth', 'topCustomers'
        ));
    }
}