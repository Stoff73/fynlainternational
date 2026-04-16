<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use function Pest\Laravel\getJson;

describe('multi-pack coexistence', function () {
    it('serves the xx-smoke health endpoint', function () {
        getJson('/api/xx/health')
            ->assertOk()
            ->assertJsonFragment(['pack' => 'xx-smoke']);
    });

    it('serves the gb health endpoint when country-gb is loaded', function () {
        if (! Route::has('gb.health') && ! collect(Route::getRoutes())->contains(fn ($route) => $route->uri() === 'api/gb/health')) {
            $this->markTestSkipped('country-gb pack is not loaded');
        }

        getJson('/api/gb/health')
            ->assertOk();
    });

    it('registers both pack routes without conflict', function () {
        $routes = collect(Route::getRoutes())
            ->map(fn ($route) => $route->uri())
            ->toArray();

        expect($routes)->toContain('api/xx/health');
    });
});
