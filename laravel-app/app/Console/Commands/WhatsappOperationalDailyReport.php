<?php

namespace App\Console\Commands;

use App\Modules\Whatsapp\Services\WhatsappOperationalDailyReportService;
use Illuminate\Console\Command;

/**
 * Read-only daily operational report command.
 *
 * Usage:
 *   php8.3-cli artisan whatsapp:operational-daily-report
 *   php8.3-cli artisan whatsapp:operational-daily-report --date=2026-06-29
 *   php8.3-cli artisan whatsapp:operational-daily-report --json
 *
 * No DB writes. No messages sent. No scheduler dependency.
 */
class WhatsappOperationalDailyReport extends Command
{
    protected $signature = 'whatsapp:operational-daily-report
                            {--date=  : Fecha YYYY-MM-DD (default: hoy)}
                            {--json   : Salida JSON cruda}
                            {--limit= : Límite de alertas (default: 500)}';

    protected $description = '[READ-ONLY] Reporte diario del Alert Engine. No envía nada. No escribe DB.';

    public function handle(WhatsappOperationalDailyReportService $service): int
    {
        $date  = trim((string) ($this->option('date')  ?: date('Y-m-d')));
        $limit = max(1, min(500, (int) ($this->option('limit') ?: 500)));

        $result = $service->report(['date' => $date, 'limit' => $limit]);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $s   = $result['summary'];
        $np  = $result['notification_preview'];

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║     REPORTE DIARIO ALERT ENGINE — SOLO LECTURA       ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line("  Fecha:          {$date}");
        $this->line("  Evaluadas:      {$s['evaluated']}");
        $this->line("  Alertas total:  {$s['alerts_total']}");
        $this->line("  Críticas:       {$s['critical']}");
        $this->line("  Altas:          {$s['high']}");
        $this->line("  Medias:         {$s['medium']}");
        $this->line("  Bajas:          {$s['low']}");
        $this->newLine();

        if (!empty($result['by_type'])) {
            $this->line('  ── Por tipo ──────────────────────────────────────────');
            foreach ($result['by_type'] as $type => $count) {
                $this->line("  {$type}: {$count}");
            }
            $this->newLine();
        }

        if (!empty($result['by_category'])) {
            $this->line('  ── Por categoría ─────────────────────────────────────');
            foreach ($result['by_category'] as $cat => $count) {
                $this->line("  {$cat}: {$count}");
            }
            $this->newLine();
        }

        if (!empty($result['top_topics'])) {
            $this->line('  ── Top motivos ───────────────────────────────────────');
            foreach (array_slice($result['top_topics'], 0, 10) as $t) {
                $this->line("  {$t['topic_label']}: {$t['count']}");
            }
            $this->newLine();
        }

        if (!empty($result['by_agent'])) {
            $this->line('  ── Por agente ────────────────────────────────────────');
            foreach ($result['by_agent'] as $ag) {
                $name = $ag['assigned_user_name'];
                $total = $ag['alerts_total'];
                $crit  = $ag['critical'];
                $this->line("  {$name}: {$total} alertas ({$crit} críticas)");
            }
            $this->newLine();
        }

        $this->line('  ── Notification Preview (dry-run) ────────────────────');
        $this->line("  Candidatas a notificar: {$np['would_notify']}");
        $this->line("  Canal:                  {$np['channel']}");
        $this->line("  Estado:                 {$np['mode']}");
        $this->line("  Política:               {$np['policy']}");
        $this->newLine();

        if (!empty($result['recommendations'])) {
            $this->line('  ── Recomendaciones ───────────────────────────────────');
            foreach ($result['recommendations'] as $rec) {
                $this->line("  • {$rec}");
            }
            $this->newLine();
        }

        $this->warn('  [READ-ONLY] No se enviaron notificaciones. DB writes: 0.');
        $this->newLine();

        return 0;
    }
}
