<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        $this->backfillPlaceholders();
        $this->dedupeExistingDuplicates();

        Schema::table('payments', function (Blueprint $table) {
            $table->unique('revolut_order_id', 'payments_revolut_order_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_revolut_order_id_unique');
        });
    }

    private function backfillPlaceholders(): void
    {
        $rows = DB::table('payments')->where('revolut_order_id', 'pending')->get(['id']);
        foreach ($rows as $row) {
            DB::table('payments')->where('id', $row->id)->update([
                'revolut_order_id' => 'upgrade_pending_'.$row->id.'_'.Str::random(8),
            ]);
        }
    }

    private function dedupeExistingDuplicates(): void
    {
        $duplicates = DB::table('payments')
            ->select('revolut_order_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('revolut_order_id')
            ->having('cnt', '>', 1)
            ->pluck('revolut_order_id');

        foreach ($duplicates as $orderId) {
            $rows = DB::table('payments')
                ->where('revolut_order_id', $orderId)
                ->orderByRaw("CASE WHEN status = 'completed' THEN 0 ELSE 1 END")
                ->orderByDesc('id')
                ->get(['id']);

            $keeper = $rows->first();
            foreach ($rows->skip(1) as $row) {
                DB::table('payments')->where('id', $row->id)->update([
                    'revolut_order_id' => 'deduped_'.$row->id.'_'.Str::random(8),
                ]);
            }
        }
    }
};
