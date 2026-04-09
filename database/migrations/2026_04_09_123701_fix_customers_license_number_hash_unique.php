<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Customer;

return new class extends Migration
{
    public function up()
    {
        // 1. Drop the UNIQUE constraint on license_number_hash (if exists)
        try {
            DB::statement('ALTER TABLE customers DROP INDEX customers_license_number_hash_unique');
        } catch (\Exception $e) {
            // Index may not exist – safe to ignore
        }

        // 2. Clean duplicate customers (same decrypted license number)
        $customers = Customer::all();
        $seen = [];

        foreach ($customers as $customer) {
            $plain = $customer->license_number; // auto-decrypted by model
            if (!$plain) continue;

            $hash = hash('sha256', strtoupper($plain));

            if (isset($seen[$hash])) {
                // Duplicate found – delete this one after moving its rentals
                $keep = $seen[$hash];
                $customer->rentals()->update(['customer_id' => $keep->id]);
                $customer->delete();
            } else {
                // First occurrence – store the hash
                $customer->license_number_hash = $hash;
                $customer->saveQuietly();
                $seen[$hash] = $customer;
            }
        }

        // 3. Add a regular (non‑unique) index for performance
        $indexes = DB::select("SHOW INDEX FROM customers WHERE Key_name = 'customers_license_number_hash_index'");
        if (empty($indexes)) {
            Schema::table('customers', function ($table) {
                $table->index('license_number_hash');
            });
        }
    }

    public function down()
    {
        // Remove the regular index and restore unique constraint (if possible)
        Schema::table('customers', function ($table) {
            $table->dropIndex('customers_license_number_hash_index');
            $table->unique('license_number_hash');
        });
    }
};