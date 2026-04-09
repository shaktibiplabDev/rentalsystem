<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            // Add verification_status if missing
            if (!Schema::hasColumn('documents', 'verification_status')) {
                $table->string('verification_status', 50)->nullable()->after('is_verified');
            }

            // Add license_ocr_data if missing
            if (!Schema::hasColumn('documents', 'license_ocr_data')) {
                $table->json('license_ocr_data')->nullable()->after('aadhaar_image');
            }

            // Add extracted_name if missing
            if (!Schema::hasColumn('documents', 'extracted_name')) {
                $table->string('extracted_name')->nullable()->after('license_ocr_data');
            }

            // Add extracted_license if missing
            if (!Schema::hasColumn('documents', 'extracted_license')) {
                $table->string('extracted_license')->nullable()->after('extracted_name');
            }

            // Add verified_at if missing
            if (!Schema::hasColumn('documents', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('extracted_license');
            }

            // Also ensure aadhaar_image column exists (optional)
            if (!Schema::hasColumn('documents', 'aadhaar_image')) {
                $table->string('aadhaar_image')->nullable()->after('license_image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $columns = ['verification_status', 'license_ocr_data', 'extracted_name', 'extracted_license', 'verified_at', 'aadhaar_image'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('documents', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};