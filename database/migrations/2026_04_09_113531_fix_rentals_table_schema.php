<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Fix status column - change from ENUM to VARCHAR(50) if needed
            // First check if column exists and is ENUM, then modify
            if (Schema::hasColumn('rentals', 'status')) {
                $table->string('status', 50)->default('pending')->change();
            } else {
                $table->string('status', 50)->default('pending');
            }

            // Add phase column if missing
            if (!Schema::hasColumn('rentals', 'phase')) {
                $table->string('phase', 50)->default('verification')->after('status');
            }

            // Add other missing columns that the controller uses
            if (!Schema::hasColumn('rentals', 'verification_fee_deducted')) {
                $table->decimal('verification_fee_deducted', 10, 2)->default(0)->after('total_price');
            }

            if (!Schema::hasColumn('rentals', 'verification_transaction_id')) {
                $table->unsignedBigInteger('verification_transaction_id')->nullable()->after('verification_fee_deducted');
            }

            if (!Schema::hasColumn('rentals', 'is_verification_cached')) {
                $table->boolean('is_verification_cached')->default(false)->after('verification_transaction_id');
            }

            if (!Schema::hasColumn('rentals', 'verification_reference_id')) {
                $table->string('verification_reference_id', 100)->nullable()->after('is_verification_cached');
            }

            if (!Schema::hasColumn('rentals', 'verification_completed_at')) {
                $table->timestamp('verification_completed_at')->nullable()->after('verification_reference_id');
            }

            if (!Schema::hasColumn('rentals', 'document_id')) {
                $table->unsignedBigInteger('document_id')->nullable()->after('verification_completed_at');
            }

            if (!Schema::hasColumn('rentals', 'document_upload_completed_at')) {
                $table->timestamp('document_upload_completed_at')->nullable()->after('document_id');
            }

            if (!Schema::hasColumn('rentals', 'agreement_path')) {
                $table->string('agreement_path')->nullable()->after('document_upload_completed_at');
            }

            if (!Schema::hasColumn('rentals', 'signed_agreement_path')) {
                $table->string('signed_agreement_path')->nullable()->after('agreement_path');
            }

            if (!Schema::hasColumn('rentals', 'customer_with_vehicle_image')) {
                $table->string('customer_with_vehicle_image')->nullable()->after('signed_agreement_path');
            }

            if (!Schema::hasColumn('rentals', 'vehicle_condition_video')) {
                $table->string('vehicle_condition_video')->nullable()->after('customer_with_vehicle_image');
            }

            if (!Schema::hasColumn('rentals', 'agreement_signed_at')) {
                $table->timestamp('agreement_signed_at')->nullable()->after('vehicle_condition_video');
            }

            if (!Schema::hasColumn('rentals', 'end_time')) {
                $table->timestamp('end_time')->nullable()->after('start_time');
            }

            if (!Schema::hasColumn('rentals', 'vehicle_in_good_condition')) {
                $table->boolean('vehicle_in_good_condition')->default(true)->after('end_time');
            }

            if (!Schema::hasColumn('rentals', 'damage_amount')) {
                $table->decimal('damage_amount', 10, 2)->default(0)->after('vehicle_in_good_condition');
            }

            if (!Schema::hasColumn('rentals', 'damage_description')) {
                $table->text('damage_description')->nullable()->after('damage_amount');
            }

            if (!Schema::hasColumn('rentals', 'damage_images')) {
                $table->json('damage_images')->nullable()->after('damage_description');
            }

            if (!Schema::hasColumn('rentals', 'return_completed_at')) {
                $table->timestamp('return_completed_at')->nullable()->after('damage_images');
            }

            if (!Schema::hasColumn('rentals', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('return_completed_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Remove added columns - but careful not to break existing data
            $columns = [
                'phase', 'verification_fee_deducted', 'verification_transaction_id',
                'is_verification_cached', 'verification_reference_id', 'verification_completed_at',
                'document_id', 'document_upload_completed_at', 'agreement_path',
                'signed_agreement_path', 'customer_with_vehicle_image', 'vehicle_condition_video',
                'agreement_signed_at', 'end_time', 'vehicle_in_good_condition', 'damage_amount',
                'damage_description', 'damage_images', 'return_completed_at', 'receipt_path'
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('rentals', $column)) {
                    $table->dropColumn($column);
                }
            }
            // Revert status to original type if needed (you may skip this in down)
        });
    }
};