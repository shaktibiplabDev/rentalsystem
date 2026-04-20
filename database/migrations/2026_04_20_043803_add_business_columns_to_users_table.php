<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('business_display_name')->nullable();
            $table->text('business_display_address')->nullable();
            $table->string('legal_business_name')->nullable();
            $table->string('gst_number', 15)->nullable();
            $table->timestamp('gst_verified_at')->nullable();
            $table->json('gst_verification_data')->nullable();
            $table->string('gst_status')->nullable();
            $table->string('taxpayer_type')->nullable();
            $table->string('constitution_of_business')->nullable();
            $table->json('nature_of_business_activities')->nullable();
            $table->text('registered_business_address')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('business_phone', 20)->nullable();
            $table->string('business_email')->nullable();
            $table->string('business_logo')->nullable();
            $table->enum('business_verification_status', ['unverified', 'pending', 'verified'])->default('unverified');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'business_display_name',
                'business_display_address',
                'legal_business_name',
                'gst_number',
                'gst_verified_at',
                'gst_verification_data',
                'gst_status',
                'taxpayer_type',
                'constitution_of_business',
                'nature_of_business_activities',
                'registered_business_address',
                'latitude',
                'longitude',
                'business_phone',
                'business_email',
                'business_logo',
                'business_verification_status',
            ]);
        });
    }
};