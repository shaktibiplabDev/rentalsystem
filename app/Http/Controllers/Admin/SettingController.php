<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    public function index()
    {
        $verificationPrice = Setting::where('key', 'verification_price')->value('value') ?? 3;
        $leaseThreshold = Setting::where('key', 'lease_threshold_minutes')->value('value') ?? 60;
        
        return view('admin.settings.index', compact('verificationPrice', 'leaseThreshold'));
    }
    
    public function update(Request $request)
    {
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
    }
}