<?php

declare(strict_types=1);

namespace Fynla\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillMoneyColumns extends Command
{
    protected $signature = 'money:backfill
                            {table : The database table name}
                            {columns* : Money column names to backfill}
                            {--currency=GBP : Default currency code}
                            {--batch-size=500 : Number of rows per batch}
                            {--dry-run : Show what would be updated without writing}';

    protected $description = 'Backfill shadow money columns (_minor, _ccy) from legacy decimal columns';

    public function handle(): int
    {
        $table = $this->argument('table');
        $columns = $this->argument('columns');
        $currencyCode = $this->option('currency');
        $batchSize = (int) $this->option('batch-size');
        $dryRun = $this->option('dry-run');

        // Determine minor units for the currency
        $minorUnits = match (strtoupper($currencyCode)) {
            'JPY', 'KRW', 'VND', 'CLP', 'ISK' => 0,
            'BHD', 'KWD', 'OMR' => 3,
            default => 2,
        };
        $multiplier = 10 ** $minorUnits;

        $this->info("Backfilling {$table}: " . implode(', ', $columns));
        $this->info("Currency: {$currencyCode} (minor units: {$minorUnits})");

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written');
        }

        $totalRows = DB::table($table)->count();
        $this->info("Total rows: {$totalRows}");

        $bar = $this->output->createProgressBar($totalRows);
        $updated = 0;
        $skipped = 0;
        $errors = 0;

        DB::table($table)->orderBy('id')->chunk($batchSize, function ($rows) use (
            $table, $columns, $currencyCode, $multiplier, $dryRun, $bar, &$updated, &$skipped, &$errors
        ) {
            foreach ($rows as $row) {
                $updates = [];

                foreach ($columns as $column) {
                    $minorCol = $column . '_minor';
                    $ccyCol = $column . '_ccy';

                    // Skip if already backfilled
                    if ($row->$minorCol !== null) {
                        $skipped++;
                        continue;
                    }

                    $decimal = $row->$column;

                    if ($decimal === null) {
                        $updates[$minorCol] = null;
                        $updates[$ccyCol] = null;
                    } else {
                        $minor = (int) round((float) $decimal * $multiplier);
                        $updates[$minorCol] = $minor;
                        $updates[$ccyCol] = $currencyCode;
                    }
                }

                if (!empty($updates) && !$dryRun) {
                    try {
                        DB::table($table)->where('id', $row->id)->update($updates);
                        $updated++;
                    } catch (\Throwable $e) {
                        $errors++;
                        $this->error("Row {$row->id}: {$e->getMessage()}");
                    }
                } elseif (!empty($updates)) {
                    $updated++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info("Backfill complete: {$updated} updated, {$skipped} skipped, {$errors} errors");

        // Verification checksum
        if (!$dryRun && $errors === 0) {
            $this->verifyChecksum($table, $columns, $multiplier);
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Verify that sum(decimal) * multiplier == sum(minor) within tolerance.
     */
    private function verifyChecksum(string $table, array $columns, int $multiplier): void
    {
        $this->info('Running verification checksums...');

        foreach ($columns as $column) {
            $minorCol = $column . '_minor';

            $sumDecimal = DB::table($table)->whereNotNull($column)->sum($column);
            $sumMinor = DB::table($table)->whereNotNull($minorCol)->sum($minorCol);

            $expectedMinor = (int) round($sumDecimal * $multiplier);
            $diff = abs($expectedMinor - (int) $sumMinor);

            // Allow tolerance of 1 minor unit per 1000 rows (rounding accumulation)
            $rowCount = DB::table($table)->whereNotNull($column)->count();
            $tolerance = max(1, (int) ceil($rowCount / 1000));

            if ($diff <= $tolerance) {
                $this->info("  {$column}: checksum OK (diff: {$diff}, tolerance: {$tolerance})");
            } else {
                $this->error("  {$column}: CHECKSUM FAILED — expected ~{$expectedMinor}, got {$sumMinor} (diff: {$diff})");
            }
        }
    }
}
