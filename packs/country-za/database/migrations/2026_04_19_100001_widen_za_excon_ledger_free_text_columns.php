<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Widen free-text columns on za_exchange_control_ledger (WS 1.3c).
 *
 * Original WS 1.3b migration sized destination_country as varchar(2) (ISO
 * country code) and purpose as varchar(64). The WS 1.3c UI captures free
 * text for both: destination is a country NAME (e.g. "United Kingdom"),
 * purpose is up to 255 chars ("Offshore investment portfolio diversification").
 *
 * Widening is forward-compatible — all existing 2-char rows fit the new sizes.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('za_exchange_control_ledger')) {
            return;
        }

        Schema::table('za_exchange_control_ledger', function (Blueprint $table) {
            $table->string('destination_country', 120)->nullable()->change();
            $table->string('purpose', 255)->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('za_exchange_control_ledger')) {
            return;
        }

        Schema::table('za_exchange_control_ledger', function (Blueprint $table) {
            $table->string('destination_country', 2)->nullable()->change();
            $table->string('purpose', 64)->nullable()->change();
        });
    }
};
