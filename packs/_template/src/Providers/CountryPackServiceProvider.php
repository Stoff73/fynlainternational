<?php

declare(strict_types=1);

namespace Fynla\Packs\XX\Providers;

use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\XX\Support\PackManifest;
use Illuminate\Support\ServiceProvider;

class CountryPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind pack-specific implementations to core contracts
        // Example:
        // $this->app->bind("pack.xx.tax", \Fynla\Packs\XX\Tax\TaxEngine::class);
        // $this->app->bind("pack.xx.retirement", \Fynla\Packs\XX\Retirement\RetirementEngine::class);
        // $this->app->bind("pack.xx.investment", \Fynla\Packs\XX\Investment\InvestmentEngine::class);
        // $this->app->bind("pack.xx.protection", \Fynla\Packs\XX\Protection\ProtectionEngine::class);
        // $this->app->bind("pack.xx.estate", \Fynla\Packs\XX\Estate\EstateEngine::class);
        // $this->app->bind("pack.xx.exchange_control", \Fynla\Packs\XX\ExchangeControl\NoopExchangeControl::class);
        // $this->app->bind("pack.xx.identity", \Fynla\Packs\XX\Identity\IdentityValidator::class);
        // $this->app->bind("pack.xx.localisation", \Fynla\Packs\XX\Localisation\Localisation::class);
        // $this->app->bind("pack.xx.banking", \Fynla\Packs\XX\Banking\BankingValidator::class);
    }

    public function boot(): void
    {
        // Register with the core PackRegistry
        $registry = $this->app->make(PackRegistry::class);
        $registry->register(CorePackManifest::fromArray(PackManifest::describe()));

        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
        $this->loadTranslationsFrom(__DIR__ . '/../../resources/lang', 'xx');
    }
}
