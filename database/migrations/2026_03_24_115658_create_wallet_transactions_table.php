<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateWalletTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id(); // BIGINT UNSIGNED

            $table->unsignedBigInteger('user_id')->nullable();

            $table->decimal('amount', 10, 2);
            $table->string('type'); // credit / debit

            // Keep minimal here, rest added by your existing migration
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
}