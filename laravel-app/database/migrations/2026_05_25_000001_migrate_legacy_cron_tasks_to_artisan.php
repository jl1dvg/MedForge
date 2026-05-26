<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Migra las tareas legacy del CronRunner al scheduler de Laravel.
 *
 * - 7 tareas sin equivalente artisan → type='artisan', command='cron:legacy-task <slug>'
 * - 6 tareas duplicadas (ya cubiertas por comandos artisan propios) → enabled=0
 */
return new class extends Migration
{
    public function up(): void
    {
        // Tareas que no tienen un comando artisan propio: se delegan a cron:legacy-task.
        $toLegacyWrapper = [
            'ai-sync'                       => '*/30 * * * *',
            'billing-autocreation'          => '*/15 * * * *',
            'cive-extension-health'         => '*/15 * * * *',
            'crm-task-supervisor-escalations' => '*/5 * * * *',
            'identity-verification-expiration' => '0 2 * * *',
            'kpi-refresh'                   => '0 * * * *',
            'stats-refresh'                 => '0 * * * *',
        ];

        foreach ($toLegacyWrapper as $slug => $cron) {
            DB::table('cron_schedule')
                ->where('slug', $slug)
                ->update([
                    'type'            => 'artisan',
                    'command'         => "cron:legacy-task {$slug}",
                    'cron_expression' => $cron,
                    'enabled'         => 1,
                    'updated_at'      => now(),
                ]);
        }

        // Tareas legacy que ya están cubiertas por comandos artisan propios → desactivar.
        $toDisable = [
            'iess-billing-sync',           // cubierto por billing-facturacion-real (*/4h)
            'iess-derivaciones-scrape-missing', // cubierto por derivaciones-scrape-missing (hourly)
            'iess-derivaciones-sync',      // cubierto por derivaciones-refresh (7h, 14h)
            'solicitudes-derivaciones-refresh', // cubierto por derivaciones-refresh
            'solicitudes-overdue',         // cubierto por marcar-vencidas (7am)
            'solicitudes-reminders',       // cubierto por enviar-recordatorios (8am)
        ];

        DB::table('cron_schedule')
            ->whereIn('slug', $toDisable)
            ->update([
                'enabled'    => 0,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        // Revertir las 7 tareas a legacy.
        $slugs = [
            'ai-sync', 'billing-autocreation', 'cive-extension-health',
            'crm-task-supervisor-escalations', 'identity-verification-expiration',
            'kpi-refresh', 'stats-refresh',
        ];

        foreach ($slugs as $slug) {
            DB::table('cron_schedule')
                ->where('slug', $slug)
                ->update([
                    'type'       => 'legacy',
                    'command'    => $slug,
                    'updated_at' => now(),
                ]);
        }

        // Re-activar las 6 desactivadas.
        $toEnable = [
            'iess-billing-sync', 'iess-derivaciones-scrape-missing',
            'iess-derivaciones-sync', 'solicitudes-derivaciones-refresh',
            'solicitudes-overdue', 'solicitudes-reminders',
        ];

        DB::table('cron_schedule')
            ->whereIn('slug', $toEnable)
            ->update([
                'enabled'    => 1,
                'updated_at' => now(),
            ]);
    }
};
