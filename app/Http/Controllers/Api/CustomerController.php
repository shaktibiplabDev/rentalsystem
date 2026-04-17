<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccessLog;
use App\Traits\LogsCustomerAccess;
use Carbon\Carbon;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    use LogsCustomerAccess;
    
    // Define allowed fields for mass assignment protection
    protected $allowedUpdateFields = [
        'name', 'father_name', 'phone', 'address', 'blood_group', 'aadhaar_number'
    ];
    
    // Define fields that can be searched
    protected $searchableFields = ['name', 'phone', 'license_number'];
    
    // Cache duration for customer data (minutes)
    protected $cacheDuration = 60;
    
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
                    'user_role' => auth()->user()->role,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view customer data.'
                ], 403);
            }
            
            $perPage = $request->get('per_page', 20);
            $perPage = min(max($perPage, 1), 100); // Limit between 1 and 100
            
            $search = $request->get('search');
            $search = $search ? $this->sanitizeInput($search) : null;
            
            $query = Customer::query();
            
            if ($search) {
                $query->where(function ($q) use ($search) {
                    foreach ($this->searchableFields as $field) {
                        $q->orWhere($field, 'LIKE', "%{$search}%");
                    }
                });
            }
            
            // Add sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = $request->get('sort_order', 'desc');
            $allowedSortFields = ['id', 'name', 'phone', 'created_at', 'updated_at'];
            
            if (!in_array($sortBy, $allowedSortFields)) {
                $sortBy = 'created_at';
            }
            
            $query->orderBy($sortBy, $sortOrder);
            
            $customers = $query->paginate($perPage);
            
            $customers->getCollection()->transform(function ($customer) {
                return $this->formatCustomerWithStats($customer);
            });
            
            Log::info('Customers retrieved', [
                'admin_id' => auth()->id(),
                'total' => $customers->total(),
                'filters' => ['search' => $search, 'sort_by' => $sortBy]
            ]);
            
            return response()->json([
                'success' => true,
                'data' => $customers,
                'message' => 'Customers retrieved successfully'
            ], 200);
            
        } catch (QueryException $e) {
            Log::error('Database error fetching customers', [
                'error_code' => $e->getCode(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => 'Database error occurred'
            ], 500);
        } catch (Exception $e) {
            Log::error('Unexpected error fetching customers', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => 'An unexpected error occurred'
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
                    'customer_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view customer details.'
                ], 403);
            }
            
            // Validate ID is numeric
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer ID'
                ], 400);
            }
            
            // Try to get from cache first
            $cacheKey = 'customer_' . $id;
            $customer = Cache::remember($cacheKey, now()->addMinutes($this->cacheDuration), function () use ($id) {
                return Customer::find($id);
            });
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            // Log access for audit
            $this->logCustomerView($customer->id);
            
            $formattedCustomer = $this->formatCustomerWithStats($customer);
            
            // Get rentals with pagination
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
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Update customer information - ADMIN ONLY with mass assignment protection
     */
    public function update(Request $request, $id)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                Log::warning('Unauthorized customer update attempt', [
                    'user_id' => auth()->id(),
                    'user_role' => auth()->user()->role,
                    'customer_id' => $id,
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can update customer information.'
                ], 403);
            }
            
            // Validate ID is numeric
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer ID'
                ], 400);
            }
            
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|regex:/^[a-zA-Z\s\-]+$/',
                'father_name' => 'nullable|string|max:255|regex:/^[a-zA-Z\s\-]+$/',
                'phone' => 'sometimes|required|string|max:20|regex:/^[0-9]{10,15}$/',
                'address' => 'sometimes|required|string|max:500',
                'blood_group' => 'nullable|string|max:10|in:A+,A-,B+,B-,AB+,AB-,O+,O-',
                'aadhaar_number' => 'nullable|string|max:20|regex:/^[0-9]{12}$/|unique:customers,aadhaar_number,' . $id
            ]);
            
            // Sanitize inputs to prevent XSS
            foreach ($validated as $key => $value) {
                if (is_string($value)) {
                    $validated[$key] = $this->sanitizeInput($value);
                }
            }
            
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
            
            // Check if Aadhaar is being changed and already exists
            if (isset($validated['aadhaar_number']) && $validated['aadhaar_number'] !== $customer->aadhaar_number) {
                $existingAadhaar = Customer::where('aadhaar_number', $validated['aadhaar_number'])
                    ->where('id', '!=', $id)
                    ->exists();
                    
                if ($existingAadhaar) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Customer with this Aadhaar number already exists',
                        'errors' => [
                            'aadhaar_number' => ['A customer with this Aadhaar number already exists.']
                        ]
                    ], 422);
                }
            }
            
            DB::beginTransaction();
            
            try {
                $oldData = $customer->toArray();
                
                // CRITICAL: Only update allowed fields to prevent mass assignment
                $updateData = array_intersect_key($validated, array_flip($this->allowedUpdateFields));
                $customer->update($updateData);
                
                // Clear cache for this customer
                Cache::forget('customer_' . $id);
                
                DB::commit();
                
                // Log the changes (without sensitive data)
                $changedFields = array_keys($updateData);
                Log::info('Customer updated successfully', [
                    'customer_id' => $id,
                    'updated_by' => auth()->id(),
                    'fields_updated' => $changedFields,
                    'ip' => $request->ip()
                ]);
                
            } catch (QueryException $e) {
                DB::rollBack();
                
                Log::error('Database error updating customer', [
                    'customer_id' => $id,
                    'error_code' => $e->getCode(),
                    'user_id' => auth()->id()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to update customer',
                    'error' => 'Database error occurred'
                ], 500);
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Customer updated successfully',
                'data' => $this->formatCustomerWithStats($customer->fresh())
            ], 200);
            
        } catch (ValidationException $e) {
            Log::warning('Customer update validation failed', [
                'errors' => array_keys($e->errors()),
                'customer_id' => $id,
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (Exception $e) {
            Log::error('Unexpected error updating customer', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to update customer',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }

    /**
     * Search customers - ADMIN ONLY with rate limiting
     */
    public function search(Request $request)
    {
        try {
            // ADMIN ONLY
            if (auth()->user()->role !== 'admin') {
                Log::warning('Unauthorized customer search attempt', [
                    'user_id' => auth()->id(),
                    'ip' => $request->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can search customers.'
                ], 403);
            }
            
            // Rate limiting for search
            $rateLimitKey = 'customer_search_' . auth()->id();
            $attempts = (int) Cache::get($rateLimitKey, 0);
            
            if ($attempts >= 30) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many search requests. Please try again later.',
                ], 429);
            }
            
            $validated = $request->validate([
                'query' => 'required|string|min:2|max:100|regex:/^[a-zA-Z0-9\s\-]+$/'
            ]);
            
            $query = $this->sanitizeInput($validated['query']);
            
            $customers = Customer::where(function ($q) use ($query) {
                    $q->where('name', 'LIKE', '%' . $query . '%')
                      ->orWhere('phone', 'LIKE', '%' . $query . '%')
                      ->orWhere('license_number', 'LIKE', '%' . $query . '%');
                })
                ->orderBy('name')
                ->limit(50)
                ->get()
                ->map(function ($customer) {
                    return $this->formatCustomerBasic($customer);
                });
            
            // Increment rate limit counter
            Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(1));
            
            Log::info('Customer search performed', [
                'query' => $query,
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
            Log::error('Unexpected error searching customers', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to search customers',
                'error' => 'An unexpected error occurred'
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
                Log::warning('Unauthorized customer statistics access', [
                    'user_id' => auth()->id(),
                    'customer_id' => $id,
                    'ip' => request()->ip()
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized. Only administrators can view customer statistics.'
                ], 403);
            }
            
            // Validate ID is numeric
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer ID'
                ], 400);
            }
            
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            // Try to get from cache
            $cacheKey = 'customer_stats_' . $id;
            $statistics = Cache::remember($cacheKey, now()->addMinutes(30), function () use ($customer) {
                return [
                    'total_rentals' => $customer->rentals()->count(),
                    'active_rentals' => $customer->rentals()->where('status', 'active')->count(),
                    'completed_rentals' => $customer->rentals()->where('status', 'completed')->count(),
                    'cancelled_rentals' => $customer->rentals()->where('status', 'cancelled')->count(),
                    'total_spent' => (float) $customer->rentals()->where('status', 'completed')->sum('total_price'),
                    'first_rental' => $customer->rentals()->orderBy('created_at', 'asc')->first(),
                    'last_rental' => $customer->rentals()->orderBy('created_at', 'desc')->first(),
                    'favorite_vehicle' => $this->getFavoriteVehicle($customer),
                    'license_details' => $this->getLicenseDetails($customer),
                    'average_rental_duration' => $this->getAverageRentalDuration($customer),
                    'rental_frequency' => $this->getRentalFrequency($customer)
                ];
            });
            
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
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch statistics',
                'error' => 'An unexpected error occurred'
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
            
            // Validate ID is numeric
            if (!is_numeric($id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer ID'
                ], 400);
            }
            
            $customer = Customer::find($id);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found'
                ], 404);
            }
            
            $perPage = $request->get('per_page', 15);
            $perPage = min(max($perPage, 1), 50);
            
            $rentals = $customer->rentals()
                ->with(['vehicle', 'documents'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);
            
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
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental history',
                'error' => 'An unexpected error occurred'
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
            
            $perPage = $request->get('per_page', 20);
            $perPage = min(max($perPage, 1), 100);
            
            $customers = Customer::whereHas('rentals', function ($query) {
                    $query->where('status', 'completed');
                })
                ->whereDoesntHave('documents')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
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
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customers',
                'error' => 'An unexpected error occurred'
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
            
            $perPage = $request->get('per_page', 20);
            $perPage = min(max($perPage, 1), 100);
            
            $customers = Customer::whereNotNull('license_number')
                ->whereNotNull('license_data')
                ->orderBy('created_at', 'desc')
                ->paginate($perPage)
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
            Log::error('Failed to fetch verified customers', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch verified customers',
                'error' => 'An unexpected error occurred'
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
                'license_number' => 'required|string|max:20|regex:/^[A-Z0-9]{6,20}$/i'
            ]);
            
            $licenseNumber = strtoupper($this->sanitizeInput($validated['license_number']));
            
            // Rate limiting
            $rateLimitKey = 'license_check_' . auth()->id();
            $attempts = (int) Cache::get($rateLimitKey, 0);
            
            if ($attempts >= 100) {
                return response()->json([
                    'success' => false,
                    'message' => 'Too many license check requests. Please try again later.',
                ], 429);
            }
            
            Cache::put($rateLimitKey, $attempts + 1, now()->addMinutes(1));
            
            $customer = Customer::where('license_number', $licenseNumber)
                ->whereNotNull('license_data')
                ->first();
            
            if ($customer) {
                // Return ONLY essential data for verification, NO personal info
                // Check if license is still valid
                $isLicenseValid = true;
                if ($customer->license_valid_to_non_transport) {
                    $isLicenseValid = Carbon::now()->lessThanOrEqualTo($customer->license_valid_to_non_transport);
                }
                
                return response()->json([
                    'success' => true,
                    'exists' => true,
                    'customer_id' => $customer->id,
                    'name' => $this->maskName($customer->name),
                    'license_number' => $this->maskLicenseNumber($customer->license_number),
                    'license_valid_from' => $customer->license_valid_from_non_transport,
                    'license_valid_to' => $customer->license_valid_to_non_transport,
                    'license_photo' => $customer->license_photo_url,
                    'license_data_exists' => true,
                    'license_is_valid' => $isLicenseValid
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
            Log::error('Failed to check customer by license', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to check customer',
                'error' => 'An unexpected error occurred'
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
            
            if (!in_array($format, ['csv', 'json'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid export format. Supported formats: csv, json'
                ], 400);
            }
            
            // Rate limiting for exports
            $rateLimitKey = 'customer_export_' . auth()->id();
            $lastExport = Cache::get($rateLimitKey);
            
            if ($lastExport && $lastExport > now()->subMinutes(5)->timestamp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Please wait 5 minutes before exporting again.'
                ], 429);
            }
            
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
                    'License Number' => $this->maskLicenseNumber($customer->license_number),
                    'Aadhaar Number' => $this->maskAadhaar($customer->aadhaar_number),
                    'Date of Birth' => $customer->date_of_birth,
                    'Blood Group' => $customer->blood_group,
                    'Total Rentals' => $customer->rentals->count(),
                    'Total Spent' => number_format($customer->rentals->sum('total_price'), 2),
                    'First Rental' => $customer->rentals->min('created_at'),
                    'Last Rental' => $customer->rentals->max('created_at'),
                    'Created At' => $customer->created_at,
                    'License Valid From' => $customer->license_valid_from_non_transport,
                    'License Valid To' => $customer->license_valid_to_non_transport
                ];
            });
            
            // Store export timestamp
            Cache::put($rateLimitKey, now()->timestamp, now()->addMinutes(5));
            
            Log::info('Customer data exported', [
                'user_id' => auth()->id(),
                'format' => $format,
                'count' => $exportData->count()
            ]);
            
            if ($format === 'csv') {
                $csv = $this->arrayToCsv($exportData->toArray());
                $filename = 'customers_' . date('Y-m-d_His') . '.csv';
                
                return response($csv, 200)
                    ->header('Content-Type', 'text/csv; charset=UTF-8')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
                    ->header('Cache-Control', 'private, max-age=0, must-revalidate');
            }
            
            return response()->json([
                'success' => true,
                'data' => $exportData,
                'total' => $exportData->count(),
                'exported_at' => now()->toIso8601String()
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Failed to export customers', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to export customer data',
                'error' => 'An unexpected error occurred'
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
            
            // Try to get from cache
            $cacheKey = 'customer_analytics';
            $analytics = Cache::remember($cacheKey, now()->addHours(6), function () {
                return [
                    'total_customers' => Customer::count(),
                    'verified_customers' => Customer::whereNotNull('license_data')->count(),
                    'verification_rate' => Customer::count() > 0 
                        ? round((Customer::whereNotNull('license_data')->count() / Customer::count()) * 100, 2)
                        : 0,
                    'customers_with_aadhaar' => Customer::whereNotNull('aadhaar_number')->count(),
                    'customers_by_blood_group' => $this->getBloodGroupDistribution(),
                    'customer_retention_rate' => $this->getRetentionRate(),
                    'new_customers_last_30_days' => Customer::where('created_at', '>=', now()->subDays(30))->count(),
                    'new_customers_last_7_days' => Customer::where('created_at', '>=', now()->subDays(7))->count(),
                    'top_customers' => $this->getTopCustomers(10),
                    'recent_customers' => Customer::orderBy('created_at', 'desc')->limit(10)->get()->map(function($customer) {
                        return $this->formatCustomerBasic($customer);
                    }),
                    'license_expiry_soon' => $this->getLicensesExpiringSoon(30), // Licenses expiring in next 30 days
                    'inactive_customers' => $this->getInactiveCustomers(90) // No rentals in last 90 days
                ];
            });
            
            return response()->json([
                'success' => true,
                'data' => $analytics,
                'message' => 'Customer analytics retrieved successfully'
            ], 200);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch customer analytics', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch customer analytics',
                'error' => 'An unexpected error occurred'
            ], 500);
        }
    }
    
    // ============================================
    // PRIVATE HELPER METHODS
    // ============================================
    
    /**
     * Sanitize input to prevent XSS
     */
    protected function sanitizeInput(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }
        
        $cleaned = strip_tags($input);
        $cleaned = htmlspecialchars($cleaned, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $cleaned = preg_replace('/[\x00-\x1F\x7F]/', '', $cleaned);
        
        return mb_substr($cleaned, 0, 500);
    }
    
    /**
     * Mask customer name for privacy
     */
    protected function maskName(?string $name): ?string
    {
        if (!$name) return null;
        
        $parts = explode(' ', $name);
        $masked = [];
        
        foreach ($parts as $part) {
            if (strlen($part) <= 2) {
                $masked[] = $part;
            } else {
                $masked[] = substr($part, 0, 2) . str_repeat('*', strlen($part) - 2);
            }
        }
        
        return implode(' ', $masked);
    }
    
    /**
     * Mask license number for privacy
     */
    protected function maskLicenseNumber(?string $license): ?string
    {
        if (!$license) return null;
        
        if (strlen($license) <= 8) {
            return substr($license, 0, 2) . str_repeat('*', strlen($license) - 2);
        }
        
        return substr($license, 0, 4) . str_repeat('*', strlen($license) - 8) . substr($license, -4);
    }
    
    /**
     * Mask Aadhaar number for privacy
     */
    protected function maskAadhaar(?string $aadhaar): ?string
    {
        if (!$aadhaar) return null;
        
        $aadhaar = preg_replace('/[^0-9]/', '', $aadhaar);
        if (strlen($aadhaar) === 12) {
            return 'XXXX-XXXX-' . substr($aadhaar, -4);
        }
        
        return substr($aadhaar, 0, 2) . str_repeat('*', strlen($aadhaar) - 4) . substr($aadhaar, -2);
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
                'license_number' => $this->maskLicenseNumber($customer->license_number),
                'aadhaar_number' => $this->maskAadhaar($customer->aadhaar_number),
                'date_of_birth' => $customer->date_of_birth,
                'blood_group' => $customer->blood_group,
                'license_photo' => $customer->license_photo_url,
                'license_valid_from' => $customer->license_valid_from_non_transport,
                'license_valid_to' => $customer->license_valid_to_non_transport,
                'license_is_valid' => $customer->license_valid_to_non_transport 
                    ? Carbon::now()->lessThanOrEqualTo($customer->license_valid_to_non_transport)
                    : false,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
                'statistics' => $rentalStats
            ];
        } catch (QueryException $e) {
            Log::warning('Failed to load customer statistics', [
                'customer_id' => $customer->id,
                'error_code' => $e->getCode()
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
            'license_number' => $this->maskLicenseNumber($customer->license_number),
            'license_valid_to' => $customer->license_valid_to_non_transport,
            'license_is_valid' => $customer->license_valid_to_non_transport 
                ? Carbon::now()->lessThanOrEqualTo($customer->license_valid_to_non_transport)
                : false,
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
     * Get average rental duration for a customer
     */
    protected function getAverageRentalDuration(Customer $customer)
    {
        try {
            $completedRentals = $customer->rentals()
                ->where('status', 'completed')
                ->whereNotNull('start_time')
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
            Log::warning('Failed to get average rental duration', [
                'customer_id' => $customer->id,
                'error' => $e->getMessage()
            ]);
            
            return null;
        }
    }
    
    /**
     * Get rental frequency for a customer
     */
    protected function getRentalFrequency(Customer $customer)
    {
        try {
            $firstRental = $customer->rentals()
                ->where('status', 'completed')
                ->orderBy('created_at', 'asc')
                ->first();
            
            $lastRental = $customer->rentals()
                ->where('status', 'completed')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if (!$firstRental || !$lastRental || $firstRental->id === $lastRental->id) {
                return null;
            }
            
            $daysBetween = $firstRental->created_at->diffInDays($lastRental->created_at);
            $rentalCount = $customer->rentals()->where('status', 'completed')->count();
            
            if ($daysBetween === 0) {
                return null;
            }
            
            $averageDaysBetween = $daysBetween / ($rentalCount - 1);
            
            return [
                'average_days_between_rentals' => round($averageDaysBetween, 1),
                'rentals_per_month' => round(30 / $averageDaysBetween, 1),
                'total_rentals' => $rentalCount,
                'first_rental_date' => $firstRental->created_at,
                'last_rental_date' => $lastRental->created_at
            ];
        } catch (Exception $e) {
            Log::warning('Failed to get rental frequency', [
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
                'license_number' => $this->maskLicenseNumber($customer->license_number),
                'name' => $this->maskName($customer->name),
                'father_name' => $customer->father_name,
                'date_of_issue' => $customer->license_issue_date,
                'valid_from' => $customer->license_valid_from_non_transport,
                'valid_to' => $customer->license_valid_to_non_transport,
                'is_valid' => $customer->license_valid_to_non_transport 
                    ? Carbon::now()->lessThanOrEqualTo($customer->license_valid_to_non_transport)
                    : false,
                'address' => $customer->license_address,
                'address_list' => json_decode($customer->license_address_list, true) ?? [],
                'vehicle_classes' => json_decode($customer->vehicle_classes_data, true) ?? [],
                'reference_id' => $customer->license_reference_id,
                'photo_url' => $customer->license_photo_url,
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
                        'formatted_spent' => '₹' . number_format($customer->total_spent ?? 0, 2),
                        'average_spent_per_rental' => $customer->rentals_count > 0 
                            ? round(($customer->total_spent ?? 0) / $customer->rentals_count, 2)
                            : 0
                    ];
                });
        } catch (Exception $e) {
            Log::error('Failed to get top customers', ['error' => $e->getMessage()]);
            return [];
        }
    }
    
    /**
     * Get licenses expiring soon
     */
    protected function getLicensesExpiringSoon($days = 30)
    {
        try {
            $expiryDate = Carbon::now()->addDays($days);
            
            return Customer::whereNotNull('license_valid_to_non_transport')
                ->where('license_valid_to_non_transport', '<=', $expiryDate)
                ->where('license_valid_to_non_transport', '>=', Carbon::now())
                ->count();
        } catch (Exception $e) {
            Log::error('Failed to get licenses expiring soon', ['error' => $e->getMessage()]);
            return 0;
        }
    }
    
    /**
     * Get inactive customers
     */
    protected function getInactiveCustomers($days = 90)
    {
        try {
            $cutoffDate = Carbon::now()->subDays($days);
            
            return Customer::whereDoesntHave('rentals', function($query) use ($cutoffDate) {
                $query->where('created_at', '>=', $cutoffDate);
            })->count();
        } catch (Exception $e) {
            Log::error('Failed to get inactive customers', ['error' => $e->getMessage()]);
            return 0;
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
            // Sanitize each field for CSV
            $sanitizedRow = array_map(function($field) {
                if (is_string($field)) {
                    return htmlspecialchars_decode($field, ENT_QUOTES);
                }
                return $field;
            }, $row);
            fputcsv($output, $sanitizedRow);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        // Add UTF-8 BOM for Excel compatibility
        return "\xEF\xBB\xBF" . $csv;
    }
}
