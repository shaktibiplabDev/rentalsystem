<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;

class MapController extends Controller
{
    public function index()
    {
        // Get all shops (users with role 'user') that have coordinates
        $shops = User::where('role', 'user')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('id', 'name', 'wallet_balance', 'latitude', 'longitude', 'business_display_address', 'business_phone', 'business_email')
            ->get();

        return view('admin.map', compact('shops'));
    }
}