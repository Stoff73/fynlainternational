<?php

declare(strict_types=1);

use App\Services\Protection\UkProtectionEngine;
use Fynla\Core\Contracts\ProtectionEngine;

beforeEach(function () {
    $this->engine = app(UkProtectionEngine::class);
});

it('implements the ProtectionEngine contract', function () {
    expect($this->engine)->toBeInstanceOf(ProtectionEngine::class);
});

it('lists UK policy types with term_life + critical_illness', function () {
    $codes = array_column($this->engine->getAvailablePolicyTypes(), 'code');

    expect($codes)->toContain('term_life', 'critical_illness', 'income_protection');
});

it('computes UK life cover at 10× income + debts', function () {
    $r = $this->engine->calculateCoverageNeeds([
        'policy_type' => 'term_life',
        'annual_income' => 5_000_000,  // £50,000
        'outstanding_debts' => 10_000_000,  // £100,000
        'dependants' => 2,
        'existing_coverage' => 0,
        'age' => 40,
    ]);

    expect($r['recommended_cover'])->toBe(60_000_000);  // 10×£50k + £100k
});

it('returns trust-based tax notes', function () {
    $t = $this->engine->getPolicyTaxTreatment('term_life');

    expect($t['notes'])->toContain('trust');
});
