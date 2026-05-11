<?php

declare(strict_types=1);

namespace Fynla\Core\Providers;

use Fynla\Core\Contracts\PackAssetRepository;
use Fynla\Core\Contracts\PackAssetResolver;
use Fynla\Core\Contracts\PackEstateRepository;
use Fynla\Core\Http\Middleware\ActiveJurisdictionMiddleware;
use Fynla\Core\Http\Middleware\EnsurePackEnabled;
use Fynla\Core\Http\Middleware\LegacyApiRewrite;
use Fynla\Core\Jurisdiction\ActiveJurisdictions;
use Fynla\Core\Query\CompositePackAssetRepository;
use Fynla\Core\Query\CompositePackAssetResolver;
use Fynla\Core\Query\CompositePackEstateRepository;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Core\TaxYear\TaxYearResolver;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Routing\Router;
use Illuminate\Support\ServiceProvider;

/**
 * Core service provider for the Fynla multi-country architecture.
 *
 * Registers the PackRegistry singleton, ActiveJurisdictions resolver,
 * and jurisdiction-related route middleware.
 */
class CoreServiceProvider extends ServiceProvider
{
    /**
     * Register core services into the container.
     */
    public function register(): void
    {
        $this->app->singleton(PackRegistry::class, function (): PackRegistry {
            return new PackRegistry();
        });

        $this->app->singleton(ActiveJurisdictions::class, function (): ActiveJurisdictions {
            return new ActiveJurisdictions();
        });

        $this->app->singleton(TaxYearResolver::class, function (): TaxYearResolver {
            return new TaxYearResolver();
        });

        // R-14b-i: container-resolved query layer. Composite defaults walk
        // PackRegistry and merge per-pack repository results, so core models
        // (User, Household, Goal et al.) read pack-owned assets through
        // these contracts instead of pack-namespaced `hasMany` literals.
        // Packs override by binding `pack.{code}.asset_repo` /
        // `pack.{code}.estate_repo` / `pack.{code}.asset_resolver`; the
        // composite resolves those keys lazily at read time.
        $this->app->singleton(PackAssetRepository::class, function (Container $app): PackAssetRepository {
            return new CompositePackAssetRepository($app, $app->make(PackRegistry::class));
        });

        $this->app->singleton(PackEstateRepository::class, function (Container $app): PackEstateRepository {
            return new CompositePackEstateRepository($app, $app->make(PackRegistry::class));
        });

        $this->app->singleton(PackAssetResolver::class, function (Container $app): PackAssetResolver {
            return new CompositePackAssetResolver($app, $app->make(PackRegistry::class));
        });
    }

    /**
     * Boot core services.
     *
     * Registers route middleware, core migrations, and artisan commands.
     */
    public function boot(): void
    {
        /** @var Router $router */
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('active.jurisdiction', ActiveJurisdictionMiddleware::class);
        $router->aliasMiddleware('pack.enabled', EnsurePackEnabled::class);
        $router->aliasMiddleware('legacy.api.rewrite', LegacyApiRewrite::class);

        // Core migrations live in database/migrations/ (standard Laravel path)
        // to avoid duplicate-run issues with loadMigrationsFrom().

        // Register artisan commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Fynla\Core\Console\Commands\GenerateMoneyMigration::class,
                \Fynla\Core\Console\Commands\BackfillMoneyColumns::class,
                \Fynla\Core\Console\Commands\BackfillAllMoneyColumns::class,
                \Fynla\Core\Console\Commands\AuditMoneyColumns::class,
            ]);
        }
    }
}
