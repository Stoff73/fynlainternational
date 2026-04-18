<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Providers;

use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\Za\Support\PackManifest;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the SA pack with the core PackRegistry, exposes ZaTaxEngine
 * as pack.za.tax, and loads pack-owned migrations.
 *
 * ZaTaxConfigService is bound as a singleton so its request-scoped cache
 * is shared across calls. ZaTaxEngine resolves the config service via
 * constructor injection.
 */
class ZaPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ZaTaxConfigService::class);
        $this->app->bind('pack.za.tax', ZaTaxEngine::class);

        // WS 1.2a — Savings
        $this->app->bind('pack.za.savings', \Fynla\Packs\Za\Savings\ZaSavingsEngine::class);
        $this->app->bind(
            'pack.za.tfsa.tracker',
            \Fynla\Packs\Za\Savings\ZaTfsaContributionTracker::class,
        );
        $this->app->bind(
            'pack.za.savings.emergency_fund',
            \Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator::class,
        );

        // WS 1.3a — Investment
        $this->app->bind('pack.za.investment', \Fynla\Packs\Za\Investment\ZaInvestmentEngine::class);
        $this->app->bind(
            'pack.za.investment.cgt',
            \Fynla\Packs\Za\Investment\ZaCgtCalculator::class,
        );
        $this->app->bind(
            'pack.za.investment.lot_tracker',
            \Fynla\Packs\Za\Investment\ZaBaseCostTracker::class,
        );

        // WS 1.3b — Exchange Control
        $this->app->bind(
            'pack.za.exchange_control',
            \Fynla\Packs\Za\ExchangeControl\ZaExchangeControl::class,
        );
        $this->app->bind(
            'pack.za.exchange_control.ledger',
            \Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger::class,
        );
    }

    public function boot(): void
    {
        /** @var PackRegistry $registry */
        $registry = $this->app->make(PackRegistry::class);

        // Idempotent — avoid RuntimeException when the provider boots twice
        // (e.g. test bootstrap after package discovery).
        if (! $registry->isEnabled('za')) {
            $registry->register(CorePackManifest::fromArray(PackManifest::describe()));
        }

        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
