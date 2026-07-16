<?php

declare(strict_types=1);

use Fynla\Core\Exceptions\FinancialCalculationException;
use Fynla\Core\Models\User;
use Fynla\Packs\Gb\Investment\ScenarioService;
use Fynla\Packs\Gb\Jobs\RunMonteCarloSimulation;
use Fynla\Packs\Gb\Models\Investment\InvestmentScenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;

uses(RefreshDatabase::class);

/**
 * ScenarioService::runScenario dispatched RunMonteCarloSimulation with a single
 * array where the job expects positional scalars (start value, contribution,
 * return, volatility, years, ...) — a guaranteed TypeError that Larastan had
 * caught and the baseline entombed. Fix: map + unit-convert the scenario params;
 * fail with a clear domain error when required inputs are absent.
 */
it('throws a clear domain error when scenario parameters are incomplete', function () {
    $user = User::factory()->create();
    $scenario = InvestmentScenario::factory()->create([
        'user_id' => $user->id,
        'scenario_type' => 'custom',
        'parameters' => ['time_horizon_years' => 20], // no start_value / return / volatility
    ]);

    expect(fn () => app(ScenarioService::class)->runScenario($scenario))
        ->toThrow(FinancialCalculationException::class);
});

it('dispatches Monte Carlo with percent parameters converted to decimals', function () {
    Bus::fake();

    $user = User::factory()->create();
    $scenario = InvestmentScenario::factory()->create([
        'user_id' => $user->id,
        'scenario_type' => 'custom',
        'parameters' => [
            'start_value' => 100000,
            'monthly_contribution' => 500,
            'annual_return_percent' => 7.0,
            'volatility_percent' => 15.0,
            'time_horizon_years' => 20,
            'simulations' => 1000,
        ],
    ]);

    app(ScenarioService::class)->runScenario($scenario);

    Bus::assertDispatched(RunMonteCarloSimulation::class, function ($job) {
        $read = function (string $name) use ($job) {
            $property = new ReflectionProperty($job, $name);
            $property->setAccessible(true);

            return $property->getValue($job);
        };

        return abs($read('expectedReturn') - 0.07) < 1e-9
            && abs($read('volatility') - 0.15) < 1e-9
            && (float) $read('startValue') === 100000.0
            && (int) $read('years') === 20;
    });
});
