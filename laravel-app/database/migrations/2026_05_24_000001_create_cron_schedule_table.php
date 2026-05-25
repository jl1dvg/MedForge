<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cron_schedule', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 255);
            $table->string('command', 500);
            $table->enum('type', ['artisan', 'legacy'])->default('artisan');
            $table->string('cron_expression', 100)->default('*/15 * * * *');
            $table->boolean('enabled')->default(true);
            $table->boolean('run_in_background')->default(true);
            $table->boolean('without_overlapping')->default(true);
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 50)->nullable(); // ok | skipped | failed
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cron_schedule');
    }
};
