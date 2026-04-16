<?php

declare(strict_types=1);

namespace Fynla\Core\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AuditMoneyColumns extends Command
{
    protected $signature = 'money:audit
                            {--path=database/migrations : Path to scan for migration files}
                            {--format=table : Output format (table or csv)}';

    protected $description = 'Scan migration files and list all decimal/double/float columns likely to be money columns';

    public function handle(): int
    {
        $path = base_path($this->option('path'));
        $format = $this->option('format');

        if (!is_dir($path)) {
            $this->error("Migration path not found: {$path}");
            return self::FAILURE;
        }

        $files = File::glob($path . '/*.php');
        sort($files);

        $this->info("Scanning " . count($files) . " migration files in {$path}");
        $this->newLine();

        $results = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $filename = basename($file);

            // Extract table name from Schema::create or Schema::table calls
            $tables = [];
            if (preg_match_all("/Schema::(create|table)\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $tableMatches)) {
                $tables = $tableMatches[2];
            }

            $tableName = $tables[0] ?? 'unknown';

            // Match decimal, double, float column definitions
            $patterns = [
                'decimal' => "/->decimal\s*\(\s*['\"]([^'\"]+)['\"]/",
                'double'  => "/->double\s*\(\s*['\"]([^'\"]+)['\"]/",
                'float'   => "/->float\s*\(\s*['\"]([^'\"]+)['\"]/",
            ];

            foreach ($patterns as $type => $pattern) {
                if (preg_match_all($pattern, $content, $colMatches)) {
                    foreach ($colMatches[1] as $columnName) {
                        // Skip columns that are clearly not money (percentages, rates, etc.)
                        $likelyMoney = $this->isLikelyMoneyColumn($columnName);

                        $results[] = [
                            'table'       => $tableName,
                            'column'      => $columnName,
                            'type'        => $type,
                            'likely_money' => $likelyMoney ? 'Yes' : 'Maybe',
                            'migration'   => $filename,
                        ];
                    }
                }
            }
        }

        if (empty($results)) {
            $this->info('No decimal/double/float columns found.');
            return self::SUCCESS;
        }

        // Sort by table, then column
        usort($results, fn ($a, $b) => [$a['table'], $a['column']] <=> [$b['table'], $b['column']]);

        // Deduplicate (same table + column may appear in multiple migrations)
        $seen = [];
        $deduplicated = [];
        foreach ($results as $row) {
            $key = $row['table'] . '.' . $row['column'];
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $deduplicated[] = $row;
            }
        }

        if ($format === 'csv') {
            $this->line('table,column,type,likely_money,migration');
            foreach ($deduplicated as $row) {
                $this->line(implode(',', $row));
            }
        } else {
            $this->table(
                ['Table', 'Column', 'Type', 'Likely Money?', 'Migration'],
                $deduplicated,
            );
        }

        $this->newLine();

        $moneyCount = count(array_filter($deduplicated, fn ($r) => $r['likely_money'] === 'Yes'));
        $maybeCount = count($deduplicated) - $moneyCount;

        $this->info("Found {$moneyCount} likely money columns, {$maybeCount} uncertain (total: " . count($deduplicated) . ")");
        $this->info("Review 'Maybe' columns manually to confirm if they hold monetary values.");

        return self::SUCCESS;
    }

    /**
     * Heuristic: does this column name suggest it holds a monetary amount?
     */
    private function isLikelyMoneyColumn(string $column): bool
    {
        $moneyIndicators = [
            'amount', 'balance', 'value', 'price', 'cost', 'fee', 'charge',
            'premium', 'salary', 'income', 'revenue', 'payment', 'deposit',
            'withdrawal', 'contribution', 'deduction', 'allowance', 'benefit',
            'coverage', 'sum_assured', 'fund_value', 'portfolio', 'liability',
            'debt', 'mortgage', 'loan', 'rent', 'tax', 'vat', 'nrb', 'rnrb',
            'threshold', 'exemption', 'relief', 'credit', 'surplus', 'shortfall',
            'target', 'total', 'gross', 'net', 'annual', 'monthly',
            'lump_sum', 'drawdown', 'annuity', 'pension', 'expenditure',
        ];

        $notMoneyIndicators = [
            'percentage', 'percent', 'rate', 'ratio', 'factor', 'weight',
            'latitude', 'longitude', 'score', 'rating', 'probability',
            'share', 'ownership',
        ];

        $columnLower = strtolower($column);

        // Check negative indicators first
        foreach ($notMoneyIndicators as $indicator) {
            if (str_contains($columnLower, $indicator)) {
                return false;
            }
        }

        // Check positive indicators
        foreach ($moneyIndicators as $indicator) {
            if (str_contains($columnLower, $indicator)) {
                return true;
            }
        }

        return false;
    }
}
