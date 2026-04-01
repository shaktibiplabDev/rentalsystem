<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SetupCashfreeWebhook extends Command
{
    protected $signature = 'cashfree:setup-webhook {--mode=sandbox}';
    protected $description = 'Setup Cashfree webhook endpoints';
    
    public function handle()
    {
        $mode = $this->option('mode');
        $webhookUrl = config('app.url') . '/api/webhooks/cashfree/verification';
        
        $this->info('Setting up Cashfree webhooks...');
        $this->info("Webhook URL: {$webhookUrl}");
        
        $apiKey = $mode === 'production' 
            ? config('cashfree.verification.api_key')
            : config('cashfree.verification.api_key'); // Use test keys in sandbox
        
        $clientId = $mode === 'production'
            ? config('cashfree.verification.client_id')
            : config('cashfree.verification.client_id');
        
        $clientSecret = $mode === 'production'
            ? config('cashfree.verification.client_secret')
            : config('cashfree.verification.client_secret');
        
        if (!$apiKey || !$clientId || !$clientSecret) {
            $this->error('Cashfree credentials not configured. Please check your .env file.');
            return 1;
        }
        
        // Try to register webhook via Cashfree API
        try {
            $response = Http::withHeaders([
                'x-api-version' => '2024-12-01',
                'x-client-id' => $clientId,
                'x-client-secret' => $clientSecret,
                'Content-Type' => 'application/json'
            ])->post('https://api.cashfree.com/pg/webhooks', [
                'webhook_url' => $webhookUrl,
                'webhook_events' => [
                    'VERIFICATION_SUCCESS',
                    'VERIFICATION_FAILED',
                    'VERIFICATION_PENDING',
                    'PAYMENT_SUCCESS',
                    'PAYMENT_FAILED'
                ],
                'webhook_secret' => config('cashfree.webhook_secret')
            ]);
            
            if ($response->successful()) {
                $this->info('✓ Webhook registered successfully!');
                $this->info('Response: ' . json_encode($response->json(), JSON_PRETTY_PRINT));
            } else {
                $this->error('Failed to register webhook: ' . $response->body());
                
                $this->info("\nPlease manually configure webhook in Cashfree Dashboard:");
                $this->info("1. Go to https://dashboard.cashfree.com/webhooks");
                $this->info("2. Add new webhook with URL: {$webhookUrl}");
                $this->info("3. Select events: VERIFICATION_SUCCESS, VERIFICATION_FAILED, VERIFICATION_PENDING");
                $this->info("4. Set secret: " . config('cashfree.webhook_secret'));
            }
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            
            $this->info("\nPlease manually configure webhook in Cashfree Dashboard:");
            $this->info("1. Go to https://dashboard.cashfree.com/webhooks");
            $this->info("2. Add new webhook with URL: {$webhookUrl}");
            $this->info("3. Select events: VERIFICATION_SUCCESS, VERIFICATION_FAILED, VERIFICATION_PENDING");
            $this->info("4. Set secret: " . config('cashfree.webhook_secret'));
        }
        
        return 0;
    }
}