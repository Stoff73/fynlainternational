<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Fynla\Core\Jurisdiction\AssignPrimaryJurisdiction;
use Fynla\Core\Models\User;
use Illuminate\Console\Command;

/**
 * Assigns a primary jurisdiction to any user that has none — fixing the
 * pre-WS1 population where signup never wrote a user_jurisdictions row. GB is
 * the correct default: the row-less users are all UK-era. Idempotent.
 */
class BackfillJurisdictions extends Command
{
    protected $signature = 'jurisdictions:backfill {--code=GB : ISO country code to assign to row-less users}';

    protected $description = 'Assign a primary jurisdiction to any user that has none';

    public function handle(AssignPrimaryJurisdiction $assign): int
    {
        $code = strtoupper((string) $this->option('code'));
        $assigned = 0;

        User::query()
            ->whereDoesntHave('jurisdictions')
            ->chunkById(200, function ($users) use ($assign, $code, &$assigned) {
                foreach ($users as $user) {
                    $assign->assign($user, $code);
                    $assigned++;
                }
            });

        $this->info("Backfilled {$assigned} user(s) with primary jurisdiction {$code}.");

        return self::SUCCESS;
    }
}
