<?php

namespace App\Console\Commands;

use App\Modules\Whatsapp\Services\WhatsappOperationalNotificationPreviewService;
use Illuminate\Console\Command;

/**
 * Dry-run preview command — shows which critical HOT unassigned alerts
 * would be notified in a future Fase 4C. Never sends anything.
 *
 * Usage:
 *   php8.3-cli artisan whatsapp:operational-notifications-preview
 *   php8.3-cli artisan whatsapp:operational-notifications-preview --date=2026-06-29
 *   php8.3-cli artisan whatsapp:operational-notifications-preview --json
 */
class WhatsappOperationalNotificationsPreview extends Command
{
    protected $signature = 'whatsapp:operational-notifications-preview
                            {--date= : Fecha YYYY-MM-DD (default: hoy)}
                            {--json  : Salida JSON cruda}';

    protected $description = '[DRY-RUN] Preview de notificaciones internas del Alert Engine. No envía nada.';

    public function handle(WhatsappOperationalNotificationPreviewService $service): int
    {
        $date = trim((string) ($this->option('date') ?: date('Y-m-d')));

        $result = $service->preview(['date' => $date]);

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            return 0;
        }

        $this->newLine();
        $this->line('╔══════════════════════════════════════════════════════╗');
        $this->line('║  NOTIFICATION PREVIEW — DRY-RUN — NO SE ENVÍA NADA  ║');
        $this->line('╚══════════════════════════════════════════════════════╝');
        $this->newLine();
        $this->line("  Fecha:          {$date}");
        $this->line("  Modo:           dry_run");
        $this->line("  Canal:          none (no configurado)");
        $this->line("  Evaluadas:      {$result['evaluated']}");
        $this->line("  Candidatas:     {$result['would_notify']}");
        $this->line("  DB writes:      0");
        $this->newLine();

        if (empty($result['notifications'])) {
            $this->info('  Sin candidatas para esta fecha.');
            $this->newLine();
            return 0;
        }

        foreach ($result['notifications'] as $i => $n) {
            $num = $i + 1;
            $this->line("  ── Candidata #{$num} ──────────────────────────────────");
            $this->line("  Conversación:  #{$n['conversation_id']}");
            $this->line("  Nombre:        {$n['display_name']}");
            $this->line("  WhatsApp:      {$n['wa_number']}");
            $this->line("  HC:            " . ($n['hc_number'] ?: '—'));
            $this->line("  Motivo:        {$n['topic_label']}");
            $this->line("  Esperando:     {$n['waiting_minutes']} min");
            $this->line("  URL:           {$n['chat_url']}");
            $this->newLine();
            $this->line('  Mensaje preview:');
            foreach (explode("\n", $n['message_preview']) as $line) {
                $this->line("    {$line}");
            }
            $this->newLine();
        }

        $this->warn('  [DRY-RUN] No se envió ninguna notificación.');
        $this->newLine();

        return 0;
    }
}
