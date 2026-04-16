<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tax_years', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->string('label', 20)->comment("e.g. '2025/26', '2026/27'");
            $table->date('starts_on')->comment('First day of tax year');
            $table->date('ends_on')->comment('Last day of tax year');
            $table->timestamps();

            $table->unique(['jurisdiction_id', 'label']);
            $table->index(['jurisdiction_id', 'starts_on', 'ends_on'], 'tax_years_date_range_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_years');
    }
};
