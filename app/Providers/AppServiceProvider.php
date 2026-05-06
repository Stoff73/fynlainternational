<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\Plans\PlanConfigService;
use Illuminate\Database\Eloquent\Factories\Factory;
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

        // R-4: factories live under `Database\Factories\<ModelName>Factory`
        // regardless of where the model itself lives. Laravel's default
        // resolver mangles the model FQCN into the factory FQCN, which
        // doesn't work when the model namespace is `Fynla\Core\Models\…`
        // or `Fynla\Packs\Gb\Models\…`. Strip those prefixes so the
        // factory still resolves to `Database\Factories\<ModelName>Factory`.
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            $shortName = class_basename($modelName);

            // Preserve sub-namespaces under Models/ (e.g. Estate, Investment)
            // so e.g. `Fynla\Packs\Gb\Models\Estate\Trust` still resolves
            // to `Database\Factories\Estate\TrustFactory`.
            foreach (['Fynla\\Core\\Models\\', 'Fynla\\Packs\\Gb\\Models\\', 'App\\Models\\'] as $prefix) {
                if (str_starts_with($modelName, $prefix)) {
                    $relative = substr($modelName, strlen($prefix));
                    return 'Database\\Factories\\' . $relative . 'Factory';
                }
            }

            return 'Database\\Factories\\' . $shortName . 'Factory';
        });

        // Inverse: factory → model. Laravel's default looks under App\Models\
        // which doesn't find models that have relocated to core or GB pack.
        // Walk a known set of namespaces and return the first match.
        Factory::guessModelNamesUsing(function (Factory $factory): string {
            $factoryClass = get_class($factory);
            $relative = preg_replace(
                '/^Database\\\\Factories\\\\/',
                '',
                preg_replace('/Factory$/', '', $factoryClass)
            );

            foreach ([
                'Fynla\\Core\\Models\\',
                'Fynla\\Packs\\Gb\\Models\\',
                'App\\Models\\',
            ] as $prefix) {
                $candidate = $prefix . $relative;
                if (class_exists($candidate)) {
                    return $candidate;
                }
            }

            // Fallback to Laravel default behaviour.
            return 'App\\' . class_basename($factoryClass);
        });

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
