<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIndexesToWalletTransactions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Add indexes for frequently queried columns
            if (Schema::hasColumn('wallet_transactions', 'reference_id')) {
                $table->index('reference_id', 'idx_reference_id');
            }
            
            if (Schema::hasColumn('wallet_transactions', 'status')) {
                $table->index('status', 'idx_status');
            }
            
            if (Schema::hasColumn('wallet_transactions', 'payment_order_id')) {
                $table->index('payment_order_id', 'idx_payment_order_id');
            }
            
            if (Schema::hasColumn('wallet_transactions', 'payment_session_id')) {
                $table->index('payment_session_id', 'idx_payment_session_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropIndex(['idx_reference_id', 'idx_status', 'idx_payment_order_id', 'idx_payment_session_id']);
        });
    }
}