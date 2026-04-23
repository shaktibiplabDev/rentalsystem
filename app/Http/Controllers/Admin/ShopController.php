<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Log;

class ShopController extends Controller
{
    public function index()
    {
        $shops = User::where('role', 'user')
            ->withCount(['rentals', 'vehicles'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $totalShops = User::where('role', 'user')->count();
        $shops = User::where('role', 'user')->withCount(['rentals', 'vehicles'])->paginate(20);

        return view('admin.shops.index', compact('shops', 'totalShops'));
    }

    public function show($id)
    {
        $shop = User::where('role', 'user')->findOrFail($id);

        // Get all rentals for this shop
        $allRentals = Rental::where('user_id', $id)->get();
        $completedRentals = $allRentals->where('status', 'completed');

        // Calculate stats
        $stats = [
            'total_rentals' => $allRentals->count(),
            'completed_rentals' => $completedRentals->count(),
            'total_earnings' => $completedRentals->sum('total_price'),
            'verification_fees' => $allRentals->sum('verification_fee_deducted'),
            'active_rentals' => $allRentals->where('status', 'active')->count(),
            'cancelled_rentals' => $allRentals->where('status', 'cancelled')->count(),
            'verifications' => $allRentals->whereNotNull('verification_completed_at')->count(),
            'fresh_verifications' => $allRentals->where('is_verification_cached', false)->count(),
            'cached_verifications' => $allRentals->where('is_verification_cached', true)->count(),
            'platform_profit' => ($allRentals->where('is_verification_cached', false)->count() * 1) +
                                ($allRentals->where('is_verification_cached', true)->count() * 3),
        ];

        // Get paginated rentals for display
        $rentals = Rental::where('user_id', $id)
            ->with(['vehicle', 'customer'])
            ->latest()
            ->paginate(15);

        // Get vehicles
        $vehicles = Vehicle::where('user_id', $id)->get();

        return view('admin.shops.show', compact('shop', 'rentals', 'vehicles', 'stats'));
    }

    /**
     * Get shop details as HTML partial for dashboard
     */
    /**
     * Get shop details as HTML partial for dashboard
     */
    public function details($id)
    {
        $shop = User::where('role', 'user')->findOrFail($id);

        // Load all rentals for this shop
        $shopRentals = Rental::where('user_id', $id)->get();
        $verifications = $shopRentals->whereNotNull('verification_completed_at');

        // Calculate stats - set these as properties on the shop object
        $shop->total_rentals = $shopRentals->count();
        $shop->active_rentals = $shopRentals->where('status', 'active')->count();
        $shop->completed_rentals = $shopRentals->where('status', 'completed')->count();
        $shop->cancelled_rentals = $shopRentals->where('status', 'cancelled')->count();
        $shop->verifications = $verifications->count();
        $shop->fresh_verifications = $verifications->where('is_verification_cached', false)->count();
        $shop->cached_verifications = $verifications->where('is_verification_cached', true)->count();
        $shop->total_income = $shopRentals->sum('total_price');
        $shop->profit_from_verifications = ($shop->fresh_verifications * 1) + ($shop->cached_verifications * 3);

        // Get recent rentals with vehicle and customer info
        $recentRentals = $shopRentals->sortByDesc('created_at')->take(5);
        $shop->recent_rentals = $recentRentals;

        // Get fleet vehicles
        $shop->fleet_vehicles = Vehicle::where('user_id', $id)->get();

        // Debug: Log to check if data is being fetched
        Log::info('Shop details fetched', [
            'shop_id' => $id,
            'shop_name' => $shop->name,
            'total_rentals' => $shop->total_rentals,
            'verifications' => $shop->verifications,
            'recent_rentals_count' => $recentRentals->count(),
        ]);

        return view('admin.partials.shop_detail', compact('shop'));
    }
}
