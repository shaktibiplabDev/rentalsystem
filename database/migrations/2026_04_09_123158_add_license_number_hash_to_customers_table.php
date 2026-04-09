<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Customer;

return new class extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('customers', 'license_number_hash')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->string('license_number_hash', 64)->nullable()->after('license_number');
                $table->index('license_number_hash'); // regular index, not unique
            });
        }

        // Backfill existing customers
        $customers = Customer::all();
        foreach ($customers as $customer) {
            if ($customer->license_number && !$customer->license_number_hash) {
                $plain = $customer->license_number;
                if ($plain) {
                    $customer->license_number_hash = hash('sha256', strtoupper($plain));
                    $customer->saveQuietly();
                }
            }
        }
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropIndex(['license_number_hash']);
            $table->dropColumn('license_number_hash');
        });
    }
};