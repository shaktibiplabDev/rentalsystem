<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rental;
use App\Models\Vehicle;

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
            $rentals = Rental::where('user_id', $id)->whereNotNull('verification_completed_at')->get();
            $newVerifs = $rentals->where('is_verification_cached', false)->count();
            $repeatVerifs = $rentals->where('is_verification_cached', true)->count();
            $income = ($newVerifs * 1) + ($repeatVerifs * 3);
            $fleet = Vehicle::where('user_id', $id)->get()->map(fn($v) => "<span class='chip'><i class='fas fa-car'></i>{$v->name}</span>")->join('');
            $feb = Rental::where('user_id', $id)->whereMonth('created_at', 2)->whereYear('created_at', 2026)->count();
            $mar = Rental::where('user_id', $id)->whereMonth('created_at', 3)->whereYear('created_at', 2026)->count();
            
            return response()->json([
                'name' => $shop->name,
                'email' => $shop->email,
                'gst_number' => $shop->gst_number,
                'wallet' => number_format($shop->wallet_balance, 2),
                'verifications' => $rentals->count(),
                'income' => number_format($income, 2),
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
}