<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDetailedLicenseFieldsToCustomersTable extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {

            // New fields only (no duplicates)
            $table->date('license_issue_date')->nullable();

            $table->date('license_valid_from_non_transport')->nullable();
            $table->date('license_valid_to_non_transport')->nullable();

            $table->text('license_address')->nullable();

            $table->json('license_address_list')->nullable();

            $table->json('vehicle_classes_data')->nullable();

            $table->string('license_reference_id')->nullable();
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'license_issue_date',
                'license_valid_from_non_transport',
                'license_valid_to_non_transport',
                'license_address',
                'license_address_list',
                'vehicle_classes_data',
                'license_reference_id'
            ]);
        });
    }
}