<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_jurisdictions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('jurisdiction_id')->constrained('jurisdictions')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false)->comment("User's primary jurisdiction");
            $table->timestamp('activated_at')->nullable()->comment('When this jurisdiction was activated for the user');
            $table->timestamps();

            $table->unique(['user_id', 'jurisdiction_id']);
            $table->index('user_id');
            $table->index('jurisdiction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_jurisdictions');
    }
};
