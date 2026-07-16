<?php

declare(strict_types=1);

use Fynla\Packs\Gb\Database\Seeders\TaxConfigurationSeeder;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Regulatory correctness: FSCS deposit protection rose to £120,000 (£240,000
 * joint) from 1 December 2025. The active 2026/27 config previously inherited
 * the old £85,000 from getTaxConfig202526() because the 2026/27 delta block
 * never overrode it — wrong guidance on the active tax year.
 */
it('serves the 2026/27 FSCS deposit protection limits from active config', function () {
    $this->seed(TaxConfigurationSeeder::class);

    $config = app(TaxConfigService::class);

    expect($config->getSavingsConfig('fscs_deposit_protection'))->toBe(120000)
        ->and($config->getSavingsConfig('fscs_joint_protection'))->toBe(240000)
        ->and($config->getSavingsConfig('fscs_temporary_high_balance'))->toBe(1400000);
});
