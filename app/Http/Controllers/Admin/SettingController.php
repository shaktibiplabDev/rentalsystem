<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    public function index()
    {
        try {
            $verificationPrice = Setting::where('key', 'verification_price')->value('value') ?? 3;
            $leaseThreshold = Setting::where('key', 'lease_threshold_minutes')->value('value') ?? 60;
            $totalShops = User::where('role', 'user')->count();

            return view('admin.settings.index', compact('verificationPrice', 'leaseThreshold', 'totalShops'));

        } catch (\Exception $e) {
            Log::error('Settings index error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load settings. Please try again.');
        }
    }

    public function update(Request $request)
    {
        try {
            $request->validate([
                'verification_price' => 'required|numeric|min:0|max:100',
                'lease_threshold_minutes' => 'required|integer|min:1|max:120',
            ]);

            Setting::updateOrCreate(
                ['key' => 'verification_price'],
                ['value' => $request->verification_price]
            );

            Setting::updateOrCreate(
                ['key' => 'lease_threshold_minutes'],
                ['value' => $request->lease_threshold_minutes]
            );

            return redirect()->back()->with('success', 'Settings updated successfully.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()->back()
                ->withErrors($e->errors())
                ->withInput();
        } catch (\Exception $e) {
            Log::error('Settings update error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update settings. Please try again.')
                ->withInput();
        }
    }
}
