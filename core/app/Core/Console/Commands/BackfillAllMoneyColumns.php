<?php

declare(strict_types=1);

namespace Fynla\Core\Console\Commands;

use Fynla\Core\Money\MoneyColumnRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill ALL shadow money columns across every table in MoneyColumnRegistry.
 *
 * This is the bulk wrapper around money:backfill. It iterates every table
 * in the registry, skips empty tables or tables missing shadow columns,
 * and runs the per-table backfill with built-in checksum verification.
 */
class BackfillAllMoneyColumns extends Command
{
    protected $signature = 'money:backfill-all
                            {--dry-run : Show what would be updated without writing}
                            {--batch-size=500 : Number of rows per batch}';

    protected $description = 'Backfill ALL shadow money columns (_minor, _ccy) from legacy decimal columns across every registered table';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        $allTables = MoneyColumnRegistry::all();

        $this->info('Money Column Backfill — All Tables');
        $this->info('===================================');
        $this->info('Tables in registry: ' . count($allTables));
        $this->info('Total money columns: ' . MoneyColumnRegistry::count());

        if ($dryRun) {
            $this->warn('DRY RUN — no changes will be written');
        }

        $this->newLine();

        $tablesProcessed = 0;
        $tablesSkipped = 0;
        $totalUpdated = 0;
        $totalErrors = 0;

        foreach ($allTables as $table => $columns) {
            // Check table exists
            if (! Schema::hasTable($table)) {
                $this->warn("  SKIP {$table} — table does not exist");
                $tablesSkipped++;
                continue;
            }

            // Check table has rows
            $rowCount = DB::table($table)->count();
            if ($rowCount === 0) {
                $this->line("  SKIP {$table} — empty (0 rows)");
                $tablesSkipped++;
                continue;
            }

            // Check at least one shadow column exists
            $firstCol = array_key_first($columns);
            $shadowCol = $firstCol . '_minor';
            if (! Schema::hasColumn($table, $shadowCol)) {
                $this->warn("  SKIP {$table} — shadow column {$shadowCol} not found (run shadow migrations first)");
                $tablesSkipped++;
                continue;
            }

            $columnNames = array_keys($columns);
            $currencyCode = reset($columns); // All are GBP in Phase 0

            $this->info("Processing {$table} ({$rowCount} rows, " . count($columns) . ' money columns)...');

            $minorUnits = match (strtoupper($currencyCode)) {
                'JPY', 'KRW', 'VND', 'CLP', 'ISK' => 0,
                'BHD', 'KWD', 'OMR' => 3,
                default => 2,
            };
            $multiplier = 10 ** $minorUnits;

            $bar = $this->output->createProgressBar($rowCount);
            $updated = 0;
            $skipped = 0;
            $errors = 0;

            DB::table($table)->orderBy('id')->chunk($batchSize, function ($rows) use (
                $table, $columnNames, $currencyCode, $multiplier, $dryRun, $bar, &$updated, &$skipped, &$errors
            ) {
                foreach ($rows as $row) {
                    $updates = [];

                    foreach ($columnNames as $column) {
                        $minorCol = $column . '_minor';
                        $ccyCol = $column . '_ccy';

                        // Check shadow column exists on the row object
                        if (! property_exists($row, $minorCol)) {
                            continue;
                        }

                        // Skip if already backfilled
                        if ($row->$minorCol !== null) {
                            $skipped++;
                            continue;
                        }

                        // Check source column exists
                        if (! property_exists($row, $column)) {
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

                    if (count($updates) > 0 && ! $dryRun) {
                        try {
                            DB::table($table)->where('id', $row->id)->update($updates);
                            $updated++;
                        } catch (\Throwable $e) {
                            $errors++;
                            $this->error("  Row {$row->id}: {$e->getMessage()}");
                        }
                    } elseif (count($updates) > 0) {
                        $updated++;
                    }

                    $bar->advance();
                }
            });

            $bar->finish();
            $this->newLine();
            $this->info("  Result: {$updated} updated, {$skipped} skipped, {$errors} errors");

            // Verification checksum
            if (! $dryRun && $errors === 0 && $updated > 0) {
                $this->verifyChecksums($table, $columnNames, $multiplier);
            }

            $this->newLine();
            $tablesProcessed++;
            $totalUpdated += $updated;
            $totalErrors += $errors;
        }

        $this->newLine();
        $this->info('===================================');
        $this->info("Complete: {$tablesProcessed} tables processed, {$tablesSkipped} skipped");
        $this->info("Total rows updated: {$totalUpdated}");

        if ($totalErrors > 0) {
            $this->error("Total errors: {$totalErrors}");
        }

        return $totalErrors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Verify that sum(decimal) * multiplier == sum(minor) within tolerance.
     */
    private function verifyChecksums(string $table, array $columns, int $multiplier): void
    {
        foreach ($columns as $column) {
            $minorCol = $column . '_minor';

            if (! Schema::hasColumn($table, $minorCol)) {
                continue;
            }

            $sumDecimal = DB::table($table)->whereNotNull($column)->sum($column);
            $sumMinor = DB::table($table)->whereNotNull($minorCol)->sum($minorCol);

            $expectedMinor = (int) round($sumDecimal * $multiplier);
            $diff = abs($expectedMinor - (int) $sumMinor);

            $rowCount = DB::table($table)->whereNotNull($column)->count();
            $tolerance = max(1, (int) ceil($rowCount / 1000));

            if ($diff <= $tolerance) {
                $this->info("  Checksum {$column}: OK (diff: {$diff})");
            } else {
                $this->error("  Checksum {$column}: FAILED — expected ~{$expectedMinor}, got {$sumMinor} (diff: {$diff})");
            }
        }
    }
}
