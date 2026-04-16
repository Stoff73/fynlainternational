<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique()->comment('ISO 3166-1 alpha-2 country code');
            $table->string('name', 100)->comment('Country name in English');
            $table->char('currency', 3)->comment('ISO 4217 currency code');
            $table->string('locale', 10)->comment('BCP 47 locale tag, e.g. en-GB');
            $table->boolean('active')->default(true)->comment('Whether this jurisdiction is available for new users');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jurisdictions');
    }
};
