<?php

namespace App\Traits;

use App\Models\CustomerAccessLog;
use Illuminate\Support\Facades\Request;

trait LogsCustomerAccess
{
    /**
     * Log customer access
     *
     * @param int $customerId
     * @param string $action
     * @param int|null $rentalId
     * @param array|null $additionalMetadata
     * @return void
     */
    protected function logCustomerAccess($customerId, $action, $rentalId = null, $additionalMetadata = null)
    {
        try {
            $metadata = [
                'ip' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'url' => Request::fullUrl(),
                'method' => Request::method(),
            ];
            
            if ($additionalMetadata) {
                $metadata = array_merge($metadata, $additionalMetadata);
            }
            
            CustomerAccessLog::create([
                'customer_id' => $customerId,
                'user_id' => auth()->id(),
                'action' => $action,
                'rental_id' => $rentalId,
                'metadata' => $metadata,
                'created_at' => now()
            ]);
        } catch (\Exception $e) {
            // Log error but don't break the main flow
            \Illuminate\Support\Facades\Log::error('Failed to log customer access', [
                'error' => $e->getMessage(),
                'customer_id' => $customerId,
                'action' => $action
            ]);
        }
    }
    
    /**
     * Log customer view
     */
    protected function logCustomerView($customerId)
    {
        $this->logCustomerAccess($customerId, 'view');
    }
    
    /**
     * Log rental start
     */
    protected function logRentalStart($customerId, $rentalId)
    {
        $this->logCustomerAccess($customerId, 'rental_start', $rentalId);
    }
    
    /**
     * Log rental end
     */
    protected function logRentalEnd($customerId, $rentalId)
    {
        $this->logCustomerAccess($customerId, 'rental_end', $rentalId);
    }
    
    /**
     * Log customer verification (cached data used)
     */
    protected function logCachedVerification($customerId)
    {
        $this->logCustomerAccess($customerId, 'cached_verification', null, [
            'cached_used' => true,
            'verification_saved' => true
        ]);
    }
    
    /**
     * Log fresh verification (API call made)
     */
    protected function logFreshVerification($customerId)
    {
        $this->logCustomerAccess($customerId, 'fresh_verification', null, [
            'api_called' => true,
            'verification_paid' => true
        ]);
    }
}