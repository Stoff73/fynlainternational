<?php

declare(strict_types=1);

namespace Fynla\Packs\Za\Providers;

use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\Za\Banking\ZaBankingValidator;
use Fynla\Packs\Za\Estate\ZaEstateEngine;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControl;
use Fynla\Packs\Za\ExchangeControl\ZaExchangeControlLedger;
use Fynla\Packs\Za\Goals\ZaGoalsDefaults;
use Fynla\Packs\Za\Goals\ZaSeveranceBenefitCalculator;
use Fynla\Packs\Za\Identity\ZaIdValidator;
use Fynla\Packs\Za\Investment\ZaBaseCostTracker;
use Fynla\Packs\Za\Investment\ZaCgtCalculator;
use Fynla\Packs\Za\Investment\ZaInvestmentEngine;
use Fynla\Packs\Za\Localisation\ZaLocalisation;
use Fynla\Packs\Za\Protection\ZaProtectionEngine;
use Fynla\Packs\Za\Query\ZaPackAssetRepository;
use Fynla\Packs\Za\Query\ZaPackAssetResolver;
use Fynla\Packs\Za\Query\ZaPackEstateRepository;
use Fynla\Packs\Za\Query\ZaPackUserRelationProvider;
use Fynla\Packs\Za\Retirement\ZaCompulsoryAnnuitisationService;
use Fynla\Packs\Za\Retirement\ZaContributionSplitService;
use Fynla\Packs\Za\Retirement\ZaLifeAnnuityCalculator;
use Fynla\Packs\Za\Retirement\ZaLivingAnnuityCalculator;
use Fynla\Packs\Za\Retirement\ZaReg28Monitor;
use Fynla\Packs\Za\Retirement\ZaRetirementEngine;
use Fynla\Packs\Za\Retirement\ZaRetirementFundBucketRepository;
use Fynla\Packs\Za\Retirement\ZaSavingsPotWithdrawalSimulator;
use Fynla\Packs\Za\Savings\ZaEmergencyFundCalculator;
use Fynla\Packs\Za\Savings\ZaSavingsEngine;
use Fynla\Packs\Za\Savings\ZaTfsaContributionTracker;
use Fynla\Packs\Za\Support\PackManifest;
use Fynla\Packs\Za\Tax\ZaTaxConfigService;
use Fynla\Packs\Za\Tax\ZaTaxEngine;
use Illuminate\Support\Facades\Route;
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
        $this->app->bind('pack.za.savings', ZaSavingsEngine::class);
        $this->app->bind(
            'pack.za.tfsa.tracker',
            ZaTfsaContributionTracker::class,
        );
        $this->app->bind(
            'pack.za.savings.emergency_fund',
            ZaEmergencyFundCalculator::class,
        );

        // WS 1.3a — Investment
        $this->app->bind('pack.za.investment', ZaInvestmentEngine::class);
        $this->app->bind(
            'pack.za.investment.cgt',
            ZaCgtCalculator::class,
        );
        $this->app->bind(
            'pack.za.investment.lot_tracker',
            ZaBaseCostTracker::class,
        );

        // WS 1.3b — Exchange Control
        $this->app->bind(
            'pack.za.exchange_control',
            ZaExchangeControl::class,
        );
        $this->app->bind(
            'pack.za.exchange_control.ledger',
            ZaExchangeControlLedger::class,
        );

        // WS 1.4a — Retirement
        $this->app->bind('pack.za.retirement', ZaRetirementEngine::class);
        $this->app->bind(
            'pack.za.retirement.contribution_split',
            ZaContributionSplitService::class,
        );
        $this->app->bind(
            'pack.za.retirement.savings_pot_simulator',
            ZaSavingsPotWithdrawalSimulator::class,
        );
        $this->app->bind(
            'pack.za.retirement.buckets',
            ZaRetirementFundBucketRepository::class,
        );

        // WS 1.4b — Annuity mechanics
        $this->app->bind(
            'pack.za.retirement.living_annuity',
            ZaLivingAnnuityCalculator::class,
        );
        $this->app->bind(
            'pack.za.retirement.life_annuity',
            ZaLifeAnnuityCalculator::class,
        );
        $this->app->bind(
            'pack.za.retirement.compulsory_annuitisation',
            ZaCompulsoryAnnuitisationService::class,
        );

        // WS 1.4c — Reg 28 Monitor
        $this->app->bind('pack.za.reg28.monitor', ZaReg28Monitor::class);

        // WS 1.5 — Protection
        $this->app->bind('pack.za.protection', ZaProtectionEngine::class);

        // WS 1.6 — Estate
        $this->app->bind('pack.za.estate', ZaEstateEngine::class);

        // WS 1.7 — Goals & Life Events
        $this->app->bind('pack.za.goals.defaults', ZaGoalsDefaults::class);
        $this->app->bind(
            'pack.za.goals.severance',
            ZaSeveranceBenefitCalculator::class,
        );

        // WS 1.8 — Localisation + Identity + Banking
        $this->app->bind('pack.za.localisation', ZaLocalisation::class);
        $this->app->bind('pack.za.identity', ZaIdValidator::class);
        $this->app->bind('pack.za.banking', ZaBankingValidator::class);

        // R-14b-iii: Null implementations of the three cross-pack query
        // contracts. Bound for structural symmetry with the GB pack so
        // CompositePack*::iterate(PackRegistry::codes()) finds a real
        // implementation under pack.za.* rather than silently skipping.
        // Real implementations land per-model with WS 1.2/1.3/1.4
        // feature workstreams that follow R-14b.
        $this->app->bind('pack.za.asset_repo', ZaPackAssetRepository::class);
        $this->app->bind('pack.za.estate_repo', ZaPackEstateRepository::class);
        $this->app->bind('pack.za.asset_resolver', ZaPackAssetResolver::class);

        // R-14b-vii-prep: Null user-relation provider for SA. Phase 1 SA
        // doesn't model UK-equivalent per-module surfaces; bound for
        // structural symmetry so the composite finds a real implementation
        // under `pack.za.user_relations` rather than silently skipping.
        $this->app->bind('pack.za.user_relations', ZaPackUserRelationProvider::class);
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

        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');

        // Pack-owned routes (mirrors GbPackServiceProvider). The route file
        // carries its own /za prefix + auth/jurisdiction middleware; core
        // routes/api.php no longer knows the ZA pack exists.
        Route::middleware('api')
            ->prefix('api')
            ->group(__DIR__.'/../../routes/api.php');
    }
}
