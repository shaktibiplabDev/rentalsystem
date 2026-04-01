<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Exception;

class VehicleController extends Controller
{
    /**
     * Get all vehicles for the authenticated user
     */
    public function index(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated access to vehicles', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $query = Vehicle::where('user_id', $userId);
                
                // Apply filters
                if ($request->has('status')) {
                    $request->validate(['status' => 'in:available,on_rent,unavailable']);
                    $query->where('status', $request->status);
                }
                
                if ($request->has('type')) {
                    $query->where('type', 'like', '%' . $request->type . '%');
                }
                
                if ($request->has('search')) {
                    $search = $request->search;
                    $query->where(function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%")
                          ->orWhere('number_plate', 'like', "%{$search}%");
                    });
                }
                
                // Sorting
                $sortBy = $request->get('sort_by', 'created_at');
                $sortOrder = $request->get('sort_order', 'desc');
                $allowedSortFields = ['id', 'name', 'type', 'status', 'hourly_rate', 'daily_rate', 'created_at'];
                
                if (!in_array($sortBy, $allowedSortFields)) {
                    $sortBy = 'created_at';
                }
                
                $query->orderBy($sortBy, $sortOrder);
                
                // Pagination
                $perPage = $request->get('per_page', 15);
                $perPage = min(max($perPage, 1), 100);
                
                $vehicles = $query->paginate($perPage);
                
                // Add statistics to each vehicle
                $vehicles->getCollection()->transform(function ($vehicle) {
                    try {
                        $vehicle->statistics = [
                            'total_rentals' => $vehicle->rentals()->count(),
                            'active_rentals' => $vehicle->rentals()->where('status', 'active')->count(),
                            'completed_rentals' => $vehicle->rentals()->where('status', 'completed')->count(),
                            'total_revenue' => (float) $vehicle->rentals()->where('status', 'completed')->sum('total_price'),
                            'last_rental_date' => $vehicle->rentals()->latest()->first()?->created_at
                        ];
                        return $vehicle;
                    } catch (Exception $e) {
                        Log::warning('Failed to load vehicle statistics', [
                            'vehicle_id' => $vehicle->id,
                            'error' => $e->getMessage()
                        ]);
                        return $vehicle;
                    }
                });
                
            } catch (QueryException $e) {
                Log::error('Database error fetching vehicles', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                    'sql' => method_exists($e, 'getSql') ? $e->getSql() : null
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch vehicles',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $vehicles->items(),
                'pagination' => [
                    'current_page' => $vehicles->currentPage(),
                    'last_page' => $vehicles->lastPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total(),
                    'from' => $vehicles->firstItem(),
                    'to' => $vehicles->lastItem()
                ],
                'filters' => [
                    'status' => $request->status ?? null,
                    'type' => $request->type ?? null,
                    'search' => $request->search ?? null
                ]
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching vehicles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicles',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Create a new vehicle
     */
    public function store(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated vehicle creation attempt', [
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'number_plate' => 'required|string|max:50|unique:vehicles,number_plate',
                'type' => 'required|string|max:100',
                'hourly_rate' => 'nullable|numeric|min:0|max:999999.99',
                'daily_rate' => 'nullable|numeric|min:0|max:999999.99',
                'weekly_rate' => 'nullable|numeric|min:0|max:999999.99',
                'status' => 'sometimes|in:available,on_rent,unavailable',
                'description' => 'nullable|string|max:1000',
                'features' => 'nullable|array',
                'images' => 'nullable|array'
            ]);

            $validated['user_id'] = $userId;
            $validated['status'] = $validated['status'] ?? 'available';
            
            // Convert features and images to JSON if provided
            if (isset($validated['features'])) {
                $validated['features'] = json_encode($validated['features']);
            }
            if (isset($validated['images'])) {
                $validated['images'] = json_encode($validated['images']);
            }

            DB::beginTransaction();
            
            try {
                $vehicle = Vehicle::create($validated);
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            Log::info('Vehicle created successfully', [
                'vehicle_id' => $vehicle->id,
                'user_id' => $userId,
                'number_plate' => $validated['number_plate'],
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle created successfully',
                'data' => $vehicle
            ], 201);

        } catch (ValidationException $e) {
            Log::warning('Vehicle creation validation failed', [
                'errors' => $e->errors(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            // Handle duplicate entry error
            if ($e->errorInfo[1] == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle creation failed',
                    'errors' => [
                        'number_plate' => ['This number plate is already registered.']
                    ]
                ], 422);
            }
            
            Log::error('Database error creating vehicle', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error creating vehicle', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to create vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get a specific vehicle
     */
    public function show($id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $vehicle = $this->findUserVehicle($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Vehicle not found', [
                    'vehicle_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'errors' => [
                        'vehicle' => ['Vehicle not found or access denied.']
                    ]
                ], 404);
            }

            // Load statistics
            try {
                $vehicle->statistics = [
                    'total_rentals' => $vehicle->rentals()->count(),
                    'active_rentals' => $vehicle->rentals()->where('status', 'active')->count(),
                    'completed_rentals' => $vehicle->rentals()->where('status', 'completed')->count(),
                    'cancelled_rentals' => $vehicle->rentals()->where('status', 'cancelled')->count(),
                    'total_revenue' => (float) $vehicle->rentals()->where('status', 'completed')->sum('total_price'),
                    'average_rental_duration' => $this->getAverageRentalDuration($vehicle),
                    'last_rental' => $vehicle->rentals()->with('customer')->latest()->first(),
                    'next_available' => $this->getNextAvailableTime($vehicle)
                ];
            } catch (Exception $e) {
                Log::warning('Failed to load vehicle statistics', [
                    'vehicle_id' => $vehicle->id,
                    'error' => $e->getMessage()
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => $vehicle
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching vehicle', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Update a vehicle
     */
    public function update(Request $request, $id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $vehicle = $this->findUserVehicle($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Vehicle not found for update', [
                    'vehicle_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'errors' => [
                        'vehicle' => ['Vehicle not found or access denied.']
                    ]
                ], 404);
            }

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'number_plate' => [
                    'sometimes',
                    'required',
                    'string',
                    'max:50',
                    Rule::unique('vehicles', 'number_plate')->ignore($vehicle->id)
                ],
                'type' => 'sometimes|required|string|max:100',
                'hourly_rate' => 'nullable|numeric|min:0|max:999999.99',
                'daily_rate' => 'nullable|numeric|min:0|max:999999.99',
                'weekly_rate' => 'nullable|numeric|min:0|max:999999.99',
                'status' => 'sometimes|in:available,on_rent,unavailable',
                'description' => 'nullable|string|max:1000',
                'features' => 'nullable|array',
                'images' => 'nullable|array'
            ]);

            // Convert features and images to JSON if provided
            if (isset($validated['features'])) {
                $validated['features'] = json_encode($validated['features']);
            }
            if (isset($validated['images'])) {
                $validated['images'] = json_encode($validated['images']);
            }

            DB::beginTransaction();
            
            try {
                $vehicle->update($validated);
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            Log::info('Vehicle updated successfully', [
                'vehicle_id' => $id,
                'user_id' => $userId,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle updated successfully',
                'data' => $vehicle->fresh()
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Vehicle update validation failed', [
                'errors' => $e->errors(),
                'vehicle_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            // Handle duplicate entry error
            if ($e->errorInfo[1] == 1062 || str_contains($e->getMessage(), 'Duplicate entry')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle update failed',
                    'errors' => [
                        'number_plate' => ['This number plate is already registered.']
                    ]
                ], 422);
            }
            
            Log::error('Database error updating vehicle', [
                'vehicle_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error updating vehicle', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Update vehicle status
     */
    public function updateStatus(Request $request, $id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $vehicle = $this->findUserVehicle($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Vehicle not found for status update', [
                    'vehicle_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'errors' => [
                        'vehicle' => ['Vehicle not found or access denied.']
                    ]
                ], 404);
            }

            $validated = $request->validate([
                'status' => 'required|in:available,on_rent,unavailable',
                'reason' => 'nullable|string|max:500'
            ]);

            // Validate status transition
            if ($validated['status'] === $vehicle->status) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle is already in this status',
                    'errors' => [
                        'status' => ['Vehicle is already ' . $vehicle->status . '.']
                    ]
                ], 422);
            }

            // Check if vehicle can be updated to on_rent
            if ($validated['status'] === 'on_rent') {
                if ($vehicle->status === 'on_rent') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vehicle is already on rent',
                        'errors' => [
                            'status' => ['Vehicle is already on rent.']
                        ]
                    ], 422);
                }
            }

            // Check if vehicle can be updated to available from on_rent
            if ($validated['status'] === 'available' && $vehicle->status === 'on_rent') {
                try {
                    $hasActiveRentals = $vehicle->rentals()
                        ->where('status', 'active')
                        ->exists();
                        
                    if ($hasActiveRentals) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Cannot mark vehicle as available',
                            'errors' => [
                                'status' => ['Vehicle has active rentals and cannot be marked as available.']
                            ]
                        ], 422);
                    }
                } catch (QueryException $e) {
                    Log::error('Error checking active rentals', [
                        'vehicle_id' => $id,
                        'error' => $e->getMessage()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to verify vehicle rentals',
                        'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                    ], 500);
                }
            }

            DB::beginTransaction();
            
            try {
                $oldStatus = $vehicle->status;
                $vehicle->update(['status' => $validated['status']]);
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            Log::info('Vehicle status updated', [
                'vehicle_id' => $id,
                'user_id' => $userId,
                'old_status' => $oldStatus,
                'new_status' => $validated['status'],
                'reason' => $validated['reason'] ?? null,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle status updated successfully',
                'data' => [
                    'id' => $vehicle->id,
                    'status' => $vehicle->status,
                    'previous_status' => $oldStatus
                ]
            ], 200);

        } catch (ValidationException $e) {
            Log::warning('Vehicle status update validation failed', [
                'errors' => $e->errors(),
                'vehicle_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
            
        } catch (QueryException $e) {
            Log::error('Database error updating vehicle status', [
                'vehicle_id' => $id,
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle status',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error updating vehicle status', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update vehicle status',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Delete a vehicle
     */
    public function destroy($id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                Log::warning('Unauthenticated vehicle deletion attempt', [
                    'vehicle_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $vehicle = $this->findUserVehicle($id, $userId);
            } catch (ModelNotFoundException $e) {
                Log::warning('Vehicle not found for deletion', [
                    'vehicle_id' => $id,
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found',
                    'errors' => [
                        'vehicle' => ['Vehicle not found or access denied.']
                    ]
                ], 404);
            }

            // Check if vehicle has any rentals
            try {
                $hasRentals = $vehicle->rentals()->exists();
                
                if ($hasRentals) {
                    Log::warning('Attempted to delete vehicle with rentals', [
                        'vehicle_id' => $id,
                        'user_id' => $userId,
                        'rental_count' => $vehicle->rentals()->count()
                    ]);
                    
                    return response()->json([
                        'success' => false,
                        'message' => 'Cannot delete vehicle',
                        'errors' => [
                            'vehicle' => ['This vehicle has rental history and cannot be deleted.']
                        ]
                    ], 422);
                }
            } catch (QueryException $e) {
                Log::error('Error checking vehicle rentals', [
                    'vehicle_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to verify vehicle rentals',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            DB::beginTransaction();
            
            try {
                $vehicle->delete();
                DB::commit();
            } catch (QueryException $e) {
                DB::rollBack();
                throw $e;
            }

            Log::info('Vehicle deleted successfully', [
                'vehicle_id' => $id,
                'user_id' => $userId,
                'number_plate' => $vehicle->number_plate,
                'ip' => request()->ip()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Vehicle deleted successfully'
            ], 200);

        } catch (QueryException $e) {
            Log::error('Database error deleting vehicle', [
                'vehicle_id' => $id,
                'user_id' => auth()->id(),
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
            ], 500);
            
        } catch (Exception $e) {
            Log::error('Unexpected error deleting vehicle', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete vehicle',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get available vehicles
     */
    public function available(Request $request)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $query = Vehicle::where('user_id', $userId)
                    ->where('status', 'available');
                
                // Filter by type
                if ($request->has('type')) {
                    $query->where('type', $request->type);
                }
                
                // Filter by price range
                if ($request->has('min_price')) {
                    $query->where(function ($q) use ($request) {
                        $q->where('hourly_rate', '>=', $request->min_price)
                          ->orWhere('daily_rate', '>=', $request->min_price);
                    });
                }
                
                if ($request->has('max_price')) {
                    $query->where(function ($q) use ($request) {
                        $q->where('hourly_rate', '<=', $request->max_price)
                          ->orWhere('daily_rate', '<=', $request->max_price);
                    });
                }
                
                $vehicles = $query->orderBy('hourly_rate', 'asc')
                    ->paginate($request->get('per_page', 15));
                
            } catch (QueryException $e) {
                Log::error('Database error fetching available vehicles', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch available vehicles',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $vehicles->items(),
                'pagination' => [
                    'current_page' => $vehicles->currentPage(),
                    'last_page' => $vehicles->lastPage(),
                    'per_page' => $vehicles->perPage(),
                    'total' => $vehicles->total()
                ]
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching available vehicles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch available vehicles',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Get vehicle statistics
     */
    public function statistics($id)
    {
        try {
            $userId = auth()->id();
            
            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            try {
                $vehicle = $this->findUserVehicle($id, $userId);
            } catch (ModelNotFoundException $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'Vehicle not found'
                ], 404);
            }

            try {
                $statistics = [
                    'total_rentals' => $vehicle->rentals()->count(),
                    'active_rentals' => $vehicle->rentals()->where('status', 'active')->count(),
                    'completed_rentals' => $vehicle->rentals()->where('status', 'completed')->count(),
                    'cancelled_rentals' => $vehicle->rentals()->where('status', 'cancelled')->count(),
                    'total_revenue' => (float) $vehicle->rentals()->where('status', 'completed')->sum('total_price'),
                    'average_rental_duration' => $this->getAverageRentalDuration($vehicle),
                    'average_revenue_per_rental' => $this->getAverageRevenuePerRental($vehicle),
                    'utilization_rate' => $this->calculateUtilizationRate($vehicle),
                    'monthly_breakdown' => $this->getMonthlyBreakdown($vehicle),
                    'top_customers' => $this->getTopCustomers($vehicle)
                ];
            } catch (QueryException $e) {
                Log::error('Database error fetching vehicle statistics', [
                    'vehicle_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to fetch statistics',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'data' => $statistics
            ], 200);

        } catch (Exception $e) {
            Log::error('Unexpected error fetching vehicle statistics', [
                'vehicle_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Find a vehicle belonging to the authenticated user
     */
    protected function findUserVehicle($id, $userId = null)
    {
        $userId = $userId ?? auth()->id();
        
        $vehicle = Vehicle::where('id', $id)
            ->where('user_id', $userId)
            ->first();

        if (!$vehicle) {
            throw new ModelNotFoundException('Vehicle not found or access denied');
        }

        return $vehicle;
    }

    /**
     * Get average rental duration for a vehicle
     */
    protected function getAverageRentalDuration($vehicle)
    {
        try {
            $completedRentals = $vehicle->rentals()
                ->where('status', 'completed')
                ->whereNotNull('end_time')
                ->get();
            
            if ($completedRentals->isEmpty()) {
                return null;
            }
            
            $totalMinutes = $completedRentals->sum(function ($rental) {
                return $rental->start_time->diffInMinutes($rental->end_time);
            });
            
            $averageMinutes = $totalMinutes / $completedRentals->count();
            
            return [
                'minutes' => round($averageMinutes),
                'hours' => round($averageMinutes / 60, 1),
                'formatted' => $this->formatDuration($averageMinutes)
            ];
        } catch (Exception $e) {
            Log::error('Error calculating average rental duration', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get average revenue per rental
     */
    protected function getAverageRevenuePerRental($vehicle)
    {
        try {
            $completedRentals = $vehicle->rentals()
                ->where('status', 'completed')
                ->count();
            
            if ($completedRentals == 0) {
                return 0;
            }
            
            $totalRevenue = (float) $vehicle->rentals()
                ->where('status', 'completed')
                ->sum('total_price');
            
            return round($totalRevenue / $completedRentals, 2);
        } catch (Exception $e) {
            Log::error('Error calculating average revenue', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Calculate utilization rate
     */
    protected function calculateUtilizationRate($vehicle)
    {
        try {
            $totalDays = 30; // Last 30 days
            $startDate = now()->subDays($totalDays);
            
            $activeRentalDays = $vehicle->rentals()
                ->where('status', 'completed')
                ->where('end_time', '>=', $startDate)
                ->get()
                ->sum(function ($rental) {
                    return $rental->start_time->diffInDays($rental->end_time);
                });
            
            $utilization = ($activeRentalDays / $totalDays) * 100;
            return round(min($utilization, 100), 2);
        } catch (Exception $e) {
            Log::error('Error calculating utilization rate', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Get monthly breakdown
     */
    protected function getMonthlyBreakdown($vehicle)
    {
        try {
            return $vehicle->rentals()
                ->where('status', 'completed')
                ->select(
                    DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                    DB::raw('COUNT(*) as rental_count'),
                    DB::raw('SUM(total_price) as revenue')
                )
                ->groupBy('month')
                ->orderBy('month', 'desc')
                ->limit(12)
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->month => [
                        'rentals' => $item->rental_count,
                        'revenue' => (float) $item->revenue
                    ]];
                });
        } catch (Exception $e) {
            Log::error('Error getting monthly breakdown', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get top customers for a vehicle
     */
    protected function getTopCustomers($vehicle)
    {
        try {
            return $vehicle->rentals()
                ->where('status', 'completed')
                ->select('customer_id', DB::raw('COUNT(*) as rental_count'), DB::raw('SUM(total_price) as total_spent'))
                ->with('customer')
                ->groupBy('customer_id')
                ->orderBy('rental_count', 'desc')
                ->limit(5)
                ->get()
                ->map(function ($item) {
                    return [
                        'customer_id' => $item->customer_id,
                        'name' => $item->customer->name ?? 'N/A',
                        'phone' => $item->customer->phone ?? 'N/A',
                        'rental_count' => $item->rental_count,
                        'total_spent' => (float) $item->total_spent
                    ];
                });
        } catch (Exception $e) {
            Log::error('Error getting top customers', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get next available time for a vehicle
     */
    protected function getNextAvailableTime($vehicle)
    {
        try {
            $activeRental = $vehicle->rentals()
                ->where('status', 'active')
                ->latest()
                ->first();
            
            if ($activeRental && $activeRental->end_time) {
                return $activeRental->end_time;
            }
            
            return null;
        } catch (Exception $e) {
            Log::error('Error getting next available time', [
                'vehicle_id' => $vehicle->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Format duration in minutes
     */
    protected function formatDuration($minutes)
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        
        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours}h {$remainingMinutes}m";
        } elseif ($hours > 0) {
            return "{$hours}h";
        } else {
            return "{$remainingMinutes}m";
        }
    }
}