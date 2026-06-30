<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('service_mode', ['auto', 'on', 'off'])->default('auto');
            $table->datetime('readonly_start')->nullable();
            $table->datetime('readonly_end')->nullable();
            $table->string('readonly_message')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed the initial tenant (CIVE). The read-only window matches the
        // defaults in config/medforge-readonly.php so behaviour is unchanged
        // until the owner explicitly changes it from the /owner panel.
        DB::table('companies')->insert([
            'name'             => 'CIVE',
            'slug'             => 'cive',
            'service_mode'     => 'auto',
            'readonly_start'   => '2026-07-15 00:00:00',
            'readonly_end'     => '2026-07-31 23:59:59',
            'readonly_message' => 'Sistema en modo solo lectura. No se pueden guardar cambios en este momento.',
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
