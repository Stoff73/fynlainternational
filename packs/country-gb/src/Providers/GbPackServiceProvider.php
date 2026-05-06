<?php

declare(strict_types=1);

namespace Fynla\Packs\Gb\Providers;

use Fynla\Core\LifeTables\NullLifeTableProvider;
use Fynla\Core\Localisation\NullLocalisation;
use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Core\Validation\NullBankingValidator;
use Fynla\Core\Validation\NullIdentityValidator;
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
 * The four contracts without a real UK implementation today
 * (Localisation, IdentityValidator, BankingValidator, LifeTableProvider)
 * resolve to core's Null implementations. R-11 replaces them with
 * GbLocalisation / NinoValidator / GbBankingValidator / GbLifeTableProvider.
 */
class GbPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // 9 contract bindings carried over from app/Providers/GbPackServiceProvider.
        // FQCNs still point at \App\…; updated in-place as files move.
        $this->app->bind('pack.gb.tax', \App\Services\TaxConfigService::class);
        $this->app->bind('pack.gb.retirement', \App\Services\Retirement\UkRetirementEngine::class);
        $this->app->bind('pack.gb.investment', \App\Services\Investment\UkInvestmentEngine::class);
        $this->app->bind('pack.gb.protection', \App\Services\Protection\UkProtectionEngine::class);
        $this->app->bind('pack.gb.estate', \App\Services\Estate\UkEstateEngine::class);
        $this->app->bind('pack.gb.savings', \App\Services\Savings\UkSavingsEngine::class);
        $this->app->bind('pack.gb.exchange_control', \App\Services\ExchangeControl\UkExchangeControl::class);
        $this->app->bind('pack.gb.tax_optimisation', \App\Agents\TaxOptimisationAgent::class);

        // 4 contracts where the GB pack does not yet have a real
        // implementation. Null implementations live in core and are
        // shared across packs that haven't built out the surface yet.
        // R-11 replaces these GB bindings with real classes.
        $this->app->bind('pack.gb.localisation', NullLocalisation::class);
        $this->app->bind('pack.gb.identity', NullIdentityValidator::class);
        $this->app->bind('pack.gb.banking', NullBankingValidator::class);
        $this->app->bind('pack.gb.life_tables', NullLifeTableProvider::class);
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
    }
}
