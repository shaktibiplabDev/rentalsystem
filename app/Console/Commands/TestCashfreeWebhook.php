<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestCashfreeWebhook extends Command
{
    protected $signature = 'cashfree:test-webhook {--success} {--failed}';
    protected $description = 'Test Cashfree webhook locally';
    
    public function handle()
    {
        $webhookUrl = config('app.url') . '/api/webhooks/cashfree/verification';
        
        $payload = $this->option('success') 
            ? $this->getSuccessPayload()
            : $this->getFailurePayload();
        
        $this->info("Sending test webhook to: {$webhookUrl}");
        
        $response = Http::post($webhookUrl, $payload);
        
        $this->info("Response: " . $response->status());
        $this->info($response->body());
    }
    
    protected function getSuccessPayload()
    {
        return [
            'event' => 'VERIFICATION_SUCCESS',
            'verification_id' => 'TEST_VERIFICATION_123',
            'status' => 'SUCCESS',
            'document_type' => 'AADHAAR',
            'document_fields' => [
                'name' => 'Test User',
                'aadhaar_number' => '123456789012',
                'dob' => '1990-01-01'
            ],
            'quality_checks' => [
                'blur' => false,
                'glare' => false,
                'partially_present' => false
            ],
            'fraud_checks' => [
                'is_screenshot' => false,
                'is_forged' => false
            ]
        ];
    }
    
    protected function getFailurePayload()
    {
        return [
            'event' => 'VERIFICATION_FAILED',
            'verification_id' => 'TEST_VERIFICATION_123',
            'status' => 'FAILED',
            'message' => 'Image quality too low',
            'quality_checks' => [
                'blur' => true,
                'glare' => false,
                'partially_present' => true
            ]
        ];
    }
}