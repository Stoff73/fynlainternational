<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only ledger of TFSA contribution events.
 *
 * Rows are keyed EITHER by (user_id, null beneficiary_id) for an adult's
 * own TFSA, OR by (user_id, beneficiary_id) for a minor TFSA where
 * beneficiary_id points to the family_members row for the child. The
 * child's R46k/R500k allowance is tracked independently from the parent's
 * own allowance.
 *
 * amount_minor uses signed bigInteger to match the WS 0.6 shadow-column
 * migration pattern; amount_ccy pairs with it (always 'ZAR' for ZA rows,
 * kept for cross-border aggregation consistency).
 *
 * source_type distinguishes original deposits from provider-to-provider
 * transfers. Both count toward the annual cap (SARS rule) — the column
 * is audit metadata, not business logic.
 *
 * Canonical SA savings_accounts.account_type values consumed alongside
 * this ledger:
 *   'tfsa', 'transactional', 'notice_7', 'notice_32', 'notice_90',
 *   'fixed_deposit', 'money_market', 'rsa_retail_bond', 'endowment'
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_tfsa_contributions', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('beneficiary_id')->nullable()
                ->constrained('family_members')->nullOnDelete();
            $t->foreignId('savings_account_id')->nullable()
                ->constrained('savings_accounts')->nullOnDelete();
            $t->string('tax_year', 10)->index();
            $t->bigInteger('amount_minor');
            $t->string('amount_ccy', 3)->default('ZAR');
            $t->enum('source_type', ['contribution', 'transfer_in'])
                ->default('contribution');
            $t->date('contribution_date');
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->index(['user_id', 'beneficiary_id', 'tax_year'], 'za_tfsa_cap_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_tfsa_contributions');
    }
};
