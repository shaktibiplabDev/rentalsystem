<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Remove duplicate rows with same payment_order_id (keep the completed one if exists)
        $duplicates = DB::table('wallet_transactions')
            ->select('payment_order_id', DB::raw('COUNT(*) as count'))
            ->whereNotNull('payment_order_id')
            ->groupBy('payment_order_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            $ids = DB::table('wallet_transactions')
                ->where('payment_order_id', $dup->payment_order_id)
                ->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
                ->orderBy('id', 'desc')
                ->get()
                ->pluck('id')
                ->slice(1);
            DB::table('wallet_transactions')->whereIn('id', $ids)->delete();
        }

        // Add unique index on payment_order_id (prevents duplicate order_ids)
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->unique('payment_order_id');
        });
    }

    public function down()
    {
        Schema::table('wallet_transactions', function (Blueprint $table) {
            $table->dropUnique(['payment_order_id']);
        });
    }
};