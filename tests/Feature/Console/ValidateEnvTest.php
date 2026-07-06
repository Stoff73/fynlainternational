<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;

/**
 * Test Gauntlet G-5 H-4 — env:validate command.
 */
it('passes for a valid local environment', function () {
    // Local dev: real APP_KEY, non-production target — no credential blockers.
    Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $this->artisan('env:validate', ['--target' => 'local'])
        ->assertExitCode(0);
});

it('blocks a production target when running under local flags', function () {
    Config::set('app.env', 'local');
    Config::set('app.debug', true);

    $this->artisan('env:validate', ['--target' => 'production'])
        ->expectsOutputToContain("APP_ENV must be 'production'")
        ->assertExitCode(1);
});

it('blocks a placeholder APP_KEY in any environment', function () {
    Config::set('app.key', 'base64:GENERATE_THIS_ON_SERVER');

    $this->artisan('env:validate', ['--target' => 'local'])
        ->expectsOutputToContain('APP_KEY must be a real generated key')
        ->assertExitCode(1);
});

it('passes a fully-configured production environment', function () {
    Config::set('app.env', 'production');
    Config::set('app.debug', false);
    Config::set('app.key', 'base64:'.base64_encode(random_bytes(32)));

    $set = [
        'REVOLUT_SANDBOX' => 'false',
        'DB_DATABASE' => 'fynla', 'DB_USERNAME' => 'u', 'DB_PASSWORD' => 'realpass',
        'MAIL_HOST' => 'smtp.example.com', 'MAIL_USERNAME' => 'mailer', 'MAIL_PASSWORD' => 'realmailpass',
        'REVOLUT_API_KEY' => 'sk_real', 'REVOLUT_WEBHOOK_SECRET' => 'wsk_real',
        'AGENT_INTERNAL_TOKEN' => 'realtoken', 'ANTHROPIC_API_KEY' => 'sk-ant-real',
    ];
    $keys = [...array_keys($set), 'LIFECYCLE_TEST_RECIPIENT'];

    try {
        putenv('LIFECYCLE_TEST_RECIPIENT');
        unset($_ENV['LIFECYCLE_TEST_RECIPIENT'], $_SERVER['LIFECYCLE_TEST_RECIPIENT']);
        foreach ($set as $k => $v) {
            putenv("{$k}={$v}");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }

        $this->artisan('env:validate', ['--target' => 'production'])
            ->assertExitCode(0);
    } finally {
        foreach ($keys as $k) {
            putenv($k);
            unset($_ENV[$k], $_SERVER[$k]);
        }
    }
});
