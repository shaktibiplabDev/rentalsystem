<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Drop foreign key constraint first if it exists
            if (Schema::hasColumn('customers', 'user_id')) {
                // Try to drop foreign key if it was named by Laravel convention
                $table->dropForeign(['user_id']);
                $table->dropColumn('user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (!Schema::hasColumn('customers', 'user_id')) {
                $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            }
        });
    }
};