<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add all columns that might be missing based on the Customer model
            if (!Schema::hasColumn('customers', 'father_name')) {
                $table->string('father_name')->nullable()->after('name');
            }

            if (!Schema::hasColumn('customers', 'license_photo')) {
                $table->string('license_photo')->nullable()->after('license_data');
            }

            if (!Schema::hasColumn('customers', 'license_issue_date')) {
                $table->date('license_issue_date')->nullable()->after('license_photo');
            }

            if (!Schema::hasColumn('customers', 'license_valid_from_non_transport')) {
                $table->date('license_valid_from_non_transport')->nullable()->after('license_issue_date');
            }

            if (!Schema::hasColumn('customers', 'license_valid_to_non_transport')) {
                $table->date('license_valid_to_non_transport')->nullable()->after('license_valid_from_non_transport');
            }

            if (!Schema::hasColumn('customers', 'license_address')) {
                $table->text('license_address')->nullable()->after('license_valid_to_non_transport');
            }

            if (!Schema::hasColumn('customers', 'license_address_list')) {
                $table->json('license_address_list')->nullable()->after('license_address');
            }

            if (!Schema::hasColumn('customers', 'vehicle_classes_data')) {
                $table->json('vehicle_classes_data')->nullable()->after('license_address_list');
            }

            if (!Schema::hasColumn('customers', 'license_reference_id')) {
                $table->string('license_reference_id')->nullable()->after('vehicle_classes_data');
            }

            // Also ensure aadhaar_number exists (if needed by your logic)
            if (!Schema::hasColumn('customers', 'aadhaar_number')) {
                $table->string('aadhaar_number')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('customers', 'aadhaar_data')) {
                $table->json('aadhaar_data')->nullable()->after('license_data');
            }

            // user_id might be missing if you want to link customers to shop owners
            if (!Schema::hasColumn('customers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->after('id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $columns = [
                'father_name', 'license_photo', 'license_issue_date',
                'license_valid_from_non_transport', 'license_valid_to_non_transport',
                'license_address', 'license_address_list', 'vehicle_classes_data',
                'license_reference_id', 'aadhaar_number', 'aadhaar_data', 'user_id'
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('customers', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};