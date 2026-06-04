<?php

namespace App\Modules\Whatsapp\Services;

use App\Modules\Shared\Support\SettingsOptionResolver;
use App\Modules\CRM\Services\CrmContactResolverService;
use App\Modules\CRM\Services\CrmOpportunityService;
use App\Models\WhatsappAutoresponderSession;
use App\Models\WhatsappConversation;
use App\Models\WhatsappConversationAttribution;
use App\Models\WhatsappHandoff;
use App\Models\WhatsappMessage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class FlowRuntimeExecutionService
{
    private ?SettingsOptionResolver $settingsResolver = null;
    private int $sessionVersion = 0;

    /** @var array<string, mixed> */
    private array $currentFlowSettings = [];

    public function __construct(
        private readonly FlowmakerService           $flowmakerService = new FlowmakerService(),
        private readonly FlowmakerSandboxService    $sandboxService = new FlowmakerSandboxService(),
        private readonly WhatsappConfigService      $configService = new WhatsappConfigService(),
        private readonly CloudApiTransportService   $transport = new CloudApiTransportService(),
        private readonly FlowSigcenterAgendaService $sigcenterAgendaService = new FlowSigcenterAgendaService(),
        private readonly FlowAiAgentPreviewService  $aiAgentPreviewService = new FlowAiAgentPreviewService(),
        private readonly WhatsappAppointmentReminderService $appointmentReminderService = new WhatsappAppointmentReminderService(),
    )
    {
    }

    /**
     * @param array<string, mixed> $messagePayload
     * @return array{executed:bool,matched:bool,scenario_id:?string,messages_sent:int,handoff_requested:bool,reason:?string}
     */
    public function executeInbound(WhatsappConversation $conversation, WhatsappMessage $inboundMessage, array $messagePayload): array
    {
        if (!(bool)config('whatsapp.migration.automation.enabled', false)) {
            return $this->result(false, false, null, 0, false, 'automation_disabled');
        }

        $text = trim((string)($inboundMessage->body ?? ''));
        $type = trim((string)($inboundMessage->message_type ?? 'text'));
        $isMediaMessage = $text === '' && in_array($type, ['audio', 'image', 'video', 'sticker', 'document', 'location'], true);

        if ($text === '' && !$isMediaMessage) {
            return $this->result(false, false, null, 0, false, 'empty_text');
        }

        $waNumber = (string)($conversation->wa_number ?? '');
        $flow = $this->sandboxService->getFlowPayload($waNumber)
            ?? $this->flowmakerService->getActiveFlowPayload();
        $this->currentFlowSettings = is_array($flow['settings'] ?? null) ? $flow['settings'] : [];

        if ((bool)($conversation->assigned_user_id ?? false)) {
            if (!$this->humanQueueIsOpen()) {
                $this->releaseConversationToBot($conversation);
                $offHoursMsg = trim((string) ($this->currentFlowSettings['off_hours_agent_release_message'] ?? ''));
                if ($offHoursMsg === '') {
                    $offHoursMsg = 'Nuestros agentes ya terminaron por hoy 🌙 Pero puedo ayudarte ahora mismo.';
                }
                $this->sendFlowMessage($conversation, [
                    'type'    => 'buttons',
                    'body'    => $offHoursMsg,
                    'buttons' => [
                        ['id' => 'menu', 'title' => '📋 Ver opciones'],
                    ],
                ], []);
            } else {
                return $this->result(false, false, null, 0, false, 'conversation_assigned');
            }
        }

        if ((bool) ($conversation->needs_human ?? false)) {
            if (!$this->humanQueueIsOpen() || $this->isBotReactivationCommand($text)) {
                $this->releaseConversationToBot($conversation);
            } else {
                return $this->result(false, false, null, 0, false, 'conversation_needs_human');
            }
        }

        if ((bool)($conversation->assigned_user_id ?? false)) {
            return $this->result(false, false, null, 0, false, 'conversation_assigned');
        }

        if ((bool) ($conversation->needs_human ?? false)) {
            return $this->result(false, false, null, 0, false, 'conversation_needs_human');
        }

        if ($isMediaMessage) {
            $mediaReplies = [
                'audio'    => "Recibí un audio, pero solo proceso texto 📝\n¿Necesitas ayuda? Escribe *MENU*.",
                'image'    => "Recibí una imagen, pero solo proceso texto 📝\n¿Necesitas ayuda? Escribe *MENU*.",
                'video'    => "Recibí un video, pero solo proceso texto 📝\n¿Necesitas ayuda? Escribe *MENU*.",
                'sticker'  => "¡Gracias! 😄 Si necesitas ayuda, escribe *MENU*.",
                'document' => "Recibí un documento, pero solo proceso texto.\n¿Necesitas ayuda? Escribe *MENU*.",
                'location' => "Recibí tu ubicación, pero no la proceso aún.\n¿Necesitas ayuda? Escribe *MENU*.",
            ];
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => $mediaReplies[$type] ?? "Solo proceso mensajes de texto.\nEscribe *MENU* para ver las opciones.",
            ], []);
            return $this->result(true, false, 'non_text_reply', 1, false, 'non_text_message');
        }

        $session = WhatsappAutoresponderSession::query()
            ->where('conversation_id', $conversation->id)
            ->first();

        $this->sessionVersion = (int) ($session?->session_version ?? 0);

        $context = is_array($session?->context) ? $session->context : [];
        if (!isset($context['state'])) {
            $context['state'] = 'inicio';
        }
        $context = $this->clearAbandonmentMonitorOnInbound($context);
        $context = $this->seedPatientContextFromConversation($context, $conversation);
        $context = $this->captureAwaitingInput($context, $text, $inboundMessage);

        $currentState = (string) ($context['state'] ?? '');
        $reminderResult = str_starts_with($currentState, 'agenda_')
            ? null
            : $this->appointmentReminderService->handleInboundResponse($conversation, $text);
        if (is_array($reminderResult) && !empty($reminderResult['handled'])) {
            $this->saveSession(
                $conversation,
                (string) $conversation->wa_number,
                'appointment_reminder_response',
                null,
                null,
                array_merge($context, ['state' => 'menu_principal']),
                $messagePayload,
            );

            return $this->result(
                true,
                true,
                'appointment_reminder_response',
                (int) ($reminderResult['messages_sent'] ?? 0),
                (bool) ($reminderResult['handoff_requested'] ?? false),
                (string) ($reminderResult['reason'] ?? null)
            );
        }

        $facts = $this->buildFacts($conversation, $inboundMessage, $session, $context, $text, $messagePayload);
        if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion' || $this->isExplicitCancelConfirmationReply($text)) {
            $activeBooking = $this->activeSigcenterBooking($conversation, $context);
            if ($activeBooking !== null && $this->bookingCancellationConfirmed($text)) {
                $preview = $this->sigcenterAgendaService->execute([
                    'type' => 'sigcenter_agenda',
                    'operation' => 'cancel_appointment',
                    'company_id' => 113,
                    'agenda_id' => $activeBooking->sigcenter_agenda_id ?? null,
                    'motivo' => 'Solicitado por paciente vía WhatsApp',
                ], $context, [
                    'wa_number' => $conversation->wa_number,
                    'text' => $text,
                    'conversation_id' => $conversation->id,
                ], true);

                $this->markBookingCancellationResult($activeBooking, $preview, $conversation, $inboundMessage);
                $this->sendFlowMessage(
                    $conversation,
                    !empty($preview['ok']) ? $this->bookingCancelledMessage($activeBooking) : $this->bookingCancellationFailedMessage((string)($preview['error'] ?? '')),
                    $context
                );

                $this->saveSession(
                    $conversation,
                    (string) $conversation->wa_number,
                    'booking_cancel_confirmation',
                    null,
                    null,
                    array_merge($context, ['state' => 'menu_principal']),
                    $messagePayload,
                );

                return $this->result(true, true, 'booking_cancel_confirmation', 1, false, null);
            }

            if ($this->bookingCancellationRejected($text)) {
                $this->sendFlowMessage($conversation, [
                    'type' => 'text',
                    'body' => 'No se canceló tu cita. Si necesitas otra gestión, escribe AYUDA.',
                ], $context);

                return $this->result(true, true, 'booking_cancel_rejected', 1, false, null);
            }

            // NUEVO: hermético — texto no reconocido mientras espera cancelación
            if (($context['state'] ?? null) === 'agenda_confirmar_cancelacion' && $activeBooking !== null) {
                $this->sendFlowMessage($conversation, [
                    'type' => 'text',
                    'body' => "No entendí tu respuesta 🤔\n\n"
                        . "¿Confirmas la cancelación de tu cita?\n"
                        . "Escribe *SÍ* para cancelar o *NO* para mantenerla.",
                ], $context);
                return $this->result(true, true, 'booking_cancel_awaiting', 1, false, null);
            }
        }

        // Botón "Cambiar horario" desde pantalla de pre-confirmación
        if ($this->normalizeText($text) === 'cambiar horario' || trim($text) === 'cambiar_horario') {
            $context['state'] = 'agenda_esperando_horario';
            unset($context['awaiting_field'], $context['horario_texto'], $context['fecha_inicio_raw']);
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => '🔄 Sin problema. ¿Qué horario prefieres? Escribe la fecha o elige de las opciones disponibles.',
            ], $context);
            $this->saveSession($conversation, (string)$conversation->wa_number, 'cambiar_horario',
                null, null, $context, $messagePayload);
            return $this->result(true, true, 'cambiar_horario', 1, false, null);
        }

        if ($this->isBookingChangeRequest($text)) {
            $activeBooking = $this->activeSigcenterBooking($conversation, $context);
            if ($activeBooking !== null) {
                $changeType = $this->bookingChangeType($text);
                if ($changeType === 'cancel') {
                    $this->sendFlowMessage($conversation, $this->bookingCancelConfirmationMessage($activeBooking), $context);
                } else {
                    $this->recordSigcenterBookingChangeRequest($activeBooking, $conversation, $inboundMessage, $changeType);
                    $this->markConversationForBookingSupport($conversation, $activeBooking, $changeType);
                    $this->sendFlowMessage($conversation, $this->bookingChangeRequestMessage($activeBooking, $changeType), $context);
                }

                $this->saveSession(
                    $conversation,
                    (string) $conversation->wa_number,
                    $session?->scenario_id ?? 'booking_change_request',
                    $session?->node_id,
                    null,
                    array_merge($context, [
                        'state' => $changeType === 'cancel' ? 'agenda_confirmar_cancelacion' : 'soporte_cita',
                        'booking_change_requested' => $changeType,
                    ]),
                    $messagePayload,
                );

                return $this->result(true, true, 'booking_change_request', 1, $changeType !== 'cancel', null);
            }
        }

        $fallbackScenarios = [];

        foreach (($flow['scenarios'] ?? []) as $scenario) {
            if (!is_array($scenario) || !$this->scenarioIsPublished($scenario)) {
                continue;
            }

            if ($this->shouldSkipCatchAllFallbackDuringAgenda($scenario, $facts)) {
                continue;
            }

            if ($this->isCatchAllFallbackScenario($scenario)) {
                $fallbackScenarios[] = $scenario;
                continue;
            }

            if (!$this->scenarioMatches($scenario, $facts)) {
                continue;
            }

            $run = $this->executeActions($scenario['actions'] ?? [], $context, $conversation, $inboundMessage, $text, (string)($scenario['id'] ?? ''));
            $context = $run['context'];

            $this->saveSession(
                $conversation,
                (string) $conversation->wa_number,
                (string) ($scenario['id'] ?? 'scenario'),
                null,
                isset($context['awaiting_field']) ? 'input' : null,
                $context,
                $messagePayload,
            );

            if (!empty($context['off_hours_handoff_pending'])) {
                $this->deferHandoffToBusinessHours($conversation, $context);
            }
            if (!empty($context['handoff_requested'])) {
                $this->markConversationForHandoff($conversation, $scenario, $context);
            }

            return $this->result(true, true, (string)($scenario['id'] ?? 'scenario'), $run['messages_sent'], !empty($context['handoff_requested']), null);
        }

        $recovery = $this->recoverNoMatchFlow($flow, $conversation, $inboundMessage, $messagePayload, $context, $text, $facts, $session);
        if ($recovery !== null) {
            return $recovery;
        }

        foreach ($fallbackScenarios as $scenario) {
            if (!$this->scenarioMatches($scenario, $facts)) {
                continue;
            }

            $run = $this->executeActions($scenario['actions'] ?? [], $context, $conversation, $inboundMessage, $text, (string)($scenario['id'] ?? 'fallback'));
            $context = $run['context'];

            $this->saveSession(
                $conversation,
                (string) $conversation->wa_number,
                (string) ($scenario['id'] ?? 'fallback'),
                null,
                isset($context['awaiting_field']) ? 'input' : null,
                $context,
                $messagePayload,
            );

            if (!empty($context['off_hours_handoff_pending'])) {
                $this->deferHandoffToBusinessHours($conversation, $context);
            }
            if (!empty($context['handoff_requested'])) {
                $this->markConversationForHandoff($conversation, $scenario, $context);
            }

            return $this->result(true, true, (string)($scenario['id'] ?? 'fallback'), $run['messages_sent'], !empty($context['handoff_requested']), null);
        }

        if (empty($context['awaiting_field']) && $this->isCourtesyMessage($text)) {
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => '¡Con gusto! 😊 Si necesitas algo más, escribe *MENU* y te ayudo.',
            ], $context);
            return $this->result(true, true, 'courtesy_reply', 1, false, null);
        }

        $frustrationLevel = $this->isFrustrationSignal($text);
        if ($frustrationLevel === 2) {
            $context['handoff_requested'] = true;
            $context['handoff_topic'] = 'frustracion_explicita';
            $context['handoff_note'] = 'Paciente expresó frustración explícita: "' . mb_substr($text, 0, 80) . '"';
            $this->sendFlowMessage($conversation, [
                'type' => 'text',
                'body' => 'Lamentamos tu experiencia. Un asesor te atenderá de inmediato. 🙏',
            ], $context);
            $this->saveSession($conversation, (string)$conversation->wa_number, 'frustration_handoff',
                null, null, $context, $messagePayload);
            $this->markConversationForHandoff($conversation, [], $context);
            return $this->result(true, true, 'frustration_handoff', 1, true, null);
        }
        if ($frustrationLevel === 1) {
            $this->sendFlowMessage($conversation, [
                'type' => 'buttons',
                'body' => 'Disculpa la confusión 🙏 ¿Cómo te podemos ayudar?',
                'buttons' => [
                    ['id' => 'agendar', 'title' => '📅 Agendar cita'],
                    ['id' => 'consultar_cita', 'title' => '🔍 Ver mi cita'],
                    ['id' => 'ayuda', 'title' => '🙋 Hablar con asesor'],
                ],
            ], $context);
            return $this->result(true, true, 'frustration_mild', 1, false, null);
        }

        $fallbackBody = trim((string) ($flow['settings']['no_match_fallback_message'] ?? ''));
        if ($fallbackBody === '') {
            $fallbackBody = "No entendí tu mensaje.\nEscribe *MENU* para ver las opciones.";
        }

        $this->sendFlowMessage($conversation, ['type' => 'text', 'body' => $fallbackBody], $context);

        $this->saveSession(
            $conversation,
            (string) $conversation->wa_number,
            'no_match_fallback',
            null,
            null,
            $context,
            $messagePayload,
        );

        return $this->result(true, false, 'no_match_fallback', 1, false, 'no_match');
    }

    /**
     * @param array<string, mixed> $flow
     * @param array<string, mixed> $messagePayload
     * @param array<string, mixed> $context
     * @param array<string, mixed> $facts
     * @return array{executed:bool,matched:bool,scenario_id:?string,messages_sent:int,handoff_requested:bool,reason:?string}|null
     */
    private function recoverNoMatchFlow(
        array                         $flow,
        WhatsappConversation          $conversation,
        WhatsappMessage               $inboundMessage,
        array                         $messagePayload,
        array                         $context,
        string                        $text,
        array                         $facts,
        ?WhatsappAutoresponderSession $session,
    ): ?array
    {
        if ($this->shouldRetryConsent($facts)) {
            $this->sendFlowMessage($conversation, $this->consentRetryMessage(), $context);
            $context['state'] = 'consentimiento_pendiente';
            unset($context['awaiting_field']);

            $this->saveSession(
                $conversation,
                (string) $conversation->wa_number,
                'consent_retry',
                $session?->node_id,
                null,
                $context,
                $messagePayload,
            );

            return $this->result(true, true, 'consent_retry', 1, false, null);
        }

        if ($this->shouldRetryCedula($facts)) {
            $retryCount = $this->incrementInputRetry($context, 'cedula');
            if ($retryCount >= 3) {
                $this->resetInputRetry($context, 'cedula');
                $this->sendFlowMessage($conversation, [
                    'type' => 'text',
                    'body' => 'No pudimos verificar tu información. Un asesor te contactará para ayudarte. 🙏',
                ], $context);
                $context['handoff_requested'] = true;
                $context['handoff_topic'] = 'cedula_no_reconocida';
                $context['handoff_note'] = 'Paciente no pudo ingresar cédula válida tras 3 intentos.';
                $context['state'] = 'handoff_cedula';
                unset($context['awaiting_field']);
                return $this->result(true, true, 'cedula_max_retries', 1, true, null);
            }
            $this->sendFlowMessage($conversation, $this->cedulaRetryMessage(), $context);
            $context['state'] = 'esperando_cedula';
            $context['awaiting_field'] = 'cedula';

            $this->saveSession(
                $conversation,
                (string) $conversation->wa_number,
                'cedula_retry',
                $session?->node_id,
                'input',
                $context,
                $messagePayload,
            );

            return $this->result(true, true, 'cedula_retry', 1, false, null);
        }

        if ($this->isNaturalSchedulingIntent($text)) {
            if (empty($context['consent'])) {
                $this->sendFlowMessage($conversation, $this->consentRetryMessage(), $context);
                $context['state'] = 'consentimiento_pendiente';
                unset($context['awaiting_field']);

                $this->saveSession(
                    $conversation,
                    (string) $conversation->wa_number,
                    'natural_schedule_consent',
                    null,
                    null,
                    $context,
                    $messagePayload,
                );

                return $this->result(true, true, 'natural_schedule_consent', 1, false, null);
            }

            if (!$this->hasPatientIdentifier($context, $conversation)) {
                $this->sendFlowMessage($conversation, $this->scheduleIdentifierRequestMessage(), $context);
                $context['state'] = 'esperando_cedula';
                $context['awaiting_field'] = 'cedula';

                $this->saveSession(
                    $conversation,
                    (string) $conversation->wa_number,
                    'natural_schedule_identifier',
                    null,
                    'input',
                    $context,
                    $messagePayload,
                );

                return $this->result(true, true, 'natural_schedule_identifier', 1, false, null);
            }

            $scenario = $this->findSchedulingEntryScenario($flow['scenarios'] ?? []);
            if ($scenario !== null) {
                $run = $this->executeActions($scenario['actions'] ?? [], $context, $conversation, $inboundMessage, $text, (string)($scenario['id'] ?? 'natural_schedule'));
                $context = $run['context'];

                $this->saveSession(
                    $conversation,
                    (string) $conversation->wa_number,
                    (string) ($scenario['id'] ?? 'natural_schedule'),
                    null,
                    isset($context['awaiting_field']) ? 'input' : null,
                    $context,
                    $messagePayload,
                );

                return $this->result(true, true, (string)($scenario['id'] ?? 'natural_schedule'), $run['messages_sent'], !empty($context['handoff_requested']), null);
            }

            $this->sendFlowMessage($conversation, $this->mainMenuMessage(), $context);
            $context['state'] = 'menu_principal';
            unset($context['awaiting_field']);

            $this->saveSession(
                $conversation,
                (string) $conversation->wa_number,
                'natural_schedule_menu',
                null,
                null,
                $context,
                $messagePayload,
            );

            return $this->result(true, true, 'natural_schedule_menu', 1, false, null);
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function clearAbandonmentMonitorOnInbound(array $context): array
    {
        if (!is_array($context['abandonment_monitor'] ?? null)) {
            return $context;
        }

        unset($context['abandonment_monitor']);

        return $context;
    }

    private function isBotReactivationCommand(string $text): bool
    {
        $normalized = $this->normalizeKeyword($text);

        return in_array($normalized, [
            'menu',
            'hola',
            'inicio',
            'iniciar',
            'empezar',
            'comenzar',
            'agendar',
            'agendar cita',
            'consultar cita',
            'servicios y sedes',
            'sedes',
            'promociones',
        ], true);
    }

    private function normalizeKeyword(string $text): string
    {
        $normalized = mb_strtolower(trim($text), 'UTF-8');
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);

        return preg_replace('/\s+/', ' ', $normalized) ?? $normalized;
    }

    private function releaseConversationToBot(WhatsappConversation $conversation): void
    {
        $conversation->fill([
            'needs_human' => false,
            'handoff_notes' => null,
            'handoff_role_id' => null,
            'assigned_user_id' => null,
            'assigned_at' => null,
            'handoff_requested_at' => null,
        ])->save();

        if (Schema::hasTable('whatsapp_handoffs')) {
            WhatsappHandoff::query()
                ->where('conversation_id', $conversation->id)
                ->whereIn('status', ['queued', 'assigned', 'expired'])
                ->update([
                    'status' => 'resolved',
                    'assigned_until' => null,
                    'last_activity_at' => now(),
                    'updated_at' => now(),
                ]);
        }

        $conversation->refresh();
    }

    private function humanQueueIsOpen(): bool
    {
        return Cache::remember('whatsapp.queue_open_status', 60, function (): bool {
            $options = $this->settingsOptions([
                'whatsapp_handoff_business_timezone',
                'whatsapp_handoff_business_schedule',
                'whatsapp_handoff_business_holidays',
                'whatsapp_handoff_business_start',
                'whatsapp_handoff_business_end',
            ]);
            return $this->computeHumanQueueIsOpen($options);
        });
    }

    /**
     * @param array<string,string> $options
     */
    private function computeHumanQueueIsOpen(array $options): bool
    {
        $timezone = trim((string) ($options['whatsapp_handoff_business_timezone'] ?? 'America/Guayaquil'));
        if ($timezone === '') {
            $timezone = 'America/Guayaquil';
        }

        $now = Carbon::now($timezone);
        if ($this->isConfiguredHoliday($now->toDateString(), (string) ($options['whatsapp_handoff_business_holidays'] ?? ''))) {
            return false;
        }

        $daySchedule = $this->resolveDaySchedule($now->isoWeekday(), $options);
        if ($daySchedule === null || !($daySchedule['enabled'] ?? false)) {
            return false;
        }

        $start = $this->minutesFromHour((string) ($daySchedule['start'] ?? '08:00'), 8 * 60);
        $end = $this->minutesFromHour((string) ($daySchedule['end'] ?? '18:00'), 18 * 60);
        $current = ((int) $now->format('H')) * 60 + (int) $now->format('i');

        if ($start === $end) {
            return true;
        }

        if ($start < $end) {
            return $current >= $start && $current < $end;
        }

        return $current >= $start || $current < $end;
    }

    /**
     * @param array<string,string> $options
     * @return array{enabled:bool,start:string,end:string}|null
     */
    private function resolveDaySchedule(int $isoWeekday, array $options): ?array
    {
        $schedule = json_decode((string) ($options['whatsapp_handoff_business_schedule'] ?? ''), true);
        if (!is_array($schedule)) {
            $start = (string) ($options['whatsapp_handoff_business_start'] ?? '08:00');
            $end = (string) ($options['whatsapp_handoff_business_end'] ?? '18:00');

            return $isoWeekday >= 1 && $isoWeekday <= 6
                ? ['enabled' => true, 'start' => $start, 'end' => $end]
                : ['enabled' => false, 'start' => $start, 'end' => $end];
        }

        $dayKey = [
            1 => 'monday',
            2 => 'tuesday',
            3 => 'wednesday',
            4 => 'thursday',
            5 => 'friday',
            6 => 'saturday',
            7 => 'sunday',
        ][$isoWeekday] ?? 'monday';

        $day = $schedule[$dayKey] ?? null;
        if (is_string($day) && preg_match('/^(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})$/', $day, $matches)) {
            return ['enabled' => true, 'start' => $matches[1], 'end' => $matches[2]];
        }

        if (!is_array($day)) {
            return null;
        }

        return [
            'enabled' => filter_var($day['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN),
            'start' => (string) ($day['start'] ?? '08:00'),
            'end' => (string) ($day['end'] ?? '18:00'),
        ];
    }

    private function isConfiguredHoliday(string $date, string $configured): bool
    {
        $dates = preg_split('/[\r\n,]+/', $configured) ?: [];

        return in_array($date, array_map(static fn(string $value): string => trim($value), $dates), true);
    }

    private function minutesFromHour(string $value, int $default): int
    {
        if (!preg_match('/^(\d{1,2}):(\d{2})$/', trim($value), $matches)) {
            return $default;
        }

        $hour = max(0, min(23, (int) $matches[1]));
        $minute = max(0, min(59, (int) $matches[2]));

        return ($hour * 60) + $minute;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function captureAwaitingInput(array $context, string $text, WhatsappMessage $inboundMessage): array
    {
        $field = $context['awaiting_field'] ?? null;
        if (!is_string($field) || trim($field) === '' || trim($text) === '') {
            return $context;
        }

        if ($this->isNavigationCommand($text)) {
            return $context;
        }

        [$value, $interactiveLabel] = $this->resolveCapturedInputValue($text, $inboundMessage);
        $field = trim($field);
        $context[$field] = $value;
        $label = $interactiveLabel !== '' ? $interactiveLabel : $this->resolveCapturedOptionLabel($context, $field, $value, $inboundMessage);
        if ($label !== null && $label !== '') {
            $this->storeCapturedOptionLabel($context, $field, $label);
        }
        if (in_array($field, ['cedula', 'identificacion', 'identifier', 'hc_number'], true)) {
            $context['cedula'] = $this->normalizeIdentifier($value);
            $context['identifier'] = $context['cedula'];
            $context['current_identifier'] = $context['cedula'];
            $this->resetInputRetry($context, 'cedula');
        }
        if ($field === 'trabajador_id') {
            $context = $this->enrichDoctorSelectionContext($context, $value);
        }
        unset($context['awaiting_field']);

        return $context;
    }

    private function isNavigationCommand(string $text): bool
    {
        return in_array($this->normalizeText(str_replace('_', ' ', $text)), [
            'atras',
            'volver',
            'menu',
            'inicio',
            'salir',
        ], true);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function resolveCapturedInputValue(string $text, WhatsappMessage $inboundMessage): array
    {
        $text = trim($text);
        $payload = is_array($inboundMessage->raw_payload) ? $inboundMessage->raw_payload : [];
        $reply = data_get($payload, 'interactive.list_reply');
        if (!is_array($reply)) {
            $reply = data_get($payload, 'interactive.button_reply');
        }

        $replyId = is_array($reply) ? trim((string)($reply['id'] ?? '')) : '';
        $replyTitle = is_array($reply) ? trim((string)($reply['title'] ?? '')) : '';

        if ($replyId !== '') {
            return [$replyId, $replyTitle];
        }

        return [$text, ''];
    }

    /**
     * @param array<int, mixed> $actions
     * @param array<string, mixed> $context
     * @return array{context:array<string,mixed>,messages_sent:int}
     */
    private function executeActions(array $actions, array $context, WhatsappConversation $conversation, WhatsappMessage $inboundMessage, string $text, string $scenarioId, bool $resetFlags = true): array
    {
        $messagesSent = 0;
        if ($resetFlags) {
            unset($context['handoff_requested'], $context['handoff_reasons'], $context['handoff_note']);
        }

        foreach ($actions as $index => $action) {
            if (!is_array($action)) {
                continue;
            }

            $type = (string)($action['type'] ?? '');
            if ($type === '') {
                continue;
            }

            if ($type === 'lookup_patient') {
                $context = $this->lookupPatient($action, $context, $conversation, $text);
                continue;
            }

            if ($type === 'conditional') {
                $branch = $this->actionConditionMatches(is_array($action['condition'] ?? null) ? $action['condition'] : [], $context)
                    ? ($action['then'] ?? [])
                    : ($action['else'] ?? []);
                if (is_array($branch)) {
                    $run = $this->executeActions($branch, $context, $conversation, $inboundMessage, $text, $scenarioId, false);
                    $context = $run['context'];
                    $messagesSent += $run['messages_sent'];
                }
                continue;
            }

            if (in_array($type, ['send_message', 'send_buttons', 'send_list'], true)) {
                $message = $action['message'] ?? null;
                if (is_array($message)) {
                    $this->sendFlowMessage($conversation, $message, $context);
                    $messagesSent++;
                }
                continue;
            }

            if ($type === 'send_sequence') {
                foreach (($action['messages'] ?? []) as $message) {
                    if (!is_array($message)) {
                        continue;
                    }
                    $this->sendFlowMessage($conversation, $message, $context);
                    $messagesSent++;
                }
                continue;
            }

            if ($type === 'send_template' && is_array($action['template'] ?? null)) {
                $this->sendTemplate($conversation, $action['template']);
                $messagesSent++;
                continue;
            }

            if ($type === 'sigcenter_agenda') {
                if ($this->shouldBlockDuplicateBooking($action, $conversation, $context)) {
                    $this->sendFlowMessage($conversation, $this->duplicateBookingMessage($this->activeSigcenterBooking($conversation, $context)), $context);
                    $messagesSent++;
                    $context['state'] = 'menu_principal';
                    unset($context['awaiting_field']);
                    continue;
                }

                if ($this->shouldRequirePatientIdentifierForAgenda($action, $context) && !$this->hasPatientIdentifier($context, $conversation)) {
                    $this->sendFlowMessage($conversation, [
                        'type' => 'text',
                        'body' => 'Antes de agendar, por favor escribe tu número de cédula.',
                    ], $context);
                    $messagesSent++;
                    $context['state'] = 'esperando_cedula';
                    $context['awaiting_field'] = 'cedula';
                    continue;
                }

                $slowOperations = ['list_times', 'list_days', 'book_appointment', 'cancel_appointment', 'list_doctors_by_name'];
                $currentOperation = $this->normalizeSigcenterOperation((string)($action['operation'] ?? ''));
                if (in_array($currentOperation, $slowOperations, true)) {
                    $waitingMessages = [
                        'book_appointment'    => '📋 Confirmando tu cita...',
                        'cancel_appointment'  => '⚙️ Procesando la cancelación...',
                        'list_times'          => '🔍 Buscando horarios disponibles...',
                        'list_days'           => '🔍 Consultando fechas disponibles...',
                        'list_doctors_by_name' => '🔍 Buscando al médico...',
                    ];
                    $this->sendFlowMessage($conversation, [
                        'type' => 'text',
                        'body' => $waitingMessages[$currentOperation] ?? '🔍 Consultando disponibilidad...',
                    ], $context);
                }

                $preview = $this->sigcenterAgendaService->execute($action, $context, [
                    'wa_number' => $conversation->wa_number,
                    'text' => $text,
                    'conversation_id' => $conversation->id,
                    'current_identifier' => $context['current_identifier'] ?? $conversation->patient_hc_number,
                    'cedula' => $context['cedula'] ?? $conversation->patient_hc_number,
                ], $this->bookingIsConfirmed($action, $context, $text));

                $context[(string)($preview['store_result_as'] ?? 'sigcenter_result')] = [
                    'operation' => $preview['operation'] ?? null,
                    'ready' => $preview['ready'] ?? false,
                    'data' => $preview['data'] ?? null,
                    'executed_at' => now()->toISOString(),
                ];

                if (!empty($preview['handoff_requested'])) {
                    $context['handoff_requested'] = true;
                    if (is_string($preview['handoff_note'] ?? null) && trim($preview['handoff_note']) !== '') {
                        $context['handoff_note'] = trim($preview['handoff_note']);
                    }
                    if (is_string($preview['handoff_topic'] ?? null) && trim($preview['handoff_topic']) !== '') {
                        $context['handoff_topic'] = trim($preview['handoff_topic']);
                    }
                    if (is_string($preview['handoff_priority'] ?? null) && trim($preview['handoff_priority']) !== '') {
                        $context['handoff_priority'] = trim(strtolower($preview['handoff_priority']));
                    }
                }

                if (($preview['operation'] ?? null) === 'list_procedimientos') {
                    $context['resolved_cita_tipo'] = (string)($preview['resolved_cita_tipo'] ?? 'sin_clasificacion');
                    $context['resolved_procedimiento_ids'] = is_array($preview['resolved_procedimiento_ids'] ?? null)
                        ? array_values(array_map(static fn(mixed $item): string => (string)$item, $preview['resolved_procedimiento_ids']))
                        : [];
                    $context['resolved_procedimiento_reason'] = (string)($preview['resolved_procedimiento_reason'] ?? 'unknown');
                    $context['resolved_last_consulta_at'] = $preview['resolved_last_consulta_at'] ?? null;
                    $context['resolved_last_surgery_at'] = $preview['resolved_last_surgery_at'] ?? null;

                    $autoProcedure = $this->resolveProcedureSelectionFromPreview($action, $preview);
                    if ($autoProcedure !== null) {
                        $context['procedimiento_id'] = $autoProcedure['id'];
                        $context['procedimiento_id_label'] = $autoProcedure['label'];
                        $context['procedimiento_nombre'] = $autoProcedure['label'];
                    }
                }

                if (($preview['operation'] ?? null) === 'book_appointment') {
                    if (!empty($preview['ok'])) {
                        $context = $this->recordSigcenterBooking($preview, $context, $conversation, $inboundMessage);
                        $this->sendFlowMessage($conversation, $this->bookingSuccessMessage($context), $context);
                        $messagesSent++;
                        // Auto-resolve: booking successful → close the conversation
                        $conversation->fill([
                            'needs_human' => false,
                            'assigned_user_id' => null,
                            'assigned_at' => null,
                        ])->save();
                    } elseif (str_contains((string)($preview['error'] ?? ''), 'Confirmación requerida')) {
                        $this->sendFlowMessage($conversation, $this->bookingPreConfirmationMessage($context), $context);
                        $messagesSent++;
                        $context['state'] = 'agenda_confirmar_cita';
                    } else {
                        $this->sendFlowMessage($conversation, $this->bookingFailureMessage((string)($preview['error'] ?? '')), $context);
                        $messagesSent++;
                    }
                }

                if (!empty($preview['send_result']) && is_array($preview['outbound_message'] ?? null)) {
                    $this->sendFlowMessage($conversation, $preview['outbound_message'], $context);
                    $messagesSent++;
                    if (is_string($preview['save_response_as'] ?? null) && $preview['save_response_as'] !== '') {
                        $context['awaiting_field'] = $preview['save_response_as'];
                    }
                    if (is_string($preview['next_state'] ?? null) && $preview['next_state'] !== '') {
                        $context['state'] = $preview['next_state'];
                    }
                }
                if (($preview['operation'] ?? null) === 'check_pending_appointment') {
                    if (is_string($preview['next_state'] ?? null) && $preview['next_state'] !== '') {
                        $context['state'] = $preview['next_state'];
                    }
                    if (!empty($preview['found'])) {
                        break;
                    }
                }
                continue;
            }

            if ($type === 'set_state') {
                $context['state'] = (string)($action['state'] ?? 'inicio');
                continue;
            }

            if ($type === 'set_context') {
                foreach (($action['values'] ?? []) as $key => $value) {
                    if (is_scalar($value)) {
                        $context[(string)$key] = $value;
                    }
                }
                continue;
            }

            if ($type === 'upsert_patient_from_context') {
                $context = $this->upsertPatientFromContext($context, $conversation);
                continue;
            }

            if ($type === 'goto_menu') {
                $this->sendFlowMessage($conversation, $this->mainMenuMessage(), $context);
                $messagesSent++;
                continue;
            }

            if ($type === 'show_active_booking') {
                $this->sendFlowMessage($conversation, $this->activeBookingLookupMessage($conversation, $context), $context);
                $messagesSent++;
                continue;
            }

            if ($type === 'show_specialties_catalog') {
                $this->sendFlowMessage($conversation, $this->specialtiesCatalogMessage(), $context);
                $messagesSent++;
                continue;
            }

            if ($type === 'persist_lead_capture') {
                $context = $this->persistLeadCaptureFromContext($conversation, $context);
                continue;
            }

            if ($type === 'store_consent') {
                $context['consent'] = (bool)($action['value'] ?? true);
                continue;
            }

            if ($type === 'handoff_agent') {
                if (!$this->humanQueueIsOpen()) {
                    $offHoursMsg = trim((string) ($action['off_hours_message']
                        ?? $this->currentFlowSettings['off_hours_handoff_message']
                        ?? ''));
                    if ($offHoursMsg === '') {
                        $offHoursMsg = 'En este momento nuestros agentes no están disponibles 🕐 En el próximo horario de atención un agente te ayudará.';
                    }
                    $this->sendFlowMessage($conversation, ['type' => 'text', 'body' => $offHoursMsg], $context);
                    $messagesSent++;
                    $context['off_hours_handoff_pending'] = true;
                    if (isset($action['role_id']) && is_numeric($action['role_id'])) {
                        $context['handoff_role_id'] = (int)$action['role_id'];
                    }
                    if (isset($action['note']) && is_string($action['note'])) {
                        $context['handoff_note'] = $this->renderPlaceholders($action['note'], $context);
                    }
                    continue;
                }

                $context['handoff_requested'] = true;
                if (isset($action['role_id']) && is_numeric($action['role_id'])) {
                    $context['handoff_role_id'] = (int)$action['role_id'];
                }
                if (isset($action['note']) && is_string($action['note'])) {
                    $context['handoff_note'] = $this->renderPlaceholders($action['note'], $context);
                }
                if (isset($action['topic']) && is_string($action['topic'])) {
                    $context['handoff_topic'] = trim($action['topic']);
                }
                if (isset($action['priority']) && is_string($action['priority'])) {
                    $context['handoff_priority'] = trim(strtolower($action['priority']));
                }
                continue;
            }

            if ($type === 'ai_agent') {
                $preview = $this->aiAgentPreviewService->preview(
                    array_merge($action, [
                        'scenario_id' => $scenarioId,
                        'action_index' => $index,
                    ]),
                    [
                        'wa_number' => $conversation->wa_number,
                        'text' => $text,
                        'conversation_id' => $conversation->id,
                    ],
                    $context
                );
                $context = is_array($preview['context_after'] ?? null) ? $preview['context_after'] : $context;
                $response = trim((string)($preview['response'] ?? ''));
                if ($response !== '') {
                    $this->sendFlowMessage($conversation, ['type' => 'text', 'body' => $response], $context);
                    $messagesSent++;
                }
                if (!empty($preview['suggested_handoff'])) {
                    $context['handoff_requested'] = true;
                    $context['handoff_reasons'] = is_array($preview['handoff_reasons'] ?? null) ? $preview['handoff_reasons'] : [];
                    $triageUrgency = trim((string)($context['triage_nivel_urgencia'] ?? ''));
                    $triageDestination = trim((string)($context['triage_destino'] ?? ''));
                    $context['handoff_note'] = str_starts_with($triageDestination, 'handoff')
                        ? 'Triage de síntomas sugirió atención humana prioritaria.'
                        : 'AI Agent sugirió handoff.';
                    $context['handoff_topic'] = str_starts_with($triageDestination, 'handoff')
                        ? 'triage_urgente'
                        : 'faq_escalada';
                    $context['handoff_priority'] = in_array($triageUrgency, ['emergente', 'alta'], true)
                        ? 'high'
                        : 'normal';
                }
            }
        }

        return ['context' => $context, 'messages_sent' => $messagesSent];
    }

    /**
     * @param array<string, mixed> $preview
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function recordSigcenterBooking(array $preview, array $context, WhatsappConversation $conversation, WhatsappMessage $inboundMessage): array
    {
        $success = !empty($preview['executed']) && !empty($preview['ok']);
        $context['sigcenter_booking_status'] = $success ? 'created' : 'failed';

        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return $context;
        }

        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];
        $response = is_array($preview['data'] ?? null) ? $preview['data'] : [];
        $agendaId = $this->firstScalarFromNested($response, ['agenda_id', 'id_agenda', 'ID_AGENDA', 'id', 'codigo']);
        $fechaInicio = $this->parseDateTime($payload['fecha_inicio'] ?? $context['fecha_inicio'] ?? null);
        $now = now();

        DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversation->id,
            'wa_number' => (string)$conversation->wa_number,
            'inbound_message_id' => $inboundMessage->wa_message_id,
            'status' => $success ? 'created' : 'failed',
            'patient_hc_number' => $conversation->patient_hc_number ?: ($context['cedula'] ?? $context['identifier'] ?? null),
            'patient_full_name' => $conversation->patient_full_name ?: data_get($context, 'patient.full_name'),
            'sigcenter_agenda_id' => $agendaId,
            'trabajador_id' => $this->scalarOrNull($payload['trabajador_id'] ?? $context['trabajador_id'] ?? null),
            'medico_nombre' => $this->scalarOrNull($context['medico_nombre'] ?? $context['trabajador_id_label'] ?? null),
            'sede_id' => $this->scalarOrNull($payload['ID_SEDE'] ?? $context['sede_id'] ?? null),
            'sede_nombre' => $this->scalarOrNull($context['sede_nombre'] ?? $context['sede_id_label'] ?? null),
            'procedimiento_id' => $this->scalarOrNull($payload['procedimiento_id'] ?? $context['procedimiento_id'] ?? null),
            'procedimiento_nombre' => $this->scalarOrNull($context['procedimiento_nombre'] ?? $context['procedimiento_id_label'] ?? null),
            'fecha_inicio' => $fechaInicio,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'response' => json_encode($preview, JSON_UNESCAPED_UNICODE),
            'error' => $success ? null : $this->scalarOrNull($preview['error'] ?? null),
            'booked_at' => $success ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return $context;
    }

    /**
     * @return array{type:string,body:string}
     */
    private function bookingPreConfirmationMessage(array $context): array
    {
        $parts = [];
        foreach ([
            'sede_id_label'         => '🏥 Sede',
            'trabajador_id_label'   => '👨‍⚕️ Médico',
            'fecha_inicio'          => '🗓️ Fecha',
            'horario_texto'         => '🕙 Hora',
            'subespecialidad_label' => '🔬 Especialidad',
        ] as $key => $label) {
            $value = trim((string)($context[$key] ?? ''));
            if ($value !== '') {
                $parts[] = "{$label}: {$value}";
            }
        }
        $summary = $parts !== [] ? "\n\n" . implode("\n", $parts) : '';

        return [
            'type' => 'buttons',
            'body' => "📋 *Resumen de tu cita*{$summary}\n\n¿Confirmamos el agendamiento?",
            'buttons' => [
                ['id' => 'confirmar_cita',  'title' => '✅ Confirmar'],
                ['id' => 'cambiar_horario', 'title' => '🔄 Cambiar horario'],
                ['id' => 'cancelar_agenda', 'title' => '❌ Cancelar'],
            ],
        ];
    }

    private function bookingSuccessMessage(array $context = []): array
    {
        $parts = [];
        foreach ([
            'sede_id_label'         => '🏥 Sede',
            'trabajador_id_label'   => '👨‍⚕️ Médico',
            'fecha_inicio'          => '🗓️ Fecha',
            'horario_texto'         => '🕙 Hora',
            'subespecialidad_label' => '🔬 Especialidad',
        ] as $key => $label) {
            $value = trim((string)($context[$key] ?? ''));
            if ($value !== '') {
                $parts[] = "{$label}: {$value}";
            }
        }
        $summary = $parts !== [] ? "\n" . implode("\n", $parts) : '';

        return [
            'type' => 'buttons',
            'body' => "✅ *¡Cita agendada exitosamente!*{$summary}\n\n*Recomendaciones:*\n▪️ Estar 10 min antes\n▪️ Traer cédula o pasaporte\n▪️ Mascarilla obligatoria\n▪️ Máximo un acompañante\n\n🙌 *¡Te esperamos!*\n\n_Te enviaremos un recordatorio 24h antes._",
            'buttons' => [
                ['id' => 'agendar',        'title' => '📅 Agendar otra cita'],
                ['id' => 'menu_principal', 'title' => '🏠 Menú principal'],
            ],
        ];
    }

    /**
     * @return array{type:string,body:string}
     */
    private function bookingFailureMessage(string $error): array
    {
        $detail = trim($error) !== '' ? "\n\nDetalle técnico: {$error}" : '';

        return [
            'type' => 'text',
            'body' => 'No pudimos confirmar tu cita en este momento. Un agente revisará tu solicitud.' . $detail,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function shouldBlockDuplicateBooking(array $action, WhatsappConversation $conversation, array $context): bool
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return false;
        }

        $operation = $this->normalizeSigcenterOperation((string)($action['operation'] ?? ''));
        if (!in_array($operation, [
            'list_specialties',
            'list_doctors',
            'list_sedes',
            'list_procedimientos',
            'list_days',
            'list_times',
            'book_appointment',
        ], true)) {
            return false;
        }

        return $this->activeSigcenterBooking($conversation, $context) !== null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function activeSigcenterBooking(WhatsappConversation $conversation, array $context): ?object
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return null;
        }

        $identifier = $this->normalizeIdentifier((string)($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? $conversation->patient_hc_number ?? ''));
        $query = DB::table('whatsapp_sigcenter_bookings')
            ->where('status', 'created')
            ->where(function ($dateQuery): void {
                $dateQuery->whereNull('fecha_inicio')
                    ->orWhere('fecha_inicio', '>=', now()->format('Y-m-d H:i:s'));
            });

        if ($identifier !== '') {
            $query->where(function ($scope) use ($identifier, $conversation): void {
                $scope->where('patient_hc_number', $identifier)
                    ->orWhere('wa_number', (string)$conversation->wa_number);
            });
        } else {
            $query->where('wa_number', (string)$conversation->wa_number);
        }

        return $query->orderByRaw('fecha_inicio IS NULL ASC')
            ->orderBy('fecha_inicio')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{type:string,body:string}
     */
    private function duplicateBookingMessage(?object $booking): array
    {
        $summary = $this->bookingSummaryText($booking);

        return [
            'type' => 'text',
            'body' => "Ya tienes una cita vigente registrada desde WhatsApp{$summary}.\n\nPara evitar duplicados no puedo crear otra cita. Si deseas cambiarla, escribe CANCELAR CITA o REAGENDAR CITA y un agente te ayudará.",
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @return array{type:string,body:string}
     */
    private function activeBookingLookupMessage(WhatsappConversation $conversation, array $context): array
    {
        $booking = $this->activeSigcenterBooking($conversation, $context);
        if ($booking === null) {
            return [
                'type' => 'text',
                'body' => 'Actualmente no tienes citas registradas desde WhatsApp.' . "\n\n" . 'Si deseas, puedo ayudarte a agendar una nueva cita. Escribe AGENDAR o vuelve al MENU.',
            ];
        }

        return [
            'type' => 'text',
            'body' => 'Esta es tu cita vigente registrada desde WhatsApp' . $this->bookingSummaryText($booking) . "\n\n" . 'Si necesitas cambiarla, escribe CANCELAR CITA o REAGENDAR CITA.',
        ];
    }

    /**
     * @return array{type:string,body:string}
     */
    private function bookingChangeRequestMessage(object $booking, string $changeType): array
    {
        $action = $changeType === 'cancel' ? 'cancelar' : 'reagendar';
        $summary = $this->bookingSummaryText($booking);

        return [
            'type' => 'text',
            'body' => "Recibimos tu solicitud para {$action} tu cita{$summary}.\n\nUn agente revisará la cita y se pondrá en contacto contigo. Mientras tanto no se crearán citas duplicadas.",
        ];
    }

    /**
     * @return array{type:string,body:string,buttons:array<int,array{id:string,title:string}>}
     */
    private function bookingCancelConfirmationMessage(object $booking): array
    {
        $summary = $this->bookingSummaryText($booking);

        return [
            'type' => 'buttons',
            'body' => "Antes de cancelar, confirma esta acción sobre tu cita{$summary}.\n\n¿Deseas cancelar esta cita?",
            'buttons' => [
                ['id' => 'confirmar_cancelacion', 'title' => 'Sí cancelar'],
                ['id' => 'mantener_cita', 'title' => 'No cancelar'],
            ],
        ];
    }

    /**
     * @return array{type:string,body:string}
     */
    private function bookingCancelledMessage(object $booking): array
    {
        $summary = $this->bookingSummaryText($booking);

        return [
            'type' => 'text',
            'body' => "Tu cita fue cancelada exitosamente{$summary}.\n\nSi necesitas agendar una nueva cita, escribe HOLA o MENU.",
        ];
    }

    /**
     * @return array{type:string,body:string}
     */
    private function bookingCancellationFailedMessage(string $error): array
    {
        return [
            'type' => 'text',
            'body' => 'No pudimos cancelar tu cita automáticamente. Un agente revisará tu solicitud.',
        ];
    }

    private function bookingCancellationConfirmed(string $text): bool
    {
        $normalized = $this->normalizeText(str_replace('_', ' ', $text));

        return in_array($normalized, [
            'confirmar cancelacion',
            'si cancelar',
            'sí cancelar',
            'confirmar',
            'si',
            'sí',
        ], true);
    }

    private function isExplicitCancelConfirmationReply(string $text): bool
    {
        return in_array($this->normalizeText(str_replace('_', ' ', $text)), [
            'confirmar cancelacion',
            'si cancelar',
            'sí cancelar',
        ], true);
    }

    private function bookingCancellationRejected(string $text): bool
    {
        $normalized = $this->normalizeText(str_replace('_', ' ', $text));

        return in_array($normalized, [
            'no cancelar',
            'mantener cita',
            'no',
            'cancelar no',
        ], true);
    }

    /**
     * @param array<string, mixed> $preview
     */
    private function markBookingCancellationResult(object $booking, array $preview, WhatsappConversation $conversation, WhatsappMessage $inboundMessage): void
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return;
        }

        $now = now();
        $ok = !empty($preview['ok']);
        DB::table('whatsapp_sigcenter_bookings')
            ->where('id', $booking->id)
            ->update([
                'status' => $ok ? 'cancelled' : 'cancel_failed',
                'response' => json_encode($preview, JSON_UNESCAPED_UNICODE),
                'error' => $ok ? null : $this->scalarOrNull($preview['error'] ?? null),
                'updated_at' => $now,
            ]);

        DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversation->id,
            'wa_number' => (string)$conversation->wa_number,
            'inbound_message_id' => $inboundMessage->wa_message_id,
            'status' => $ok ? 'cancelled' : 'cancel_failed',
            'patient_hc_number' => $booking->patient_hc_number ?? $conversation->patient_hc_number,
            'patient_full_name' => $booking->patient_full_name ?? $conversation->patient_full_name,
            'sigcenter_agenda_id' => $booking->sigcenter_agenda_id ?? null,
            'trabajador_id' => $booking->trabajador_id ?? null,
            'medico_nombre' => $booking->medico_nombre ?? null,
            'sede_id' => $booking->sede_id ?? null,
            'sede_nombre' => $booking->sede_nombre ?? null,
            'procedimiento_id' => $booking->procedimiento_id ?? null,
            'procedimiento_nombre' => $booking->procedimiento_nombre ?? null,
            'fecha_inicio' => $booking->fecha_inicio ?? null,
            'payload' => json_encode([
                'requested_action' => 'cancel',
                'source_booking_id' => $booking->id ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'response' => json_encode($preview, JSON_UNESCAPED_UNICODE),
            'error' => $ok ? null : $this->scalarOrNull($preview['error'] ?? null),
            'booked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function bookingSummaryText(?object $booking): string
    {
        if ($booking === null) {
            return '';
        }

        $parts = [];
        foreach ([
                     'fecha_inicio' => 'Fecha',
                     'medico_nombre' => 'Médico',
                     'sede_nombre' => 'Sede',
                     'procedimiento_nombre' => 'Procedimiento',
                 ] as $field => $label) {
            $value = $booking->{$field} ?? null;
            if (is_scalar($value) && trim((string)$value) !== '') {
                $parts[] = $label . ': ' . trim((string)$value);
            }
        }

        return $parts === [] ? '' : ":\n" . implode("\n", $parts);
    }

    private function isFrustrationSignal(string $text): int
    {
        $normalized = mb_strtolower(trim($text));

        $explicitFrustration = ['no funciona', 'esto no sirve', 'que malo', 'qué malo', 'pesimo', 'pésimo',
            'terrible', 'no me ayuda', 'no sirve', 'inutil', 'inútil', 'horrible'];
        foreach ($explicitFrustration as $p) {
            if (str_contains($normalized, $p)) {
                return 2;
            }
        }

        if (preg_match('/^\?{1,3}$/', $normalized)) {
            return 1;
        }
        $mildFrustration = ['no entiendo', 'no comprendo', 'ayuda urgente',
            'no puedo', 'como funciona', 'no sé', 'no se', 'que hago', 'qué hago'];
        foreach ($mildFrustration as $p) {
            if (str_contains($normalized, $p)) {
                return 1;
            }
        }

        return 0;
    }

    private function isCourtesyMessage(string $text): bool
    {
        $normalized = mb_strtolower(trim($text));
        $patterns = [
            'gracias', 'muchas gracias', 'ok gracias', 'si gracias', 'sí gracias',
            'ty', 'thx', 'thanks', 'thank you',
            'de nada', 'con gusto', 'perfecto gracias', 'listo gracias',
            'ya gracias', 'ya, gracias', 'ok, gracias', 'listo', 'entendido gracias',
        ];
        foreach ($patterns as $p) {
            if ($normalized === $p) {
                return true;
            }
        }
        if (mb_strlen($normalized) <= 30 && str_contains($normalized, 'gracias')) {
            return true;
        }
        return false;
    }

    private function isBookingChangeRequest(string $text): bool
    {
        $normalized = $this->normalizeText($text);

        return str_contains($normalized, 'cancel')
            || str_contains($normalized, 'anul')
            || str_contains($normalized, 'reagend')
            || str_contains($normalized, 'cambiar cita')
            || str_contains($normalized, 'mover cita');
    }

    private function bookingChangeType(string $text): string
    {
        $normalized = $this->normalizeText($text);

        return str_contains($normalized, 'cancel') || str_contains($normalized, 'anul')
            ? 'cancel'
            : 'reschedule';
    }

    private function markConversationForBookingSupport(WhatsappConversation $conversation, object $booking, string $changeType): void
    {
        $action = $changeType === 'cancel' ? 'cancelación' : 'reagendamiento';
        $conversation->fill([
            'needs_human' => true,
            'handoff_notes' => trim(sprintf(
                'Solicitud de %s de cita WhatsApp. Cita: %s, sede: %s, procedimiento: %s.',
                $action,
                (string)($booking->fecha_inicio ?? 'sin fecha'),
                (string)($booking->sede_nombre ?? $booking->sede_id ?? 'sin sede'),
                (string)($booking->procedimiento_nombre ?? $booking->procedimiento_id ?? 'sin procedimiento')
            )),
            'handoff_requested_at' => now(),
        ]);
        $conversation->save();
        $this->syncActiveHandoffRecord($conversation, [
            'handoff_topic' => $changeType === 'cancel' ? 'operacion_cancelacion' : 'operacion_reagenda',
            'handoff_priority' => 'high',
        ]);
    }

    private function recordSigcenterBookingChangeRequest(object $booking, WhatsappConversation $conversation, WhatsappMessage $inboundMessage, string $changeType): void
    {
        if (!Schema::hasTable('whatsapp_sigcenter_bookings')) {
            return;
        }

        $now = now();
        DB::table('whatsapp_sigcenter_bookings')->insert([
            'conversation_id' => $conversation->id,
            'wa_number' => (string)$conversation->wa_number,
            'inbound_message_id' => $inboundMessage->wa_message_id,
            'status' => $changeType === 'cancel' ? 'cancel_requested' : 'reschedule_requested',
            'patient_hc_number' => $booking->patient_hc_number ?? $conversation->patient_hc_number,
            'patient_full_name' => $booking->patient_full_name ?? $conversation->patient_full_name,
            'sigcenter_agenda_id' => $booking->sigcenter_agenda_id ?? null,
            'trabajador_id' => $booking->trabajador_id ?? null,
            'medico_nombre' => $booking->medico_nombre ?? null,
            'sede_id' => $booking->sede_id ?? null,
            'sede_nombre' => $booking->sede_nombre ?? null,
            'procedimiento_id' => $booking->procedimiento_id ?? null,
            'procedimiento_nombre' => $booking->procedimiento_nombre ?? null,
            'fecha_inicio' => $booking->fecha_inicio ?? null,
            'payload' => json_encode([
                'requested_action' => $changeType,
                'source_booking_id' => $booking->id ?? null,
            ], JSON_UNESCAPED_UNICODE),
            'response' => null,
            'error' => null,
            'booked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function normalizeSigcenterOperation(string $operation): string
    {
        return match ($operation) {
            'especialidades', 'specialties', 'list_specialties', '' => 'list_specialties',
            'medicos', 'doctors', 'list_doctors' => 'list_doctors',
            'sedes', 'list_sedes' => 'list_sedes',
            'procedimientos', 'list_procedimientos' => 'list_procedimientos',
            'dias', 'days', 'list_days' => 'list_days',
            'horarios', 'times', 'list_times' => 'list_times',
            'agendar', 'book', 'book_appointment' => 'book_appointment',
            default => $operation,
        };
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function seedPatientContextFromConversation(array $context, WhatsappConversation $conversation): array
    {
        $identifier = $this->normalizeIdentifier((string)($conversation->patient_hc_number ?? ''));
        if ($identifier !== '') {
            $context['cedula'] ??= $identifier;
            $context['identifier'] ??= $identifier;
            $context['current_identifier'] ??= $identifier;
            $context['patient_found'] = true;
            $context['patient_new'] ??= false;
        }

        $fullName = trim((string)($conversation->patient_full_name ?? ''));
        if ($fullName !== '' && !isset($context['patient'])) {
            $context['patient'] = [
                'hc_number' => $identifier,
                'full_name' => $fullName,
            ];
        }

        $attribution = $this->conversationAttribution($conversation);
        if ($attribution !== null) {
            $meta = is_array($attribution->meta ?? null) ? $attribution->meta : [];
            $leadCapture = is_array($meta['lead_capture'] ?? null) ? $meta['lead_capture'] : [];
            foreach (['lead_email', 'lead_source', 'lead_source_detail', 'crm_lead_id'] as $key) {
                if (!isset($context[$key]) && isset($leadCapture[$key]) && is_scalar($leadCapture[$key])) {
                    $context[$key] = (string)$leadCapture[$key];
                }
            }
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function lookupPatient(array $action, array $context, WhatsappConversation $conversation, string $text): array
    {
        $field = trim((string)($action['field'] ?? 'cedula'));
        $source = trim((string)($action['source'] ?? 'context'));
        $identifier = $source === 'message'
            ? $this->normalizeIdentifier($text)
            : $this->normalizeIdentifier((string)($context[$field] ?? $context['cedula'] ?? $context['identifier'] ?? ''));

        if ($identifier === '') {
            $context['patient_found'] = false;
            return $context;
        }

        $context['cedula'] = $identifier;
        $context['identifier'] = $identifier;
        $context['current_identifier'] = $identifier;

        $patient = DB::table('patient_data')
            ->whereRaw("REPLACE(TRIM(COALESCE(hc_number, '')), ' ', '') = ?", [$identifier])
            ->first();

        if ($patient === null) {
            $context['patient_found'] = false;
            $context['patient_new'] = true;
            return $context;
        }

        $fullName = $this->patientFullName((array)$patient);
        $context['patient_found'] = true;
        $context['patient_new'] = false;
        $context['patient'] = [
            'hc_number' => $identifier,
            'full_name' => $fullName !== '' ? $fullName : $identifier,
            'fname' => (string)($patient->fname ?? ''),
            'mname' => (string)($patient->mname ?? ''),
            'lname' => (string)($patient->lname ?? ''),
            'lname2' => (string)($patient->lname2 ?? ''),
            'email' => (string)($patient->email ?? ''),
        ];

        $email = trim((string)($patient->email ?? ''));
        if ($email !== '' && !isset($context['lead_email'])) {
            $context['lead_email'] = $email;
        }

        $conversation->fill([
            'patient_hc_number' => $identifier,
            'patient_full_name' => $context['patient']['full_name'],
            'needs_human' => false,
        ]);
        $conversation->save();

        return $context;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function actionConditionMatches(array $condition, array $context): bool
    {
        $type = (string)($condition['type'] ?? 'always');

        return match ($type) {
            'always' => true,
            'patient_found' => (bool)($condition['value'] ?? true) === (bool)($context['patient_found'] ?? isset($context['patient'])),
            'context_flag' => $this->contextActionFlagMatches($condition, $context),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $context
     */
    private function contextActionFlagMatches(array $condition, array $context): bool
    {
        $key = (string)($condition['key'] ?? '');
        if ($key === '') {
            return false;
        }

        if (!array_key_exists('value', $condition)) {
            return !empty($context[$key]);
        }

        return ($context[$key] ?? null) == $condition['value'];
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function upsertPatientFromContext(array $context, WhatsappConversation $conversation): array
    {
        $identifier = $this->normalizeIdentifier((string)($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? ''));
        if ($identifier === '') {
            return $context;
        }

        $context['cedula'] = $identifier;
        $context['identifier'] = $identifier;
        $context['current_identifier'] = $identifier;
        $context['patient_new'] ??= true;

        $conversation->fill([
            'patient_hc_number' => $identifier,
            'patient_full_name' => $conversation->patient_full_name ?: ($conversation->display_name ?: null),
        ]);
        $conversation->save();

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function hasPatientIdentifier(array $context, WhatsappConversation $conversation): bool
    {
        return $this->normalizeIdentifier((string)($context['cedula'] ?? $context['identifier'] ?? $context['current_identifier'] ?? $conversation->patient_hc_number ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function shouldRequirePatientIdentifierForAgenda(array $action, array $context): bool
    {
        $operation = (string)($action['operation'] ?? '');
        $state = (string)($context['state'] ?? '');

        return $operation === 'book_appointment'
            || str_starts_with($state, 'agenda_')
            || !empty($context['consent']);
    }

    /**
     * @param array<string, mixed> $facts
     */
    private function shouldRetryConsent(array $facts): bool
    {
        return ($facts['state'] ?? null) === 'consentimiento_pendiente'
            && !($facts['has_consent'] ?? false)
            && trim((string)($facts['message'] ?? '')) !== '';
    }

    /**
     * @param array<string, mixed> $facts
     */
    private function incrementInputRetry(array &$context, string $field): int
    {
        $key = 'input_retry_' . $field;
        $context[$key] = (int) ($context[$key] ?? 0) + 1;
        return $context[$key];
    }

    private function resetInputRetry(array &$context, string $field): void
    {
        unset($context['input_retry_' . $field]);
    }

    private function shouldRetryCedula(array $facts): bool
    {
        if (($facts['state'] ?? null) !== 'esperando_cedula') {
            return false;
        }

        if (trim((string)($facts['raw_message'] ?? '')) === '') {
            return false;
        }

        return !preg_match('/^\d{6,10}$/', (string)($facts['digits'] ?? ''));
    }

    private function isNaturalSchedulingIntent(string $text): bool
    {
        $normalized = $this->normalizeText($text);
        if ($normalized === '') {
            return false;
        }

        foreach ([
                     'agendar cita',
                     'agendar',
                     'agenda',
                     'quiero una cita',
                     'quiero agendar',
                     'sacar cita',
                     'consulta',
                     'doctor',
                     'especialidad',
                     'turno',
                 ] as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<int, mixed> $scenarios
     * @return array<string, mixed>|null
     */
    private function findSchedulingEntryScenario(array $scenarios): ?array
    {
        $preferredOperations = ['list_specialties', 'list_procedimientos', 'list_doctors', 'list_sedes'];

        foreach ($preferredOperations as $operation) {
            foreach ($scenarios as $scenario) {
                if (!is_array($scenario) || !$this->scenarioIsPublished($scenario)) {
                    continue;
                }

                foreach (($scenario['actions'] ?? []) as $action) {
                    if (!is_array($action) || (string)($action['type'] ?? '') !== 'sigcenter_agenda') {
                        continue;
                    }

                    if ($this->normalizeSigcenterOperation((string)($action['operation'] ?? '')) === $operation) {
                        return $scenario;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function resolveCapturedOptionLabel(array $context, string $field, string $value, WhatsappMessage $inboundMessage): ?string
    {
        $label = $this->findLabelInCachedSigcenterResults($context, $field, $value);
        if ($label !== null && $label !== '') {
            return $label;
        }

        $payload = is_array($inboundMessage->raw_payload) ? $inboundMessage->raw_payload : [];
        $reply = data_get($payload, 'interactive.list_reply');
        if (!is_array($reply)) {
            $reply = data_get($payload, 'interactive.button_reply');
        }

        $replyId = is_array($reply) ? trim((string)($reply['id'] ?? '')) : '';
        $replyTitle = is_array($reply) ? trim((string)($reply['title'] ?? '')) : '';

        return $replyId === $value && $replyTitle !== '' ? $replyTitle : null;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function findLabelInCachedSigcenterResults(array $context, string $field, string $value): ?string
    {
        foreach ($this->cachedResultKeysForField($field) as $key) {
            $label = $this->labelFromCachedResult($context[$key] ?? null, $field, $value);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        foreach ($context as $entry) {
            $label = $this->labelFromCachedResult($entry, $field, $value);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function cachedResultKeysForField(string $field): array
    {
        return match ($field) {
            'subespecialidad' => ['agenda_especialidades'],
            'trabajador_id' => ['agenda_medicos', 'agenda_medicos_busqueda'],
            'sede_id', 'ID_SEDE' => ['sigcenter_sedes', 'sigcenter_sedes_doctor'],
            'procedimiento_id' => ['sigcenter_procedimientos'],
            'fecha' => ['sigcenter_dias'],
            'fecha_inicio' => ['sigcenter_horarios'],
            default => [],
        };
    }

    /**
     * @param mixed $entry
     */
    private function labelFromCachedResult(mixed $entry, string $field, string $value): ?string
    {
        if (!is_array($entry) || !is_array($entry['data'] ?? null)) {
            return null;
        }

        $records = match ($field) {
            'subespecialidad' => $this->recordsFromData($entry['data'], ['especialidades']),
            'trabajador_id' => $this->recordsFromData($entry['data'], ['medicos', 'doctors', 'data', 'items', 'result']),
            'sede_id', 'ID_SEDE' => $this->recordsFromData($entry['data'], ['sede', 'sedes', 'data', 'items', 'result']),
            'procedimiento_id' => $this->recordsFromData($entry['data'], ['tipoProcedimientos', 'procedimientos', 'data', 'items', 'result']),
            'fecha' => $this->recordsFromData($entry['data'], ['dias', 'fechas', 'data', 'items', 'result']),
            'fecha_inicio' => $this->recordsFromData($entry['data'], ['horarios', 'times', 'data', 'items', 'result']),
            default => [],
        };

        foreach ($records as $record) {
            $label = $this->labelFromRecord($record, $field, $value);
            if ($label !== null && $label !== '') {
                return $label;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function enrichDoctorSelectionContext(array $context, string $trabajadorId): array
    {
        $record = $this->doctorRecordFromCachedResults($context, $trabajadorId);
        if (!is_array($record)) {
            return $context;
        }

        $subespecialidad = trim((string)($record['subespecialidad'] ?? ''));
        if ($subespecialidad !== '') {
            $context['subespecialidad'] = $subespecialidad;
            $context['subespecialidad_nombre'] = $subespecialidad;
        }

        $especialidad = trim((string)($record['especialidad'] ?? ''));
        if ($especialidad !== '') {
            $context['especialidad'] = $especialidad;
        }

        $doctorName = trim((string)($record['nombre'] ?? ''));
        if ($doctorName !== '') {
            $context['medico_nombre'] = $doctorName;
            $context['trabajador_id_label'] = $doctorName;
        }

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>|null
     */
    private function doctorRecordFromCachedResults(array $context, string $trabajadorId): ?array
    {
        $trabajadorId = trim($trabajadorId);
        if ($trabajadorId === '') {
            return null;
        }

        foreach (['agenda_medicos', 'agenda_medicos_busqueda'] as $key) {
            $entry = $context[$key] ?? null;
            if (!is_array($entry) || !is_array($entry['data'] ?? null)) {
                continue;
            }

            $records = $this->recordsFromData($entry['data'], ['medicos', 'doctors', 'data', 'items', 'result']);
            foreach ($records as $record) {
                if (!is_array($record)) {
                    continue;
                }

                $candidate = $this->firstRecordValue($record, ['trabajador_id', 'ID_TRABAJADOR', 'id_trabajador', 'codigo', 'id']);
                if ($candidate !== '' && $candidate === $trabajadorId) {
                    return $record;
                }
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $preview
     * @return array{id:string,label:string}|null
     */
    private function resolveProcedureSelectionFromPreview(array $action, array $preview): ?array
    {
        if (($preview['operation'] ?? null) !== 'list_procedimientos') {
            return null;
        }

        $autoSelect = $action['auto_select_single'] ?? null;
        $shouldAutoSelect = $autoSelect === true
            || $autoSelect === 1
            || $autoSelect === '1'
            || $autoSelect === 'true'
            || $autoSelect === 'yes'
            || empty($preview['send_result']);

        if (!$shouldAutoSelect) {
            return null;
        }

        $data = is_array($preview['data'] ?? null) ? $preview['data'] : [];
        $records = $this->recordsFromData($data, ['tipoProcedimientos', 'procedimientos', 'data', 'items', 'result']);
        $allowedIds = is_array($preview['resolved_procedimiento_ids'] ?? null)
            ? array_values(array_filter(array_map(static fn(mixed $item): string => trim((string)$item), $preview['resolved_procedimiento_ids'])))
            : [];

        $matches = [];
        foreach ($records as $record) {
            if (!is_array($record)) {
                continue;
            }

            $id = $this->firstRecordValue($record, ['procedimiento_id', 'ID_PROCEDIMIENTO', 'id_procedimiento', 'id', 'codigo']);
            if ($id === '') {
                continue;
            }

            if ($allowedIds !== [] && !in_array($id, $allowedIds, true)) {
                continue;
            }

            $label = $this->firstRecordValue($record, ['procedimiento', 'NOMBRE_PROCEDIMIENTO', 'nombre_procedimiento', 'nombre', 'descripcion']);
            $matches[$id] = [
                'id' => $id,
                'label' => $label !== '' ? $label : $id,
            ];
        }

        if (count($matches) === 1) {
            return array_values($matches)[0];
        }

        if (count($allowedIds) === 1) {
            $id = $allowedIds[0];
            $label = $this->defaultProcedureLabel($id);

            return [
                'id' => $id,
                'label' => $label !== '' ? $label : $id,
            ];
        }

        return null;
    }

    private function defaultProcedureLabel(string $id): string
    {
        return match ($id) {
            '530' => 'Consulta nuevo',
            '531' => 'Cita Médica',
            '532' => 'Consulta control',
            '536' => 'Post quirúrgico',
            default => '',
        };
    }

    /**
     * @param mixed $record
     */
    private function labelFromRecord(mixed $record, string $field, string $value): ?string
    {
        if (is_scalar($record)) {
            $text = trim((string)$record);
            return $text === $value ? $text : null;
        }

        if (!is_array($record)) {
            return null;
        }

        [$idKeys, $labelKeys] = match ($field) {
            'trabajador_id' => [['trabajador_id', 'id_trabajador', 'id'], ['nombre', 'name']],
            'sede_id', 'ID_SEDE' => [['ID_SEDE', 'id_sede', 'sede_id', 'id', 'codigo'], ['NOMBRE', 'NOMBRE_SEDE', 'sede', 'nombre_sede', 'nombre', 'descripcion']],
            'procedimiento_id' => [['procedimiento_id', 'ID_PROCEDIMIENTO', 'id_procedimiento', 'id', 'codigo'], ['procedimiento', 'NOMBRE_PROCEDIMIENTO', 'nombre_procedimiento', 'nombre', 'descripcion']],
            'fecha' => [['FECHA', 'fecha', 'date', 'dia', 'id'], ['FECHA', 'fecha', 'date', 'dia', 'label']],
            'fecha_inicio' => [['fecha_inicio', 'FECHA_INICIO', 'inicio', 'hora', 'id'], ['hora', 'HORA', 'inicio', 'fecha_inicio', 'FECHA_INICIO', 'label']],
            default => [['id', $field], ['title', 'nombre', 'descripcion', $field]],
        };

        $id = $this->firstRecordValue($record, $idKeys);
        if ($id !== $value) {
            return null;
        }

        return $this->firstRecordValue($record, $labelKeys) ?: $id;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function storeCapturedOptionLabel(array &$context, string $field, string $label): void
    {
        $context[$field . '_label'] = $label;

        $alias = match ($field) {
            'subespecialidad' => 'subespecialidad_nombre',
            'trabajador_id' => 'medico_nombre',
            'sede_id', 'ID_SEDE' => 'sede_nombre',
            'procedimiento_id' => 'procedimiento_nombre',
            'fecha' => 'fecha_texto',
            'fecha_inicio' => 'horario_texto',
            default => null,
        };

        if ($alias !== null) {
            $context[$alias] = $label;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @param array<int, string> $keys
     * @return array<int, mixed>
     */
    private function recordsFromData(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (isset($data[$key]) && is_array($data[$key])) {
                return array_values($data[$key]);
            }
        }

        if (array_is_list($data)) {
            return array_values($data);
        }

        return [];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, string> $keys
     */
    private function firstRecordValue(array $record, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $record[$key] ?? null;
            if (is_scalar($value) && trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }

        return '';
    }

    /**
     * @param mixed $value
     */
    private function scalarOrNull(mixed $value): ?string
    {
        if (!is_scalar($value)) {
            return null;
        }

        $text = trim((string)$value);

        return $text !== '' ? $text : null;
    }

    /**
     * @param mixed $value
     */
    private function parseDateTime(mixed $value): ?string
    {
        $text = $this->scalarOrNull($value);
        if ($text === null) {
            return null;
        }

        try {
            return Carbon::parse($text)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param mixed $data
     * @param array<int, string> $keys
     */
    private function firstScalarFromNested(mixed $data, array $keys): ?string
    {
        if (!is_array($data)) {
            return null;
        }

        foreach ($keys as $key) {
            $value = $data[$key] ?? null;
            $scalar = $this->scalarOrNull($value);
            if ($scalar !== null) {
                return $scalar;
            }
        }

        foreach ($data as $value) {
            if (!is_array($value)) {
                continue;
            }

            $scalar = $this->firstScalarFromNested($value, $keys);
            if ($scalar !== null) {
                return $scalar;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function patientFullName(array $row): string
    {
        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter([
            (string)($row['fname'] ?? ''),
            (string)($row['mname'] ?? ''),
            (string)($row['lname'] ?? ''),
            (string)($row['lname2'] ?? ''),
        ], static fn(string $value): bool => trim($value) !== ''))) ?? '');
    }

    private function normalizeIdentifier(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?: trim($value);
    }

    /**
     * @return array<string, mixed>
     */
    private function mainMenuMessage(): array
    {
        $rows = $this->mainMenuRows();

        return [
            'type' => 'list',
            'body' => "👁️ *¿En qué puedo ayudarte hoy?*",
            'button_text' => '✨ Ver opciones',
            'sections' => [
                [
                    'title' => 'Opciones',
                    'rows' => $rows,
                ],
            ],
        ];
    }

    /**
     * @return array<int, array{id:string,title:string,description:string}>
     */
    private function mainMenuRows(): array
    {
        $options = $this->settingsOptions([
            'whatsapp_menu_agendar_enabled',
            'whatsapp_menu_consultar_cita_enabled',
            'whatsapp_menu_servicios_sedes_enabled',
            'whatsapp_menu_promociones_enabled',
            'whatsapp_menu_ayuda_enabled',
        ]);

        $catalog = [
            ['id' => 'agendar', 'title' => '📅 Agendar cita', 'description' => 'Consultas, cirugías y especialistas', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_agendar_enabled', true)],
            ['id' => 'consultar_cita', 'title' => '🔍 Ver mi cita', 'description' => 'Consultar o cancelar cita vigente', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_consultar_cita_enabled', true)],
            ['id' => 'servicios_y_sedes', 'title' => '📍 Sedes y horarios', 'description' => 'Dónde atendemos y en qué horario', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_servicios_sedes_enabled', true)],
            ['id' => 'especialidades', 'title' => '🩺 Especialidades', 'description' => 'Qué tratamos en CIVE', 'enabled' => true],
            ['id' => 'precios_convenios', 'title' => '💰 Precios y convenios', 'description' => 'Tarifas, seguros y convenios', 'enabled' => true],
            ['id' => 'promociones', 'title' => '🎁 Promociones', 'description' => 'Campañas y descuentos vigentes', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_promociones_enabled', true)],
            ['id' => 'ayuda', 'title' => '🙋 Hablar con asesor', 'description' => 'Atención personalizada', 'enabled' => $this->settingFlag($options, 'whatsapp_menu_ayuda_enabled', true)],
        ];

        return array_values(array_map(
            static fn(array $row): array => [
                'id' => $row['id'],
                'title' => $row['title'],
                'description' => $row['description'],
            ],
            array_filter($catalog, static fn(array $row): bool => (bool)($row['enabled'] ?? false))
        ));
    }

    /**
     * @param array<string,string> $options
     */
    private function settingFlag(array $options, string $key, bool $default): bool
    {
        if (!array_key_exists($key, $options)) {
            return $default;
        }

        return in_array(strtolower(trim((string)$options[$key])), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * @param array<int,string> $keys
     * @return array<string,string>
     */
    private function settingsOptions(array $keys): array
    {
        if ($this->settingsResolver === null) {
            $this->settingsResolver = new SettingsOptionResolver();
        }

        return $this->settingsResolver->getOptions($keys);
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function persistLeadCaptureFromContext(WhatsappConversation $conversation, array $context): array
    {
        $identifier = $this->normalizeIdentifier((string)($context['cedula'] ?? $context['identifier'] ?? $conversation->patient_hc_number ?? ''));
        $email = trim((string)($context['lead_email'] ?? $context['email'] ?? ''));
        $leadSource = trim((string)($context['lead_source_label'] ?? $context['lead_source'] ?? ''));
        $leadSourceDetail = trim((string)($context['lead_source_detail'] ?? ''));

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $context;
        }

        $capture = array_filter([
            'lead_email' => $email !== '' ? $email : null,
            'lead_source' => $leadSource !== '' ? $leadSource : null,
            'lead_source_detail' => $leadSourceDetail !== '' ? $leadSourceDetail : null,
            'patient_new' => !empty($context['patient_new']),
            'patient_found' => !empty($context['patient_found']),
            'captured_at' => now()->toDateTimeString(),
        ], static fn(mixed $value): bool => $value !== null && $value !== '');

        $attribution = $this->conversationAttribution($conversation);
        if ($attribution !== null) {
            $meta = is_array($attribution->meta ?? null) ? $attribution->meta : [];
            $meta['lead_capture'] = array_merge(
                is_array($meta['lead_capture'] ?? null) ? $meta['lead_capture'] : [],
                $capture
            );
            $attribution->meta = $meta;
            $attribution->last_synced_at = now();
            $attribution->save();
        }

        if (Schema::hasTable('crm_leads')) {
            $crmLeadId = $this->upsertCrmLeadCapture($conversation, $context, $identifier, $email, $leadSource, $leadSourceDetail);
            if ($crmLeadId !== null) {
                $context['crm_lead_id'] = (string)$crmLeadId;
                if ($attribution !== null) {
                    $meta = is_array($attribution->meta ?? null) ? $attribution->meta : [];
                    $meta['lead_capture'] = array_merge(
                        is_array($meta['lead_capture'] ?? null) ? $meta['lead_capture'] : [],
                        ['crm_lead_id' => $crmLeadId]
                    );
                    $attribution->meta = $meta;
                    $attribution->save();
                }
            }
        }

        $crmOpportunityId = $this->upsertCrmOpportunityLeadCapture(
            conversation: $conversation,
            context: $context,
            identifier: $identifier,
            leadSource: $leadSource,
            leadSourceDetail: $leadSourceDetail,
        );
        if ($crmOpportunityId !== null) {
            $context['crm_opportunity_id'] = (string)$crmOpportunityId;

            if ($attribution !== null) {
                $meta = is_array($attribution->meta ?? null) ? $attribution->meta : [];
                $meta['lead_capture'] = array_merge(
                    is_array($meta['lead_capture'] ?? null) ? $meta['lead_capture'] : [],
                    ['crm_opportunity_id' => $crmOpportunityId]
                );
                $attribution->meta = $meta;
                $attribution->save();
            }
        }

        if ($email !== '') {
            $context['lead_email'] = $email;
        }
        if ($leadSource !== '') {
            $context['lead_source'] = $leadSource;
        }
        if ($leadSourceDetail !== '') {
            $context['lead_source_detail'] = $leadSourceDetail;
        }
        $context['lead_capture_saved'] = true;

        return $context;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function upsertCrmOpportunityLeadCapture(
        WhatsappConversation $conversation,
        array $context,
        string $identifier,
        string $leadSource,
        string $leadSourceDetail,
    ): ?int {
        if (!Schema::hasTable('crm_contacts')
            || !Schema::hasTable('crm_opportunities')
            || !Schema::hasTable('crm_activities')
        ) {
            return null;
        }

        $name = trim((string)data_get($context, 'patient.full_name', ''));
        if ($name === '') {
            $name = trim((string)($conversation->patient_full_name ?? $conversation->display_name ?? $conversation->wa_number));
        }

        $contact = app(CrmContactResolverService::class)->resolve(
            phone: trim((string)$conversation->wa_number),
            name: $name !== '' ? $name : trim((string)$conversation->wa_number),
            cedula: $identifier !== '' ? $identifier : null,
            source: 'whatsapp',
        );

        $titleDetail = $leadSourceDetail !== '' ? $leadSourceDetail : ($leadSource !== '' ? $leadSource : 'captura automática');
        $opportunity = app(CrmOpportunityService::class)->upsertFromEvent(
            contact: $contact,
            title: 'Lead WhatsApp: ' . $titleDetail,
            source: 'whatsapp',
            sourceId: (int)$conversation->id,
            sourceType: 'whatsapp_flow_capture',
        );

        return (int)$opportunity->id;
    }

    private function conversationAttribution(WhatsappConversation $conversation): ?WhatsappConversationAttribution
    {
        if (!Schema::hasTable('whatsapp_conversation_attributions')) {
            return null;
        }

        return WhatsappConversationAttribution::query()
            ->where('conversation_id', $conversation->id)
            ->first();
    }

    /**
     * @param array<string, mixed> $context
     */
    private function upsertCrmLeadCapture(
        WhatsappConversation $conversation,
        array                $context,
        string               $identifier,
        string               $email,
        string               $leadSource,
        string               $leadSourceDetail,
    ): ?int
    {
        $name = trim((string)data_get($context, 'patient.full_name', ''));
        if ($name === '') {
            $name = trim((string)($conversation->patient_full_name ?? $conversation->display_name ?? $conversation->wa_number));
        }

        $firstName = trim((string)data_get($context, 'patient.fname', ''));
        $lastName = trim(implode(' ', array_filter([
            trim((string)data_get($context, 'patient.lname', '')),
            trim((string)data_get($context, 'patient.lname2', '')),
        ])));
        $phone = trim((string)$conversation->wa_number);
        $source = $leadSource !== '' ? 'WhatsApp - ' . $leadSource : 'WhatsApp';

        $attribution = $this->conversationAttribution($conversation);
        $attributionNote = null;
        if ($attribution !== null) {
            $parts = array_filter([
                trim((string)($attribution->source_category ?? '')),
                trim((string)($attribution->source_type ?? '')),
                trim((string)($attribution->source_id ?? '')),
                trim((string)($attribution->headline ?? '')),
            ]);
            if ($parts !== []) {
                $attributionNote = implode(' | ', $parts);
            }
        }

        $noteParts = array_filter([
            'Captura automática desde flujo WhatsApp.',
            $leadSourceDetail !== '' ? 'Detalle origen: ' . $leadSourceDetail : null,
            $attributionNote !== null ? 'Atribución: ' . $attributionNote : null,
        ]);

        $payload = array_filter([
            'hc_number' => $identifier !== '' ? $identifier : null,
            'name' => $name !== '' ? $name : $phone,
            'first_name' => $firstName !== '' ? $firstName : null,
            'last_name' => $lastName !== '' ? $lastName : null,
            'email' => $email !== '' ? $email : null,
            'phone' => $phone !== '' ? $phone : null,
            'status' => 'new',
            'source' => $source,
            'notes' => $noteParts !== [] ? implode("\n", $noteParts) : null,
        ], static fn(mixed $value): bool => $value !== null && $value !== '');

        $query = DB::table('crm_leads');
        $existing = $identifier !== ''
            ? $query->where('hc_number', $identifier)->first()
            : $query->where('phone', $phone)->first();

        $now = now();
        if ($existing !== null) {
            $changes = $payload;
            unset($changes['status']);
            $changes['updated_at'] = $now;
            DB::table('crm_leads')->where('id', $existing->id)->update($changes);

            return (int)$existing->id;
        }

        $payload['created_at'] = $now;
        $payload['updated_at'] = $now;

        return (int)DB::table('crm_leads')->insertGetId($payload);
    }

    /**
     * @return array{type:string,body:string}
     */
    private function specialtiesCatalogMessage(): array
    {
        $specialties = collect();

        if (Schema::hasTable('whatsapp_sigcenter_doctor_catalog')) {
            $specialties = DB::table('whatsapp_sigcenter_doctor_catalog')
                ->where('active', true)
                ->whereNotNull('subespecialidad')
                ->where('subespecialidad', '<>', '')
                ->distinct()
                ->orderBy('subespecialidad')
                ->pluck('subespecialidad');
        }

        if ($specialties->isEmpty()) {
            $specialties = DB::table('users')
                ->whereNotNull('id_trabajador')
                ->whereNotNull('subespecialidad')
                ->where('subespecialidad', '<>', '')
                ->distinct()
                ->orderBy('subespecialidad')
                ->pluck('subespecialidad');
        }

        $items = $this->specialtiesCatalogItems(
            $specialties
                ->filter(fn(mixed $value): bool => is_string($value) && trim((string)$value) !== '')
                ->map(fn(mixed $value): string => trim((string)$value))
                ->values()
                ->all()
        );

        if ($items === []) {
            return [
                'type' => 'text',
                'body' => 'En este momento no pude cargar el listado de especialidades. Si deseas apoyo, escribe AYUDA.',
            ];
        }

        return [
            'type' => 'text',
            'body' => "Estas son nuestras especialidades disponibles:\n\n" . implode("\n", $items) . "\n\nSi deseas agendar una cita, escribe AGENDAR o vuelve al MENU.",
        ];
    }

    /**
     * @param array<int, string> $available
     * @return array<int, string>
     */
    private function specialtiesCatalogItems(array $available): array
    {
        $available = array_values(array_filter(array_map(
            static fn(mixed $value): string => trim((string)$value),
            $available
        ), static fn(string $value): bool => $value !== ''));

        $availableByKey = [];
        foreach ($available as $value) {
            $availableByKey[$this->specialtyKey($value)] = $value;
        }

        $items = [];
        $seen = [];

        $appendItem = function (string $value) use (&$items, &$seen): void {
            $meta = $this->specialtyDisplayMeta($value);
            $dedupeKey = $this->specialtyKey((string)($meta['title'] ?? $value));
            if ($dedupeKey === '' || isset($seen[$dedupeKey])) {
                return;
            }

            $line = '• ' . ($meta['title'] ?? $value);
            $description = trim((string)($meta['description'] ?? ''));
            if ($description !== '') {
                $line .= "\n  " . $description;
            }

            $items[] = $line;
            $seen[$dedupeKey] = true;
        };

        foreach ($this->preferredSpecialtyOrder() as $preferred) {
            $value = $availableByKey[$this->specialtyKey($preferred)] ?? null;
            if ($value === null) {
                continue;
            }

            $appendItem($value);
        }

        foreach ($available as $value) {
            $appendItem($value);
        }

        return $items;
    }

    /**
     * @return array{title:string,description:string}
     */
    private function specialtyDisplayMeta(string $value): array
    {
        $key = $this->specialtyKey($value);
        $map = [
            'oculoplastia' => ['title' => 'Oculoplástica', 'description' => ''],
            'retina y vitreo' => ['title' => 'Retina y Vítreo', 'description' => ''],
            'oftalmopediatria' => ['title' => 'Oftalmopediatría', 'description' => ''],
            'oftalmologo general' => ['title' => 'Segmento Anterior', 'description' => 'Superficie Ocular, Cirugía de Catarata'],
            'segmento anterior' => ['title' => 'Segmento Anterior', 'description' => 'Superficie Ocular, Cirugía de Catarata'],
            'glaucoma' => ['title' => 'Glaucoma', 'description' => ''],
            'cornea y cirugia refractiva' => ['title' => 'Córnea y Cirugía Refractiva', 'description' => ''],
            'oncologia ocular' => ['title' => 'Oncología Ocular', 'description' => ''],
            'contactologia y baja vision' => ['title' => 'Contactología y Baja Visión', 'description' => ''],
        ];

        return $map[$key] ?? ['title' => $this->titleCaseSpecialty($value), 'description' => ''];
    }

    /**
     * @return array<int, string>
     */
    private function preferredSpecialtyOrder(): array
    {
        return [
            'oculoplastia',
            'retina y vitreo',
            'oftalmopediatria',
            'oftalmologo general',
            'segmento anterior',
            'glaucoma',
            'cornea y cirugia refractiva',
            'oncologia ocular',
            'contactologia y baja vision',
        ];
    }

    private function specialtyKey(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
        ]);

        return preg_replace('/\s+/', ' ', $value) ?? $value;
    }

    private function titleCaseSpecialty(string $value): string
    {
        $value = preg_replace('/\s+/', ' ', mb_strtolower(trim($value), 'UTF-8')) ?? trim($value);
        if ($value === '') {
            return '';
        }

        $smallWords = ['y', 'e', 'de', 'del', 'la', 'las', 'los', 'en'];
        $words = preg_split('/\s+/', $value) ?: [];

        return implode(' ', array_map(static function (string $word, int $index) use ($smallWords): string {
            if ($index > 0 && in_array($word, $smallWords, true)) {
                return $word;
            }

            return mb_strtoupper(mb_substr($word, 0, 1, 'UTF-8'), 'UTF-8')
                . mb_substr($word, 1, null, 'UTF-8');
        }, $words, array_keys($words)));
    }

    /**
     * @return array{type:string,body:string,buttons:array<int,array{id:string,title:string}>}
     */
    private function consentRetryMessage(): array
    {
        return [
            'type' => 'buttons',
            'body' => 'Para ayudarte con tu cita o revisar tus datos, necesito tu autorización para usar tus datos protegidos. ¿Nos autorizas?',
            'buttons' => [
                ['id' => 'acepto', 'title' => 'Acepto'],
                ['id' => 'no_acepto', 'title' => 'No autorizo'],
            ],
        ];
    }

    /**
     * @return array{type:string,body:string}
     */
    private function cedulaRetryMessage(): array
    {
        return [
            'type' => 'text',
            'body' => 'Para continuar necesito tu número de cédula en formato válido. Escríbelo con 6 a 10 dígitos, sin espacios ni guiones. Si prefieres apoyo, escribe AYUDA.',
        ];
    }

    /**
     * @return array{type:string,body:string}
     */
    private function scheduleIdentifierRequestMessage(): array
    {
        return [
            'type' => 'text',
            'body' => 'Puedo ayudarte a agendar tu cita. Primero necesito tu número de cédula para continuar.',
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $facts
     */
    private function shouldSkipCatchAllFallbackDuringAgenda(array $scenario, array $facts): bool
    {
        $state = (string)($facts['state'] ?? '');
        if (!str_starts_with($state, 'agenda_')) {
            return false;
        }

        $conditions = array_values(array_filter($scenario['conditions'] ?? [], 'is_array'));
        if ($conditions === []) {
            return true;
        }

        $hasAgendaSpecificCondition = false;
        foreach ($conditions as $condition) {
            $type = (string)($condition['type'] ?? '');
            if (in_array($type, ['state_is', 'awaiting_is'], true)) {
                $hasAgendaSpecificCondition = true;
                break;
            }
        }

        if ($hasAgendaSpecificCondition) {
            return false;
        }

        $id = mb_strtolower((string)($scenario['id'] ?? ''), 'UTF-8');
        $name = mb_strtolower((string)($scenario['name'] ?? ''), 'UTF-8');

        return str_contains($id, 'fallback') || str_contains($name, 'fallback');
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function isCatchAllFallbackScenario(array $scenario): bool
    {
        $conditions = array_values(array_filter($scenario['conditions'] ?? [], 'is_array'));
        if (count($conditions) !== 1) {
            return false;
        }

        $onlyCondition = $conditions[0];
        if ((string)($onlyCondition['type'] ?? '') !== 'always') {
            return false;
        }

        $id = mb_strtolower((string)($scenario['id'] ?? ''), 'UTF-8');
        $name = mb_strtolower((string)($scenario['name'] ?? ''), 'UTF-8');

        return str_contains($id, 'fallback') || str_contains($name, 'fallback');
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function bookingIsConfirmed(array $action, array $context, string $text): bool
    {
        $operation = (string)($action['operation'] ?? '');
        if (!in_array($operation, ['agendar', 'book', 'book_appointment'], true)) {
            return false;
        }

        foreach ([$action['confirmed'] ?? null, $action['confirmation_granted'] ?? null] as $value) {
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        foreach (['sigcenter_booking_confirmed', 'appointment_confirmed', 'cita_confirmada'] as $key) {
            $value = $context[$key] ?? null;
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        $contextKey = $action['confirmation_context_key'] ?? null;
        if (is_string($contextKey) && $contextKey !== '') {
            $value = $context[$contextKey] ?? null;
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes') {
                return true;
            }
        }

        return in_array($this->normalizeText($text), [
            'confirmo',
            'confirmar',
            'si confirmo',
            'si agendar',
            'agendar',
            'crear cita',
            'acepto',
        ], true);
    }

    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $context
     */
    private function sendFlowMessage(WhatsappConversation $conversation, array $message, array $context): void
    {
        $config = $this->transportConfig();
        $recipient = $this->normalizePhoneNumber((string)$conversation->wa_number, $config['default_country_code']);
        if ($recipient === '') {
            throw new RuntimeException('Número de WhatsApp inválido para automatización.');
        }

        $type = (string)($message['type'] ?? 'text');
        $body = $this->normalizeRenderedMessageText(
            $this->renderPlaceholders((string)($message['body'] ?? ''), $context)
        );
        $transportResult = match ($type) {
            'buttons' => $this->transport->sendInteractiveButtons(
                $config['phone_number_id'],
                $config['access_token'],
                $config['api_version'],
                $recipient,
                $body,
                is_array($message['buttons'] ?? null) ? $message['buttons'] : [],
                isset($message['header']) ? $this->normalizeRenderedMessageText($this->renderPlaceholders((string)$message['header'], $context)) : null,
                isset($message['footer']) ? $this->normalizeRenderedMessageText($this->renderPlaceholders((string)$message['footer'], $context)) : null,
            ),
            'list' => $this->transport->sendInteractiveList(
                $config['phone_number_id'],
                $config['access_token'],
                $config['api_version'],
                $recipient,
                $body,
                is_array($message['sections'] ?? null) ? $message['sections'] : [],
                (string)($message['button_text'] ?? $message['button'] ?? 'Seleccionar'),
                isset($message['footer']) ? $this->normalizeRenderedMessageText($this->renderPlaceholders((string)$message['footer'], $context)) : null,
            ),
            default => $this->transport->sendText(
                $config['phone_number_id'],
                $config['access_token'],
                $config['api_version'],
                $recipient,
                $body,
                (bool)($message['preview_url'] ?? false),
            ),
        };

        $this->persistOutbound($conversation, $type === 'buttons' || $type === 'list' ? 'interactive' : $type, $body, $transportResult);
    }

    /**
     * @param array<string, mixed> $template
     */
    private function sendTemplate(WhatsappConversation $conversation, array $template): void
    {
        $config = $this->transportConfig();
        $recipient = $this->normalizePhoneNumber((string)$conversation->wa_number, $config['default_country_code']);
        $name = trim((string)($template['name'] ?? $template['code'] ?? ''));
        $language = trim((string)($template['language'] ?? 'es'));
        if ($recipient === '' || $name === '') {
            throw new RuntimeException('Plantilla de Flowmaker incompleta.');
        }

        $transportResult = $this->transport->sendTemplate(
            $config['phone_number_id'],
            $config['access_token'],
            $config['api_version'],
            $recipient,
            $name,
            $language,
            is_array($template['components'] ?? null) ? $template['components'] : [],
        );

        $this->persistOutbound($conversation, 'template', $name, $transportResult);
    }

    /**
     * @param array{wa_message_id:string,status:string,raw:array<string,mixed>} $transportResult
     */
    private function persistOutbound(WhatsappConversation $conversation, string $type, string $body, array $transportResult): void
    {
        $sentAt = now();
        DB::transaction(function () use ($conversation, $type, $body, $transportResult, $sentAt): void {
            WhatsappMessage::query()->create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $transportResult['wa_message_id'],
                'direction' => 'outbound',
                'message_type' => $type,
                'body' => $body !== '' ? $body : null,
                'raw_payload' => $transportResult['raw'],
                'status' => $transportResult['status'],
                'message_timestamp' => $sentAt,
                'sent_at' => $sentAt,
            ]);

            $conversation->fill([
                'last_message_at' => $sentAt,
                'last_message_direction' => 'outbound',
                'last_message_type' => $type,
                'last_message_preview' => mb_substr($body !== '' ? $body : '[' . $type . ']', 0, 512),
            ]);
            $conversation->save();
        });
    }

    /**
     * @return array{enabled:bool,phone_number_id:string,access_token:string,api_version:string,default_country_code:string}
     */
    private function transportConfig(): array
    {
        $config = $this->configService->get();
        $dryRun = (bool)config('whatsapp.migration.automation.dry_run', true);
        if ($dryRun) {
            config()->set('whatsapp.transport.dry_run', true);
            $config['enabled'] = true;
            $config['phone_number_id'] = $config['phone_number_id'] !== '' ? $config['phone_number_id'] : 'dry-run-phone';
            $config['access_token'] = $config['access_token'] !== '' ? $config['access_token'] : 'dry-run-token';
        }

        if (!$config['enabled'] || $config['phone_number_id'] === '' || $config['access_token'] === '') {
            throw new RuntimeException('La automatización de WhatsApp V2 no tiene Cloud API configurado.');
        }

        return [
            'enabled' => (bool)$config['enabled'],
            'phone_number_id' => (string)$config['phone_number_id'],
            'access_token' => (string)$config['access_token'],
            'api_version' => (string)$config['api_version'],
            'default_country_code' => (string)$config['default_country_code'],
        ];
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $context
     */
    /**
     * @param array<string, mixed> $context
     */
    private function deferHandoffToBusinessHours(WhatsappConversation $conversation, array $context): void
    {
        $note = 'Handoff solicitado fuera de horario laboral. Se activará en el próximo turno.';
        if (!empty($context['handoff_note']) && is_string($context['handoff_note'])) {
            $note .= ' · ' . trim($context['handoff_note']);
        }

        $conversation->fill([
            'needs_human'          => true,
            'handoff_notes'        => $note,
            'handoff_role_id'      => isset($context['handoff_role_id']) && is_numeric($context['handoff_role_id'])
                ? (int) $context['handoff_role_id']
                : null,
            'handoff_requested_at' => now(),
        ])->save();
        // No se llama syncActiveHandoffRecord() — sin notificaciones a agentes
    }

    private function markConversationForHandoff(WhatsappConversation $conversation, array $scenario, array $context): void
    {
        $note = 'Escalado desde Flowmaker';
        if (!empty($scenario['name'])) {
            $note .= ': ' . (string)$scenario['name'];
        }
        if (!empty($context['handoff_note']) && is_string($context['handoff_note'])) {
            $note .= ' · ' . trim($context['handoff_note']);
        }

        $conversation->fill([
            'needs_human' => true,
            'handoff_notes' => $note,
            'handoff_role_id' => isset($context['handoff_role_id']) && is_numeric($context['handoff_role_id']) ? (int)$context['handoff_role_id'] : null,
            'handoff_requested_at' => now(),
        ]);
        $conversation->save();
        $this->syncActiveHandoffRecord($conversation, $context);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function syncActiveHandoffRecord(WhatsappConversation $conversation, array $context): void
    {
        if (!Schema::hasTable('whatsapp_handoffs')) {
            return;
        }

        $topic = trim((string)($context['handoff_topic'] ?? ''));
        $priority = strtolower(trim((string)($context['handoff_priority'] ?? '')));
        if (!in_array($priority, ['critical', 'high', 'normal'], true)) {
            $priority = $conversation->patient_hc_number ? 'high' : 'normal';
        }

        $handoff = WhatsappHandoff::query()
            ->where('conversation_id', $conversation->id)
            ->whereIn('status', ['queued', 'assigned'])
            ->orderByDesc('id')
            ->first();

        if (!$handoff instanceof WhatsappHandoff) {
            $handoff = new WhatsappHandoff([
                'conversation_id' => $conversation->id,
                'wa_number' => (string)$conversation->wa_number,
                'queued_at' => $conversation->handoff_requested_at ?? now(),
            ]);
        }

        $handoff->fill([
            'status' => (int)($conversation->assigned_user_id ?? 0) > 0 ? 'assigned' : 'queued',
            'priority' => $priority,
            'topic' => $topic !== '' ? $topic : 'faq_escalada',
            'handoff_role_id' => $conversation->handoff_role_id,
            'assigned_agent_id' => (int)($conversation->assigned_user_id ?? 0) > 0 ? (int)$conversation->assigned_user_id : null,
            'assigned_at' => $conversation->assigned_at,
            'assigned_until' => (int)($conversation->assigned_user_id ?? 0) > 0 ? ($handoff->assigned_until ?? now()->addHours(24)) : null,
            'queued_at' => $handoff->queued_at ?? $conversation->handoff_requested_at ?? now(),
            'last_activity_at' => now(),
            'notes' => $conversation->handoff_notes,
        ]);
        $handoff->save();
    }

    /**
     * @param array<string, mixed> $scenario
     * @param array<string, mixed> $facts
     */
    private function scenarioMatches(array $scenario, array $facts): bool
    {
        foreach (($scenario['conditions'] ?? []) as $condition) {
            if (!is_array($condition) || !$this->evaluateCondition($condition, $facts)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function evaluateCondition(array $condition, array $facts): bool
    {
        $type = (string)($condition['type'] ?? 'always');

        return match ($type) {
            'always' => true,
            'is_first_time' => (bool)($condition['value'] ?? false) === (bool)($facts['is_first_time'] ?? false),
            'has_consent' => (bool)($condition['value'] ?? false) === (bool)($facts['has_consent'] ?? false),
            'state_is' => ($facts['state'] ?? null) === ($condition['value'] ?? null),
            'awaiting_is' => ($facts['awaiting_field'] ?? null) === ($condition['value'] ?? null),
            'message_in' => $this->messageIn($condition, $facts),
            'message_contains' => $this->messageContains($condition, $facts),
            'message_matches' => $this->messageMatches($condition, $facts),
            'last_interaction_gt' => (int)($facts['minutes_since_last'] ?? 0) >= max(0, (int)($condition['minutes'] ?? 0)),
            'patient_found' => (bool)($facts['patient_found'] ?? false),
            'context_flag' => $this->contextFlagMatches($condition, $facts),
            default => false,
        };
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageIn(array $condition, array $facts): bool
    {
        $needle = (string)($facts['message'] ?? '');
        foreach ($this->conditionTextList($condition, 'values') as $value) {
            if ($needle === $this->normalizeText($value)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageContains(array $condition, array $facts): bool
    {
        $needle = (string)($facts['message'] ?? '');
        foreach ($this->conditionTextList($condition, 'keywords') as $keyword) {
            $keyword = $this->normalizeText($keyword);
            if ($keyword !== '' && str_contains($needle, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function messageMatches(array $condition, array $facts): bool
    {
        $pattern = trim((string)($condition['pattern'] ?? $condition['value'] ?? ''));
        if ($pattern === '') {
            return false;
        }

        $regex = '~' . str_replace('~', '\\~', $pattern) . '~u';
        foreach ([(string)($facts['raw_message'] ?? ''), (string)($facts['message'] ?? ''), (string)($facts['digits'] ?? '')] as $candidate) {
            if ($candidate !== '' && @preg_match($regex, $candidate) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array<string, mixed> $facts
     */
    private function contextFlagMatches(array $condition, array $facts): bool
    {
        $key = (string)($condition['key'] ?? '');
        if ($key === '') {
            return false;
        }

        $value = $facts[$key] ?? null;
        if (!array_key_exists('value', $condition)) {
            return (bool)$value;
        }

        return $condition['value'] == $value;
    }

    /**
     * @param array<string, mixed> $condition
     * @return array<int, string>
     */
    private function conditionTextList(array $condition, string $listKey): array
    {
        $values = $condition[$listKey] ?? null;
        if (is_array($values)) {
            return array_values(array_filter(array_map(
                static fn($value): string => trim((string)$value),
                $values
            ), static fn(string $value): bool => $value !== ''));
        }

        $value = $condition['value'] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return array_values(array_filter(array_map(
                static fn(string $item): string => trim($item),
                explode(',', $value)
            ), static fn(string $item): bool => $item !== ''));
        }

        return [];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $messagePayload
     * @return array<string, mixed>
     */
    private function buildFacts(
        WhatsappConversation          $conversation,
        WhatsappMessage               $inboundMessage,
        ?WhatsappAutoresponderSession $session,
        array                         $context,
        string                        $text,
        array                         $messagePayload,
    ): array
    {
        $normalized = $this->normalizeText($text);
        $facts = [
            'is_first_time' => $session === null && empty($context['consent']),
            'state' => $context['state'] ?? 'inicio',
            'awaiting_field' => $context['awaiting_field'] ?? null,
            'has_consent' => !empty($context['consent']),
            'message' => $normalized,
            'raw_message' => $text,
            'patient_found' => isset($context['patient']) || trim((string)($conversation->patient_hc_number ?? '')) !== '',
            'consent_identifier' => $context['identifier'] ?? null,
            'current_identifier' => $context['cedula'] ?? ($context['identifier'] ?? $conversation->patient_hc_number),
            'wa_number' => $conversation->wa_number,
            'message_id' => $messagePayload['id'] ?? $inboundMessage->wa_message_id,
        ];

        $digits = preg_replace('/\D+/', '', $text) ?? '';
        if ($digits !== '') {
            $facts['digits'] = $digits;
        }

        if ($session?->last_interaction_at instanceof Carbon) {
            $facts['minutes_since_last'] = $session->last_interaction_at->diffInMinutes(now());
        }

        foreach ($context as $key => $value) {
            if (!is_string($key) || array_key_exists($key, $facts)) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $facts[$key] = $value;
            }
        }

        return $facts;
    }

    /**
     * @param array<string, mixed> $scenario
     */
    private function scenarioIsPublished(array $scenario): bool
    {
        return (string)($scenario['status'] ?? 'published') === 'published';
    }

    private function normalizeText(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = strtr($text, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ü' => 'u',
            'ñ' => 'n',
        ]);
        $text = preg_replace('/[^a-z0-9 ]+/u', '', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function renderPlaceholders(string $text, array $context): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.-]+)\s*}}/', function (array $matches) use ($context): string {
            $key = (string)$matches[1];
            $value = data_get($context, $this->displayPlaceholderKey($key, $context));
            if ($value === null && str_starts_with($key, 'context.')) {
                $value = data_get($context, $this->displayPlaceholderKey(substr($key, 8), $context));
            }
            if (is_scalar($value)) {
                return (string)$value;
            }

            return '';
        }, $text) ?? $text;
    }

    private function normalizeRenderedMessageText(string $text): string
    {
        return str_replace(
            ["\\r\\n", "\\n", "\\r", "\\t"],
            ["\r\n", "\n", "\r", "\t"],
            $text
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    private function displayPlaceholderKey(string $key, array $context): string
    {
        $alias = match ($key) {
            'trabajador_id' => 'medico_nombre',
            'sede_id', 'ID_SEDE' => 'sede_nombre',
            'procedimiento_id' => 'procedimiento_nombre',
            'fecha' => 'fecha_texto',
            'fecha_inicio' => 'horario_texto',
            default => null,
        };

        return $alias !== null && data_get($context, $alias) !== null ? $alias : $key;
    }

    private function normalizePhoneNumber(string $phoneNumber, string $defaultCountryCode): string
    {
        $digits = preg_replace('/\D+/', '', $phoneNumber) ?: '';
        if ($digits === '') {
            return '';
        }

        if ($defaultCountryCode !== '' && !str_starts_with($digits, $defaultCountryCode)) {
            return $defaultCountryCode . ltrim($digits, '0');
        }

        return $digits;
    }

    /**
     * @return array{executed:bool,matched:bool,scenario_id:?string,messages_sent:int,handoff_requested:bool,reason:?string}
     */
    private function result(bool $executed, bool $matched, ?string $scenarioId, int $messagesSent, bool $handoffRequested, ?string $reason): array
    {
        return [
            'executed' => $executed,
            'matched' => $matched,
            'scenario_id' => $scenarioId,
            'messages_sent' => $messagesSent,
            'handoff_requested' => $handoffRequested,
            'reason' => $reason,
        ];
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $messagePayload
     */
    private function saveSession(
        WhatsappConversation $conversation,
        string $waNumber,
        string $scenarioId,
        ?string $nodeId,
        ?string $awaiting,
        array $context,
        array $messagePayload,
    ): void {
        $nextVersion = ($this->sessionVersion % 255) + 1;

        $data = [
            'wa_number'           => $waNumber,
            'scenario_id'         => $scenarioId,
            'node_id'             => $nodeId,
            'awaiting'            => $awaiting,
            'context'             => $context,
            'last_payload'        => $messagePayload,
            'last_interaction_at' => now(),
            'session_version'     => $nextVersion,
        ];

        if ($this->sessionVersion === 0) {
            WhatsappAutoresponderSession::create(
                array_merge($data, [
                    'conversation_id' => $conversation->id,
                    'session_version' => 1,
                ])
            );
            $this->sessionVersion = 1;
            return;
        }

        $affected = WhatsappAutoresponderSession::query()
            ->where('conversation_id', $conversation->id)
            ->where('session_version', $this->sessionVersion)
            ->update($data);

        if ($affected === 0) {
            \Illuminate\Support\Facades\Log::warning('whatsapp.session_conflict', [
                'wa_number'      => $waNumber,
                'loaded_version' => $this->sessionVersion,
            ]);
            return;
        }

        $this->sessionVersion = $nextVersion;
    }
}
