<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\WhatsappAppointmentReminder;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessageTemplate;
use App\Modules\Shared\Support\SettingsOptionResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WhatsappAppointmentReminderService
{
    private ?SettingsOptionResolver $settingsResolver = null;
    private const LEGACY_REMINDER_TEMPLATE_CODE = 'recordatorio_cita_medica_cive';
    private const META_CONFIRMATION_TEMPLATE_CODE = 'confirmacion_cita_med_v2';

    public function __construct(
        private readonly AutomatedConversationDispatchService $dispatchService = new AutomatedConversationDispatchService(),
        private readonly ConversationOpsService $conversationOpsService = new ConversationOpsService(),
        private readonly WhatsappConfigService $configService = new WhatsappConfigService(),
        private readonly ReminderTemplateVariableCatalog $variableCatalog = new ReminderTemplateVariableCatalog(),
        private readonly array $settingsOverride = [],
    ) {
    }

    /**
     * @param array{for_date?:string,override_wa_number?:string,ignore_window?:bool,first_only?:int} $options
     * @return array{scanned:int,candidates:int,sent:int,failed:int,skipped:int,rows:array<int,array<string,mixed>>,error?:string}
     */
    public function dispatchWindow(string $windowKey, bool $dryRun = false, int $limit = 200, array $options = []): array
    {
        if (!Schema::hasTable('whatsapp_appointment_reminders') || !Schema::hasTable('procedimiento_proyectado')) {
            return [
                'scanned' => 0,
                'candidates' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'Tablas de recordatorios o procedimiento_proyectado no disponibles.',
            ];
        }

        if (!$this->remindersEnabled()) {
            return [
                'scanned' => 0,
                'candidates' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'Los recordatorios automáticos están desactivados por configuración.',
            ];
        }

        $leadMinutes = $this->windowMinutes($windowKey);
        if ($leadMinutes <= 0) {
            return [
                'scanned' => 0,
                'candidates' => 0,
                'sent' => 0,
                'failed' => 0,
                'skipped' => 0,
                'rows' => [],
                'error' => 'Ventana de recordatorio no configurada.',
            ];
        }

        $limit = max(1, $limit);
        $forDate = trim((string) ($options['for_date'] ?? ''));
        $overrideWaNumber = $this->normalizePhone((string) ($options['override_wa_number'] ?? ''));
        $ignoreWindow = (bool) ($options['ignore_window'] ?? false);
        $firstOnly = max(0, (int) ($options['first_only'] ?? 0));
        $effectiveLimit = $firstOnly > 0 ? min($firstOnly, $limit) : $limit;
        $toleranceMinutes = max(5, $this->windowToleranceMinutes());
        $now = now($this->reminderTimezone());
        $windowStart = $now->copy()->addMinutes($leadMinutes - $toleranceMinutes);
        $windowEnd = $now->copy()->addMinutes($leadMinutes + $toleranceMinutes);
        $dateFrom = $forDate !== '' ? $forDate : $windowStart->copy()->startOfDay()->toDateString();
        $dateTo = $forDate !== '' ? $forDate : $windowEnd->copy()->endOfDay()->toDateString();

        $rows = [];
        $scanned = 0;
        $candidates = 0;
        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $preparedCandidates = [];

        $events = DB::table('procedimiento_proyectado')
            ->select([
                'id',
                'form_id',
                'hc_number',
                'procedimiento_proyectado',
                'doctor',
                'sede_departamento',
                'estado_agenda',
                'fecha',
                'hora',
            ])
            ->whereNotNull('form_id')
            ->whereNotNull('hc_number')
            ->whereNotNull('fecha')
            ->whereNotNull('hora')
            ->whereDate('fecha', '>=', $dateFrom)
            ->whereDate('fecha', '<=', $dateTo)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->limit($effectiveLimit * 8)
            ->get();

        foreach ($events as $event) {
            $scanned++;
            $eventAt = $this->eventAt($event->fecha ?? null, $event->hora ?? null);
            if (
                !$eventAt instanceof CarbonInterface
                || (!$ignoreWindow && !$this->eventMatchesDispatchWindow($eventAt, $now, $windowStart, $windowEnd, $windowKey))
            ) {
                $skipped++;
                continue;
            }

            if ($this->shouldSkipByExcludedKeywords(
                (string) ($event->procedimiento_proyectado ?? ''),
                (string) ($event->doctor ?? '')
            )) {
                $skipped++;
                continue;
            }

            $sourceType = $this->classifySourceType((string) ($event->procedimiento_proyectado ?? ''));
            if ($sourceType === null) {
                $skipped++;
                continue;
            }

            if (!$this->estadoAgendaAllowsReminder((string) ($event->estado_agenda ?? ''))) {
                $skipped++;
                continue;
            }

            if ($sourceType === 'imagenes') {
                $groupKey = implode('|', [
                    'imagenes',
                    (string) $event->hc_number,
                    $eventAt->toDateString(),
                ]);

                if (!isset($preparedCandidates[$groupKey])) {
                    $preparedCandidates[$groupKey] = [
                        'form_id' => (int) $event->form_id,
                        'hc_number' => (string) $event->hc_number,
                        'source_type' => $sourceType,
                        'doctor' => trim((string) ($event->doctor ?? '')),
                        'sede_departamento' => trim((string) ($event->sede_departamento ?? '')),
                        'estado_agenda' => trim((string) ($event->estado_agenda ?? '')),
                        'procedimiento_proyectado' => 'Exámenes de imágenes programados',
                        'event_at' => $eventAt,
                        'group_date' => $eventAt->toDateString(),
                        'group_count' => 1,
                    ];
                } else {
                    $preparedCandidates[$groupKey]['group_count'] = (int) $preparedCandidates[$groupKey]['group_count'] + 1;

                    if ($eventAt->lt($preparedCandidates[$groupKey]['event_at'])) {
                        $preparedCandidates[$groupKey]['form_id'] = (int) $event->form_id;
                        $preparedCandidates[$groupKey]['doctor'] = trim((string) ($event->doctor ?? ''));
                        $preparedCandidates[$groupKey]['sede_departamento'] = trim((string) ($event->sede_departamento ?? ''));
                        $preparedCandidates[$groupKey]['estado_agenda'] = trim((string) ($event->estado_agenda ?? ''));
                        $preparedCandidates[$groupKey]['event_at'] = $eventAt;
                    }
                }

                continue;
            }

            $preparedCandidates[] = [
                'form_id' => (int) $event->form_id,
                'hc_number' => (string) $event->hc_number,
                'source_type' => $sourceType,
                'doctor' => trim((string) ($event->doctor ?? '')),
                'sede_departamento' => trim((string) ($event->sede_departamento ?? '')),
                'estado_agenda' => trim((string) ($event->estado_agenda ?? '')),
                'procedimiento_proyectado' => trim((string) ($event->procedimiento_proyectado ?? '')),
                'event_at' => $eventAt,
                'group_date' => $eventAt->toDateString(),
                'group_count' => 1,
            ];
        }

        foreach (array_values($preparedCandidates) as $candidate) {
            if ($candidates >= $effectiveLimit) {
                break;
            }

            $eventAt = $candidate['event_at'];
            $sourceType = (string) $candidate['source_type'];
            $hcNumber = (string) $candidate['hc_number'];
            $formId = (int) $candidate['form_id'];
            $doctor = (string) $candidate['doctor'];
            $sedeDepartamento = (string) $candidate['sede_departamento'];
            $estadoAgenda = (string) ($candidate['estado_agenda'] ?? '');
            $procedimiento = (string) $candidate['procedimiento_proyectado'];
            $groupCount = (int) ($candidate['group_count'] ?? 1);

            $template = $this->resolveTemplateForSource($sourceType);
            if (!$template instanceof WhatsappMessageTemplate) {
                $skipped++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'skipped',
                    'reason' => 'template_missing',
                ];
                continue;
            }

            $recipient = $this->resolveRecipient($hcNumber);
            if ($recipient['wa_number'] === '') {
                $skipped++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'skipped',
                    'reason' => 'recipient_missing',
                ];
                continue;
            }

            $targetWaNumber = $overrideWaNumber !== '' ? $overrideWaNumber : $recipient['wa_number'];
            if ($targetWaNumber === '') {
                $skipped++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'skipped',
                    'reason' => 'target_wa_missing',
                ];
                continue;
            }

            $activeConversation = WhatsappConversation::query()
                ->where('wa_number', $targetWaNumber)
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->first();

            if ($this->hasReachedPatientDailyLimit($hcNumber)) {
                $skipped++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'skipped',
                    'reason' => 'daily_limit',
                ];
                continue;
            }

            if ($overrideWaNumber === '' && $this->hasRecentOutboundToRecipient($targetWaNumber)) {
                $skipped++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'skipped',
                    'reason' => 'recent_outbound',
                ];
                continue;
            }

            $dedupeKey = $this->dedupeKey(
                $sourceType,
                $formId,
                $hcNumber,
                $windowKey,
                $eventAt,
                (string) ($candidate['group_date'] ?? '')
            );

            if (WhatsappAppointmentReminder::query()->where('dedupe_key', $dedupeKey)->exists()) {
                $skipped++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'skipped',
                    'reason' => 'duplicate',
                ];
                continue;
            }

            $payload = [
                'patient_name' => (string) ($recipient['patient_name'] ?? ''),
                'doctor' => $doctor,
                'sede' => $sedeDepartamento,
                'procedimiento' => $procedimiento,
                'event_at' => $eventAt->toIso8601String(),
                'window' => $windowKey,
                'source_type' => $sourceType,
                'group_count' => $groupCount,
            ];

            $candidates++;

            if ($dryRun) {
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'dry_run',
                    'wa_number' => $targetWaNumber,
                    'patient_name' => $recipient['patient_name'],
                ];
                continue;
            }

            $reminder = WhatsappAppointmentReminder::query()->create([
                'conversation_id' => $activeConversation?->id,
                'wa_number' => $targetWaNumber,
                'hc_number' => $hcNumber,
                'form_id' => $formId,
                'source_type' => $sourceType,
                'template_code' => (string) $template->template_code,
                'reminder_window' => $windowKey,
                'dedupe_key' => $dedupeKey,
                'event_at' => $eventAt,
                'status' => 'pending',
                'payload' => $payload,
            ]);

            try {
                $templateVariables = $this->templateVariables(
                    (string) $template->template_code,
                    $sourceType,
                    $recipient,
                    $hcNumber,
                    $targetWaNumber,
                    $formId,
                    $eventAt,
                    $doctor,
                    $procedimiento,
                    $sedeDepartamento,
                    $estadoAgenda,
                    $windowKey,
                    $groupCount,
                    $this->templateVariableCount($template)
                );

                $result = $this->dispatchService->sendTemplate(
                    $targetWaNumber,
                    (int) $template->id,
                    $recipient['patient_name'],
                    $hcNumber,
                    $recipient['patient_name'],
                    $templateVariables
                );

                $reminder->fill([
                    'conversation_id' => (int) data_get($result, 'conversation.id', 0) ?: $activeConversation?->id,
                    'wa_number' => $targetWaNumber,
                    'template_message_id' => (string) data_get($result, 'message.wa_message_id', ''),
                    'status' => 'sent',
                    'sent_at' => now(),
                    'payload' => array_merge($payload, [
                        'template_variables' => $templateVariables,
                        'recipient_override' => $overrideWaNumber !== '' ? $overrideWaNumber : null,
                    ]),
                ])->save();

                $this->recordReminderOperationalEvent($reminder, 'reminder_sent', 'sent');

                $sent++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'sent',
                    'wa_number' => $targetWaNumber,
                    'patient_name' => $recipient['patient_name'],
                ];
            } catch (\Throwable $e) {
                $failureReason = $this->classifyReminderFailure($e->getMessage());

                $reminder->fill([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'payload' => array_merge($payload, [
                        'failure_reason' => $failureReason,
                    ]),
                    'notes' => mb_substr($e->getMessage(), 0, 2000),
                ])->save();

                $this->recordReminderOperationalEvent($reminder, 'reminder_failed', 'dispatch_failed', [
                    'error' => $e->getMessage(),
                ]);

                $failed++;
                $rows[] = [
                    'form_id' => $formId,
                    'hc_number' => $hcNumber,
                    'source_type' => $sourceType,
                    'status' => 'failed',
                    'wa_number' => $targetWaNumber,
                    'failure_reason' => $failureReason,
                    'reason' => $e->getMessage(),
                ];
            }
        }

        return [
            'scanned' => $scanned,
            'candidates' => $candidates,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    private function eventMatchesDispatchWindow(
        CarbonInterface $eventAt,
        CarbonInterface $now,
        CarbonInterface $windowStart,
        CarbonInterface $windowEnd,
        string $windowKey
    ): bool {
        if ($eventAt->betweenIncluded($windowStart, $windowEnd)) {
            return true;
        }

        if ($windowKey !== '24h' || !$this->catchup24hEnabled()) {
            return false;
        }

        if ($eventAt->lte($now) || $eventAt->gte($windowStart)) {
            return false;
        }

        return $now->diffInMinutes($eventAt, false) >= $this->catchup24hMinLeadMinutes();
    }

    private function dedupeKey(
        string $sourceType,
        int $formId,
        string $hcNumber,
        string $windowKey,
        CarbonInterface $eventAt,
        string $groupDate
    ): string {
        if ($sourceType === 'imagenes' && $groupDate !== '') {
            return sha1(implode('|', [
                $sourceType,
                $hcNumber,
                $groupDate,
                $windowKey,
            ]));
        }

        return sha1(implode('|', [
            $sourceType,
            (string) $formId,
            $windowKey,
            $eventAt->format('Y-m-d H:i:s'),
        ]));
    }

    /**
     * @return array{handled:bool,messages_sent:int,handoff_requested:bool,reason:string}|null
     */
    public function handleInboundResponse(WhatsappConversation $conversation, string $text): ?array
    {
        if (!Schema::hasTable('whatsapp_appointment_reminders')) {
            return null;
        }

        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return null;
        }

        $reminder = WhatsappAppointmentReminder::query()
            ->where(function ($query) use ($conversation): void {
                $query->where('conversation_id', $conversation->id)
                    ->orWhere('wa_number', (string) $conversation->wa_number);
            })
            ->where('status', 'sent')
            ->whereNull('responded_at')
            ->orderByDesc('sent_at')
            ->orderByDesc('id')
            ->first();

        if (!$reminder instanceof WhatsappAppointmentReminder) {
            return null;
        }

        if ($this->isReminderConfirmReply($normalized)) {
            $reminder->fill([
                'status' => 'responded',
                'response_value' => 'confirmar',
                'responded_at' => now(),
            ])->save();

            $this->recordReminderOperationalEvent($reminder, 'reminder_confirmed', 'patient_confirmed');

            $this->dispatchService->sendSystemText(
                $conversation,
                '✅ Gracias por confirmar tu asistencia. Si necesitas algo más, escribe AYUDA o MENU.'
            );

            return [
                'handled' => true,
                'messages_sent' => 1,
                'handoff_requested' => false,
                'reason' => 'reminder_confirmed',
            ];
        }

        if ($this->isReminderAgentReply($normalized)) {
            $reminder->fill([
                'status' => 'responded',
                'response_value' => 'agente',
                'responded_at' => now(),
            ])->save();

            $this->recordReminderOperationalEvent($reminder, 'reminder_agent_requested', 'patient_requested_agent');

            $payload = is_array($reminder->payload) ? $reminder->payload : [];
            $note = sprintf(
                'Recordatorio WhatsApp. El paciente pidió comunicarse con un agente para su %s del %s (%s, %s).',
                (string) ($reminder->source_type === 'imagenes' ? 'estudio' : 'cita'),
                $this->formatEventForNote($reminder->event_at),
                trim((string) ($payload['procedimiento'] ?? 'sin procedimiento')),
                trim((string) ($payload['sede'] ?? 'sin sede'))
            );

            $this->conversationOpsService->enqueueConversationToRole(
                (int) $conversation->id,
                max(1, $this->agentRoleId()),
                0,
                true,
                $note
            );

            $this->dispatchService->sendSystemText(
                $conversation,
                '🆘 Un agente revisará tu solicitud y te contactará por este mismo canal.'
            );

            return [
                'handled' => true,
                'messages_sent' => 1,
                'handoff_requested' => true,
                'reason' => 'reminder_agent_requested',
            ];
        }

        return null;
    }

    /**
     * @param array<string,mixed> $extraPayload
     */
    private function recordReminderOperationalEvent(
        WhatsappAppointmentReminder $reminder,
        string $eventType,
        string $reason,
        array $extraPayload = [],
    ): void {
        try {
            if (!Schema::hasTable('whatsapp_operational_events') || (int) ($reminder->conversation_id ?? 0) <= 0) {
                return;
            }

            app(WhatsappOperationalEventService::class)->record([
                'conversation_id' => (int) $reminder->conversation_id,
                'reminder_id' => (int) $reminder->id,
                'event_type' => $eventType,
                'event_at' => match ($eventType) {
                    'reminder_sent' => $reminder->sent_at ?? now(),
                    'reminder_failed' => $reminder->failed_at ?? now(),
                    default => $reminder->responded_at ?? now(),
                },
                'actor_type' => $eventType === 'reminder_confirmed' || $eventType === 'reminder_agent_requested'
                    ? 'patient'
                    : 'system',
                'producer' => 'whatsapp_appointment_reminder_service',
                'wa_number' => $reminder->wa_number,
                'patient_hc_number' => $reminder->hc_number,
                'reason' => $reason,
                'payload' => array_merge([
                    'source_type' => $reminder->source_type,
                    'reminder_window' => $reminder->reminder_window,
                    'template_code' => $reminder->template_code,
                    'status' => $reminder->status,
                ], $extraPayload),
                'idempotency_key' => "{$eventType}:reminder:{$reminder->id}",
            ]);
        } catch (\Throwable) {
            // Operational events are observability; reminder delivery must remain the source behavior.
        }
    }

    private function classifySourceType(string $procedimiento): ?string
    {
        $normalized = $this->normalizeText($procedimiento);
        if ($normalized === '') {
            return null;
        }

        foreach ($this->imagingKeywords() as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'imagenes';
            }
        }

        foreach ($this->serviceKeywords() as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return 'servicios_oftalmologicos_generales';
            }
        }

        return null;
    }

    private function estadoAgendaAllowsReminder(string $estado): bool
    {
        $normalized = $this->normalizeText($estado);
        if ($normalized === '') {
            return true;
        }

        foreach (['cancel', 'anulad', 'atendid', 'cerrad', 'no show', 'no_show'] as $blocked) {
            if (str_contains($normalized, $blocked)) {
                return false;
            }
        }

        return true;
    }

    private function resolveTemplateForSource(string $sourceType): ?WhatsappMessageTemplate
    {
        $configuredCode = $sourceType === 'imagenes'
            ? $this->imageTemplateCode()
            : $this->serviceTemplateCode();
        $code = $this->effectiveTemplateCode($configuredCode);

        if ($code === '') {
            return null;
        }

        return WhatsappMessageTemplate::query()
            ->with('whatsapp_template_revision')
            ->where('template_code', $code)
            ->whereRaw('LOWER(status) in (?, ?)', ['approved', 'active'])
            ->first();
    }

    private function effectiveTemplateCode(string $code): string
    {
        $code = trim($code);
        if ($code === self::LEGACY_REMINDER_TEMPLATE_CODE) {
            return self::META_CONFIRMATION_TEMPLATE_CODE;
        }

        return $code;
    }

    /**
     * @return array{wa_number:string,patient_name:string,first_name:string,last_name:string,phone:string,email:string,affiliation:string,gender:string,birth_date:string}
     */
    private function resolveRecipient(string $hcNumber): array
    {
        $patient = null;
        if (Schema::hasTable('patient_data')) {
            $columns = array_values(array_filter([
                'fname',
                'mname',
                'lname',
                'lname2',
                'celular',
                Schema::hasColumn('patient_data', 'email') ? 'email' : null,
                Schema::hasColumn('patient_data', 'afiliacion') ? 'afiliacion' : null,
                Schema::hasColumn('patient_data', 'sexo') ? 'sexo' : null,
                Schema::hasColumn('patient_data', 'fecha_nacimiento') ? 'fecha_nacimiento' : null,
            ]));

            $patient = DB::table('patient_data')
                ->select($columns)
                ->where('hc_number', $hcNumber)
                ->orderByDesc('id')
                ->first();
        }

        $patientName = trim(implode(' ', array_filter([
            trim((string) ($patient->fname ?? '')),
            trim((string) ($patient->mname ?? '')),
            trim((string) ($patient->lname ?? '')),
            trim((string) ($patient->lname2 ?? '')),
        ])));
        $firstName = trim((string) ($patient->fname ?? ''));
        $lastName = trim(implode(' ', array_filter([
            trim((string) ($patient->lname ?? '')),
            trim((string) ($patient->lname2 ?? '')),
        ])));
        $clinicalPhone = $this->normalizePhone((string) ($patient->celular ?? ''));

        $conversation = WhatsappConversation::query()
            ->where('patient_hc_number', $hcNumber)
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->first();

        if ($conversation instanceof WhatsappConversation) {
            $waNumber = $this->normalizePhone((string) $conversation->wa_number);
            if ($waNumber !== '') {
                $name = trim((string) ($conversation->patient_full_name ?: $conversation->display_name ?: $patientName));

                return [
                    'wa_number' => $waNumber,
                    'patient_name' => $name !== '' ? $name : $waNumber,
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'phone' => $clinicalPhone,
                    'email' => trim((string) ($patient->email ?? '')),
                    'affiliation' => trim((string) ($patient->afiliacion ?? '')),
                    'gender' => trim((string) ($patient->sexo ?? '')),
                    'birth_date' => trim((string) ($patient->fecha_nacimiento ?? '')),
                ];
            }
        }

        return [
            'wa_number' => $clinicalPhone,
            'patient_name' => $patientName !== '' ? $patientName : $clinicalPhone,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => $clinicalPhone,
            'email' => trim((string) ($patient->email ?? '')),
            'affiliation' => trim((string) ($patient->afiliacion ?? '')),
            'gender' => trim((string) ($patient->sexo ?? '')),
            'birth_date' => trim((string) ($patient->fecha_nacimiento ?? '')),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    private function templateVariables(
        string $templateCode,
        string $sourceType,
        array $recipient,
        string $hcNumber,
        string $waNumber,
        int $formId,
        CarbonInterface $eventAt,
        string $doctor,
        string $procedimiento,
        string $sede,
        string $estadoAgenda,
        string $windowKey,
        int $groupCount,
        int $expectedCount
    ): array {
        $context = $this->templateVariableContext(
            $recipient,
            $hcNumber,
            $waNumber,
            $formId,
            $eventAt,
            $doctor,
            $procedimiento,
            $sede,
            $estadoAgenda,
            $sourceType,
            $windowKey,
            $groupCount
        );

        $mappedVariables = $this->variableCatalog->resolveVariables(
            $sourceType,
            $templateCode,
            $expectedCount,
            $this->settings(),
            $context
        );

        $patientName = (string) ($recipient['patient_name'] ?? '');

        if ($mappedVariables !== []) {
            $result = $mappedVariables;
        } elseif ($templateCode === self::META_CONFIRMATION_TEMPLATE_CODE) {
            $result = [
                $patientName !== '' ? $patientName : 'Paciente',
                $eventAt->locale('es')->translatedFormat('d/m/Y'),
                $eventAt->format('H:i'),
                trim($doctor) !== '' ? trim($doctor) : 'Por confirmar',
            ];
        } else {
            $result = [
                $patientName !== '' ? $patientName : 'Paciente',
                $sede !== '' ? $sede : 'Sede por confirmar',
                $eventAt->locale('es')->translatedFormat('d/m/Y'),
                $eventAt->format('H:i'),
                trim($doctor) !== '' ? trim($doctor) : 'Por confirmar',
                $this->sedeAddress($sede),
                $this->cleanProcedure($procedimiento),
            ];
        }

        $site = $context['site'] ?? [];
        $result['_location_lat'] = (string) ($site['latitude'] ?? '');
        $result['_location_lng'] = (string) ($site['longitude'] ?? '');
        $result['_location_name'] = (string) ($site['name'] ?? '');
        $result['_location_address'] = (string) ($site['address'] ?? '');

        return $result;
    }

    /**
     * @param array<string,string> $recipient
     * @return array<string,mixed>
     */
    private function templateVariableContext(
        array $recipient,
        string $hcNumber,
        string $waNumber,
        int $formId,
        CarbonInterface $eventAt,
        string $doctor,
        string $procedimiento,
        string $sede,
        string $estadoAgenda,
        string $sourceType,
        string $windowKey,
        int $groupCount
    ): array {
        $site = $this->siteContext($sede);
        $patientName = trim((string) ($recipient['patient_name'] ?? ''));
        $cleanProcedure = $this->cleanProcedure($procedimiento);

        return [
            'patient' => [
                'name' => $patientName !== '' ? $patientName : 'Paciente',
                'first_name' => trim((string) ($recipient['first_name'] ?? '')),
                'last_name' => trim((string) ($recipient['last_name'] ?? '')),
                'hc_number' => $hcNumber,
                'phone' => trim((string) ($recipient['phone'] ?? '')),
                'wa_number' => $waNumber,
                'email' => trim((string) ($recipient['email'] ?? '')),
                'affiliation' => trim((string) ($recipient['affiliation'] ?? '')),
                'gender' => trim((string) ($recipient['gender'] ?? '')),
                'birth_date' => $this->formatDateValue((string) ($recipient['birth_date'] ?? '')),
            ],
            'appointment' => [
                'form_id' => (string) $formId,
                'date' => $eventAt->locale('es')->translatedFormat('d/m/Y'),
                'date_iso' => $eventAt->toDateString(),
                'time' => $eventAt->format('H:i'),
                'datetime' => $eventAt->locale('es')->translatedFormat('d/m/Y H:i'),
                'doctor' => trim($doctor) !== '' ? trim($doctor) : 'Por confirmar',
                'procedure' => $cleanProcedure,
                'procedure_short' => $this->shortProcedure($procedimiento),
                'procedure_full' => trim($procedimiento) !== '' ? trim($procedimiento) : 'Atención programada',
                'service_type' => $sourceType === 'imagenes' ? 'Imágenes' : 'Servicios oftalmológicos generales',
                'status' => trim($estadoAgenda),
                'source_type' => $sourceType,
            ],
            'site' => $site,
            'clinic' => [
                'name' => $this->settingString('companyname', 'Clínica Internacional de la Visión del Ecuador'),
                'short_name' => 'CIVE',
                'website' => $this->settingString('companywebsite', 'https://cive.ec/'),
                'phone' => $this->settingString('companyphone', '043710160'),
            ],
            'reminder' => [
                'window' => $windowKey,
                'type' => $sourceType === 'imagenes' ? 'Imágenes' : 'Servicios oftalmológicos generales',
                'group_count' => (string) $groupCount,
            ],
            'fallback' => [
                'empty' => 'Por confirmar',
            ],
        ];
    }

    /**
     * @return array{name:string,address:string,maps_url:string,phone:string,contact_center:string,latitude:string,longitude:string}
     */
    private function siteContext(string $sede): array
    {
        $normalized = $this->normalizeText($sede);
        $contactCenter = $this->settingString('companyphone', '043710160');

        if (str_contains($normalized, 'villa')) {
            return [
                'name' => 'Villa Club',
                'address' => $this->settingString(
                    'whatsapp_reminder_site_address_villa_club',
                    'Parroquia satélite La Aurora de Daule, km 12 Av. León Febres-Cordero. Junto a la Piazza Villa Club.'
                ),
                'maps_url' => $this->settingString(
                    'whatsapp_reminder_site_maps_villa_club',
                    'https://maps.app.goo.gl/i1ryHLC6JUzkefHa6'
                ),
                'latitude' => $this->settingString('whatsapp_reminder_site_lat_villa_club', ''),
                'longitude' => $this->settingString('whatsapp_reminder_site_lng_villa_club', ''),
                'phone' => $contactCenter,
                'contact_center' => $contactCenter,
            ];
        }

        if (str_contains($normalized, 'ceibos')) {
            return [
                'name' => 'Ceibos',
                'address' => $this->settingString(
                    'whatsapp_reminder_site_address_ceibos',
                    'C.C. La Vista de San Eduardo #200, km 6.5 Av. del Bombero.'
                ),
                'maps_url' => $this->settingString(
                    'whatsapp_reminder_site_maps_ceibos',
                    'Comunícate con nuestro equipo para confirmar la ubicación.'
                ),
                'latitude' => $this->settingString('whatsapp_reminder_site_lat_ceibos', ''),
                'longitude' => $this->settingString('whatsapp_reminder_site_lng_ceibos', ''),
                'phone' => $contactCenter,
                'contact_center' => $contactCenter,
            ];
        }

        if (str_contains($normalized, 'matriz')) {
            return [
                'name' => 'Matriz',
                'address' => $this->settingString(
                    'whatsapp_reminder_site_address_matriz',
                    'Comunícate con nuestro equipo para confirmar la ubicación.'
                ),
                'maps_url' => $this->settingString(
                    'whatsapp_reminder_site_maps_matriz',
                    'Comunícate con nuestro equipo para confirmar la ubicación.'
                ),
                'latitude' => $this->settingString('whatsapp_reminder_site_lat_matriz', ''),
                'longitude' => $this->settingString('whatsapp_reminder_site_lng_matriz', ''),
                'phone' => $contactCenter,
                'contact_center' => $contactCenter,
            ];
        }

        return [
            'name' => trim($sede) !== '' ? trim($sede) : 'Sede por confirmar',
            'address' => 'Comunícate con nuestro equipo para confirmar la ubicación.',
            'maps_url' => 'Comunícate con nuestro equipo para confirmar la ubicación.',
            'latitude' => '',
            'longitude' => '',
            'phone' => $contactCenter,
            'contact_center' => $contactCenter,
        ];
    }

    private function shortProcedure(string $procedimiento): string
    {
        return mb_substr($this->cleanProcedure($procedimiento), 0, 120, 'UTF-8');
    }

    private function cleanProcedure(string $procedimiento): string
    {
        $value = trim($procedimiento);
        if ($value === '') {
            return 'Atención programada';
        }

        $parts = array_values(array_filter(array_map('trim', preg_split('/\s+-\s+/', $value) ?: [])));
        while ($parts !== []) {
            $head = (string) $parts[0];
            $normalized = $this->normalizeText($head);
            if (
                in_array($normalized, ['servicios oftalmologicos generales', 'servicio oftalmologico general', 'imagenes'], true)
                || preg_match('/^[A-Z]{2,}(?:-[A-Z0-9]+)+$/i', $head) === 1
                || preg_match('/^\d+$/', $head) === 1
            ) {
                array_shift($parts);
                continue;
            }

            break;
        }

        $clean = trim(implode(' - ', $parts));
        if ($clean === '') {
            $clean = (string) end($parts);
        }

        return mb_substr($clean !== '' ? $clean : $value, 0, 180, 'UTF-8');
    }

    private function formatDateValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        try {
            return Carbon::parse($value, $this->reminderTimezone())->format('d/m/Y');
        } catch (\Throwable) {
            return $value;
        }
    }

    private function templateVariableCount(WhatsappMessageTemplate $template): int
    {
        return $this->variableCatalog->countTemplateVariables(
            (string) ($template->whatsapp_template_revision?->body_text ?? '')
        );
    }

    private function sedeAddress(string $sede): string
    {
        return (string) ($this->siteContext($sede)['address'] ?? 'Revisa la ubicación enviada por nuestro equipo o comunícate con un agente.');
    }

    private function eventAt(mixed $fecha, mixed $hora): ?CarbonInterface
    {
        $date = trim((string) $fecha);
        $time = trim((string) $hora);
        if ($date === '' || $date === '0000-00-00') {
            return null;
        }

        $time = $this->normalizeTimeValue($time);
        if ($time === null) {
            return null;
        }

        try {
            return Carbon::parse($date . ' ' . $time, $this->reminderTimezone());
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeTimeValue(string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000-00-00')) {
            return null;
        }

        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        if (preg_match('/^\d{2}:\d{2}$/', $value) === 1) {
            return $value . ':00';
        }

        try {
            return Carbon::parse($value)->format('H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    private function isReminderConfirmReply(string $normalized): bool
    {
        return in_array($normalized, [
            'confirmar',
            'confirmo',
            'confirmo asistencia',
            'si confirmo',
            'si',
        ], true);
    }

    private function isReminderAgentReply(string $normalized): bool
    {
        return in_array($normalized, [
            'comunicarse con un agente',
            'comunicarse_con_un_agente',
            'comunicarse con agente',
            'comunicarse_con_agente',
            'agente',
            'necesito reagendar',
            'reagendar',
        ], true);
    }

    private function formatEventForNote(mixed $value): string
    {
        if (!$value instanceof CarbonInterface) {
            return 'fecha no disponible';
        }

        return $value->format('Y-m-d H:i');
    }

    private function normalizePhone(string $value): string
    {
        $digits = preg_replace('/\D+/', '', trim($value));
        if ($digits === '') {
            return '';
        }

        $defaultCountryCode = preg_replace('/\D+/', '', (string) ($this->configService->get()['default_country_code'] ?? ''));
        if ($defaultCountryCode !== '' && !str_starts_with($digits, $defaultCountryCode) && str_starts_with($digits, '0')) {
            return $defaultCountryCode . ltrim($digits, '0');
        }

        return $digits;
    }

    private function remindersEnabled(): bool
    {
        return $this->settingBool(
            'whatsapp_reminders_enabled',
            (bool) config('whatsapp.migration.reminders.enabled', false)
        );
    }

    private function windowMinutes(string $windowKey): int
    {
        $defaults = [
            '24h' => (int) config('whatsapp.migration.reminders.windows.24h', 1440),
            '2h' => (int) config('whatsapp.migration.reminders.windows.2h', 120),
        ];

        $enabledKey = $windowKey === '2h'
            ? 'whatsapp_reminder_window_2h_enabled'
            : 'whatsapp_reminder_window_24h_enabled';

        if (!$this->settingBool($enabledKey, true)) {
            return 0;
        }

        $minutesKey = $windowKey === '2h'
            ? 'whatsapp_reminder_window_2h_minutes'
            : 'whatsapp_reminder_window_24h_minutes';

        return max(0, $this->settingInt($minutesKey, (int) ($defaults[$windowKey] ?? 0)));
    }

    private function windowToleranceMinutes(): int
    {
        return max(5, $this->settingInt(
            'whatsapp_reminder_window_tolerance_minutes',
            (int) config('whatsapp.migration.reminders.window_tolerance_minutes', 15)
        ));
    }

    private function catchup24hEnabled(): bool
    {
        return $this->settingBool('whatsapp_reminder_24h_catchup_enabled', true);
    }

    private function catchup24hMinLeadMinutes(): int
    {
        $default = max(180, $this->windowMinutes('2h') + $this->windowToleranceMinutes());

        return max(30, $this->settingInt('whatsapp_reminder_24h_catchup_min_lead_minutes', $default));
    }

    private function serviceTemplateCode(): string
    {
        return $this->settingString(
            'whatsapp_reminder_service_template_code',
            (string) config('whatsapp.migration.reminders.consultation_template_code', 'recordatorio_cita_medica_cive')
        );
    }

    private function imageTemplateCode(): string
    {
        return $this->settingString(
            'whatsapp_reminder_imaging_template_code',
            (string) config('whatsapp.migration.reminders.image_template_code', 'recordatorio_cita_medica_cive')
        );
    }

    /**
     * @return array<int,string>
     */
    private function serviceKeywords(): array
    {
        $fallback = [
            'consulta',
            'servicio oftalmologico',
            'servicios oftalmologicos',
            'control',
            'oftalmologica',
            'oftalmologico',
        ];

        return $this->settingList('whatsapp_reminder_service_keywords', $fallback);
    }

    /**
     * @return array<int,string>
     */
    private function imagingKeywords(): array
    {
        $fallback = [
            'imagenes',
            'imagen',
            'oct',
            'topografia',
            'paquimetria',
            'biometria',
            'retinografia',
            'angiografia',
            'campo visual',
            'ultrasonido',
            'ecografia',
            'examen',
        ];

        return $this->settingList('whatsapp_reminder_imaging_keywords', $fallback);
    }

    /**
     * @return array<int,string>
     */
    private function excludedKeywords(): array
    {
        return $this->settingList('whatsapp_reminder_excluded_keywords', [
            'optometria',
            'optometrista',
        ]);
    }

    private function shouldSkipByExcludedKeywords(string $procedimiento, string $doctor): bool
    {
        $haystack = $this->normalizeText($procedimiento . ' ' . $doctor);
        if ($haystack === '') {
            return false;
        }

        foreach ($this->excludedKeywords() as $keyword) {
            if ($keyword !== '' && str_contains($haystack, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function agentRoleId(): int
    {
        return max(1, $this->settingInt(
            'whatsapp_reminder_agent_role_id',
            (int) config('whatsapp.migration.reminders.agent_role_id', 4)
        ));
    }

    private function hasRecentOutboundToRecipient(string $waNumber): bool
    {
        $hours = max(0, $this->settingInt('whatsapp_reminder_skip_if_recent_outbound_hours', 12));
        if ($hours <= 0 || !Schema::hasTable('whatsapp_messages') || !Schema::hasTable('whatsapp_conversations')) {
            return false;
        }

        return DB::table('whatsapp_messages as wm')
            ->join('whatsapp_conversations as wc', 'wc.id', '=', 'wm.conversation_id')
            ->where('wc.wa_number', $waNumber)
            ->where('wm.direction', 'outbound')
            ->where('wm.created_at', '>=', now($this->reminderTimezone())->subHours($hours))
            ->exists();
    }

    private function classifyReminderFailure(string $message): string
    {
        $normalized = $this->normalizeText($message);

        if (in_array($normalized, ['reminder_location_header_missing_coordinates', 'template_location_header_missing_coordinates'], true)) {
            return 'location_header_missing_coordinates';
        }

        if (
            str_contains($normalized, 'template_header_location_mismatch')
            || (str_contains($normalized, 'expected') && str_contains($normalized, 'location') && str_contains($normalized, 'unknown'))
            || str_contains($normalized, '132012')
        ) {
            return 'template_header_location_mismatch';
        }

        if (str_contains($normalized, 'whatsapp_messages') && str_contains($normalized, 'doesn') && str_contains($normalized, 'exist')) {
            return 'whatsapp_messages_table_missing';
        }

        if (str_contains($normalized, 'whatsapp cloud api error')) {
            return 'cloud_api_error';
        }

        return 'unexpected_error';
    }

    private function hasReachedPatientDailyLimit(string $hcNumber): bool
    {
        $limit = max(0, $this->settingInt('whatsapp_reminder_max_per_patient_per_day', 2));
        if ($limit <= 0) {
            return false;
        }

        return WhatsappAppointmentReminder::query()
            ->where('hc_number', $hcNumber)
            ->whereDate('created_at', now($this->reminderTimezone())->toDateString())
            ->count() >= $limit;
    }

    private function settingBool(string $key, bool $default): bool
    {
        $value = $this->settings()[$key] ?? null;
        if ($value === null || trim((string) $value) === '') {
            return $default;
        }

        return in_array(mb_strtolower(trim((string) $value), 'UTF-8'), ['1', 'true', 'yes', 'on', 'si'], true);
    }

    private function settingInt(string $key, int $default): int
    {
        $value = $this->settings()[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '' || !is_numeric((string) $value)) {
            return $default;
        }

        return (int) $value;
    }

    private function settingString(string $key, string $default): string
    {
        $value = $this->settings()[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            return $default;
        }

        return trim((string) $value);
    }

    /**
     * @param array<int,string> $default
     * @return array<int,string>
     */
    private function settingList(string $key, array $default): array
    {
        $value = $this->settings()[$key] ?? null;
        if (!is_scalar($value) || trim((string) $value) === '') {
            return $default;
        }

        $items = preg_split('/[\r\n,;]+/', (string) $value) ?: [];
        $items = array_values(array_filter(array_map(function (string $item): string {
            return $this->normalizeText($item);
        }, $items)));

        return $items !== [] ? $items : $default;
    }

    /**
     * @return array<string,string>
     */
    private function settings(): array
    {
        $settings = $this->settingsResolver()->getOptions([
            'whatsapp_reminders_enabled',
            'whatsapp_reminder_service_template_code',
            'whatsapp_reminder_imaging_template_code',
            ReminderTemplateVariableCatalog::SERVICE_MAPPING_KEY,
            ReminderTemplateVariableCatalog::IMAGING_MAPPING_KEY,
            'whatsapp_reminder_window_24h_enabled',
            'whatsapp_reminder_window_2h_enabled',
            'whatsapp_reminder_window_24h_minutes',
            'whatsapp_reminder_window_2h_minutes',
            'whatsapp_reminder_window_tolerance_minutes',
            'whatsapp_reminder_24h_catchup_enabled',
            'whatsapp_reminder_24h_catchup_min_lead_minutes',
            'whatsapp_reminder_max_per_patient_per_day',
            'whatsapp_reminder_skip_if_recent_outbound_hours',
            'whatsapp_reminder_agent_role_id',
            'whatsapp_reminder_service_keywords',
            'whatsapp_reminder_imaging_keywords',
            'whatsapp_reminder_excluded_keywords',
            'whatsapp_reminder_site_maps_villa_club',
            'whatsapp_reminder_site_maps_ceibos',
            'whatsapp_reminder_site_maps_matriz',
            'whatsapp_reminder_site_address_villa_club',
            'whatsapp_reminder_site_address_ceibos',
            'whatsapp_reminder_site_address_matriz',
            'whatsapp_reminder_site_lat_villa_club',
            'whatsapp_reminder_site_lng_villa_club',
            'whatsapp_reminder_site_lat_ceibos',
            'whatsapp_reminder_site_lng_ceibos',
            'whatsapp_reminder_site_lat_matriz',
            'whatsapp_reminder_site_lng_matriz',
            'whatsapp_reminder_timezone',
            'companyname',
            'companywebsite',
            'companyphone',
        ]);

        return $this->settingsOverride !== [] ? array_merge($settings, $this->settingsOverride) : $settings;
    }

    private function reminderTimezone(): string
    {
        return $this->settingString(
            'whatsapp_reminder_timezone',
            (string) config('whatsapp.migration.reminders.timezone', 'America/Guayaquil')
        );
    }

    private function settingsResolver(): SettingsOptionResolver
    {
        if (!$this->settingsResolver instanceof SettingsOptionResolver) {
            $this->settingsResolver = new SettingsOptionResolver();
        }

        return $this->settingsResolver;
    }

    private function normalizeText(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        $value = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'u', 'n'],
            $value
        );
        $value = preg_replace('/\s+/', ' ', $value) ?: '';

        return trim($value);
    }
}
