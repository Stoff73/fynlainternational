<?php

declare(strict_types=1);

/**
 * Per-Pack Routes Architecture Tests (R-14).
 *
 * The /api/{cc}/* prefix on the backend is the canonical jurisdiction
 * scope. Frontend pack routes mount unprefixed (pack scoping handles
 * SA-vs-UK isolation), so the SA pack's routes.js must not declare
 * any path with a `/za/` prefix — those legacy URLs are handled by a
 * one-time soft-redirect catch-all in core's router.
 *
 * The ZA pack's routes.js does not exist yet (R-13b only relocated
 * components); this test is dormant until that file is created, at
 * which point it becomes the guard against reintroducing /za/ prefixes.
 */

$projectRoot = realpath(__DIR__.'/../../');

it('SA pack routes file declares no /za/ URL prefix', function () use ($projectRoot) {
    $routesFile = $projectRoot.'/packs/country-za/resources/js/routes.js';

    if (! file_exists($routesFile)) {
        expect(true)->toBeTrue();

        return;
    }

    $content = file_get_contents($routesFile);
    expect($content)->not->toMatch(
        '#path:\s*[\'"]/za/#',
        'SA pack routes.js must not declare paths with a /za/ prefix — '.
        'pack scoping handles isolation; /za/ URLs are legacy and '.
        'redirected by core\'s catch-all (see resources/js/router/index.js).'
    );
});
