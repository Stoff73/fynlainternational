<?php

declare(strict_types=1);

namespace App\Providers;

use Fynla\Core\Registry\PackManifest;
use Fynla\Core\Registry\PackRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Registers the existing UK services under the pack.gb.* container keys
 * and declares the GB pack to the core PackRegistry.
 *
 * No UK files move. This provider is additive — UK code continues to resolve
 * via its existing class names too.
 *
 * Contract gap (documented): pack.gb.localisation, pack.gb.identity,
 * pack.gb.banking, pack.gb.life_tables are intentionally NOT bound here. The
 * equivalent UK classes (GbLocalisation, NationalInsuranceValidator,
 * UkBankingValidator, UkLifeTableProvider) don't exist yet. They'll be added
 * as the UK side catches up with the ZaLocalisation / ZaIdValidator /
 * ZaBankingValidator / ZaLifeTableProvider work in Phase 1.7–1.8.
 */
class GbPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind('pack.gb.tax', \App\Services\TaxConfigService::class);
        $this->app->bind('pack.gb.retirement', \App\Agents\RetirementAgent::class);
        $this->app->bind('pack.gb.investment', \App\Services\Investment\UkInvestmentEngine::class);
        $this->app->bind('pack.gb.protection', \App\Agents\ProtectionAgent::class);
        $this->app->bind('pack.gb.estate', \App\Agents\EstateAgent::class);
        $this->app->bind('pack.gb.savings', \App\Services\Savings\UkSavingsEngine::class);
        $this->app->bind('pack.gb.exchange_control', \App\Services\ExchangeControl\UkExchangeControl::class);
    }

    public function boot(PackRegistry $registry): void
    {
        // Idempotent: skip if another call path already registered GB.
        if ($registry->isEnabled('gb')) {
            return;
        }

        $registry->register(PackManifest::fromArray([
            'code' => 'gb',
            'name' => 'United Kingdom',
            'currency' => 'GBP',
            'locale' => 'en_GB',
            // UK tables are unprefixed per the ADR-007 table-prefix deferral.
            'table_prefix' => '',
            // Sidebar already knows UK modules; Workstream 0.5 composes it
            // from the user's active jurisdiction state.
            'navigation' => [],
            // UK routes stay at /api/* unprefixed.
            'routes' => [],
        ]));
    }
}
