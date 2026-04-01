<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Traits\LogsCustomerAccess;
use Exception;

class CustomerController extends Controller
{
    use LogsCustomerAccess;
    
    /**
     * Get all customers - ADMIN ONLY
     * Shop owners should NOT see customer data
     */
    public function index(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                Log::warning('Unauthorized attempt to view customers', [
                    'user_id' => auth()->id(),
                    'user_role' => auth()->user()->role
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view customer data.'
                ], 403);
            }
            
            $perPage = $request->get('per_page', 20);
            $search = $request->get('search');
            
            $query = Customer::query();
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'LIKE', "%{$search}%")
                      ->orWhere('phone', 'LIKE', "%{$search}%")
                      ->orWhere('license_number', 'LIKE', "%{$search}%");
                });
            }
            
            $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);
            
            $customers->getCollection()->transform(function ($customer) {
                return $this->formatCustomerWithStats($customer);
            });
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Customers retrieved successfully'
            ], 200);
            
        } catch (QueryException $e) {
            Log::error('Database error fetching customers', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers'
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching customers', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers'
            ], 500);
        }
    }

    /**
     * Get a specific customer by ID - ADMIN ONLY
     */
    public function show($id)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                Log::warning('Unauthorized attempt to view customer details', [
                    'user_id' => auth()->id(),
                    'customer_id' => $id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view customer details.'
                ], 403);
            }
            
            $customer = Customer::findOrFail($id);
            
            $this->logCustomerView($customer->id);
            
            $formattedCustomer = $this->formatCustomerWithStats($customer);
            
            $rentals = $customer->rentals()
                ->with(['vehicle', 'documents'])
                ->orderBy('created_at', 'desc')
                ->paginate(10);
            
            $formattedCustomer['rentals'] = $rentals;
            
            return response()->json([
                'success' => true,
                'data' => $formattedCustomer
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching customer', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer'
            ], 500);
        }
    }

    /**
     * Update customer information - ADMIN ONLY
     */
    public function update(Request $request, $id)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                Log::warning('Unauthorized customer update attempt', [
                    'user_id' => auth()->id(),
                    'user_role' => auth()->user()->role,
                    'customer_id' => $id
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can update customer information.'
                ], 403);
            }
            
            $customer = Customer::findOrFail($id);
            
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'father_name' => 'nullable|string|max:255',
                'phone' => 'sometimes|required|string|max:20',
                'address' => 'sometimes|required|string|max:500',
                'blood_group' => 'nullable|string|max:10',
                'aadhaar_number' => 'nullable|string|max:20|unique:customers,aadhaar_number,' . $id
            ]);
            
            // Check if phone number is being changed and already exists
            if (isset($validated['phone']) && $validated['phone'] !== $customer->phone) {
                $existingCustomer = Customer::where('phone', $validated['phone'])
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($existingCustomer) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Customer with this phone number already exists',
                        'errors' => [
                            'phone' => ['A customer with this phone number already exists.']
                        ]
                    ], 422);
                }
            }
            
            DB::beginTransaction();
            
            try {
                $oldData = $customer->toArray();
                $customer->update($validated);
                DB::commit();
                
                Log::info('Customer updated successfully', [
                    'customer_id' => $id,
                    'updated_by' => auth()->id(),
                    'changes' => array_intersect_key($validated, array_diff_assoc($validated, $oldData)),
                    'ip' => $request->ip()
                ]);
                
            } catch (QueryException $e) {
                DB::rollBack();
                
                Log::error('Database error updating customer', [
                    'customer_id' => $id,
                    'error' => $e->getMessage()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update customer',
                    'error' => config('app.debug') ? $e->getMessage() : 'Database error occurred'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $this->formatCustomerWithStats($customer->fresh())
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Unexpected error updating customer', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer'
            ], 500);
        }
    }

    /**
     * Search customers - ADMIN ONLY
     */
    public function search(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can search customers.'
                ], 403);
            }
            
            $validated = $request->validate([
                'query' => 'required|string|min:2|max:100'
            ]);
            
            $customers = Customer::where(function ($query) use ($validated) {
                    $query->where('name', 'LIKE', '%' . $validated['query'] . '%')
                        ->orWhere('phone', 'LIKE', '%' . $validated['query'] . '%')
                        ->orWhere('license_number', 'LIKE', '%' . $validated['query'] . '%');
                })
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->map(function ($customer) {
                    return $this->formatCustomerBasic($customer);
                });
            
            Log::info('Customer search performed', [
                'query' => $validated['query'],
                'results_count' => $customers->count(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'total' => $customers->count()
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error searching customers', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to search customers'
            ], 500);
        }
    }
    
    /**
     * Get customer statistics - ADMIN ONLY
     */
    public function statistics($id)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view customer statistics.'
                ], 403);
            }
            
            $customer = Customer::findOrFail($id);
            
            $statistics = [
                'total_rentals' => $customer->rentals()->count(),
                'active_rentals' => $customer->rentals()->where('status', 'active')->count(),
                'completed_rentals' => $customer->rentals()->where('status', 'completed')->count(),
                'cancelled_rentals' => $customer->rentals()->where('status', 'cancelled')->count(),
                'total_spent' => (float) $customer->rentals()->where('status', 'completed')->sum('total_price'),
                'first_rental' => $customer->rentals()->orderBy('created_at', 'asc')->first(),
                'last_rental' => $customer->rentals()->orderBy('created_at', 'desc')->first(),
                'favorite_vehicle' => $this->getFavoriteVehicle($customer),
                'license_details' => $this->getLicenseDetails($customer)
            ];
            
            return response()->json([
                'success' => true,
                'data' => $statistics
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching customer statistics', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics'
            ], 500);
        }
    }
    
    /**
     * Get customer rental history - ADMIN ONLY
     */
    public function rentalHistory($id, Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view rental history.'
                ], 403);
            }
            
            $customer = Customer::findOrFail($id);
            
            $rentals = $customer->rentals()
                ->with(['vehicle', 'documents'])
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 15));
            
            return response()->json([
                'success' => true,
                'data' => $rentals,
                'customer' => $this->formatCustomerBasic($customer)
            ], 200);
            
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        } catch (Exception $e) {
            Log::error('Failed to fetch customer rental history', [
                'customer_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental history'
            ], 500);
        }
    }
    
    /**
     * Get customers with incomplete documentation - ADMIN ONLY
     */
    public function incompleteDocumentation(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can access this report.'
                ], 403);
            }
            
            $customers = Customer::whereHas('rentals', function ($query) {
                    $query->where('status', 'completed');
                })
                ->whereDoesntHave('documents')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20))
                ->through(function ($customer) {
                    return $this->formatCustomerBasic($customer);
                });
            
            Log::info('Incomplete documentation report generated', [
                'count' => $customers->total(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Customers with incomplete documentation retrieved'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch customers with incomplete documentation', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers'
            ], 500);
        }
    }
    
    /**
     * Get verified customers - ADMIN ONLY
     */
    public function verifiedCustomers(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can access this report.'
                ], 403);
            }
            
            $customers = Customer::whereNotNull('license_number')
                ->whereNotNull('license_data')
                ->orderBy('created_at', 'desc')
                ->paginate($request->get('per_page', 20))
                ->through(function ($customer) {
                    return $this->formatCustomerWithStats($customer);
                });
            
            Log::info('Verified customers list generated', [
                'count' => $customers->total(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Verified customers retrieved successfully'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch verified customers', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verified customers'
            ], 500);
        }
    }
    
    /**
     * Check if a customer exists by license number - INTERNAL USE ONLY
     * This returns minimal data for rental verification
     */
    public function checkByLicense(Request $request)
    {
        try {
            // Allow all authenticated users but return minimal data
            // This is used by RentalController for verification
            $validated = $request->validate([
                'license_number' => 'required|string|max:20'
            ]);
            
            $customer = Customer::where('license_number', $validated['license_number'])
                ->whereNotNull('license_data')
                ->first();
            
            if ($customer) {
                // Return ONLY essential data for verification, NO personal info
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'customer_id' => $customer->id,
                    'name' => $customer->name,
                    'license_number' => $customer->license_number,
                    'license_valid_from' => $customer->license_valid_from_non_transport,
                    'license_valid_to' => $customer->license_valid_to_non_transport,
                    'license_photo' => $customer->license_photo ? asset('storage/' . $customer->license_photo) : null,
                    'license_data_exists' => true
                ], 200);
            }
            
            return response()->json([
                'success' => true,
                'exists' => false,
                'message' => 'No verified customer found with this license number'
            ], 200);
            
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Failed to check customer by license', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to check customer'
            ], 500);
        }
    }
    
    /**
     * Export customers list - ADMIN ONLY
     */
    public function export(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can export customer data.'
                ], 403);
            }
            
            $format = $request->get('format', 'csv');
            
            $customers = Customer::with(['rentals' => function($query) {
                    $query->where('status', 'completed');
                }])
                ->orderBy('created_at', 'desc')
                ->get();
            
            $exportData = $customers->map(function ($customer) {
                return [
                    'ID' => $customer->id,
                    'Name' => $customer->name,
                    'Father Name' => $customer->father_name,
                    'Phone' => $customer->phone,
                    'Address' => $customer->address,
                    'License Number' => $customer->license_number,
                    'Aadhaar Number' => $customer->aadhaar_number,
                    'Date of Birth' => $customer->date_of_birth,
                    'Blood Group' => $customer->blood_group,
                    'Total Rentals' => $customer->rentals->count(),
                    'Total Spent' => $customer->rentals->sum('total_price'),
                    'First Rental' => $customer->rentals->min('created_at'),
                    'Last Rental' => $customer->rentals->max('created_at'),
                    'Created At' => $customer->created_at
                ];
            });
            
            Log::info('Customer data exported', [
                'user_id' => auth()->id(),
                'format' => $format,
                'count' => $exportData->count()
            ]);
            
            if ($format === 'csv') {
                $csv = $this->arrayToCsv($exportData->toArray());
                return response($csv, 200)
                    ->header('Content-Type', 'text/csv')
                    ->header('Content-Disposition', 'attachment; filename="customers_' . date('Y-m-d') . '.csv"');
            }
            
            return response()->json([
                'success' => true,
                'data' => $exportData,
                'total' => $exportData->count()
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Failed to export customers', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to export customer data'
            ], 500);
        }
    }
    
    /**
     * Customer analytics - ADMIN ONLY
     */
    public function analytics(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can access analytics.'
                ], 403);
            }
            
            $analytics = [
                'total_customers' => Customer::count(),
                'verified_customers' => Customer::whereNotNull('license_data')->count(),
                'verification_rate' => Customer::count() > 0 
                    ? round((Customer::whereNotNull('license_data')->count() / Customer::count()) * 100, 2)
                    : 0,
                'customers_with_aadhaar' => Customer::whereNotNull('aadhaar_number')->count(),
                'customers_by_blood_group' => $this->getBloodGroupDistribution(),
                'customer_retention_rate' => $this->getRetentionRate(),
                'top_customers' => $this->getTopCustomers(10),
                'recent_customers' => Customer::orderBy('created_at', 'desc')->limit(10)->get()->map(function($customer) {
                    return $this->formatCustomerBasic($customer);
                })
            ];
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Customer analytics retrieved successfully'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch customer analytics', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer analytics'
            ], 500);
        }
    }
    
    /**
     * Format customer with statistics
     */
    protected function formatCustomerWithStats(Customer $customer)
    {
        try {
            $rentalStats = [
                'total_rentals' => $customer->rentals()->count(),
                'active_rentals' => $customer->rentals()->where('status', 'active')->count(),
                'completed_rentals' => $customer->rentals()->where('status', 'completed')->count(),
                'total_spent' => (float) $customer->rentals()->where('status', 'completed')->sum('total_price')
            ];
            
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'father_name' => $customer->father_name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'license_number' => $customer->license_number,
                'aadhaar_number' => $customer->aadhaar_number,
                'date_of_birth' => $customer->date_of_birth,
                'blood_group' => $customer->blood_group,
                'license_photo' => $customer->license_photo ? asset('storage/' . $customer->license_photo) : null,
                'license_valid_from' => $customer->license_valid_from_non_transport,
                'license_valid_to' => $customer->license_valid_to_non_transport,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
                'statistics' => $rentalStats
            ];
        } catch (QueryException $e) {
            Log::warning('Failed to load customer statistics', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'address' => $customer->address,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
                'statistics' => null
            ];
        }
    }
    
    /**
     * Format customer basic info (without heavy statistics)
     */
    protected function formatCustomerBasic(Customer $customer)
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'license_number' => $customer->license_number,
            'created_at' => $customer->created_at
        ];
    }
    
    /**
     * Get favorite vehicle for a customer
     */
    protected function getFavoriteVehicle(Customer $customer)
    {
        try {
            $favorite = $customer->rentals()
                ->select('vehicle_id', DB::raw('COUNT(*) as rental_count'))
                ->where('status', 'completed')
                ->groupBy('vehicle_id')
                ->orderBy('rental_count', 'desc')
                ->with('vehicle')
                ->first();
                
            if ($favorite && $favorite->vehicle) {
                return [
                    'id' => $favorite->vehicle->id,
                    'name' => $favorite->vehicle->name,
                    'rental_count' => $favorite->rental_count
                ];
            }
            
            return null;
        } catch (Exception $e) {
            Log::warning('Failed to get favorite vehicle', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get license details for a customer
     */
    protected function getLicenseDetails(Customer $customer)
    {
        try {
            if (!$customer->license_data) {
                return null;
            }
            
            $licenseData = json_decode($customer->license_data, true);
            
            return [
                'license_number' => $customer->license_number,
                'name' => $customer->name,
                'father_name' => $customer->father_name,
                'date_of_issue' => $customer->license_issue_date,
                'valid_from' => $customer->license_valid_from_non_transport,
                'valid_to' => $customer->license_valid_to_non_transport,
                'address' => $customer->license_address,
                'address_list' => json_decode($customer->license_address_list, true) ?? [],
                'vehicle_classes' => json_decode($customer->vehicle_classes_data, true) ?? [],
                'reference_id' => $customer->license_reference_id,
                'photo_url' => $customer->license_photo ? asset('storage/' . $customer->license_photo) : null,
                'raw_data' => $licenseData
            ];
        } catch (Exception $e) {
            Log::warning('Failed to get license details', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get blood group distribution
     */
    protected function getBloodGroupDistribution()
    {
        try {
            return Customer::select('blood_group', DB::raw('count(*) as total'))
                ->whereNotNull('blood_group')
                ->groupBy('blood_group')
                ->get()
                ->mapWithKeys(function ($item) {
                    return [$item->blood_group => $item->total];
                });
        } catch (Exception $e) {
            Log::error('Failed to get blood group distribution', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get customer retention rate
     */
    protected function getRetentionRate()
    {
        try {
            // Get customers with more than 1 rental (returning customers)
            $returningCustomers = Customer::has('rentals', '>', 1)->count();
            $totalCustomers = Customer::has('rentals')->count();
            
            if ($totalCustomers === 0) {
                return 0;
            }
            
            return round(($returningCustomers / $totalCustomers) * 100, 2);
        } catch (Exception $e) {
            Log::error('Failed to calculate retention rate', ['error' => $e->getMessage()]);
            return 0;
        }
    }
    
    /**
     * Get top customers by rental count and spending
     */
    protected function getTopCustomers($limit = 10)
    {
        try {
            return Customer::withCount('rentals')
                ->withSum(['rentals as total_spent' => function($query) {
                    $query->where('status', 'completed');
                }], 'total_price')
                ->orderBy('total_spent', 'desc')
                ->limit($limit)
                ->get()
                ->map(function ($customer) {
                    return [
                        'id' => $customer->id,
                        'name' => $customer->name,
                        'phone' => $customer->phone,
                        'rental_count' => $customer->rentals_count,
                        'total_spent' => (float) $customer->total_spent,
                        'formatted_spent' => '₹' . number_format($customer->total_spent ?? 0, 2)
                    ];
                });
        } catch (Exception $e) {
            Log::error('Failed to get top customers', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Convert array to CSV
     */
    protected function arrayToCsv(array $data)
    {
        if (empty($data)) {
            return '';
        }
        
        $output = fopen('php://temp', 'r+');
        fputcsv($output, array_keys($data[0]));
        
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
}