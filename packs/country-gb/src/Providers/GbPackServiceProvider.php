<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Providers;

use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\Gb\LifeTables\GbLifeTableProvider;
use Fynla\Packs\Gb\Localisation\GbLocalisation;
use Fynla\Packs\Gb\Validation\GbBankingValidator;
use Fynla\Packs\Gb\Validation\NinoValidator;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the UK pack with the core PackRegistry and binds the 14
 * country-pack contract keys to the existing UK service classes.
 *
 * Phase 1 state: bindings still resolve to \App\Services\… because
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
        // FQCNs still point at \App\…; updated in-place as files move.
        $this->app->bind('pack.gb.tax', \Fynla\Packs\Gb\Tax\TaxConfigService::class);
        $this->app->bind('pack.gb.retirement', \Fynla\Packs\Gb\Retirement\UkRetirementEngine::class);
        $this->app->bind('pack.gb.investment', \Fynla\Packs\Gb\Investment\UkInvestmentEngine::class);
        $this->app->bind('pack.gb.protection', \Fynla\Packs\Gb\Protection\UkProtectionEngine::class);
        $this->app->bind('pack.gb.estate', \Fynla\Packs\Gb\Estate\UkEstateEngine::class);
        $this->app->bind('pack.gb.savings', \Fynla\Packs\Gb\Savings\UkSavingsEngine::class);
        $this->app->bind('pack.gb.exchange_control', \App\Services\ExchangeControl\UkExchangeControl::class);
        $this->app->bind('pack.gb.tax_optimisation', \App\Agents\TaxOptimisationAgent::class);

        // R-11: real GB implementations of the 4 remaining contracts.
        $this->app->bind('pack.gb.localisation', GbLocalisation::class);
        $this->app->bind('pack.gb.identity', NinoValidator::class);
        $this->app->bind('pack.gb.banking', GbBankingValidator::class);
        $this->app->bind('pack.gb.life_tables', GbLifeTableProvider::class);
    }

    public function boot(PackRegistry $registry): void
    {
        // R-4: polymorphic *_type columns store the model FQCN. A one-shot
        // data migration converts legacy App\Models\X values to the
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
