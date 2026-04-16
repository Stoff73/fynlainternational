<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

it('returns a healthy response from the xx-smoke pack', function () {
    getJson('/api/xx/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'pack' => 'xx-smoke',
            'version' => '0.0.1',
            'purpose' => 'ci-smoke-test',
        ]);
});
