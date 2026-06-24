<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAutoresponderSession;
use App\Models\WhatsappConversation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ConversationAbandonmentMonitorService
{
    public function __construct(
        private readonly ConversationOpsService $conversationOpsService = new ConversationOpsService(),
        private readonly AutomatedConversationDispatchService $dispatchService = new AutomatedConversationDispatchService(),
        private readonly WhatsappRealtimeService $realtime = new WhatsappRealtimeService(),
    ) {
    }

    /**
     * @param array{dry_run?:bool,limit?:int,max_age_hours?:int} $options
     * @return array{scanned:int,candidates:int,nudged:int,closed:int,enqueued:int,skipped:int,rows:array<int,array<string,mixed>>,error?:string}
     */
    public function scan(array $options = []): array
    {
        if (!Schema::hasTable('whatsapp_autoresponder_sessions') || !Schema::hasTable('whatsapp_conversations')) {
            return [
                'scanned' => 0,
                'candidates' => 0,
                'nudged' => 0,
                'closed' => 0,
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
                'nudged' => 0,
                'closed' => 0,
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
        $nudged = 0;
        $closed = 0;
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

            if ($conversation->closed_at !== null) {
                $skipped++;
                continue;
            }

            $monitorContext = is_array($context['abandonment_monitor'] ?? null) ? $context['abandonment_monitor'] : [];
            $escalatedAt = $this->parseNullableCarbon($monitorContext['escalated_at'] ?? null);
            $closedAt = $this->parseNullableCarbon($monitorContext['closed_at'] ?? null);
            $nudgedAt = $this->parseNullableCarbon($monitorContext['nudged_at'] ?? null);
            if ($this->sessionHasNoNewInboundSince($session->last_interaction_at, $escalatedAt)
                || $this->sessionHasNoNewInboundSince($session->last_interaction_at, $closedAt)
            ) {
                $skipped++;
                continue;
            }

            $candidates++;
            $action = 'candidate';
            $row = [
                'conversation_id' => (int) $conversation->id,
                'wa_number' => (string) $conversation->wa_number,
                'state' => $state,
                'state_label' => $this->stateLabel($state),
                'idle_minutes' => (int) round($idleMinutes),
                'threshold_minutes' => $rules[$state],
                'patient' => (string) ($conversation->patient_full_name ?: $conversation->display_name ?: $conversation->wa_number),
                'action' => $action,
            ];

            if (!$dryRun) {
                if ($nudgedAt === null || !$this->sessionHasNoNewInboundSince($session->last_interaction_at, $nudgedAt)) {
                    $nudgeMessage = $this->buildNudgeMessage($state, $context);
                    if (is_array($nudgeMessage)) {
                        $this->dispatchService->sendSystemMessage($conversation, $nudgeMessage);
                    } else {
                        $this->dispatchService->sendSystemText($conversation, $nudgeMessage);
                    }

                    $context['abandonment_monitor'] = array_merge($monitorContext, [
                        'nudged_at' => now()->toISOString(),
                        'nudged_count' => (int) ($monitorContext['nudged_count'] ?? 0) + 1,
                        'abandonment_status' => 'nudged',
                        'state' => $state,
                        'idle_minutes' => $idleMinutes,
                    ]);

                    $session->fill([
                        'context' => $context,
                    ])->save();

                    $row['action'] = 'nudged';
                    $nudged++;
                } elseif ($this->followupWindowExpired($nudgedAt, $state)) {
                    if ($this->isHighIntentState($state)) {
                        $note = $this->handoffNote($state, $idleMinutes);
                        $this->conversationOpsService->enqueueConversationToRole(
                            (int) $conversation->id,
                            $this->defaultRoleId(),
                            0,
                            true,
                            $note
                        );
                        $this->conversationOpsService->recordHandoffEventForConversation(
                            (int) $conversation->id,
                            'abandonment_escalated',
                            null,
                            $note
                        );
                        $this->realtime->broadcastHandoffOperationalEvent([
                            'event' => 'handoff.escalated',
                            'conversation_id' => (int) $conversation->id,
                            'priority' => 'high',
                            'topic' => 'captacion_agendar',
                            'reason' => 'abandonment_monitor',
                            'assigned_to' => null,
                            'timestamp' => now()->toISOString(),
                        ]);

                        $context['abandonment_monitor'] = array_merge($monitorContext, [
                            'nudged_at' => $nudgedAt->toISOString(),
                            'nudged_count' => (int) ($monitorContext['nudged_count'] ?? 1),
                            'abandonment_status' => 'escalated',
                            'escalated_at' => now()->toISOString(),
                            'state' => $state,
                            'idle_minutes' => $idleMinutes,
                        ]);

                        $session->fill([
                            'context' => $context,
                        ])->save();

                        $row['action'] = 'escalated';
                        $enqueued++;
                    } else {
                        $context['abandonment_monitor'] = array_merge($monitorContext, [
                            'nudged_at' => $nudgedAt->toISOString(),
                            'nudged_count' => (int) ($monitorContext['nudged_count'] ?? 1),
                            'abandonment_status' => 'closed',
                            'closed_at' => now()->toISOString(),
                            'closed_reason' => $this->closedReason($state),
                            'state' => $state,
                            'idle_minutes' => $idleMinutes,
                        ]);

                        $session->fill([
                            'context' => $context,
                        ])->save();

                        $row['action'] = 'closed';
                        $closed++;
                    }
                } else {
                    $skipped++;
                    continue;
                }
            }

            $rows[] = $row;
        }

        return [
            'scanned' => $scanned,
            'candidates' => $candidates,
            'nudged' => $nudged,
            'closed' => $closed,
            'enqueued' => $enqueued,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @return array{sessions:array<int,array<string,mixed>>,handoffs:array<string,mixed>,error?:string}
     */
    public function audit(array $options = []): array
    {
        if (!Schema::hasTable('whatsapp_autoresponder_sessions') || !Schema::hasTable('whatsapp_conversations')) {
            return [
                'sessions' => [],
                'handoffs' => [],
                'error' => 'Tablas de sesiones o conversaciones no disponibles.',
            ];
        }

        $rules = $this->stateRules();
        $maxAgeHours = max(1, (int) ($options['max_age_hours'] ?? config('whatsapp.migration.abandonment_monitor.max_age_hours', 72)));
        $newestThreshold = now()->subHours($maxAgeHours);

        $sessions = WhatsappAutoresponderSession::query()
            ->where('last_interaction_at', '>=', $newestThreshold)
            ->get();

        $sessionSummary = [];
        foreach ($sessions as $session) {
            $context = is_array($session->context) ? $session->context : [];
            $state = strtolower(trim((string) ($context['state'] ?? '')));
            if ($state === '' || !isset($rules[$state])) {
                continue;
            }

            $idleMinutes = $session->last_interaction_at instanceof CarbonInterface
                ? $session->last_interaction_at->diffInMinutes(now())
                : null;
            $monitor = is_array($context['abandonment_monitor'] ?? null) ? $context['abandonment_monitor'] : [];
            $status = (string) ($monitor['abandonment_status'] ?? 'open');

            $sessionSummary[$state] ??= [
                'state' => $state,
                'state_label' => $this->stateLabel($state),
                'threshold_minutes' => $rules[$state],
                'total' => 0,
                'over_threshold' => 0,
                'nudged' => 0,
                'closed' => 0,
                'escalated' => 0,
            ];

            $sessionSummary[$state]['total']++;
            if ($idleMinutes !== null && $idleMinutes >= $rules[$state]) {
                $sessionSummary[$state]['over_threshold']++;
            }
            if ($status === 'nudged') {
                $sessionSummary[$state]['nudged']++;
            } elseif ($status === 'closed') {
                $sessionSummary[$state]['closed']++;
            } elseif ($status === 'escalated') {
                $sessionSummary[$state]['escalated']++;
            }
        }

        $handoffSummary = [
            'active' => 0,
            'queued' => 0,
            'assigned' => 0,
            'older_than_24h' => 0,
            'topics' => [],
        ];

        if (Schema::hasTable('whatsapp_handoffs')) {
            $handoffs = DB::table('whatsapp_handoffs')
                ->select(['status', 'topic', 'queued_at', 'created_at'])
                ->whereIn('status', ['queued', 'assigned'])
                ->get();

            foreach ($handoffs as $handoff) {
                $handoffSummary['active']++;
                $status = strtolower(trim((string) ($handoff->status ?? '')));
                if ($status === 'queued') {
                    $handoffSummary['queued']++;
                } elseif ($status === 'assigned') {
                    $handoffSummary['assigned']++;
                }

                $topic = trim((string) ($handoff->topic ?? ''));
                $topic = $topic !== '' ? $topic : '(sin_topic)';
                $handoffSummary['topics'][$topic] = (int) ($handoffSummary['topics'][$topic] ?? 0) + 1;

                $queuedAt = $this->parseNullableCarbon($handoff->queued_at ?? null)
                    ?? $this->parseNullableCarbon($handoff->created_at ?? null);
                if ($queuedAt !== null && $queuedAt->lessThanOrEqualTo(now()->subHours(24))) {
                    $handoffSummary['older_than_24h']++;
                }
            }
        }

        uasort($handoffSummary['topics'], static fn (int $a, int $b): int => $b <=> $a);

        return [
            'sessions' => array_values($sessionSummary),
            'handoffs' => $handoffSummary,
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
            'esperando_correo_paciente_nuevo' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.esperando_cedula', 15),
            'esperando_origen_lead_paciente_nuevo' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.esperando_cedula', 15),
            'agenda_filtro_sector' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_sede_inicio' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'menu_agendar_modo' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_sede' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_subespecialidad' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_medico' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'esperando_nombre_doctor' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_doctor_directo' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_sede_directa' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'esperando_sintomas_triage' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'triage_confirmacion' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_fecha_general' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_medico_general_por_fecha' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_esperando_horario_general_fecha' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.agenda', 12),
            'agenda_confirmar_cita_fecha_general' => (int) config('whatsapp.migration.abandonment_monitor.thresholds.confirmacion', 10),
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

    private function closedReason(string $state): string
    {
        return match (true) {
            $state === 'consentimiento_pendiente' => 'abandono_consentimiento',
            in_array($state, ['esperando_cedula', 'esperando_correo_paciente_nuevo', 'esperando_origen_lead_paciente_nuevo'], true) => 'abandono_identificacion',
            $this->isHighIntentState($state) => 'abandono_agenda_avanzada',
            default => 'abandono_agenda_temprana',
        };
    }

    private function isHighIntentState(string $state): bool
    {
        return in_array($state, [
            'agenda_esperando_medico',
            'esperando_nombre_doctor',
            'agenda_esperando_doctor_directo',
            'agenda_esperando_sede_directa',
            'agenda_esperando_fecha_general',
            'agenda_esperando_medico_general_por_fecha',
            'agenda_esperando_horario_general_fecha',
            'agenda_esperando_dia',
            'agenda_esperando_horario',
            'agenda_confirmar_cita',
            'agenda_confirmar_cita_fecha_general',
        ], true);
    }

    private function followupWindowExpired(CarbonImmutable $nudgedAt, string $state): bool
    {
        $minutes = $this->isHighIntentState($state)
            ? max(1, (int) config('whatsapp.migration.abandonment_monitor.followup_minutes.high_intent', 10))
            : max(1, (int) config('whatsapp.migration.abandonment_monitor.followup_minutes.low_intent', 10));

        return $nudgedAt->addMinutes($minutes)->lessThanOrEqualTo(now());
    }

    private function sessionHasNoNewInboundSince(mixed $lastInteractionAt, ?CarbonImmutable $marker): bool
    {
        return $marker !== null
            && $lastInteractionAt instanceof CarbonInterface
            && $lastInteractionAt->lessThanOrEqualTo($marker);
    }

    private function stateLabel(string $state): string
    {
        return match ($state) {
            'consentimiento_pendiente' => 'Consentimiento pendiente',
            'esperando_cedula' => 'Esperando cédula',
            'esperando_correo_paciente_nuevo' => 'Esperando correo',
            'esperando_origen_lead_paciente_nuevo' => 'Esperando origen del lead',
            'agenda_filtro_sector' => 'Esperando tipo de paciente',
            'agenda_esperando_sede_inicio' => 'Esperando sede inicial',
            'menu_agendar_modo' => 'Esperando modo de agenda',
            'agenda_esperando_sede' => 'Esperando sede',
            'agenda_esperando_subespecialidad' => 'Esperando especialidad',
            'agenda_esperando_medico' => 'Esperando médico',
            'esperando_nombre_doctor' => 'Esperando nombre de doctor',
            'agenda_esperando_doctor_directo' => 'Esperando selección de doctor',
            'agenda_esperando_sede_directa' => 'Esperando sede del doctor',
            'esperando_sintomas_triage' => 'Esperando síntomas',
            'triage_confirmacion' => 'Esperando decisión del triage',
            'agenda_esperando_fecha_general' => 'Esperando fecha por agenda general',
            'agenda_esperando_medico_general_por_fecha' => 'Esperando médico por fecha',
            'agenda_esperando_horario_general_fecha' => 'Esperando horario por fecha',
            'agenda_confirmar_cita_fecha_general' => 'Esperando confirmación de cita por fecha',
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
    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|string
     */
    private function buildNudgeMessage(string $state, array $context): array|string
    {
        $agendaStates = [
            'agenda_esperando_sede', 'agenda_esperando_subespecialidad', 'agenda_esperando_medico',
            'esperando_nombre_doctor', 'agenda_esperando_doctor_directo', 'agenda_esperando_sede_directa',
            'agenda_esperando_fecha_general', 'agenda_esperando_medico_general_por_fecha',
            'agenda_esperando_horario_general_fecha', 'agenda_confirmar_cita_fecha_general',
            'agenda_esperando_procedimiento', 'agenda_esperando_dia', 'agenda_esperando_horario',
            'agenda_confirmar_cita', 'agenda_filtro_sector', 'agenda_esperando_sede_inicio',
            'menu_agendar_modo',
        ];

        $isAgendaState = str_starts_with($state, 'agenda_') || in_array($state, $agendaStates, true);

        if (!$isAgendaState) {
            return (string) config(
                'whatsapp.migration.abandonment_monitor.nudge_message',
                '⏳ Parece que se interrumpió tu proceso. Si aún deseas continuar, responde este mensaje y con gusto te ayudo.'
            );
        }

        $medico = trim((string)($context['trabajador_id_label'] ?? $context['medico_nombre'] ?? ''));
        $sede   = trim((string)($context['sede_id_label'] ?? $context['sede_nombre'] ?? ''));

        $parts = [];
        if ($medico !== '') {
            $parts[] = "el *{$medico}*";
        }
        if ($sede !== '') {
            $parts[] = "en *{$sede}*";
        }

        $contextLine = $parts !== []
            ? 'Estabas eligiendo un horario con ' . implode(' ', $parts) . '.'
            : 'Estabas a punto de agendar una cita.';

        return [
            'type' => 'buttons',
            'body' => "⏳ ¡Hola! {$contextLine}\n\n¿Continuamos?",
            'buttons' => [
                ['id' => 'agendar',        'title' => '✅ Sí, continuar'],
                ['id' => 'menu_principal', 'title' => '🔄 Empezar de nuevo'],
            ],
        ];
    }

}
