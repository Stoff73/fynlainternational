<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Test Gauntlet G-5 H-3 — every outbound HTTP call to an external service must
 * carry an explicit ->timeout(), so a hung upstream (Revolut, FCM, xAI, Awin,
 * postcode lookup) can't stall a request indefinitely.
 *
 * Static scan: any `Http::…->post/get/put/patch/delete(` chain in the service
 * layer must contain `timeout` somewhere in the chain.
 */
it('every external HTTP call in services carries an explicit timeout', function () {
    $dirs = [base_path('app/Services'), base_path('packs/country-gb/src'), base_path('packs/country-za/src'), base_path('core/app')];
    $violations = [];

    foreach ($dirs as $dir) {
        if (! is_dir($dir)) {
            continue;
        }
        foreach (File::allFiles($dir) as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            $contents = File::get($f->getPathname());
            // Match Http:: chains terminating in a verb call; check for timeout.
            if (preg_match_all('/Http::.*?->(?:post|get|put|patch|delete)\(/s', $contents, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$chain]) {
                    if (! str_contains($chain, 'timeout')) {
                        $violations[] = str_replace(base_path().'/', '', $f->getPathname());
                        break;
                    }
                }
            }
        }
    }

    expect(array_unique($violations))->toBeEmpty(
        "External HTTP calls without ->timeout() found (G-5 H-3):\n".implode("\n", array_unique($violations))
    );
});
