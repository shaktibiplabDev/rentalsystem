<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RentalController extends Controller
{
    public function index(Request $request)
    {
        try {
            $query = Rental::with(['user', 'vehicle', 'customer']);
            $totalShops = User::where('role', 'user')->count();

            // Filter by status
            if ($request->status) {
                $query->where('status', $request->status);
            }

            // Filter by shop (user_id)
            if ($request->user_id) {
                $query->where('user_id', $request->user_id);
            }

            // Search by customer name, vehicle name, or number plate
            if ($request->search) {
                $query->where(function($q) use ($request) {
                    $q->whereHas('customer', fn($q2) => $q2->where('name', 'like', "%{$request->search}%"))
                      ->orWhereHas('vehicle', fn($q2) => $q2->where('name', 'like', "%{$request->search}%"))
                      ->orWhereHas('vehicle', fn($q2) => $q2->where('number_plate', 'like', "%{$request->search}%"));
                });
            }

            $rentals = $query->latest()->paginate(20);

            // Calculate summary statistics
            $summary = [
                'total' => Rental::count(),
                'active' => Rental::where('status', 'active')->count(),
                'completed' => Rental::where('status', 'completed')->count(),
                'cancelled' => Rental::where('status', 'cancelled')->count(),
                'total_revenue' => Rental::where('status', 'completed')->sum('total_price'),
                'platform_profit' => Rental::whereNotNull('verification_completed_at')
                    ->get()
                    ->sum(fn($r) => $r->is_verification_cached ? 3 : 1),
            ];

            return view('admin.rentals.index', compact('rentals', 'summary', 'totalShops'));

        } catch (\Exception $e) {
            Log::error('Rental index error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load rentals. Please try again.');
        }
    }

    public function show($id)
    {
        try {
            $rental = Rental::with(['user', 'vehicle', 'customer', 'document'])->findOrFail($id);

            // Calculate duration
            $durationHours = null;
            if ($rental->start_time && $rental->end_time) {
                $durationHours = round($rental->start_time->diffInHours($rental->end_time), 1);
            } elseif ($rental->start_time && $rental->status === 'active') {
                $durationHours = round($rental->start_time->diffInHours(now()), 1);
            }

            // Calculate platform profit
            $platformProfit = 0;
            if ($rental->verification_completed_at) {
                $platformProfit = $rental->is_verification_cached ? 3 : 1;
            }

            return view('admin.rentals.show', compact('rental', 'durationHours', 'platformProfit'));

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return redirect()->route('admin.rentals.index')
                ->with('error', 'Rental not found.');
        } catch (\Exception $e) {
            Log::error('Rental show error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to load rental details. Please try again.');
        }
    }
}