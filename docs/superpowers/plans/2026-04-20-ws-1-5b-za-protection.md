# WS 1.5b — SA Protection Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the SA Protection module (6 policy types, coverage-gap analysis, beneficiaries with estate-duty awareness + `is_dutiable` forward-compat for WS 1.6) at `/za/protection`, full-stack, plus retire tech-debt W2 (`toMinorZAR` reuse) across prior ZA workstreams.

**Status:** Amended 2026-04-20 after PRD audit. W1 (DialogContainer refactor) extracted to a separate follow-up PR to reduce regression surface on shipped WS 1.2b/1.3c/1.4d code. See PRD-ws-1-5b-za-protection-frontend.md for the audit findings and resolutions.

**Architecture:** Single-page dashboard with 3 tabs. Fresh ZA-native pack tables (`za_protection_policies` + `za_protection_beneficiaries`). Controller in `app/Http/Controllers/Api/Za/` delegates to the existing `ZaProtectionEngine` in the pack; aggregate coverage-gap method added. Frontend mirrors WS 1.4d `/za/retirement` shape: one Vue view, 3 tabs, Vuex module, axios service, one sidebar entry.

**Tech Stack:** Laravel 10 (PHP 8.2, Pest, Sanctum, Eloquent), Vue 3 + Vuex + Vue Router, Tailwind (design-system palette tokens), Capacitor iOS. Follows `fynlaDesignGuide.md` v1.4.0.

**Source spec:** `docs/superpowers/specs/2026-04-20-ws-1-5b-za-protection-design.md` (committed `620862c`).

---

## File structure

**New files (~34):**

| Layer | Path | Purpose |
|---|---|---|
| Pack migration | `packs/country-za/database/migrations/2026_04_20_100001_create_za_protection_policies_table.php` | Table 1 |
| Pack migration | `packs/country-za/database/migrations/2026_04_20_100002_create_za_protection_beneficiaries_table.php` | Table 2 |
| Pack model | `packs/country-za/src/Models/ZaProtectionPolicy.php` | Eloquent policy |
| Pack model | `packs/country-za/src/Models/ZaProtectionBeneficiary.php` | Eloquent beneficiary |
| Pack factory | `packs/country-za/database/factories/ZaProtectionPolicyFactory.php` | Test factory |
| Pack factory | `packs/country-za/database/factories/ZaProtectionBeneficiaryFactory.php` | Test factory |
| Pack engine extension | `packs/country-za/src/Protection/ZaProtectionEngine.php` *(edit)* | Add `calculateAggregateCoverageGap()` |
| Controller | `app/Http/Controllers/Api/Za/ZaProtectionController.php` | HTTP adapter |
| Form requests | `app/Http/Requests/Za/Protection/StoreZaProtectionPolicyRequest.php` | Create |
| | `app/Http/Requests/Za/Protection/UpdateZaProtectionPolicyRequest.php` | Update |
| | `app/Http/Requests/Za/Protection/StoreZaBeneficiariesRequest.php` | Replace beneficiaries |
| | `app/Http/Requests/Za/Protection/CoverageGapRequest.php` | Coverage-gap request |
| Resources | `app/Http/Resources/Za/Protection/ZaProtectionPolicyResource.php` | Policy output |
| | `app/Http/Resources/Za/Protection/ZaProtectionBeneficiaryResource.php` | Beneficiary output |
| | `app/Http/Resources/Za/Protection/ZaCoverageGapResource.php` | Gap analysis output |
| Routes | `routes/api.php` *(edit)* | Add `/za/protection/*` prefix group |
| Shared component | `resources/js/components/Common/DialogContainer.vue` | Modal a11y wrapper (tech-debt W1) |
| Frontend view | `resources/js/views/ZA/ZaProtectionDashboard.vue` | Page shell with 3 tabs |
| Protection tab 1 | `resources/js/components/ZA/Protection/ZaPoliciesTable.vue` | Grouped policies list |
| | `resources/js/components/ZA/Protection/ZaProtectionPolicyForm.vue` | Unified form (conditional fields) |
| | `resources/js/components/ZA/Protection/ZaProtectionPolicyModal.vue` | Modal wrapper |
| | `resources/js/components/ZA/Protection/ZaPolicyDetailCard.vue` | Read-only detail |
| | `resources/js/components/ZA/Protection/ZaPolicyTypeSelector.vue` | 6-option selector |
| Protection tab 2 | `resources/js/components/ZA/Protection/ZaCoverageGapDashboard.vue` | 4-gauge grid |
| | `resources/js/components/ZA/Protection/ZaCoverageGaugeCard.vue` | Single gauge |
| | `resources/js/components/ZA/Protection/ZaCoverageRationalePanel.vue` | "Why this number?" |
| | `resources/js/components/ZA/Protection/ZaMissingInputsEmptyState.vue` | Empty state + deep links |
| Protection tab 3 | `resources/js/components/ZA/Protection/ZaBeneficiariesTab.vue` | All-policies table |
| | `resources/js/components/ZA/Protection/ZaBeneficiaryEditor.vue` | Inline editor |
| Vuex module | `resources/js/store/modules/zaProtection.js` *(replace placeholder)* | Module state/actions |
| Service | `resources/js/services/zaProtectionService.js` | Axios wrapper |
| Router + sidebar | `resources/js/router/index.js` *(edit)* | Add `/za/protection` route |
| | `resources/js/store/modules/jurisdiction.js` *(edit)* | Append sidebar entry |
| Tests | `tests/Feature/Api/Za/ZaProtectionControllerTest.php` | ~18 tests |
| | `tests/Feature/Api/Za/ZaProtectionBeneficiaryTest.php` | ~6 tests |
| | `packs/country-za/tests/Unit/ZaProtectionEngineAggregateGapTest.php` | ~4 tests |
| | `tests/Integration/ZaProtectionWorkflowTest.php` | ~2 tests |

**Edits to prior-WS modals (tech-debt W1):**
- `resources/js/components/ZA/Savings/ZaContributionModal.vue`
- `resources/js/components/ZA/Investment/ZaInvestmentForm.vue` (modal mode)
- `resources/js/components/ZA/Retirement/ZaContributionModal.vue`
- `resources/js/components/ZA/Retirement/ZaRetirementFundForm.vue`

**Edits to prior-WS components (tech-debt W2 — `Math.round*100` → `toMinorZAR`):**
- WS 1.4d components listed in `tech-debt-report.md` W2 (9 files, 15 sites): `ZaCompulsoryAnnuitisationCard.vue`, `ZaSavingsPotWithdrawalCard.vue`, `ZaSection11fReliefCalculator.vue`, `ZaContributionModal.vue`, `ZaRetirementFundForm.vue`, `ZaLivingAnnuitySlider.vue`, `ZaLifeAnnuityQuote.vue`, `ZaReg28AllocationForm.vue`, plus the one in `ZaContributionModal` for Retirement.

---

## Task 1: Pack migration — `za_protection_policies`

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_20_100001_create_za_protection_policies_table.php`

- [ ] **Step 1: Write migration**

```php
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
```

- [ ] **Step 2: Run migration**

Run: `php artisan migrate`
Expected: `2026_04_20_100001_create_za_protection_policies_table ........ DONE`

- [ ] **Step 3: Verify schema**

Run: `php artisan tinker --execute="echo json_encode(\DB::select('DESCRIBE za_protection_policies'));"`
Expected: table exists with columns `id, user_id, joint_owner_id, ownership_percentage, product_type, provider, policy_number, cover_amount_minor, premium_amount_minor, premium_frequency, start_date, end_date, severity_tier, waiting_period_months, benefit_term_months, group_scheme, notes, created_at, updated_at, deleted_at`.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_20_100001_create_za_protection_policies_table.php
git commit -m "feat(za-protection): create za_protection_policies table (WS 1.5b)"
```

---

## Task 2: Pack migration — `za_protection_beneficiaries`

**Files:**
- Create: `packs/country-za/database/migrations/2026_04_20_100002_create_za_protection_beneficiaries_table.php`

- [ ] **Step 1: Write migration**

```php
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
```

- [ ] **Step 2: Run migration + verify**

Run: `php artisan migrate`
Expected: table `za_protection_beneficiaries` created with FK to `za_protection_policies`.

Run: `php artisan tinker --execute="echo json_encode(\DB::select('DESCRIBE za_protection_beneficiaries'));"`
Expected: 9 columns with `policy_id` foreign key.

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/database/migrations/2026_04_20_100002_create_za_protection_beneficiaries_table.php
git commit -m "feat(za-protection): create za_protection_beneficiaries table (WS 1.5b)"
```

---

## Task 3: Pack model — `ZaProtectionPolicy`

**Files:**
- Create: `packs/country-za/src/Models/ZaProtectionPolicy.php`

- [ ] **Step 1: Write the model**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * ZA protection policy (WS 1.5b).
 *
 * Pack-owned model; cross-namespace FK targets (users) resolved via
 * runtime FQCN to keep the pack free of compile-time main-app imports.
 *
 * product_type discriminates across six SA product categories per
 * ZaProtectionEngine::getAvailablePolicyTypes().
 */
class ZaProtectionPolicy extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'za_protection_policies';

    protected $fillable = [
        'user_id',
        'joint_owner_id',
        'ownership_percentage',
        'product_type',
        'provider',
        'policy_number',
        'cover_amount_minor',
        'premium_amount_minor',
        'premium_frequency',
        'start_date',
        'end_date',
        'severity_tier',
        'waiting_period_months',
        'benefit_term_months',
        'group_scheme',
        'notes',
    ];

    protected $casts = [
        'ownership_percentage' => 'decimal:2',
        'cover_amount_minor' => 'integer',
        'premium_amount_minor' => 'integer',
        'start_date' => 'date',
        'end_date' => 'date',
        'waiting_period_months' => 'integer',
        'benefit_term_months' => 'integer',
        'group_scheme' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'user_id');
    }

    public function jointOwner(): BelongsTo
    {
        return $this->belongsTo(self::resolveAppModel('User'), 'joint_owner_id');
    }

    public function beneficiaries(): HasMany
    {
        return $this->hasMany(ZaProtectionBeneficiary::class, 'policy_id');
    }

    protected static function newFactory()
    {
        return \Fynla\Packs\Za\Database\Factories\ZaProtectionPolicyFactory::new();
    }

    private static function resolveAppModel(string $short): string
    {
        return '\\' . 'App' . '\\Models\\' . $short;
    }
}
```

- [ ] **Step 2: Verify model is loadable**

Run: `php artisan tinker --execute="echo get_class(new \Fynla\Packs\Za\Models\ZaProtectionPolicy);"`
Expected: `Fynla\Packs\Za\Models\ZaProtectionPolicy`

- [ ] **Step 3: Commit**

```bash
git add packs/country-za/src/Models/ZaProtectionPolicy.php
git commit -m "feat(za-protection): ZaProtectionPolicy Eloquent model (WS 1.5b)"
```

---

## Task 4: Pack model — `ZaProtectionBeneficiary`

**Files:**
- Create: `packs/country-za/src/Models/ZaProtectionBeneficiary.php`

- [ ] **Step 1: Write the model**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Beneficiary nomination for a SA protection policy (WS 1.5b).
 *
 * The sum of allocation_percentage across all rows for a given policy
 * must equal 100.00 — enforced at the application layer
 * (StoreZaBeneficiariesRequest + controller transaction).
 */
class ZaProtectionBeneficiary extends Model
{
    use HasFactory;

    protected $table = 'za_protection_beneficiaries';

    protected $fillable = [
        'policy_id',
        'beneficiary_type',
        'name',
        'relationship',
        'allocation_percentage',
        'id_number',
        'is_dutiable',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'is_dutiable' => 'boolean',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(ZaProtectionPolicy::class, 'policy_id');
    }

    /**
     * Auto-set is_dutiable when beneficiary_type is assigned. Payable-to-estate
     * policies are dutiable under Estate Duty Act s3(3)(a)(ii). WS 1.6 Estate
     * will consume this flag.
     */
    public function setBeneficiaryTypeAttribute(string $value): void
    {
        $this->attributes['beneficiary_type'] = $value;
        $this->attributes['is_dutiable'] = ($value === 'estate');
    }

    protected static function newFactory()
    {
        return \Fynla\Packs\Za\Database\Factories\ZaProtectionBeneficiaryFactory::new();
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add packs/country-za/src/Models/ZaProtectionBeneficiary.php
git commit -m "feat(za-protection): ZaProtectionBeneficiary Eloquent model (WS 1.5b)"
```

---

## Task 5: Pack factories for test seeding

**Files:**
- Create: `packs/country-za/database/factories/ZaProtectionPolicyFactory.php`
- Create: `packs/country-za/database/factories/ZaProtectionBeneficiaryFactory.php`

- [ ] **Step 1: Policy factory**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Database\Factories;

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZaProtectionPolicyFactory extends Factory
{
    protected $model = ZaProtectionPolicy::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'joint_owner_id' => null,
            'ownership_percentage' => 100,
            'product_type' => 'life',
            'provider' => $this->faker->randomElement(['Discovery Life', 'Liberty', 'Old Mutual', 'Sanlam', 'Momentum']),
            'policy_number' => strtoupper($this->faker->bothify('??######')),
            'cover_amount_minor' => $this->faker->numberBetween(500_000_00, 10_000_000_00),
            'premium_amount_minor' => $this->faker->numberBetween(500_00, 5_000_00),
            'premium_frequency' => 'monthly',
            'start_date' => $this->faker->dateTimeBetween('-5 years', 'now')->format('Y-m-d'),
            'end_date' => null,
            'severity_tier' => null,
            'waiting_period_months' => null,
            'benefit_term_months' => null,
            'group_scheme' => false,
            'notes' => null,
        ];
    }

    public function life(): static
    {
        return $this->state(['product_type' => 'life']);
    }

    public function dread(): static
    {
        return $this->state([
            'product_type' => 'dread',
            'severity_tier' => 'B',
        ]);
    }

    public function incomeProtection(): static
    {
        return $this->state([
            'product_type' => 'idisability_income',
            'waiting_period_months' => 3,
            'benefit_term_months' => 240, // to age 65 rough proxy
        ]);
    }

    public function funeral(): static
    {
        return $this->state([
            'product_type' => 'funeral',
            'cover_amount_minor' => 30_000_00,
            'premium_amount_minor' => 150_00,
        ]);
    }
}
```

- [ ] **Step 2: Beneficiary factory**

```php
<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Database\Factories;

use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

class ZaProtectionBeneficiaryFactory extends Factory
{
    protected $model = ZaProtectionBeneficiary::class;

    public function definition(): array
    {
        return [
            'policy_id' => ZaProtectionPolicy::factory(),
            'beneficiary_type' => 'spouse',
            'name' => $this->faker->name(),
            'relationship' => 'spouse',
            'allocation_percentage' => 100,
            'id_number' => null,
        ];
    }

    public function estate(): static
    {
        return $this->state([
            'beneficiary_type' => 'estate',
            'name' => null,
            'relationship' => null,
            'id_number' => null,
        ]);
    }

    public function nominated(): static
    {
        return $this->state([
            'beneficiary_type' => 'nominated_individual',
            'id_number' => $this->faker->numerify('#############'),
        ]);
    }
}
```

- [ ] **Step 3: Verify factories resolve + smoke-test**

Run: `php artisan tinker --execute="\$p = \Fynla\Packs\Za\Models\ZaProtectionPolicy::factory()->make(); echo json_encode(\$p->only(['product_type','provider','cover_amount_minor']));"`
Expected: JSON with `product_type=life`, non-empty `provider`, integer `cover_amount_minor`.

- [ ] **Step 4: Commit**

```bash
git add packs/country-za/database/factories/ZaProtectionPolicyFactory.php packs/country-za/database/factories/ZaProtectionBeneficiaryFactory.php
git commit -m "feat(za-protection): factories for policy + beneficiary (WS 1.5b)"
```

---

## Task 6: Extend `ZaProtectionEngine` with aggregate coverage-gap method

**Files:**
- Modify: `packs/country-za/src/Protection/ZaProtectionEngine.php`
- Test: `packs/country-za/tests/Unit/ZaProtectionEngineAggregateGapTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use Fynla\Packs\Za\Protection\ZaProtectionEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;

beforeEach(function () {
    $this->engine = new ZaProtectionEngine(app(ZaTaxConfigService::class));
});

it('aggregates coverage gap across the four primary categories with zero policies', function () {
    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: [],
        userContext: ['annual_income' => 480_000, 'outstanding_debts' => 800_000, 'dependants' => 2],
    );

    expect($result)->toHaveKeys(['life', 'idisability_income', 'dread', 'funeral']);
    expect($result['life']['existing_cover'])->toBe(0);
    expect($result['life']['shortfall'])->toBeGreaterThan(0);
    expect($result['funeral']['recommended_cover'])->toBe(3 * 3_000_000); // 3 lives × R30k in minor units
});

it('sums existing cover of the same product type across multiple policies', function () {
    $policies = [
        ['product_type' => 'life', 'cover_amount_minor' => 2_000_000_00],
        ['product_type' => 'life', 'cover_amount_minor' => 3_000_000_00],
        ['product_type' => 'dread', 'cover_amount_minor' => 500_000_00],
    ];

    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: $policies,
        userContext: ['annual_income' => 480_000, 'outstanding_debts' => 800_000, 'dependants' => 2],
    );

    expect($result['life']['existing_cover'])->toBe(5_000_000_00); // sum of both life policies
    expect($result['dread']['existing_cover'])->toBe(500_000_00);
});

it('flags missing_inputs when annual_income is zero or missing', function () {
    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: [],
        userContext: ['annual_income' => 0, 'outstanding_debts' => 0, 'dependants' => 0],
    );

    expect($result['life']['missing_inputs'])->toContain('annual_income');
    expect($result['idisability_income']['missing_inputs'])->toContain('annual_income');
});

it('treats idisability_lump as sharing the dread calculation shape', function () {
    $policies = [
        ['product_type' => 'idisability_lump', 'cover_amount_minor' => 1_000_000_00],
    ];

    $result = $this->engine->calculateAggregateCoverageGap(
        userPolicies: $policies,
        userContext: ['annual_income' => 480_000, 'outstanding_debts' => 0, 'dependants' => 0],
    );

    // idisability_lump cover rolls into dread category per engine convention
    expect($result['dread']['existing_cover'])->toBe(1_000_000_00);
});
```

- [ ] **Step 2: Run and confirm failure**

Run: `./vendor/bin/pest packs/country-za/tests/Unit/ZaProtectionEngineAggregateGapTest.php`
Expected: FAIL with "Call to undefined method … calculateAggregateCoverageGap".

- [ ] **Step 3: Add the method to `ZaProtectionEngine`**

Append to `packs/country-za/src/Protection/ZaProtectionEngine.php` (before the final `}`):

```php
    /**
     * Aggregate coverage-gap analysis across the four primary categories
     * (life, idisability_income, dread, funeral) for a user.
     *
     * idisability_lump rolls into the dread bucket (same calculation
     * shape, both return lump-sum needs).
     *
     * If required inputs are missing or zero, the corresponding category
     * returns a `missing_inputs` array listing what's needed. Other
     * categories still compute.
     *
     * @param  array<int, array{product_type: string, cover_amount_minor: int}>  $userPolicies
     * @param  array{annual_income: int, outstanding_debts: int, dependants: int}  $userContext
     * @return array<string, array{recommended_cover: int, minimum_cover: int, existing_cover: int, shortfall: int, rationale: string, missing_inputs: array<int,string>}>
     */
    public function calculateAggregateCoverageGap(array $userPolicies, array $userContext): array
    {
        $income = (int) ($userContext['annual_income'] ?? 0);
        $debts = (int) ($userContext['outstanding_debts'] ?? 0);
        $dependants = (int) ($userContext['dependants'] ?? 0);

        $sumByType = [
            'life' => 0,
            'whole_of_life' => 0,
            'dread' => 0,
            'idisability_lump' => 0,
            'idisability_income' => 0,
            'funeral' => 0,
        ];
        foreach ($userPolicies as $p) {
            $type = $p['product_type'] ?? '';
            if (isset($sumByType[$type])) {
                $sumByType[$type] += (int) ($p['cover_amount_minor'] ?? 0);
            }
        }

        $lifeExisting = $sumByType['life'] + $sumByType['whole_of_life'];
        $dreadExisting = $sumByType['dread'] + $sumByType['idisability_lump'];
        $incomeProtectionExisting = $sumByType['idisability_income'];
        $funeralExisting = $sumByType['funeral'];

        return [
            'life' => $this->wrapGap(
                $income > 0
                    ? $this->calculateCoverageNeeds([
                        'policy_type' => 'life',
                        'annual_income' => $income,
                        'outstanding_debts' => $debts,
                        'dependants' => $dependants,
                        'existing_coverage' => $lifeExisting,
                    ])
                    : null,
                $lifeExisting,
                missing: $income > 0 ? [] : ['annual_income'],
            ),
            'idisability_income' => $this->wrapGap(
                $income > 0
                    ? $this->calculateCoverageNeeds([
                        'policy_type' => 'idisability_income',
                        'annual_income' => $income,
                        'existing_coverage' => $incomeProtectionExisting,
                    ])
                    : null,
                $incomeProtectionExisting,
                missing: $income > 0 ? [] : ['annual_income'],
            ),
            'dread' => $this->wrapGap(
                $income > 0
                    ? $this->calculateCoverageNeeds([
                        'policy_type' => 'dread',
                        'annual_income' => $income,
                        'existing_coverage' => $dreadExisting,
                    ])
                    : null,
                $dreadExisting,
                missing: $income > 0 ? [] : ['annual_income'],
            ),
            'funeral' => $this->wrapGap(
                $this->calculateCoverageNeeds([
                    'policy_type' => 'funeral',
                    'dependants' => $dependants,
                    'existing_coverage' => $funeralExisting,
                ]),
                $funeralExisting,
                missing: [],
            ),
        ];
    }

    /**
     * Normalise a calculator result or missing-inputs state into the
     * shape consumers expect, including `existing_cover` and
     * `missing_inputs`.
     *
     * @param  array{recommended_cover: int, minimum_cover: int, shortfall: int, rationale: string}|null  $needs
     * @param  array<int,string>  $missing
     * @return array{recommended_cover: int, minimum_cover: int, existing_cover: int, shortfall: int, rationale: string, missing_inputs: array<int,string>}
     */
    private function wrapGap(?array $needs, int $existing, array $missing): array
    {
        if ($needs === null) {
            return [
                'recommended_cover' => 0,
                'minimum_cover' => 0,
                'existing_cover' => $existing,
                'shortfall' => 0,
                'rationale' => 'Required inputs missing. Complete the prompted module to compute this gap.',
                'missing_inputs' => $missing,
            ];
        }

        return [
            'recommended_cover' => $needs['recommended_cover'],
            'minimum_cover' => $needs['minimum_cover'],
            'existing_cover' => $existing,
            'shortfall' => $needs['shortfall'],
            'rationale' => $needs['rationale'],
            'missing_inputs' => $missing,
        ];
    }
```

- [ ] **Step 4: Run test again — confirm pass**

Run: `./vendor/bin/pest packs/country-za/tests/Unit/ZaProtectionEngineAggregateGapTest.php`
Expected: 4 passing.

- [ ] **Step 5: Run full pack test suite to catch regressions**

Run: `./vendor/bin/pest packs/country-za/`
Expected: all pack tests pass (existing `ZaProtectionEngineTest` etc.).

- [ ] **Step 6: Commit**

```bash
git add packs/country-za/src/Protection/ZaProtectionEngine.php packs/country-za/tests/Unit/ZaProtectionEngineAggregateGapTest.php
git commit -m "feat(za-protection): aggregate coverage-gap method + unit tests (WS 1.5b)"
```

---

## Task 7: Form requests

**Files:**
- Create: `app/Http/Requests/Za/Protection/StoreZaProtectionPolicyRequest.php`
- Create: `app/Http/Requests/Za/Protection/UpdateZaProtectionPolicyRequest.php`
- Create: `app/Http/Requests/Za/Protection/StoreZaBeneficiariesRequest.php`
- Create: `app/Http/Requests/Za/Protection/CoverageGapRequest.php`

- [ ] **Step 1: StoreZaProtectionPolicyRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreZaProtectionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_type' => ['required', Rule::in([
                'life', 'whole_of_life', 'dread',
                'idisability_lump', 'idisability_income', 'funeral',
            ])],
            'provider' => 'required|string|max:120',
            'policy_number' => 'nullable|string|max:60',
            'cover_amount_minor' => 'required|integer|min:0|max:999999999999',
            'premium_amount_minor' => 'required|integer|min:0|max:9999999999',
            'premium_frequency' => ['required', Rule::in(['monthly', 'quarterly', 'annual'])],
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'severity_tier' => ['nullable', Rule::in(['A', 'B', 'C', 'D']), 'required_if:product_type,dread'],
            'waiting_period_months' => [
                'nullable', 'integer', 'min:0', 'max:60',
                'required_if:product_type,idisability_income',
            ],
            'benefit_term_months' => [
                'nullable', 'integer', 'min:0', 'max:600',
                'required_if:product_type,idisability_income',
            ],
            'group_scheme' => 'sometimes|boolean',
            'notes' => 'nullable|string|max:2000',
            'joint_owner_id' => 'nullable|exists:users,id',
            'ownership_percentage' => 'nullable|numeric|min:0.01|max:100',
            'beneficiaries' => 'sometimes|array',
            'beneficiaries.*.beneficiary_type' => ['required_with:beneficiaries', Rule::in([
                'estate', 'spouse', 'nominated_individual',
                'testamentary_trust', 'inter_vivos_trust',
            ])],
            'beneficiaries.*.name' => 'nullable|string|max:200',
            'beneficiaries.*.relationship' => 'nullable|string|max:80',
            'beneficiaries.*.allocation_percentage' => 'required_with:beneficiaries|numeric|min:0.01|max:100',
            'beneficiaries.*.id_number' => 'nullable|string|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'severity_tier.required_if' => 'A severity tier (A/B/C/D) is required for dread disease policies.',
            'waiting_period_months.required_if' => 'Waiting period is required for income protection policies.',
            'benefit_term_months.required_if' => 'Benefit term is required for income protection policies.',
        ];
    }
}
```

- [ ] **Step 2: UpdateZaProtectionPolicyRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateZaProtectionPolicyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provider' => 'sometimes|string|max:120',
            'policy_number' => 'sometimes|nullable|string|max:60',
            'cover_amount_minor' => 'sometimes|integer|min:0|max:999999999999',
            'premium_amount_minor' => 'sometimes|integer|min:0|max:9999999999',
            'premium_frequency' => ['sometimes', Rule::in(['monthly', 'quarterly', 'annual'])],
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|nullable|date|after_or_equal:start_date',
            'severity_tier' => ['sometimes', 'nullable', Rule::in(['A', 'B', 'C', 'D'])],
            'waiting_period_months' => 'sometimes|nullable|integer|min:0|max:60',
            'benefit_term_months' => 'sometimes|nullable|integer|min:0|max:600',
            'group_scheme' => 'sometimes|boolean',
            'notes' => 'sometimes|nullable|string|max:2000',
            'joint_owner_id' => 'sometimes|nullable|exists:users,id',
            'ownership_percentage' => 'sometimes|numeric|min:0.01|max:100',
            // product_type intentionally immutable — changing type should be a new policy
        ];
    }
}
```

- [ ] **Step 3: StoreZaBeneficiariesRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreZaBeneficiariesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'beneficiaries' => 'required|array|min:1|max:20',
            'beneficiaries.*.beneficiary_type' => ['required', Rule::in([
                'estate', 'spouse', 'nominated_individual',
                'testamentary_trust', 'inter_vivos_trust',
            ])],
            'beneficiaries.*.name' => 'nullable|string|max:200',
            'beneficiaries.*.relationship' => 'nullable|string|max:80',
            'beneficiaries.*.allocation_percentage' => 'required|numeric|min:0.01|max:100',
            'beneficiaries.*.id_number' => 'nullable|string|max:20',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $sum = 0.0;
            foreach ((array) $this->input('beneficiaries', []) as $b) {
                $sum += (float) ($b['allocation_percentage'] ?? 0);
            }
            // Allow 0.01 tolerance for floating-point drift
            if (abs($sum - 100.0) > 0.01) {
                $v->errors()->add('beneficiaries', sprintf(
                    'Beneficiary allocation_percentage must sum to 100 (got %.2f).',
                    $sum,
                ));
            }
            foreach ((array) $this->input('beneficiaries', []) as $i => $b) {
                if (($b['beneficiary_type'] ?? null) === 'nominated_individual' && empty($b['name'])) {
                    $v->errors()->add("beneficiaries.$i.name", 'Name is required for a nominated individual beneficiary.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'beneficiaries.required' => 'At least one beneficiary is required.',
            'beneficiaries.min' => 'At least one beneficiary is required.',
        ];
    }
}
```

- [ ] **Step 4: CoverageGapRequest**

```php
<?php

declare(strict_types=1);

namespace App\Http\Requests\Za\Protection;

use Illuminate\Foundation\Http\FormRequest;

class CoverageGapRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // No request body today. Reserved for future query params like
        // ?recalculate_assumptions=true. Kept as a separate class so
        // API-contract changes are cleanly scoped.
        return [];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/Za/Protection/
git commit -m "feat(za-protection): form requests (store/update/beneficiaries/coverage-gap) (WS 1.5b)"
```

---

## Task 8: API resources

**Files:**
- Create: `app/Http/Resources/Za/Protection/ZaProtectionPolicyResource.php`
- Create: `app/Http/Resources/Za/Protection/ZaProtectionBeneficiaryResource.php`
- Create: `app/Http/Resources/Za/Protection/ZaCoverageGapResource.php`

- [ ] **Step 1: ZaProtectionPolicyResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Fynla\Packs\Za\Models\ZaProtectionPolicy
 */
class ZaProtectionPolicyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'joint_owner_id' => $this->joint_owner_id,
            'ownership_percentage' => (float) $this->ownership_percentage,
            'product_type' => $this->product_type,
            'provider' => $this->provider,
            'policy_number' => $this->policy_number,
            'cover_amount_minor' => (int) $this->cover_amount_minor,
            'cover_amount_major' => round($this->cover_amount_minor / 100, 2),
            'premium_amount_minor' => (int) $this->premium_amount_minor,
            'premium_amount_major' => round($this->premium_amount_minor / 100, 2),
            'premium_frequency' => $this->premium_frequency,
            'start_date' => $this->start_date?->toIso8601String(),
            'end_date' => $this->end_date?->toIso8601String(),
            'severity_tier' => $this->severity_tier,
            'waiting_period_months' => $this->waiting_period_months,
            'benefit_term_months' => $this->benefit_term_months,
            'group_scheme' => (bool) $this->group_scheme,
            'notes' => $this->notes,
            'beneficiaries' => ZaProtectionBeneficiaryResource::collection($this->whenLoaded('beneficiaries')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 2: ZaProtectionBeneficiaryResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \Fynla\Packs\Za\Models\ZaProtectionBeneficiary
 */
class ZaProtectionBeneficiaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'policy_id' => $this->policy_id,
            'beneficiary_type' => $this->beneficiary_type,
            'name' => $this->name,
            'relationship' => $this->relationship,
            'allocation_percentage' => (float) $this->allocation_percentage,
            'id_number' => $this->id_number,
            'is_dutiable' => (bool) $this->is_dutiable,
        ];
    }
}
```

- [ ] **Step 3: ZaCoverageGapResource**

```php
<?php

declare(strict_types=1);

namespace App\Http\Resources\Za\Protection;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Transforms the engine's 4-category coverage-gap payload for the API.
 * Wrapped around the full array returned by calculateAggregateCoverageGap().
 * Uses the standard JsonResource constructor (resource = mixed) — no
 * custom constructor signature, consistent with WS 1.4d resources.
 */
class ZaCoverageGapResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var array<string, array{recommended_cover: int, minimum_cover: int, existing_cover: int, shortfall: int, rationale: string, missing_inputs: array<int,string>}> $payload */
        $payload = $this->resource;

        return collect($payload)
            ->map(fn (array $cat, string $key) => [
                'category' => $key,
                'recommended_cover_minor' => $cat['recommended_cover'],
                'recommended_cover_major' => round($cat['recommended_cover'] / 100, 2),
                'minimum_cover_minor' => $cat['minimum_cover'],
                'minimum_cover_major' => round($cat['minimum_cover'] / 100, 2),
                'existing_cover_minor' => $cat['existing_cover'],
                'existing_cover_major' => round($cat['existing_cover'] / 100, 2),
                'shortfall_minor' => $cat['shortfall'],
                'shortfall_major' => round($cat['shortfall'] / 100, 2),
                'rationale' => $cat['rationale'],
                'missing_inputs' => $cat['missing_inputs'],
            ])
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Commit**

```bash
git add app/Http/Resources/Za/Protection/
git commit -m "feat(za-protection): API resources (policy/beneficiary/coverage-gap) (WS 1.5b)"
```

---

## Task 9: Write failing controller feature tests (TDD)

**Files:**
- Create: `tests/Feature/Api/Za/ZaProtectionControllerTest.php`

- [ ] **Step 1: Write the test file**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Mirrors WS 1.4d ZaRetirementControllerTest pattern.
    // pack.enabled:za middleware reads FYNLA_ACTIVE_PACKS env var,
    // NOT user_jurisdictions rows. ZaTaxConfigurationSeeder internally
    // chains ZaJurisdictionSeeder, so no separate call needed.
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('lists protection policies for the authenticated user', function () {
    ZaProtectionPolicy::factory()->for($this->user)->count(3)->create();
    ZaProtectionPolicy::factory()->count(2)->create(); // other users' policies

    $response = $this->getJson('/api/za/protection/policies');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data'))->toHaveCount(3);
});

it('creates a life policy with a spouse beneficiary', function () {
    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'life',
        'provider' => 'Discovery Life',
        'cover_amount_minor' => 5_000_000_00,
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
        'beneficiaries' => [
            ['beneficiary_type' => 'spouse', 'name' => 'Test Spouse', 'allocation_percentage' => 100],
        ],
    ]);

    $response->assertCreated()->assertJsonPath('success', true);
    expect(ZaProtectionPolicy::count())->toBe(1);
    expect(ZaProtectionBeneficiary::count())->toBe(1);
});

it('rejects a dread policy without severity_tier', function () {
    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'dread',
        'provider' => 'Liberty',
        'cover_amount_minor' => 1_000_000_00,
        'premium_amount_minor' => 800_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['severity_tier']);
});

it('requires waiting_period_months and benefit_term_months for idisability_income', function () {
    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'idisability_income',
        'provider' => 'Momentum',
        'cover_amount_minor' => 30_000_00,
        'premium_amount_minor' => 400_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['waiting_period_months', 'benefit_term_months']);
});

it('shows a policy belonging to the user', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();

    $response = $this->getJson("/api/za/protection/policies/{$policy->id}");

    $response->assertOk()->assertJsonPath('data.id', $policy->id);
});

it('returns 404 for a policy belonging to another user', function () {
    $policy = ZaProtectionPolicy::factory()->create();

    $response = $this->getJson("/api/za/protection/policies/{$policy->id}");

    $response->assertStatus(404);
});

it('updates a policy', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'cover_amount_minor' => 1_000_000_00,
    ]);

    $response = $this->putJson("/api/za/protection/policies/{$policy->id}", [
        'cover_amount_minor' => 6_000_000_00,
    ]);

    $response->assertOk();
    expect($policy->fresh()->cover_amount_minor)->toBe(6_000_000_00);
});

it('soft-deletes a policy', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();

    $response = $this->deleteJson("/api/za/protection/policies/{$policy->id}");

    $response->assertOk();
    expect(ZaProtectionPolicy::find($policy->id))->toBeNull();
    expect(ZaProtectionPolicy::withTrashed()->find($policy->id))->not->toBeNull();
});

it('passes through policy types from the engine', function () {
    $response = $this->getJson('/api/za/protection/policy-types');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(6);
    expect(collect($response->json('data'))->pluck('code')->all())->toContain(
        'life', 'whole_of_life', 'dread', 'idisability_lump', 'idisability_income', 'funeral',
    );
});

it('passes through tax-treatment for a policy type', function () {
    $response = $this->getJson('/api/za/protection/tax-treatment/life');

    $response->assertOk();
    expect($response->json('data.premiums_deductible'))->toBe(false);
    expect($response->json('data.payout_taxable'))->toBe(false);
});

it('computes coverage-gap happy path with policies and user context', function () {
    $this->user->update(['annual_employment_income' => 480_000]);

    ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'cover_amount_minor' => 2_000_000_00,
    ]);

    $response = $this->getJson('/api/za/protection/coverage-gap');

    $response->assertOk();
    $data = collect($response->json('data'));
    $life = $data->firstWhere('category', 'life');
    expect($life['existing_cover_minor'])->toBe(2_000_000_00);
    expect($life['shortfall_minor'])->toBeGreaterThan(0);
    expect($life['missing_inputs'])->toBe([]);
});

it('flags missing_inputs in coverage-gap when user has no income data', function () {
    $response = $this->getJson('/api/za/protection/coverage-gap');

    $response->assertOk();
    $data = collect($response->json('data'));
    $life = $data->firstWhere('category', 'life');
    expect($life['missing_inputs'])->toContain('annual_income');
});

it('summarises the dashboard payload', function () {
    ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
    ]);
    ZaProtectionPolicy::factory()->for($this->user)->funeral()->create([
        'premium_amount_minor' => 150_00,
        'premium_frequency' => 'monthly',
    ]);

    $response = $this->getJson('/api/za/protection/dashboard');

    $response->assertOk()->assertJsonPath('success', true);
    expect($response->json('data.total_monthly_premium_minor'))->toBe(1_650_00);
    expect($response->json('data.policies_by_type'))->toBeArray();
});

it('blocks protection endpoints when ZA jurisdiction is not active', function () {
    $otherUser = User::factory()->create();
    Sanctum::actingAs($otherUser);

    $response = $this->getJson('/api/za/protection/dashboard');

    $response->assertStatus(403);
});

it('blocks writes from preview users', function () {
    $this->user->is_preview_user = true;
    $this->user->save();

    $response = $this->postJson('/api/za/protection/policies', [
        'product_type' => 'life',
        'provider' => 'Discovery Life',
        'cover_amount_minor' => 5_000_000_00,
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ]);

    // PreviewWriteInterceptor returns 200 with is_preview=true, not 403 or a row creation.
    $response->assertOk();
    expect(ZaProtectionPolicy::count())->toBe(0);
});

it('returns joint-owner policies in the list', function () {
    $other = User::factory()->create();
    ZaProtectionPolicy::factory()
        ->for($other, 'user')
        ->create(['joint_owner_id' => $this->user->id]);

    $response = $this->getJson('/api/za/protection/policies');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('cascades beneficiary delete when the policy is hard-deleted via force delete', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();
    ZaProtectionBeneficiary::factory()->for($policy, 'policy')->count(2)->create();

    $policy->forceDelete();

    expect(ZaProtectionBeneficiary::where('policy_id', $policy->id)->count())->toBe(0);
});

it('refuses to update a policy belonging to another user', function () {
    $policy = ZaProtectionPolicy::factory()->create();

    $response = $this->putJson("/api/za/protection/policies/{$policy->id}", [
        'cover_amount_minor' => 999_999_99,
    ]);

    $response->assertStatus(404);
});
```

- [ ] **Step 2: Run tests — confirm failures**

Run: `./vendor/bin/pest tests/Feature/Api/Za/ZaProtectionControllerTest.php`
Expected: 18 fails — routes and controller don't exist yet.

- [ ] **Step 3: Commit the failing tests**

```bash
git add tests/Feature/Api/Za/ZaProtectionControllerTest.php
git commit -m "test(za-protection): failing controller feature tests (TDD, WS 1.5b)"
```

---

## Task 10: Implement `ZaProtectionController` + routes

**Files:**
- Create: `app/Http/Controllers/Api/Za/ZaProtectionController.php`
- Modify: `routes/api.php` (append inside ZA group)

- [ ] **Step 1: Write the controller**

```php
<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Za;

use App\Http\Controllers\Controller;
use App\Http\Requests\Za\Protection\CoverageGapRequest;
use App\Http\Requests\Za\Protection\StoreZaBeneficiariesRequest;
use App\Http\Requests\Za\Protection\StoreZaProtectionPolicyRequest;
use App\Http\Requests\Za\Protection\UpdateZaProtectionPolicyRequest;
use App\Http\Resources\Za\Protection\ZaCoverageGapResource;
use App\Http\Resources\Za\Protection\ZaProtectionBeneficiaryResource;
use App\Http\Resources\Za\Protection\ZaProtectionPolicyResource;
use App\Models\FamilyMember;
use App\Models\Mortgage;
use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Fynla\Packs\Za\Protection\ZaProtectionEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * HTTP adapter over the ZA protection domain (WS 1.5b).
 *
 * Thin proxy: every method either resolves a pack binding and delegates,
 * or performs straight CRUD over pack models. No business logic here.
 */
class ZaProtectionController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $policies = ZaProtectionPolicy::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->get();

        $byType = $policies->groupBy('product_type')->map(fn ($group) => [
            'count' => $group->count(),
            'total_cover_minor' => (int) $group->sum('cover_amount_minor'),
            'total_premium_minor' => (int) $group->sum(fn ($p) => $this->monthlyPremium($p)),
        ])->all();

        $totalMonthly = $policies->sum(fn ($p) => $this->monthlyPremium($p));

        return response()->json([
            'success' => true,
            'message' => 'Protection dashboard payload.',
            'data' => [
                'policy_count' => $policies->count(),
                'total_monthly_premium_minor' => (int) $totalMonthly,
                'total_monthly_premium_major' => round($totalMonthly / 100, 2),
                'policies_by_type' => $byType,
            ],
        ]);
    }

    public function listPolicies(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $policies = ZaProtectionPolicy::query()
            ->with('beneficiaries')
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->orderBy('product_type')
            ->orderBy('start_date', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => ZaProtectionPolicyResource::collection($policies),
        ]);
    }

    public function storePolicy(StoreZaProtectionPolicyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $beneficiaries = $data['beneficiaries'] ?? [];
        unset($data['beneficiaries']);
        $data['user_id'] = $request->user()->id;

        $policy = DB::transaction(function () use ($data, $beneficiaries) {
            $policy = ZaProtectionPolicy::create($data);
            foreach ($beneficiaries as $b) {
                $b['policy_id'] = $policy->id;
                ZaProtectionBeneficiary::create($b);
            }
            return $policy->load('beneficiaries');
        });

        return response()->json([
            'success' => true,
            'message' => 'Policy created.',
            'data' => new ZaProtectionPolicyResource($policy),
        ], Response::HTTP_CREATED);
    }

    public function showPolicy(Request $request, int $id): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $id);

        return response()->json([
            'success' => true,
            'data' => new ZaProtectionPolicyResource($policy->load('beneficiaries')),
        ]);
    }

    public function updatePolicy(UpdateZaProtectionPolicyRequest $request, int $id): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $id);
        $policy->update($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Policy updated.',
            'data' => new ZaProtectionPolicyResource($policy->fresh('beneficiaries')),
        ]);
    }

    public function deletePolicy(Request $request, int $id): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $id);
        $policy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Policy deleted.',
        ]);
    }

    public function policyTypes(): JsonResponse
    {
        /** @var ZaProtectionEngine $engine */
        $engine = app('pack.za.protection');

        return response()->json([
            'success' => true,
            'data' => $engine->getAvailablePolicyTypes(),
        ]);
    }

    public function taxTreatment(string $type): JsonResponse
    {
        /** @var ZaProtectionEngine $engine */
        $engine = app('pack.za.protection');

        try {
            $data = $engine->getPolicyTaxTreatment($type);
        } catch (InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function coverageGap(CoverageGapRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        $policies = ZaProtectionPolicy::query()
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->get()
            ->map(fn ($p) => [
                'product_type' => $p->product_type,
                'cover_amount_minor' => (int) $p->cover_amount_minor,
            ])
            ->all();

        // Annual income from User columns (app/Traits/ResolvesIncome.php pattern).
        // Engine expects MINOR units (R-cents), so multiply by 100.
        $user = $request->user();
        $annualIncomeMajor = (float) ($user->annual_employment_income ?? 0)
            + (float) ($user->annual_self_employment_income ?? 0)
            + (float) ($user->annual_rental_income ?? 0)
            + (float) ($user->annual_dividend_income ?? 0)
            + (float) ($user->annual_interest_income ?? 0)
            + (float) ($user->annual_other_income ?? 0)
            + (float) ($user->annual_trust_income ?? 0);
        $annualIncome = (int) round($annualIncomeMajor * 100);

        $outstandingDebts = $this->outstandingDebts($userId);
        $dependants = FamilyMember::query()
            ->where('user_id', $userId)
            ->where('is_dependent', true)
            ->count();

        /** @var ZaProtectionEngine $engine */
        $engine = app('pack.za.protection');
        $gap = $engine->calculateAggregateCoverageGap($policies, [
            'annual_income' => $annualIncome,
            'outstanding_debts' => $outstandingDebts,
            'dependants' => $dependants,
        ]);

        return response()->json([
            'success' => true,
            'data' => (new ZaCoverageGapResource($gap))->toArray($request),
            'meta' => [
                'inputs' => [
                    'annual_income' => $annualIncome,
                    'outstanding_debts' => $outstandingDebts,
                    'dependants' => $dependants,
                ],
            ],
        ]);
    }

    public function listBeneficiaries(Request $request, int $policyId): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $policyId);

        return response()->json([
            'success' => true,
            'data' => ZaProtectionBeneficiaryResource::collection($policy->beneficiaries),
        ]);
    }

    public function storeBeneficiaries(StoreZaBeneficiariesRequest $request, int $policyId): JsonResponse
    {
        $policy = $this->findUserPolicy($request, $policyId);

        $beneficiaries = $request->validated()['beneficiaries'];

        DB::transaction(function () use ($policy, $beneficiaries) {
            $policy->beneficiaries()->delete();
            foreach ($beneficiaries as $b) {
                $b['policy_id'] = $policy->id;
                ZaProtectionBeneficiary::create($b);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Beneficiaries replaced.',
            'data' => ZaProtectionBeneficiaryResource::collection($policy->fresh('beneficiaries')->beneficiaries),
        ]);
    }

    private function findUserPolicy(Request $request, int $id): ZaProtectionPolicy
    {
        $userId = $request->user()->id;

        $policy = ZaProtectionPolicy::query()
            ->where('id', $id)
            ->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)->orWhere('joint_owner_id', $userId);
            })
            ->first();

        if (! $policy) {
            abort(Response::HTTP_NOT_FOUND, 'Policy not found.');
        }

        return $policy;
    }

    private function monthlyPremium(ZaProtectionPolicy $p): int
    {
        return match ($p->premium_frequency) {
            'monthly' => (int) $p->premium_amount_minor,
            'quarterly' => (int) round($p->premium_amount_minor / 3),
            'annual' => (int) round($p->premium_amount_minor / 12),
            default => 0,
        };
    }

    private function outstandingDebts(int $userId): int
    {
        // Sum outstanding mortgage balances for the user via Eloquent
        // (architecture test blocks DB facade in controllers). The
        // `mortgages.outstanding_balance` column is decimal(15,2) in
        // MAJOR units; engine expects MINOR units, so convert.
        $mortgagesMajor = (float) Mortgage::query()
            ->where('user_id', $userId)
            ->sum('outstanding_balance');

        return (int) round($mortgagesMajor * 100);
    }
}
```

- [ ] **Step 2: Register routes**

Edit `routes/api.php`. Find the `// WS 1.4d — Retirement` block (approx line 1294) and append after its closing `});`, inside the same top-level ZA group:

```php
        // WS 1.5b — Protection
        Route::prefix('protection')->as('protection.')->group(function () {
            Route::get('dashboard', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'dashboard'])->name('dashboard');

            Route::get('policies', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'listPolicies'])->name('policies.index');
            Route::post('policies', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'storePolicy'])->name('policies.store');
            Route::get('policies/{id}', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'showPolicy'])->whereNumber('id')->name('policies.show');
            Route::put('policies/{id}', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'updatePolicy'])->whereNumber('id')->name('policies.update');
            Route::delete('policies/{id}', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'deletePolicy'])->whereNumber('id')->name('policies.destroy');

            Route::get('policy-types', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'policyTypes'])->name('policy-types');
            Route::get('tax-treatment/{type}', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'taxTreatment'])->name('tax-treatment');

            Route::get('coverage-gap', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'coverageGap'])->name('coverage-gap');

            Route::get('beneficiaries/{policyId}', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'listBeneficiaries'])->whereNumber('policyId')->name('beneficiaries.index');
            Route::post('beneficiaries/{policyId}', [\App\Http\Controllers\Api\Za\ZaProtectionController::class, 'storeBeneficiaries'])->whereNumber('policyId')->name('beneficiaries.store');
        });
```

- [ ] **Step 3: Run feature tests — confirm pass**

Run: `./vendor/bin/pest tests/Feature/Api/Za/ZaProtectionControllerTest.php`
Expected: all 18 pass. If any fail, inspect the specific failure and adjust — NEVER skip.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/Api/Za/ZaProtectionController.php routes/api.php
git commit -m "feat(za-protection): ZaProtectionController + 11 routes (WS 1.5b)"
```

---

## Task 11: Beneficiary controller feature tests

**Files:**
- Create: `tests/Feature/Api/Za/ZaProtectionBeneficiaryTest.php`

- [ ] **Step 1: Write the tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionBeneficiary;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(\Database\Seeders\TaxConfigurationSeeder::class);
    (new \Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder())->run();
    (new \Database\Seeders\ZaJurisdictionSeeder())->run();
    $this->user = User::factory()->create();
    $this->user->jurisdictions()->syncWithoutDetaching([
        \App\Models\Jurisdiction::where('country_code', 'ZA')->first()->id => ['is_active' => true],
    ]);
    Sanctum::actingAs($this->user);
    $this->policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create();
});

it('lists beneficiaries for a policy', function () {
    ZaProtectionBeneficiary::factory()->for($this->policy, 'policy')->count(2)->create(['allocation_percentage' => 50]);

    $response = $this->getJson("/api/za/protection/beneficiaries/{$this->policy->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(2);
});

it('replaces the beneficiary set atomically', function () {
    ZaProtectionBeneficiary::factory()->for($this->policy, 'policy')->create(['allocation_percentage' => 100]);

    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'spouse', 'name' => 'Spouse', 'allocation_percentage' => 50],
            ['beneficiary_type' => 'nominated_individual', 'name' => 'Child', 'allocation_percentage' => 50, 'id_number' => '9001015009087'],
        ],
    ]);

    $response->assertOk();
    expect($this->policy->fresh()->beneficiaries)->toHaveCount(2);
});

it('rejects beneficiaries that do not sum to 100', function () {
    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'spouse', 'name' => 'Spouse', 'allocation_percentage' => 60],
            ['beneficiary_type' => 'nominated_individual', 'name' => 'Child', 'allocation_percentage' => 50, 'id_number' => '9001015009087'],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['beneficiaries']);
});

it('accepts an estate beneficiary with null name', function () {
    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'estate', 'allocation_percentage' => 100],
        ],
    ]);

    $response->assertOk();
    expect($this->policy->fresh()->beneficiaries->first()->beneficiary_type)->toBe('estate');
});

it('requires a name for nominated_individual', function () {
    $response = $this->postJson("/api/za/protection/beneficiaries/{$this->policy->id}", [
        'beneficiaries' => [
            ['beneficiary_type' => 'nominated_individual', 'allocation_percentage' => 100, 'id_number' => '9001015009087'],
        ],
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['beneficiaries.0.name']);
});

it('cascade-deletes beneficiaries on policy force-delete', function () {
    ZaProtectionBeneficiary::factory()->for($this->policy, 'policy')->count(3)->create(['allocation_percentage' => 33.33]);
    expect(ZaProtectionBeneficiary::where('policy_id', $this->policy->id)->count())->toBe(3);

    $this->policy->forceDelete();

    expect(ZaProtectionBeneficiary::where('policy_id', $this->policy->id)->count())->toBe(0);
});
```

- [ ] **Step 2: Run tests — confirm all pass**

Run: `./vendor/bin/pest tests/Feature/Api/Za/ZaProtectionBeneficiaryTest.php`
Expected: 6 pass.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Api/Za/ZaProtectionBeneficiaryTest.php
git commit -m "test(za-protection): beneficiary controller feature tests (WS 1.5b)"
```

---

## Task 12: Integration workflow tests

**Files:**
- Create: `tests/Integration/Za/ZaProtectionWorkflowTest.php` *(under `Za/` subfolder per WS 1.4d convention)*

- [ ] **Step 1: Write tests**

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Fynla\Packs\Za\Models\ZaProtectionPolicy;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    putenv('FYNLA_ACTIVE_PACKS=GB,ZA');
    $this->seed(\Fynla\Packs\Za\Database\Seeders\ZaTaxConfigurationSeeder::class);
    $this->user = User::factory()->create(['annual_employment_income' => 480_000]);
    Sanctum::actingAs($this->user);
});

afterEach(function () {
    putenv('FYNLA_ACTIVE_PACKS');
});

it('updates the coverage-gap after creating a policy', function () {
    $baseline = $this->getJson('/api/za/protection/coverage-gap')->json('data');
    $lifeBefore = collect($baseline)->firstWhere('category', 'life');

    $this->postJson('/api/za/protection/policies', [
        'product_type' => 'life',
        'provider' => 'Discovery Life',
        'cover_amount_minor' => 5_000_000_00,
        'premium_amount_minor' => 1_500_00,
        'premium_frequency' => 'monthly',
        'start_date' => '2026-01-01',
    ])->assertCreated();

    $after = $this->getJson('/api/za/protection/coverage-gap')->json('data');
    $lifeAfter = collect($after)->firstWhere('category', 'life');

    expect($lifeAfter['existing_cover_minor'])->toBe($lifeBefore['existing_cover_minor'] + 5_000_000_00);
    expect($lifeAfter['shortfall_minor'])->toBeLessThan($lifeBefore['shortfall_minor']);
});

it('updates the coverage-gap after deleting a policy', function () {
    $policy = ZaProtectionPolicy::factory()->for($this->user)->life()->create([
        'cover_amount_minor' => 5_000_000_00,
    ]);

    $before = collect($this->getJson('/api/za/protection/coverage-gap')->json('data'))
        ->firstWhere('category', 'life');

    $this->deleteJson("/api/za/protection/policies/{$policy->id}")->assertOk();

    $after = collect($this->getJson('/api/za/protection/coverage-gap')->json('data'))
        ->firstWhere('category', 'life');

    expect($after['existing_cover_minor'])->toBe(0);
    expect($after['shortfall_minor'])->toBeGreaterThan($before['shortfall_minor']);
});
```

- [ ] **Step 2: Run tests**

Run: `./vendor/bin/pest tests/Integration/Za/ZaProtectionWorkflowTest.php`
Expected: 2 pass.

- [ ] **Step 3: Run full suite to confirm zero regressions**

Run: `./vendor/bin/pest`
Expected: 2,777+ passing (2,747 baseline + ~30 new), 4 pre-existing failures (ProtectionWorkflowTest adequacy_score), 2 skipped.

- [ ] **Step 4: Commit**

```bash
git add tests/Integration/Za/ZaProtectionWorkflowTest.php
git commit -m "test(za-protection): integration workflow tests (create/delete → coverage gap updates) (WS 1.5b)"
```

---

## ~~Task 13: Shared `DialogContainer` component (tech-debt W1)~~ — DEFERRED

**Deferred to a separate follow-up PR per PRD audit Q-A.** Rationale: refactoring 4 prior-WS modals inside this workstream expands the regression surface and rollback complexity. The follow-up PR will ship `DialogContainer` and refactor WS 1.2b + 1.3c + 1.4d + 1.5b modals together, with targeted Playwright tests for each.

**In WS 1.5b**, the new `ZaProtectionPolicyModal.vue` uses a hand-rolled modal shell matching the existing WS 1.4d pattern (`role="dialog"`, click-outside backdrop, simple close button). See Task 17 Step 4 for the exact markup.

Skip Tasks 13 and 14. Proceed directly to Task 15.

## ~~Task 13 (old): `DialogContainer` component~~

**Files:**
- Create: `resources/js/components/Common/DialogContainer.vue`

- [ ] **Step 1: Write the component**

```vue
<template>
  <teleport to="body">
    <transition name="dialog-fade">
      <div
        v-if="modelValue"
        class="fixed inset-0 z-50 flex items-center justify-center"
        @click.self="onBackdrop"
      >
        <div class="absolute inset-0 bg-horizon-500/40" aria-hidden="true" />
        <div
          ref="panel"
          role="dialog"
          aria-modal="true"
          :aria-labelledby="titleId"
          tabindex="-1"
          class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col"
          @keydown.esc="close"
          @keydown.tab="trapFocus"
        >
          <header v-if="title" class="px-6 py-4 border-b border-savannah-100 flex justify-between items-center">
            <h2 :id="titleId" class="text-xl font-bold text-horizon-500">{{ title }}</h2>
            <button
              type="button"
              class="text-horizon-300 hover:text-horizon-500 text-2xl leading-none"
              aria-label="Close dialog"
              @click="close"
            >×</button>
          </header>
          <div class="overflow-y-auto flex-1">
            <slot />
          </div>
          <footer v-if="$slots.footer" class="px-6 py-4 border-t border-savannah-100">
            <slot name="footer" />
          </footer>
        </div>
      </div>
    </transition>
  </teleport>
</template>

<script>
export default {
  name: 'DialogContainer',
  props: {
    modelValue: { type: Boolean, required: true },
    title: { type: String, default: '' },
    closeOnBackdrop: { type: Boolean, default: true },
  },
  emits: ['update:modelValue', 'close'],
  data() {
    return { titleId: `dialog-title-${Math.random().toString(36).slice(2, 9)}` };
  },
  watch: {
    modelValue(v) {
      if (v) this.$nextTick(() => this.$refs.panel?.focus());
    },
  },
  methods: {
    close() {
      this.$emit('update:modelValue', false);
      this.$emit('close');
    },
    onBackdrop() {
      if (this.closeOnBackdrop) this.close();
    },
    trapFocus(event) {
      if (!this.$refs.panel) return;
      const focusable = this.$refs.panel.querySelectorAll(
        'a, button, textarea, input, select, [tabindex]:not([tabindex="-1"])',
      );
      if (!focusable.length) return;
      const first = focusable[0];
      const last = focusable[focusable.length - 1];
      if (event.shiftKey && document.activeElement === first) {
        last.focus();
        event.preventDefault();
      } else if (!event.shiftKey && document.activeElement === last) {
        first.focus();
        event.preventDefault();
      }
    },
  },
};
</script>

<style scoped>
.dialog-fade-enter-active, .dialog-fade-leave-active {
  transition: opacity 0.2s ease;
}
.dialog-fade-enter-from, .dialog-fade-leave-to {
  opacity: 0;
}
</style>
```

- [ ] **Step 2: Verify build runs without errors**

Run: `npm run build 2>&1 | tail -20`
Expected: build succeeds; no Vue template compile errors for `DialogContainer.vue`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/Common/DialogContainer.vue
git commit -m "feat(common): shared DialogContainer with a11y (role/aria/focus-trap/esc) (WS 1.5b, tech-debt W1)"
```

---

## ~~Task 14: Refactor 4 prior-WS modals to use `DialogContainer`~~ — DEFERRED TO FOLLOW-UP PR

**Files (modify):**
- `resources/js/components/ZA/Savings/ZaContributionModal.vue`
- `resources/js/components/ZA/Investment/ZaInvestmentForm.vue` (modal wrapper portion)
- `resources/js/components/ZA/Retirement/ZaContributionModal.vue`
- `resources/js/components/ZA/Retirement/ZaRetirementFundForm.vue`

For each: replace the custom backdrop/panel markup with `<DialogContainer v-model="open" :title="...">...</DialogContainer>`, import the new component, drop any hand-rolled Escape handlers / focus traps.

- [ ] **Step 1: Refactor `ZaContributionModal.vue` (Savings)**

Open the file. Identify the outer `<div class="fixed inset-0 ...">` backdrop + panel. Replace with:

```vue
<template>
  <DialogContainer :model-value="open" :title="modalTitle" @update:model-value="$emit('close')">
    <form @submit.prevent="handleSubmit">
      <!-- existing form body unchanged -->
    </form>
  </DialogContainer>
</template>

<script>
import DialogContainer from '@/components/Common/DialogContainer.vue';
// existing imports...

export default {
  name: 'ZaContributionModal',
  components: { DialogContainer },
  // rest unchanged
};
</script>
```

Remove any hand-rolled Escape / click-outside logic from the component's `mounted()` / `beforeUnmount()` — `DialogContainer` owns it.

- [ ] **Step 2: Repeat for `ZaInvestmentForm.vue` (Investment, modal mode only — keep inline mode untouched)**

Same pattern. If the component has a `props: { isModal: Boolean }`, use `v-if="isModal"` to conditionally wrap the body in `DialogContainer`, falling back to an unwrapped `<div>` for inline usage.

- [ ] **Step 3: Repeat for `ZaContributionModal.vue` (Retirement)**

Same pattern as Savings.

- [ ] **Step 4: Repeat for `ZaRetirementFundForm.vue`**

Same pattern.

- [ ] **Step 5: Run build + smoke-test existing modals**

Run: `npm run build`
Expected: build succeeds.

Then manually smoke-test (via Playwright later, Task 19). For now, record in handover that modal refactor is untested until Playwright step.

- [ ] **Step 6: Commit**

```bash
git add resources/js/components/ZA/Savings/ZaContributionModal.vue \
  resources/js/components/ZA/Investment/ZaInvestmentForm.vue \
  resources/js/components/ZA/Retirement/ZaContributionModal.vue \
  resources/js/components/ZA/Retirement/ZaRetirementFundForm.vue
git commit -m "refactor(za-modals): route 4 ZA modals through DialogContainer (tech-debt W1)"
```

---

## Task 15: `zaProtectionService.js` + Vuex module

**Files:**
- Create: `resources/js/services/zaProtectionService.js`
- Modify: `resources/js/store/modules/zaProtection.js` (replace placeholder)

- [ ] **Step 1: Service**

```js
import api from './api';

const zaProtectionService = {
  async getDashboard() {
    const { data } = await api.get('/za/protection/dashboard');
    return data;
  },
  async listPolicies() {
    const { data } = await api.get('/za/protection/policies');
    return data;
  },
  async getPolicy(id) {
    const { data } = await api.get(`/za/protection/policies/${id}`);
    return data;
  },
  async createPolicy(payload) {
    const { data } = await api.post('/za/protection/policies', payload);
    return data;
  },
  async updatePolicy(id, payload) {
    const { data } = await api.put(`/za/protection/policies/${id}`, payload);
    return data;
  },
  async deletePolicy(id) {
    const { data } = await api.delete(`/za/protection/policies/${id}`);
    return data;
  },
  async getPolicyTypes() {
    const { data } = await api.get('/za/protection/policy-types');
    return data;
  },
  async getTaxTreatment(type) {
    const { data } = await api.get(`/za/protection/tax-treatment/${type}`);
    return data;
  },
  async getCoverageGap() {
    const { data } = await api.get('/za/protection/coverage-gap');
    return data;
  },
  async listBeneficiaries(policyId) {
    const { data } = await api.get(`/za/protection/beneficiaries/${policyId}`);
    return data;
  },
  async saveBeneficiaries(policyId, beneficiaries) {
    const { data } = await api.post(`/za/protection/beneficiaries/${policyId}`, { beneficiaries });
    return data;
  },
};

export default zaProtectionService;
```

- [ ] **Step 2: Vuex module (replace placeholder contents)**

```js
import zaProtectionService from '@/services/zaProtectionService';

const state = () => ({
  policies: [],
  beneficiaries: {}, // keyed by policy id
  policyTypes: [],
  taxTreatments: {}, // keyed by product_type
  coverageGap: null,
  dashboard: null,
  loading: false,
  error: null,
});

const getters = {
  isLoaded: (state) => state.dashboard !== null,
  policiesByType: (state) => {
    return state.policies.reduce((acc, p) => {
      acc[p.product_type] = acc[p.product_type] || [];
      acc[p.product_type].push(p);
      return acc;
    }, {});
  },
  beneficiariesForPolicy: (state) => (policyId) => state.beneficiaries[policyId] || [],
};

const mutations = {
  setLoading(state, v) { state.loading = v; },
  setError(state, err) { state.error = err; },
  setDashboard(state, payload) { state.dashboard = payload; },
  setPolicies(state, list) { state.policies = list; },
  addPolicy(state, p) { state.policies.push(p); },
  updatePolicy(state, p) {
    const i = state.policies.findIndex((x) => x.id === p.id);
    if (i >= 0) state.policies.splice(i, 1, p);
  },
  removePolicy(state, id) {
    const i = state.policies.findIndex((x) => x.id === id);
    if (i >= 0) state.policies.splice(i, 1);
  },
  setPolicyTypes(state, list) { state.policyTypes = list; },
  setTaxTreatment(state, { type, data }) { state.taxTreatments = { ...state.taxTreatments, [type]: data }; },
  setCoverageGap(state, payload) { state.coverageGap = payload; },
  setBeneficiaries(state, { policyId, list }) {
    state.beneficiaries = { ...state.beneficiaries, [policyId]: list };
  },
  reset(state) {
    state.policies = [];
    state.beneficiaries = {};
    state.policyTypes = [];
    state.taxTreatments = {};
    state.coverageGap = null;
    state.dashboard = null;
    state.loading = false;
    state.error = null;
  },
};

const actions = {
  async fetchDashboard({ commit }) {
    commit('setLoading', true);
    try {
      const response = await zaProtectionService.getDashboard();
      commit('setDashboard', response.data);
    } catch (e) { commit('setError', e.message); throw e; } finally { commit('setLoading', false); }
  },
  async fetchPolicies({ commit }) {
    commit('setLoading', true);
    try {
      const response = await zaProtectionService.listPolicies();
      commit('setPolicies', response.data);
    } catch (e) { commit('setError', e.message); throw e; } finally { commit('setLoading', false); }
  },
  async createPolicy({ commit, dispatch }, payload) {
    const response = await zaProtectionService.createPolicy(payload);
    commit('addPolicy', response.data);
    await dispatch('fetchCoverageGap');
    return response.data;
  },
  async updatePolicy({ commit, dispatch }, { id, payload }) {
    const response = await zaProtectionService.updatePolicy(id, payload);
    commit('updatePolicy', response.data);
    await dispatch('fetchCoverageGap');
    return response.data;
  },
  async deletePolicy({ commit, dispatch }, id) {
    await zaProtectionService.deletePolicy(id);
    commit('removePolicy', id);
    await dispatch('fetchCoverageGap');
  },
  async fetchPolicyTypes({ commit, state }) {
    if (state.policyTypes.length) return;
    const response = await zaProtectionService.getPolicyTypes();
    commit('setPolicyTypes', response.data);
  },
  async fetchTaxTreatment({ commit, state }, type) {
    if (state.taxTreatments[type]) return state.taxTreatments[type];
    const response = await zaProtectionService.getTaxTreatment(type);
    commit('setTaxTreatment', { type, data: response.data });
    return response.data;
  },
  async fetchCoverageGap({ commit }) {
    const response = await zaProtectionService.getCoverageGap();
    commit('setCoverageGap', { categories: response.data, inputs: response.meta?.inputs });
  },
  async fetchBeneficiaries({ commit }, policyId) {
    const response = await zaProtectionService.listBeneficiaries(policyId);
    commit('setBeneficiaries', { policyId, list: response.data });
  },
  async saveBeneficiaries({ commit }, { policyId, beneficiaries }) {
    const response = await zaProtectionService.saveBeneficiaries(policyId, beneficiaries);
    commit('setBeneficiaries', { policyId, list: response.data });
    return response.data;
  },
  reset({ commit }) { commit('reset'); },
};

export default {
  namespaced: true,
  state,
  getters,
  mutations,
  actions,
};
```

- [ ] **Step 3: Verify build**

Run: `npm run build`
Expected: build succeeds; module is bundled.

- [ ] **Step 4: Commit**

```bash
git add resources/js/services/zaProtectionService.js resources/js/store/modules/zaProtection.js
git commit -m "feat(za-protection): axios service + Vuex module (WS 1.5b)"
```

---

## Task 16: Route + sidebar registration

**Files:**
- Modify: `resources/js/router/index.js`
- Modify: `resources/js/store/modules/jurisdiction.js`

- [ ] **Step 1: Add route (mirrors `/za/retirement` meta exactly)**

Locate the `/za/retirement` route in `router/index.js` (around line 726). Directly after it, add:

```js
{
  path: '/za/protection',
  name: 'ZaProtection',
  component: () => import('@/views/ZA/ZaProtectionDashboard.vue'),
  meta: {
    requiresAuth: true,
    requiresJurisdiction: 'za',
    breadcrumb: [
      { label: 'Home', path: '/dashboard' },
      { label: 'South Africa — Protection', path: '/za/protection' },
    ],
  },
},
```

- [ ] **Step 2: Append sidebar entry (correct shape per `jurisdiction.js:45-52`)**

In `resources/js/store/modules/jurisdiction.js`, append to `MODULES_BY_JURISDICTION.za` (replacing the `// WS 1.5b will add za-protection here` comment):

```js
{ key: 'za-protection', label: 'Protection', route: '/za/protection', icon: 'shield', section: 'zaSection' },
```

Note: `key` and `section` are REQUIRED; the property is `route`, NOT `path`.

- [ ] **Step 3: Verify build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Commit (defer until view exists — do not push a broken route)**

Actually don't commit yet — route would 404 without the view. Continue to Task 17.

---

## Task 17: Dashboard view + Policies tab components (5 files)

**Files:**
- Create: `resources/js/views/ZA/ZaProtectionDashboard.vue`
- Create: `resources/js/components/ZA/Protection/ZaPoliciesTable.vue`
- Create: `resources/js/components/ZA/Protection/ZaProtectionPolicyForm.vue`
- Create: `resources/js/components/ZA/Protection/ZaProtectionPolicyModal.vue`
- Create: `resources/js/components/ZA/Protection/ZaPolicyDetailCard.vue`
- Create: `resources/js/components/ZA/Protection/ZaPolicyTypeSelector.vue`

Shells provided here. Implementer fills in markup that follows `fynlaDesignGuide.md` v1.4.0 and mirrors WS 1.4d component conventions (palette tokens only, no amber/orange, no scores, no inline `Math.round(x*100)` — import `toMinorZAR` from `@/utils/zaCurrency`). Scripts show required data/computed/methods/emits.

- [ ] **Step 1: `ZaProtectionDashboard.vue` (view)**

```vue
<template>
  <div class="za-protection-dashboard">
    <header class="mb-6">
      <h1 class="text-3xl font-black text-horizon-500">Protection</h1>
      <p class="text-horizon-300 mt-2">Policies, coverage gap, and beneficiaries.</p>
    </header>
    <div class="tabs border-b border-savannah-100 mb-6">
      <button
        v-for="tab in tabs" :key="tab.key"
        type="button"
        :class="['px-4 py-2 border-b-2 transition',
                 activeTab === tab.key ? 'border-raspberry-500 text-horizon-500 font-bold' : 'border-transparent text-horizon-300 hover:text-horizon-500']"
        @click="setTab(tab.key)"
      >{{ tab.label }}</button>
    </div>
    <div v-if="loading" class="flex justify-center py-16">
      <div class="w-10 h-10 border-4 border-horizon-200 border-t-raspberry-500 rounded-full animate-spin" />
    </div>
    <component v-else :is="currentComponent" />
  </div>
</template>

<script>
import { mapActions, mapGetters, mapState } from 'vuex';
import ZaPoliciesTable from '@/components/ZA/Protection/ZaPoliciesTable.vue';
import ZaCoverageGapDashboard from '@/components/ZA/Protection/ZaCoverageGapDashboard.vue';
import ZaBeneficiariesTab from '@/components/ZA/Protection/ZaBeneficiariesTab.vue';

export default {
  name: 'ZaProtectionDashboard',
  components: { ZaPoliciesTable, ZaCoverageGapDashboard, ZaBeneficiariesTab },
  data() {
    return {
      activeTab: this.$route.query.tab || 'policies',
      tabs: [
        { key: 'policies', label: 'Policies', component: 'ZaPoliciesTable' },
        { key: 'coverage-gap', label: 'Coverage gap', component: 'ZaCoverageGapDashboard' },
        { key: 'beneficiaries', label: 'Beneficiaries', component: 'ZaBeneficiariesTab' },
      ],
    };
  },
  computed: {
    ...mapState('zaProtection', ['loading']),
    ...mapGetters('zaProtection', ['isLoaded']),
    currentComponent() {
      const t = this.tabs.find((x) => x.key === this.activeTab);
      return t ? t.component : 'ZaPoliciesTable';
    },
  },
  async mounted() {
    await Promise.all([
      this.fetchDashboard(),
      this.fetchPolicies(),
      this.fetchCoverageGap(),
      this.fetchPolicyTypes(),
    ]);
  },
  methods: {
    ...mapActions('zaProtection', ['fetchDashboard', 'fetchPolicies', 'fetchCoverageGap', 'fetchPolicyTypes']),
    setTab(key) {
      this.activeTab = key;
      this.$router.replace({ query: { ...this.$route.query, tab: key } }).catch(() => {});
    },
  },
};
</script>
```

- [ ] **Step 2: `ZaPoliciesTable.vue`**

```vue
<template>
  <section>
    <div class="flex justify-between items-center mb-4">
      <h2 class="text-xl font-bold text-horizon-500">Your policies</h2>
      <button
        v-preview-disabled="'add'"
        type="button"
        class="bg-raspberry-500 text-white px-4 py-2 rounded-md font-semibold hover:bg-raspberry-600"
        @click="openAdd"
      >Add policy</button>
    </div>
    <div v-if="!policies.length" class="text-horizon-300 py-12 text-center">
      No policies yet. Click "Add policy" to start.
    </div>
    <div v-for="(group, type) in policiesByType" :key="type" class="mb-6">
      <h3 class="text-lg font-bold text-horizon-500 mb-2">{{ typeLabel(type) }}</h3>
      <table class="w-full border-collapse">
        <thead>
          <tr class="border-b border-savannah-100 text-horizon-300 text-sm">
            <th class="text-left py-2">Provider</th>
            <th class="text-right py-2">Cover</th>
            <th class="text-right py-2">Premium</th>
            <th class="text-right py-2">Beneficiaries</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in group" :key="p.id" class="border-b border-savannah-100">
            <td class="py-3">{{ p.provider }}</td>
            <td class="py-3 text-right">{{ formatZAR(p.cover_amount_major) }}</td>
            <td class="py-3 text-right">{{ formatZAR(p.premium_amount_major) }} / {{ p.premium_frequency }}</td>
            <td class="py-3 text-right">{{ (p.beneficiaries || []).length }}</td>
            <td class="py-3 text-right">
              <button v-preview-disabled type="button" class="text-raspberry-500 hover:underline mr-2" @click="openEdit(p)">Edit</button>
              <button v-preview-disabled="'delete'" type="button" class="text-raspberry-500 hover:underline" @click="confirmDelete(p)">Delete</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <ZaProtectionPolicyModal
      :open="modalOpen"
      :policy="editing"
      @save="handleSave"
      @close="modalOpen = false"
    />
  </section>
</template>

<script>
import { mapActions, mapGetters } from 'vuex';
import { zaCurrencyMixin } from '@/mixins/zaCurrencyMixin';
import ZaProtectionPolicyModal from './ZaProtectionPolicyModal.vue';

export default {
  name: 'ZaPoliciesTable',
  components: { ZaProtectionPolicyModal },
  mixins: [zaCurrencyMixin],
  data() {
    return { modalOpen: false, editing: null };
  },
  computed: {
    ...mapGetters('zaProtection', ['policiesByType']),
    policies() { return this.$store.state.zaProtection.policies; },
  },
  methods: {
    ...mapActions('zaProtection', ['createPolicy', 'updatePolicy', 'deletePolicy']),
    typeLabel(t) {
      return { life: 'Life cover', whole_of_life: 'Whole of life', dread: 'Dread disease',
               idisability_lump: 'Lump-sum disability', idisability_income: 'Income protection',
               funeral: 'Funeral cover' }[t] || t;
    },
    openAdd() { this.editing = null; this.modalOpen = true; },
    openEdit(p) { this.editing = p; this.modalOpen = true; },
    async confirmDelete(p) {
      if (!confirm(`Delete ${p.provider} ${this.typeLabel(p.product_type)}?`)) return;
      await this.deletePolicy(p.id);
    },
    async handleSave(payload) {
      if (this.editing) {
        await this.updatePolicy({ id: this.editing.id, payload });
      } else {
        await this.createPolicy(payload);
      }
      this.modalOpen = false;
    },
  },
};
</script>
```

- [ ] **Step 3: `ZaProtectionPolicyForm.vue`** — unified form with conditional fields. Uses `toMinorZAR` for cover/premium conversion. Emits `save` (not `submit`) per CLAUDE.md rule 4.

```vue
<template>
  <form @submit.prevent="handleSubmit" class="space-y-4 p-6">
    <div>
      <label class="block text-sm font-bold text-horizon-500 mb-1">Policy type</label>
      <ZaPolicyTypeSelector v-model="form.product_type" :disabled="!!existingPolicy" />
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Provider</label>
        <input v-model="form.provider" type="text" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Policy number (optional)</label>
        <input v-model="form.policy_number" type="text" class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Cover amount (R)</label>
        <input v-model.number="form.cover_amount_major" type="number" step="1" min="0" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Premium (R)</label>
        <input v-model.number="form.premium_amount_major" type="number" step="0.01" min="0" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Premium frequency</label>
        <select v-model="form.premium_frequency" required class="w-full border border-savannah-200 rounded-md p-2">
          <option value="monthly">Monthly</option>
          <option value="quarterly">Quarterly</option>
          <option value="annual">Annual</option>
        </select>
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Start date</label>
        <input v-model="form.start_date" type="date" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <!-- conditional: dread -->
    <div v-if="form.product_type === 'dread'">
      <label class="block text-sm font-bold text-horizon-500 mb-1">Severity tier (ASISA SCIDEP)</label>
      <select v-model="form.severity_tier" required class="w-full border border-savannah-200 rounded-md p-2">
        <option value="A">A — Most severe (100% payout)</option>
        <option value="B">B — Severe (75%)</option>
        <option value="C">C — Moderate (50%)</option>
        <option value="D">D — Mild (25%)</option>
      </select>
    </div>
    <!-- conditional: income protection -->
    <div v-if="form.product_type === 'idisability_income'" class="grid grid-cols-2 gap-4">
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Waiting period (months)</label>
        <input v-model.number="form.waiting_period_months" type="number" min="0" max="60" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
      <div>
        <label class="block text-sm font-bold text-horizon-500 mb-1">Benefit term (months)</label>
        <input v-model.number="form.benefit_term_months" type="number" min="0" max="600" required class="w-full border border-savannah-200 rounded-md p-2" />
      </div>
    </div>
    <div class="flex justify-end gap-2 pt-4 border-t border-savannah-100">
      <button type="button" class="px-4 py-2 text-horizon-300 hover:text-horizon-500" @click="$emit('close')">Cancel</button>
      <button type="submit" class="px-4 py-2 bg-raspberry-500 text-white rounded-md font-semibold hover:bg-raspberry-600">Save</button>
    </div>
  </form>
</template>

<script>
import { toMinorZAR } from '@/utils/zaCurrency';
import ZaPolicyTypeSelector from './ZaPolicyTypeSelector.vue';

export default {
  name: 'ZaProtectionPolicyForm',
  components: { ZaPolicyTypeSelector },
  props: {
    existingPolicy: { type: Object, default: null },
  },
  emits: ['save', 'close'],
  data() {
    const base = {
      product_type: 'life',
      provider: '',
      policy_number: '',
      cover_amount_major: 0,
      premium_amount_major: 0,
      premium_frequency: 'monthly',
      start_date: new Date().toISOString().split('T')[0],
      severity_tier: null,
      waiting_period_months: null,
      benefit_term_months: null,
    };
    const fromExisting = this.existingPolicy ? {
      product_type: this.existingPolicy.product_type,
      provider: this.existingPolicy.provider,
      policy_number: this.existingPolicy.policy_number || '',
      cover_amount_major: this.existingPolicy.cover_amount_major,
      premium_amount_major: this.existingPolicy.premium_amount_major,
      premium_frequency: this.existingPolicy.premium_frequency,
      start_date: this.existingPolicy.start_date?.substring(0, 10) || base.start_date,
      severity_tier: this.existingPolicy.severity_tier,
      waiting_period_months: this.existingPolicy.waiting_period_months,
      benefit_term_months: this.existingPolicy.benefit_term_months,
    } : {};
    return { form: { ...base, ...fromExisting } };
  },
  methods: {
    handleSubmit() {
      const payload = {
        product_type: this.form.product_type,
        provider: this.form.provider,
        policy_number: this.form.policy_number || null,
        cover_amount_minor: toMinorZAR(this.form.cover_amount_major || 0),
        premium_amount_minor: toMinorZAR(this.form.premium_amount_major || 0),
        premium_frequency: this.form.premium_frequency,
        start_date: this.form.start_date,
      };
      if (this.form.product_type === 'dread') payload.severity_tier = this.form.severity_tier;
      if (this.form.product_type === 'idisability_income') {
        payload.waiting_period_months = this.form.waiting_period_months;
        payload.benefit_term_months = this.form.benefit_term_months;
      }
      this.$emit('save', payload);
    },
  },
};
</script>
```

- [ ] **Step 4: `ZaProtectionPolicyModal.vue` (hand-rolled modal — DialogContainer deferred to follow-up PR)**

```vue
<template>
  <teleport to="body">
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center" @click.self="$emit('close')">
      <div class="absolute inset-0 bg-horizon-500/40" aria-hidden="true" />
      <div role="dialog" aria-modal="true" :aria-labelledby="titleId"
           class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-hidden flex flex-col"
           @keydown.esc="$emit('close')">
        <header class="px-6 py-4 border-b border-savannah-100 flex justify-between items-center">
          <h2 :id="titleId" class="text-xl font-bold text-horizon-500">{{ title }}</h2>
          <button type="button" class="text-horizon-300 hover:text-horizon-500 text-2xl leading-none"
                  aria-label="Close dialog" @click="$emit('close')">×</button>
        </header>
        <div class="overflow-y-auto flex-1">
          <ZaProtectionPolicyForm
            :existing-policy="policy"
            @save="(payload) => $emit('save', payload)"
            @close="$emit('close')"
          />
        </div>
      </div>
    </div>
  </teleport>
</template>

<script>
import ZaProtectionPolicyForm from './ZaProtectionPolicyForm.vue';

export default {
  name: 'ZaProtectionPolicyModal',
  components: { ZaProtectionPolicyForm },
  props: {
    open: { type: Boolean, required: true },
    policy: { type: Object, default: null },
  },
  emits: ['update:open', 'save', 'close'],
  data() {
    return { titleId: `dialog-title-${Math.random().toString(36).slice(2, 9)}` };
  },
  computed: {
    title() { return this.policy ? 'Edit policy' : 'Add policy'; },
  },
};
</script>
```

Note: this markup is what the follow-up DialogContainer PR will replace — keeping the `role="dialog"` / `aria-modal` / Escape handler inline matches the existing WS 1.4d modal conventions exactly.

- [ ] **Step 5: `ZaPolicyDetailCard.vue`** (minimal — shows read-only policy detail + tax-treatment text)

```vue
<template>
  <div class="bg-white rounded-lg p-6 border border-savannah-100">
    <h3 class="text-xl font-bold text-horizon-500">{{ policy.provider }}</h3>
    <dl class="mt-4 grid grid-cols-2 gap-4">
      <div><dt class="text-sm text-horizon-300">Cover amount</dt><dd class="font-bold">{{ formatZAR(policy.cover_amount_major) }}</dd></div>
      <div><dt class="text-sm text-horizon-300">Premium</dt><dd class="font-bold">{{ formatZAR(policy.premium_amount_major) }} / {{ policy.premium_frequency }}</dd></div>
      <div v-if="policy.severity_tier"><dt class="text-sm text-horizon-300">Severity tier</dt><dd class="font-bold">{{ policy.severity_tier }}</dd></div>
    </dl>
    <div v-if="taxTreatment" class="mt-6 p-4 bg-eggshell-500 rounded-md">
      <h4 class="font-bold text-horizon-500 mb-2">Tax treatment</h4>
      <p class="text-sm text-horizon-300">{{ taxTreatment.notes }}</p>
    </div>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';
import { zaCurrencyMixin } from '@/mixins/zaCurrencyMixin';

export default {
  name: 'ZaPolicyDetailCard',
  mixins: [zaCurrencyMixin],
  props: { policy: { type: Object, required: true } },
  computed: {
    ...mapState('zaProtection', ['taxTreatments']),
    taxTreatment() { return this.taxTreatments[this.policy.product_type] || null; },
  },
  async mounted() { await this.fetchTaxTreatment(this.policy.product_type); },
  methods: { ...mapActions('zaProtection', ['fetchTaxTreatment']) },
};
</script>
```

- [ ] **Step 6: `ZaPolicyTypeSelector.vue`**

```vue
<template>
  <div>
    <select :value="modelValue" :disabled="disabled" @change="$emit('update:modelValue', $event.target.value)"
            class="w-full border border-savannah-200 rounded-md p-2">
      <option v-for="t in policyTypes" :key="t.code" :value="t.code">{{ t.name }} — {{ t.description }}</option>
    </select>
  </div>
</template>

<script>
import { mapActions, mapState } from 'vuex';

export default {
  name: 'ZaPolicyTypeSelector',
  props: {
    modelValue: { type: String, required: true },
    disabled: { type: Boolean, default: false },
  },
  emits: ['update:modelValue'],
  computed: { ...mapState('zaProtection', ['policyTypes']) },
  async mounted() { if (!this.policyTypes.length) await this.fetchPolicyTypes(); },
  methods: { ...mapActions('zaProtection', ['fetchPolicyTypes']) },
};
</script>
```

- [ ] **Step 7: Verify build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 8: Commit**

```bash
git add resources/js/views/ZA/ZaProtectionDashboard.vue resources/js/components/ZA/Protection/
git commit -m "feat(za-protection): dashboard view + 5 policies-tab components (WS 1.5b)"
```

---

## Task 18: Coverage-gap tab components (4 files)

**Files:**
- Create: `resources/js/components/ZA/Protection/ZaCoverageGapDashboard.vue`
- Create: `resources/js/components/ZA/Protection/ZaCoverageGaugeCard.vue`
- Create: `resources/js/components/ZA/Protection/ZaCoverageRationalePanel.vue`
- Create: `resources/js/components/ZA/Protection/ZaMissingInputsEmptyState.vue`

- [ ] **Step 1: `ZaCoverageGapDashboard.vue`**

```vue
<template>
  <section>
    <h2 class="text-xl font-bold text-horizon-500 mb-4">Coverage gap analysis</h2>
    <div v-if="!categories" class="text-horizon-300 py-12 text-center">Loading…</div>
    <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <template v-for="cat in categories" :key="cat.category">
        <ZaMissingInputsEmptyState v-if="cat.missing_inputs.length" :category="cat" />
        <ZaCoverageGaugeCard v-else :category="cat" />
      </template>
    </div>
    <div v-if="inputs" class="mt-6 text-xs text-horizon-300">
      Inputs used: annual income {{ formatZAR(inputs.annual_income) }}, outstanding debts {{ formatZAR(inputs.outstanding_debts / 100) }}, dependants {{ inputs.dependants }}.
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import { zaCurrencyMixin } from '@/mixins/zaCurrencyMixin';
import ZaCoverageGaugeCard from './ZaCoverageGaugeCard.vue';
import ZaMissingInputsEmptyState from './ZaMissingInputsEmptyState.vue';

export default {
  name: 'ZaCoverageGapDashboard',
  components: { ZaCoverageGaugeCard, ZaMissingInputsEmptyState },
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapState('zaProtection', ['coverageGap']),
    categories() { return this.coverageGap?.categories || null; },
    inputs() { return this.coverageGap?.inputs || null; },
  },
};
</script>
```

- [ ] **Step 2: `ZaCoverageGaugeCard.vue`**

```vue
<template>
  <div class="bg-white rounded-lg p-6 border border-savannah-100">
    <header class="flex justify-between items-baseline mb-4">
      <h3 class="font-bold text-horizon-500">{{ label }}</h3>
      <span v-if="category.shortfall_minor > 0" class="text-sm font-bold text-raspberry-500">Shortfall</span>
      <span v-else class="text-sm font-bold text-spring-500">On track</span>
    </header>
    <div class="h-3 bg-savannah-100 rounded-full overflow-hidden">
      <div class="h-full bg-raspberry-500 transition-all" :style="{ width: pct + '%' }" />
    </div>
    <dl class="mt-4 grid grid-cols-3 gap-2 text-sm">
      <div><dt class="text-horizon-300">Recommended</dt><dd class="font-bold">{{ formatZAR(category.recommended_cover_major) }}</dd></div>
      <div><dt class="text-horizon-300">Existing</dt><dd class="font-bold">{{ formatZAR(category.existing_cover_major) }}</dd></div>
      <div><dt class="text-horizon-300">Shortfall</dt><dd class="font-bold text-raspberry-500">{{ formatZAR(category.shortfall_major) }}</dd></div>
    </dl>
    <ZaCoverageRationalePanel :rationale="category.rationale" />
  </div>
</template>

<script>
import { zaCurrencyMixin } from '@/mixins/zaCurrencyMixin';
import ZaCoverageRationalePanel from './ZaCoverageRationalePanel.vue';

export default {
  name: 'ZaCoverageGaugeCard',
  components: { ZaCoverageRationalePanel },
  mixins: [zaCurrencyMixin],
  props: { category: { type: Object, required: true } },
  computed: {
    label() {
      return { life: 'Life cover', idisability_income: 'Income protection',
               dread: 'Dread disease', funeral: 'Funeral cover' }[this.category.category];
    },
    pct() {
      if (!this.category.recommended_cover_minor) return 0;
      const v = (this.category.existing_cover_minor / this.category.recommended_cover_minor) * 100;
      return Math.min(100, Math.max(0, Math.round(v)));
    },
  },
};
</script>
```

- [ ] **Step 3: `ZaCoverageRationalePanel.vue`**

```vue
<template>
  <details class="mt-4 text-sm">
    <summary class="cursor-pointer text-horizon-300 hover:text-horizon-500">Why this number?</summary>
    <p class="mt-2 text-horizon-300">{{ rationale }}</p>
  </details>
</template>

<script>
export default {
  name: 'ZaCoverageRationalePanel',
  props: { rationale: { type: String, required: true } },
};
</script>
```

- [ ] **Step 4: `ZaMissingInputsEmptyState.vue`**

```vue
<template>
  <div class="bg-eggshell-500 rounded-lg p-6 border border-savannah-100">
    <h3 class="font-bold text-horizon-500">{{ label }}</h3>
    <p class="mt-2 text-sm text-horizon-300">We need more data to compute this gap.</p>
    <ul class="mt-3 space-y-2">
      <li v-for="input in category.missing_inputs" :key="input">
        <router-link :to="linkFor(input)" class="text-raspberry-500 hover:underline text-sm font-bold">
          → {{ promptFor(input) }}
        </router-link>
      </li>
    </ul>
  </div>
</template>

<script>
export default {
  name: 'ZaMissingInputsEmptyState',
  props: { category: { type: Object, required: true } },
  computed: {
    label() {
      return { life: 'Life cover', idisability_income: 'Income protection',
               dread: 'Dread disease', funeral: 'Funeral cover' }[this.category.category];
    },
  },
  methods: {
    linkFor(input) {
      return { annual_income: '/income', dependants: '/household' }[input] || '/dashboard';
    },
    promptFor(input) {
      return { annual_income: 'Add your annual income', dependants: 'Add your household members' }[input] || 'Complete the prompted module';
    },
  },
};
</script>
```

- [ ] **Step 5: Verify build + commit**

Run: `npm run build`
Expected: build succeeds.

```bash
git add resources/js/components/ZA/Protection/ZaCoverageGapDashboard.vue \
  resources/js/components/ZA/Protection/ZaCoverageGaugeCard.vue \
  resources/js/components/ZA/Protection/ZaCoverageRationalePanel.vue \
  resources/js/components/ZA/Protection/ZaMissingInputsEmptyState.vue
git commit -m "feat(za-protection): 4 coverage-gap tab components (WS 1.5b)"
```

---

## Task 19: Beneficiaries tab components (2 files)

**Files:**
- Create: `resources/js/components/ZA/Protection/ZaBeneficiariesTab.vue`
- Create: `resources/js/components/ZA/Protection/ZaBeneficiaryEditor.vue`

- [ ] **Step 1: `ZaBeneficiariesTab.vue`**

```vue
<template>
  <section>
    <h2 class="text-xl font-bold text-horizon-500 mb-4">Beneficiaries</h2>
    <div v-if="!policies.length" class="text-horizon-300 py-12 text-center">
      Add a policy first to nominate beneficiaries.
    </div>
    <div v-for="policy in policies" :key="policy.id" class="mb-6 bg-white rounded-lg p-6 border border-savannah-100">
      <header class="flex justify-between items-baseline mb-3">
        <div>
          <h3 class="font-bold text-horizon-500">{{ policy.provider }} — {{ typeLabel(policy.product_type) }}</h3>
          <p class="text-sm text-horizon-300">Cover {{ formatZAR(policy.cover_amount_major) }}</p>
        </div>
        <span v-if="hasEstate(policy)" class="text-xs font-bold bg-violet-500 text-white px-2 py-1 rounded">
          Estate nomination — dutiable under s3(3)(a)(ii)
        </span>
      </header>
      <ZaBeneficiaryEditor :policy="policy" />
    </div>
  </section>
</template>

<script>
import { mapState } from 'vuex';
import { zaCurrencyMixin } from '@/mixins/zaCurrencyMixin';
import ZaBeneficiaryEditor from './ZaBeneficiaryEditor.vue';

export default {
  name: 'ZaBeneficiariesTab',
  components: { ZaBeneficiaryEditor },
  mixins: [zaCurrencyMixin],
  computed: {
    ...mapState('zaProtection', ['policies']),
  },
  methods: {
    typeLabel(t) {
      return { life: 'Life cover', whole_of_life: 'Whole of life', dread: 'Dread disease',
               idisability_lump: 'Lump-sum disability', idisability_income: 'Income protection',
               funeral: 'Funeral cover' }[t] || t;
    },
    hasEstate(p) {
      return (p.beneficiaries || []).some((b) => b.beneficiary_type === 'estate');
    },
  },
};
</script>
```

- [ ] **Step 2: `ZaBeneficiaryEditor.vue`** — inline editor with sum=100 guard, per-type field visibility.

```vue
<template>
  <div>
    <table class="w-full">
      <thead>
        <tr class="text-sm text-horizon-300 border-b border-savannah-100">
          <th class="text-left py-2">Type</th>
          <th class="text-left py-2">Name</th>
          <th class="text-left py-2">Relationship</th>
          <th class="text-left py-2">ID number</th>
          <th class="text-right py-2">Allocation %</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="(b, idx) in rows" :key="idx" class="border-b border-savannah-100">
          <td class="py-2"><select v-model="b.beneficiary_type" class="border rounded p-1">
            <option value="estate">Estate</option>
            <option value="spouse">Spouse</option>
            <option value="nominated_individual">Nominated individual</option>
            <option value="testamentary_trust">Testamentary trust</option>
            <option value="inter_vivos_trust">Inter-vivos trust</option>
          </select></td>
          <td class="py-2"><input v-model="b.name" type="text" :disabled="b.beneficiary_type === 'estate'" class="border rounded p-1" /></td>
          <td class="py-2"><input v-model="b.relationship" type="text" class="border rounded p-1" /></td>
          <td class="py-2"><input v-model="b.id_number" type="text" :disabled="b.beneficiary_type !== 'nominated_individual'" class="border rounded p-1 w-32" /></td>
          <td class="py-2 text-right"><input v-model.number="b.allocation_percentage" type="number" step="0.01" min="0" max="100" class="border rounded p-1 w-24 text-right" /></td>
          <td class="py-2 text-right"><button type="button" class="text-raspberry-500" @click="remove(idx)">Remove</button></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <td colspan="4" class="text-right text-sm text-horizon-300 py-2">Sum:</td>
          <td class="py-2 text-right font-bold" :class="sumValid ? 'text-spring-500' : 'text-raspberry-500'">{{ sum.toFixed(2) }}</td>
          <td></td>
        </tr>
      </tfoot>
    </table>
    <div class="flex justify-between items-center mt-4">
      <button type="button" class="text-raspberry-500 hover:underline" @click="add">+ Add beneficiary</button>
      <button
        v-preview-disabled
        type="button"
        :disabled="!sumValid"
        :class="['px-4 py-2 rounded-md font-semibold',
                 sumValid ? 'bg-raspberry-500 text-white hover:bg-raspberry-600' : 'bg-savannah-100 text-horizon-300 cursor-not-allowed']"
        @click="save"
      >Save beneficiaries</button>
    </div>
  </div>
</template>

<script>
import { mapActions } from 'vuex';

export default {
  name: 'ZaBeneficiaryEditor',
  props: { policy: { type: Object, required: true } },
  data() {
    return { rows: JSON.parse(JSON.stringify(this.policy.beneficiaries || [])) };
  },
  computed: {
    sum() { return this.rows.reduce((a, r) => a + (Number(r.allocation_percentage) || 0), 0); },
    sumValid() { return Math.abs(this.sum - 100) < 0.01 && this.rows.length > 0; },
  },
  methods: {
    ...mapActions('zaProtection', ['saveBeneficiaries']),
    add() {
      this.rows.push({ beneficiary_type: 'spouse', name: '', relationship: '', id_number: '', allocation_percentage: 0 });
    },
    remove(idx) { this.rows.splice(idx, 1); },
    async save() {
      if (!this.sumValid) return;
      await this.saveBeneficiaries({ policyId: this.policy.id, beneficiaries: this.rows });
    },
  },
};
</script>
```

- [ ] **Step 3: Verify build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 4: Commit route + sidebar + view + all components now that everything is wired**

```bash
git add resources/js/components/ZA/Protection/ZaBeneficiariesTab.vue \
  resources/js/components/ZA/Protection/ZaBeneficiaryEditor.vue \
  resources/js/router/index.js \
  resources/js/store/modules/jurisdiction.js
git commit -m "feat(za-protection): beneficiaries tab + router + sidebar registration (WS 1.5b)"
```

---

## Task 20: Tech-debt W2 sweep — refactor `Math.round(x*100)` → `toMinorZAR`

**Files (modify):**
- `resources/js/components/ZA/Retirement/ZaCompulsoryAnnuitisationCard.vue` (lines 95-97)
- `resources/js/components/ZA/Retirement/ZaSavingsPotWithdrawalCard.vue` (lines 116-117)
- `resources/js/components/ZA/Retirement/ZaSection11fReliefCalculator.vue`
- `resources/js/components/ZA/Retirement/ZaContributionModal.vue`
- `resources/js/components/ZA/Retirement/ZaRetirementFundForm.vue`
- `resources/js/components/ZA/Retirement/ZaLivingAnnuitySlider.vue`
- `resources/js/components/ZA/Retirement/ZaLifeAnnuityQuote.vue`
- `resources/js/components/ZA/Retirement/ZaReg28AllocationForm.vue`

For each, search for the pattern `Math.round((this.form.X || 0) * 100)` or `Math.round(X * 100)` and replace with `toMinorZAR(X || 0)`. Add `import { toMinorZAR } from '@/utils/zaCurrency';` at the top if missing.

- [ ] **Step 1: Sweep all 9 files**

Run (read-only — don't edit through script): `grep -rn 'Math.round.*\* 100' resources/js/components/ZA/Retirement/`

Then for each match: open the file, add the import if missing, replace the inline expression with `toMinorZAR(...)`.

- [ ] **Step 2: Verify build**

Run: `npm run build`
Expected: build succeeds.

- [ ] **Step 3: Run ZA retirement feature tests to confirm no behaviour change**

Run: `./vendor/bin/pest tests/Feature/Api/Za/ZaRetirementControllerTest.php`
Expected: all WS 1.4d tests still pass.

- [ ] **Step 4: Commit**

```bash
git add resources/js/components/ZA/Retirement/
git commit -m "refactor(za-retirement): replace inline Math.round(x*100) with toMinorZAR (tech-debt W2)"
```

---

## Task 21: Playwright browser smoke + final verification

**Files:** none (uses existing dev server)

- [ ] **Step 0: Seed a ZA-ready test user**

`za-retirement-test@example.com` referenced in the WS 1.4d handover was never actually seeded. Before Playwright, add a test user with ZA income + FamilyMember dependants.

Edit `database/seeders/TestUsersSeeder.php` — append a record:

```php
$zaProtectionUser = User::updateOrCreate(
    ['email' => 'za-protection-test@example.com'],
    [
        'name' => 'ZA Protection Test',
        'password' => bcrypt('password'),
        'email_verified_at' => now(),
        'annual_employment_income' => 480_000,
    ],
);
\App\Models\FamilyMember::factory()->for($zaProtectionUser)->count(2)->create(['is_dependent' => true]);
\App\Models\Mortgage::factory()->for($zaProtectionUser)->create(['outstanding_balance' => 800_000]);
```

Run: `php artisan db:seed --class=TestUsersSeeder --force`
Expected: user `za-protection-test@example.com` exists with income + 2 family members + 1 mortgage.

- [ ] **Step 1: Rebuild + clear cache**

Run: `npm run build && php artisan cache:clear && php artisan config:clear && php artisan route:clear`
Expected: all clean.

- [ ] **Step 2: Verify dev servers are up on :8003 / :5175 (previous session state)**

Run: `lsof -iTCP:8003 -sTCP:LISTEN -P | head -3`
Expected: php-artisan-serve process.

If not up, start: `./dev.sh` (or run on alternate ports if 8001/5174 are owned by the sibling UK repo).

- [ ] **Step 3: Run the Playwright smoke scenario**

Follow spec § 5.2 (6 steps):
1. Login as `za-protection-test@example.com` / `password` (seeded in step 0); fetch MFA code from DB as per CLAUDE.md local-dev recipe.
2. Verify sidebar shows "South Africa" → "Protection".
3. Navigate to `/za/protection`; verify all 3 tab headers.
4. **Tab 1:** add a life policy (Discovery Life, R5,000,000, R1,500/month, start date 2026-01-01, one spouse beneficiary @ 100%); verify row appears; edit cover to R6,000,000; verify update; delete; verify removal.
5. **Tab 2:** verify 4 gauges; test missing-inputs state by checking the funeral gauge when test user has no HouseholdMember; add a HouseholdMember via tinker if needed; refresh and re-verify.
6. **Tab 3:** add a dread policy (Liberty, severity B, R750,000); add 2 beneficiaries (spouse 50% + nominated_individual 50% with fake SA ID); save; attempt 60/50 split and verify client-side guard blocks save.

For EACH step: fill fields via the native-setter approach (Vue v-model compatibility), submit via `form.requestSubmit()` if reactivity is flaky. Take a screenshot after each confirmation.

- [ ] **Step 4: Verify full-suite baseline**

Run: `./vendor/bin/pest`
Expected: 2,777+ passing, 4 pre-existing failures, 2 skipped. NO new regressions.

- [ ] **Step 5: Spot-check smoke the 4 refactored modals**

In the browser, quickly open each of the 4 pre-existing ZA modals (Savings contribution, Investment form, Retirement contribution, Retirement fund) and verify:
- Opens without errors.
- Escape closes the modal.
- Tab key stays trapped within the modal.
- Submit still works (create a trivial record, delete it).

- [ ] **Step 6: Update `CLAUDE.md` metrics + final commit**

Edit `CLAUDE.md` — update the top-of-file metrics table:
- Vue Components: 700 → ~714 (+ 13 Protection components + 1 shared DialogContainer)
- Controllers: 98 → 99
- Vuex Stores: 39 → 39 (replaced placeholder, no new module)

```bash
git add CLAUDE.md
git commit -m "docs(claude-md): refresh metrics after WS 1.5b (Vue 700→714, Controllers 98→99)"
```

- [ ] **Step 7: Push all WS 1.5b commits**

```bash
git push origin main
```

Expected: all WS 1.5b commits land on `origin/main`. No force-push, no rebase.

---

## Self-review (run after all tasks drafted)

**Spec coverage (cross-ref vs `docs/superpowers/specs/2026-04-20-ws-1-5b-za-protection-design.md`):**

| Spec § | Item | Covered by Task |
|---|---|---|
| § 2.1 | `za_protection_policies` table | Task 1 |
| § 2.2 | `za_protection_beneficiaries` table | Task 2 |
| § 2.3 | Eloquent models | Tasks 3, 4 |
| § 3.1-3.2 | Controller + 11 endpoints | Task 10 |
| § 3.3 | 4 form requests | Task 7 |
| § 3.4 | 3 API resources | Task 8 |
| § 3.5 | Engine aggregate method | Task 6 |
| § 3.6 | PreviewWriteInterceptor | Task 9 test (`blocks writes from preview users`) |
| § 4.1 | Route | Task 16 (committed with Task 19) |
| § 4.2 | Sidebar registration | Task 16 (committed with Task 19) |
| § 4.3 | View | Task 17 |
| § 4.4 Tab 1 | 5 policies-tab components | Task 17 |
| § 4.4 Tab 2 | 4 coverage-gap components | Task 18 |
| § 4.4 Tab 3 | 2 beneficiaries components | Task 19 |
| § 4.5 | DialogContainer | Task 13 |
| § 4.5 | Refactor 4 prior modals | Task 14 |
| § 4.6 | Vuex module | Task 15 |
| § 4.7 | Service | Task 15 |
| § 4.8 | `toMinorZAR` usage | Task 17 (new) + Task 20 (sweep) |
| § 4.9 | Design system compliance | All frontend tasks — palette tokens, no amber/orange, no scores |
| § 5.1 | Pest tests (~30) | Tasks 6 (4), 9 (18), 11 (6), 12 (2) = 30 |
| § 5.2 | Playwright smoke | Task 21 |
| § 6 | W1 + W2 tech debt | Tasks 13, 14 (W1), 17, 20 (W2) |
| § 9 | Risk mitigations | Test user HouseholdMember noted in Task 21 step 3; joint-owner test in Task 9; sum=100 guard in Task 7 + 11 |

**Placeholder scan:** No `TBD`/`TODO`/`???` in any task. Every task has exact file paths, complete code blocks, and explicit verification commands.

**Type consistency:**
- `toMinorZAR` used consistently in Task 17 form and Task 20 refactor.
- `cover_amount_minor` / `cover_amount_major` naming stable across resource (Task 8), service (Task 15), and components (Task 17-19).
- `beneficiary_type` enum values identical across migration (Task 2), form request (Task 7), model (Task 4), resource (Task 8), components (Task 19).
- `missing_inputs` field defined in engine (Task 6), returned by resource (Task 8), consumed by `ZaMissingInputsEmptyState` (Task 18).

**Scope:** Single workstream, produces working module end-to-end. No decomposition required.

---

## Execution handoff

Plan complete and saved. 21 tasks, TDD where it pays off (engine + controller + beneficiary tests written before implementation), frequent commits, explicit verification at every checkpoint. Tech-debt W1 and W2 retired as part of the workstream.

**Workflow gate before execution:** `/prd-writer` audit — this plan should be validated against live codebase via parallel `feature-dev:code-explorer` + `feature-dev:code-architect` review, with amendments applied before implementation per the project workflow memory rule.
