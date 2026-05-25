<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CronScheduleSeeder extends Seeder
{
    /** @var string[] */
    private const OBSOLETE_SLUGS = [
        'cive-index-admisiones-sync',      // SSH ya no disponible
        'crm-task-reminders-legacy',       // cubierto por artisan solicitudes:crm-task-reminders
        'whatsapp-handoff-requeue',        // cubierto por artisan whatsapp:handoff-requeue-expired
        'solicitudes-crm-sync-legacy',     // cubierto por artisan solicitudes:crm-sync
        'reporting-async-queue',           // deprecado desde 2026-03-06
    ];

    public function run(): void
    {
        DB::table('cron_schedule')->whereIn('slug', self::OBSOLETE_SLUGS)->delete();

        $artisan = [
            ['slug' => 'evaluar-sla', 'name' => 'Evaluar SLA solicitudes', 'command' => 'solicitudes:evaluar-sla', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Evalúa y marca solicitudes fuera de SLA.'],
            ['slug' => 'derivaciones-scrape-missing', 'name' => 'Scraping derivaciones faltantes', 'command' => 'derivaciones:scrape-missing --limit=200 --max-attempts=3 --cooldown-hours=6', 'cron_expression' => '0 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Scraping de derivaciones sin código en billing_main.'],
            ['slug' => 'enviar-recordatorios', 'name' => 'Recordatorios quirúrgicos', 'command' => 'solicitudes:enviar-recordatorios', 'cron_expression' => '0 8 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Notificaciones automáticas para cirugías próximas.'],
            ['slug' => 'crm-sync', 'name' => 'Sincronización CRM solicitudes', 'command' => 'solicitudes:crm-sync --lookback=3 --lookahead=14', 'cron_expression' => '0 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza solicitudes con SigCenter en ventana horaria.'],
            ['slug' => 'derivaciones-refresh', 'name' => 'Refresh derivaciones sin número', 'command' => 'solicitudes:derivaciones-refresh --solo-sin-numero', 'cron_expression' => '0 7,14 * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Actualiza derivaciones sin número a las 7am y 2pm.'],
            ['slug' => 'marcar-vencidas', 'name' => 'Marcar solicitudes vencidas', 'command' => 'solicitudes:marcar-vencidas', 'cron_expression' => '0 7 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Marca como vencidas las solicitudes cuyo agendamiento ya pasó.'],
            ['slug' => 'crm-task-reminders', 'name' => 'Recordatorios de tareas CRM', 'command' => 'solicitudes:crm-task-reminders --limit=100', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Dispara avisos cuando vence remind_at de una tarea CRM.'],
            ['slug' => 'handoff-requeue-expired', 'name' => 'Reencolar handoffs WhatsApp', 'command' => 'whatsapp:handoff-requeue-expired', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Reencola conversaciones cuyo tiempo de asignación expiró.'],
            ['slug' => 'flowmaker-shadow-sync', 'name' => 'Flowmaker shadow sync', 'command' => 'whatsapp:flowmaker-shadow-sync --limit=100', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Compara ejecución real vs shadow del flowmaker.'],
            ['slug' => 'monitor-abandonment', 'name' => 'Monitor abandono WhatsApp', 'command' => 'whatsapp:monitor-abandonment --limit=100', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Detecta y escala conversaciones abandonadas.'],
            ['slug' => 'sigcenter-availability-sync', 'name' => 'Disponibilidad SigCenter WhatsApp', 'command' => "whatsapp:sigcenter-availability-sync --days=7 --specialty='oftalmologo general'", 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza disponibilidad de agenda para el bot de citas.'],
            ['slug' => 'appointment-reminders-24h', 'name' => 'Recordatorios cita 24h', 'command' => 'whatsapp:appointment-reminders 24h --limit=200', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Envía recordatorio WhatsApp 24h antes de la cita.'],
            ['slug' => 'appointment-reminders-2h', 'name' => 'Recordatorios cita 2h', 'command' => 'whatsapp:appointment-reminders 2h --limit=200', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Envía recordatorio WhatsApp 2h antes de la cita.'],
            ['slug' => 'nas-index-day', 'name' => 'Índice NAS — 2 días', 'command' => 'imagenes:nas-index --days=2', 'cron_expression' => '0 7-19/2 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Indexa imágenes NAS de los últimos 2 días (cada 2h en horario hábil).'],
            ['slug' => 'nas-index-30days', 'name' => 'Índice NAS — 30 días', 'command' => 'imagenes:nas-index --days=30 --force', 'cron_expression' => '30 2 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Re-indexación nocturna de 30 días.'],
            ['slug' => 'index-admisiones-short', 'name' => 'Admisiones sync corto', 'command' => 'index-admisiones:sync --lookback=1 --lookahead=0 --extractor=scraper', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza admisiones del día cada 15 minutos.'],
            ['slug' => 'index-admisiones-wide', 'name' => 'Admisiones sync amplio', 'command' => 'index-admisiones:sync --lookback=14 --lookahead=14 --extractor=scraper', 'cron_expression' => '0 0,6,12,18 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza admisiones de 14 días pasados y futuros (4 veces al día).'],
            ['slug' => 'billing-facturacion-real', 'name' => 'Facturación real sync', 'command' => 'billing:facturacion-real-sync --extractor=scraper', 'cron_expression' => '0 */4 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Sincroniza facturación real del mes actual cada 4 horas.'],
            ['slug' => 'farmacia-conciliacion-short', 'name' => 'Conciliación recetas — corto', 'command' => 'farmacia:conciliar-recetas --lookback=14 --lookahead=0', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Concilia recetas de farmacia de los últimos 14 días.'],
            ['slug' => 'farmacia-conciliacion-wide', 'name' => 'Conciliación recetas — amplio', 'command' => 'farmacia:conciliar-recetas --lookback=45 --lookahead=0', 'cron_expression' => '30 2 * * *', 'run_in_background' => 1, 'without_overlapping' => 1, 'description' => 'Concilia recetas de farmacia de los últimos 45 días (nocturno).'],
        ];

        $legacy = [
            ['slug' => 'solicitudes-overdue', 'name' => 'Actualizar solicitudes atrasadas', 'command' => 'solicitudes-overdue', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Marca solicitudes vencidas (legacy).'],
            ['slug' => 'solicitudes-reminders', 'name' => 'Recordatorios de cirugías (legacy)', 'command' => 'solicitudes-reminders', 'cron_expression' => '*/10 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Notificaciones para cirugías próximas (legacy).'],
            ['slug' => 'crm-task-supervisor-escalations', 'name' => 'Escalamientos CRM', 'command' => 'crm-task-supervisor-escalations', 'cron_expression' => '*/5 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Notifica supervisores cuando una tarea CRM vence.'],
            ['slug' => 'billing-autocreation', 'name' => 'Prefacturación automática', 'command' => 'billing-autocreation', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Crea registros en billing_main para solicitudes listas.'],
            ['slug' => 'stats-refresh', 'name' => 'Estadísticas diarias', 'command' => 'stats-refresh', 'cron_expression' => '0 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Recalcula métricas operativas.'],
            ['slug' => 'kpi-refresh', 'name' => 'Snapshots de KPIs', 'command' => 'kpi-refresh', 'cron_expression' => '0 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Recalcula KPIs para dashboards.'],
            ['slug' => 'ai-sync', 'name' => 'Analítica IA', 'command' => 'ai-sync', 'cron_expression' => '*/30 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza resultados de análisis IA.'],
            ['slug' => 'cive-extension-health', 'name' => 'Supervisión API CIVE Extension', 'command' => 'cive-extension-health', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Verifica disponibilidad de endpoints de la extensión.'],
            ['slug' => 'identity-verification-expiration', 'name' => 'Caducidad biométrica', 'command' => 'identity-verification-expiration', 'cron_expression' => '0 2 * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Marca certificaciones biométricas vencidas.'],
            ['slug' => 'iess-derivaciones-sync', 'name' => 'Derivaciones IESS sync', 'command' => 'iess-derivaciones-sync', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza derivaciones IESS.'],
            ['slug' => 'iess-derivaciones-scrape-missing', 'name' => 'Scraping derivaciones IESS', 'command' => 'iess-derivaciones-scrape-missing', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Scraping de derivaciones IESS faltantes.'],
            ['slug' => 'iess-billing-sync', 'name' => 'Facturas IESS sync', 'command' => 'iess-billing-sync', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Sincroniza facturación IESS.'],
            ['slug' => 'solicitudes-derivaciones-refresh', 'name' => 'Derivaciones en solicitudes', 'command' => 'solicitudes-derivaciones-refresh', 'cron_expression' => '*/15 * * * *', 'run_in_background' => 0, 'without_overlapping' => 1, 'description' => 'Actualiza derivaciones para solicitudes estatales.'],
        ];

        $rows = [];
        foreach ($artisan as $task) {
            $rows[] = array_merge(['type' => 'artisan', 'enabled' => 1], $task);
        }
        foreach ($legacy as $task) {
            $rows[] = array_merge(['type' => 'legacy', 'enabled' => 1], $task);
        }

        foreach ($rows as $row) {
            DB::table('cron_schedule')->updateOrInsert(
                ['slug' => $row['slug']],
                $row
            );
        }
    }
}
