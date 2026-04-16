<?php

declare(strict_types=1);

namespace Fynla\Packs\XXSmoke\Providers;

use Fynla\Core\Registry\PackManifest as CorePackManifest;
use Fynla\Core\Registry\PackRegistry;
use Fynla\Packs\XXSmoke\Support\PackManifest;
use Illuminate\Support\ServiceProvider;

class CountryPackServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Register with the core PackRegistry
        $registry = $this->app->make(PackRegistry::class);
        $registry->register(CorePackManifest::fromArray(PackManifest::describe()));

        $this->loadRoutesFrom(__DIR__ . '/../../routes/api.php');
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');
    }
}
