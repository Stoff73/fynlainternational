<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $gb = DB::table('jurisdictions')->where('code', 'GB')->first();

        if (! $gb) {
            return;
        }

        // Backfill all existing users into GB jurisdiction, skipping any that already have a row
        DB::table('user_jurisdictions')->insertUsing(
            ['user_id', 'jurisdiction_id', 'is_primary', 'activated_at', 'created_at', 'updated_at'],
            DB::table('users')
                ->leftJoin('user_jurisdictions', function ($join) use ($gb) {
                    $join->on('users.id', '=', 'user_jurisdictions.user_id')
                        ->where('user_jurisdictions.jurisdiction_id', '=', $gb->id);
                })
                ->whereNull('user_jurisdictions.id')
                ->select([
                    'users.id as user_id',
                    DB::raw((string) $gb->id . ' as jurisdiction_id'),
                    DB::raw('1 as is_primary'),
                    'users.created_at as activated_at',
                    DB::raw('NOW() as created_at'),
                    DB::raw('NOW() as updated_at'),
                ])
        );
    }

    public function down(): void
    {
        $gb = DB::table('jurisdictions')->where('code', 'GB')->first();

        if (! $gb) {
            return;
        }

        DB::table('user_jurisdictions')
            ->where('jurisdiction_id', $gb->id)
            ->delete();
    }
};
