<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Document;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Models\User;
use App\Services\CashfreeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use App\Traits\LogsCustomerAccess;
use Exception;
use PDF;

class RentalController extends Controller
{
    use LogsCustomerAccess;

    protected $cashfreeService;

    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * SINGLE API CALL - Start rental with instant verification
     * Uses DL number and DOB for verification, caches results for repeat customers
     *
     * Business Logic:
     * - Always charges shop owner ₹5 verification fee (platform revenue)
     * - Fresh verification: Platform pays Cashfree ₹2, profit ₹3
     * - Cached verification: Platform pays ₹0, profit ₹5
     * - Customer data shared across all shop owners
     */
    public function start(Request $request)
    {
        try {
            $validated = $request->validate([
                'vehicle_id'      => 'required|exists:vehicles,id',
                'customer_phone'  => 'required|string|max:20',
                'dl_number'       => 'required|string|max:20',
                'dob'             => 'required|date|date_format:Y-m-d',
                'license_image'   => 'nullable|file|image|mimes:jpeg,png,jpg|max:5120',
                'aadhaar_image'   => 'nullable|file|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            return DB::transaction(function () use ($request, $validated) {
                $shopOwner = auth()->user();

                if (!$shopOwner) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Shop owner not authenticated',
                    ], 401);
                }

                // STEP 1: Get verification price from settings (Shop owner pays ₹5)
                $verificationPrice = (float) Setting::where('key', 'verification_price')->value('value');

                if (!$verificationPrice) {
                    Log::error('Verification price not set in database settings');
                    return response()->json([
                        'success' => false,
                        'message' => 'Verification fee configuration missing',
                        'error'   => 'Please contact administrator to set verification price in settings',
                    ], 500);
                }

                // STEP 2: Lock vehicle and check availability
                $vehicle = Vehicle::where('id', $validated['vehicle_id'])
                    ->where('user_id', $shopOwner->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();

                if (!$vehicle) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Vehicle is not available for rent',
                        'errors'  => ['vehicle_id' => ['This vehicle is currently not available.']],
                    ], 409);
                }

                // STEP 3: Check shop owner wallet balance (always need ₹5)
                $user = User::where('id', $shopOwner->id)->lockForUpdate()->first();

                if ($user->wallet_balance < $verificationPrice) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Insufficient wallet balance',
                        'errors'  => [
                            'wallet' => [
                                'Need ₹' . number_format($verificationPrice, 2) . ' for DL verification. ' .
                                'Current balance: ₹' . number_format($user->wallet_balance, 2),
                            ],
                        ],
                    ], 402);
                }

                // STEP 4: Check if customer already exists in shared database
                $existingCustomer = Customer::where('license_number', $validated['dl_number'])
                    ->whereNotNull('license_data')
                    ->first();

                $dlVerification  = null;
                $isFromCache     = false;
                $rawData         = [];
                $details         = [];
                $validity        = [];
                $customerName    = null;
                $customerAddress = null;
                $fatherName      = null;
                $photoUrl        = null;
                $dateOfIssue     = null;
                $validFrom       = null;
                $validTo         = null;
                $addressList     = [];
                $vehicleClasses  = [];
                $referenceId     = null;
                $localPhotoPath  = null;

                if ($existingCustomer) {
                    // ============================================
                    // CACHED VERIFICATION - Use existing data
                    // Platform pays ₹0, profit ₹5
                    // ============================================
                    $this->logCachedVerification($existingCustomer->id);
                    $isFromCache = true;

                    // Update phone if different
                    if ($existingCustomer->phone !== $validated['customer_phone']) {
                        Log::info('Updating customer phone number', [
                            'customer_id' => $existingCustomer->id,
                            'old_phone'   => $existingCustomer->phone,
                            'new_phone'   => $validated['customer_phone'],
                        ]);
                        $existingCustomer->update(['phone' => $validated['customer_phone']]);
                    }

                    // Handle license_data - it might be string or already array from model cast
                    $licenseData = $existingCustomer->license_data;
                    if (is_string($licenseData)) {
                        $licenseData = json_decode($licenseData, true);
                    }
                    $licenseData = $licenseData ?? [];

                    // Handle address_list
                    $addressListData = $existingCustomer->license_address_list;
                    if (is_string($addressListData)) {
                        $addressListData = json_decode($addressListData, true);
                    }
                    $addressListData = $addressListData ?? [];

                    // Handle vehicle_classes
                    $vehicleClassesData = $existingCustomer->vehicle_classes_data;
                    if (is_string($vehicleClassesData)) {
                        $vehicleClassesData = json_decode($vehicleClassesData, true);
                    }
                    $vehicleClassesData = $vehicleClassesData ?? [];

                    // Use cached data
                    $dlVerification = [
                        'success'      => true,
                        'status'       => 'VALID',
                        'name'         => $existingCustomer->name,
                        'father_name'  => $existingCustomer->father_name,
                        'address'      => $existingCustomer->address,
                        'dl_number'    => $validated['dl_number'],
                        'dob'          => $existingCustomer->date_of_birth,
                        'date_of_issue'=> $existingCustomer->license_issue_date,
                        'valid_from'   => $existingCustomer->license_valid_from_non_transport,
                        'valid_to'     => $existingCustomer->license_valid_to_non_transport,
                        'vehicle_classes' => $vehicleClassesData,
                        'reference_id' => $existingCustomer->license_reference_id,
                        'photo_url'    => $existingCustomer->license_photo
                            ? asset('storage/' . $existingCustomer->license_photo)
                            : null,
                        'address_list' => $addressListData,
                        'raw_response' => $licenseData,
                        'from_cache'   => true,
                    ];
                    $localPhotoPath = $existingCustomer->license_photo;

                    Log::info('Using cached customer data - Pure profit for platform', [
                        'customer_id'     => $existingCustomer->id,
                        'dl_number'       => $validated['dl_number'],
                        'shop_owner_id'   => $shopOwner->id,
                        'platform_profit' => $verificationPrice,
                    ]);
                } else {
                    // ============================================
                    // FRESH VERIFICATION - Call Cashfree API
                    // Platform pays Cashfree ₹2, profit ₹3
                    // ============================================
                    $dlVerification = $this->cashfreeService->verifyDrivingLicense(
                        $validated['dl_number'],
                        $validated['dob']
                    );

                    if (!$dlVerification['success'] || $dlVerification['status'] !== 'VALID') {
                        return response()->json([
                            'success' => false,
                            'message' => 'Driving license verification failed',
                            'errors'  => [
                                'dl_number' => [$dlVerification['error'] ?? 'Invalid driving license number or date of birth'],
                            ],
                        ], 422);
                    }

                    Log::info('Fresh verification - Platform paid Cashfree', [
                        'dl_number'       => $validated['dl_number'],
                        'shop_owner_id'   => $shopOwner->id,
                        'cashfree_cost'   => 2,
                        'platform_profit' => $verificationPrice - 2,
                    ]);
                }

                // STEP 5: Extract all verified details
                $rawData         = $dlVerification['raw_response'] ?? [];
                $details         = $rawData['details_of_driving_licence'] ?? [];
                $validity        = $rawData['dl_validity'] ?? [];
                $customerName    = $dlVerification['name'];
                $customerAddress = $dlVerification['address'] ?? $details['address'] ?? null;
                $fatherName      = $dlVerification['father_name'] ?? $details['father_or_husband_name'] ?? null;
                $photoUrl        = $details['photo'] ?? null;
                $dateOfIssue     = $dlVerification['date_of_issue'] ?? null;
                $validFrom       = $dlVerification['valid_from'] ?? $validity['non_transport']['from'] ?? null;
                $validTo         = $dlVerification['valid_to'] ?? $validity['non_transport']['to'] ?? null;
                $addressList     = $details['address_list'] ?? [];
                $vehicleClasses  = $dlVerification['vehicle_classes'] ?? [];
                $referenceId     = $dlVerification['reference_id'] ?? null;

                // STEP 6: Download photo (only for new customers)
                if (!$isFromCache && $photoUrl) {
                    try {
                        $photoContents = file_get_contents($photoUrl);
                        if ($photoContents !== false) {
                            $photoDir = 'customers/photos/' . date('Y/m/d');
                            Storage::disk('public')->makeDirectory($photoDir, 0755, true);
                            $photoFileName  = 'customer_' . $validated['customer_phone'] . '_' . date('YmdHis') . '.jpg';
                            $localPhotoPath = $photoDir . '/' . $photoFileName;
                            Storage::disk('public')->put($localPhotoPath, $photoContents);

                            Log::info('Customer photo downloaded', [
                                'customer_phone' => $validated['customer_phone'],
                                'photo_path'     => $localPhotoPath,
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::error('Failed to download customer photo', [
                            'customer_phone' => $validated['customer_phone'],
                            'error'          => $e->getMessage(),
                        ]);
                    }
                }

                // STEP 7: Create or update customer (shared across all shop owners)
                $customer = Customer::updateOrCreate(
                    ['license_number' => $validated['dl_number']],
                    [
                        'name'                              => $customerName,
                        'father_name'                       => $fatherName,
                        'phone'                             => $validated['customer_phone'],
                        'address'                           => $customerAddress,
                        'license_address'                   => $customerAddress,
                        'license_address_list'              => json_encode($addressList),
                        'date_of_birth'                     => $validated['dob'],
                        'license_issue_date'                => $dateOfIssue,
                        'license_valid_from_non_transport'  => $validFrom,
                        'license_valid_to_non_transport'    => $validTo,
                        'license_photo'                     => $localPhotoPath,
                        'license_data'                      => json_encode($rawData),
                        'license_reference_id'              => $referenceId,
                        'vehicle_classes_data'              => json_encode($vehicleClasses),
                    ]
                );

                // Log fresh verification if this was a new customer
                if (!$isFromCache) {
                    $this->logFreshVerification($customer->id);
                }

                // STEP 8: Handle document uploads (optional)
                $newLicensePath = null;
                $newAadhaarPath = null;

                if ($request->hasFile('license_image')) {
                    $permanentDir = 'documents/' . date('Y/m/d');
                    Storage::disk('public')->makeDirectory($permanentDir, 0755, true);
                    $newLicensePath = $request->file('license_image')->store($permanentDir, 'public');
                }

                if ($request->hasFile('aadhaar_image')) {
                    $permanentDir = 'documents/' . date('Y/m/d');
                    Storage::disk('public')->makeDirectory($permanentDir, 0755, true);
                    $newAadhaarPath = $request->file('aadhaar_image')->store($permanentDir, 'public');
                }

                // STEP 9: Deduct verification fee from shop owner (ALWAYS ₹5)
                $user->wallet_balance -= $verificationPrice;
                $user->save();

                $transaction = WalletTransaction::create([
                    'user_id'    => $user->id,
                    'amount'     => $verificationPrice,
                    'type'       => 'debit',
                    'reason'     => 'Driving license verification (DL: ' . $validated['dl_number'] . ')',
                    'status'     => 'completed',
                    'metadata'   => json_encode([
                        'is_cached'   => $isFromCache,
                        'dl_number'   => $validated['dl_number'],
                        'customer_id' => $customer->id,
                    ]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // STEP 10: Create rental
                $rental = Rental::create([
                    'user_id'     => $shopOwner->id,
                    'vehicle_id'  => $vehicle->id,
                    'customer_id' => $customer->id,
                    'status'      => 'active',
                    'start_time'  => now(),
                    'total_price' => 0,
                ]);

                // STEP 11: Create document record and link to rental immediately
                $document = Document::create([
                    'rental_id'           => $rental->id,
                    'license_image'       => $newLicensePath,
                    'aadhaar_image'       => $newAadhaarPath,
                    'is_verified'         => true,
                    'verification_status' => 'verified',
                    'license_ocr_data'    => json_encode($rawData),
                    'extracted_name'      => $customerName,
                    'extracted_license'   => $validated['dl_number'],
                    'verified_at'         => now(),
                ]);

                // FIX: Save document_id on rental so show() can eager-load it
                $rental->update(['document_id' => $document->id]);
                $vehicle->update(['status' => 'on_rent']);

                // STEP 12: Generate agreement PDF
                // FIX: Ensure agreements directory exists before PDF generation
                Storage::disk('public')->makeDirectory('agreements', 0755, true);

                $agreementPath = $this->generateAgreement(
                    $rental, $customer, $vehicle, $document, $transaction, $shopOwner, $dlVerification
                );

                // FIX: Save agreement_path on rental so show() returns it
                if ($agreementPath) {
                    $rental->update(['agreement_path' => $agreementPath]);
                    $rental->refresh(); // ensure in-memory model has latest values
                }

                // STEP 13: Log rental start activity
                $this->logRentalStart($customer->id, $rental->id);

                Log::info('Rental started successfully', [
                    'rental_id'       => $rental->id,
                    'dl_number'       => $validated['dl_number'],
                    'customer_phone'  => $validated['customer_phone'],
                    'from_cache'      => $isFromCache,
                    'shop_owner_id'   => $shopOwner->id,
                    'verification_fee'=> $verificationPrice,
                    'platform_profit' => $isFromCache ? $verificationPrice : $verificationPrice - 2,
                    'agreement_path'  => $agreementPath,
                    'license_image'   => $newLicensePath,
                    'aadhaar_image'   => $newAadhaarPath,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'License verified! Rental started successfully.',
                    'data'    => [
                        'rental_id'                  => $rental->id,
                        'status'                     => 'active',
                        'start_time'                 => $rental->start_time,
                        'verification_fee'           => (float) $verificationPrice,
                        'formatted_verification_fee' => '₹' . number_format($verificationPrice, 2),
                        'wallet_balance'             => (float) $user->wallet_balance,
                        'formatted_wallet_balance'   => '₹' . number_format($user->wallet_balance, 2),
                        'transaction_id'             => $transaction->id,
                        'agreement_path'             => $agreementPath
                            ? asset('storage/' . $agreementPath)
                            : null,
                        'document' => [
                            'id'            => $document->id,
                            'license_image' => $document->license_image
                                ? asset('storage/' . $document->license_image)
                                : null,
                            'aadhaar_image' => $document->aadhaar_image
                                ? asset('storage/' . $document->aadhaar_image)
                                : null,
                            'is_verified'   => $document->is_verified,
                            'verified_at'   => $document->verified_at,
                        ],
                        'customer' => [
                            'id'                  => $customer->id,
                            'name'                => $customer->name,
                            'father_name'         => $customer->father_name,
                            'phone'               => $customer->phone,
                            'address'             => $customer->address,
                            'license_number'      => $customer->license_number,
                            'date_of_birth'       => $customer->date_of_birth,
                            'license_issue_date'  => $customer->license_issue_date,
                            'license_valid_from'  => $customer->license_valid_from_non_transport,
                            'license_valid_to'    => $customer->license_valid_to_non_transport,
                            'license_photo'       => $customer->license_photo
                                ? asset('storage/' . $customer->license_photo)
                                : null,
                        ],
                        'vehicle' => [
                            'id'           => $vehicle->id,
                            'name'         => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                            'hourly_rate'  => (float) $vehicle->hourly_rate,
                            'daily_rate'   => (float) $vehicle->daily_rate,
                        ],
                        'license_details' => [
                            'status'       => 'VALID',
                            'name'         => $customerName,
                            'father_name'  => $fatherName,
                            'dl_number'    => $validated['dl_number'],
                            'address'      => $customerAddress,
                            'date_of_birth'=> $validated['dob'],
                            'date_of_issue'=> $dateOfIssue,
                            'valid_from'   => $validFrom,
                            'valid_to'     => $validTo,
                            'photo_url'    => $localPhotoPath
                                ? asset('storage/' . $localPhotoPath)
                                : null,
                        ],
                    ],
                ], 200);
            });
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors'  => $e->errors(),
            ], 422);
        } catch (Exception $e) {
            Log::error('Rental start error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start rental',
                'error'   => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * End rental and calculate final price
     */
    public function end($id)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rental = Rental::where('id', $id)
                ->where('user_id', $userId)
                ->where('status', 'active')
                ->with(['vehicle', 'customer', 'user'])
                ->first();

            if (!$rental) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rental not found or already completed',
                ], 404);
            }

            return DB::transaction(function () use ($rental) {
                $rental->end_time = now();

                $totalMinutes = $rental->start_time->diffInMinutes($rental->end_time);
                $totalHours   = $totalMinutes / 60;

                $leaseThreshold = Setting::where('key', 'lease_threshold_minutes')->value('value');
                if (!$leaseThreshold || $leaseThreshold <= 0) {
                    $leaseThreshold = 60;
                }

                if ($totalMinutes <= $leaseThreshold) {
                    $hoursToCharge = 1;
                } else {
                    $hoursToCharge = ceil($totalHours);
                }

                $vehicle    = $rental->vehicle;
                $totalPrice = $this->calculatePrice($vehicle, $hoursToCharge);

                $rental->total_price = $totalPrice;
                $rental->status      = 'completed';
                $rental->save();

                $vehicle->update(['status' => 'available']);

                // FIX: Ensure receipts directory exists before PDF generation
                Storage::disk('public')->makeDirectory('receipts', 0755, true);

                $receiptPath = $this->generateReceipt($rental);
                if ($receiptPath) {
                    $rental->update(['receipt_path' => $receiptPath]);
                    $rental->refresh();
                }

                // Log rental end activity
                $this->logRentalEnd($rental->customer_id, $rental->id);

                Log::info('Rental completed successfully', [
                    'rental_id'        => $rental->id,
                    'customer_id'      => $rental->customer_id,
                    'shop_owner_id'    => $rental->user_id,
                    'total_price'      => $totalPrice,
                    'duration_minutes' => $totalMinutes,
                    'receipt_path'     => $receiptPath,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Rental completed successfully',
                    'data'    => [
                        'rental_id'              => $rental->id,
                        'total_price'            => (float) $rental->total_price,
                        'formatted_price'        => '₹' . number_format($rental->total_price, 2),
                        'total_minutes'          => $totalMinutes,
                        'total_hours'            => round($totalHours, 2),
                        'hours_charged'          => $hoursToCharge,
                        'lease_threshold_minutes'=> (int) $leaseThreshold,
                        'start_time'             => $rental->start_time,
                        'end_time'               => $rental->end_time,
                        'duration_text'          => $this->formatDuration($totalMinutes),
                        'receipt_path'           => $receiptPath
                            ? asset('storage/' . $receiptPath)
                            : null,
                        'vehicle' => [
                            'id'           => $vehicle->id,
                            'name'         => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                            'hourly_rate'  => (float) $vehicle->hourly_rate,
                            'daily_rate'   => (float) $vehicle->daily_rate,
                        ],
                        'customer' => [
                            'id'    => $rental->customer->id,
                            'name'  => $rental->customer->name,
                            'phone' => $rental->customer->phone,
                        ],
                    ],
                ]);
            });
        } catch (Exception $e) {
            Log::error('Error ending rental: ' . $e->getMessage(), [
                'rental_id' => $id,
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to complete rental',
                'error'   => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get active rentals
     */
    public function active(Request $request)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rentals = Rental::where('user_id', $userId)
                ->where('status', 'active')
                ->with(['vehicle', 'customer'])
                ->orderBy('start_time', 'desc')
                ->get()
                ->map(function ($rental) {
                    $elapsedMinutes = $rental->start_time->diffInMinutes(now());

                    return [
                        'rental_id'       => $rental->id,
                        'vehicle'         => [
                            'id'           => $rental->vehicle->id,
                            'name'         => $rental->vehicle->name,
                            'number_plate' => $rental->vehicle->number_plate,
                            'hourly_rate'  => (float) $rental->vehicle->hourly_rate,
                            'daily_rate'   => (float) $rental->vehicle->daily_rate,
                        ],
                        'customer'        => [
                            'id'             => $rental->customer->id,
                            'name'           => $rental->customer->name,
                            'phone'          => $rental->customer->phone,
                            'license_number' => $rental->customer->license_number,
                        ],
                        'start_time'      => $rental->start_time,
                        'elapsed_minutes' => $elapsedMinutes,
                        'elapsed_hours'   => round($elapsedMinutes / 60, 2),
                        'estimated_price' => $this->calculateEstimatedPrice($rental),
                        'agreement_path'  => $rental->agreement_path
                            ? asset('storage/' . $rental->agreement_path)
                            : null,
                    ];
                });

            return response()->json([
                'success' => true,
                'data'    => $rentals,
                'total'   => $rentals->count(),
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching active rentals: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch active rentals',
                'error'   => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get rental history
     */
    public function history(Request $request)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rentals = Rental::where('user_id', $userId)
                ->where('status', 'completed')
                ->with(['vehicle', 'customer'])
                ->orderBy('end_time', 'desc')
                ->paginate($request->get('per_page', 20))
                ->through(function ($rental) {
                    return [
                        'id'      => $rental->id,
                        'vehicle' => [
                            'id'           => $rental->vehicle->id,
                            'name'         => $rental->vehicle->name,
                            'number_plate' => $rental->vehicle->number_plate,
                        ],
                        'customer' => [
                            'id'             => $rental->customer->id,
                            'name'           => $rental->customer->name,
                            'phone'          => $rental->customer->phone,
                            'license_number' => $rental->customer->license_number,
                        ],
                        'start_time'       => $rental->start_time,
                        'end_time'         => $rental->end_time,
                        'duration_minutes' => $rental->start_time->diffInMinutes($rental->end_time),
                        'duration_text'    => $this->formatDuration(
                            $rental->start_time->diffInMinutes($rental->end_time)
                        ),
                        'total_price'      => (float) $rental->total_price,
                        'formatted_price'  => '₹' . number_format($rental->total_price, 2),
                        'receipt_path'     => $rental->receipt_path
                            ? asset('storage/' . $rental->receipt_path)
                            : null,
                        'agreement_path'   => $rental->agreement_path
                            ? asset('storage/' . $rental->agreement_path)
                            : null,
                        'completed_at'     => $rental->updated_at,
                    ];
                });

            return response()->json([
                'success'    => true,
                'data'       => $rentals->items(),
                'pagination' => [
                    'current_page' => $rentals->currentPage(),
                    'last_page'    => $rentals->lastPage(),
                    'per_page'     => $rentals->perPage(),
                    'total'        => $rentals->total(),
                    'from'         => $rentals->firstItem(),
                    'to'           => $rentals->lastItem(),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching rental history: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental history',
                'error'   => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get a specific rental by ID with full details
     * Accessible by the shop owner who created the rental
     *
     * FIX: Added 'document' to eager load, and document block to response
     */
    public function show($id)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            // FIX: Added 'document' to the with() call
            $rental = Rental::where('id', $id)
                ->where('user_id', $userId)
                ->with(['vehicle', 'customer', 'user', 'document'])
                ->first();

            if (!$rental) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rental not found',
                ], 404);
            }

            // Calculate duration
            $durationMinutes = null;
            $durationText    = null;
            $totalPrice      = (float) $rental->total_price;

            if ($rental->status === 'completed' && $rental->end_time) {
                $durationMinutes = $rental->start_time->diffInMinutes($rental->end_time);
                $durationText    = $this->formatDuration($durationMinutes);
            } elseif ($rental->status === 'active') {
                $durationMinutes = $rental->start_time->diffInMinutes(now());
                $durationText    = $this->formatDuration($durationMinutes);
                $totalPrice      = $this->calculateEstimatedPrice($rental);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'id'                   => $rental->id,
                    'status'               => $rental->status,
                    'start_time'           => $rental->start_time,
                    'end_time'             => $rental->end_time,
                    'duration_minutes'     => $durationMinutes,
                    'duration_text'        => $durationText,
                    'total_price'          => $totalPrice,
                    'formatted_total_price'=> '₹' . number_format($totalPrice, 2),
                    'agreement_path'       => $rental->agreement_path
                        ? asset('storage/' . $rental->agreement_path)
                        : null,
                    'receipt_path'         => $rental->receipt_path
                        ? asset('storage/' . $rental->receipt_path)
                        : null,
                    'created_at'           => $rental->created_at,
                    'updated_at'           => $rental->updated_at,

                    // FIX: document block now included with license & aadhaar image paths
                    'document' => $rental->document ? [
                        'id'            => $rental->document->id,
                        'license_image' => $rental->document->license_image
                            ? asset('storage/' . $rental->document->license_image)
                            : null,
                        'aadhaar_image' => $rental->document->aadhaar_image
                            ? asset('storage/' . $rental->document->aadhaar_image)
                            : null,
                        'is_verified'   => $rental->document->is_verified,
                        'verified_at'   => $rental->document->verified_at,
                    ] : null,

                    'vehicle' => [
                        'id'           => $rental->vehicle->id,
                        'name'         => $rental->vehicle->name,
                        'number_plate' => $rental->vehicle->number_plate,
                        'type'         => $rental->vehicle->type ?? 'Standard',
                        'hourly_rate'  => (float) ($rental->vehicle->hourly_rate ?? 0),
                        'daily_rate'   => (float) ($rental->vehicle->daily_rate ?? 0),
                    ],
                    'customer' => [
                        'id'             => $rental->customer->id,
                        'name'           => $rental->customer->name,
                        'phone'          => $rental->customer->phone,
                        'address'        => $rental->customer->address,
                        'license_number' => $rental->customer->license_number,
                        'license_photo'  => $rental->customer->license_photo
                            ? asset('storage/' . $rental->customer->license_photo)
                            : null,
                    ],
                    'shop_owner' => [
                        'id'    => $rental->user->id,
                        'name'  => $rental->user->name,
                        'phone' => $rental->user->phone,
                    ],
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching rental details: ' . $e->getMessage(), [
                'rental_id' => $id,
                'trace'     => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental details',
                'error'   => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Get rental statistics for the authenticated shop owner
     */
    public function statistics(Request $request)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rentals          = Rental::where('user_id', $userId);
            $completedRentals = Rental::where('user_id', $userId)->where('status', 'completed');
            $activeRentals    = Rental::where('user_id', $userId)->where('status', 'active');

            $statistics = [
                'summary' => [
                    'total_rentals'       => $rentals->count(),
                    'active_rentals'      => $activeRentals->count(),
                    'completed_rentals'   => $completedRentals->count(),
                    'cancelled_rentals'   => Rental::where('user_id', $userId)->where('status', 'cancelled')->count(),
                    'total_revenue'       => (float) $completedRentals->sum('total_price'),
                    'average_rental_value'=> $completedRentals->count() > 0
                        ? round($completedRentals->sum('total_price') / $completedRentals->count(), 2)
                        : 0,
                    'completion_rate'     => $rentals->count() > 0
                        ? round(($completedRentals->count() / $rentals->count()) * 100, 2)
                        : 0,
                ],
                'period' => [
                    'last_24_hours' => [
                        'rentals' => Rental::where('user_id', $userId)
                            ->where('created_at', '>=', now()->subDay())
                            ->count(),
                        'revenue' => (float) Rental::where('user_id', $userId)
                            ->where('status', 'completed')
                            ->where('created_at', '>=', now()->subDay())
                            ->sum('total_price'),
                    ],
                    'last_7_days' => [
                        'rentals' => Rental::where('user_id', $userId)
                            ->where('created_at', '>=', now()->subDays(7))
                            ->count(),
                        'revenue' => (float) Rental::where('user_id', $userId)
                            ->where('status', 'completed')
                            ->where('created_at', '>=', now()->subDays(7))
                            ->sum('total_price'),
                    ],
                    'last_30_days' => [
                        'rentals' => Rental::where('user_id', $userId)
                            ->where('created_at', '>=', now()->subDays(30))
                            ->count(),
                        'revenue' => (float) Rental::where('user_id', $userId)
                            ->where('status', 'completed')
                            ->where('created_at', '>=', now()->subDays(30))
                            ->sum('total_price'),
                    ],
                    'this_month' => [
                        'rentals' => Rental::where('user_id', $userId)
                            ->whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->count(),
                        'revenue' => (float) Rental::where('user_id', $userId)
                            ->where('status', 'completed')
                            ->whereMonth('created_at', now()->month)
                            ->whereYear('created_at', now()->year)
                            ->sum('total_price'),
                    ],
                ],
                'top_vehicles' => Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->select('vehicle_id', DB::raw('COUNT(*) as rental_count'), DB::raw('SUM(total_price) as revenue'))
                    ->with('vehicle')
                    ->groupBy('vehicle_id')
                    ->orderBy('revenue', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'vehicle_id'   => $item->vehicle_id,
                            'vehicle_name' => $item->vehicle->name ?? 'N/A',
                            'rental_count' => $item->rental_count,
                            'revenue'      => (float) $item->revenue,
                        ];
                    }),
                'top_customers' => Rental::where('user_id', $userId)
                    ->where('status', 'completed')
                    ->select('customer_id', DB::raw('COUNT(*) as rental_count'), DB::raw('SUM(total_price) as total_spent'))
                    ->with('customer')
                    ->groupBy('customer_id')
                    ->orderBy('total_spent', 'desc')
                    ->limit(5)
                    ->get()
                    ->map(function ($item) {
                        return [
                            'customer_id'    => $item->customer_id,
                            'customer_name'  => $item->customer->name ?? 'N/A',
                            'customer_phone' => $item->customer->phone ?? 'N/A',
                            'rental_count'   => $item->rental_count,
                            'total_spent'    => (float) $item->total_spent,
                        ];
                    }),
            ];

            return response()->json([
                'success' => true,
                'data'    => $statistics,
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching rental statistics: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch rental statistics',
                'error'   => config('app.debug') ? $e->getMessage() : 'An unexpected error occurred',
            ], 500);
        }
    }

    /**
     * Download rental agreement
     */
    public function downloadAgreement($id)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rental = Rental::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$rental) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rental not found',
                ], 404);
            }

            if (!$rental->agreement_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agreement not found',
                ], 404);
            }

            if (!Storage::disk('public')->exists($rental->agreement_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Agreement file not found on disk',
                ], 404);
            }

            return Storage::disk('public')->download($rental->agreement_path, "agreement_{$rental->id}.pdf");
        } catch (Exception $e) {
            Log::error('Error downloading agreement: ' . $e->getMessage(), [
                'rental_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download agreement',
            ], 500);
        }
    }

    /**
     * Download rental receipt
     */
    public function downloadReceipt($id)
    {
        try {
            $userId = auth()->id();

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated',
                ], 401);
            }

            $rental = Rental::where('id', $id)
                ->where('user_id', $userId)
                ->first();

            if (!$rental) {
                return response()->json([
                    'success' => false,
                    'message' => 'Rental not found',
                ], 404);
            }

            if (!$rental->receipt_path) {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt not found',
                ], 404);
            }

            if (!Storage::disk('public')->exists($rental->receipt_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Receipt file not found on disk',
                ], 404);
            }

            return Storage::disk('public')->download($rental->receipt_path, "receipt_{$rental->id}.pdf");
        } catch (Exception $e) {
            Log::error('Error downloading receipt: ' . $e->getMessage(), [
                'rental_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to download receipt',
            ], 500);
        }
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    protected function calculatePrice(Vehicle $vehicle, int $hours): float
    {
        if ($vehicle->hourly_rate && $hours <= 24) {
            return (float) $vehicle->hourly_rate * $hours;
        }

        if ($vehicle->daily_rate) {
            $days = max(1, ceil($hours / 24));
            return (float) $vehicle->daily_rate * $days;
        }

        return (float) $vehicle->hourly_rate * $hours;
    }

    protected function calculateEstimatedPrice(Rental $rental): float
    {
        $elapsedMinutes = $rental->start_time->diffInMinutes(now());
        $elapsedHours   = $elapsedMinutes / 60;

        $leaseThreshold = (int) Setting::where('key', 'lease_threshold_minutes')->value('value');
        if (!$leaseThreshold || $leaseThreshold <= 0) {
            $leaseThreshold = 60;
        }

        if ($elapsedMinutes <= $leaseThreshold) {
            $hoursToCharge = 1;
        } else {
            $hoursToCharge = ceil($elapsedHours);
        }

        return $this->calculatePrice($rental->vehicle, $hoursToCharge);
    }

    protected function generateAgreement($rental, $customer, $vehicle, $document, $transaction = null, $shopOwner = null, $dlVerification = null)
    {
        try {
            $agreementNumber   = 'AGR-' . strtoupper(uniqid()) . '-' . date('Ymd');
            $verificationPrice = Setting::where('key', 'verification_price')->value('value') ?? 0;

            // FIX: Ensure the agreements directory exists
            Storage::disk('public')->makeDirectory('agreements', 0755, true);

            // Get customer photo path
            $customerPhotoPath = null;
            if ($customer->license_photo) {
                $fullPath = Storage::disk('public')->path($customer->license_photo);
                if (file_exists($fullPath)) {
                    $photoData         = file_get_contents($fullPath);
                    $customerPhotoPath = 'data:image/jpeg;base64,' . base64_encode($photoData);
                }
            }

            // Get document images
            $licenseImageBase64 = null;
            if ($document->license_image && Storage::disk('public')->exists($document->license_image)) {
                $licenseImageData   = Storage::disk('public')->get($document->license_image);
                $licenseImageBase64 = 'data:image/jpeg;base64,' . base64_encode($licenseImageData);
            }

            $aadhaarImageBase64 = null;
            if ($document->aadhaar_image && Storage::disk('public')->exists($document->aadhaar_image)) {
                $aadhaarImageData   = Storage::disk('public')->get($document->aadhaar_image);
                $aadhaarImageBase64 = 'data:image/jpeg;base64,' . base64_encode($aadhaarImageData);
            }

            $agreementData = [
                'agreement_number' => $agreementNumber,
                'rental_id'        => $rental->id,
                'date'             => now()->format('d/m/Y'),
                'time'             => now()->format('h:i A'),
                'shop_owner'       => [
                    'name'    => $shopOwner ? $shopOwner->name    : ($rental->user->name    ?? 'Vehicle Rental Shop'),
                    'phone'   => $shopOwner ? $shopOwner->phone   : ($rental->user->phone   ?? 'N/A'),
                    'address' => $shopOwner ? ($shopOwner->address ?? 'N/A') : 'N/A',
                ],
                'customer' => [
                    'id'                  => $customer->id,
                    'name'                => $customer->name,
                    'father_name'         => $customer->father_name,
                    'phone'               => $customer->phone,
                    'address'             => $customer->address,
                    'license_number'      => $customer->license_number,
                    'date_of_birth'       => $customer->date_of_birth
                        ? date('d/m/Y', strtotime($customer->date_of_birth))
                        : 'N/A',
                    'license_issue_date'  => $customer->license_issue_date
                        ? date('d/m/Y', strtotime($customer->license_issue_date))
                        : 'N/A',
                    'license_valid_from'  => $customer->license_valid_from_non_transport
                        ? date('d/m/Y', strtotime($customer->license_valid_from_non_transport))
                        : 'N/A',
                    'license_valid_to'    => $customer->license_valid_to_non_transport
                        ? date('d/m/Y', strtotime($customer->license_valid_to_non_transport))
                        : 'N/A',
                    'photo'               => $customerPhotoPath,
                ],
                'vehicle' => [
                    'id'           => $vehicle->id,
                    'name'         => $vehicle->name,
                    'number_plate' => $vehicle->number_plate,
                    'type'         => $vehicle->type ?? 'Standard',
                    'hourly_rate'  => number_format($vehicle->hourly_rate, 2),
                    'daily_rate'   => number_format($vehicle->daily_rate, 2),
                    'model'        => $vehicle->model ?? 'N/A',
                    'year'         => $vehicle->year ?? 'N/A',
                ],
                'terms' => [
                    'hourly_rate'      => number_format($vehicle->hourly_rate, 2),
                    'daily_rate'       => number_format($vehicle->daily_rate, 2),
                    'lease_threshold'  => Setting::where('key', 'lease_threshold_minutes')->value('value') ?? 60,
                    'verification_fee' => number_format($verificationPrice, 2),
                ],
                'documents' => [
                    'license_image'      => $licenseImageBase64,
                    'aadhaar_image'      => $aadhaarImageBase64,
                    'license_image_path' => $document->license_image,
                    'aadhaar_image_path' => $document->aadhaar_image,
                ],
                'vehicle_classes' => json_decode($customer->vehicle_classes_data ?? '[]', true),
                'address_list'    => json_decode($customer->license_address_list ?? '[]', true),
                'transaction'     => $transaction ? [
                    'id'     => $transaction->id,
                    'amount' => number_format($transaction->amount, 2),
                    'type'   => $transaction->type,
                    'date'   => $transaction->created_at->format('d/m/Y H:i:s'),
                ] : null,
                'start_time'   => $rental->start_time->format('d/m/Y h:i A'),
                'generated_at' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = PDF::loadView('pdf.rental-agreement', $agreementData);
            $pdf->setPaper('A4', 'portrait');

            $filename = "agreements/agreement_{$rental->id}_{$agreementNumber}.pdf";
            Storage::disk('public')->put($filename, $pdf->output());

            Log::info('Agreement generated successfully', [
                'rental_id'      => $rental->id,
                'agreement_path' => $filename,
            ]);

            return $filename;
        } catch (Exception $e) {
            Log::error('Agreement generation failed', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    protected function generateReceipt($rental)
    {
        try {
            $receiptNumber     = 'RCT-' . strtoupper(uniqid()) . '-' . date('Ymd');
            $verificationPrice = Setting::where('key', 'verification_price')->value('value') ?? 0;

            // FIX: Ensure receipts directory exists
            Storage::disk('public')->makeDirectory('receipts', 0755, true);

            $totalMinutes   = $rental->start_time->diffInMinutes($rental->end_time);
            $totalHours     = $totalMinutes / 60;
            $leaseThreshold = Setting::where('key', 'lease_threshold_minutes')->value('value') ?? 60;

            if ($totalMinutes <= $leaseThreshold) {
                $hoursToCharge = 1;
            } else {
                $hoursToCharge = ceil($totalHours);
            }

            $receiptData = [
                'receipt_number' => $receiptNumber,
                'rental_id'      => $rental->id,
                'date'           => now()->format('d/m/Y'),
                'time'           => now()->format('h:i A'),
                'shop_owner'     => [
                    'name'  => $rental->user->name  ?? 'Vehicle Rental Shop',
                    'phone' => $rental->user->phone ?? 'N/A',
                ],
                'customer' => [
                    'name'           => $rental->customer->name,
                    'phone'          => $rental->customer->phone,
                    'address'        => $rental->customer->address,
                    'license_number' => $rental->customer->license_number,
                ],
                'vehicle' => [
                    'name'         => $rental->vehicle->name,
                    'number_plate' => $rental->vehicle->number_plate,
                    'hourly_rate'  => number_format($rental->vehicle->hourly_rate, 2),
                    'daily_rate'   => number_format($rental->vehicle->daily_rate, 2),
                ],
                'rental_period' => [
                    'start_time'     => $rental->start_time->format('d/m/Y h:i A'),
                    'end_time'       => $rental->end_time->format('d/m/Y h:i A'),
                    'duration'       => $this->formatDuration($totalMinutes),
                    'total_minutes'  => $totalMinutes,
                    'total_hours'    => round($totalHours, 2),
                    'hours_charged'  => $hoursToCharge,
                    'lease_threshold'=> $leaseThreshold,
                ],
                'charges' => [
                    'verification_fee' => number_format($verificationPrice, 2),
                    'rental_amount'    => number_format($rental->total_price, 2),
                    'total'            => number_format($verificationPrice + $rental->total_price, 2),
                ],
                'payment_status' => 'Paid',
                'payment_method' => 'Wallet',
                'generated_at'   => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = PDF::loadView('pdf.rental-receipt', $receiptData);
            $pdf->setPaper('A4', 'portrait');

            $filename = "receipts/receipt_{$rental->id}_{$receiptNumber}.pdf";
            Storage::disk('public')->put($filename, $pdf->output());

            Log::info('Receipt generated successfully', [
                'rental_id'    => $rental->id,
                'receipt_path' => $filename,
            ]);

            return $filename;
        } catch (Exception $e) {
            Log::error('Receipt generation failed', [
                'rental_id' => $rental->id,
                'error'     => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    protected function formatDuration(int $minutes): string
    {
        $hours             = floor($minutes / 60);
        $remainingMinutes  = $minutes % 60;

        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} hour(s) {$remainingMinutes} minute(s)";
        } elseif ($hours > 0) {
            return "{$hours} hour(s)";
        } else {
            return "{$remainingMinutes} minute(s)";
        }
    }

    protected function generateReferenceId(): string
    {
        return 'VER_' . strtoupper(uniqid()) . '_' . date('YmdHis');
    }
}