<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CashfreeService
{
    protected $verificationClientId;

    protected $verificationClientSecret;

    protected $paymentClientId;

    protected $paymentClientSecret;

    protected $baseUrl;

    protected $apiVersion;

    protected $paymentApiVersion;

    protected $mode;

    protected $webhookSecret;

    public function __construct()
    {
        $this->mode = config('cashfree.mode', 'sandbox');

        // Set base URL based on mode
        $this->baseUrl = $this->mode === 'production'
            ? 'https://api.cashfree.com'
            : 'https://sandbox.cashfree.com';

        // Verification API credentials (for document verification)
        $this->verificationClientId = config('cashfree.verification.client_id');
        $this->verificationClientSecret = config('cashfree.verification.client_secret');
        $this->apiVersion = config('cashfree.verification.api_version', '2025-01-01');

        // Payment API credentials (for wallet recharge)
        $this->paymentClientId = config('cashfree.payment.client_id');
        $this->paymentClientSecret = config('cashfree.payment.client_secret');
        $this->paymentApiVersion = config('cashfree.payment.api_version', '2022-09-01');

        // ⚠️ CRITICAL: Webhook secret - MUST be configured in Cashfree Dashboard
        // How to get: Cashfree Dashboard → Payment Gateway → Developers → Webhook Configuration
        // After creating webhook, copy the "Secret Key" to .env as CASHFREE_WEBHOOK_SECRET

        if (empty($this->verificationClientId) || empty($this->verificationClientSecret)) {
            Log::warning('Cashfree verification credentials not configured');
        }

        if (empty($this->paymentClientId) || empty($this->paymentClientSecret)) {
            Log::warning('Cashfree payment credentials not configured');
        }

        if (empty($this->webhookSecret)) {
            Log::warning('⚠️ Cashfree webhook secret not configured! Webhook verification will fail.', [
                'environment' => $this->mode,
            ]);
        }
    }

    /**
     * Create a payment order for wallet recharge
     *
     * @param  string|null  $idempotencyKey  Optional key to prevent duplicate orders
     * @return array
     */
    public function createPaymentOrder(array $orderData, $idempotencyKey = null)
    {
        try {
            $headers = [
                'Content-Type' => 'application/json',
                'x-client-id' => $this->paymentClientId,
                'x-client-secret' => $this->paymentClientSecret,
                'x-api-version' => $this->paymentApiVersion,
            ];

            // Add idempotency key if provided
            if ($idempotencyKey) {
                $headers['x-idempotency-key'] = $idempotencyKey;
            }

            $response = Http::withHeaders($headers)
                ->post($this->baseUrl.'/pg/orders', $orderData);

            Log::info('Cashfree API Response', [
                'status' => $response->status(),
                'order_id_sent' => $orderData['order_id'] ?? 'unknown',
                'idempotency_key' => $idempotencyKey ? substr($idempotencyKey, 0, 20).'...' : null,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $sessionId = $data['payment_session_id'] ?? null;

                Log::info('Cashfree payment order created', [
                    'order_id' => $data['order_id'] ?? null,
                    'payment_session_id' => $sessionId ? substr($sessionId, 0, 20).'...' : null,
                    'order_status' => $data['order_status'] ?? null,
                ]);

                return [
                    'success' => true,
                    'order_id' => $data['order_id'],
                    'payment_session_id' => $sessionId,
                    'order_status' => $data['order_status'] ?? null,
                    'raw_response' => $data,
                ];
            }

            Log::error('Cashfree create payment order failed', [
                'status' => $response->status(),
                'response' => $response->body(),
                'order_data' => $orderData,
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Failed to create payment order',
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Cashfree create payment order exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment service temporarily unavailable: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Get payment order status
     */
    public function getPaymentOrderStatus($orderId)
    {
        try {
            Log::info('Fetching payment order status', [
                'order_id' => $orderId,
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-client-id' => $this->paymentClientId,
                'x-client-secret' => $this->paymentClientSecret,
                'x-api-version' => $this->paymentApiVersion,
            ])->get($this->baseUrl.'/pg/orders/'.$orderId);

            if ($response->successful()) {
                $data = $response->json();

                Log::info('Payment order status retrieved', [
                    'order_id' => $orderId,
                    'order_status' => $data['order_status'],
                    'order_amount' => $data['order_amount'],
                ]);

                return [
                    'success' => true,
                    'order_id' => $data['order_id'],
                    'order_status' => $data['order_status'],
                    'order_amount' => $data['order_amount'],
                    'order_currency' => $data['order_currency'],
                    'payments' => $data['payments'] ?? [],
                    'raw_response' => $data,
                ];
            }

            Log::error('Cashfree get payment order failed', [
                'order_id' => $orderId,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);

            return [
                'success' => false,
                'error' => $response->json()['message'] ?? 'Failed to fetch order status',
                'status_code' => $response->status(),
            ];
        } catch (Exception $e) {
            Log::error('Cashfree get payment order exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Service temporarily unavailable',
            ];
        }
    }

    /**
     * ✅ CRITICAL: Verify webhook signature
     *
     * This ensures the webhook is actually from Cashfree and not a fake request.
     *
     * IMPORTANT: Cashfree uses base64 encoded HMAC SHA256 signature.
     * The signature is in the header: x-webhook-signature
     *
     * How to get webhook secret:
     * 1. Go to Cashfree Dashboard → Payment Gateway → Developers → Webhook Configuration
     * 2. Click "Create Webhook" or edit existing
     * 3. Set URL: https://your-domain.com/api/wallet/payment-webhook
     * 4. Select events: PAYMENT_SUCCESS_WEBHOOK, PAYMENT_FAILED_WEBHOOK
     * 5. After creation, you'll see a "Secret Key" - copy that to .env as CASHFREE_WEBHOOK_SECRET
     */
    public function verifyWebhookSignature($payload, $signature)
    {
        if (empty($this->webhookSecret)) {
            Log::error('❌ Webhook secret not configured! Cannot verify webhook authenticity.');

            return false;
        }

        // ✅ CORRECT: Cashfree uses base64 encoded HMAC SHA256
        $calculatedSignature = base64_encode(hash_hmac('sha256', $payload, $this->webhookSecret, true));
        $isValid = hash_equals($calculatedSignature, $signature);

        Log::info('Webhook signature verification', [
            'is_valid' => $isValid,
            'received_signature' => $signature ? substr($signature, 0, 20).'...' : 'missing',
            'calculated_signature' => substr($calculatedSignature, 0, 20).'...',
        ]);

        return $isValid;
    }

    /**
     * Get payment link from session ID (for web fallback)
     */
    public function getPaymentLink($paymentSessionId)
    {
        if (! $paymentSessionId) {
            return null;
        }

        if ($this->mode === 'production') {
            return 'https://payments.cashfree.com/order/pay?order_session_id='.$paymentSessionId;
        } else {
            return 'https://sandbox.cashfree.com/pg/orders/pay?order_session_id='.$paymentSessionId;
        }
    }

    /**
     * Validate payment session ID format
     */
    public function isValidPaymentSessionId($sessionId)
    {
        if (empty($sessionId)) {
            return false;
        }

        // Valid session ID should start with "session_" and contain only alphanumeric and underscore
        $pattern = '/^session_[A-Za-z0-9]+$/';
        $isValid = (bool) preg_match($pattern, $sessionId);

        if (! $isValid) {
            Log::warning('Invalid payment session ID format', [
                'session_id' => $sessionId,
                'length' => strlen($sessionId),
            ]);
        }

        return $isValid;
    }

    /**
     * ============================================
     * DOCUMENT VERIFICATION METHODS (UNCHANGED)
     * ============================================
     */

    /**
     * Verify Driving License using DL number and DOB
     * Added idempotency support and retry logic
     */
    public function verifyDrivingLicense($dlNumber, $dob, $idempotencyKey = null)
    {
        // Generate idempotency key if not provided
        if (! $idempotencyKey) {
            $idempotencyKey = 'dl_'.md5($dlNumber.$dob.date('Ymd'));
        }

        $attempt = 0;
        $maxAttempts = 3;
        $lastError = null;

        while ($attempt < $maxAttempts) {
            try {
                $verificationId = $idempotencyKey.'_'.uniqid();

                Log::info('DL verification attempt', [
                    'attempt' => $attempt + 1,
                    'dl_number' => substr($dlNumber, 0, 4).'****',
                    'verification_id' => $verificationId,
                ]);

                $response = Http::withHeaders([
                    'Content-Type' => 'application/json',
                    'x-client-id' => $this->verificationClientId,
                    'x-client-secret' => $this->verificationClientSecret,
                    'x-api-version' => $this->apiVersion,
                    'x-idempotency-key' => $idempotencyKey,  // Idempotency header
                ])->post($this->baseUrl.'/verification/driving-license', [
                    'verification_id' => $verificationId,
                    'dl_number' => $dlNumber,
                    'dob' => $dob,
                ]);

                if ($response->successful()) {
                    $data = $response->json();
                    $details = $data['details_of_driving_licence'] ?? [];
                    $validity = $data['dl_validity'] ?? [];

                    Log::info('DL verification successful', [
                        'dl_number' => substr($dlNumber, 0, 4).'****',
                        'status' => $data['status'] ?? 'UNKNOWN',
                    ]);

                    return [
                        'success' => true,
                        'status' => $data['status'] ?? 'INVALID',
                        'verification_id' => $data['verification_id'] ?? $verificationId,
                        'reference_id' => $data['reference_id'] ?? null,
                        'dl_number' => $data['dl_number'] ?? $dlNumber,
                        'dob' => $data['dob'] ?? $dob,
                        'name' => $details['name'] ?? null,
                        'father_name' => $details['father_or_husband_name'] ?? null,
                        'address' => $details['address'] ?? null,
                        'photo_url' => $details['photo'] ?? null,
                        'date_of_issue' => $details['date_of_issue'] ?? null,
                        'valid_from' => $validity['non_transport']['from'] ?? null,
                        'valid_to' => $validity['non_transport']['to'] ?? null,
                        'vehicle_classes' => $this->extractVehicleClasses($data),
                        'raw_response' => $data,
                    ];
                }

                // If HTTP error, retry on 5xx, not on 4xx
                $status = $response->status();
                if ($status >= 500 && $attempt < $maxAttempts - 1) {
                    $attempt++;
                    sleep(pow(2, $attempt)); // exponential backoff

                    continue;
                }

                // Non-retryable error
                Log::error('DL verification failed', [
                    'dl_number' => substr($dlNumber, 0, 4).'****',
                    'status' => $status,
                    'response' => $response->body(),
                ]);

                return [
                    'success' => false,
                    'error' => $response->json()['message'] ?? 'Verification failed',
                    'status_code' => $status,
                ];

            } catch (Exception $e) {
                $lastError = $e->getMessage();
                if ($attempt < $maxAttempts - 1) {
                    $attempt++;
                    sleep(pow(2, $attempt));

                    continue;
                }
                break;
            }
        }

        Log::error('DL verification final failure after retries', [
            'dl_number' => substr($dlNumber, 0, 4).'****',
            'last_error' => $lastError,
        ]);

        return [
            'success' => false,
            'error' => 'Service temporarily unavailable after '.$maxAttempts.' attempts',
        ];
    }

    /**
     * Verify GST number with Cashfree API
     *
     * @param  string|null  $businessName  (optional for better matching)
     */
    public function verifyGST(string $gstNumber, ?string $businessName = null): array
    {
        try {
            $payload = ['GSTIN' => $gstNumber];

            // Add business name if provided for better verification
            if ($businessName) {
                $payload['business_name'] = $businessName;
            }

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-api-version' => $this->apiVersion,
                'x-client-id' => $this->verificationClientId,
                'x-client-secret' => $this->verificationClientSecret,
            ])->post($this->baseUrl.'/verification/gstin', $payload);

            if ($response->successful()) {
                $data = $response->json();

                // Check if GSTIN exists
                if (isset($data['valid']) && $data['valid'] === true && isset($data['GSTIN'])) {
                    // Parse principal place address
                    $principalAddress = $data['principal_place_split_address'] ?? null;
                    $additionalAddresses = $data['additional_address_array'] ?? [];

                    return [
                        'success' => true,
                        'valid' => true,
                        'gst_number' => $data['GSTIN'],
                        'legal_business_name' => $data['legal_name_of_business'] ?? null,
                        'trade_business_name' => $data['trade_name_of_business'] ?? null,
                        'business_name' => $data['trade_name_of_business'] ?? $data['legal_name_of_business'] ?? null,
                        'date_of_registration' => $data['date_of_registration'] ?? null,
                        'taxpayer_type' => $data['taxpayer_type'] ?? null,
                        'gst_status' => $data['gst_in_status'] ?? null,
                        'constitution_of_business' => $data['constitution_of_business'] ?? null,
                        'center_jurisdiction' => $data['center_jurisdiction'] ?? null,
                        'state_jurisdiction' => $data['state_jurisdiction'] ?? null,
                        'last_update_date' => $data['last_update_date'] ?? null,
                        'nature_of_business_activities' => $data['nature_of_business_activities'] ?? [],

                        // Address information
                        'address' => $data['principal_place_address'] ?? null,
                        'principal_address_details' => $principalAddress ? [
                            'building_name' => $principalAddress['building_name'] ?? null,
                            'building_number' => $principalAddress['building_number'] ?? null,
                            'street' => $principalAddress['street'] ?? null,
                            'location' => $principalAddress['location'] ?? null,
                            'city' => $principalAddress['city'] ?? null,
                            'district' => $principalAddress['district'] ?? null,
                            'state' => $principalAddress['state'] ?? null,
                            'pincode' => $principalAddress['pincode'] ?? null,
                            'latitude' => $principalAddress['latitude'] ?? null,
                            'longitude' => $principalAddress['longitude'] ?? null,
                        ] : null,

                        'additional_addresses' => array_map(function ($addr) {
                            return [
                                'address' => $addr['address'] ?? null,
                                'split_address' => $addr['split_address'] ?? null,
                            ];
                        }, $additionalAddresses),

                        'reference_id' => $data['reference_id'] ?? null,
                        'raw_response' => $data,
                    ];
                }

                // GSTIN doesn't exist
                return [
                    'success' => false,
                    'valid' => false,
                    'error' => $data['message'] ?? 'GSTIN does not exist',
                    'raw_response' => $data,
                ];
            }

            // Handle API errors
            $errorData = $response->json();
            $errorMessage = $errorData['message'] ?? 'GST verification failed';

            Log::error('GST verification API error', [
                'gst_number' => $gstNumber,
                'status_code' => $response->status(),
                'error' => $errorMessage,
                'response' => $errorData,
            ]);

            return [
                'success' => false,
                'valid' => false,
                'error' => $errorMessage,
                'status_code' => $response->status(),
            ];

        } catch (Exception $e) {
            Log::error('GST verification exception', [
                'gst_number' => $gstNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'valid' => false,
                'error' => 'Failed to connect to verification service',
            ];
        }
    }

    /**
     * Extract vehicle classes from response
     */
    protected function extractVehicleClasses($data)
    {
        $classes = [];

        if (isset($data['badge_details']) && is_array($data['badge_details'])) {
            foreach ($data['badge_details'] as $badge) {
                if (isset($badge['class_of_vehicle']) && is_array($badge['class_of_vehicle'])) {
                    $classes = array_merge($classes, $badge['class_of_vehicle']);
                }
            }
        }

        return array_unique($classes);
    }

    /**
     * Test API connectivity (for debugging)
     */
    public function testConnection()
    {
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-client-id' => $this->paymentClientId,
                'x-client-secret' => $this->paymentClientSecret,
                'x-api-version' => $this->paymentApiVersion,
            ])->get($this->baseUrl.'/pg/orders');

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'message' => $response->successful() ? 'Connected successfully' : 'Connection failed',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
