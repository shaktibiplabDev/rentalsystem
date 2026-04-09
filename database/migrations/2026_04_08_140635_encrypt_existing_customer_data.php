<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $customers = \App\Models\Customer::all();
        foreach ($customers as $customer) {
            if ($customer->aadhaar_number && ! str_starts_with($customer->aadhaar_number, 'eyJ')) {
                $plainAadhaar = $customer->aadhaar_number;
                $customer->aadhaar_number = $plainAadhaar; // This triggers encryption
                $customer->saveQuietly(); // Save without triggering events
            }
            if ($customer->license_number && ! str_starts_with($customer->license_number, 'eyJ')) {
                $plainLicense = $customer->license_number;
                $customer->license_number = $plainLicense;
                $customer->saveQuietly();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
