<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerAccessLog;
use App\Models\Document;
use App\Models\Rental;
use App\Models\Setting;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WalletTransaction;
use App\Services\CashfreeService;
use App\Traits\LogsCustomerAccess;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PDF;

class RentalController extends Controller
{
    use LogsCustomerAccess;

    protected $cashfreeService;

    protected $allowedImageMimes = ['image/jpeg', 'image/png', 'image/jpg'];

    protected $allowedVideoMimes = ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/mpeg'];

    protected $maxDamageAmount = 100000;

    protected $maxRentalHours = 720;

    public function __construct(CashfreeService $cashfreeService)
    {
        $this->cashfreeService = $cashfreeService;
    }

    /**
     * PHASE 1: VERIFICATION & CUSTOMER SAVE
     */
    public function phase1Verify(Request $request)
    {
        try {
            $validated = $request->validate([
                'vehicle_id' => 'required|exists:vehicles,id',
                'customer_phone' => 'required|string|max:20|regex:/^[0-9]{10,15}$/',
                'dl_number' => 'required|string|max:20|regex:/^[A-Z0-9]{6,20}$/i',
                'dob' => 'required|date|date_format:Y-m-d|before:today|after:1900-01-01',
            ]);

            $shopOwner = auth()->user();
            if (! $shopOwner) {
                return response()->json(['success' => false, 'message' => 'Shop owner not authenticated'], 401);
            }

            $vehicle = Vehicle::where('id', $validated['vehicle_id'])
                ->where('user_id', $shopOwner->id)
                ->where('status', 'available')
                ->first();
            if (! $vehicle) {
                return response()->json(['success' => false, 'message' => 'Vehicle is not available for rent'], 409);
            }

            $verificationPrice = (float) Setting::where('key', 'verification_price')->value('value');
            if (! $verificationPrice || $verificationPrice <= 0) {
                return response()->json(['success' => false, 'message' => 'Verification fee configuration missing'], 500);
            }

            $user = User::where('id', $shopOwner->id)->lockForUpdate()->first();
            if (! $user) {
                return response()->json(['success' => false, 'message' => 'User not found'], 404);
            }
            if ($user->wallet_balance < $verificationPrice) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient wallet balance',
                    'errors' => ['wallet' => ['Need ₹'.number_format($verificationPrice, 2).'. Current: ₹'.number_format($user->wallet_balance, 2)]],
                ], 402);
            }

            // Use license_number_hash for lookup
            $dlNumber = strtoupper($validated['dl_number']);
            $licenseHash = hash('sha256', $dlNumber);
            $existingCustomer = Customer::where('license_number_hash', $licenseHash)->first();

            $dlVerification = null;
            $isFromCache = false;
            $customerData = [];
            $referenceId = null;

            if ($existingCustomer && $existingCustomer->license_data) {
                // Cached verification
                $isFromCache = true;
                $licenseData = is_string($existingCustomer->license_data) ? json_decode($existingCustomer->license_data, true) : $existingCustomer->license_data;
                $addressList = $existingCustomer->license_address_list ? json_decode($existingCustomer->license_address_list, true) : [];
                $vehicleClasses = $existingCustomer->vehicle_classes_data ? json_decode($existingCustomer->vehicle_classes_data, true) : [];

                $dlVerification = [
                    'success' => true,
                    'status' => 'VALID',
                    'name' => $this->sanitizeInput($existingCustomer->name),
                    'father_name' => $this->sanitizeInput($existingCustomer->father_name),
                    'address' => $this->sanitizeInput($existingCustomer->address),
                    'dl_number' => $dlNumber,
                    'dob' => $existingCustomer->date_of_birth,
                    'date_of_issue' => $existingCustomer->license_issue_date,
                    'valid_from' => $existingCustomer->license_valid_from_non_transport,
                    'valid_to' => $existingCustomer->license_valid_to_non_transport,
                    'vehicle_classes' => $vehicleClasses,
                    'address_list' => $addressList,
                    'photo_url' => $existingCustomer->license_photo_url,
                    'reference_id' => $existingCustomer->license_reference_id,
                    'from_cache' => true,
                    'raw_response' => $licenseData,
                ];

                $customerData = [
                    'name' => $this->sanitizeInput($existingCustomer->name),
                    'father_name' => $this->sanitizeInput($existingCustomer->father_name),
                    'address' => $this->sanitizeInput($existingCustomer->address),
                    'date_of_birth' => $existingCustomer->date_of_birth,
                    'license_issue_date' => $existingCustomer->license_issue_date,
                    'license_valid_from' => $existingCustomer->license_valid_from_non_transport,
                    'license_valid_to' => $existingCustomer->license_valid_to_non_transport,
                    'photo_path' => $existingCustomer->license_photo,
                    'vehicle_classes' => $vehicleClasses,
                    'address_list' => $addressList,
                ];

                if ($existingCustomer->phone !== $validated['customer_phone']) {
                    $existingCustomer->update(['phone' => $validated['customer_phone']]);
                }
                $referenceId = $existingCustomer->license_reference_id;
            } else {
                // Fresh verification
                $dlVerification = $this->cashfreeService->verifyDrivingLicense(
                    $validated['dl_number'],
                    $validated['dob']
                );

                if (! $dlVerification['success'] || $dlVerification['status'] !== 'VALID') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Driving license verification failed',
                        'errors' => ['dl_number' => [$dlVerification['error'] ?? 'Invalid DL or DOB']],
                    ], 422);
                }

                $rawData = $dlVerification['raw_response'] ?? [];
                $details = $rawData['details_of_driving_licence'] ?? [];
                $validity = $rawData['dl_validity'] ?? [];

                $customerName = $this->sanitizeInput($dlVerification['name'] ?? '');
                $customerAddress = $this->sanitizeInput($dlVerification['address'] ?? $details['address'] ?? null);
                $fatherName = $this->sanitizeInput($dlVerification['father_name'] ?? $details['father_or_husband_name'] ?? null);
                $photoUrl = $details['photo'] ?? null;
                $dateOfIssue = $dlVerification['date_of_issue'] ?? $details['date_of_issue'] ?? null;
                $validFrom = $dlVerification['valid_from'] ?? $validity['non_transport']['from'] ?? null;
                $validTo = $dlVerification['valid_to'] ?? $validity['non_transport']['to'] ?? null;
                $addressList = $details['address_list'] ?? [];
                $vehicleClasses = $dlVerification['vehicle_classes'] ?? [];
                $referenceId = $dlVerification['reference_id'] ?? $this->generateReferenceId();

                $localPhotoPath = null;
                if ($photoUrl) {
                    $localPhotoPath = $this->downloadCustomerPhotoSecurely($validated['customer_phone'], $photoUrl);
                }

                $customerData = [
                    'name' => $customerName,
                    'father_name' => $fatherName,
                    'address' => $customerAddress,
                    'date_of_birth' => $validated['dob'],
                    'license_issue_date' => $dateOfIssue,
                    'license_valid_from' => $validFrom,
                    'license_valid_to' => $validTo,
                    'photo_path' => $localPhotoPath,
                    'vehicle_classes' => $vehicleClasses,
                    'address_list' => $addressList,
                ];
            }

            // Start transaction
            return DB::transaction(function () use (
                $validated, $shopOwner, $user, $verificationPrice, $vehicle,
                $dlVerification, $isFromCache, $existingCustomer, $customerData,
                $referenceId, $dlNumber, $licenseHash
            ) {
                $customer = Customer::updateOrCreate(
                    ['license_number_hash' => $licenseHash],
                    [
                        'license_number' => $dlNumber,
                        'license_number_hash' => $licenseHash,
                        'name' => $customerData['name'],
                        'father_name' => $customerData['father_name'] ?? null,
                        'phone' => $validated['customer_phone'],
                        'address' => $customerData['address'] ?? '',
                        'date_of_birth' => $validated['dob'],
                        'license_data' => json_encode($dlVerification['raw_response'] ?? []),
                        'license_photo' => $customerData['photo_path'] ?? ($existingCustomer->license_photo ?? null),
                        'license_issue_date' => $customerData['license_issue_date'] ?? null,
                        'license_valid_from_non_transport' => $customerData['license_valid_from'] ?? null,
                        'license_valid_to_non_transport' => $customerData['license_valid_to'] ?? null,
                        'license_address' => $customerData['address'] ?? null,
                        'license_address_list' => json_encode($customerData['address_list'] ?? []),
                        'vehicle_classes_data' => json_encode($customerData['vehicle_classes'] ?? []),
                        'license_reference_id' => $referenceId,
                    ]
                );

                $updated = User::where('id', $user->id)
                    ->where('wallet_balance', '>=', $verificationPrice)
                    ->update(['wallet_balance' => DB::raw('wallet_balance - '.$verificationPrice)]);
                if (! $updated) {
                    throw new Exception('Failed to deduct verification fee');
                }
                $user->refresh();
                Cache::forget('wallet_balance_'.$user->id);

                $transaction = WalletTransaction::create([
                    'user_id' => $user->id,
                    'amount' => $verificationPrice,
                    'type' => 'debit',
                    'reason' => 'DL verification '.($isFromCache ? '(CACHED)' : '(FRESH)').' - DL: '.$dlNumber,
                    'status' => 'completed',
                    'reference_id' => $this->generateReferenceId(),
                    'metadata' => json_encode([
                        'is_cached' => $isFromCache,
                        'dl_number' => $dlNumber,
                        'customer_id' => $customer->id,
                    ]),
                ]);

                if (! $isFromCache) {
                    $this->logFreshVerification($customer->id);
                } else {
                    $this->logCachedVerification($customer->id);
                }

                $rental = Rental::create([
                    'user_id' => $shopOwner->id,
                    'vehicle_id' => $vehicle->id,
                    'customer_id' => $customer->id,
                    'phase' => 'verification',
                    'status' => 'pending',
                    'start_time' => null,
                    'total_price' => 0,
                    'verification_fee_deducted' => $verificationPrice,
                    'verification_transaction_id' => $transaction->id,
                    'is_verification_cached' => $isFromCache,
                    'verification_reference_id' => $referenceId,
                    'verification_completed_at' => now(),
                ]);

                $verificationToken = $this->storeVerificationData([
                    'rental_id' => $rental->id,
                    'customer_id' => $customer->id,
                    'vehicle_id' => $vehicle->id,
                    'dl_number' => $dlNumber,
                    'is_from_cache' => $isFromCache,
                    'verification_price' => $verificationPrice,
                    'transaction_id' => $transaction->id,
                    'verification_data' => $dlVerification,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => $isFromCache ? 'Customer verified from cache. Fee deducted.' : 'License verified. Fee deducted.',
                    'data' => [
                        'verification_token' => $verificationToken,
                        'rental_id' => $rental->id,
                        'verification_source' => $isFromCache ? 'cached' : 'fresh',
                        'verification_fee' => $verificationPrice,
                        'wallet_balance' => (float) $user->wallet_balance,
                        'transaction_id' => $transaction->id,
                        'customer' => [
                            'id' => $customer->id,
                            'name' => $customer->name,
                            'phone' => $customer->phone,
                            'license_number' => $customer->license_number,
                            'license_issue_date' => $customer->license_issue_date,
                            'dob' => $customer->date_of_birth,
                            'address' => $customer->address,
                            'customer_photo_url' => $customer->license_photo_url,
                            'license_validity' => [
                                'valid_from' => $customer->license_valid_from_non_transport,
                                'valid_to' => $customer->license_valid_to_non_transport,
                            ],
                        ],
                        'vehicle' => [
                            'id' => $vehicle->id,
                            'name' => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                        ],
                    ],
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Phase 1 error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Verification failed', 'error' => config('app.debug') ? $e->getMessage() : 'Server error'], 500);
        }
    }

    /**
     * PHASE 2: DOCUMENT UPLOAD & AGREEMENT GENERATION
     */
    public function phase2UploadDocuments(Request $request)
    {
        try {
            $validated = $request->validate([
                'verification_token' => 'required|string|size:64',
                'license_image' => 'nullable|file|image|mimes:jpeg,png,jpg|max:5120',
                'aadhaar_image' => 'nullable|file|image|mimes:jpeg,png,jpg|max:5120',
            ]);

            $shopOwner = auth()->user();
            if (! $shopOwner) {
                return response()->json(['success' => false, 'message' => 'Shop owner not authenticated'], 401);
            }

            if ($request->hasFile('license_image') && ! $this->validateFileContent($request->file('license_image'), $this->allowedImageMimes)) {
                return response()->json(['success' => false, 'message' => 'Invalid license image file type'], 422);
            }
            if ($request->hasFile('aadhaar_image') && ! $this->validateFileContent($request->file('aadhaar_image'), $this->allowedImageMimes)) {
                return response()->json(['success' => false, 'message' => 'Invalid aadhaar image file type'], 422);
            }

            $verificationData = $this->getVerificationData($validated['verification_token']);
            if (! $verificationData) {
                Log::warning('Invalid or expired verification token', ['user_id' => $shopOwner->id]);

                return response()->json(['success' => false, 'message' => 'Invalid or expired verification token'], 400);
            }

            $rental = Rental::where('id', $verificationData['rental_id'])
                ->where('user_id', $shopOwner->id)
                ->where('phase', 'verification')
                ->first();
            if (! $rental) {
                return response()->json(['success' => false, 'message' => 'Rental not found or not in verification phase'], 404);
            }

            return DB::transaction(function () use ($request, $verificationData, $rental, $shopOwner) {
                $vehicle = Vehicle::where('id', $verificationData['vehicle_id'])
                    ->where('user_id', $shopOwner->id)
                    ->where('status', 'available')
                    ->lockForUpdate()
                    ->first();
                if (! $vehicle) {
                    return response()->json(['success' => false, 'message' => 'Vehicle is no longer available'], 409);
                }

                $customer = Customer::find($verificationData['customer_id']);
                if (! $customer) {
                    return response()->json(['success' => false, 'message' => 'Customer not found'], 404);
                }

                $newLicensePath = null;
                $newAadhaarPath = null;

                if ($request->hasFile('license_image')) {
                    $dir = 'documents/'.date('Y/m/d');
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                    $secureFilename = 'license_'.$rental->id.'_'.time().'_'.bin2hex(random_bytes(8)).'.jpg';
                    $newLicensePath = $request->file('license_image')->storeAs($dir, $secureFilename, 'public');
                }

                if ($request->hasFile('aadhaar_image')) {
                    $dir = 'documents/'.date('Y/m/d');
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                    $secureFilename = 'aadhaar_'.$rental->id.'_'.time().'_'.bin2hex(random_bytes(8)).'.jpg';
                    $newAadhaarPath = $request->file('aadhaar_image')->storeAs($dir, $secureFilename, 'public');
                }

                $document = Document::create([
                    'rental_id' => $rental->id,
                    'license_image' => $newLicensePath,
                    'aadhaar_image' => $newAadhaarPath,
                    'is_verified' => true,
                    'verification_status' => 'verified',
                    'license_ocr_data' => json_encode($verificationData['verification_data']['raw_response'] ?? []),
                    'extracted_name' => $customer->name,
                    'extracted_license' => $verificationData['dl_number'],
                    'verified_at' => now(),
                ]);

                $rental->update([
                    'document_id' => $document->id,
                    'phase' => 'document_upload',
                    'document_upload_completed_at' => now(),
                ]);

                Storage::disk('public')->makeDirectory('agreements', 0755, true);
                $agreementPath = $this->generateAgreement($rental, $customer, $vehicle, $document, $rental->user, $verificationData);
                if ($agreementPath) {
                    $rental->update(['agreement_path' => $agreementPath]);
                }

                Log::info('Phase 2 completed', ['rental_id' => $rental->id, 'document_id' => $document->id]);

                return response()->json([
                    'success' => true,
                    'message' => 'Documents uploaded successfully. Please proceed to agreement signing.',
                    'data' => [
                        'rental_id' => $rental->id,
                        'agreement_path' => $agreementPath ? asset('storage/'.$agreementPath) : null,
                        'document' => [
                            'id' => $document->id,
                            'license_image' => $document->license_image ? asset('storage/'.$document->license_image) : null,
                            'aadhaar_image' => $document->aadhaar_image ? asset('storage/'.$document->aadhaar_image) : null,
                        ],
                        'next_phase' => 'agreement_signing',
                    ],
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Phase 2 error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Failed to upload documents', 'error' => config('app.debug') ? $e->getMessage() : 'Server error'], 500);
        }
    }

    /**
     * PHASE 3: AGREEMENT SIGNING & VEHICLE HANDOVER
     */
    public function phase3SignAndHandover(Request $request, $rentalId)
    {
        try {
            $shopOwner = auth()->user();
            if (! $shopOwner) {
                return response()->json(['success' => false, 'message' => 'Shop owner not authenticated'], 401);
            }

            $rental = Rental::where('id', $rentalId)->where('user_id', $shopOwner->id)->first();
            if (! $rental) {
                return response()->json(['success' => false, 'message' => 'Rental not found or access denied'], 404);
            }
            if ($rental->phase !== 'document_upload') {
                return response()->json(['success' => false, 'message' => 'Rental not in document upload phase. Current phase: '.$rental->phase], 422);
            }

            // Only validate the required signed agreement image
            $validated = $request->validate([
                'signed_agreement_image' => 'required|file|image|mimes:jpeg,png,jpg|max:10240',
            ]);

            // Optional files - we'll handle them separately without strict validation
            $customerWithVehicleImage = $request->file('customer_with_vehicle_image');
            $vehicleConditionVideo = $request->file('vehicle_condition_video');

            return DB::transaction(function () use ($request, $rental, $shopOwner, $customerWithVehicleImage, $vehicleConditionVideo) {
                $vehicle = Vehicle::where('id', $rental->vehicle_id)->where('user_id', $shopOwner->id)->lockForUpdate()->first();
                if (! $vehicle) {
                    return response()->json(['success' => false, 'message' => 'Vehicle not found'], 404);
                }

                // Upload signed agreement image (required)
                $signedAgreementPath = null;
                if ($request->hasFile('signed_agreement_image')) {
                    $dir = 'agreements/signed/'.date('Y/m/d');
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                    $secureFilename = 'signed_'.$rental->id.'_'.time().'_'.bin2hex(random_bytes(8)).'.jpg';
                    $signedAgreementPath = $request->file('signed_agreement_image')->storeAs($dir, $secureFilename, 'public');
                }

                // Upload customer with vehicle photo (optional)
                $customerWithVehiclePath = null;
                if ($customerWithVehicleImage && $customerWithVehicleImage->isValid()) {
                    // Optional: basic validation for images
                    if (in_array($customerWithVehicleImage->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg'])) {
                        $dir = 'handover_photos/'.date('Y/m/d');
                        Storage::disk('public')->makeDirectory($dir, 0755, true);
                        $secureFilename = 'handover_'.$rental->id.'_'.time().'_'.bin2hex(random_bytes(8)).'.jpg';
                        $customerWithVehiclePath = $customerWithVehicleImage->storeAs($dir, $secureFilename, 'public');
                    } else {
                        Log::warning('Invalid customer photo mime type, skipping upload', ['rental_id' => $rental->id]);
                    }
                }

                // Upload vehicle condition video (optional)
                $vehicleConditionVideoPath = null;
                if ($vehicleConditionVideo && $vehicleConditionVideo->isValid()) {
                    // Optional: basic validation for videos
                    if (in_array($vehicleConditionVideo->getMimeType(), ['video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/mpeg'])) {
                        $dir = 'vehicle_condition_videos/'.date('Y/m/d');
                        Storage::disk('public')->makeDirectory($dir, 0755, true);
                        $secureFilename = 'condition_'.$rental->id.'_'.time().'_'.bin2hex(random_bytes(8));
                        $extension = $vehicleConditionVideo->getClientOriginalExtension();
                        $vehicleConditionVideoPath = $vehicleConditionVideo->storeAs($dir, $secureFilename.'.'.$extension, 'public');
                    } else {
                        Log::warning('Invalid video mime type, skipping upload', ['rental_id' => $rental->id]);
                    }
                }

                $rental->update([
                    'phase' => 'active',
                    'status' => 'active',
                    'start_time' => now(),
                    'signed_agreement_path' => $signedAgreementPath,
                    'customer_with_vehicle_image' => $customerWithVehiclePath,
                    'vehicle_condition_video' => $vehicleConditionVideoPath,
                    'agreement_signed_at' => now(),
                ]);

                $vehicle->update(['status' => 'on_rent']);
                $this->logRentalStart($rental->customer_id, $rental->id);

                return response()->json([
                    'success' => true,
                    'message' => 'Agreement signed and vehicle handed over. Rental started successfully.',
                    'data' => [
                        'rental_id' => $rental->id,
                        'start_time' => $rental->start_time,
                        'status' => 'active',
                        'signed_agreement_url' => $signedAgreementPath ? asset('storage/'.$signedAgreementPath) : null,
                        'customer_photo_url' => $customerWithVehiclePath ? asset('storage/'.$customerWithVehiclePath) : null,
                        'condition_video_url' => $vehicleConditionVideoPath ? asset('storage/'.$vehicleConditionVideoPath) : null,
                        'vehicle' => [
                            'id' => $vehicle->id,
                            'name' => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                        ],
                    ],
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Phase 3 error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Failed to complete agreement signing', 'error' => config('app.debug') ? $e->getMessage() : 'Server error'], 500);
        }
    }

    /**
     * RETURN VEHICLE
     */
    public function returnVehicle(Request $request, $rentalId)
    {
        try {
            $shopOwner = auth()->user();
            if (! $shopOwner) {
                return response()->json(['success' => false, 'message' => 'Shop owner not authenticated'], 401);
            }

            $rental = Rental::where('id', $rentalId)
                ->where('user_id', $shopOwner->id)
                ->where('phase', 'active')
                ->with(['vehicle', 'customer', 'user'])
                ->first();
            if (! $rental) {
                return response()->json(['success' => false, 'message' => 'Active rental not found or access denied'], 404);
            }

            // Manually parse vehicle_in_good_condition (accept string 'true'/'false' or boolean)
            $vehicleInGoodCondition = $request->input('vehicle_in_good_condition');
            if (is_string($vehicleInGoodCondition)) {
                $vehicleInGoodCondition = filter_var($vehicleInGoodCondition, FILTER_VALIDATE_BOOLEAN);
            } else {
                $vehicleInGoodCondition = (bool) $vehicleInGoodCondition;
            }

            // Build validation rules dynamically
            $rules = [
                'vehicle_in_good_condition' => 'required|boolean',
                'damage_amount' => 'required_if:vehicle_in_good_condition,false|numeric|min:0|max:'.$this->maxDamageAmount,
                'damage_description' => 'required_if:vehicle_in_good_condition,false|string|max:500',
            ];

            // Damage images can be either array OR a single file OR null
            if ($request->hasFile('damage_images')) {
                $damageImages = $request->file('damage_images');
                if (is_array($damageImages)) {
                    $rules['damage_images.*'] = 'file|image|mimes:jpeg,png,jpg|max:5120';
                } else {
                    // Single file uploaded - treat as single item array
                    $rules['damage_images'] = 'file|image|mimes:jpeg,png,jpg|max:5120';
                }
            }

            $validated = $request->validate($rules);

            // Override the validated input with our parsed boolean
            $validated['vehicle_in_good_condition'] = $vehicleInGoodCondition;

            // Sanitize damage description
            if (isset($validated['damage_description'])) {
                $validated['damage_description'] = $this->sanitizeInput($validated['damage_description']);
            }

            // Handle damage images - convert single file to array
            $damageImageFiles = [];
            if ($request->hasFile('damage_images')) {
                $files = $request->file('damage_images');
                if (is_array($files)) {
                    $damageImageFiles = $files;
                } else {
                    $damageImageFiles = [$files];
                }
            }

            // Validate each damage image content
            foreach ($damageImageFiles as $index => $image) {
                if (! $this->validateFileContent($image, $this->allowedImageMimes)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Invalid damage image file type for image '.($index + 1),
                    ], 422);
                }
            }

            return DB::transaction(function () use ($validated, $rental, $damageImageFiles) {
                // Upload damage images
                $damageImages = [];
                if (! empty($damageImageFiles)) {
                    $dir = 'damage_images/'.date('Y/m/d');
                    Storage::disk('public')->makeDirectory($dir, 0755, true);
                    foreach ($damageImageFiles as $image) {
                        $secureFilename = 'damage_'.$rental->id.'_'.time().'_'.bin2hex(random_bytes(8)).'.jpg';
                        $path = $image->storeAs($dir, $secureFilename, 'public');
                        $damageImages[] = $path;
                    }
                }

                $rental->end_time = now();
                $totalMinutes = $rental->start_time->diffInMinutes($rental->end_time);
                if ($totalMinutes > $this->maxRentalHours * 60) {
                    Log::warning('Excessive rental duration detected', ['rental_id' => $rental->id, 'total_minutes' => $totalMinutes]);
                }

                $leaseThreshold = (int) Setting::where('key', 'lease_threshold_minutes')->value('value');
                $leaseThreshold = ($leaseThreshold > 0) ? min($leaseThreshold, 120) : 60;

                if ($totalMinutes <= $leaseThreshold) {
                    $hoursToCharge = 1;
                } else {
                    $hoursToCharge = ceil($totalMinutes / 60);
                }
                $hoursToCharge = min($hoursToCharge, $this->maxRentalHours);

                $vehicle = $rental->vehicle;
                $rentalCharges = $this->calculatePrice($vehicle, $hoursToCharge);
                $damageAmount = $validated['vehicle_in_good_condition'] ? 0 : min(($validated['damage_amount'] ?? 0), $this->maxDamageAmount);
                $totalPrice = $rentalCharges + $damageAmount;

                $rental->update([
                    'phase' => 'completed',
                    'status' => 'completed',
                    'total_price' => $totalPrice,
                    'vehicle_in_good_condition' => $validated['vehicle_in_good_condition'],
                    'damage_amount' => $damageAmount,
                    'damage_description' => $validated['vehicle_in_good_condition'] ? null : ($validated['damage_description'] ?? null),
                    'damage_images' => ! empty($damageImages) ? json_encode($damageImages) : null,
                    'return_completed_at' => now(),
                ]);

                $vehicle->update(['status' => 'available']);
                $verificationPrice = Setting::where('key', 'verification_price')->value('value') ?? 0;
                $receiptPath = $this->generateReceipt($rental, $rentalCharges, $damageAmount, $totalMinutes, $hoursToCharge, $leaseThreshold, $verificationPrice);
                if ($receiptPath) {
                    $rental->update(['receipt_path' => $receiptPath]);
                }

                $this->logRentalEnd($rental->customer_id, $rental->id);

                return response()->json([
                    'success' => true,
                    'message' => $damageAmount > 0 ? 'Vehicle returned with damage. Receipt generated.' : 'Vehicle returned in good condition.',
                    'data' => [
                        'rental_id' => $rental->id,
                        'return_summary' => [
                            'return_time' => $rental->end_time,
                            'duration_minutes' => $totalMinutes,
                            'duration_text' => $this->formatDuration($totalMinutes),
                            'hours_charged' => $hoursToCharge,
                            'lease_threshold_minutes' => $leaseThreshold,
                        ],
                        'charges' => [
                            'rental_charges' => ['amount' => $rentalCharges, 'formatted' => '₹'.number_format($rentalCharges, 2)],
                            'damage_charges' => ['amount' => $damageAmount, 'formatted' => '₹'.number_format($damageAmount, 2), 'description' => $rental->damage_description],
                            'verification_fee' => ['amount' => $verificationPrice, 'formatted' => '₹'.number_format($verificationPrice, 2)],
                            'total' => ['amount' => $totalPrice + $verificationPrice, 'formatted' => '₹'.number_format($totalPrice + $verificationPrice, 2)],
                        ],
                        'damage_info' => $damageAmount > 0 ? [
                            'description' => $rental->damage_description,
                            'images' => array_map(fn ($p) => asset('storage/'.$p), $damageImages),
                        ] : null,
                        'receipt_path' => $receiptPath ? asset('storage/'.$receiptPath) : null,
                        'vehicle' => [
                            'id' => $vehicle->id,
                            'name' => $vehicle->name,
                            'number_plate' => $vehicle->number_plate,
                            'status' => 'available',
                        ],
                        'customer' => [
                            'id' => $rental->customer->id,
                            'name' => $rental->customer->name,
                            'phone' => $rental->customer->phone,
                        ],
                    ],
                ]);
            });
        } catch (ValidationException $e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'errors' => $e->errors()], 422);
        } catch (Exception $e) {
            Log::error('Return vehicle error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json(['success' => false, 'message' => 'Failed to process return', 'error' => config('app.debug') ? $e->getMessage() : 'Server error'], 500);
        }
    }

    /**
     * Get current rental phase status
     */
    public function getPhaseStatus($rentalId)
    {
        try {
            $shopOwner = auth()->user();
            if (! $shopOwner) {
                return response()->json(['success' => false, 'message' => 'Shop owner not authenticated'], 401);
            }

            $rental = Rental::where('id', $rentalId)
                ->where('user_id', $shopOwner->id)
                ->with(['vehicle', 'customer', 'document'])
                ->first();

            if (! $rental) {
                return response()->json(['success' => false, 'message' => 'Rental not found or access denied'], 404);
            }

            $phaseInfo = $this->getPhaseInfo($rental);

            return response()->json(['success' => true, 'data' => $phaseInfo], 200);
        } catch (Exception $e) {
            Log::error('Get phase status error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to get rental status'], 500);
        }
    }

    /**
     * Cancel rental
     */
    public function cancel($rentalId)
    {
        try {
            $shopOwner = auth()->user();
            if (! $shopOwner) {
                return response()->json(['success' => false, 'message' => 'Shop owner not authenticated'], 401);
            }

            $rental = Rental::where('id', $rentalId)
                ->where('user_id', $shopOwner->id)
                ->whereNotIn('phase', ['completed', 'cancelled'])
                ->first();

            if (! $rental) {
                return response()->json(['success' => false, 'message' => 'Rental not found or already completed/cancelled'], 404);
            }

            $rental->update(['phase' => 'cancelled', 'status' => 'cancelled']);
            if ($rental->vehicle->status === 'on_rent') {
                $rental->vehicle->update(['status' => 'available']);
            }

            return response()->json(['success' => true, 'message' => 'Rental cancelled successfully. Verification fee is non-refundable.'], 200);
        } catch (Exception $e) {
            Log::error('Cancel rental error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to cancel rental'], 500);
        }
    }

    /**
     * Get active rentals
     */
    public function active()
    {
        try {
            $user = auth()->user();
            $rentals = Rental::where('user_id', $user->id)
                ->where('status', 'active')
                ->with(['vehicle', 'customer'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json(['success' => true, 'data' => $rentals, 'total' => $rentals->count()], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch active rentals'], 500);
        }
    }

    /**
     * Get rental history
     */
    public function history(Request $request)
    {
        try {
            $user = auth()->user();
            $perPage = $request->get('per_page', 15);
            $rentals = Rental::where('user_id', $user->id)
                ->whereIn('status', ['completed', 'cancelled'])
                ->with(['vehicle', 'customer'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $rentals->items(),
                'pagination' => [
                    'current_page' => $rentals->currentPage(),
                    'last_page' => $rentals->lastPage(),
                    'per_page' => $rentals->perPage(),
                    'total' => $rentals->total(),
                ],
            ], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch rental history'], 500);
        }
    }

    /**
     * Get specific rental
     */
    public function show($id)
    {
        try {
            $user = auth()->user();
            $rental = Rental::where('id', $id)
                ->where('user_id', $user->id)
                ->with(['vehicle', 'customer', 'document'])
                ->first();
            if (! $rental) {
                return response()->json(['success' => false, 'message' => 'Rental not found'], 404);
            }

            return response()->json(['success' => true, 'data' => $rental], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch rental'], 500);
        }
    }

    /**
     * Get rental statistics
     */
    public function statistics()
    {
        try {
            $user = auth()->user();
            $stats = [
                'total_rentals' => Rental::where('user_id', $user->id)->count(),
                'active_rentals' => Rental::where('user_id', $user->id)->where('status', 'active')->count(),
                'completed_rentals' => Rental::where('user_id', $user->id)->where('status', 'completed')->count(),
                'cancelled_rentals' => Rental::where('user_id', $user->id)->where('status', 'cancelled')->count(),
                'total_earnings' => (float) Rental::where('user_id', $user->id)->where('status', 'completed')->sum('total_price'),
                'total_verification_fees' => (float) Rental::where('user_id', $user->id)->sum('verification_fee_deducted'),
                'average_rental_duration' => $this->getUserAverageRentalDuration($user->id),
                'most_rented_vehicle' => $this->getUserMostRentedVehicle($user->id),
            ];

            return response()->json(['success' => true, 'data' => $stats], 200);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch statistics'], 500);
        }
    }

    /**
     * Download agreement PDF
     */
    public function downloadAgreement($id)
    {
        try {
            $user = auth()->user();
            $rental = Rental::where('id', $id)->where('user_id', $user->id)->first();
            if (! $rental || ! $rental->agreement_path || ! Storage::disk('public')->exists($rental->agreement_path)) {
                return response()->json(['success' => false, 'message' => 'Agreement not found'], 404);
            }

            return Storage::disk('public')->download($rental->agreement_path);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to download agreement'], 500);
        }
    }

    /**
     * Download receipt PDF
     */
    public function downloadReceipt($id)
    {
        try {
            $user = auth()->user();
            $rental = Rental::where('id', $id)->where('user_id', $user->id)->first();
            if (! $rental || ! $rental->receipt_path || ! Storage::disk('public')->exists($rental->receipt_path)) {
                return response()->json(['success' => false, 'message' => 'Receipt not found'], 404);
            }

            return Storage::disk('public')->download($rental->receipt_path);
        } catch (Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to download receipt'], 500);
        }
    }

    // ============================================
    // TOKEN STORAGE (DATABASE)
    // ============================================

    protected function storeVerificationData(array $data): string
    {
        $token = bin2hex(random_bytes(32));
        DB::table('verification_tokens')->insert([
            'user_id' => auth()->id(),
            'token' => $token,
            'data' => json_encode($data),
            'expires_at' => now()->addMinutes(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $token;
    }

    protected function getVerificationData(string $token): ?array
    {
        $record = DB::table('verification_tokens')
            ->where('token', $token)
            ->where('user_id', auth()->id())
            ->where('expires_at', '>', now())
            ->first();
        if ($record) {
            DB::table('verification_tokens')->where('id', $record->id)->delete();

            return json_decode($record->data, true);
        }

        return null;
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    protected function validateFileContent(UploadedFile $file, array $allowedMimes): bool
    {
        try {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file->getPathname());
            finfo_close($finfo);

            return in_array($mimeType, $allowedMimes);
        } catch (Exception $e) {
            Log::error('File content validation failed', ['error' => $e->getMessage()]);

            return false;
        }
    }

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

    protected function downloadCustomerPhotoSecurely(string $phone, string $photoUrl): ?string
    {
        try {
            if (! str_starts_with($photoUrl, 'https://') && ! str_starts_with($photoUrl, 'http://')) {
                return null;
            }
            if (! app()->environment('local') && ! str_starts_with($photoUrl, 'https://')) {
                return null;
            }
            $context = stream_context_create([
                'http' => ['timeout' => 10, 'user_agent' => 'RentalSystem/1.0'],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $photoContents = @file_get_contents($photoUrl, false, $context);
            if ($photoContents !== false && strlen($photoContents) > 0 && strlen($photoContents) < 5 * 1024 * 1024) {
                $photoDir = 'customers/photos/'.date('Y/m/d');
                Storage::disk('public')->makeDirectory($photoDir, 0755, true);
                $photoFileName = 'customer_'.$phone.'_'.date('YmdHis').'_'.bin2hex(random_bytes(8)).'.jpg';
                $localPhotoPath = $photoDir.'/'.$photoFileName;
                Storage::disk('public')->put($localPhotoPath, $photoContents);

                return $localPhotoPath;
            }
        } catch (Exception $e) {
            Log::error('Failed to download customer photo', ['error' => $e->getMessage()]);
        }

        return null;
    }

    protected function generateReferenceId(): string
    {
        return 'VER_'.strtoupper(uniqid()).'_'.date('YmdHis').'_'.bin2hex(random_bytes(4));
    }

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

    protected function generateAgreement($rental, $customer, $vehicle, $document, $shopOwner, $verificationData)
    {
        try {
            $agreementNumber = 'AGR-'.strtoupper(uniqid()).'-'.date('Ymd');
            $verificationPrice = Setting::where('key', 'verification_price')->value('value') ?? 0;
            Storage::disk('public')->makeDirectory('agreements', 0755, true);

            $customerPhotoPath = null;
            if ($customer->license_photo) {
                $fullPath = Storage::disk('public')->path($customer->license_photo);
                if (file_exists($fullPath) && is_file($fullPath)) {
                    $photoData = @file_get_contents($fullPath);
                    if ($photoData !== false) {
                        $customerPhotoPath = 'data:image/jpeg;base64,'.base64_encode($photoData);
                    }
                }
            }

            $agreementData = [
                'agreement_number' => $agreementNumber,
                'rental_id' => $rental->id,
                'date' => now()->format('d/m/Y'),
                'time' => now()->format('h:i A'),
                'shop_owner' => [
                    'name' => $this->sanitizeInput($shopOwner->name ?? 'Vehicle Rental Shop'),
                    'phone' => $shopOwner->phone ?? 'N/A',
                    'address' => $this->sanitizeInput($shopOwner->address ?? 'N/A'),
                ],
                'customer' => [
                    'id' => $customer->id,
                    'name' => $this->sanitizeInput($customer->name),
                    'father_name' => $this->sanitizeInput($customer->father_name),
                    'phone' => $customer->phone,
                    'address' => $this->sanitizeInput($customer->address),
                    'license_number' => $customer->license_number,
                    'date_of_birth' => $customer->date_of_birth ? date('d/m/Y', strtotime($customer->date_of_birth)) : 'N/A',
                    'license_issue_date' => $customer->license_issue_date ? date('d/m/Y', strtotime($customer->license_issue_date)) : 'N/A',
                    'license_valid_from' => $customer->license_valid_from_non_transport ? date('d/m/Y', strtotime($customer->license_valid_from_non_transport)) : 'N/A',
                    'license_valid_to' => $customer->license_valid_to_non_transport ? date('d/m/Y', strtotime($customer->license_valid_to_non_transport)) : 'N/A',
                    'photo' => $customerPhotoPath,
                ],
                'vehicle' => [
                    'id' => $vehicle->id,
                    'name' => $this->sanitizeInput($vehicle->name),
                    'number_plate' => $vehicle->number_plate,
                    'type' => $this->sanitizeInput($vehicle->type ?? 'Standard'),
                    'hourly_rate' => number_format($vehicle->hourly_rate, 2),
                    'daily_rate' => number_format($vehicle->daily_rate, 2),
                    'model' => $this->sanitizeInput($vehicle->model ?? 'N/A'),
                    'year' => $vehicle->year ?? 'N/A',
                ],
                'terms' => [
                    'hourly_rate' => number_format($vehicle->hourly_rate, 2),
                    'daily_rate' => number_format($vehicle->daily_rate, 2),
                    'lease_threshold' => Setting::where('key', 'lease_threshold_minutes')->value('value') ?? 60,
                    'verification_fee' => number_format($verificationPrice, 2),
                ],
                'verification_source' => $verificationData['is_from_cache'] ? 'Cached' : 'Fresh',
                'generated_at' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = PDF::loadView('pdf.rental-agreement', $agreementData);
            $pdf->setPaper('A4', 'portrait');
            $filename = "agreements/agreement_{$rental->id}_{$agreementNumber}.pdf";
            Storage::disk('public')->put($filename, $pdf->output());

            return $filename;
        } catch (Exception $e) {
            Log::error('Agreement generation failed', ['rental_id' => $rental->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function generateReceipt($rental, $rentalCharges, $damageCharges, $totalMinutes, $hoursToCharge, $leaseThreshold, $verificationPrice)
    {
        try {
            $receiptNumber = 'RCT-'.strtoupper(uniqid()).'-'.date('Ymd');
            Storage::disk('public')->makeDirectory('receipts', 0755, true);
            $totalPrice = $rentalCharges + $damageCharges;
            $totalHours = $totalMinutes / 60;

            $receiptData = [
                'receipt_number' => $receiptNumber,
                'rental_id' => $rental->id,
                'date' => now()->format('d/m/Y'),
                'time' => now()->format('h:i A'),
                'shop_owner' => [
                    'name' => $this->sanitizeInput($rental->user->name ?? 'Vehicle Rental Shop'),
                    'phone' => $rental->user->phone ?? 'N/A',
                ],
                'customer' => [
                    'name' => $this->sanitizeInput($rental->customer->name),
                    'phone' => $rental->customer->phone,
                    'address' => $this->sanitizeInput($rental->customer->address),
                    'license_number' => $rental->customer->license_number,
                ],
                'vehicle' => [
                    'name' => $this->sanitizeInput($rental->vehicle->name),
                    'number_plate' => $rental->vehicle->number_plate,
                    'hourly_rate' => number_format($rental->vehicle->hourly_rate, 2),
                    'daily_rate' => number_format($rental->vehicle->daily_rate, 2),
                ],
                'rental_period' => [
                    'start_time' => $rental->start_time->format('d/m/Y h:i A'),
                    'end_time' => $rental->end_time->format('d/m/Y h:i A'),
                    'duration' => $this->formatDuration($totalMinutes),
                    'total_minutes' => $totalMinutes,
                    'total_hours' => round($totalHours, 2),
                    'hours_charged' => $hoursToCharge,
                    'lease_threshold' => $leaseThreshold,
                ],
                'charges' => [
                    'verification_fee' => number_format($verificationPrice, 2),
                    'rental_amount' => number_format($rentalCharges, 2),
                    'damage_amount' => number_format($damageCharges, 2),
                    'total' => number_format($totalPrice + $verificationPrice, 2),
                ],
                'damage_info' => $damageCharges > 0 ? [
                    'description' => $this->sanitizeInput($rental->damage_description),
                    'images' => $rental->damage_images ? json_decode($rental->damage_images, true) : [],
                ] : null,
                'payment_status' => 'Paid',
                'payment_method' => 'Wallet',
                'generated_at' => now()->format('d/m/Y H:i:s'),
            ];

            $pdf = PDF::loadView('pdf.rental-receipt', $receiptData);
            $pdf->setPaper('A4', 'portrait');
            $filename = "receipts/receipt_{$rental->id}_{$receiptNumber}.pdf";
            Storage::disk('public')->put($filename, $pdf->output());

            return $filename;
        } catch (Exception $e) {
            Log::error('Receipt generation failed', ['rental_id' => $rental->id, 'error' => $e->getMessage()]);

            return null;
        }
    }

    protected function formatDuration(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;
        if ($hours > 0 && $remainingMinutes > 0) {
            return "{$hours} hour(s) {$remainingMinutes} minute(s)";
        } elseif ($hours > 0) {
            return "{$hours} hour(s)";
        } else {
            return "{$remainingMinutes} minute(s)";
        }
    }

    protected function getPhaseInfo(Rental $rental): array
    {
        $phaseStatus = [
            'current_phase' => $rental->phase,
            'phases' => [
                'verification' => [
                    'completed' => ! is_null($rental->verification_completed_at),
                    'completed_at' => $rental->verification_completed_at,
                    'fee_deducted' => $rental->verification_fee_deducted,
                    'is_cached' => $rental->is_verification_cached,
                ],
                'document_upload' => [
                    'completed' => ! is_null($rental->document_upload_completed_at),
                    'completed_at' => $rental->document_upload_completed_at,
                ],
                'agreement_signing' => [
                    'completed' => ! is_null($rental->agreement_signed_at),
                    'completed_at' => $rental->agreement_signed_at,
                ],
                'active_rental' => [
                    'active' => $rental->phase === 'active',
                    'start_time' => $rental->start_time,
                    'elapsed_minutes' => $rental->start_time ? $rental->start_time->diffInMinutes(now()) : null,
                ],
                'completed' => $rental->phase === 'completed',
            ],
        ];

        if ($rental->document) {
            $phaseStatus['phases']['document_upload']['license_image'] = $rental->document->license_image ? asset('storage/'.$rental->document->license_image) : null;
            $phaseStatus['phases']['document_upload']['aadhaar_image'] = $rental->document->aadhaar_image ? asset('storage/'.$rental->document->aadhaar_image) : null;
        }
        if ($rental->agreement_path) {
            $phaseStatus['phases']['agreement_signing']['agreement_url'] = asset('storage/'.$rental->agreement_path);
        }
        if ($rental->signed_agreement_path) {
            $phaseStatus['phases']['agreement_signing']['signed_agreement_url'] = asset('storage/'.$rental->signed_agreement_path);
        }
        if ($rental->customer_with_vehicle_image) {
            $phaseStatus['phases']['agreement_signing']['customer_photo_url'] = asset('storage/'.$rental->customer_with_vehicle_image);
        }
        if ($rental->vehicle_condition_video) {
            $phaseStatus['phases']['agreement_signing']['condition_video_url'] = asset('storage/'.$rental->vehicle_condition_video);
        }
        if ($rental->receipt_path) {
            $phaseStatus['phases']['completed']['receipt_url'] = asset('storage/'.$rental->receipt_path);
        }

        return $phaseStatus;
    }

    protected function logFreshVerification($customerId)
    {
        try {
            CustomerAccessLog::create(['customer_id' => $customerId, 'user_id' => auth()->id(), 'action' => 'fresh_verification', 'created_at' => now()]);
        } catch (Exception $e) {
            Log::error('Failed to log fresh verification', ['error' => $e->getMessage()]);
        }
    }

    protected function logCachedVerification($customerId)
    {
        try {
            CustomerAccessLog::create(['customer_id' => $customerId, 'user_id' => auth()->id(), 'action' => 'cached_verification', 'created_at' => now()]);
        } catch (Exception $e) {
            Log::error('Failed to log cached verification', ['error' => $e->getMessage()]);
        }
    }

    protected function logRentalStart($customerId, $rentalId)
    {
        try {
            CustomerAccessLog::create(['customer_id' => $customerId, 'user_id' => auth()->id(), 'action' => 'rental_start', 'rental_id' => $rentalId, 'created_at' => now()]);
        } catch (Exception $e) {
            Log::error('Failed to log rental start', ['error' => $e->getMessage()]);
        }
    }

    protected function logRentalEnd($customerId, $rentalId)
    {
        try {
            CustomerAccessLog::create(['customer_id' => $customerId, 'user_id' => auth()->id(), 'action' => 'rental_end', 'rental_id' => $rentalId, 'created_at' => now()]);
        } catch (Exception $e) {
            Log::error('Failed to log rental end', ['error' => $e->getMessage()]);
        }
    }

    private function getUserAverageRentalDuration($userId): ?float
    {
        $completedRentals = Rental::where('user_id', $userId)
            ->where('status', 'completed')
            ->whereNotNull('start_time')
            ->whereNotNull('end_time')
            ->get();
        if ($completedRentals->isEmpty()) {
            return null;
        }
        $totalMinutes = $completedRentals->sum(fn ($rental) => $rental->start_time->diffInMinutes($rental->end_time));

        return round($totalMinutes / $completedRentals->count(), 2);
    }

    private function getUserMostRentedVehicle($userId)
    {
        $vehicle = Rental::where('user_id', $userId)
            ->where('status', 'completed')
            ->select('vehicle_id', DB::raw('COUNT(*) as rental_count'))
            ->groupBy('vehicle_id')
            ->orderBy('rental_count', 'desc')
            ->with('vehicle')
            ->first();
        if (! $vehicle || ! $vehicle->vehicle) {
            return null;
        }

        return ['id' => $vehicle->vehicle->id, 'name' => $vehicle->vehicle->name, 'rental_count' => $vehicle->rental_count];
    }

    public function downloadSignedAgreement($id)
    {
        try {
            $user = auth()->user();
            $rental = Rental::where('id', $id)->where('user_id', $user->id)->first();

            if (! $rental || ! $rental->signed_agreement_path || ! Storage::disk('public')->exists($rental->signed_agreement_path)) {
                return response()->json(['success' => false, 'message' => 'Signed agreement not found'], 404);
            }

            return Storage::disk('public')->download($rental->signed_agreement_path);
        } catch (Exception $e) {
            Log::error('Download signed agreement error: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to download signed agreement'], 500);
        }
    }
}
