<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class MapController extends Controller
{
    public function index()
    {
        // Get all shops (users with role 'user' or 'owner')
        $shops = User::where(function($q) {
                $q->where('role', 'user')->orWhere('role', 'owner');
            })
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'wallet_balance', 'latitude', 'longitude', 'business_display_address')
            ->get();

        return view('admin.map', compact('shops'));
    }
}