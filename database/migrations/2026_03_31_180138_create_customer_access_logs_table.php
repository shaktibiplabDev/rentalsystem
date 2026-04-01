<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomerAccessLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('customer_access_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('user_id'); // shop owner who accessed
            $table->string('action', 50); // 'view', 'rental_start', 'rental_end', 'verify', 'update'
            $table->unsignedBigInteger('rental_id')->nullable();
            $table->text('metadata')->nullable(); // Additional data like IP, user agent, etc.
            $table->timestamp('created_at')->useCurrent();
            
            // Indexes for faster queries
            $table->index(['customer_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
            
            // Foreign keys
            $table->foreign('customer_id')
                  ->references('id')
                  ->on('customers')
                  ->onDelete('cascade');
                  
            $table->foreign('user_id')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
                  
            $table->foreign('rental_id')
                  ->references('id')
                  ->on('rentals')
                  ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('customer_access_logs');
    }
}