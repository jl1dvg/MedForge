<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('procedimiento_proyectado', function (Blueprint $table): void {
            if (!Schema::hasColumn('procedimiento_proyectado', 'sigcenter_present')) {
                $table->boolean('sigcenter_present')
                    ->default(true)
                    ->after('visita_id');
            }

            if (!Schema::hasColumn('procedimiento_proyectado', 'sigcenter_last_seen_at')) {
                $table->timestamp('sigcenter_last_seen_at')
                    ->nullable()
                    ->after('sigcenter_present');
            }

            if (!Schema::hasColumn('procedimiento_proyectado', 'sigcenter_missing_at')) {
                $table->timestamp('sigcenter_missing_at')
                    ->nullable()
                    ->after('sigcenter_last_seen_at');
            }

            $table->index(['fecha', 'sigcenter_present'], 'pp_fecha_sigcenter_present_idx');
            $table->index('sigcenter_missing_at', 'pp_sigcenter_missing_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('procedimiento_proyectado', function (Blueprint $table): void {
            $table->dropIndex('pp_fecha_sigcenter_present_idx');
            $table->dropIndex('pp_sigcenter_missing_at_idx');

            if (Schema::hasColumn('procedimiento_proyectado', 'sigcenter_missing_at')) {
                $table->dropColumn('sigcenter_missing_at');
            }

            if (Schema::hasColumn('procedimiento_proyectado', 'sigcenter_last_seen_at')) {
                $table->dropColumn('sigcenter_last_seen_at');
            }

            if (Schema::hasColumn('procedimiento_proyectado', 'sigcenter_present')) {
                $table->dropColumn('sigcenter_present');
            }
        });
    }
};
