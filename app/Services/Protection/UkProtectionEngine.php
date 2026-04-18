<?php

declare(strict_types=1);

namespace App\Services\Protection;

use Fynla\Core\Contracts\ProtectionEngine;

/**
 * UK-side ProtectionEngine stub.
 *
 * UK protection catalogue: term life, whole-of-life, critical illness,
 * income protection (PHI), family income benefit. Full coverage-needs
 * logic lives in existing UK services; this stub satisfies the contract
 * with minimal rule-of-thumb heuristics.
 */
class UkProtectionEngine implements ProtectionEngine
{
    public function getAvailablePolicyTypes(): array
    {
        return [
            ['code' => 'term_life', 'name' => 'Term life', 'description' => 'Level or decreasing term', 'category' => 'life'],
            ['code' => 'whole_of_life', 'name' => 'Whole-of-life', 'description' => 'Permanent cover', 'category' => 'life'],
            ['code' => 'critical_illness', 'name' => 'Critical illness', 'description' => 'Lump sum on diagnosis', 'category' => 'health'],
            ['code' => 'income_protection', 'name' => 'Income protection (PHI)', 'description' => 'Monthly benefit on disability', 'category' => 'income'],
            ['code' => 'family_income_benefit', 'name' => 'Family income benefit', 'description' => 'Monthly benefit to age X', 'category' => 'life'],
        ];
    }

    public function calculateCoverageNeeds(array $params): array
    {
        $income = (int) ($params['annual_income'] ?? 0);
        $debts = (int) ($params['outstanding_debts'] ?? 0);
        $existing = (int) ($params['existing_coverage'] ?? 0);

        $recommended = ($income * 10) + $debts;

        return [
            'recommended_cover' => $recommended,
            'minimum_cover' => (int) round($recommended * 0.5),
            'shortfall' => max(0, $recommended - $existing),
            'rationale' => 'UK rule of thumb: 10× annual income + outstanding debts.',
        ];
    }

    public function getPolicyTaxTreatment(string $policyType): array
    {
        return [
            'premiums_deductible' => false,
            'payout_taxable' => false,
            'trust_treatment' => 'trust_or_nominated_beneficiary',
            'notes' => 'Policies in trust fall outside the estate for IHT.',
        ];
    }
}
