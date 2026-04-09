// database/migrations/2026_01_01_000001_add_rental_phases_to_rentals_table.php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Phase tracking
            $table->enum('phase', ['verification', 'document_upload', 'agreement_signing', 'active', 'completed', 'cancelled'])
                ->default('verification')
                ->after('status');
            
            // Verification tracking
            $table->decimal('verification_fee_deducted', 10, 2)->default(0)->after('total_price');
            $table->unsignedBigInteger('verification_transaction_id')->nullable()->after('verification_fee_deducted');
            $table->boolean('is_verification_cached')->default(false)->after('verification_transaction_id');
            $table->string('verification_reference_id')->nullable()->after('is_verification_cached');
            
            // Phase 3: Signed agreement and vehicle condition
            $table->string('signed_agreement_path')->nullable()->after('agreement_path');
            $table->string('customer_with_vehicle_image')->nullable()->after('signed_agreement_path');
            $table->string('vehicle_condition_video')->nullable()->after('customer_with_vehicle_image');
            
            // Return assessment
            $table->boolean('vehicle_in_good_condition')->nullable()->after('vehicle_condition_video');
            $table->decimal('damage_amount', 10, 2)->default(0)->after('vehicle_in_good_condition');
            $table->text('damage_description')->nullable()->after('damage_amount');
            $table->json('damage_images')->nullable()->after('damage_description');
            
            // Timestamps for each phase
            $table->timestamp('verification_completed_at')->nullable();
            $table->timestamp('document_upload_completed_at')->nullable();
            $table->timestamp('agreement_signed_at')->nullable();
            $table->timestamp('return_completed_at')->nullable();
        });
    }

    public function down()
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropColumn([
                'phase', 'signed_agreement_path', 'customer_with_vehicle_image',
                'vehicle_condition_video', 'vehicle_in_good_condition',
                'damage_amount', 'damage_description', 'damage_images',
                'verification_completed_at', 'document_upload_completed_at',
                'agreement_signed_at', 'return_completed_at',
                'verification_fee_deducted', 'verification_transaction_id',
                'is_verification_cached', 'verification_reference_id'
            ]);
        });
    }
};