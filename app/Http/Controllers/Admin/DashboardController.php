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
        // Get all shops (users with role 'user' - NOT 'owner' from your schema)
        // Your schema shows role = 'user' for shops (John has role 'user')
        $shops = User::where('role', 'user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Calculate real totals
        $totalWallet = $shops->sum('wallet_balance');
        $totalShops = $shops->count();
        
        // Rental statistics
        $totalRentals = Rental::count();
        $activeRentals = Rental::where('status', 'active')->count();
        $completedRentals = Rental::where('status', 'completed')->count();
        $totalRevenue = Rental::where('status', 'completed')->sum('total_price');
        
        // Verification statistics
        $totalVerifications = Rental::whereNotNull('verification_completed_at')->count();
        $cachedVerifications = Rental::where('is_verification_cached', true)->count();
        $freshVerifications = Rental::where('is_verification_cached', false)->count();
        
        // Platform profit from verifications (₹1 per fresh, ₹3 per cached)
        $platformProfit = ($freshVerifications * 1) + ($cachedVerifications * 3);
        
        // Total vehicles across all shops
        $totalVehicles = Vehicle::count();
        $availableVehicles = Vehicle::where('status', 'available')->count();
        $onRentVehicles = Vehicle::where('status', 'on_rent')->count();
        
        // Wallet statistics
        $totalWalletCredits = WalletTransaction::where('type', 'credit')->where('status', 'completed')->sum('amount');
        $totalWalletDebits = WalletTransaction::where('type', 'debit')->where('status', 'completed')->sum('amount');
        
        // Get top 5 customers by rental count
        $topCustomers = Customer::withCount('rentals')
            ->orderBy('rentals_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($customer) {
                // Extract city from address if exists
                $city = '—';
                if ($customer->address) {
                    $parts = explode(',', $customer->address);
                    $city = count($parts) > 1 ? trim($parts[count($parts)-2]) : trim($parts[0]);
                }
                $customer->city = $city;
                return $customer;
            });
        
        // Month-over-month growth (compare last 30 days vs previous 30 days)
        $last30DaysRentals = Rental::where('created_at', '>=', Carbon::now()->subDays(30))->count();
        $previous30DaysRentals = Rental::whereBetween('created_at', [Carbon::now()->subDays(60), Carbon::now()->subDays(30)])->count();
        $growth = $previous30DaysRentals > 0 
            ? round(($last30DaysRentals - $previous30DaysRentals) / $previous30DaysRentals * 100, 1)
            : ($last30DaysRentals > 0 ? 100 : 0);
        
        return view('admin.dashboard', compact(
            'shops',
            'totalWallet',
            'totalShops',
            'totalRentals',
            'activeRentals',
            'completedRentals',
            'totalRevenue',
            'totalVerifications',
            'platformProfit',
            'totalVehicles',
            'availableVehicles',
            'onRentVehicles',
            'totalWalletCredits',
            'totalWalletDebits',
            'topCustomers',
            'growth'
        ));
    }
}