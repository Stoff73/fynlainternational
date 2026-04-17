<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Plans\PlanConfigService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Request-scoped singleton for plan configuration (same pattern as TaxConfigService)
        $this->app->scoped(PlanConfigService::class);

        // Register both AI client singletons — runtime provider selection happens
        // in HasAiChat/HasAiGuardrails via cache check (admin toggle)
        $this->app->singleton(\App\Services\AI\XaiClient::class);

        if (class_exists(\Anthropic\Client::class)) {
            $this->app->singleton(\Anthropic\Client::class, function () {
                $apiKey = config('services.anthropic.api_key');

                if (empty($apiKey)) {
                    throw new \RuntimeException('ANTHROPIC_API_KEY is not configured.');
                }

                return new \Anthropic\Client(apiKey: $apiKey);
            });
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent lazy loading in non-production environments to catch N+1 query issues
        Model::preventLazyLoading(! app()->isProduction());

        // Workstream 0.6 — wire the jurisdiction-detection observer on every
        // asset-bearing model that carries a country_code column. The
        // observer auto-activates a user's jurisdictions from asset
        // location; users never see the word "jurisdiction" in the UI.
        $assetModels = [
            \App\Models\Investment\InvestmentAccount::class,
            \App\Models\DCPension::class,
            \App\Models\DBPension::class,
            \App\Models\SavingsAccount::class,
            \App\Models\Property::class,
            \App\Models\Estate\Asset::class,
        ];
        foreach ($assetModels as $modelClass) {
            $modelClass::observe(\Fynla\Core\Observers\JurisdictionDetectionObserver::class);
        }
    }
}
