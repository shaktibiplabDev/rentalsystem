<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddTransferGroupIdToWalletTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Add transfer_group_id to link debit and credit transactions together
            $table->string('transfer_group_id', 100)->nullable()->after('reference_id');
            
            // Add index for faster lookups
            $table->index('transfer_group_id', 'idx_transfer_group_id');
            
            // Optional: Add composite index for common queries
            $table->index(['user_id', 'transfer_group_id'], 'idx_user_transfer_group');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex('idx_transfer_group_id');
            $table->dropIndex('idx_user_transfer_group');
            $table->dropColumn('transfer_group_id');
        });
    }
}