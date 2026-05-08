<?php

declare(strict_types=1);

use Fynla\Core\Contracts\LifeTableProvider;
use Fynla\Packs\Gb\Database\Seeders\ActuarialLifeTablesSeeder;
use Fynla\Packs\Gb\LifeTables\GbLifeTableProvider;

beforeEach(function () {
    $this->seed(ActuarialLifeTablesSeeder::class);
    $this->provider = new GbLifeTableProvider();
});

it('implements the LifeTableProvider contract', function () {
    expect($this->provider)->toBeInstanceOf(LifeTableProvider::class);
});

it('resolves from the container with the GB life-tables binding', function () {
    expect(app('pack.gb.life_tables'))->toBeInstanceOf(GbLifeTableProvider::class);
});

it('returns the exact ONS life expectancy at a seeded age', function () {
    // Construct a DOB that lands exactly on age 65 today.
    $dob = (new DateTimeImmutable('-65 years'))->format('Y-m-d');

    // ONS 2020-2022 male life expectancy at 65 = 18.3 years.
    expect($this->provider->getLifeExpectancy($dob, 'male'))->toBe(18.3);

    // Female 65 = 20.9.
    expect($this->provider->getLifeExpectancy($dob, 'female'))->toBe(20.9);
});

it('linearly interpolates life expectancy between seeded ages', function () {
    // Age 67 sits between seeded rows at 65 (male LE 18.3) and 70 (LE 14.6).
    // Linear: 18.3 + (14.6 - 18.3) * (2 / 5) = 18.3 - 1.48 = 16.82.
    $dob = (new DateTimeImmutable('-67 years'))->format('Y-m-d');

    expect($this->provider->getLifeExpectancy($dob, 'male'))
        ->toBeGreaterThan(14.6)
        ->toBeLessThan(18.3);
});

it('returns 1.0 when current age >= target age', function () {
    expect($this->provider->getSurvivalProbability(70, 70, 'male'))->toBe(1.0);
    expect($this->provider->getSurvivalProbability(70, 65, 'male'))->toBe(1.0);
});

it('returns a survival probability between 0 and 1 for plausible ranges', function () {
    $p = $this->provider->getSurvivalProbability(65, 80, 'male');

    expect($p)->toBeGreaterThan(0.0)
        ->toBeLessThan(1.0);

    // Survival to a more distant target should be lower than survival to a closer one.
    $pNear = $this->provider->getSurvivalProbability(65, 70, 'male');
    expect($p)->toBeLessThan($pNear);
});

it('treats unknown gender values as male (defensive default)', function () {
    $dob = (new DateTimeImmutable('-65 years'))->format('Y-m-d');

    expect($this->provider->getLifeExpectancy($dob, 'unknown'))->toBe(18.3);
});
