<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SA protection beneficiaries (WS 1.5b).
 *
 * Many-to-one with za_protection_policies. allocation_percentage sums
 * to 100.00 per policy (enforced at controller/request level, not DB).
 *
 * beneficiary_type per spec § 6.4:
 *   - estate: name null; policies payable here are dutiable under
 *     Estate Duty Act s3(3)(a)(ii)
 *   - spouse / nominated_individual / testamentary_trust / inter_vivos_trust
 *
 * id_number is SA 13-digit ID for nominated_individual only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('za_protection_beneficiaries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('policy_id')
                ->constrained('za_protection_policies')
                ->cascadeOnDelete();
            $t->enum('beneficiary_type', [
                'estate',
                'spouse',
                'nominated_individual',
                'testamentary_trust',
                'inter_vivos_trust',
            ]);
            $t->string('name', 200)->nullable();
            $t->string('relationship', 80)->nullable();
            $t->decimal('allocation_percentage', 5, 2);
            $t->string('id_number', 20)->nullable();
            $t->boolean('is_dutiable')->default(false);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('za_protection_beneficiaries');
    }
};
