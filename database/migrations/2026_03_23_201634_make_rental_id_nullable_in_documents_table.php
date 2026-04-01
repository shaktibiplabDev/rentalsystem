<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeRentalIdNullableInDocumentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('documents', function (Blueprint $table) {
            // Make rental_id nullable
            if (Schema::hasColumn('documents', 'rental_id')) {
                $table->unsignedBigInteger('rental_id')->nullable()->change();
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
            if (Schema::hasColumn('documents', 'rental_id')) {
                $table->unsignedBigInteger('rental_id')->nullable(false)->change();
            }
        });
    }
}