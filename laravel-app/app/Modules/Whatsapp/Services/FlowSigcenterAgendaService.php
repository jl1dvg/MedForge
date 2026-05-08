<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class FlowSigcenterAgendaService
{
    private const COMPANY_ID = 113;

    /**
     * @param array<string, mixed> $action
     * @param array<string, mixed> $context
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function preview(array $action, array $context, array $input): array
    {
        $operation = $this->normalizeOperation((string) ($action['operation'] ?? 'list_days'));
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
            'mutates_agenda' => $operation === 'book_appointment',
            'requires_confirmation' => $operation === 'book_appointment',
            'store_result_as' => $this->storeResultAs($action, $operation),
            'preview_only' => true,
        ];

        if ($this->shouldSendResult($action) && $missing === [] && in_array($operation, ['list_specialties', 'list_doctors'], true)) {
            $preview = array_merge($preview, $this->executeLocalCatalog($preview), [
                'preview_only' => true,
                'executed' => false,
            ]);
        }

        if ($this->shouldSendResult($action) && $missing === [] && !in_array($operation, ['list_specialties', 'list_doctors'], true)) {
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

        if (in_array($operation, ['list_specialties', 'list_doctors'], true)) {
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

        if ($operation === 'book_appointment' && !$confirmed) {
            return $this->withConversationOutput($action, array_merge($preview, [
                'ok' => false,
                'executed' => false,
                'error' => 'Confirmación requerida antes de crear una cita real.',
            ]));
        }

        $result = $operation === 'book_appointment'
            ? $this->executeBooking($preview)
            : $this->executeLookup($preview);

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
            'list_sedes' => 'sede_id',
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
            'list_sedes' => 'agenda_esperando_sede',
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
        $rows = $this->listRows($operation, is_array($result['data'] ?? null) ? $result['data'] : []);

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
     * @param array<string, mixed> $data
     * @return array<int, array{id: string, title: string, description: string}>
     */
    private function listRows(string $operation, array $data): array
    {
        if ($operation === 'list_specialties') {
            $specialties = is_array($data['especialidades'] ?? null) ? $data['especialidades'] : [];

            return array_values(array_filter(array_map(static function (mixed $specialty): ?array {
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

            return array_values(array_filter(array_map(static function (mixed $doctor): ?array {
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
                    'title' => mb_substr($title, 0, 24, 'UTF-8'),
                    'description' => mb_substr(trim((string) ($doctor['subespecialidad'] ?? '')), 0, 72, 'UTF-8'),
                ];
            }, $doctors)));
        }

        if ($operation === 'list_sedes') {
            return $this->genericRows($this->recordsFromData($data, ['sede', 'sedes', 'data', 'items', 'result']), [
                'id' => ['ID_SEDE', 'id_sede', 'sede_id', 'id', 'codigo'],
                'title' => ['NOMBRE', 'NOMBRE_SEDE', 'sede', 'nombre_sede', 'nombre', 'descripcion'],
                'description' => ['direccion', 'ciudad', 'departamento'],
            ]);
        }

        if ($operation === 'list_procedimientos') {
            return $this->genericRows($this->recordsFromData($data, ['tipoProcedimientos', 'procedimientos', 'data', 'items', 'result']), [
                'id' => ['procedimiento_id', 'ID_PROCEDIMIENTO', 'id_procedimiento', 'id', 'codigo'],
                'title' => ['procedimiento', 'NOMBRE_PROCEDIMIENTO', 'nombre_procedimiento', 'nombre', 'descripcion'],
                'description' => ['tipo', 'area', 'departamento'],
            ]);
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
            'list_sedes' => 'Elige la sede para tu cita.',
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
            'list_sedes' => 'Sedes',
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
            'sedes', 'list_sedes' => 'list_sedes',
            'procedimientos', 'list_procedimientos' => 'list_procedimientos',
            'dias', 'days', 'list_days' => 'list_days',
            'horarios', 'times', 'list_times' => 'list_times',
            'agendar', 'book', 'book_appointment' => 'book_appointment',
            default => 'list_days',
        };
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

        if ($operation === 'list_specialties') {
            return [
                'especialidad' => (string) $this->value($action, $context, $input, 'especialidad', 'Cirujano Oftalmólogo'),
            ];
        }

        if ($operation === 'list_doctors') {
            return [
                'especialidad' => (string) $this->value($action, $context, $input, 'especialidad', 'Cirujano Oftalmólogo'),
                'subespecialidad' => (string) $this->value($action, $context, $input, 'subespecialidad'),
            ];
        }

        if ($operation === 'list_sedes' || $operation === 'list_procedimientos') {
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
            'list_sedes', 'list_procedimientos' => ['trabajador_id'],
            'list_days' => ['trabajador_id', 'ID_SEDE'],
            'list_times' => ['trabajador_id', 'ID_SEDE', 'FECHA'],
            'book_appointment' => ['identificacion', 'trabajador_id', 'procedimiento_id', 'fecha_inicio', 'ID_SEDE'],
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
            'list_sedes' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/sede-departamento',
            'list_procedimientos' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/procedimiento-doctor-crm',
            'list_days' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/horarios-disponibles-dias',
            'list_times' => 'https://sigcenter.ddns.net:18093/restful/api-agenda/horarios-disponibles-especifico',
            'book_appointment' => 'https://sigcenter.ddns.net:18093/restful/api-eva/agendar-facturar',
            default => '',
        };
    }

    private function method(string $operation): string
    {
        if (in_array($operation, ['list_specialties', 'list_doctors'], true)) {
            return 'LOCAL_DB';
        }

        return $operation === 'book_appointment' ? 'POST' : 'GET_JSON_WITH_POST_FALLBACK';
    }

    private function operationLabel(string $operation): string
    {
        return match ($operation) {
            'list_specialties' => 'Listar especialidades disponibles',
            'list_doctors' => 'Listar médicos por especialidad',
            'list_sedes' => 'Consultar sedes disponibles',
            'list_procedimientos' => 'Consultar procedimientos del doctor',
            'list_days' => 'Consultar días disponibles',
            'list_times' => 'Consultar horarios de un día',
            'book_appointment' => 'Crear agendamiento en Sigcenter',
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
            'list_sedes' => 'sigcenter_sedes',
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

        return [
            'ok' => true,
            'http_code' => 200,
            'attempted_method' => 'LOCAL_DB',
            'data' => [
                'medicos' => $this->listDoctors(
                    (string) ($payload['especialidad'] ?? 'Cirujano Oftalmólogo'),
                    (string) ($payload['subespecialidad'] ?? '')
                ),
            ],
            'raw' => null,
            'error' => null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function listSpecialties(string $especialidad): array
    {
        return DB::table('users')
            ->where(function ($query) use ($especialidad): void {
                $query->where('especialidad', $especialidad)
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
            })
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function listDoctors(string $especialidad, string $subespecialidad): array
    {
        return DB::table('users')
            ->select(['id', 'nombre', 'email', 'profile_photo', 'especialidad', 'subespecialidad', 'id_trabajador'])
            ->where(function ($query) use ($especialidad): void {
                $query->where('especialidad', $especialidad)
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMÓLOGO'")
                    ->orWhereRaw("UPPER(TRIM(COALESCE(especialidad, ''))) = 'CIRUJANO OFTALMOLOGO'");
            })
            ->where('subespecialidad', $subespecialidad)
            ->orderBy('nombre')
            ->get()
            ->map(static fn (object $row): array => [
                'id' => (int) $row->id,
                'nombre' => (string) ($row->nombre ?? ''),
                'email' => $row->email,
                'profile_photo' => $row->profile_photo,
                'especialidad' => (string) ($row->especialidad ?? ''),
                'subespecialidad' => (string) ($row->subespecialidad ?? ''),
                'trabajador_id' => $row->id_trabajador !== null ? (string) $row->id_trabajador : null,
            ])
            ->values()
            ->all();
    }
}
