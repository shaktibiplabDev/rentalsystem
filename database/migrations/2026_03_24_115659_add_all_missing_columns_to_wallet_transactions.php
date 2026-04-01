<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAllMissingColumnsToWalletTransactions extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            // Add reference_id (unique identifier for each transaction)
            if (!Schema::hasColumn('wallet_transactions', 'reference_id')) {
                $table->string('reference_id')->unique()->nullable();
            }
            
            // Add status column
            if (!Schema::hasColumn('wallet_transactions', 'status')) {
                $table->string('status')->default('completed');
            }
            
            // Add payment_order_id (Cashfree order ID)
            if (!Schema::hasColumn('wallet_transactions', 'payment_order_id')) {
                $table->string('payment_order_id')->nullable();
            }
            
            // Add payment_session_id (Cashfree session ID)
            if (!Schema::hasColumn('wallet_transactions', 'payment_session_id')) {
                $table->string('payment_session_id')->nullable();
            }
            
            // Add payment_details (JSON for payment responses)
            if (!Schema::hasColumn('wallet_transactions', 'payment_details')) {
                $table->json('payment_details')->nullable();
            }
            
            // Add payment_method
            if (!Schema::hasColumn('wallet_transactions', 'payment_method')) {
                $table->string('payment_method')->nullable();
            }
            
            // Add notes
            if (!Schema::hasColumn('wallet_transactions', 'notes')) {
                $table->text('notes')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $columns = ['reference_id', 'status', 'payment_order_id', 'payment_session_id', 
                        'payment_details', 'payment_method', 'notes'];
            
            foreach ($columns as $column) {
                if (Schema::hasColumn('wallet_transactions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}