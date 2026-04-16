<?php

declare(strict_types=1);

namespace App\Services\Admin;

use Illuminate\Support\Facades\DB;

class DatabaseMetricsService
{
    /**
     * Get the total database size in bytes
     */
    public function getDatabaseSizeInBytes(): int
    {
        try {
            $dbName = config('database.connections.'.config('database.default').'.database');
            $result = DB::select(
                'SELECT SUM(data_length + index_length) as size
                 FROM information_schema.TABLES
                 WHERE table_schema = ?',
                [$dbName]
            );

            return (int) ($result[0]->size ?? 0);
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Get database size formatted as human-readable string
     */
    public function getDatabaseSize(): string
    {
        $bytes = $this->getDatabaseSizeInBytes();

        if ($bytes === 0) {
            return 'Unknown';
        }

        return $this->formatBytes($bytes);
    }

    /**
     * Get database table statistics
     */
    public function getTableStatistics(): array
    {
        try {
            $dbName = config('database.connections.'.config('database.default').'.database');
            $tables = DB::select(
                'SELECT
                    table_name,
                    table_rows,
                    data_length,
                    index_length,
                    (data_length + index_length) as total_size
                 FROM information_schema.TABLES
                 WHERE table_schema = ?
                 ORDER BY (data_length + index_length) DESC',
                [$dbName]
            );

            return array_map(function ($table) {
                return [
                    'name' => $table->table_name,
                    'rows' => (int) $table->table_rows,
                    'data_size' => $this->formatBytes((int) $table->data_length),
                    'index_size' => $this->formatBytes((int) $table->index_length),
                    'total_size' => $this->formatBytes((int) $table->total_size),
                ];
            }, $tables);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get database connection information
     */
    public function getConnectionInfo(): array
    {
        $connection = config('database.default');
        $config = config("database.connections.{$connection}");

        return [
            'driver' => $config['driver'] ?? 'unknown',
            'host' => $config['host'] ?? 'unknown',
            'port' => $config['port'] ?? 'unknown',
            'database' => $config['database'] ?? 'unknown',
            'connection' => $connection,
        ];
    }

    /**
     * Format bytes to human-readable string
     */
    public function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}
