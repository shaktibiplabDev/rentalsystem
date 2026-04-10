<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class AddUniqueToCompletedTransactions extends Migration
{
    public function up()
    {
        // Ensure no duplicate completed transactions exist first
        $duplicates = DB::table('wallet_transactions')
            ->select('payment_order_id', DB::raw('COUNT(*) as count'))
            ->where('status', 'completed')
            ->whereNotNull('payment_order_id')
            ->groupBy('payment_order_id')
            ->having('count', '>', 1)
            ->get();

        foreach ($duplicates as $dup) {
            // Keep the oldest, delete newer ones
            $ids = DB::table('wallet_transactions')
                ->where('payment_order_id', $dup->payment_order_id)
                ->where('status', 'completed')
                ->orderBy('id')
                ->get()
                ->pluck('id')
                ->slice(1);
            DB::table('wallet_transactions')->whereIn('id', $ids)->delete();
        }

        // Add partial unique index (only for completed rows)
        DB::statement('CREATE UNIQUE INDEX idx_unique_completed_order ON wallet_transactions (payment_order_id) WHERE status = "completed"');
    }

    public function down()
    {
        DB::statement('DROP INDEX idx_unique_completed_order ON wallet_transactions');
    }
}