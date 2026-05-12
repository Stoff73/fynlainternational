<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * G-(-1) MVP no-op stub. Registered so `php artisan schedule:list` shows
 * the daily lifecycle job, gating G-0-iv's verification that the
 * scheduler picks up the lifecycle pipeline. The full lifecycle engine
 * (which this command will drive once built) is captured as tech debt
 * in docs/superpowers/specs/2026-04-14-lifecycle-email-engine-design.md.
 */
class LifecycleRunDaily extends Command
{
    protected $signature = 'lifecycle:run-daily';

    protected $description = 'Stub: daily lifecycle dispatcher (no-op until the full engine lands)';

    public function handle(): int
    {
        Log::info('lifecycle.run-daily.stub', [
            'note' => 'no-op stub — full lifecycle engine pending (G-(-1) MVP only)',
        ]);

        $this->info('lifecycle:run-daily — no-op stub (G-(-1) MVP)');

        return self::SUCCESS;
    }
}
