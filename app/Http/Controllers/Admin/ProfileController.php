<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Rental;
use App\Models\WalletTransaction;
use Carbon\Carbon;

class ProfileController extends Controller
{
    public function index()
    {
        // Fix: Use 'user' role instead of 'owner'
        $totalShops = User::where('role', 'user')->count();
        
        $totalCustomers = Customer::count();
        $totalVerifications = Rental::whereNotNull('verification_completed_at')->count();
        
        // Get platform profit
        $freshVerifications = Rental::where('is_verification_cached', false)->count();
        $cachedVerifications = Rental::where('is_verification_cached', true)->count();
        $platformProfit = ($freshVerifications * 1) + ($cachedVerifications * 3);
        
        // Get recent activities from logs
        $activities = [
            [
                'color' => 'var(--green)', 
                'text' => 'Logged in as ' . auth()->user()->name, 
                'time' => now()->format('d M Y, h:i A')
            ],
            [
                'color' => 'var(--accent)', 
                'text' => 'Platform profit: ₹' . number_format($platformProfit, 2), 
                'time' => now()->format('d M Y, h:i A')
            ],
            [
                'color' => 'var(--amber)', 
                'text' => 'Total verifications: ' . $totalVerifications, 
                'time' => now()->format('d M Y, h:i A')
            ],
        ];
        
        return view('admin.profile', compact('totalShops', 'totalCustomers', 'totalVerifications', 'activities'));
    }
}