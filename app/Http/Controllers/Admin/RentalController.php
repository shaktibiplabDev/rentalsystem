<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use Illuminate\Http\Request;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        $query = Rental::with(['user', 'vehicle', 'customer']);
        
        if ($request->status) {
            $query->where('status', $request->status);
        }
        
        if ($request->search) {
            $query->whereHas('customer', fn($q) => $q->where('name', 'like', "%{$request->search}%"))
                  ->orWhereHas('vehicle', fn($q) => $q->where('number_plate', 'like', "%{$request->search}%"))
                  ->orWhereHas('user', fn($q) => $q->where('name', 'like', "%{$request->search}%"));
        }
        
        $rentals = $query->latest()->paginate(20);
        
        // Calculate profit per rental
        foreach ($rentals as $rental) {
            if ($rental->verification_completed_at) {
                $rental->platform_profit = $rental->is_verification_cached ? 3 : 1;
            } else {
                $rental->platform_profit = 0;
            }
        }
        
        return view('admin.rentals.index', compact('rentals'));
    }
    
    public function show($id)
    {
        $rental = Rental::with(['user', 'vehicle', 'customer', 'document'])->findOrFail($id);
        
        // Calculate duration
        $durationHours = null;
        if ($rental->start_time && $rental->end_time) {
            $durationHours = round($rental->start_time->diffInHours($rental->end_time), 1);
        } elseif ($rental->start_time && $rental->status === 'active') {
            $durationHours = round($rental->start_time->diffInHours(now()), 1);
        }
        
        return view('admin.rentals.show', compact('rental', 'durationHours'));
    }
    
    public function forceEnd($id)
    {
        $rental = Rental::where('status', 'active')->findOrFail($id);
        
        $rental->update([
            'status' => 'completed',
            'phase' => 'completed',
            'end_time' => now(),
            'return_completed_at' => now(),
        ]);
        
        if ($rental->vehicle) {
            $rental->vehicle->update(['status' => 'available']);
        }
        
        return redirect()->back()->with('success', 'Rental force-ended successfully.');
    }
}