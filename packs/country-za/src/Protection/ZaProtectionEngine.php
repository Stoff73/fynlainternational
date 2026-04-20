<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Protection;

use Fynla\Core\Contracts\ProtectionEngine;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use InvalidArgumentException;

/**
 * SA ProtectionEngine — life, dread, income-protection, funeral.
 *
 * Spec § 6: SA replaces UK's Critical Illness with Dread Disease
 * (severity-tiered payouts per ASISA SCIDEP A/B/C/D), income protection
 * at typical 75% gross cap, funeral cover (new category — small policy
 * covering extended family).
 *
 * Coverage heuristics per § 6.3:
 *   - Life: capitalise dependants + bond + education + estate costs –
 *     existing liquid assets – group life.
 *   - Income protection: 75% of gross pay (caller supplies gross).
 *   - Dread disease: 1–3× annual salary (engine returns 2× as
 *     recommendation, 1× as minimum).
 *   - Funeral: R10k–R100k per life; engine returns default R30k per
 *     dependant + member.
 *
 * Tax treatment (§ 6.2): life / dread / funeral payouts are generally
 * tax-free to nominated beneficiaries. Income protection premiums are
 * not deductible (post-2015); payouts are tax-free.
 */
class ZaProtectionEngine implements ProtectionEngine
{
    private const DREAD_RECOMMENDED_MULTIPLE = 2;
    private const DREAD_MINIMUM_MULTIPLE = 1;
    private const LIFE_INCOME_MULTIPLE = 10;  // capitalise 10× gross as rough proxy
    private const LIFE_MINIMUM_MULTIPLE = 5;
    private const FUNERAL_PER_LIFE_MINOR = 3_000_000;  // R30,000 default

    public function __construct(
        private readonly ZaTaxConfigService $config,
    ) {
    }

    public function getAvailablePolicyTypes(): array
    {
        return [
            ['code' => 'life', 'name' => 'Life cover', 'description' => 'Level or decreasing term life', 'category' => 'life'],
            ['code' => 'whole_of_life', 'name' => 'Whole-of-life', 'description' => 'Less common in SA retail', 'category' => 'life'],
            ['code' => 'dread', 'name' => 'Dread disease', 'description' => 'Severity-tiered payouts (ASISA SCIDEP A/B/C/D)', 'category' => 'health'],
            ['code' => 'idisability_lump', 'name' => 'Lump-sum disability', 'description' => 'Pays on permanent disability', 'category' => 'health'],
            ['code' => 'idisability_income', 'name' => 'Income protection', 'description' => 'Typically % of gross; 2-year or to-age-65', 'category' => 'income'],
            ['code' => 'funeral', 'name' => 'Funeral cover', 'description' => 'Small short-term policy for family', 'category' => 'funeral'],
        ];
    }

    public function calculateCoverageNeeds(array $params): array
    {
        $type = $params['policy_type'] ?? '';
        $income = (int) ($params['annual_income'] ?? 0);
        $debts = (int) ($params['outstanding_debts'] ?? 0);
        $dependants = (int) ($params['dependants'] ?? 0);
        $existing = (int) ($params['existing_coverage'] ?? 0);

        if ($income < 0 || $debts < 0 || $dependants < 0 || $existing < 0) {
            throw new InvalidArgumentException('Coverage inputs cannot be negative.');
        }

        return match ($type) {
            'life', 'whole_of_life' => $this->lifeCoverNeeds($income, $debts, $dependants, $existing),
            'dread' => $this->dreadCoverNeeds($income, $existing),
            'idisability_income' => $this->incomeProtectionNeeds($income, $existing),
            'idisability_lump' => $this->dreadCoverNeeds($income, $existing),  // same shape, different policy
            'funeral' => $this->funeralCoverNeeds($dependants, $existing),
            default => throw new InvalidArgumentException("Unknown policy_type '{$type}'."),
        };
    }

    public function getPolicyTaxTreatment(string $policyType): array
    {
        return match ($policyType) {
            'life', 'whole_of_life' => [
                'premiums_deductible' => false,
                'payout_taxable' => false,
                'trust_treatment' => 'nominated_beneficiary_or_testamentary_trust',
                'notes' => 'Policies payable to a nominated beneficiary with correct wording are not dutiable under s3(3)(a)(ii). Payouts to the estate are dutiable.',
            ],
            'dread', 'idisability_lump' => [
                'premiums_deductible' => false,
                'payout_taxable' => false,
                'trust_treatment' => 'nominated_beneficiary',
                'notes' => 'Lump-sum payouts are tax-free.',
            ],
            'idisability_income' => [
                'premiums_deductible' => false,
                'payout_taxable' => false,
                'trust_treatment' => 'nominated_beneficiary',
                'notes' => 'Premiums no longer deductible (post-2015 rule change). Payouts are tax-free.',
            ],
            'funeral' => [
                'premiums_deductible' => false,
                'payout_taxable' => false,
                'trust_treatment' => 'nominated_beneficiary',
                'notes' => 'Small short-term policies. Payouts tax-free to nominated beneficiary.',
            ],
            default => throw new InvalidArgumentException("Unknown policy_type '{$policyType}'."),
        };
    }

    /**
     * @return array{recommended_cover: int, minimum_cover: int, shortfall: int, rationale: string}
     */
    private function lifeCoverNeeds(int $income, int $debts, int $dependants, int $existing): array
    {
        $dependantCapital = $income * self::LIFE_INCOME_MULTIPLE;
        $recommended = $dependantCapital + $debts;
        $minimum = ($income * self::LIFE_MINIMUM_MULTIPLE) + $debts;

        return [
            'recommended_cover' => $recommended,
            'minimum_cover' => $minimum,
            'shortfall' => max(0, $recommended - $existing),
            'rationale' => "Capitalise {$dependants} dependants at 10× annual income plus outstanding debts. Existing cover {$existing} applied.",
        ];
    }

    /**
     * @return array{recommended_cover: int, minimum_cover: int, shortfall: int, rationale: string}
     */
    private function dreadCoverNeeds(int $income, int $existing): array
    {
        $recommended = $income * self::DREAD_RECOMMENDED_MULTIPLE;
        $minimum = $income * self::DREAD_MINIMUM_MULTIPLE;

        return [
            'recommended_cover' => $recommended,
            'minimum_cover' => $minimum,
            'shortfall' => max(0, $recommended - $existing),
            'rationale' => 'Dread disease cover typically 1-3× annual salary.',
        ];
    }

    /**
     * @return array{recommended_cover: int, minimum_cover: int, shortfall: int, rationale: string}
     */
    private function incomeProtectionNeeds(int $annualIncome, int $existing): array
    {
        // Annual benefit = 75% of gross income; recommended monthly equiv.
        $annualBenefit = (int) round($annualIncome * 0.75);

        return [
            'recommended_cover' => $annualBenefit,
            'minimum_cover' => (int) round($annualIncome * 0.50),
            'shortfall' => max(0, $annualBenefit - $existing),
            'rationale' => 'Income protection capped at typical 75% of gross pay.',
        ];
    }

    /**
     * @return array{recommended_cover: int, minimum_cover: int, shortfall: int, rationale: string}
     */
    private function funeralCoverNeeds(int $dependants, int $existing): array
    {
        $lives = $dependants + 1;  // member + dependants
        $recommended = $lives * self::FUNERAL_PER_LIFE_MINOR;

        return [
            'recommended_cover' => $recommended,
            'minimum_cover' => (int) round($recommended * 0.33),
            'shortfall' => max(0, $recommended - $existing),
            'rationale' => "R30,000 per life × {$lives} lives (member + {$dependants} dependants).",
        ];
    }

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
}
