<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddOcrFieldsToCustomersTable extends Migration
{
    public function up()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->string('license_number')->nullable()->after('address');
            $table->string('aadhaar_number')->nullable()->after('license_number');
            $table->date('date_of_birth')->nullable()->after('aadhaar_number');
            $table->string('blood_group')->nullable()->after('date_of_birth');
            $table->json('license_data')->nullable()->after('blood_group');
            $table->json('aadhaar_data')->nullable()->after('license_data');
        });
    }

    public function down()
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn([
                'license_number',
                'aadhaar_number',
                'date_of_birth',
                'blood_group',
                'license_data',
                'aadhaar_data'
            ]);
        });
    }
}