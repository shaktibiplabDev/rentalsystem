<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeAadhaarImageNullableInDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            // Make aadhaar_image column nullable
            if (Schema::hasColumn('documents', 'aadhaar_image')) {
                $table->string('aadhaar_image')->nullable()->change();
            }
            
            // Also make aadhaar_ocr_data nullable if it exists
            if (Schema::hasColumn('documents', 'aadhaar_ocr_data')) {
                $table->text('aadhaar_ocr_data')->nullable()->change();
            }
            
            // Make extracted_aadhaar nullable if it exists
            if (Schema::hasColumn('documents', 'extracted_aadhaar')) {
                $table->string('extracted_aadhaar')->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('documents', function (Blueprint $table) {
            // Revert changes (this might fail if there are NULL values)
            if (Schema::hasColumn('documents', 'aadhaar_image')) {
                $table->string('aadhaar_image')->nullable(false)->change();
            }
            
            if (Schema::hasColumn('documents', 'aadhaar_ocr_data')) {
                $table->text('aadhaar_ocr_data')->nullable(false)->change();
            }
            
            if (Schema::hasColumn('documents', 'extracted_aadhaar')) {
                $table->string('extracted_aadhaar')->nullable(false)->change();
            }
        });
    }
}