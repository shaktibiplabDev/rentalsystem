<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert verification price setting
        DB::table('settings')->updateOrInsert(
            ['key' => 'verification_price'],
            [
                'value' => '5',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Insert lease threshold minutes setting
        DB::table('settings')->updateOrInsert(
            ['key' => 'lease_threshold_minutes'],
            [
                'value' => '5',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->whereIn('key', ['verification_price', 'lease_threshold_minutes'])
            ->delete();
    }
};