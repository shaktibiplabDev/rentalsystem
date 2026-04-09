<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('rentals', function (Blueprint $table) {
            // Add foreign key constraint if the column exists
            if (Schema::hasColumn('rentals', 'verification_transaction_id')) {
                $table->foreign('verification_transaction_id')
                      ->references('id')
                      ->on('wallet_transactions')
                      ->onDelete('set null');
            }
        });
    }

    public function down()
    {
        Schema::table('rentals', function (Blueprint $table) {
            $table->dropForeign(['verification_transaction_id']);
        });
    }
};