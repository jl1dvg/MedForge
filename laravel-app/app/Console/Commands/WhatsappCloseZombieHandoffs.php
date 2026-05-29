<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappCloseZombieHandoffs extends Command
{
    protected $signature = 'whatsapp:close-zombie-handoffs
                            {--days=7 : Días de inactividad para considerar zombie}
                            {--dry-run : Reporta sin escribir}';

    protected $description = 'Cierra handoffs activos cuya conversación no tuvo actividad en N días';

    public function handle(): int
    {
        if (!Schema::hasTable('whatsapp_handoffs') || !Schema::hasTable('whatsapp_conversations')) {
            $this->error('Tablas whatsapp no encontradas.');
            return 1;
        }

        $days     = (int) $this->option('days');
        $dryRun   = (bool) $this->option('dry-run');
        $cutoff   = Carbon::now()->subDays($days)->format('Y-m-d H:i:s');
        $now      = Carbon::now()->format('Y-m-d H:i:s');

        // Handoffs activos cuya conversación no tuvo ningún mensaje en los últimos N días
        $rows = DB::select(
            'SELECT h.id AS handoff_id, h.conversation_id
             FROM whatsapp_handoffs h
             INNER JOIN whatsapp_conversations c ON c.id = h.conversation_id
             WHERE h.status IN (\'queued\', \'assigned\', \'expired\')
               AND NOT EXISTS (
                   SELECT 1 FROM whatsapp_messages m
                   WHERE m.conversation_id = h.conversation_id
                     AND COALESCE(m.message_timestamp, m.created_at) >= ?
               )',
            [$cutoff]
        );

        $total = count($rows);
        $this->info("Handoffs zombie encontrados (inactivos >{$days}d): {$total}");

        if ($total === 0 || $dryRun) {
            if ($dryRun) {
                $this->warn('--dry-run activo, no se escribió nada.');
            }
            return 0;
        }

        $handoffIds      = array_column($rows, 'handoff_id');
        $conversationIds = array_unique(array_column($rows, 'conversation_id'));

        // Cerrar handoffs
        DB::table('whatsapp_handoffs')
            ->whereIn('id', $handoffIds)
            ->update(['status' => 'resolved', 'resolved_at' => $now, 'last_activity_at' => $now]);

        // Quitar flag needs_human de las conversaciones
        DB::table('whatsapp_conversations')
            ->whereIn('id', $conversationIds)
            ->update(['needs_human' => false, 'assigned_user_id' => null, 'assigned_at' => null]);

        // Registrar eventos si la tabla existe
        if (Schema::hasTable('whatsapp_handoff_events')) {
            $events = array_map(fn ($id) => [
                'handoff_id'    => $id,
                'event_type'    => 'resolved',
                'actor_user_id' => null,
                'notes'         => "Auto-cierre: sin actividad en más de {$days} días",
                'created_at'    => $now,
            ], $handoffIds);

            foreach (array_chunk($events, 500) as $chunk) {
                DB::table('whatsapp_handoff_events')->insert($chunk);
            }
        }

        $this->info("Cerrados: {$total} handoffs / " . count($conversationIds) . ' conversaciones.');
        return 0;
    }
}
