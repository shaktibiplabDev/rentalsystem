<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // Phone remains NOT NULL - Google users must provide phone
            // So no change to phone column
            
            // Add Google login columns
            if (!Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('email');
            }
            
            if (!Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('google_id');
            }
            
            if (!Schema::hasColumn('users', 'is_google_user')) {
                $table->boolean('is_google_user')->default(false)->after('avatar');
            }
            
            if (!Schema::hasColumn('users', 'google_verified_at')) {
                $table->timestamp('google_verified_at')->nullable()->after('is_google_user');
            }
            
            // Add password_reset_required flag for Google users who need to set password
            if (!Schema::hasColumn('users', 'password_set_required')) {
                $table->boolean('password_set_required')->default(false)->after('google_verified_at');
            }
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'google_id',
                'avatar', 
                'is_google_user',
                'google_verified_at',
                'password_set_required'
            ]);
        });
    }
};