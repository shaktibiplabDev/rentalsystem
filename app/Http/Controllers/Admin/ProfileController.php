<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

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

    public function update(Request $request)
    {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email,' . $user->id,
                'phone' => 'nullable|string|max:20',
            ]);

            $user->update($validated);

            return redirect()->route('admin.profile')->with('success', 'Profile updated successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.profile')
                ->with('error', 'Validation failed: ' . $e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Profile update failed: ' . $e->getMessage());
            return redirect()->route('admin.profile')
                ->with('error', 'Failed to update profile. Please try again.');
        }
    }

    public function updatePassword(Request $request)
    {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'current_password' => 'required',
                'password' => 'required|min:8|confirmed',
            ]);

            // Verify current password
            if (!Hash::check($validated['current_password'], $user->password)) {
                return redirect()->route('admin.profile')
                    ->with('error', 'Current password is incorrect.');
            }

            $user->update([
                'password' => Hash::make($validated['password'])
            ]);

            return redirect()->route('admin.profile')
                ->with('success', 'Password changed successfully!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->route('admin.profile')
                ->with('error', 'Validation failed: ' . $e->getMessage())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Password change failed: ' . $e->getMessage());
            return redirect()->route('admin.profile')
                ->with('error', 'Failed to change password. Please try again.');
        }
    }
}
