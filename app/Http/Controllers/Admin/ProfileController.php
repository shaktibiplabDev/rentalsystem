<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Rental;
class ProfileController extends Controller
{
    public function index()
    {
        $totalShops = User::where('role', 'user')->count();
        $totalCustomers = Customer::count();

        $verificationQuery = Rental::whereNotNull('verification_completed_at');
        $totalVerifications = (clone $verificationQuery)->count();
        $freshVerifications = (clone $verificationQuery)
            ->where(function ($q) {
                $q->where('is_verification_cached', false)
                    ->orWhereNull('is_verification_cached');
            })
            ->count();
        $cachedVerifications = (clone $verificationQuery)
            ->where('is_verification_cached', true)
            ->count();

        $platformProfit = ($freshVerifications * 1) + ($cachedVerifications * 3);

        $totalRentals = Rental::count();

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
                'color' => 'var(--purple)',
                'text' => "Verification split: {$freshVerifications} fresh · {$cachedVerifications} cached",
                'time' => now()->format('d M Y, h:i A')
            ],
            [
                'color' => 'var(--amber)', 
                'text' => 'Total verifications: ' . $totalVerifications, 
                'time' => now()->format('d M Y, h:i A')
            ],
        ];

        return view('admin.profile', compact(
            'totalShops',
            'totalCustomers',
            'totalVerifications',
            'freshVerifications',
            'cachedVerifications',
            'platformProfit',
            'totalRentals',
            'activities'
        ));
    }
}
