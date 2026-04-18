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
}
