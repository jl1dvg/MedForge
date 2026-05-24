<?php

namespace App\Modules\Whatsapp\Services;

use App\Models\ProcedimientoProyectado;
use Illuminate\Support\Carbon;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class FlowSigcenterAgendaService
{
    private const DOCTOR_CATALOG_TABLE = 'whatsapp_sigcenter_doctor_catalog';
    private const DOCTOR_AVAILABILITY_TABLE = 'whatsapp_sigcenter_doctor_availability';

    /** @var array<string, bool> */
    private static array $tableExistsCache = [];
    private const COMPANY_ID = 113;
    /** @var array<int, string> */
    private const DEFAULT_ALLOWED_SEDE_IDS = ['16', '1'];
    /** @var array<int, string> */
    private const DEFAULT_ALLOWED_PROCEDIMIENTO_IDS = ['530', '531', '532'];
    private const PROCEDIMIENTO_NUEVO_PACIENTE = '530';
    private const PROCEDIMIENTO_CITA_MEDICA = '531';
    private const PROCEDIMIENTO_CONTROL = '532';
    private const PROCEDIMIENTO_POST_QUIRURGICO = '536';
    /** @var array<string, string> */
    private const DEFAULT_SEDE_LABELS = [
        '16' => 'Ceibos',
        '1' => 'Villa Club',
    ];
    /** @var array<string, string> */
    private const LOCAL_SEDE_CODE_TO_ID = [
        'CEIBOS' => '16',
        'VILLACLUB' => '1',
    ];
    /** @var array<string, string> */
    private const DEFAULT_PROCEDIMIENTO_LABELS = [
        '530' => 'Consulta nuevo',
        '531' => 'Cita Médica',
        '532' => 'Consulta control',
        '536' => 'Post quirúrgico',
    ];

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preview(array $action, array $context, array $input): array
    {
        $operation = $this->normalizeOperation((string) ($action['operation'] ?? 'list_days'));
        $operation = $this->inferOperationFromAction($operation, $action);

        if ($operation === 'check_pending_appointment') {
            return $this->executeCheckPendingAppointment($action, $context);
        }

        $payload = $this->buildPayload($operation, $action, $context, $input);
        $missing = $this->missingFields($operation, $payload);

        $preview = [
            'type' => 'sigcenter_agenda',
            'operation' => $operation,
            'label' => $this->operationLabel($operation),
            'endpoint' => $this->endpoint($operation),
            'method' => $this->method($operation),
            'payload' => $payload,
            'missing_fields' => $missing,
            'ready' => $missing === [],
            'mutates_agenda' => in_array($operation, ['book_appointment', 'cancel_appointment'], true),
            'requires_confirmation' => in_array($operation, ['book_appointment', 'cancel_appointment'], true),
            'store_result_as' => $this->storeResultAs($action, $operation),
            'preview_only' => true,
            'context_snapshot' => $context,
        ];

        if ($operation === 'list_procedimientos') {
            $preview = array_merge($preview, $this->resolvedProcedureMetadata($action, $context));
        }

        if ($this->shouldSendResult($action) && $missing === [] && in_array($operation, ['list_specialties', 'list_doctors', 'list_sedes', 'list_doctors_by_name', 'list_sedes_by_doctor', 'list_dates_by_specialty', 'list_doctors_by_date'], true)) {
            $preview = array_merge($preview, $this->executeLocalCatalog($preview), [
                'preview_only' => true,
                'executed' => false,
            ]);
        }

        if ($this->shouldSendResult($action) && $missing === [] && !in_array($operation, ['list_specialties', 'list_doctors', 'list_doctors_by_name', 'list_times'], true)) {
            $stored = $context[(string) $preview['store_result_as']] ?? null;
            if (is_array($stored) && isset($stored['data']) && is_array($stored['data'])) {
                $preview = array_merge($preview, [
                    'ok' => true,
                    'http_code' => 200,
                    'attempted_method' => 'CONTEXT_CACHE',
                    'data' => $stored['data'],
                    'raw' => null,
                    'error' => null,
                    'executed' => false,
                ]);
            }
        }

        return $this->withConversationOutput($action, $preview);
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(array $action, array $context, array $input, bool $confirmed = false): array
    {
        $preview = $this->preview($action, $context, $input);
        $operation = (string) $preview['operation'];

        if ($operation === 'check_pending_appointment') {
            return $preview;
        }

        if (in_array($operation, ['list_specialties', 'list_doctors', 'list_sedes', 'list_doctors_by_name', 'list_sedes_by_doctor', 'list_dates_by_specialty', 'list_doctors_by_date'], true)) {
            return $this->withConversationOutput($action, array_merge($preview, $this->executeLocalCatalog($preview), [
                'preview_only' => false,
                'executed' => true,
            ]));
        }

        if (!empty($preview['missing_fields'])) {
            return $this->withConversationOutput($action, array_merge($preview, [
                'ok' => false,
                'executed' => false,
                'error' => 'Faltan campos requeridos: ' . implode(', ', $preview['missing_fields']),
            ]));
        }

        if (in_array($operation, ['book_appointment', 'cancel_appointment'], true) && !$confirmed) {
            return $this->withConversationOutput($action, array_merge($preview, [
                'ok' => false,
                'executed' => false,
                'error' => 'Confirmación requerida antes de modificar una cita real.',
            ]));
        }

        $result = match ($operation) {
            'book_appointment' => $this->executeBooking($preview),
            'cancel_appointment' => $this->executeCancel($preview),
            default => $this->executeLookup($preview),
        };

        return $this->withConversationOutput($action, array_merge($preview, $result, [
            'preview_only' => false,
            'executed' => true,
        ]));
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function executeLookup(array $preview): array
    {
        $endpoint = (string) $preview['endpoint'];
        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];
        $response = $this->request('GET', $endpoint, $payload);

        if (!$response->successful()) {
            $fallback = $this->request('POST', $endpoint, $payload);
            if ($fallback->successful()) {
                return $this->responsePayload($fallback, 'POST', null);
            }

            return $this->responsePayload($response, 'GET', $this->responsePayload($fallback, 'POST', null));
        }

        return $this->responsePayload($response, 'GET', null);
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function executeBooking(array $preview): array
    {
        $endpoint = (string) $preview['endpoint'];
        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];
        $response = $this->request('POST', $endpoint, $payload);

        if ($response->successful()) {
            return $this->responsePayload($response, 'POST', null);
        }

        $formFallback = $this->request('POST_FORM', $endpoint, $payload);
        if ($formFallback->successful()) {
            return $this->responsePayload($formFallback, 'POST_FORM', null);
        }

        $getFallback = $this->request('GET', $endpoint, $payload);
        if ($getFallback->successful()) {
            return $this->responsePayload($getFallback, 'GET', null);
        }

        return $this->responsePayload($response, 'POST', [
            'post_form' => $this->responsePayload($formFallback, 'POST_FORM', null),
            'get' => $this->responsePayload($getFallback, 'GET', null),
        ]);
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function executeCancel(array $preview): array
    {
        $endpoint = (string) $preview['endpoint'];
        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];
        $response = $this->request('POST', $endpoint, $payload);

        if ($response->successful()) {
            return $this->responsePayload($response, 'POST', null);
        }

        $formFallback = $this->request('POST_FORM', $endpoint, $payload);
        if ($formFallback->successful()) {
            return $this->responsePayload($formFallback, 'POST_FORM', null);
        }

        $getFallback = $this->request('GET', $endpoint, $payload);
        if ($getFallback->successful()) {
            return $this->responsePayload($getFallback, 'GET', null);
        }

        return $this->responsePayload($response, 'POST', [
            'post_form' => $this->responsePayload($formFallback, 'POST_FORM', null),
            'get' => $this->responsePayload($getFallback, 'GET', null),
        ]);
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $result
     * @return array<string, mixed>
     */
    private function withConversationOutput(array $action, array $result): array
    {
        $operation = (string) ($result['operation'] ?? $action['operation'] ?? '');
        $result['send_result'] = $this->shouldSendResult($action);
        $result['save_response_as'] = $this->saveResponseAs($action, $operation);
        $result['next_state'] = $this->nextState($action, $operation);

        if (!$result['send_result']) {
            return $result;
        }

        $message = $this->buildOutboundList($action, $result);
        if ($message !== null) {
            $result['outbound_message'] = $message;
        } else {
            $emptyOutput = $this->emptyConversationOutput($action, $result);
            if ($emptyOutput !== null) {
                $result = array_merge($result, $emptyOutput);
            }
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $action
     */
    private function shouldSendResult(array $action): bool
    {
        $value = $action['send_result'] ?? $action['send_as_list'] ?? false;

        return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'yes';
    }

    /**
     * @param array<string, mixed> $action
     */
    private function saveResponseAs(array $action, string $operation): ?string
    {
        $value = trim((string) ($action['save_response_as'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return match ($operation) {
            'list_specialties' => 'subespecialidad',
            'list_doctors' => 'trabajador_id',
            'list_doctors_by_name' => 'trabajador_id',
            'list_sedes' => 'sede_id',
            'list_sedes_by_doctor' => 'sede_id',
            'list_dates_by_specialty' => 'fecha',
            'list_doctors_by_date' => 'trabajador_id',
            'list_procedimientos' => 'procedimiento_id',
            'list_days' => 'fecha',
            'list_times' => 'fecha_inicio',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $action
     */
    private function nextState(array $action, string $operation): ?string
    {
        $value = trim((string) ($action['next_state'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return match ($operation) {
            'list_specialties' => 'agenda_esperando_subespecialidad',
            'list_doctors' => 'agenda_esperando_medico',
            'list_doctors_by_name' => 'agenda_esperando_doctor_directo',
            'list_sedes' => 'agenda_esperando_sede',
            'list_sedes_by_doctor' => 'agenda_esperando_sede_directa',
            'list_dates_by_specialty' => 'agenda_esperando_fecha_general',
            'list_doctors_by_date' => 'agenda_esperando_medico_general_por_fecha',
            'list_procedimientos' => 'agenda_esperando_procedimiento',
            'list_days' => 'agenda_esperando_dia',
            'list_times' => 'agenda_esperando_horario',
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    private function buildOutboundList(array $action, array $result): ?array
    {
        $operation = (string) ($result['operation'] ?? '');
        $rows = $this->listRows(
            $operation,
            is_array($result['data'] ?? null) ? $result['data'] : [],
            $action,
            is_array($result['context_snapshot'] ?? null) ? $result['context_snapshot'] : []
        );

        if ($rows === []) {
            return null;
        }

        return [
            'type' => 'list',
            'body' => $this->listBody($action, $operation),
            'button_text' => $this->listButtonText($action),
            'sections' => [[
                'title' => $this->listSectionTitle($action, $operation),
                'rows' => $rows,
            ]],
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $result
     * @return array<string, mixed>|null
     */
    private function emptyConversationOutput(array $action, array $result): ?array
    {
        $operation = (string) ($result['operation'] ?? '');

        return match ($operation) {
            'list_specialties' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No encontré especialidades disponibles en este momento. Si deseas, escribe MENU para intentarlo de otra forma.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'menu_principal'),
            ],
            'list_doctors' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No encontré médicos disponibles para esa opción en este momento. Puedes elegir otra especialidad, escribir ATRÁS o volver al MENU.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'agenda_esperando_subespecialidad'),
            ],
            'list_doctors_by_name' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No encontré un doctor o doctora con ese nombre. Verifica el nombre e inténtalo nuevamente.')),
                ],
                'save_response_as' => (string) ($action['empty_save_response_as'] ?? 'doctor_query'),
                'next_state' => (string) ($action['empty_next_state'] ?? 'esperando_nombre_doctor'),
            ],
            'list_sedes_by_doctor' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No encontré sedes disponibles para ese médico. Si deseas apoyo, escribe AYUDA.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'menu_principal'),
            ],
            'list_dates_by_specialty' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No pude confirmar fechas disponibles automáticamente en este momento. Ya pasé tu solicitud al equipo de agenda para que revise cupos y te ayude a continuar.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'handoff_agenda_disponibilidad'),
                'handoff_requested' => true,
                'handoff_topic' => 'agenda_sin_disponibilidad',
                'handoff_priority' => 'high',
                'handoff_note' => $this->availabilityHandoffNote($result, 'No hubo fechas futuras en disponibilidad local por sede/especialidad.'),
            ],
            'list_doctors_by_date' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No pude confirmar médicos disponibles para esa fecha automáticamente. Ya pasé tu solicitud al equipo de agenda para que revise cupos y te ayude a continuar.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'handoff_agenda_disponibilidad'),
                'handoff_requested' => true,
                'handoff_topic' => 'agenda_sin_disponibilidad',
                'handoff_priority' => 'high',
                'handoff_note' => $this->availabilityHandoffNote($result, 'No hubo médicos disponibles en disponibilidad local para fecha/sede/especialidad.'),
            ],
            'list_procedimientos' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No pude resolver el procedimiento de tu cita en este momento. Escribe ATRÁS para volver o AYUDA para hablar con un asesor.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'menu_principal'),
            ],
            'list_days' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No pude confirmar fechas disponibles con esa selección automáticamente. Ya pasé tu solicitud al equipo de agenda para que revise cupos y te ayude a continuar.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'handoff_agenda_disponibilidad'),
                'handoff_requested' => true,
                'handoff_topic' => 'agenda_sin_disponibilidad',
                'handoff_priority' => 'high',
                'handoff_note' => $this->availabilityHandoffNote($result, 'Sigcenter no devolvió días disponibles para médico/sede.'),
            ],
            'list_times' => [
                'outbound_message' => [
                    'type' => 'text',
                    'body' => trim((string) ($action['empty_message'] ?? 'No pude confirmar horarios disponibles para esa fecha automáticamente. Ya pasé tu solicitud al equipo de agenda para que revise cupos y te ayude a continuar.')),
                ],
                'save_response_as' => null,
                'next_state' => (string) ($action['empty_next_state'] ?? 'handoff_agenda_disponibilidad'),
                'handoff_requested' => true,
                'handoff_topic' => 'agenda_sin_disponibilidad',
                'handoff_priority' => 'high',
                'handoff_note' => $this->availabilityHandoffNote($result, 'Sigcenter no devolvió horarios disponibles para médico/sede/fecha.'),
            ],
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $result
     */
    private function availabilityHandoffNote(array $result, string $reason): string
    {
        $payload = is_array($result['payload'] ?? null) ? $result['payload'] : [];
        $context = is_array($result['context_snapshot'] ?? null) ? $result['context_snapshot'] : [];

        $parts = array_filter([
            'Rescate automático de agenda WhatsApp.',
            $reason,
            'Operación: ' . (string) ($result['operation'] ?? ''),
            'Sede: ' . (string) ($payload['sede_id'] ?? $context['sede_id_label'] ?? $context['sede_id'] ?? ''),
            'Especialidad: ' . (string) ($payload['subespecialidad'] ?? $context['subespecialidad_label'] ?? $context['subespecialidad'] ?? ''),
            'Médico: ' . (string) ($payload['trabajador_id'] ?? $context['trabajador_id_label'] ?? $context['trabajador_id'] ?? ''),
            'Fecha: ' . (string) ($payload['fecha'] ?? $payload['FECHA'] ?? $context['fecha_label'] ?? $context['fecha'] ?? ''),
        ], static fn (string $value): bool => trim($value) !== '' && !str_ends_with(trim($value), ':'));

        return implode(' · ', $parts);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function listRows(string $operation, array $data, array $action, array $context = []): array
    {
        if ($operation === 'list_specialties') {
            $specialties = is_array($data['especialidades'] ?? null) ? $data['especialidades'] : [];

            return array_values(array_filter(array_map(static function (mixed $specialty): ?array {
                if (is_array($specialty)) {
                    $id = trim((string) ($specialty['subespecialidad'] ?? $specialty['id'] ?? ''));
                    $title = trim((string) ($specialty['title'] ?? $specialty['nombre'] ?? $specialty['subespecialidad'] ?? ''));
                    $description = trim((string) ($specialty['description'] ?? $specialty['descripcion'] ?? ''));
                    if ($id === '' || $title === '') {
                        return null;
                    }

                    return [
                        'id' => $id,
                        'title' => mb_substr($title, 0, 24, 'UTF-8'),
                        'description' => mb_substr($description, 0, 72, 'UTF-8'),
                    ];
                }

                $title = trim((string) $specialty);
                if ($title === '') {
                    return null;
                }

                return [
                    'id' => $title,
                    'title' => mb_substr($title, 0, 24, 'UTF-8'),
                    'description' => '',
                ];
            }, $specialties)));
        }

        if ($operation === 'list_doctors') {
            $doctors = is_array($data['medicos'] ?? null) ? $data['medicos'] : [];

            return array_values(array_filter(array_map(function (mixed $doctor): ?array {
                if (!is_array($doctor)) {
                    return null;
                }
                $title = trim((string) ($doctor['nombre'] ?? ''));
                $id = trim((string) ($doctor['trabajador_id'] ?? $doctor['id'] ?? ''));
                if ($title === '' || $id === '') {
                    return null;
                }

                return [
                    'id' => $id,
                    'title' => $this->formatDoctorRowTitle($title),
                    'description' => mb_substr($this->specialtyDisplayTitle(trim((string) ($doctor['subespecialidad'] ?? ''))), 0, 72, 'UTF-8'),
                ];
            }, $doctors)));
        }

        if ($operation === 'list_doctors_by_name') {
            $doctors = is_array($data['medicos'] ?? null) ? $data['medicos'] : [];

            return array_values(array_filter(array_map(function (mixed $doctor): ?array {
                if (!is_array($doctor)) {
                    return null;
                }
                $title = trim((string) ($doctor['nombre'] ?? ''));
                $id = trim((string) ($doctor['trabajador_id'] ?? $doctor['id'] ?? ''));
                if ($title === '' || $id === '') {
                    return null;
                }

                $parts = array_filter([
                    $this->specialtyDisplayTitle(trim((string) ($doctor['subespecialidad'] ?? ''))),
                    trim((string) ($doctor['sede'] ?? '')),
                ]);

                return [
                    'id' => $id,
                    'title' => $this->formatDoctorRowTitle($title),
                    'description' => mb_substr(implode(' · ', $parts), 0, 72, 'UTF-8'),
                ];
            }, $doctors)));
        }

        if ($operation === 'list_sedes') {
            $rows = $this->filterRowsByAllowedIds($this->genericRows($this->recordsFromData($data, ['sede', 'sedes', 'data', 'items', 'result']), [
                'id' => ['ID_SEDE', 'id_sede', 'sede_id', 'id', 'codigo'],
                'title' => ['NOMBRE', 'NOMBRE_SEDE', 'sede', 'nombre_sede', 'nombre', 'descripcion'],
                'description' => ['direccion', 'ciudad', 'departamento'],
            ]), $this->allowedIds($action, ['allowed_sede_ids', 'allowed_sedes'], self::DEFAULT_ALLOWED_SEDE_IDS));

            return $this->applyRowTitleAliases($rows, $this->titleAliases($action, ['sede_labels', 'sede_titles'], self::DEFAULT_SEDE_LABELS));
        }

        if ($operation === 'list_sedes_by_doctor') {
            $rows = $this->filterRowsByAllowedIds($this->genericRows($this->recordsFromData($data, ['sede', 'sedes', 'data', 'items', 'result']), [
                'id' => ['ID_SEDE', 'id_sede', 'sede_id', 'id', 'codigo'],
                'title' => ['NOMBRE', 'NOMBRE_SEDE', 'sede', 'nombre_sede', 'nombre', 'descripcion'],
                'description' => ['direccion', 'ciudad', 'departamento'],
            ]), $this->allowedIds($action, ['allowed_sede_ids', 'allowed_sedes'], self::DEFAULT_ALLOWED_SEDE_IDS));

            return $this->applyRowTitleAliases($rows, $this->titleAliases($action, ['sede_labels', 'sede_titles'], self::DEFAULT_SEDE_LABELS));
        }

        if ($operation === 'list_dates_by_specialty') {
            return $this->genericRows($this->recordsFromData($data, ['fechas', 'dias', 'data', 'items', 'result']), [
                'id' => ['fecha', 'FECHA', 'id', 'date'],
                'title' => ['label', 'fecha', 'FECHA', 'date'],
                'description' => ['description', 'resumen', 'disponibles', 'cupos'],
            ]);
        }

        if ($operation === 'list_doctors_by_date') {
            $doctors = is_array($data['medicos'] ?? null) ? $data['medicos'] : [];

            return array_values(array_filter(array_map(static function (mixed $doctor): ?array {
                if (!is_array($doctor)) {
                    return null;
                }

                $title = trim((string) ($doctor['nombre'] ?? ''));
                $id = trim((string) ($doctor['trabajador_id'] ?? $doctor['id'] ?? ''));
                if ($title === '' || $id === '') {
                    return null;
                }

                $parts = [];
                $slots = (int) ($doctor['available_slots_count'] ?? 0);
                if ($slots > 0) {
                    $parts[] = $slots . ' horarios';
                }

                $first = trim((string) ($doctor['first_slot_start'] ?? ''));
                $last = trim((string) ($doctor['last_slot_end'] ?? ''));
                if ($first !== '' && $last !== '') {
                    $parts[] = $first . ' a ' . $last;
                }

                return [
                    'id' => $id,
                    'title' => mb_substr($title, 0, 24, 'UTF-8'),
                    'description' => mb_substr(implode(' · ', $parts), 0, 72, 'UTF-8'),
                ];
            }, $doctors)));
        }

        if ($operation === 'list_procedimientos') {
            $resolved = $this->resolveProcedureSelection($action, $context);
            $rows = $this->filterRowsByAllowedIds($this->genericRows($this->recordsFromData($data, ['tipoProcedimientos', 'procedimientos', 'data', 'items', 'result']), [
                'id' => ['procedimiento_id', 'ID_PROCEDIMIENTO', 'id_procedimiento', 'id', 'codigo'],
                'title' => ['procedimiento', 'NOMBRE_PROCEDIMIENTO', 'nombre_procedimiento', 'nombre', 'descripcion'],
                'description' => ['tipo', 'area', 'departamento'],
            ]), $resolved['allowed_ids'] ?? $this->allowedIds($action, ['allowed_procedimiento_ids', 'allowed_procedimientos'], self::DEFAULT_ALLOWED_PROCEDIMIENTO_IDS));

            return $this->applyRowTitleAliases($rows, $this->titleAliases($action, ['procedimiento_labels', 'procedimiento_titles'], self::DEFAULT_PROCEDIMIENTO_LABELS));
        }

        if ($operation === 'list_days') {
            return $this->genericRows($this->recordsFromData($data, ['dias', 'fechas', 'data', 'items', 'result']), [
                'id' => ['FECHA', 'fecha', 'date', 'dia', 'id'],
                'title' => ['FECHA', 'fecha', 'date', 'dia', 'label'],
                'description' => ['disponibles', 'cupos', 'descripcion'],
            ]);
        }

        if ($operation === 'list_times') {
            return $this->genericRows($this->recordsFromData($data, ['horarios', 'times', 'data', 'items', 'result']), [
                'id' => ['fecha_inicio', 'FECHA_INICIO', 'inicio', 'hora', 'id'],
                'title' => ['hora', 'HORA', 'inicio', 'fecha_inicio', 'FECHA_INICIO', 'label'],
                'description' => ['consultorio', 'sede', 'descripcion'],
            ]);
        }

        return [];
    }

    /**
     * @param array<int, array{id: string, title: string, description: string}> $rows
     * @param array<int, string> $allowedIds
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function filterRowsByAllowedIds(array $rows, array $allowedIds): array
    {
        if ($allowedIds === []) {
            return $rows;
        }

        $allowed = array_fill_keys($allowedIds, true);

        return array_values(array_filter($rows, static fn (array $row): bool => isset($allowed[(string) $row['id']])));
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @return array{
     *   resolved_cita_tipo:string,
     *   resolved_procedimiento_ids:array<int,string>,
     *   resolved_procedimiento_reason:string,
     *   resolved_last_consulta_at:?string,
     *   resolved_last_surgery_at:?string
     * }
     */
    private function resolvedProcedureMetadata(array $action, array $context): array
    {
        $resolved = $this->resolveProcedureSelection($action, $context);

        return [
            'resolved_cita_tipo' => $resolved['rule'] ?? 'sin_clasificacion',
            'resolved_procedimiento_ids' => $resolved['allowed_ids'] ?? self::DEFAULT_ALLOWED_PROCEDIMIENTO_IDS,
            'resolved_procedimiento_reason' => $resolved['reason'] ?? 'fallback_default',
            'resolved_last_consulta_at' => isset($resolved['last_consulta_at']) ? $resolved['last_consulta_at']->toDateTimeString() : null,
            'resolved_last_surgery_at' => isset($resolved['last_surgery_at']) ? $resolved['last_surgery_at']->toDateTimeString() : null,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @return array{
     *   rule:string,
     *   allowed_ids:array<int,string>,
     *   reason:string,
     *   last_consulta_at:?Carbon,
     *   last_surgery_at:?Carbon
     * }
     */
    private function resolveProcedureSelection(array $action, array $context): array
    {
        $identifier = $this->patientIdentifierFromContext($action, $context);
        if ($identifier === '') {
            return [
                'rule' => 'fallback_default',
                'allowed_ids' => $this->allowedIds($action, ['allowed_procedimiento_ids', 'allowed_procedimientos'], self::DEFAULT_ALLOWED_PROCEDIMIENTO_IDS),
                'reason' => 'missing_identifier',
                'last_consulta_at' => null,
                'last_surgery_at' => null,
            ];
        }

        $lastSurgeryAt = $this->latestSurgeryAt($identifier);
        if ($lastSurgeryAt !== null && $lastSurgeryAt->greaterThanOrEqualTo(now()->subDays(30))) {
            return [
                'rule' => 'post_quirurgico',
                'allowed_ids' => [self::PROCEDIMIENTO_POST_QUIRURGICO],
                'reason' => 'recent_surgery_under_30_days',
                'last_consulta_at' => $this->latestConsultaAt($identifier),
                'last_surgery_at' => $lastSurgeryAt,
            ];
        }

        $lastConsultaAt = $this->latestConsultaAt($identifier);
        if ($lastConsultaAt === null) {
            return [
                'rule' => 'nuevo_paciente',
                'allowed_ids' => [self::PROCEDIMIENTO_NUEVO_PACIENTE],
                'reason' => 'no_prior_consultation_found',
                'last_consulta_at' => null,
                'last_surgery_at' => $lastSurgeryAt,
            ];
        }

        if ($lastConsultaAt->greaterThanOrEqualTo(now()->subDays(30))) {
            return [
                'rule' => 'control',
                'allowed_ids' => [self::PROCEDIMIENTO_CONTROL],
                'reason' => 'recent_consultation_under_30_days',
                'last_consulta_at' => $lastConsultaAt,
                'last_surgery_at' => $lastSurgeryAt,
            ];
        }

        return [
            'rule' => 'cita_medica',
            'allowed_ids' => [self::PROCEDIMIENTO_CITA_MEDICA],
            'reason' => 'consultation_over_30_days',
            'last_consulta_at' => $lastConsultaAt,
            'last_surgery_at' => $lastSurgeryAt,
        ];
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     */
    private function patientIdentifierFromContext(array $action, array $context): string
    {
        $candidates = [
            $context['current_identifier'] ?? null,
            $context['cedula'] ?? null,
            $context['identifier'] ?? null,
            data_get($context, 'patient.hc_number'),
            $action['identificacion'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $normalized = $this->normalizeIdentifier((string) $candidate);
            if ($normalized !== '') {
                return $normalized;
            }
        }

        return '';
    }

    private function latestConsultaAt(string $identifier): ?Carbon
    {
        if ($identifier === '' || !$this->tableExists('consulta_data')) {
            return null;
        }

        $value = DB::table('consulta_data')
            ->where('hc_number', $identifier)
            ->whereNotNull('fecha')
            ->where('fecha', '<=', now()->toDateTimeString())
            ->max('fecha');

        return $this->parseDatabaseDate($value);
    }

    private function latestSurgeryAt(string $identifier): ?Carbon
    {
        if ($identifier === '' || !$this->tableExists('protocolo_data')) {
            return null;
        }

        $row = DB::table('protocolo_data')
            ->selectRaw('COALESCE(fecha_inicio, fecha) AS surgery_at')
            ->where('hc_number', $identifier)
            ->where(function ($query): void {
                $query->whereNotNull('fecha_inicio')
                    ->orWhereNotNull('fecha');
            })
            ->whereRaw('COALESCE(fecha_inicio, fecha) <= ?', [now()->toDateTimeString()])
            ->orderByRaw('COALESCE(fecha_inicio, fecha) DESC')
            ->first();

        return $this->parseDatabaseDate($row->surgery_at ?? null);
    }

    private function parseDatabaseDate(mixed $value): ?Carbon
    {
        if (!$value instanceof \DateTimeInterface && (!is_string($value) || trim($value) === '')) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeIdentifier(string $value): string
    {
        return preg_replace('/\D+/', '', trim($value)) ?? '';
    }

    /**
     * @param array<string, mixed> $action
     * @param array<int, string> $keys
     * @param array<int, string> $default
     * @return array<int, string>
     */
    private function allowedIds(array $action, array $keys, array $default): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $action)) {
                continue;
            }

            $value = $action[$key];
            if (is_array($value)) {
                return array_values(array_filter(array_map(
                    static fn (mixed $item): string => trim((string) $item),
                    $value
                ), static fn (string $item): bool => $item !== ''));
            }

            if (is_string($value)) {
                return array_values(array_filter(array_map(
                    static fn (string $item): string => trim($item),
                    explode(',', $value)
                ), static fn (string $item): bool => $item !== ''));
            }
        }

        return $default;
    }

    /**
     * @param array<int, array{id: string, title: string, description: string}> $rows
     * @param array<string, string> $aliases
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function applyRowTitleAliases(array $rows, array $aliases): array
    {
        if ($aliases === []) {
            return $rows;
        }

        return array_values(array_map(static function (array $row) use ($aliases): array {
            $alias = trim((string) ($aliases[(string) $row['id']] ?? ''));
            if ($alias !== '') {
                $row['title'] = mb_substr($alias, 0, 24, 'UTF-8');
            }

            return $row;
        }, $rows));
    }

    /**
     * @param array<string, mixed> $action
     * @param array<int, string> $keys
     * @param array<string, string> $default
     * @return array<string, string>
     */
    private function titleAliases(array $action, array $keys, array $default): array
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $action)) {
                continue;
            }

            $value = $action[$key];
            if (is_array($value)) {
                $aliases = [];
                foreach ($value as $id => $label) {
                    if (is_scalar($label)) {
                        $aliases[trim((string) $id)] = trim((string) $label);
                    }
                }

                return array_filter($aliases, static fn (string $label): bool => $label !== '');
            }
        }

        return $default;
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
     * @param array<int, mixed> $records
     * @param array{id:array<int,string>,title:array<int,string>,description:array<int,string>} $map
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function genericRows(array $records, array $map): array
    {
        return array_values(array_filter(array_map(function (mixed $record) use ($map): ?array {
            if (is_scalar($record)) {
                $value = trim((string) $record);
                return $value !== '' ? [
                    'id' => $value,
                    'title' => mb_substr($value, 0, 24, 'UTF-8'),
                    'description' => '',
                ] : null;
            }

            if (!is_array($record)) {
                return null;
            }

            $id = $this->firstRecordValue($record, $map['id']);
            $title = $this->firstRecordValue($record, $map['title']);
            if ($id === '') {
                $id = $title;
            }
            if ($title === '') {
                $title = $id;
            }
            if ($id === '' || $title === '') {
                return null;
            }

            return [
                'id' => $id,
                'title' => mb_substr($title, 0, 24, 'UTF-8'),
                'description' => mb_substr($this->firstRecordValue($record, $map['description']), 0, 72, 'UTF-8'),
            ];
        }, $records)));
    }

    /**
     * @param array<string, mixed> $record
     * @param array<int, string> $keys
     */
    private function firstRecordValue(array $record, array $keys): string
    {
        foreach ($keys as $key) {
            $value = $record[$key] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $action
     */
    private function listBody(array $action, string $operation): string
    {
        $value = trim((string) ($action['prompt'] ?? $action['message_body'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return match ($operation) {
            'list_specialties' => '¿Qué especialidad oftalmológica necesitas?',
            'list_doctors' => 'Elige el médico con el que deseas agendar.',
            'list_doctors_by_name' => 'Selecciona el médico con el que deseas agendar.',
            'list_sedes' => 'Elige la sede para tu cita.',
            'list_sedes_by_doctor' => 'Elige la sede para tu cita.',
            'list_dates_by_specialty' => 'Elige una fecha disponible para tu cita.',
            'list_doctors_by_date' => 'Elige el médico disponible para esa fecha.',
            'list_procedimientos' => 'Elige el procedimiento para tu cita.',
            'list_days' => 'Elige el día disponible para tu cita.',
            'list_times' => 'Elige el horario disponible para tu cita.',
            default => 'Elige una opción para continuar.',
        };
    }

    /**
     * @param array<string, mixed> $action
     */
    private function listButtonText(array $action): string
    {
        $value = trim((string) ($action['list_button_text'] ?? ''));

        return mb_substr($value !== '' ? $value : 'Ver opciones', 0, 20, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $action
     */
    private function listSectionTitle(array $action, string $operation): string
    {
        $value = trim((string) ($action['list_section_title'] ?? ''));
        if ($value !== '') {
            return mb_substr($value, 0, 24, 'UTF-8');
        }

        return match ($operation) {
            'list_specialties' => 'Especialidades',
            'list_doctors' => 'Médicos disponibles',
            'list_doctors_by_name' => 'Médicos encontrados',
            'list_sedes' => 'Sedes',
            'list_sedes_by_doctor' => 'Sedes disponibles',
            'list_dates_by_specialty' => 'Fechas disponibles',
            'list_doctors_by_date' => 'Médicos disponibles',
            'list_procedimientos' => 'Procedimientos',
            'list_days' => 'Días disponibles',
            'list_times' => 'Horarios',
            default => 'Opciones',
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(string $method, string $endpoint, array $payload): Response
    {
        $timeout = max(5, (int) config('whatsapp.sigcenter.timeout', 25));
        $pending = Http::timeout($timeout)
            ->withoutVerifying()
            ->acceptJson()
            ->withHeaders(['Expect' => '']);

        if ($method === 'POST_FORM') {
            return $pending->asForm()->post($endpoint, $payload);
        }

        if ($method === 'GET') {
            // Sigcenter documents these lookups as GET, but its Yii controller reads
            // request body params instead of query-string params.
            return $pending->send('GET', $endpoint, ['json' => $payload]);
        }

        return $pending->send($method, $endpoint, ['json' => $payload]);
    }

    /**
     * @param array<string, mixed>|null $fallback
     * @return array<string, mixed>
     */
    private function responsePayload(Response $response, string $method, ?array $fallback): array
    {
        $json = null;
        try {
            $decoded = $response->json();
            $json = is_array($decoded) ? $decoded : null;
        } catch (\Throwable) {
            $json = null;
        }

        $payload = [
            'ok' => $response->successful(),
            'http_code' => $response->status(),
            'attempted_method' => $method,
            'data' => $json,
            'raw' => $json === null ? $response->body() : null,
            'error' => $response->successful() ? null : $this->extractError($json, $response),
        ];

        if ($fallback !== null) {
            $payload['fallback'] = $fallback;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed>|null $json
     */
    private function extractError(?array $json, Response $response): string
    {
        foreach (['error', 'message', 'msj'] as $key) {
            $value = $json[$key] ?? null;
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return 'Sigcenter respondió HTTP ' . $response->status();
    }

    private function normalizeOperation(string $operation): string
    {
        return match ($operation) {
            'especialidades', 'specialties', 'list_specialties' => 'list_specialties',
            'medicos', 'doctors', 'list_doctors' => 'list_doctors',
            'buscar_medicos', 'search_doctors', 'list_doctors_by_name' => 'list_doctors_by_name',
            'sedes', 'list_sedes' => 'list_sedes',
            'sedes_por_medico', 'doctor_sedes', 'list_sedes_by_doctor' => 'list_sedes_by_doctor',
            'fechas_especialidad', 'dates_by_specialty', 'list_dates_by_specialty' => 'list_dates_by_specialty',
            'medicos_por_fecha', 'doctors_by_date', 'list_doctors_by_date' => 'list_doctors_by_date',
            'procedimientos', 'list_procedimientos' => 'list_procedimientos',
            'dias', 'days', 'list_days' => 'list_days',
            'horarios', 'times', 'list_times' => 'list_times',
            'agendar', 'book', 'book_appointment' => 'book_appointment',
            'cancelar', 'cancel', 'cancel_appointment' => 'cancel_appointment',
            'check_pending_appointment', 'verificar_cita' => 'check_pending_appointment',
            default => 'list_days',
        };
    }

    /**
     * @param array<string, mixed> $action
     */
    private function inferOperationFromAction(string $operation, array $action): string
    {
        if ($operation !== 'list_specialties') {
            return $operation;
        }

        $saveResponseAs = trim((string) ($action['save_response_as'] ?? ''));
        $storeResultAs = trim((string) ($action['store_result_as'] ?? ''));
        $nextState = trim((string) ($action['next_state'] ?? ''));

        if (
            $storeResultAs === 'agenda_medicos_busqueda'
            || str_contains($storeResultAs, 'medicos_busqueda')
            || str_contains($nextState, 'doctor_directo')
        ) {
            return 'list_doctors_by_name';
        }

        if (
            $storeResultAs === 'sigcenter_sedes_doctor'
            || str_contains($storeResultAs, 'sedes_doctor')
            || str_contains($nextState, 'sede_directa')
        ) {
            return 'list_sedes_by_doctor';
        }

        if (
            $storeResultAs === 'agenda_medicos_fecha'
            || str_contains($storeResultAs, 'medicos_fecha')
            || str_contains($nextState, 'medico_general_por_fecha')
            || ($saveResponseAs === 'trabajador_id' && str_contains($nextState, 'medico'))
        ) {
            return 'list_doctors_by_date';
        }

        if (
            $saveResponseAs === 'fecha'
            || str_contains($storeResultAs, 'agenda_fechas')
            || str_contains($nextState, 'fecha_general')
        ) {
            return 'list_dates_by_specialty';
        }

        return $operation;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    private function buildPayload(string $operation, array $action, array $context, array $input): array
    {
        $companyId = $this->value($action, $context, $input, 'company_id', self::COMPANY_ID);
        $trabajadorId = $this->selectedValue($action, $context, $input, 'trabajador_id');
        $sedeId = $this->selectedValue(
            $action,
            $context,
            $input,
            'sede_id',
            $this->selectedValue($action, $context, $input, 'ID_SEDE', 3)
        );

        if ($operation === 'cancel_appointment') {
            return [
                'company_id' => (int) $companyId,
                'agenda_id' => $this->selectedValue($action, $context, $input, 'agenda_id'),
                'motivo' => (string) $this->value($action, $context, $input, 'motivo', 'Solicitado por paciente vía WhatsApp'),
            ];
        }

        if ($operation === 'list_specialties') {
            return [
                'especialidad' => (string) $this->value($action, $context, $input, 'especialidad', 'Cirujano Oftalmólogo'),
            ];
        }

        if ($operation === 'list_doctors') {
            return [
                'especialidad' => (string) $this->value($action, $context, $input, 'especialidad', 'Cirujano Oftalmólogo'),
                'subespecialidad' => (string) $this->value($action, $context, $input, 'subespecialidad'),
                'sede_id' => (string) $this->selectedValue($action, $context, $input, 'sede_id', ''),
            ];
        }

        if ($operation === 'list_doctors_by_name') {
            return [
                'doctor_query' => (string) $this->value($action, $context, $input, 'doctor_query'),
                'sede_id' => (string) $this->selectedValue($action, $context, $input, 'sede_id', ''),
            ];
        }

        if ($operation === 'list_sedes') {
            return [
                'especialidad' => (string) $this->value($action, $context, $input, 'especialidad', 'Cirujano Oftalmólogo'),
                'subespecialidad' => (string) $this->value($action, $context, $input, 'subespecialidad'),
            ];
        }

        if ($operation === 'list_sedes_by_doctor') {
            return [
                'trabajador_id' => $trabajadorId,
            ];
        }

        if ($operation === 'list_dates_by_specialty') {
            return [
                'sede_id' => (string) $sedeId,
                'subespecialidad' => (string) $this->value($action, $context, $input, 'subespecialidad', 'oftalmologo general'),
            ];
        }

        if ($operation === 'list_doctors_by_date') {
            return [
                'sede_id' => (string) $sedeId,
                'subespecialidad' => (string) $this->value($action, $context, $input, 'subespecialidad', 'oftalmologo general'),
                'fecha' => (string) $this->selectedValue(
                    $action,
                    $context,
                    $input,
                    'fecha',
                    $this->selectedValue($action, $context, $input, 'FECHA')
                ),
            ];
        }

        if ($operation === 'list_procedimientos') {
            return [
                'company_id' => (string) $companyId,
                'trabajador_id' => $trabajadorId,
            ];
        }

        if ($operation === 'list_days') {
            return [
                'company_id' => (string) $companyId,
                'ID_SEDE' => (string) $sedeId,
                'trabajador_id' => $trabajadorId,
            ];
        }

        if ($operation === 'list_times') {
            return [
                'company_id' => (string) $companyId,
                'ID_SEDE' => (string) $sedeId,
                'trabajador_id' => $trabajadorId,
                'FECHA' => $this->selectedValue(
                    $action,
                    $context,
                    $input,
                    'fecha',
                    $this->selectedValue($action, $context, $input, 'FECHA')
                ),
            ];
        }

        return [
            'company_id' => (int) $companyId,
            'ID_SEDE' => (string) $sedeId,
            'action' => strtoupper((string) $this->value($action, $context, $input, 'action', 'CREATE')),
            'agenda_id' => (string) $this->value($action, $context, $input, 'agenda_id', ''),
            'estado_pago' => (string) $this->value($action, $context, $input, 'estado_pago', ''),
            'identificacion' => $this->selectedValue(
                $action,
                $context,
                $input,
                'identificacion',
                $this->selectedValue(
                    $action,
                    $context,
                    $input,
                    'current_identifier',
                    $this->selectedValue(
                        $action,
                        $context,
                        $input,
                        'hc_number',
                        $this->selectedValue($action, $context, $input, 'cedula', $context['identifier'] ?? '')
                    )
                )
            ),
            'trabajador_id' => $trabajadorId,
            'procedimiento_id' => $this->selectedValue($action, $context, $input, 'procedimiento_id'),
            'fecha_inicio' => $this->appointmentStart($action, $context, $input),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<int, string>
     */
    private function missingFields(string $operation, array $payload): array
    {
        $required = match ($operation) {
            'list_specialties' => [],
            'list_doctors' => ['subespecialidad'],
            'list_doctors_by_name' => ['doctor_query'],
            'list_sedes' => ['subespecialidad'],
            'list_sedes_by_doctor' => ['trabajador_id'],
            'list_dates_by_specialty' => ['subespecialidad', 'sede_id'],
            'list_doctors_by_date' => ['subespecialidad', 'sede_id', 'fecha'],
            'list_procedimientos' => ['trabajador_id'],
            'list_days' => ['trabajador_id', 'ID_SEDE'],
            'list_times' => ['trabajador_id', 'ID_SEDE', 'FECHA'],
            'book_appointment' => ['identificacion', 'trabajador_id', 'procedimiento_id', 'fecha_inicio', 'ID_SEDE'],
            'cancel_appointment' => ['company_id', 'agenda_id', 'motivo'],
            default => [],
        };

        return array_values(array_filter($required, static function (string $key) use ($payload): bool {
            return !array_key_exists($key, $payload) || $payload[$key] === null || $payload[$key] === '';
        }));
    }

    private function endpoint(string $operation): string
    {
        return match ($operation) {
            'list_specialties' => 'local://users/subespecialidades',
            'list_doctors' => 'local://users/doctores-por-subespecialidad',
            'list_doctors_by_name' => 'local://users/doctores-por-nombre',
            'list_sedes' => 'local://users/sedes-por-subespecialidad',
            'list_sedes_by_doctor' => 'local://users/sedes-por-doctor',
            'list_dates_by_specialty' => 'local://availability/fechas-por-especialidad',
            'list_doctors_by_date' => 'local://availability/doctores-por-fecha',
            'list_procedimientos' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/procedimiento-doctor-crm',
            'list_days' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/horarios-disponibles-dias',
            'list_times' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/horarios-disponibles-especifico',
            'book_appointment' => 'https://sigcenter.ddns.net:18093/restful/api-eva/agendar-facturar',
            'cancel_appointment' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/cancelar-cita',
            default => '',
        };
    }

    private function method(string $operation): string
    {
        if (in_array($operation, ['list_specialties', 'list_doctors', 'list_sedes', 'list_doctors_by_name', 'list_sedes_by_doctor', 'list_dates_by_specialty', 'list_doctors_by_date'], true)) {
            return 'LOCAL_DB';
        }

        return in_array($operation, ['book_appointment', 'cancel_appointment'], true) ? 'POST' : 'GET_JSON_WITH_POST_FALLBACK';
    }

    private function operationLabel(string $operation): string
    {
        return match ($operation) {
            'list_specialties' => 'Listar especialidades disponibles',
            'list_doctors' => 'Listar médicos por especialidad',
            'list_doctors_by_name' => 'Buscar médicos por nombre',
            'list_sedes' => 'Consultar sedes disponibles',
            'list_sedes_by_doctor' => 'Consultar sedes disponibles del médico',
            'list_dates_by_specialty' => 'Consultar fechas disponibles por sede y especialidad',
            'list_doctors_by_date' => 'Consultar médicos disponibles por fecha',
            'list_procedimientos' => 'Consultar procedimientos del doctor',
            'list_days' => 'Consultar días disponibles',
            'list_times' => 'Consultar horarios de un día',
            'book_appointment' => 'Crear agendamiento en Sigcenter',
            'cancel_appointment' => 'Cancelar cita en Sigcenter',
            default => 'Acción Sigcenter',
        };
    }

    /**
     * @param array<string, mixed> $action
     */
    private function storeResultAs(array $action, string $operation): string
    {
        $value = trim((string) ($action['store_result_as'] ?? ''));
        if ($value !== '') {
            return $value;
        }

        return match ($operation) {
            'list_specialties' => 'agenda_especialidades',
            'list_doctors' => 'agenda_medicos',
            'list_doctors_by_name' => 'agenda_medicos_busqueda',
            'list_sedes' => 'sigcenter_sedes',
            'list_sedes_by_doctor' => 'sigcenter_sedes_doctor',
            'list_dates_by_specialty' => 'agenda_fechas_general',
            'list_doctors_by_date' => 'agenda_medicos_fecha',
            'list_procedimientos' => 'sigcenter_procedimientos',
            'list_days' => 'sigcenter_dias',
            'list_times' => 'sigcenter_horarios',
            'book_appointment' => 'sigcenter_agenda',
            default => 'sigcenter_result',
        };
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     */
    private function value(array $action, array $context, array $input, string $key, mixed $default = ''): mixed
    {
        if (array_key_exists($key, $action) && $action[$key] !== null && $action[$key] !== '') {
            return $action[$key];
        }

        $contextKey = $action[$key . '_context_key'] ?? null;
        if (is_string($contextKey) && $contextKey !== '' && array_key_exists($contextKey, $context)) {
            return $context[$contextKey];
        }

        $inputKey = $action[$key . '_input_key'] ?? null;
        if (is_string($inputKey) && $inputKey !== '' && array_key_exists($inputKey, $input)) {
            return $input[$inputKey];
        }

        if (array_key_exists($key, $context)) {
            return $context[$key];
        }

        if (array_key_exists($key, $input)) {
            return $input[$key];
        }

        return $default;
    }

    /**
     * Runtime selections captured from WhatsApp must win over builder defaults.
     *
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     */
    private function selectedValue(array $action, array $context, array $input, string $key, mixed $default = ''): mixed
    {
        if (array_key_exists($key, $context) && $context[$key] !== null && $context[$key] !== '') {
            return $context[$key];
        }

        if (array_key_exists($key, $input) && $input[$key] !== null && $input[$key] !== '') {
            return $input[$key];
        }

        $contextKey = $action[$key . '_context_key'] ?? null;
        if (is_string($contextKey) && $contextKey !== '' && array_key_exists($contextKey, $context)) {
            return $context[$contextKey];
        }

        $inputKey = $action[$key . '_input_key'] ?? null;
        if (is_string($inputKey) && $inputKey !== '' && array_key_exists($inputKey, $input)) {
            return $input[$inputKey];
        }

        if (array_key_exists($key, $action) && $action[$key] !== null && $action[$key] !== '') {
            return $action[$key];
        }

        return $default;
    }

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     */
    private function appointmentStart(array $action, array $context, array $input): string
    {
        $value = trim((string) $this->selectedValue($action, $context, $input, 'fecha_inicio'));
        if ($value === '') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}/', $value) === 1) {
            return $value;
        }

        $date = trim((string) $this->selectedValue(
            $action,
            $context,
            $input,
            'fecha',
            $this->selectedValue($action, $context, $input, 'FECHA')
        ));
        if ($date === '') {
            return $value;
        }

        $start = trim(explode('-', $value, 2)[0]);

        return trim($date . ' ' . $start);
    }

    /**
     * @param array<string, mixed> $preview
     * @return array<string, mixed>
     */
    private function executeLocalCatalog(array $preview): array
    {
        $operation = (string) ($preview['operation'] ?? '');
        $payload = is_array($preview['payload'] ?? null) ? $preview['payload'] : [];

        if ($operation === 'list_specialties') {
            return [
                'ok' => true,
                'http_code' => 200,
                'attempted_method' => 'LOCAL_DB',
                'data' => [
                    'especialidades' => $this->listSpecialties((string) ($payload['especialidad'] ?? 'Cirujano Oftalmólogo')),
                ],
                'raw' => null,
                'error' => null,
            ];
        }

        if ($operation === 'list_sedes') {
            return [
                'ok' => true,
                'http_code' => 200,
                'attempted_method' => 'LOCAL_DB',
                'data' => [
                    'sedes' => $this->listSedes(
                        (string) ($payload['especialidad'] ?? 'Cirujano Oftalmólogo'),
                        (string) ($payload['subespecialidad'] ?? '')
                    ),
                ],
                'raw' => null,
                'error' => null,
            ];
        }

        if ($operation === 'list_sedes_by_doctor') {
            return [
                'ok' => true,
                'http_code' => 200,
                'attempted_method' => 'LOCAL_DB',
                'data' => [
                    'sedes' => $this->listSedesByDoctor((string) ($payload['trabajador_id'] ?? '')),
                ],
                'raw' => null,
                'error' => null,
            ];
        }

        if ($operation === 'list_dates_by_specialty') {
            return [
                'ok' => true,
                'http_code' => 200,
                'attempted_method' => 'LOCAL_DB',
                'data' => [
                    'fechas' => $this->listAvailableDatesBySedeAndSpecialty(
                        (string) ($payload['sede_id'] ?? ''),
                        (string) ($payload['subespecialidad'] ?? '')
                    ),
                ],
                'raw' => null,
                'error' => null,
            ];
        }

        if ($operation === 'list_doctors_by_date') {
            return [
                'ok' => true,
                'http_code' => 200,
                'attempted_method' => 'LOCAL_DB',
                'data' => [
                    'medicos' => $this->listAvailableDoctorsByDate(
                        (string) ($payload['sede_id'] ?? ''),
                        (string) ($payload['subespecialidad'] ?? ''),
                        (string) ($payload['fecha'] ?? '')
                    ),
                ],
                'raw' => null,
                'error' => null,
            ];
        }

        if ($operation === 'list_doctors_by_name') {
            return [
                'ok' => true,
                'http_code' => 200,
                'attempted_method' => 'LOCAL_DB',
                'data' => [
                    'medicos' => $this->searchDoctorsByName(
                        (string) ($payload['doctor_query'] ?? ''),
                        (string) ($payload['sede_id'] ?? '')
                    ),
                ],
                'raw' => null,
                'error' => null,
            ];
        }

        return [
            'ok' => true,
            'http_code' => 200,
            'attempted_method' => 'LOCAL_DB',
            'data' => [
                'medicos' => $this->listDoctors(
                    (string) ($payload['especialidad'] ?? 'Cirujano Oftalmólogo'),
                    (string) ($payload['subespecialidad'] ?? ''),
                    (string) ($payload['sede_id'] ?? '')
                ),
            ],
            'raw' => null,
            'error' => null,
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function listSpecialties(string $especialidad): array
    {
        $available = [];

        if ($this->doctorCatalogAvailable()) {
            $available = DB::table(self::DOCTOR_CATALOG_TABLE)
                ->where('active', true)
                ->whereNotNull('subespecialidad')
                ->where('subespecialidad', '<>', '')
                ->distinct()
                ->orderBy('subespecialidad')
                ->pluck('subespecialidad')
                ->map(static fn (mixed $value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        } else {
            $available = DB::table('users')
                ->where(function ($query) use ($especialidad): void {
                    $query->where('especialidad', $especialidad)
                        ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                        ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
                })
                ->whereNotNull('id_trabajador')
                ->whereNotNull('subespecialidad')
                ->where('subespecialidad', '<>', '')
                ->distinct()
                ->orderBy('subespecialidad')
                ->pluck('subespecialidad')
                ->map(static fn (mixed $value): string => trim((string) $value))
                ->filter(static fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        return $this->buildSpecialtyCatalog($available);
    }

    /**
     * @param array<int, string> $available
     * @return array<int, array<string, string>>
     */
    private function buildSpecialtyCatalog(array $available): array
    {
        $available = array_values(array_filter(array_map(
            static fn (mixed $value): string => trim((string) $value),
            $available
        ), static fn (string $value): bool => $value !== ''));

        $availableByKey = [];
        foreach ($available as $value) {
            $availableByKey[$this->specialtyKey($value)] = $value;
        }

        $catalog = [];
        $seen = [];

        foreach ($this->preferredSpecialtyOrder() as $preferred) {
            $value = $availableByKey[$this->specialtyKey($preferred)] ?? null;
            if ($value === null) {
                continue;
            }

            $meta = $this->specialtyDisplayMeta($value);
            $dedupeKey = $this->specialtyKey((string) $meta['title']);
            if ($dedupeKey === '' || isset($seen[$dedupeKey])) {
                continue;
            }

            $catalog[] = [
                'id' => $value,
                'subespecialidad' => $value,
                'title' => $meta['title'],
                'nombre' => $meta['title'],
                'description' => $meta['description'],
                'descripcion' => $meta['description'],
            ];
            $seen[$dedupeKey] = true;
        }

        foreach ($available as $value) {
            $meta = $this->specialtyDisplayMeta($value);
            $dedupeKey = $this->specialtyKey((string) $meta['title']);
            if ($dedupeKey === '' || isset($seen[$dedupeKey])) {
                continue;
            }

            $catalog[] = [
                'id' => $value,
                'subespecialidad' => $value,
                'title' => $meta['title'],
                'nombre' => $meta['title'],
                'description' => $meta['description'],
                'descripcion' => $meta['description'],
            ];
            $seen[$dedupeKey] = true;
        }

        return $catalog;
    }

    private function specialtyDisplayTitle(string $value): string
    {
        return $this->specialtyDisplayMeta($value)['title'];
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

    private function formatDoctorRowTitle(string $fullName): string
    {
        $fullName = trim($fullName);
        if ($fullName === '') {
            return '';
        }

        if (mb_strlen($fullName, 'UTF-8') <= 24) {
            return $fullName;
        }

        $parts = array_values(array_filter(preg_split('/\s+/', $fullName) ?: [], static fn (string $part): bool => $part !== ''));
        if ($parts === []) {
            return mb_substr($fullName, 0, 24, 'UTF-8');
        }

        $partCount = count($parts);

        if ($partCount >= 4) {
            $secondSurname = $parts[$partCount - 1];
            $principalSurnameEnd = $partCount - 2;
            $principalSurnameStart = $principalSurnameEnd;

            while ($principalSurnameStart > 0 && $this->isSurnameConnector($parts[$principalSurnameStart - 1] ?? '')) {
                $principalSurnameStart--;
            }

            $givenNames = array_slice($parts, 0, $principalSurnameStart);
            $principalSurnameParts = array_slice($parts, $principalSurnameStart, $principalSurnameEnd - $principalSurnameStart + 1);
            $finalInitial = mb_substr($secondSurname, 0, 1, 'UTF-8') . '.';
        } else {
            $givenNames = array_slice($parts, 0, max(1, $partCount - 1));
            $principalSurnameParts = [$parts[$partCount - 1]];
            $finalInitial = '';
        }

        if ($givenNames === []) {
            return mb_substr($fullName, 0, 24, 'UTF-8');
        }

        $displayNames = array_slice($givenNames, 0, min(2, count($givenNames)));
        $principalSurname = implode(' ', $principalSurnameParts);

        $candidates = array_values(array_filter([
            trim(implode(' ', $displayNames) . ' ' . $principalSurname . ' ' . $finalInitial),
            trim(implode(' ', $displayNames) . ' ' . $principalSurname),
            trim(($displayNames[0] ?? '') . ' ' . $principalSurname . ' ' . $finalInitial),
            trim(($displayNames[0] ?? '') . ' ' . $principalSurname),
            trim(mb_substr((string) ($displayNames[0] ?? ''), 0, 1, 'UTF-8') . '. ' . $principalSurname),
        ], static fn (string $candidate): bool => $candidate !== ''));

        foreach ($candidates as $candidate) {
            if (mb_strlen($candidate, 'UTF-8') <= 24) {
                return $candidate;
            }
        }

        return mb_substr($fullName, 0, 24, 'UTF-8');
    }

    private function isSurnameConnector(string $token): bool
    {
        $token = mb_strtolower(trim($token), 'UTF-8');

        return in_array($token, ['de', 'del', 'de la', 'de los', 'de las', 'la', 'las', 'los'], true);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listDoctors(string $especialidad, string $subespecialidad, string $sedeId = ''): array
    {
        if ($this->doctorCatalogAvailable()) {
            $query = DB::table(self::DOCTOR_CATALOG_TABLE)
                ->select([
                    'source_user_id as id',
                    'doctor_nombre as nombre',
                    'doctor_email as email',
                    'doctor_profile_photo as profile_photo',
                    'especialidad',
                    'subespecialidad',
                    'trabajador_id',
                    'sede_nombre as sede',
                ])
                ->where('active', true)
                ->where('subespecialidad', $subespecialidad);

            if (trim($sedeId) !== '') {
                $query->where('sede_id', $sedeId);
            }

            return $query
                ->orderBy('doctor_nombre')
                ->get()
                ->map(static fn (object $row): array => [
                    'id' => $row->id !== null ? (int) $row->id : 0,
                    'nombre' => (string) ($row->nombre ?? ''),
                    'email' => $row->email,
                    'profile_photo' => $row->profile_photo,
                    'especialidad' => (string) ($row->especialidad ?? ''),
                    'subespecialidad' => (string) ($row->subespecialidad ?? ''),
                    'trabajador_id' => $row->trabajador_id !== null ? (string) $row->trabajador_id : null,
                    'sede' => (string) ($row->sede ?? ''),
                ])
                ->values()
                ->all();
        }

        return DB::table('users')
            ->select(['id', 'nombre', 'email', 'profile_photo', 'especialidad', 'subespecialidad', 'id_trabajador', 'sede'])
            ->where(function ($query) use ($especialidad): void {
                $query->where('especialidad', $especialidad)
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
            })
            ->whereNotNull('id_trabajador')
            ->where('subespecialidad', $subespecialidad)
            ->orderBy('nombre')
            ->get()
            ->filter(fn (object $row): bool => $this->doctorMatchesSede((string) ($row->sede ?? ''), $sedeId))
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'nombre' => (string) ($row->nombre ?? ''),
                'email' => $row->email,
                'profile_photo' => $row->profile_photo,
                'especialidad' => (string) ($row->especialidad ?? ''),
                'subespecialidad' => (string) ($row->subespecialidad ?? ''),
                'trabajador_id' => $row->id_trabajador !== null ? (string) $row->id_trabajador : null,
                'sede' => (string) ($row->sede ?? ''),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function searchDoctorsByName(string $query, string $sedeId = ''): array
    {
        $normalizedQuery = $this->normalizeSearchTerm($query);
        if ($normalizedQuery === '') {
            return [];
        }

        if ($this->doctorCatalogAvailable()) {
            return DB::table(self::DOCTOR_CATALOG_TABLE)
                ->select([
                    'source_user_id as id',
                    'doctor_nombre as nombre',
                    'doctor_email as email',
                    'doctor_profile_photo as profile_photo',
                    'especialidad',
                    'subespecialidad',
                    'trabajador_id',
                    'sede_nombre as sede',
                ])
                ->where('active', true)
                ->when(trim($sedeId) !== '', static fn ($query) => $query->where('sede_id', $sedeId))
                ->get()
                ->map(fn (object $row): array => [
                    'id' => $row->id !== null ? (int) $row->id : 0,
                    'nombre' => (string) ($row->nombre ?? ''),
                    'email' => $row->email,
                    'profile_photo' => $row->profile_photo,
                    'especialidad' => (string) ($row->especialidad ?? ''),
                    'subespecialidad' => (string) ($row->subespecialidad ?? ''),
                    'trabajador_id' => $row->trabajador_id !== null ? (string) $row->trabajador_id : null,
                    'sede' => (string) ($row->sede ?? ''),
                    '_score' => $this->scoreDoctorNameMatch((string) ($row->nombre ?? ''), $normalizedQuery),
                ])
                ->filter(static fn (array $row): bool => (int) ($row['_score'] ?? 0) > 0)
                ->sortByDesc('_score')
                ->unique(fn (array $row): string => (string) ($row['trabajador_id'] ?? ''))
                ->sortBy([
                    ['_score', 'desc'],
                    ['nombre', 'asc'],
                ])
                ->map(static function (array $row): array {
                    unset($row['_score']);

                    return $row;
                })
                ->values()
                ->all();
        }

        return DB::table('users')
            ->select(['id', 'nombre', 'email', 'profile_photo', 'especialidad', 'subespecialidad', 'id_trabajador', 'sede'])
            ->whereNotNull('id_trabajador')
            ->get()
            ->filter(fn (object $row): bool => $this->doctorMatchesSede((string) ($row->sede ?? ''), $sedeId))
            ->map(fn (object $row): array => [
                'id' => (int) $row->id,
                'nombre' => (string) ($row->nombre ?? ''),
                'email' => $row->email,
                'profile_photo' => $row->profile_photo,
                'especialidad' => (string) ($row->especialidad ?? ''),
                'subespecialidad' => (string) ($row->subespecialidad ?? ''),
                'trabajador_id' => $row->id_trabajador !== null ? (string) $row->id_trabajador : null,
                'sede' => (string) ($row->sede ?? ''),
                '_score' => $this->scoreDoctorNameMatch((string) ($row->nombre ?? ''), $normalizedQuery),
            ])
            ->filter(static fn (array $row): bool => (int) ($row['_score'] ?? 0) > 0)
            ->sortByDesc('_score')
            ->unique(fn (array $row): string => (string) ($row['trabajador_id'] ?? ''))
            ->sortBy([
                ['_score', 'desc'],
                ['nombre', 'asc'],
            ])
            ->map(static function (array $row): array {
                unset($row['_score']);

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function listSedesByDoctor(string $trabajadorId): array
    {
        if (trim($trabajadorId) === '') {
            return [];
        }

        if ($this->doctorCatalogAvailable()) {
            return DB::table(self::DOCTOR_CATALOG_TABLE)
                ->select(['sede_id', 'sede_nombre'])
                ->where('active', true)
                ->where('trabajador_id', $trabajadorId)
                ->distinct()
                ->orderBy('sede_nombre')
                ->get()
                ->map(static fn (object $row): array => [
                    'sede_id' => (string) ($row->sede_id ?? ''),
                    'nombre' => (string) ($row->sede_nombre ?? ''),
                ])
                ->filter(static fn (array $row): bool => trim((string) ($row['sede_id'] ?? '')) !== '' && trim((string) ($row['nombre'] ?? '')) !== '')
                ->values()
                ->all();
        }

        return DB::table('users')
            ->select(['sede'])
            ->where('id_trabajador', $trabajadorId)
            ->get()
            ->flatMap(function (object $row): array {
                return collect($this->expandDoctorSedes((string) ($row->sede ?? '')))
                    ->map(fn (string $sedeId): array => [
                        'sede_id' => $sedeId,
                        'nombre' => self::DEFAULT_SEDE_LABELS[$sedeId] ?? $sedeId,
                    ])->all();
            })
            ->unique('sede_id')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function listAvailableDatesBySedeAndSpecialty(string $sedeId, string $subespecialidad): array
    {
        if (!$this->doctorAvailabilityAvailable() || trim($sedeId) === '' || trim($subespecialidad) === '') {
            return [];
        }

        return DB::table(self::DOCTOR_AVAILABILITY_TABLE)
            ->select([
                'fecha',
                DB::raw('SUM(available_slots_count) as total_slots'),
                DB::raw('MIN(first_slot_start) as first_slot_start'),
                DB::raw('MAX(last_slot_end) as last_slot_end'),
            ])
            ->where('active', true)
            ->where('sede_id', $sedeId)
            ->where('subespecialidad', $subespecialidad)
            ->whereDate('fecha', '>=', Carbon::now('America/Guayaquil')->toDateString())
            ->groupBy('fecha')
            ->orderBy('fecha')
            ->get()
            ->map(function (object $row): array {
                $fecha = (string) ($row->fecha ?? '');
                $slots = max(0, (int) ($row->total_slots ?? 0));
                $first = trim((string) ($row->first_slot_start ?? ''));
                $last = trim((string) ($row->last_slot_end ?? ''));
                $descriptionParts = [];

                if ($slots > 0) {
                    $descriptionParts[] = $slots . ' horarios';
                }
                if ($first !== '' && $last !== '') {
                    $descriptionParts[] = $first . ' a ' . $last;
                }

                return [
                    'fecha' => $fecha,
                    'label' => $fecha,
                    'description' => implode(' · ', $descriptionParts),
                ];
            })
            ->filter(static fn (array $row): bool => trim((string) ($row['fecha'] ?? '')) !== '')
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listAvailableDoctorsByDate(string $sedeId, string $subespecialidad, string $fecha): array
    {
        if (!$this->doctorAvailabilityAvailable() || trim($sedeId) === '' || trim($subespecialidad) === '' || trim($fecha) === '') {
            return [];
        }

        return DB::table(self::DOCTOR_AVAILABILITY_TABLE)
            ->select([
                'trabajador_id',
                'doctor_nombre as nombre',
                'especialidad',
                'subespecialidad',
                'sede_id',
                'sede_nombre as sede',
                'available_slots_count',
                'first_slot_start',
                'last_slot_end',
            ])
            ->where('active', true)
            ->where('sede_id', $sedeId)
            ->where('subespecialidad', $subespecialidad)
            ->whereDate('fecha', $fecha)
            ->orderBy('doctor_nombre')
            ->get()
            ->map(static fn (object $row): array => [
                'trabajador_id' => (string) ($row->trabajador_id ?? ''),
                'nombre' => (string) ($row->nombre ?? ''),
                'especialidad' => (string) ($row->especialidad ?? ''),
                'subespecialidad' => (string) ($row->subespecialidad ?? ''),
                'sede_id' => (string) ($row->sede_id ?? ''),
                'sede' => (string) ($row->sede ?? ''),
                'available_slots_count' => (int) ($row->available_slots_count ?? 0),
                'first_slot_start' => (string) ($row->first_slot_start ?? ''),
                'last_slot_end' => (string) ($row->last_slot_end ?? ''),
            ])
            ->filter(static fn (array $row): bool => trim((string) ($row['trabajador_id'] ?? '')) !== '' && trim((string) ($row['nombre'] ?? '')) !== '')
            ->values()
            ->all();
    }

    private function normalizeSearchTerm(string $value): string
    {
        $value = trim(mb_strtolower($value, 'UTF-8'));
        if ($value === '') {
            return '';
        }

        $normalized = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ä', 'ë', 'ï', 'ö', 'ü', 'ñ'],
            ['a', 'e', 'i', 'o', 'u', 'a', 'e', 'i', 'o', 'u', 'n'],
            $value
        );

        $normalized = preg_replace('/[^a-z0-9]+/u', ' ', $normalized) ?? '';

        return trim($normalized);
    }

    private function nameMatchesQuery(string $name, string $normalizedQuery): bool
    {
        return $this->scoreDoctorNameMatch($name, $normalizedQuery) > 0;
    }

    private function scoreDoctorNameMatch(string $name, string $normalizedQuery): int
    {
        $normalizedName = $this->normalizeSearchTerm($name);
        if ($normalizedName === '' || $normalizedQuery === '') {
            return 0;
        }

        $queryTokens = array_values(array_filter(explode(' ', $normalizedQuery)));
        $nameTokens = array_values(array_filter(explode(' ', $normalizedName)));
        if ($queryTokens === [] || $nameTokens === []) {
            return 0;
        }

        $score = 0;

        if ($normalizedName === $normalizedQuery) {
            $score += 1000;
        } elseif (str_contains($normalizedName, $normalizedQuery)) {
            $score += 300;
        }

        foreach ($queryTokens as $queryToken) {
            $tokenScore = 0;

            foreach ($nameTokens as $nameToken) {
                if ($nameToken === $queryToken) {
                    $tokenScore = max($tokenScore, 200);
                    continue;
                }

                if (str_starts_with($nameToken, $queryToken)) {
                    $tokenScore = max($tokenScore, 150);
                    continue;
                }

                if (str_contains($nameToken, $queryToken)) {
                    $tokenScore = max($tokenScore, 110);
                    continue;
                }

                $maxDistance = $this->allowedDoctorTokenDistance($queryToken, $nameToken);
                if ($maxDistance > 0 && levenshtein($queryToken, $nameToken) <= $maxDistance) {
                    $tokenScore = max($tokenScore, 70);
                }
            }

            if ($tokenScore === 0) {
                return 0;
            }

            $score += $tokenScore;
        }

        return $score;
    }

    private function allowedDoctorTokenDistance(string $queryToken, string $nameToken): int
    {
        $length = max(mb_strlen($queryToken, 'UTF-8'), mb_strlen($nameToken, 'UTF-8'));

        if ($length >= 8) {
            return 2;
        }

        if ($length >= 5) {
            return 1;
        }

        return 0;
    }

    /**
     * @return array<int, string>
     */
    private function expandDoctorSedes(string $rawSede): array
    {
        $sedeIds = [];

        foreach ($this->explodeLocalSedeCodes($rawSede) as $code) {
            $id = self::LOCAL_SEDE_CODE_TO_ID[$code] ?? null;
            if ($id !== null) {
                $sedeIds[] = $id;
            }
        }

        return array_values(array_unique($sedeIds));
    }

    private function doctorAvailabilityAvailable(): bool
    {
        return $this->tableExists(self::DOCTOR_AVAILABILITY_TABLE);
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function listSedes(string $especialidad, string $subespecialidad): array
    {
        if ($this->doctorCatalogAvailable()) {
            return DB::table(self::DOCTOR_CATALOG_TABLE)
                ->select(['sede_id as ID_SEDE', 'sede_nombre as NOMBRE'])
                ->where('active', true)
                ->where('subespecialidad', $subespecialidad)
                ->distinct()
                ->orderBy('sede_nombre')
                ->get()
                ->map(static fn (object $row): array => [
                    'ID_SEDE' => trim((string) ($row->ID_SEDE ?? '')),
                    'NOMBRE' => trim((string) ($row->NOMBRE ?? '')),
                ])
                ->filter(static fn (array $row): bool => $row['ID_SEDE'] !== '' && $row['NOMBRE'] !== '')
                ->values()
                ->all();
        }

        $rows = DB::table('users')
            ->select(['sede'])
            ->where(function ($query) use ($especialidad): void {
                $query->where('especialidad', $especialidad)
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
            })
            ->whereNotNull('id_trabajador')
            ->where('subespecialidad', $subespecialidad)
            ->whereNotNull('sede')
            ->where('sede', '<>', '')
            ->pluck('sede');

        $sedeIds = [];
        foreach ($rows as $rawSede) {
            foreach ($this->explodeLocalSedeCodes((string) $rawSede) as $code) {
                $id = self::LOCAL_SEDE_CODE_TO_ID[$code] ?? null;
                if ($id !== null) {
                    $sedeIds[$id] = [
                        'ID_SEDE' => $id,
                        'NOMBRE' => self::DEFAULT_SEDE_LABELS[$id] ?? $code,
                    ];
                }
            }
        }

        return array_values($sedeIds);
    }

    private function doctorMatchesSede(string $rawSede, string $sedeId): bool
    {
        if (trim($sedeId) === '') {
            return true;
        }

        $requiredCode = array_search($sedeId, self::LOCAL_SEDE_CODE_TO_ID, true);
        if (!is_string($requiredCode) || $requiredCode === '') {
            return true;
        }

        return in_array($requiredCode, $this->explodeLocalSedeCodes($rawSede), true);
    }

    /**
     * @return array<int, string>
     */
    private function explodeLocalSedeCodes(string $rawSede): array
    {
        $value = strtoupper(str_replace([' ', '-', '_'], '', trim($rawSede)));
        if ($value === '') {
            return [];
        }

        return match ($value) {
            'CEIBOS' => ['CEIBOS'],
            'VILLACLUB' => ['VILLACLUB'],
            'CEIBOSVILLACLUB' => ['CEIBOS', 'VILLACLUB'],
            default => [],
        };
    }

    private function tableExists(string $table): bool
    {
        if (!isset(self::$tableExistsCache[$table])) {
            self::$tableExistsCache[$table] = Schema::hasTable($table);
        }
        return self::$tableExistsCache[$table];
    }

    private function doctorCatalogAvailable(): bool
    {
        if (!$this->tableExists(self::DOCTOR_CATALOG_TABLE)) {
            return false;
        }

        return DB::table(self::DOCTOR_CATALOG_TABLE)
            ->where('active', true)
            ->limit(1)
            ->exists();
    }

    private function executeCheckPendingAppointment(array $action, array $context): array
    {
        $hcNumber = $this->patientIdentifierFromContext($action, $context);

        $base = [
            'type'                  => 'sigcenter_agenda',
            'operation'             => 'check_pending_appointment',
            'mutates_agenda'        => false,
            'requires_confirmation' => false,
            'preview_only'          => false,
            'executed'              => true,
        ];

        if ($hcNumber === '') {
            return array_merge($base, [
                'found'       => false,
                'send_result' => false,
                'next_state'  => (string) ($action['not_found_next_state'] ?? ''),
            ]);
        }

        try {
            $booking = $this->findActiveWhatsappBooking($hcNumber);
            if ($booking !== null) {
                return array_merge($base, $this->buildFoundResult($action, $booking));
            }

            $projected = $this->findActiveProjectedAppointment($hcNumber);
            if ($projected !== null) {
                return array_merge($base, $this->buildFoundResult($action, $projected));
            }
        } catch (\Throwable $e) {
            Log::warning('whatsapp.check_pending_appointment_error', [
                'hc_number' => $hcNumber,
                'error'     => $e->getMessage(),
            ]);
        }

        return array_merge($base, [
            'found'       => false,
            'send_result' => false,
            'next_state'  => (string) ($action['not_found_next_state'] ?? ''),
        ]);
    }

    private function findActiveWhatsappBooking(string $hcNumber): ?array
    {
        if (!$this->tableExists('whatsapp_sigcenter_bookings')) {
            return null;
        }

        $row = DB::table('whatsapp_sigcenter_bookings')
            ->where('patient_hc_number', $hcNumber)
            ->where('status', 'created')
            ->where('fecha_inicio', '>=', now())
            ->orderBy('fecha_inicio')
            ->first();

        if ($row === null) {
            return null;
        }

        $fechaInicio = Carbon::parse($row->fecha_inicio);
        return [
            'fecha'  => $fechaInicio->format('d/m/Y'),
            'hora'   => $fechaInicio->format('H:i'),
            'medico' => (string) ($row->medico_nombre ?? ''),
            'sede'   => (string) ($row->sede_nombre ?? ''),
        ];
    }

    private function findActiveProjectedAppointment(string $hcNumber): ?array
    {
        if (!$this->tableExists('procedimiento_proyectado')) {
            return null;
        }

        $row = ProcedimientoProyectado::query()
            ->where('hc_number', $hcNumber)
            ->whereRaw("UPPER(procedimiento_proyectado) LIKE 'SERVICIOS OFTALMOLOGICOS GENERALES - SER-OFT%'")
            ->whereBetween('fecha', [now()->startOfDay(), now()->addDays(7)->endOfDay()])
            ->where(function ($q): void {
                $q->whereNull('estado_agenda')
                  ->orWhere('estado_agenda', '!=', 'CANCELADO');
            })
            ->orderBy('fecha')
            ->first();

        if ($row === null) {
            return null;
        }

        return [
            'fecha'  => Carbon::parse($row->fecha)->format('d/m/Y'),
            'hora'   => $row->hora ? Carbon::parse($row->hora)->format('H:i') : '',
            'medico' => (string) ($row->doctor ?? ''),
            'sede'   => (string) ($row->sede_departamento ?? ''),
        ];
    }

    private function buildFoundResult(array $action, array $cita): array
    {
        $template = (string) ($action['found_message'] ?? 'Ya tienes una cita agendada para el {{fecha}} a las {{hora}}.');
        $body = strtr($template, [
            '{{fecha}}'  => $cita['fecha'],
            '{{hora}}'   => $cita['hora'],
            '{{medico}}' => $cita['medico'],
            '{{sede}}'   => $cita['sede'],
        ]);

        return [
            'found'            => true,
            'send_result'      => true,
            'next_state'       => (string) ($action['found_next_state'] ?? ''),
            'outbound_message' => ['type' => 'text', 'body' => $body],
        ];
    }
}
