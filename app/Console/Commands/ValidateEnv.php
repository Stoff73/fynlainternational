<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Test Gauntlet G-5 H-4 — production-readiness environment gate.
 *
 * Asserts the running environment is safe for production before a deploy:
 * production flags are hard, sandbox/test overrides are absent, and every
 * required credential is present. Run in the G-7 prod-readiness rehearsal
 * (and safe to run anywhere — it only reads config, never prints secrets).
 *
 * Exit 0 = ready. Exit 1 = one or more blockers (listed).
 */
class ValidateEnv extends Command
{
    protected $signature = 'env:validate {--target=production : Environment profile to validate against}';

    protected $description = 'Validate the environment is production-ready (flags, absent test overrides, required credentials)';

    public function handle(): int
    {
        $target = (string) $this->option('target');
        $blockers = [];
        $warnings = [];

        if ($target === 'production') {
            // Hard production flags.
            $this->assert($blockers, config('app.env') === 'production', "APP_ENV must be 'production' (is '".config('app.env')."')");
            $this->assert($blockers, config('app.debug') === false, 'APP_DEBUG must be false');
            $this->assert($blockers, (bool) env('REVOLUT_SANDBOX', false) === false, 'REVOLUT_SANDBOX must be false in production');

            // Test-only overrides must be absent.
            $this->assert($blockers, env('LIFECYCLE_TEST_RECIPIENT') === null, 'LIFECYCLE_TEST_RECIPIENT must be unset in production');

            // Required credentials present (values read via env, never printed).
            // Production-only — local dev legitimately runs a passwordless root
            // DB and array mailer.
            $required = [
                'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD',
                'MAIL_HOST', 'MAIL_USERNAME', 'MAIL_PASSWORD',
                'REVOLUT_API_KEY', 'REVOLUT_WEBHOOK_SECRET',
                'AGENT_INTERNAL_TOKEN',
            ];
            foreach ($required as $key) {
                $val = (string) env($key, '');
                $missing = $val === '' || str_starts_with($val, 'YOUR_') || str_contains($val, 'GENERATE_THIS');
                $this->assert($blockers, ! $missing, "{$key} must be set (not a placeholder)");
            }
        }

        // A real, non-placeholder APP_KEY — required in every environment.
        $appKey = (string) config('app.key');
        $this->assert(
            $blockers,
            $appKey !== '' && ! str_contains($appKey, 'GENERATE_THIS') && ! str_contains($appKey, 'YOUR_'),
            'APP_KEY must be a real generated key'
        );

        // At least one AI provider key (Anthropic or xAI).
        $hasAi = ((string) env('ANTHROPIC_API_KEY', '') !== '' && ! str_starts_with((string) env('ANTHROPIC_API_KEY', ''), 'YOUR_'))
            || ((string) env('XAI_API_KEY', '') !== '' && ! str_starts_with((string) env('XAI_API_KEY', ''), 'YOUR_'));
        $this->assert($warnings, $hasAi, 'No AI provider key set (ANTHROPIC_API_KEY or XAI_API_KEY) — AI features will fail');

        foreach ($warnings as $w) {
            $this->warn("⚠ {$w}");
        }

        if ($blockers === []) {
            $this->info("✓ Environment is valid for '{$target}'".($warnings ? ' (with warnings above)' : '').'.');

            return self::SUCCESS;
        }

        $this->error('✗ Environment is NOT ready. Blockers:');
        foreach ($blockers as $b) {
            $this->line("  - {$b}");
        }

        return self::FAILURE;
    }

    /** @param  list<string>  $sink */
    private function assert(array &$sink, bool $condition, string $message): void
    {
        if (! $condition) {
            $sink[] = $message;
        }
    }
}
