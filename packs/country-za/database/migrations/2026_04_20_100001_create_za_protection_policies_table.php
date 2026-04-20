<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SA protection policies (WS 1.5b).
 *
 * One row per policy. The six product types (life, whole_of_life,
 * dread, idisability_lump, idisability_income, funeral) share this
 * table with a discriminator enum. SA-specific columns per spec § 6.4:
 *   - severity_tier: ASISA SCIDEP A/B/C/D, dread only
 *   - waiting_period_months + benefit_term_months: income protection only
 *   - group_scheme: flag for employer-held group cover
 *
 * Joint ownership follows the root CLAUDE.md rule 7 pattern:
 * single row, joint_owner_id + ownership_percentage on the primary.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_protection_policies', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('joint_owner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->decimal('ownership_percentage', 5, 2)->default(100);
            $t->enum('product_type', [
                'life',
                'whole_of_life',
                'dread',
                'idisability_lump',
                'idisability_income',
                'funeral',
            ]);
            $t->string('provider', 120);
            $t->string('policy_number', 60)->nullable();
            $t->bigInteger('cover_amount_minor');
            $t->bigInteger('premium_amount_minor');
            $t->enum('premium_frequency', ['monthly', 'quarterly', 'annual']);
            $t->date('start_date');
            $t->date('end_date')->nullable();
            $t->string('severity_tier', 1)->nullable();
            $t->unsignedInteger('waiting_period_months')->nullable();
            $t->unsignedInteger('benefit_term_months')->nullable();
            $t->boolean('group_scheme')->default(false);
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->softDeletes();

            $t->index(['user_id', 'product_type'], 'za_protection_user_type_idx');
            $t->index('joint_owner_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_protection_policies');
    }
};
