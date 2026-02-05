<?php

namespace Modules\Mail\Services;

use DateTimeImmutable;
use Models\SettingsModel;
use PDO;
use Throwable;

class MailboxService
{
    private const DEFAULT_SOURCES = ['solicitudes', 'examenes', 'cobertura', 'whatsapp', 'tickets'];
    private const PHONE_REPLACEMENTS = ['+', '-', ' ', '(', ')', '.', '_'];

    private PDO $pdo;
    private ?SettingsModel $settingsModel = null;
    /** @var array<string, mixed> */
    private array $config = [];
    /** @var array<string, array{name: string, hc_number?: string}|false> */
    private array $phoneCache = [];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (Throwable $exception) {
            $this->settingsModel = null;
        }
        $this->config = $this->buildConfig();
    }

    /**
     * @param array{
     *     sources?: string|array<int,string>,
     *     limit?: int,
     *     query?: string|null,
     *     contact?: string|null
     * } $filters
     * @return array<int, array<string, mixed>>
     */
    public function getFeed(array $filters = []): array
    {
        if (!$this->config['enabled']) {
            return [];
        }

        $limitValue = $filters['limit'] ?? $this->config['limit'];
        $limit = $this->sanitizeLimit($limitValue);
        $allowedSources = $this->getEnabledSources();
        if ($allowedSources === []) {
            return [];
        }

        $sources = $this->normalizeSources($filters['sources'] ?? null, $allowedSources);
        if ($sources === []) {
            return [];
        }
        $query = isset($filters['query']) ? $this->sanitizeString($filters['query']) : null;
        $contact = isset($filters['contact']) ? $this->sanitizeString($filters['contact']) : null;

        $entries = [];

        if (in_array('solicitudes', $sources, true)) {
            $entries = array_merge($entries, $this->fetchSolicitudNotes($limit));
        }

        if (in_array('examenes', $sources, true)) {
            $entries = array_merge(
                $entries,
                $this->fetchExamenNotes($limit),
                $this->fetchExamenTasks($limit),
                $this->fetchExamenStatusEvents($limit),
                $this->fetchExamenCalendarBlocks($limit),
                $this->fetchExamenMailEvents($limit)
            );
        }

        if (in_array('cobertura', $sources, true)) {
            $entries = array_merge($entries, $this->fetchCoberturaMails($limit));
        }

        if (in_array('tickets', $sources, true)) {
            $entries = array_merge($entries, $this->fetchTicketMessages($limit));
        }

        if (in_array('whatsapp', $sources, true)) {
            $entries = array_merge($entries, $this->fetchWhatsappMessages($limit));
        }

        $sortMode = $this->config['sort'];
        usort(
            $entries,
            static function (array $a, array $b) use ($sortMode): int {
                $comparison = ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);

                return $sortMode === 'oldest' ? -$comparison : $comparison;
            }
        );

        if ($query) {
            $entries = array_values(array_filter(
                $entries,
                fn(array $entry): bool => $this->matchesQuery($entry, $query)
            ));
        }

        if ($contact) {
            $needle = $this->mbLower($contact);
            $entries = array_values(array_filter(
                $entries,
                function (array $entry) use ($needle): bool {
                    $label = $this->mbLower((string) ($entry['contact']['label'] ?? ''));

                    return $needle !== '' && str_contains($label, $needle);
                }
            ));
        }

        return array_slice($entries, 0, $limit);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<int, array<string, mixed>>
     */
    public function getContacts(array $messages): array
    {
        $bucket = [];

        foreach ($messages as $message) {
            $label = $this->sanitizeString($message['contact']['label'] ?? '') ?: 'Contacto sin nombre';
            $channel = $this->sanitizeString($message['contact']['channel'] ?? '') ?: 'Inbox';
            $key = $this->mbLower($label . '|' . $channel);

            if (!isset($bucket[$key])) {
                $bucket[$key] = [
                    'label' => $label,
                    'channel' => $channel,
                    'count' => 0,
                    'last_subject' => null,
                    'last_relative' => null,
                    'last_at' => 0,
                    'patient' => $message['patient'] ?? null,
                    'identifier' => $message['contact']['identifier'] ?? null,
                ];
            }

            $bucket[$key]['count']++;

            $timestamp = $message['timestamp'] ?? 0;
            if ($timestamp > $bucket[$key]['last_at']) {
                $bucket[$key]['last_at'] = $timestamp;
                $bucket[$key]['last_subject'] = $message['subject'] ?? null;
                $bucket[$key]['last_relative'] = $message['relative_time'] ?? null;
                $bucket[$key]['avatar'] = $this->initials($label);
                $bucket[$key]['meta'] = $message['meta'] ?? [];
            }
        }

        usort(
            $bucket,
            static fn(array $a, array $b): int => ($b['last_at'] ?? 0) <=> ($a['last_at'] ?? 0)
        );

        return array_values($bucket);
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, mixed>
     */
    public function getStats(array $messages): array
    {
        $bySource = [];
        $direction = ['incoming' => 0, 'outgoing' => 0];
        $latest = 0;

        foreach ($messages as $message) {
            $source = $message['source'] ?? 'inbox';
            if (!isset($bySource[$source])) {
                $bySource[$source] = [
                    'label' => $message['source_label'] ?? ucfirst($source),
                    'count' => 0,
                ];
            }
            $bySource[$source]['count']++;

            $dir = $message['direction'] ?? null;
            if ($dir && isset($direction[$dir])) {
                $direction[$dir]++;
            }

            $latest = max($latest, $message['timestamp'] ?? 0);
        }

        $folders = [
            [
                'key' => 'inbox',
                'icon' => 'ion ion-ios-email-outline',
                'label' => 'Inbox',
                'count' => count($messages),
            ],
            [
                'key' => 'crm',
                'icon' => 'ion ion-social-buffer',
                'label' => 'CRM (Solicitudes + Exámenes + Cobertura)',
                'count' => ($bySource['solicitudes']['count'] ?? 0)
                    + ($bySource['examenes']['count'] ?? 0)
                    + ($bySource['cobertura']['count'] ?? 0),
            ],
            [
                'key' => 'cobertura',
                'icon' => 'mdi mdi-email-check-outline',
                'label' => 'Cobertura (Email)',
                'count' => $bySource['cobertura']['count'] ?? 0,
            ],
            [
                'key' => 'tickets',
                'icon' => 'ion ion-android-clipboard',
                'label' => 'Tickets',
                'count' => $bySource['tickets']['count'] ?? 0,
            ],
            [
                'key' => 'whatsapp',
                'icon' => 'mdi mdi-whatsapp',
                'label' => 'WhatsApp',
                'count' => $bySource['whatsapp']['count'] ?? 0,
            ],
        ];

        return [
            'total' => count($messages),
            'by_source' => $bySource,
            'direction' => $direction,
            'latest' => $latest,
            'folders' => $folders,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $messages
     * @return array<string, array<int, array{value:string,label:string}>>
     */
    public function buildContextOptions(array $messages): array
    {
        $contexts = [
            'solicitud' => [],
            'examen' => [],
            'ticket' => [],
        ];

        foreach ($messages as $message) {
            $related = $message['related'] ?? null;
            if (!is_array($related)) {
                continue;
            }

            $type = $related['type'] ?? null;
            $id = (int) ($related['id'] ?? 0);

            if (!$type || !isset($contexts[$type]) || $id <= 0) {
                continue;
            }

            $key = $type . ':' . $id;
            if (isset($contexts[$type][$key])) {
                continue;
            }

            $labelParts = [
                $message['subject'] ?? '',
                $message['contact']['label'] ?? '',
            ];

            $label = implode(' • ', array_filter(array_map('trim', $labelParts)));
            $contexts[$type][$key] = [
                'value' => $key,
                'label' => $label !== '' ? $label : strtoupper($type) . ' #' . $id,
            ];
        }

        foreach ($contexts as $type => &$options) {
            $options = array_values(array_slice($options, 0, 25));
        }

        return $contexts;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchSolicitudNotes(int $limit): array
    {
        $sql = <<<SQL
SELECT
    n.id,
    n.nota,
    n.created_at,
    sp.id           AS solicitud_id,
    sp.estado,
    sp.prioridad,
    sp.procedimiento,
    sp.doctor,
    sp.hc_number,
    sp.form_id,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular,
    u.nombre        AS autor_nombre,
    u.profile_photo AS autor_avatar
FROM solicitud_crm_notas n
INNER JOIN solicitud_procedimiento sp ON sp.id = n.solicitud_id
LEFT JOIN patient_data pd ON pd.hc_number = sp.hc_number
LEFT JOIN users u ON u.id = n.autor_id
ORDER BY n.created_at DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapSolicitudRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenNotes(int $limit): array
    {
        $sql = <<<SQL
SELECT
    n.id,
    n.nota,
    n.created_at,
    e.id            AS examen_id,
    e.estado,
    e.prioridad,
    e.examen_nombre,
    e.doctor,
    e.hc_number,
    e.form_id,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular,
    u.nombre        AS autor_nombre,
    u.profile_photo AS autor_avatar
FROM examen_crm_notas n
INNER JOIN consulta_examenes e ON e.id = n.examen_id
LEFT JOIN patient_data pd ON pd.hc_number = e.hc_number
LEFT JOIN users u ON u.id = n.autor_id
ORDER BY n.created_at DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapExamenRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenTasks(int $limit): array
    {
        $sql = <<<SQL
SELECT
    t.id,
    t.title AS titulo,
    t.description AS descripcion,
    t.status AS estado_tarea,
    t.assigned_to,
    t.created_by,
    COALESCE(t.due_date, DATE(t.due_at)) AS due_date,
    t.created_at,
    t.updated_at,
    t.completed_at,
    asignado.nombre AS assigned_name,
    creador.nombre AS created_name,
    e.id AS examen_id,
    e.estado,
    e.prioridad,
    e.examen_nombre,
    e.doctor,
    e.hc_number,
    e.form_id,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular
FROM crm_tasks t
INNER JOIN consulta_examenes e
    ON t.source_module = 'examenes'
   AND t.source_ref_id = CAST(e.id AS CHAR)
LEFT JOIN patient_data pd ON pd.hc_number = e.hc_number
LEFT JOIN users asignado ON asignado.id = t.assigned_to
LEFT JOIN users creador ON creador.id = t.created_by
ORDER BY COALESCE(t.updated_at, t.created_at) DESC, t.id DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapExamenTaskRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenStatusEvents(int $limit): array
    {
        $sql = <<<SQL
SELECT
    l.id,
    l.examen_id,
    l.estado_anterior,
    l.estado_nuevo,
    l.changed_by,
    l.origen,
    l.observacion,
    l.changed_at,
    u.nombre AS changed_by_name,
    e.estado,
    e.prioridad,
    e.examen_nombre,
    e.doctor,
    e.hc_number,
    e.form_id,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular
FROM examen_estado_log l
INNER JOIN consulta_examenes e ON e.id = l.examen_id
LEFT JOIN patient_data pd ON pd.hc_number = e.hc_number
LEFT JOIN users u ON u.id = l.changed_by
ORDER BY l.changed_at DESC, l.id DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapExamenStatusRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenCalendarBlocks(int $limit): array
    {
        $sql = <<<SQL
SELECT
    b.id,
    b.examen_id,
    b.doctor AS bloqueo_doctor,
    b.sala,
    b.fecha_inicio,
    b.fecha_fin,
    b.motivo,
    b.created_by,
    b.created_at,
    u.nombre AS created_by_name,
    e.estado,
    e.prioridad,
    e.examen_nombre,
    e.doctor,
    e.hc_number,
    e.form_id,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular
FROM examen_crm_calendar_blocks b
INNER JOIN consulta_examenes e ON e.id = b.examen_id
LEFT JOIN patient_data pd ON pd.hc_number = e.hc_number
LEFT JOIN users u ON u.id = b.created_by
ORDER BY b.created_at DESC, b.id DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapExamenBlockRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchExamenMailEvents(int $limit): array
    {
        $sql = <<<SQL
SELECT
    eml.id,
    eml.examen_id,
    eml.form_id,
    eml.hc_number,
    eml.to_emails,
    eml.cc_emails,
    eml.subject,
    eml.body_text,
    eml.body_html,
    eml.channel,
    eml.status,
    eml.error_message,
    eml.sent_at,
    eml.created_at,
    eml.sent_by_user_id,
    u.nombre AS autor_nombre,
    e.estado,
    e.prioridad,
    e.examen_nombre,
    e.doctor,
    e.hc_number AS examen_hc_number,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular
FROM examen_mail_log eml
INNER JOIN consulta_examenes e ON e.id = eml.examen_id
LEFT JOIN patient_data pd ON pd.hc_number = COALESCE(eml.hc_number, e.hc_number)
LEFT JOIN users u ON u.id = eml.sent_by_user_id
ORDER BY COALESCE(eml.sent_at, eml.created_at) DESC, eml.id DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapExamenMailRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchCoberturaMails(int $limit): array
    {
        $sql = <<<SQL
SELECT
    sml.id,
    sml.solicitud_id,
    sml.form_id,
    sml.hc_number,
    sml.afiliacion,
    sml.template_key,
    sml.to_emails,
    sml.cc_emails,
    sml.subject,
    sml.body_text,
    sml.body_html,
    sml.status,
    sml.error_message,
    sml.sent_at,
    sml.created_at,
    sp.procedimiento,
    sp.doctor,
    sp.hc_number AS solicitud_hc_number,
    pd.fname,
    pd.mname,
    pd.lname,
    pd.lname2,
    pd.celular,
    u.nombre AS autor_nombre
FROM solicitud_mail_log sml
LEFT JOIN solicitud_procedimiento sp ON sp.id = sml.solicitud_id
LEFT JOIN patient_data pd ON pd.hc_number = COALESCE(sp.hc_number, sml.hc_number)
LEFT JOIN users u ON u.id = sml.sent_by_user_id
ORDER BY COALESCE(sml.sent_at, sml.created_at) DESC, sml.id DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapCoberturaRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchTicketMessages(int $limit): array
    {
        $sql = <<<SQL
SELECT
    m.id,
    m.message,
    m.created_at,
    t.id            AS ticket_id,
    t.subject,
    t.priority,
    t.status,
    reporter.nombre AS reporter_nombre,
    assigned.nombre AS assigned_nombre,
    u.nombre        AS autor_nombre
FROM crm_ticket_messages m
INNER JOIN crm_tickets t ON t.id = m.ticket_id
LEFT JOIN users u ON u.id = m.author_id
LEFT JOIN users reporter ON reporter.id = t.reporter_id
LEFT JOIN users assigned ON assigned.id = t.assigned_to
ORDER BY m.created_at DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapTicketRow($row), $rows);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchWhatsappMessages(int $limit): array
    {
        $sql = <<<SQL
SELECT
    id,
    wa_number,
    direction,
    message_type,
    message_body,
    message_id,
    payload,
    created_at
FROM whatsapp_inbox_messages
ORDER BY id DESC
LIMIT :limit
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable $exception) {
            return [];
        }

        return array_map(fn(array $row): array => $this->mapWhatsappRow($row), $rows);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapSolicitudRow(array $row): array
    {
        $date = $this->parseDate($row['created_at'] ?? null);
        $patient = $this->formatPatient($row);
        $subjectParts = [
            $row['procedimiento'] ?? 'Solicitud',
            $row['doctor'] ? 'Dr(a). ' . $row['doctor'] : null,
        ];
        $subject = implode(' · ', array_filter(array_map('trim', $subjectParts)));

        $meta = array_filter([
            'Estado' => $row['estado'] ?? null,
            'Prioridad' => $row['prioridad'] ?? null,
            'HC' => $row['hc_number'] ?? null,
        ]);

        return [
            'uid' => 'solicitud:' . (int) $row['solicitud_id'] . ':' . (int) $row['id'],
            'source' => 'solicitudes',
            'source_label' => 'Solicitudes CRM',
            'category' => 'Nota CRM',
            'subject' => $subject !== '' ? $subject : 'Solicitud #' . (int) $row['solicitud_id'],
            'snippet' => $this->truncate((string) ($row['nota'] ?? '')),
            'body' => trim((string) ($row['nota'] ?? '')),
            'patient' => $patient,
            'contact' => [
                'label' => $patient['name'],
                'channel' => 'Solicitudes',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'crm' => '/solicitudes/' . (int) $row['solicitud_id'] . '/crm',
            ],
            'channels' => ['CRM', 'Solicitudes'],
            'author' => [
                'name' => $row['autor_nombre'] ?? 'Sistema',
                'initials' => $this->initials($row['autor_nombre'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => null,
            'related' => [
                'type' => 'solicitud',
                'id' => (int) $row['solicitud_id'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapExamenRow(array $row): array
    {
        $date = $this->parseDate($row['created_at'] ?? null);
        $patient = $this->formatPatient($row);
        $subjectParts = [
            $row['examen_nombre'] ?? 'Examen',
            $row['doctor'] ? 'Dr(a). ' . $row['doctor'] : null,
        ];
        $subject = implode(' · ', array_filter(array_map('trim', $subjectParts)));

        $meta = array_filter([
            'Estado' => $row['estado'] ?? null,
            'Prioridad' => $row['prioridad'] ?? null,
            'HC' => $row['hc_number'] ?? null,
        ]);

        return [
            'uid' => 'examen:' . (int) $row['examen_id'] . ':' . (int) $row['id'],
            'source' => 'examenes',
            'source_label' => 'Exámenes CRM',
            'category' => 'Nota CRM',
            'subject' => $subject !== '' ? $subject : 'Examen #' . (int) $row['examen_id'],
            'snippet' => $this->truncate((string) ($row['nota'] ?? '')),
            'body' => trim((string) ($row['nota'] ?? '')),
            'patient' => $patient,
            'contact' => [
                'label' => $patient['name'],
                'channel' => 'Exámenes',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'crm' => '/examenes/' . (int) $row['examen_id'] . '/crm',
            ],
            'channels' => ['CRM', 'Exámenes'],
            'author' => [
                'name' => $row['autor_nombre'] ?? 'Sistema',
                'initials' => $this->initials($row['autor_nombre'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => null,
            'related' => [
                'type' => 'examen',
                'id' => (int) $row['examen_id'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapExamenTaskRow(array $row): array
    {
        $date = $this->parseDate($row['updated_at'] ?? ($row['created_at'] ?? null));
        $patient = $this->formatPatient($row);
        $examenId = (int) ($row['examen_id'] ?? 0);
        $titulo = $this->sanitizeString($row['titulo'] ?? '');
        $descripcion = $this->sanitizeString($row['descripcion'] ?? '');
        $estadoTarea = $this->sanitizeString($row['estado_tarea'] ?? '');
        $dueDate = $this->sanitizeString($row['due_date'] ?? '');
        $examenNombre = $this->sanitizeString($row['examen_nombre'] ?? '');

        $subjectParts = [
            $examenNombre !== '' ? $examenNombre : ('Examen #' . $examenId),
            $titulo !== '' ? 'Tarea: ' . $titulo : 'Tarea CRM',
        ];
        $subject = implode(' · ', array_filter($subjectParts));

        $bodyParts = array_filter([
            $titulo !== '' ? 'Título: ' . $titulo : null,
            $estadoTarea !== '' ? 'Estado: ' . $estadoTarea : null,
            $dueDate !== '' ? 'Fecha límite: ' . $dueDate : null,
            $descripcion !== '' ? 'Descripción: ' . $descripcion : null,
        ]);
        $body = implode("\n", $bodyParts);

        $meta = array_filter([
            'Estado examen' => $row['estado'] ?? null,
            'Prioridad' => $row['prioridad'] ?? null,
            'Estado tarea' => $estadoTarea !== '' ? $estadoTarea : null,
            'Vence' => $dueDate !== '' ? $dueDate : null,
            'Asignado' => $row['assigned_name'] ?? null,
            'Creada por' => $row['created_name'] ?? null,
            'HC' => $row['hc_number'] ?? null,
        ]);

        return [
            'uid' => 'examen-task:' . $examenId . ':' . (int) $row['id'],
            'source' => 'examenes',
            'source_label' => 'Exámenes CRM',
            'category' => 'Tarea CRM',
            'subject' => $subject !== '' ? $subject : 'Tarea de examen',
            'snippet' => $this->truncate($body),
            'body' => $body,
            'patient' => $patient,
            'contact' => [
                'label' => $patient['name'],
                'channel' => 'Exámenes',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'crm' => '/examenes/' . $examenId . '/crm',
            ],
            'channels' => ['CRM', 'Exámenes', 'Tareas'],
            'author' => [
                'name' => $row['created_name'] ?? ($row['assigned_name'] ?? 'Sistema'),
                'initials' => $this->initials($row['created_name'] ?? ($row['assigned_name'] ?? 'Sistema')),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => null,
            'related' => [
                'type' => 'examen',
                'id' => $examenId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapExamenStatusRow(array $row): array
    {
        $date = $this->parseDate($row['changed_at'] ?? null);
        $patient = $this->formatPatient($row);
        $examenId = (int) ($row['examen_id'] ?? 0);
        $estadoAnterior = $this->sanitizeString($row['estado_anterior'] ?? '');
        $estadoNuevo = $this->sanitizeString($row['estado_nuevo'] ?? '');
        $origen = $this->sanitizeString($row['origen'] ?? '');
        $observacion = $this->sanitizeString($row['observacion'] ?? '');
        $examenNombre = $this->sanitizeString($row['examen_nombre'] ?? '');

        $body = implode("\n", array_filter([
            ($estadoAnterior !== '' || $estadoNuevo !== '')
                ? 'Transición: ' . ($estadoAnterior !== '' ? $estadoAnterior : '—') . ' → ' . ($estadoNuevo !== '' ? $estadoNuevo : '—')
                : null,
            $observacion !== '' ? 'Observación: ' . $observacion : null,
        ]));

        $meta = array_filter([
            'Estado anterior' => $estadoAnterior !== '' ? $estadoAnterior : null,
            'Estado nuevo' => $estadoNuevo !== '' ? $estadoNuevo : null,
            'Origen' => $origen !== '' ? $origen : null,
            'HC' => $row['hc_number'] ?? null,
        ]);

        return [
            'uid' => 'examen-status:' . $examenId . ':' . (int) $row['id'],
            'source' => 'examenes',
            'source_label' => 'Exámenes CRM',
            'category' => 'Cambio de estado',
            'subject' => ($examenNombre !== '' ? $examenNombre : ('Examen #' . $examenId)) . ' · Estado actualizado',
            'snippet' => $this->truncate($body),
            'body' => $body,
            'patient' => $patient,
            'contact' => [
                'label' => $patient['name'],
                'channel' => 'Exámenes',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'crm' => '/examenes/' . $examenId . '/crm',
            ],
            'channels' => ['CRM', 'Exámenes', 'Estados'],
            'author' => [
                'name' => $row['changed_by_name'] ?? 'Sistema',
                'initials' => $this->initials($row['changed_by_name'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => null,
            'related' => [
                'type' => 'examen',
                'id' => $examenId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapExamenBlockRow(array $row): array
    {
        $date = $this->parseDate($row['created_at'] ?? ($row['fecha_inicio'] ?? null));
        $patient = $this->formatPatient($row);
        $examenId = (int) ($row['examen_id'] ?? 0);
        $inicio = $this->sanitizeString($row['fecha_inicio'] ?? '');
        $fin = $this->sanitizeString($row['fecha_fin'] ?? '');
        $doctor = $this->sanitizeString($row['bloqueo_doctor'] ?? '');
        $sala = $this->sanitizeString($row['sala'] ?? '');
        $motivo = $this->sanitizeString($row['motivo'] ?? '');
        $examenNombre = $this->sanitizeString($row['examen_nombre'] ?? '');

        $body = implode("\n", array_filter([
            ($inicio !== '' || $fin !== '') ? 'Rango: ' . ($inicio !== '' ? $inicio : '—') . ' → ' . ($fin !== '' ? $fin : '—') : null,
            $doctor !== '' ? 'Doctor: ' . $doctor : null,
            $sala !== '' ? 'Sala: ' . $sala : null,
            $motivo !== '' ? 'Motivo: ' . $motivo : null,
        ]));

        $meta = array_filter([
            'Inicio' => $inicio !== '' ? $inicio : null,
            'Fin' => $fin !== '' ? $fin : null,
            'Doctor' => $doctor !== '' ? $doctor : null,
            'Sala' => $sala !== '' ? $sala : null,
            'Motivo' => $motivo !== '' ? $motivo : null,
            'HC' => $row['hc_number'] ?? null,
        ]);

        return [
            'uid' => 'examen-block:' . $examenId . ':' . (int) $row['id'],
            'source' => 'examenes',
            'source_label' => 'Exámenes CRM',
            'category' => 'Bloqueo agenda',
            'subject' => ($examenNombre !== '' ? $examenNombre : ('Examen #' . $examenId)) . ' · Bloqueo de agenda',
            'snippet' => $this->truncate($body),
            'body' => $body,
            'patient' => $patient,
            'contact' => [
                'label' => $patient['name'],
                'channel' => 'Exámenes',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'crm' => '/examenes/' . $examenId . '/crm',
            ],
            'channels' => ['CRM', 'Exámenes', 'Agenda'],
            'author' => [
                'name' => $row['created_by_name'] ?? 'Sistema',
                'initials' => $this->initials($row['created_by_name'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => null,
            'related' => [
                'type' => 'examen',
                'id' => $examenId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapExamenMailRow(array $row): array
    {
        $createdAt = $row['sent_at'] ?: $row['created_at'] ?? null;
        $date = $this->parseDate($createdAt ? (string) $createdAt : null);
        if (!empty($row['examen_hc_number']) && empty($row['hc_number'])) {
            $row['hc_number'] = $row['examen_hc_number'];
        }
        $patient = $this->formatPatient($row);
        $subject = $this->sanitizeString($row['subject'] ?? '');
        $body = $this->buildCoberturaBody($row);
        $status = $this->sanitizeString($row['status'] ?? '');
        $examenId = (int) ($row['examen_id'] ?? 0);

        $contactLabel = $patient['name'] ?? '';
        if ($contactLabel === '' || $contactLabel === 'Paciente sin nombre') {
            $contactLabel = $this->sanitizeString($row['to_emails'] ?? '');
        }

        $meta = array_filter([
            'Estado' => $status !== '' ? $status : null,
            'Canal' => $row['channel'] ?? null,
            'Para' => $row['to_emails'] ?? null,
            'CC' => $row['cc_emails'] ?? null,
            'Error' => $row['error_message'] ?? null,
        ]);

        return [
            'uid' => 'examen-mail:' . $examenId . ':' . (int) $row['id'],
            'source' => 'examenes',
            'source_label' => 'Exámenes CRM',
            'category' => strtolower($status) === 'failed' ? 'Correo fallido' : 'Correo enviado',
            'subject' => $subject !== '' ? $subject : 'Notificación de examen',
            'snippet' => $this->truncate($body),
            'body' => $body,
            'patient' => $patient,
            'contact' => [
                'label' => $contactLabel !== '' ? $contactLabel : 'Contacto',
                'channel' => 'Exámenes',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'crm' => '/examenes/' . $examenId . '/crm',
            ],
            'channels' => ['Correo', 'Exámenes', 'CRM'],
            'author' => [
                'name' => $row['autor_nombre'] ?? 'Sistema',
                'initials' => $this->initials($row['autor_nombre'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => 'outgoing',
            'related' => [
                'type' => 'examen',
                'id' => $examenId,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapCoberturaRow(array $row): array
    {
        $createdAt = $row['sent_at'] ?: $row['created_at'] ?? null;
        $date = $this->parseDate($createdAt ? (string) $createdAt : null);
        if (!empty($row['solicitud_hc_number']) && empty($row['hc_number'])) {
            $row['hc_number'] = $row['solicitud_hc_number'];
        }
        $patient = $this->formatPatient($row);
        $subject = $this->sanitizeString($row['subject'] ?? '');
        $body = $this->buildCoberturaBody($row);

        $contactLabel = $patient['name'] ?? '';
        if ($contactLabel === '' || $contactLabel === 'Paciente sin nombre') {
            $contactLabel = $this->sanitizeString($row['to_emails'] ?? '');
        }

        $meta = array_filter([
            'Estado' => $row['status'] ?? null,
            'Plantilla' => $row['template_key'] ?? null,
            'Para' => $row['to_emails'] ?? null,
            'CC' => $row['cc_emails'] ?? null,
            'Error' => $row['error_message'] ?? null,
        ]);

        $solicitudId = (int) ($row['solicitud_id'] ?? 0);

        return [
            'uid' => 'cobertura:' . (int) $row['id'],
            'source' => 'cobertura',
            'source_label' => 'Cobertura Email',
            'category' => 'Correo enviado',
            'subject' => $subject !== '' ? $subject : 'Cobertura solicitada',
            'snippet' => $this->truncate($body),
            'body' => $body,
            'patient' => $patient,
            'contact' => [
                'label' => $contactLabel !== '' ? $contactLabel : 'Contacto',
                'channel' => 'Cobertura',
                'identifier' => $patient['hc_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => $solicitudId > 0 ? ['crm' => '/solicitudes/' . $solicitudId . '/crm'] : [],
            'channels' => ['Correo', 'Cobertura'],
            'author' => [
                'name' => $row['autor_nombre'] ?? 'Sistema',
                'initials' => $this->initials($row['autor_nombre'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => 'outgoing',
            'related' => $solicitudId > 0 ? [
                'type' => 'solicitud',
                'id' => $solicitudId,
            ] : null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     */
    private function buildCoberturaBody(array $row): string
    {
        $bodyText = $this->sanitizeString($row['body_text'] ?? '');
        if ($bodyText !== '') {
            return $bodyText;
        }

        $bodyHtml = $this->sanitizeString($row['body_html'] ?? '');
        if ($bodyHtml === '') {
            return '';
        }

        $text = trim(strip_tags($bodyHtml));
        if ($text === '') {
            return '';
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapTicketRow(array $row): array
    {
        $date = $this->parseDate($row['created_at'] ?? null);
        $contactLabel = $this->sanitizeString($row['reporter_nombre'] ?? '') ?: 'Ticket #' . (int) $row['ticket_id'];
        $meta = array_filter([
            'Estado' => $row['status'] ?? null,
            'Prioridad' => $row['priority'] ?? null,
            'Asignado a' => $row['assigned_nombre'] ?? null,
        ]);

        return [
            'uid' => 'ticket:' . (int) $row['ticket_id'] . ':' . (int) $row['id'],
            'source' => 'tickets',
            'source_label' => 'Tickets',
            'category' => 'Seguimiento',
            'subject' => $this->sanitizeString($row['subject'] ?? '') ?: 'Ticket #' . (int) $row['ticket_id'],
            'snippet' => $this->truncate((string) ($row['message'] ?? '')),
            'body' => trim((string) ($row['message'] ?? '')),
            'patient' => null,
            'contact' => [
                'label' => $contactLabel,
                'channel' => 'Tickets',
                'identifier' => 'T-' . (int) $row['ticket_id'],
            ],
            'meta' => $meta,
            'links' => [
                'ticket' => '/crm?ticket=' . (int) $row['ticket_id'],
            ],
            'channels' => ['CRM', 'Tickets'],
            'author' => [
                'name' => $row['autor_nombre'] ?? 'Sistema',
                'initials' => $this->initials($row['autor_nombre'] ?? 'Sistema'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => null,
            'related' => [
                'type' => 'ticket',
                'id' => (int) $row['ticket_id'],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapWhatsappRow(array $row): array
    {
        $date = $this->parseDate($row['created_at'] ?? null);
        $body = trim((string) ($row['message_body'] ?? ''));
        $contact = $this->lookupPatientByPhone((string) ($row['wa_number'] ?? ''));
        $label = $contact['name'] ?? $row['wa_number'] ?? 'Contacto WhatsApp';
        $meta = array_filter([
            'Número' => $row['wa_number'] ?? null,
            'Tipo' => $row['message_type'] ?? null,
        ]);

        $channels = ['WhatsApp'];
        if ($contact) {
            $meta['HC'] = $contact['hc_number'] ?? null;
        }

        return [
            'uid' => 'whatsapp:' . (int) $row['id'],
            'source' => 'whatsapp',
            'source_label' => 'WhatsApp',
            'category' => $row['direction'] === 'incoming' ? 'Entrante' : 'Saliente',
            'subject' => $row['direction'] === 'incoming' ? 'Mensaje entrante' : 'Mensaje enviado',
            'snippet' => $this->truncate($body),
            'body' => $body,
            'patient' => $contact ?: null,
            'contact' => [
                'label' => $label,
                'channel' => 'WhatsApp',
                'identifier' => $row['wa_number'] ?? null,
            ],
            'meta' => $meta,
            'links' => [
                'chat' => '/whatsapp/chat?number=' . urlencode((string) ($row['wa_number'] ?? '')),
            ],
            'channels' => $channels,
            'author' => [
                'name' => $row['direction'] === 'incoming' ? ($contact['name'] ?? 'Contacto') : 'Equipo',
                'initials' => $this->initials($row['direction'] === 'incoming' ? $label : 'Equipo'),
            ],
            'timestamp' => $date?->getTimestamp() ?? 0,
            'created_at' => $this->formatIso($date),
            'relative_time' => $this->formatRelative($date),
            'direction' => $row['direction'] ?? null,
            'related' => null,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array{name: string, hc_number?: string, phone?: string}
     */
    private function formatPatient(array $row): array
    {
        $name = $this->buildFullName(
            $row['fname'] ?? null,
            $row['mname'] ?? null,
            $row['lname'] ?? null,
            $row['lname2'] ?? null
        );

        $hc = $this->sanitizeString($row['hc_number'] ?? '');

        return array_filter([
            'name' => $name ?: 'Paciente sin nombre',
            'hc_number' => $hc ?: null,
            'phone' => $this->sanitizeString($row['celular'] ?? ''),
        ]);
    }

    private function buildFullName(?string ...$parts): string
    {
        $filtered = array_filter(array_map(
            static fn($part) => is_string($part) ? trim($part) : '',
            $parts
        ));

        return $filtered ? implode(' ', $filtered) : '';
    }

    private function sanitizeLimit(mixed $value): int
    {
        $default = (int) ($this->config['limit'] ?? 50);
        $int = (int) $value;
        if ($int <= 0) {
            $int = $default;
        }

        $int = max(10, $int);

        return min($int, 200);
    }

    /**
     * @param mixed $sources
     * @return array<int, string>
     */
    private function normalizeSources(mixed $sources, array $allowed): array
    {
        if (is_string($sources) && $sources !== '') {
            $sources = array_map('trim', explode(',', $sources));
        }

        if (!is_array($sources) || $sources === []) {
            return $allowed ?: [];
        }

        $normalized = [];
        foreach ($sources as $source) {
            $value = $this->mbLower((string) $source);
            if ($value !== '' && in_array($value, $allowed, true)) {
                $normalized[] = $value;
            }
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : ($allowed ?: []);
    }

    private function sanitizeString(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    private function parseDate(?string $value): ?DateTimeImmutable
    {
        if (!$value || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function formatIso(?DateTimeImmutable $date): ?string
    {
        return $date ? $date->format(DATE_ATOM) : null;
    }

    private function formatRelative(?DateTimeImmutable $date): ?string
    {
        if (!$date) {
            return null;
        }

        $now = new DateTimeImmutable();
        $diff = $now->getTimestamp() - $date->getTimestamp();
        $suffix = $diff >= 0 ? 'hace ' : 'en ';
        $abs = abs($diff);

        if ($abs < 60) {
            return $suffix . max(1, (int) $abs) . ' s';
        }

        if ($abs < 3600) {
            return $suffix . max(1, (int) round($abs / 60)) . ' min';
        }

        if ($abs < 86400) {
            return $suffix . max(1, (int) round($abs / 3600)) . ' h';
        }

        if ($abs < 604800) {
            return $suffix . max(1, (int) round($abs / 86400)) . ' d';
        }

        return $suffix . max(1, (int) round($abs / 604800)) . ' sem';
    }

    private function initials(?string $value): string
    {
        $value = $this->sanitizeString($value);
        if ($value === '') {
            return 'MF';
        }

        $parts = preg_split('/\s+/', $value) ?: [];
        $initials = '';

        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }
            $initials .= mb_strtoupper(mb_substr($part, 0, 1));
            if (mb_strlen($initials) >= 2) {
                break;
            }
        }

        return $initials !== '' ? $initials : 'MF';
    }

    private function truncate(string $text, int $length = 120): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }

        if (function_exists('mb_strlen') && function_exists('mb_substr')) {
            if (mb_strlen($text) <= $length) {
                return $text;
            }

            return rtrim(mb_substr($text, 0, $length - 1)) . '…';
        }

        if (strlen($text) <= $length) {
            return $text;
        }

        return rtrim(substr($text, 0, $length - 1)) . '…';
    }

    private function matchesQuery(array $entry, string $query): bool
    {
        $haystacks = [
            $entry['subject'] ?? '',
            $entry['body'] ?? '',
            $entry['snippet'] ?? '',
            $entry['contact']['label'] ?? '',
            $entry['patient']['name'] ?? '',
        ];

        $query = $this->mbLower($query);

        foreach ($haystacks as $haystack) {
            if ($haystack === '') {
                continue;
            }

            $value = $this->mbLower((string) $haystack);
            if ($value !== '' && str_contains($value, $query)) {
                return true;
            }
        }

        return false;
    }

    private function mbLower(string $value): string
    {
        return function_exists('mb_strtolower') ? mb_strtolower($value) : strtolower($value);
    }

    /**
     * @return array{name: string, hc_number?: string}|null
     */
    private function lookupPatientByPhone(string $rawNumber): ?array
    {
        $normalized = $this->normalizePhone($rawNumber);
        if ($normalized === '') {
            return null;
        }

        if (array_key_exists($normalized, $this->phoneCache)) {
            $cached = $this->phoneCache[$normalized];
            return $cached === false ? null : $cached;
        }

        $sql = <<<SQL
SELECT
    hc_number,
    CONCAT_WS(' ',
        NULLIF(TRIM(fname), ''),
        NULLIF(TRIM(mname), ''),
        NULLIF(TRIM(lname), ''),
        NULLIF(TRIM(lname2), '')
    ) AS nombre
FROM patient_data
WHERE celular IS NOT NULL
  AND REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(celular, '+', ''), '-', ''), ' ', ''), '(', ''), ')', ''), '.', '') = :phone
LIMIT 1
SQL;

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':phone', $normalized, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Throwable $exception) {
            $row = null;
        }

        if (!$row) {
            $this->phoneCache[$normalized] = false;
            return null;
        }

        $result = array_filter([
            'name' => $this->sanitizeString($row['nombre'] ?? '') ?: $rawNumber,
            'hc_number' => $this->sanitizeString($row['hc_number'] ?? ''),
        ]);

        $this->phoneCache[$normalized] = $result;

        return $result;
    }

    private function normalizePhone(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $stripped = str_replace(self::PHONE_REPLACEMENTS, '', $value);
        $digits = preg_replace('/\D+/', '', $stripped);

        return $digits ?? '';
    }

    private function buildConfig(): array
    {
        $config = [
            'enabled' => true,
            'compose_enabled' => true,
            'limit' => 50,
            'sort' => 'recent',
            'sources' => [
                'solicitudes' => true,
                'examenes' => true,
                'cobertura' => true,
                'tickets' => true,
                'whatsapp' => true,
            ],
        ];

        if (!$this->settingsModel instanceof SettingsModel) {
            return $config;
        }

        try {
            $options = $this->settingsModel->getOptions([
                'mailbox_enabled',
                'mailbox_compose_enabled',
                'mailbox_source_solicitudes',
                'mailbox_source_examenes',
                'mailbox_source_cobertura',
                'mailbox_source_tickets',
                'mailbox_source_whatsapp',
                'mailbox_limit',
                'mailbox_sort',
            ]);
        } catch (Throwable $exception) {
            return $config;
        }

        $config['enabled'] = $this->optionAsBool($options['mailbox_enabled'] ?? null, true);
        $config['compose_enabled'] = $this->optionAsBool($options['mailbox_compose_enabled'] ?? null, true);

        $config['sources'] = [
            'solicitudes' => $this->optionAsBool($options['mailbox_source_solicitudes'] ?? null, true),
            'examenes' => $this->optionAsBool($options['mailbox_source_examenes'] ?? null, true),
            'cobertura' => $this->optionAsBool($options['mailbox_source_cobertura'] ?? null, true),
            'tickets' => $this->optionAsBool($options['mailbox_source_tickets'] ?? null, true),
            'whatsapp' => $this->optionAsBool($options['mailbox_source_whatsapp'] ?? null, true),
        ];

        if (array_sum(array_map(static fn($value) => $value ? 1 : 0, $config['sources'])) === 0) {
            $config['sources'] = [
                'solicitudes' => true,
                'examenes' => true,
                'cobertura' => true,
                'tickets' => true,
                'whatsapp' => true,
            ];
        }

        $limit = (int) ($options['mailbox_limit'] ?? $config['limit']);
        if ($limit <= 0) {
            $limit = $config['limit'];
        }
        $config['limit'] = min(200, max(10, $limit));

        $sort = $options['mailbox_sort'] ?? $config['sort'];
        $config['sort'] = in_array($sort, ['recent', 'oldest'], true) ? $sort : $config['sort'];

        return $config;
    }

    private function optionAsBool($value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        $normalized = is_bool($value)
            ? $value
            : in_array((string) $value, ['1', 'true', 'on'], true);

        return $normalized;
    }

    /**
     * @return array<int, string>
     */
    private function getEnabledSources(): array
    {
        $sources = array_filter(
            $this->config['sources'] ?? [],
            static fn($enabled) => $enabled === true
        );

        if ($sources === []) {
            return [];
        }

        return array_keys($sources);
    }
}
