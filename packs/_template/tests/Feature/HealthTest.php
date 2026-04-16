<?php

declare(strict_types=1);

use function Pest\Laravel\getJson;

it('returns a healthy status from the pack health endpoint', function () {
    getJson('/api/xx/health')
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'pack' => 'xx',
            'version' => '0.0.1',
        ]);
});
