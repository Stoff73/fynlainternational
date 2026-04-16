<?php

declare(strict_types=1);

namespace Fynla\Core\Providers;

use Fynla\Core\Http\Middleware\ActiveJurisdictionMiddleware;
use Fynla\Core\Http\Middleware\EnsurePackEnabled;
use Fynla\Core\Http\Middleware\LegacyApiRewrite;
use Fynla\Core\Jurisdiction\ActiveJurisdictions;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Core\TaxYear\TaxYearResolver;
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
