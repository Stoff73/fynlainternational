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

        // WS 1.4a — Retirement
        $this->app->bind('pack.za.retirement', \Fynla\Packs\Za\Retirement\ZaRetirementEngine::class);
        $this->app->bind(
            'pack.za.retirement.contribution_split',
            \Fynla\Packs\Za\Retirement\ZaContributionSplitService::class,
        );
        $this->app->bind(
            'pack.za.retirement.savings_pot_simulator',
            \Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator::class,
        );
        $this->app->bind(
            'pack.za.retirement.buckets',
            \Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository::class,
        );

        // WS 1.4b — Annuity mechanics
        $this->app->bind(
            'pack.za.retirement.living_annuity',
            \Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator::class,
        );
        $this->app->bind(
            'pack.za.retirement.life_annuity',
            \Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator::class,
        );
        $this->app->bind(
            'pack.za.retirement.compulsory_annuitisation',
            \Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService::class,
        );

        // WS 1.4c — Reg 28 Monitor
        $this->app->bind('pack.za.reg28.monitor', \Fynla\Packs\Za\Retirement\ZaReg28Monitor::class);

        // WS 1.5 — Protection
        $this->app->bind('pack.za.protection', \Fynla\Packs\Za\Protection\ZaProtectionEngine::class);

        // WS 1.6 — Estate
        $this->app->bind('pack.za.estate', \Fynla\Packs\Za\Estate\ZaEstateEngine::class);

        // WS 1.7 — Goals & Life Events
        $this->app->bind('pack.za.goals.defaults', \Fynla\Packs\Za\Goals\ZaGoalsDefaults::class);
        $this->app->bind(
            'pack.za.goals.severance',
            \Fynla\Packs\Za\Goals\ZaSeveranceBenefitCalculator::class,
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
