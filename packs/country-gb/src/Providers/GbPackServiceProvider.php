<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Providers;

use Fynla\Core\Contracts\GoalCalculationEngine;
use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\Gb\Agents\TaxOptimisationAgent;
use Fynla\Packs\Gb\Estate\UkEstateEngine;
use Fynla\Packs\Gb\ExchangeControl\UkExchangeControl;
use Fynla\Packs\Gb\Goals\GoalCalculationService;
use Fynla\Packs\Gb\Investment\UkInvestmentEngine;
use Fynla\Packs\Gb\LifeTables\GbLifeTableProvider;
use Fynla\Packs\Gb\Localisation\GbLocalisation;
use Fynla\Packs\Gb\Protection\UkProtectionEngine;
use Fynla\Packs\Gb\Query\GbPackAssetRepository;
use Fynla\Packs\Gb\Query\GbPackAssetResolver;
use Fynla\Packs\Gb\Query\GbPackEstateRepository;
use Fynla\Packs\Gb\Query\GbPackUserRelationProvider;
use Fynla\Packs\Gb\Retirement\UkRetirementEngine;
use Fynla\Packs\Gb\Savings\UkSavingsEngine;
use Fynla\Packs\Gb\Tax\TaxConfigService;
use Fynla\Packs\Gb\Validation\GbBankingValidator;
use Fynla\Packs\Gb\Validation\NinoValidator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the UK pack with the core PackRegistry and binds the 14
 * country-pack contract keys to the existing UK service classes.
 *
 * R-17 (2026-07-06): all bindings resolve to pack-local classes —
 * the UK code has not yet relocated into this pack's src/ tree. Each
 * subsequent workstream (R-3 → R-9) moves files and updates the
 * corresponding binding's FQCN to the new namespace.
 *
 * R-11 replaced the four Null bindings (Localisation, IdentityValidator,
 * BankingValidator, LifeTableProvider) with the real UK implementations
 * GbLocalisation, NinoValidator, GbBankingValidator, GbLifeTableProvider.
 */
class GbPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 9 contract bindings carried over from app/Providers/GbPackServiceProvider.
        // R-17: every FQCN below is pack-local; the legacy-namespace era is closed.
        $this->app->bind('pack.gb.tax', TaxConfigService::class);
        $this->app->bind('pack.gb.retirement', UkRetirementEngine::class);
        $this->app->bind('pack.gb.investment', UkInvestmentEngine::class);
        $this->app->bind('pack.gb.protection', UkProtectionEngine::class);
        $this->app->bind('pack.gb.estate', UkEstateEngine::class);
        $this->app->bind('pack.gb.savings', UkSavingsEngine::class);
        $this->app->bind('pack.gb.exchange_control', UkExchangeControl::class);
        $this->app->bind('pack.gb.tax_optimisation', TaxOptimisationAgent::class);

        // R-11: real GB implementations of the 4 remaining contracts.
        $this->app->bind('pack.gb.localisation', GbLocalisation::class);
        $this->app->bind('pack.gb.identity', NinoValidator::class);
        $this->app->bind('pack.gb.banking', GbBankingValidator::class);
        $this->app->bind('pack.gb.life_tables', GbLifeTableProvider::class);

        // R-14b-ii: GB implementations of the three cross-pack query
        // contracts (PackAssetRepository / PackEstateRepository /
        // PackAssetResolver). Composite defaults in CoreServiceProvider
        // iterate PackRegistry::codes() and resolve `pack.{code}.*`
        // bindings to merge results across registered packs.
        //
        // G-(-1) FR-M2: singleton(), not bind() — these resolvers are
        // stateless and called per-request from CoreServiceProvider's
        // composites, so a fresh instance per `app()->make()` is wasted
        // allocation. Singleton-identity asserted in PackBindingSingletonTest.
        $this->app->singleton('pack.gb.asset_repo', GbPackAssetRepository::class);
        $this->app->singleton('pack.gb.estate_repo', GbPackEstateRepository::class);
        $this->app->singleton('pack.gb.asset_resolver', GbPackAssetResolver::class);

        // R-14b-vii-prep: user-scoped relation provider. Returns full
        // Eloquent Models for User's per-module hasMany/hasOne relations
        // (Protection, Property, Investment, Savings, Pensions, etc.) so
        // core User can resolve them through the contract instead of
        // holding pack-namespaced `hasMany` literals.
        //
        // G-(-1) FR-M2: singleton() — same rationale as the query-layer
        // bindings above.
        $this->app->singleton('pack.gb.user_relations', GbPackUserRelationProvider::class);

        // R-14b-v: bind the GoalCalculationEngine contract to the GB
        // pack's concrete service. Core Goal's accessor methods
        // (progress_percentage, days_remaining, milestones, etc.) resolve
        // the contract from the container — keeps the jurisdiction-
        // specific rules out of the core model.
        $this->app->bind(
            GoalCalculationEngine::class,
            GoalCalculationService::class,
        );
    }

    public function boot(PackRegistry $registry): void
    {
        // R-4: polymorphic *_type columns store the model FQCN. A one-shot
        // data migration converts legacy pre-relocation model FQCN values to the
        // relocated Fynla\Packs\Gb\Models\X namespace
        // (database/migrations/…_backfill_polymorphic_morph_map_aliases.php).
        // A morph map is intentionally NOT registered here — it would
        // require every write to use an alias, breaking existing test data
        // and any third-party code that constructs morph rows by FQCN.

        // Idempotent: skip if another call path already registered GB
        // (e.g. if a test boots the framework twice).
        if ($registry->isEnabled('gb')) {
            return;
        }

        $registry->register(CorePackManifest::fromArray([
            'code' => 'gb',
            'name' => 'United Kingdom',
            'currency' => 'GBP',
            'locale' => 'en_GB',
            // Tables stay unprefixed for Phase 1 (architecture-plan-v3.md § 7).
            'table_prefix' => '',
            // Sidebar comes from per-pack navigation() in WS R-12.
            'navigation' => [],
            // UK routes mount under /api/gb/* in WS R-9 + R-14.
            'routes' => [],
        ]));

        // R-14: GB pack routes mount under /api/gb/* (Option X prefix).
        // Legacy /api/{module} URLs are rewritten transparently to
        // /api/gb/{module} by Fynla\Core\Http\Middleware\LegacyApiRewrite,
        // wired in the global $middleware stack so the rewrite happens
        // before route matching. Existing clients (mobile + web) keep
        // working unchanged for the 60-day deprecation window.
        Route::middleware('api')
            ->prefix('api/gb')
            ->group(__DIR__.'/../../routes/api.php');

        // R-10: pack-owned migrations (UK schema alters and creates).
        $this->loadMigrationsFrom(__DIR__.'/../../database/migrations');
    }
}
