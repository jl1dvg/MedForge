<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAutoresponderSession;
use App\Models\WhatsappConversation;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Schema;

class ConversationAbandonmentMonitorService
{
    public function __construct(
        private readonly ConversationOpsService $conversationOpsService = new ConversationOpsService(),
    ) {
    }

    /**
     * @param array{dry_run?:bool,limit?:int,max_age_hours?:int} $options
     * @return array{scanned:int,candidates:int,enqueued:int,skipped:int,rows:array<int,array<string,mixed>>,error?:string}
     */
    public function scan(array $options = []): array
    {
        if (!Schema::hasTable('whatsapp_autoresponder_sessions') || !Schema::hasTable('whatsapp_conversations')) {
            return [
                'scanned' => 0,
                'candidates' => 0,
                'enqueued' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'Tablas de sesiones o conversaciones no disponibles.',
            ];
        }

        $dryRun = (bool) ($options['dry_run'] ?? false);
        $limit = max(1, (int) ($options['limit'] ?? 100));
        $maxAgeHours = max(1, (int) ($options['max_age_hours'] ?? config('whatsapp.migration.abandonment_monitor.max_age_hours', 72)));
        $rules = $this->stateRules();
        if ($rules === []) {
            return [
                'scanned' => 0,
                'candidates' => 0,
                'enqueued' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'No hay reglas de abandono configuradas.',
            ];
        }

        $oldestThreshold = now()->subMinutes(min(array_values($rules)));
        $newestThreshold = now()->subHours($maxAgeHours);
        $sessions = WhatsappAutoresponderSession::query()
            ->with('whatsapp_conversation')
            ->where('last_interaction_at', '>=', $newestThreshold)
            ->where('last_interaction_at', '<=', $oldestThreshold)
            ->orderBy('last_interaction_at')
            ->limit($limit * 4)
            ->get();

        $rows = [];
        $scanned = 0;
        $candidates = 0;
        $enqueued = 0;
        $skipped = 0;

        foreach ($sessions as $session) {
            if ($candidates >= $limit) {
                break;
            }

            $scanned++;
            $context = is_array($session->context) ? $session->context : [];
            $state = strtolower(trim((string) ($context['state'] ?? '')));
            if ($state === '' || !isset($rules[$state])) {
                $skipped++;
                continue;
            }

            $conversation = $session->whatsapp_conversation;
            if (!$conversation instanceof WhatsappConversation) {
                $skipped++;
                continue;
            }

            $idleMinutes = $session->last_interaction_at instanceof \Carbon\CarbonInterface
                ? $session->last_interaction_at->diffInMinutes(now())
                : null;
            if ($idleMinutes === null || $idleMinutes < $rules[$state]) {
                $skipped++;
                continue;
            }

            if ((bool) ($conversation->needs_human ?? false)) {
                $skipped++;
                continue;
            }

            $monitorContext = is_array($context['abandonment_monitor'] ?? null) ? $context['abandonment_monitor'] : [];
            $escalatedAt = $this->parseNullableCarbon($monitorContext['escalated_at'] ?? null);
            if ($escalatedAt !== null && $session->last_interaction_at instanceof \Carbon\CarbonInterface
                && $session->last_interaction_at->lessThanOrEqualTo($escalatedAt)
            ) {
                $skipped++;
                continue;
            }

            $candidates++;
            $row = [
                'conversation_id' => (int) $conversation->id,
                'wa_number' => (string) $conversation->wa_number,
                'state' => $state,
                'state_label' => $this->stateLabel($state),
                'idle_minutes' => (int) round($idleMinutes),
                'threshold_minutes' => $rules[$state],
                'patient' => (string) ($conversation->patient_full_name ?: $conversation->display_name ?: $conversation->wa_number),
            ];

            if (!$dryRun) {
                $note = $this->handoffNote($state, $idleMinutes);
                $this->conversationOpsService->enqueueConversationToRole(
                    (int) $conversation->id,
                    $this->defaultRoleId(),
                    0,
                    true,
                    $note
                );

                $context['abandonment_monitor'] = [
                    'escalated_at' => now()->toISOString(),
                    'state' => $state,
                    'idle_minutes' => $idleMinutes,
                ];

                $session->fill([
                    'context' => $context,
                ])->save();

                $enqueued++;
            }

            $rows[] = $row;
        }

        return [
            'scanned' => $scanned,
            'candidates' => $candidates,
            'enqueued' => $enqueued,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string,int>
     */
    private function stateRules(): array
    {
        return [
            'consentimiento_pendiente' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.consentimiento_pendiente', 15),
            'esperando_cedula' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.esperando_cedula', 15),
            'agenda_esperando_sede' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_subespecialidad' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_medico' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_doctor_directo' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_sede_directa' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_procedimiento' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_dia' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_horario' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_confirmar_cita' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.confirmacion', 10),
        ];
    }

    private function defaultRoleId(): int
    {
        return max(1, (int) config('whatsapp.migration.abandonment_monitor.role_id', 4));
    }

    private function handoffNote(string $state, int $idleMinutes): string
    {
        return match ($state) {
            'consentimiento_pendiente' => sprintf(
                'Seguimiento automático de captación. El paciente abandonó el flujo en consentimiento hace %d min antes de continuar con su cita.',
                $idleMinutes
            ),
            'esperando_cedula' => sprintf(
                'Seguimiento automático de captación. El paciente abandonó el flujo en cédula hace %d min antes de continuar con su cita.',
                $idleMinutes
            ),
            'agenda_confirmar_cita' => sprintf(
                'Seguimiento automático de captación. El paciente no confirmó su cita hace %d min.',
                $idleMinutes
            ),
            default => sprintf(
                'Seguimiento automático de captación. El paciente abandonó el agendamiento en "%s" hace %d min antes de cerrar su cita.',
                $this->stateLabel($state),
                $idleMinutes
            ),
        };
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'consentimiento_pendiente' => 'Consentimiento pendiente',
            'esperando_cedula' => 'Esperando cédula',
            'agenda_esperando_sede' => 'Esperando sede',
            'agenda_esperando_subespecialidad' => 'Esperando especialidad',
            'agenda_esperando_medico' => 'Esperando médico',
            'agenda_esperando_doctor_directo' => 'Esperando selección de doctor',
            'agenda_esperando_sede_directa' => 'Esperando sede del doctor',
            'agenda_esperando_procedimiento' => 'Resolviendo procedimiento',
            'agenda_esperando_dia' => 'Esperando día',
            'agenda_esperando_horario' => 'Esperando horario',
            'agenda_confirmar_cita' => 'Esperando confirmación de cita',
            default => $state,
        };
    }

    private function parseNullableCarbon(mixed $value): ?CarbonImmutable
    {
        if (!is_scalar($value) || trim((string) $value) === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value);
        } catch (\Throwable) {
            return null;
        }
    }
}
