<?php

declare(strict_types=1);

namespace Fynla\Core\Console\Commands;

use Illuminate\Console\Command;

class GenerateMoneyMigration extends Command
{
    protected $signature = 'money:generate-migration
                            {table : The database table name}
                            {columns* : One or more money column names to add shadow columns for}
                            {--path=database/migrations : Path for the migration file}';

    protected $description = 'Generate a shadow-column migration for money columns (adds _minor and _ccy columns)';

    public function handle(): int
    {
        $table = $this->argument('table');
        $columns = $this->argument('columns');
        $path = $this->option('path');

        $timestamp = date('Y_m_d_His');
        $filename = "{$timestamp}_add_money_shadow_columns_to_{$table}_table.php";
        $filepath = base_path("{$path}/{$filename}");

        $upLines = [];
        $downLines = [];

        foreach ($columns as $column) {
            $upLines[] = "            \$table->bigInteger('{$column}_minor')->nullable()->after('{$column}');";
            $upLines[] = "            \$table->char('{$column}_ccy', 3)->nullable()->after('{$column}_minor');";
            $downLines[] = "            \$table->dropColumn('{$column}_minor');";
            $downLines[] = "            \$table->dropColumn('{$column}_ccy');";
        }

        $upBody = implode("\n", $upLines);
        $downBody = implode("\n", $downLines);

        $content = <<<PHP
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
{$upBody}
        });
    }

    public function down(): void
    {
        Schema::table('{$table}', function (Blueprint \$table) {
{$downBody}
        });
    }
};
PHP;

        if (!is_dir(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }

        file_put_contents($filepath, $content);

        $this->info("Migration created: {$path}/{$filename}");
        $this->info("Shadow columns for: " . implode(', ', $columns));

        return self::SUCCESS;
    }
}
