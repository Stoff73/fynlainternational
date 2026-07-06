<?php

declare(strict_types=1);

use Fynla\Core\Contracts\BankingValidator;
use Fynla\Core\Contracts\EstateEngine;
use Fynla\Core\Contracts\ExchangeControl;
use Fynla\Core\Contracts\IdentityValidator;
use Fynla\Core\Contracts\InvestmentEngine;
use Fynla\Core\Contracts\LifeTableProvider;
use Fynla\Core\Contracts\Localisation;
use Fynla\Core\Contracts\PackAssetRepository;
use Fynla\Core\Contracts\PackAssetResolver;
use Fynla\Core\Contracts\PackEstateRepository;
use Fynla\Core\Contracts\PackUserRelationProvider;
use Fynla\Core\Contracts\ProtectionEngine;
use Fynla\Core\Contracts\RetirementEngine;
use Fynla\Core\Contracts\SavingsEngine;
use Fynla\Core\Contracts\TaxEngine;
use Fynla\Core\Contracts\TaxOptimisationEngine;

/**
 * Test Gauntlet G-2-c — pack service-provider binding walk.
 *
 * Every pack.gb.* container key must resolve to an instance of the core
 * contract it claims to implement. This is the post-R-17 completion of the
 * old GbPackServiceProviderTest, which only covered 5 of the 16 bindings.
 */
dataset('gb bindings', [
    ['pack.gb.tax', TaxEngine::class],
    ['pack.gb.retirement', RetirementEngine::class],
    ['pack.gb.investment', InvestmentEngine::class],
    ['pack.gb.protection', ProtectionEngine::class],
    ['pack.gb.estate', EstateEngine::class],
    ['pack.gb.savings', SavingsEngine::class],
    ['pack.gb.exchange_control', ExchangeControl::class],
    ['pack.gb.tax_optimisation', TaxOptimisationEngine::class],
    ['pack.gb.localisation', Localisation::class],
    ['pack.gb.identity', IdentityValidator::class],
    ['pack.gb.banking', BankingValidator::class],
    ['pack.gb.life_tables', LifeTableProvider::class],
    ['pack.gb.asset_repo', PackAssetRepository::class],
    ['pack.gb.estate_repo', PackEstateRepository::class],
    ['pack.gb.asset_resolver', PackAssetResolver::class],
    ['pack.gb.user_relations', PackUserRelationProvider::class],
]);

it('resolves every pack.gb binding to its core contract', function (string $key, string $contract) {
    expect(app()->bound($key))->toBeTrue("Expected {$key} to be bound");
    expect(app($key))->toBeInstanceOf($contract);
})->with('gb bindings');

it('resolves the four query-layer bindings as singletons (same instance)', function (string $key) {
    // These four are bound with singleton() — the PackUserRelationProvider et al.
    // must share one instance so their request-scoped state is consistent
    // (the G-(-1) singleton fix).
    expect(app($key))->toBe(app($key));
})->with([
    'pack.gb.asset_repo',
    'pack.gb.estate_repo',
    'pack.gb.asset_resolver',
    'pack.gb.user_relations',
]);
