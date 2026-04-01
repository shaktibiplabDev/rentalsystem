<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentPathsToRentalsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Add receipt_path column
            if (!Schema::hasColumn('rentals', 'receipt_path')) {
                $table->string('receipt_path')->nullable()->after('total_price');
            }
            
            // Add agreement_path column
            if (!Schema::hasColumn('rentals', 'agreement_path')) {
                $table->string('agreement_path')->nullable()->after('receipt_path');
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
        Schema::table('rentals', function (Blueprint $table) {
            if (Schema::hasColumn('rentals', 'receipt_path')) {
                $table->dropColumn('receipt_path');
            }
            if (Schema::hasColumn('rentals', 'agreement_path')) {
                $table->dropColumn('agreement_path');
            }
        });
    }
}