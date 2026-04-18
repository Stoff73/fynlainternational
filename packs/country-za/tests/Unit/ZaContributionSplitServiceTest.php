<?php

declare(strict_types=1);

use Fynla\Packs\Za\Retirement\ZaContributionSplitService;

beforeEach(function () {
    $this->splitter = app(ZaContributionSplitService::class);
});

const TWO_POT_EFFECTIVE = '2024-09-01';

it('splits a post-2024-09-01 contribution 1/3 savings / 2/3 retirement', function () {
    $r = $this->splitter->split(3_000_000, '2026-05-10');

    expect($r['vested_delta_minor'])->toBe(0);
    expect($r['savings_delta_minor'])->toBe(1_000_000);
    expect($r['retirement_delta_minor'])->toBe(2_000_000);
});

it('allocates pre-2024-09-01 contributions 100% to vested', function () {
    $r = $this->splitter->split(3_000_000, '2024-07-15');

    expect($r['vested_delta_minor'])->toBe(3_000_000);
    expect($r['savings_delta_minor'])->toBe(0);
    expect($r['retirement_delta_minor'])->toBe(0);
});

it('splits exactly on the Two-Pot effective date', function () {
    $r = $this->splitter->split(3_000_000, TWO_POT_EFFECTIVE);

    expect($r['savings_delta_minor'])->toBe(1_000_000);
    expect($r['retirement_delta_minor'])->toBe(2_000_000);
    expect($r['vested_delta_minor'])->toBe(0);
});

it('handles rounding drift — splits are exact integers that sum to the contribution', function () {
    $r = $this->splitter->split(100_000, '2026-05-10');

    expect($r['savings_delta_minor'] + $r['retirement_delta_minor'] + $r['vested_delta_minor'])
        ->toBe(100_000);
});

it('rejects negative contributions', function () {
    expect(fn () => $this->splitter->split(-1_000, '2026-05-10'))
        ->toThrow(InvalidArgumentException::class);
});
