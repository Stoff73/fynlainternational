<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of SA exchange control transfers.
 *
 * KEYED BY CALENDAR YEAR (integer e.g. 2026), not tax year. SDA/FIA caps
 * reset on 1 January, which does NOT align with the SA tax year
 * (1 March – 28/29 February). Callers and queries must use integer
 * calendar_year throughout — this is the single biggest bug-source when
 * carrying code across from UK tax-year logic.
 *
 * allowance_type: 'sda' or 'fia' only in v1. Spec Appendix C mentions
 * foreign_inheritance and foreign_earnings as AIT-unlimited categories
 * that don't draw on SDA/FIA — those are deferred to WS 1.7 (emigration
 * life event) and would require an additive enum migration when added.
 *
 * recipient_account captures the offshore beneficiary account for audit
 * (e.g. "Investec Offshore USD Account ****7291").
 *
 * ait_documents is a nullable JSON column storing the document
 * checklist shape (e.g. `{it14sd: true, it77c: true,
 * tax_compliance_status_pin: 'TCS-2026-ABCDE'}`). Spec § 13.4 is v1
 * data capture only — no SARS eFiling integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_exchange_control_ledger', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedSmallInteger('calendar_year');
            $t->enum('allowance_type', ['sda', 'fia']);
            $t->bigInteger('amount_minor');
            $t->string('amount_ccy', 3)->default('ZAR');
            $t->string('destination_country', 2)->nullable();
            $t->string('purpose', 64)->nullable();
            $t->string('authorised_dealer', 128)->nullable();
            $t->string('recipient_account', 255)->nullable();
            $t->string('ait_reference', 64)->nullable();
            $t->json('ait_documents')->nullable();
            $t->date('transfer_date');
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'calendar_year', 'allowance_type'], 'za_excon_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_exchange_control_ledger');
    }
};
