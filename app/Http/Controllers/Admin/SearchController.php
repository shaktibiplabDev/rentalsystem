<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Models\Rental;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function globalSearch(Request $request)
    {
        $query = $request->get('q');
        
        if (strlen($query) < 2) {
            return response()->json([
                'shops' => [],
                'customers' => [],
                'rentals' => []
            ]);
        }
        
        // Search Shops (users with role 'user')
        $shops = User::where('role', 'user')
            ->where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('email', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('gst_number', 'like', "%{$query}%")
                  ->orWhere('business_display_name', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'email', 'phone']);
        
        // Search Customers
        $customers = Customer::where(function($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                  ->orWhere('phone', 'like', "%{$query}%")
                  ->orWhere('license_number', 'like', "%{$query}%")
                  ->orWhere('address', 'like', "%{$query}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'phone', 'address']);
        
        // Search Rentals
        $rentals = Rental::with(['vehicle', 'customer'])
            ->where(function($q) use ($query) {
                $q->whereHas('vehicle', fn($q2) => $q2->where('name', 'like', "%{$query}%")
                    ->orWhere('number_plate', 'like', "%{$query}%"))
                  ->orWhereHas('customer', fn($q2) => $q2->where('name', 'like', "%{$query}%")
                    ->orWhere('phone', 'like', "%{$query}%"));
            })
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'id' => $r->id,
                'vehicle_name' => $r->vehicle->name ?? 'N/A',
                'customer_name' => $r->customer->name ?? 'N/A',
            ]);
        
        return response()->json([
            'shops' => $shops,
            'customers' => $customers,
            'rentals' => $rentals,
        ]);
    }
}