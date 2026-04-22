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

        return view('admin.shops.index', compact('shops'));
    }

    public function show($id)
    {
        $shop = User::where('role', 'user')->findOrFail($id);

        if (request()->ajax()) {
            $rentals = Rental::where('user_id', $id)->get();
            $verifications = $rentals->whereNotNull('verification_completed_at');
            $newVerifs = $verifications->where('is_verification_cached', false)->count();
            $repeatVerifs = $verifications->where('is_verification_cached', true)->count();
            $income = ($newVerifs * 1) + ($repeatVerifs * 3);
            $fleet = Vehicle::where('user_id', $id)->get()->map(fn ($v) => "<span class='chip'><i class='fas fa-car'></i>{$v->name}</span>")->join('');

            // Monthly stats
            $feb = Rental::where('user_id', $id)->whereMonth('created_at', 2)->whereYear('created_at', 2026)->count();
            $mar = Rental::where('user_id', $id)->whereMonth('created_at', 3)->whereYear('created_at', 2026)->count();

            // Active and completed rentals
            $activeRentals = $rentals->where('status', 'active')->count();
            $completedRentals = $rentals->where('status', 'completed')->count();

            return response()->json([
                'name' => $shop->name,
                'email' => $shop->email,
                'gst_number' => $shop->gst_number,
                'wallet' => number_format($shop->wallet_balance, 2),
                'verifications' => $verifications->count(),
                'fresh_verifications' => $newVerifs,
                'cached_verifications' => $repeatVerifs,
                'income' => number_format($income, 2),
                'total_income' => number_format($rentals->sum('total_price'), 2),
                'active_rentals' => $activeRentals,
                'completed_rentals' => $completedRentals,
                'total_rentals' => $rentals->count(),
                'fleet' => $fleet,
                'feb' => $feb,
                'mar' => $mar,
            ]);
        }

        $rentals = Rental::where('user_id', $id)->with(['vehicle', 'customer'])->latest()->paginate(15);
        $vehicles = Vehicle::where('user_id', $id)->get();
        $totalEarnings = Rental::where('user_id', $id)->where('status', 'completed')->sum('total_price');

        return view('admin.shops.show', compact('shop', 'rentals', 'vehicles', 'totalEarnings'));
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
