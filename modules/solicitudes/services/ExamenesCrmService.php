<?php

namespace Modules\Solicitudes\Services;

use DateTimeImmutable;
use Modules\CRM\Models\LeadModel;
use Modules\CRM\Services\LeadConfigurationService;
use Modules\WhatsApp\Services\Messenger as WhatsAppMessenger;
use Modules\WhatsApp\WhatsAppModule;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class ExamenesCrmService
{
    private const ESTADOS_TAREA_VALIDOS = ['pendiente', 'en_progreso', 'completada', 'cancelada'];

    private PDO $pdo;
    private LeadModel $leadModel;
    private LeadConfigurationService $leadConfig;
    private WhatsAppMessenger $whatsapp;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->leadModel = new LeadModel($pdo);
        $this->leadConfig = new LeadConfigurationService($pdo);
        $this->whatsapp = WhatsAppModule::messenger($pdo);
    }

    public function obtenerResponsables(): array
    {
        return $this->leadConfig->getAssignableUsers();
    }

    public function obtenerFuentes(): array
    {
        return $this->leadConfig->getSources();
    }

    public function obtenerResumen(int $solicitudId): array
    {
        $detalle = $this->obtenerDetalleSolicitud($solicitudId);
        if (!$detalle) {
            throw new RuntimeException('Solicitud no encontrada');
        }

        $seguidores = $detalle['seguidores'] ?? [];
        unset($detalle['seguidores']);

        if (!empty($seguidores)) {
            $detalle['seguidores'] = $this->obtenerUsuariosPorIds($seguidores);
        } else {
            $detalle['seguidores'] = [];
        }

        $lead = null;
        if (!empty($detalle['crm_lead_id'])) {
            $lead = $this->leadModel->find((int) $detalle['crm_lead_id']);
            if ($lead) {
                $lead['url'] = $this->buildLeadUrl((int) $lead['id']);
            }
        }

        return [
            'detalle' => $detalle,
            'notas' => $this->obtenerNotas($solicitudId),
            'adjuntos' => $this->obtenerAdjuntos($solicitudId),
            'tareas' => $this->obtenerTareas($solicitudId),
            'campos_personalizados' => $this->obtenerCamposPersonalizados($solicitudId),
            'lead' => $lead,
        ];
    }

    public function guardarDetalles(int $solicitudId, array $data, ?int $usuarioId = null): void
    {
        $detalleActual = $this->obtenerDetalleSolicitud($solicitudId);
        if (!$detalleActual) {
            throw new RuntimeException('Solicitud no encontrada');
        }

        $responsableId = isset($data['responsable_id']) && $data['responsable_id'] !== ''
            ? (int) $data['responsable_id']
            : null;

        $etapa = $this->normalizarEtapa($data['pipeline_stage'] ?? null);
        $fuente = $this->normalizarTexto($data['fuente'] ?? null);
        $contactoEmail = $this->normalizarTexto($data['contacto_email'] ?? null);
        $contactoTelefono = $this->normalizarTexto($data['contacto_telefono'] ?? null);
        $seguidores = $this->normalizarSeguidores($data['seguidores'] ?? []);

        $crmLeadId = $this->sincronizarLead(
            $solicitudId,
            $detalleActual,
            [
                'crm_lead_id' => $data['crm_lead_id'] ?? null,
                'responsable_id' => $responsableId,
                'fuente' => $fuente,
                'contacto_email' => $contactoEmail,
                'contacto_telefono' => $contactoTelefono,
                'etapa' => $etapa,
            ],
            $usuarioId
        );

        $jsonSeguidores = !empty($seguidores) ? json_encode($seguidores, JSON_UNESCAPED_UNICODE) : null;

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO solicitud_crm_detalles (solicitud_id, crm_lead_id, responsable_id, pipeline_stage, fuente, contacto_email, contacto_telefono, followers)
                 VALUES (:solicitud_id, :crm_lead_id, :responsable_id, :pipeline_stage, :fuente, :contacto_email, :contacto_telefono, :followers)
                 ON DUPLICATE KEY UPDATE
                    crm_lead_id = VALUES(crm_lead_id),
                    responsable_id = VALUES(responsable_id),
                    pipeline_stage = VALUES(pipeline_stage),
                    fuente = VALUES(fuente),
                    contacto_email = VALUES(contacto_email),
                    contacto_telefono = VALUES(contacto_telefono),
                    followers = VALUES(followers)'
            );

            $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
            $stmt->bindValue(':crm_lead_id', $crmLeadId, $crmLeadId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':responsable_id', $responsableId, $responsableId ? PDO::PARAM_INT : PDO::PARAM_NULL);
            $stmt->bindValue(':pipeline_stage', $etapa);
            $stmt->bindValue(':fuente', $fuente, $fuente !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':contacto_email', $contactoEmail, $contactoEmail !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':contacto_telefono', $contactoTelefono, $contactoTelefono !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':followers', $jsonSeguidores, $jsonSeguidores !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->execute();

            if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
                $this->guardarCamposPersonalizados($solicitudId, $data['custom_fields']);
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        $detallePosterior = $this->safeObtenerDetalleSolicitud($solicitudId);
        $this->notifyWhatsAppEvent(
            $solicitudId,
            'details_updated',
            [
                'detalle' => $detallePosterior,
                'detalle_anterior' => $detalleActual,
                'payload' => [
                    'responsable_id' => $responsableId,
                    'etapa' => $etapa,
                    'fuente' => $fuente,
                    'contacto_email' => $contactoEmail,
                    'contacto_telefono' => $contactoTelefono,
                ],
            ]
        );
    }

    public function registrarNota(int $solicitudId, string $nota, ?int $autorId): void
    {
        $nota = trim(strip_tags($nota));
        if ($nota === '') {
            throw new RuntimeException('La nota no puede estar vacía');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_notas (solicitud_id, autor_id, nota) VALUES (:solicitud_id, :autor_id, :nota)'
        );
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':autor_id', $autorId, $autorId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':nota', $nota, PDO::PARAM_STR);
        $stmt->execute();

        $this->notifyWhatsAppEvent(
            $solicitudId,
            'note_added',
            [
                'nota' => $nota,
                'autor_id' => $autorId,
                'autor_nombre' => $this->obtenerNombreUsuario($autorId),
            ]
        );
    }

    public function registrarTarea(int $solicitudId, array $data, ?int $autorId): void
    {
        $titulo = trim((string) ($data['titulo'] ?? ''));
        if ($titulo === '') {
            throw new RuntimeException('El título de la tarea es obligatorio');
        }

        $descripcion = $this->normalizarTexto($data['descripcion'] ?? null);
        $estado = $this->normalizarEstadoTarea($data['estado'] ?? 'pendiente');
        $assignedTo = isset($data['assigned_to']) && $data['assigned_to'] !== '' ? (int) $data['assigned_to'] : null;
        $dueDate = $this->normalizarFecha($data['due_date'] ?? null);
        $remindAt = $this->normalizarFechaHora($data['remind_at'] ?? null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_tareas (solicitud_id, titulo, descripcion, estado, assigned_to, created_by, due_date, remind_at)
             VALUES (:solicitud_id, :titulo, :descripcion, :estado, :assigned_to, :created_by, :due_date, :remind_at)'
        );

        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':titulo', $titulo, PDO::PARAM_STR);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':estado', $estado, PDO::PARAM_STR);
        $stmt->bindValue(':assigned_to', $assignedTo, $assignedTo ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $autorId, $autorId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':due_date', $dueDate, $dueDate !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':remind_at', $remindAt, $remindAt !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->execute();

        $tareaContexto = [
            'id' => (int) $this->pdo->lastInsertId(),
            'titulo' => $titulo,
            'descripcion' => $descripcion,
            'estado' => $estado,
            'assigned_to' => $assignedTo,
            'assigned_to_nombre' => $this->obtenerNombreUsuario($assignedTo),
            'due_date' => $dueDate,
            'remind_at' => $remindAt,
        ];

        $this->notifyWhatsAppEvent(
            $solicitudId,
            'task_created',
            [
                'tarea' => $tareaContexto,
                'autor_id' => $autorId,
                'autor_nombre' => $this->obtenerNombreUsuario($autorId),
            ]
        );
    }

    public function actualizarEstadoTarea(int $solicitudId, int $tareaId, string $estado): void
    {
        $estadoNormalizado = $this->normalizarEstadoTarea($estado);

        $stmt = $this->pdo->prepare(
            'UPDATE solicitud_crm_tareas
             SET estado = :estado, completed_at = CASE WHEN :estado = "completada" THEN CURRENT_TIMESTAMP ELSE completed_at END
             WHERE id = :id AND solicitud_id = :solicitud_id'
        );

        $stmt->bindValue(':estado', $estadoNormalizado, PDO::PARAM_STR);
        $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->execute();

        $tarea = $this->obtenerTareaPorId($solicitudId, $tareaId);
        if ($tarea !== null) {
            $this->notifyWhatsAppEvent(
                $solicitudId,
                'task_status_updated',
                [
                    'tarea' => $tarea,
                ]
            );
        }
    }

    public function registrarAdjunto(
        int $solicitudId,
        string $nombreOriginal,
        string $rutaRelativa,
        ?string $mimeType,
        ?int $tamano,
        ?int $usuarioId,
        ?string $descripcion = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_adjuntos (solicitud_id, nombre_original, ruta_relativa, mime_type, tamano_bytes, descripcion, subido_por)
             VALUES (:solicitud_id, :nombre_original, :ruta_relativa, :mime_type, :tamano_bytes, :descripcion, :subido_por)'
        );

        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->bindValue(':nombre_original', $nombreOriginal, PDO::PARAM_STR);
        $stmt->bindValue(':ruta_relativa', $rutaRelativa, PDO::PARAM_STR);
        $stmt->bindValue(':mime_type', $mimeType, $mimeType !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':tamano_bytes', $tamano, $tamano !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':descripcion', $descripcion, $descripcion !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':subido_por', $usuarioId, $usuarioId ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        $this->notifyWhatsAppEvent(
            $solicitudId,
            'attachment_uploaded',
            [
                'adjunto' => [
                    'nombre_original' => $nombreOriginal,
                    'descripcion' => $descripcion,
                    'mime_type' => $mimeType,
                    'tamano' => $tamano,
                ],
                'usuario_id' => $usuarioId,
                'usuario_nombre' => $this->obtenerNombreUsuario($usuarioId),
            ]
        );
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function notifyWhatsAppEvent(int $solicitudId, string $evento, array $contexto = []): void
    {
        if (!$this->whatsapp->isEnabled()) {
            return;
        }

        $detalle = $contexto['detalle'] ?? $this->safeObtenerDetalleSolicitud($solicitudId);
        if ($detalle === null) {
            return;
        }

        $contexto['detalle'] = $detalle;

        if ($evento === 'details_updated' && isset($contexto['detalle_anterior']) && is_array($contexto['detalle_anterior'])) {
            if (!$this->huboCambiosRelevantes($contexto['detalle_anterior'], $detalle, $contexto['payload'] ?? [])) {
                return;
            }
        }

        $telefonos = $this->collectWhatsappPhones($detalle, $contexto);
        if (empty($telefonos)) {
            return;
        }

        $mensaje = $this->buildWhatsAppMessage($evento, $contexto);
        if ($mensaje === '') {
            return;
        }

        $this->whatsapp->sendTextMessage($telefonos, $mensaje);
    }

    /**
     * @param array<string, mixed> $detalle
     * @param array<string, mixed> $contexto
     *
     * @return string[]
     */
    private function collectWhatsappPhones(array $detalle, array $contexto): array
    {
        $telefonos = [];

        foreach (['crm_contacto_telefono', 'paciente_celular', 'contacto_telefono'] as $clave) {
            if (!empty($detalle[$clave])) {
                $telefonos[] = (string) $detalle[$clave];
            }
        }

        if (!empty($contexto['payload']['contacto_telefono'])) {
            $telefonos[] = (string) $contexto['payload']['contacto_telefono'];
        }

        if (!empty($contexto['telefonos_adicionales']) && is_array($contexto['telefonos_adicionales'])) {
            foreach ($contexto['telefonos_adicionales'] as $telefono) {
                if ($telefono) {
                    $telefonos[] = (string) $telefono;
                }
            }
        }

        if (!empty($contexto['tarea']['telefono'])) {
            $telefonos[] = (string) $contexto['tarea']['telefono'];
        }

        return array_values(array_unique(array_filter($telefonos)));
    }

    /**
     * @param array<string, mixed> $contexto
     */
    private function buildWhatsAppMessage(string $evento, array $contexto): string
    {
        $detalle = $contexto['detalle'] ?? [];
        $solicitudId = isset($detalle['id']) ? (int) $detalle['id'] : 0;
        $paciente = trim((string) ($detalle['paciente_nombre'] ?? ''));
        $marca = $this->whatsapp->getBrandName();
        $tituloSolicitud = $solicitudId > 0
            ? 'Solicitud #' . $solicitudId . ($paciente !== '' ? ' · ' . $paciente : '')
            : ($paciente !== '' ? $paciente : 'Solicitud CRM');

        switch ($evento) {
            case 'details_updated':
                $actual = $detalle['crm_pipeline_stage'] ?? ($detalle['pipeline_stage'] ?? null);
                $anterior = $contexto['detalle_anterior']['crm_pipeline_stage'] ?? null;
                $responsable = $detalle['crm_responsable_nombre'] ?? null;
                $lineas = [
                    '🔄 Actualización CRM - ' . $marca,
                    $tituloSolicitud,
                ];

                if (!empty($detalle['procedimiento'])) {
                    $lineas[] = 'Procedimiento: ' . $detalle['procedimiento'];
                }

                if ($actual) {
                    if ($anterior && strcasecmp($anterior, $actual) !== 0) {
                        $lineas[] = 'Etapa: ' . $anterior . ' → ' . $actual;
                    } else {
                        $lineas[] = 'Etapa actual: ' . $actual;
                    }
                }

                if ($responsable) {
                    $lineas[] = 'Responsable: ' . $responsable;
                }

                if (!empty($detalle['prioridad'])) {
                    $lineas[] = 'Prioridad: ' . ucfirst((string) $detalle['prioridad']);
                }

                if (!empty($detalle['crm_fuente'])) {
                    $lineas[] = 'Fuente: ' . $detalle['crm_fuente'];
                }

                $lineas[] = 'Ver detalle: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'note_added':
                $nota = trim((string) ($contexto['nota'] ?? ''));
                $autor = trim((string) ($contexto['autor_nombre'] ?? ''));
                $lineas = [
                    '📝 Nueva nota en CRM - ' . $marca,
                    $tituloSolicitud,
                ];
                if ($autor !== '') {
                    $lineas[] = 'Autor: ' . $autor;
                }
                if ($nota !== '') {
                    $lineas[] = 'Nota: ' . $this->truncateText($nota, 320);
                }
                $lineas[] = 'Revisa el historial: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'task_created':
                $tarea = $contexto['tarea'] ?? [];
                $lineas = [
                    '✅ Nueva tarea CRM - ' . $marca,
                    $tituloSolicitud,
                ];
                if (!empty($tarea['titulo'])) {
                    $lineas[] = 'Tarea: ' . $tarea['titulo'];
                }
                if (!empty($tarea['assigned_to_nombre'])) {
                    $lineas[] = 'Responsable: ' . $tarea['assigned_to_nombre'];
                }
                if (!empty($tarea['estado'])) {
                    $lineas[] = 'Estado inicial: ' . $this->humanizeStatus((string) $tarea['estado']);
                }
                if (!empty($tarea['due_date'])) {
                    $fecha = $this->formatDateTime($tarea['due_date'], 'd/m/Y');
                    if ($fecha) {
                        $lineas[] = 'Vencimiento: ' . $fecha;
                    }
                }
                if (!empty($tarea['remind_at'])) {
                    $recordatorio = $this->formatDateTime($tarea['remind_at'], 'd/m/Y H:i');
                    if ($recordatorio) {
                        $lineas[] = 'Recordatorio: ' . $recordatorio;
                    }
                }
                $lineas[] = 'Gestiona la tarea: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'task_status_updated':
                $tarea = $contexto['tarea'] ?? [];
                $lineas = [
                    '📌 Actualización de tarea CRM - ' . $marca,
                    $tituloSolicitud,
                ];
                if (!empty($tarea['titulo'])) {
                    $lineas[] = 'Tarea: ' . $tarea['titulo'];
                }
                if (!empty($tarea['estado'])) {
                    $lineas[] = 'Estado actual: ' . $this->humanizeStatus((string) $tarea['estado']);
                }
                if (!empty($tarea['assigned_to_nombre'])) {
                    $lineas[] = 'Responsable: ' . $tarea['assigned_to_nombre'];
                }
                if (!empty($tarea['due_date'])) {
                    $fecha = $this->formatDateTime($tarea['due_date'], 'd/m/Y');
                    if ($fecha) {
                        $lineas[] = 'Vencimiento: ' . $fecha;
                    }
                }
                $lineas[] = 'Ver tablero: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            case 'attachment_uploaded':
                $adjunto = $contexto['adjunto'] ?? [];
                $autorAdjunto = trim((string) ($contexto['usuario_nombre'] ?? ''));
                $lineas = [
                    '📎 Nuevo adjunto en CRM - ' . $marca,
                    $tituloSolicitud,
                ];
                if (!empty($adjunto['nombre_original'])) {
                    $lineas[] = 'Archivo: ' . $adjunto['nombre_original'];
                }
                if (!empty($adjunto['descripcion'])) {
                    $lineas[] = 'Descripción: ' . $this->truncateText((string) $adjunto['descripcion'], 200);
                }
                if ($autorAdjunto !== '') {
                    $lineas[] = 'Cargado por: ' . $autorAdjunto;
                }
                $lineas[] = 'Consulta los documentos: ' . $this->buildSolicitudUrl($solicitudId);

                return implode("\n", array_filter($lineas));

            default:
                return '';
        }
    }

    private function buildSolicitudUrl(int $solicitudId): string
    {
        $base = defined('BASE_URL') ? rtrim((string) BASE_URL, '/') : '';
        $path = '/solicitudes/' . $solicitudId . '/crm';

        if ($base === '') {
            return $path;
        }

        return $base . $path;
    }

    private function formatDateTime(?string $valor, string $formato): ?string
    {
        if ($valor === null || $valor === '') {
            return null;
        }

        try {
            $fecha = new DateTimeImmutable($valor);

            return $fecha->format($formato);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function truncateText(string $texto, int $limite): string
    {
        $texto = trim(preg_replace('/\s+/u', ' ', $texto));
        if (mb_strlen($texto) <= $limite) {
            return $texto;
        }

        return mb_substr($texto, 0, $limite - 1) . '…';
    }

    private function humanizeStatus(string $estado): string
    {
        $estado = str_replace('_', ' ', $estado);

        return ucwords($estado);
    }

    /**
     * @param array<string, mixed> $anterior
     * @param array<string, mixed> $actual
     * @param array<string, mixed> $payload
     */
    private function huboCambiosRelevantes(array $anterior, array $actual, array $payload): bool
    {
        $comparaciones = [
            'crm_pipeline_stage',
            'crm_responsable_id',
            'crm_contacto_telefono',
            'crm_contacto_email',
            'crm_fuente',
        ];

        foreach ($comparaciones as $clave) {
            $previo = $anterior[$clave] ?? null;
            $nuevo = $actual[$clave] ?? null;

            if ($clave === 'crm_pipeline_stage') {
                $previo = $this->normalizarEtapa($previo ?? null);
                $nuevo = $this->normalizarEtapa($nuevo ?? null);
            }

            if ($previo != $nuevo) {
                return true;
            }
        }

        if (!empty($payload['contacto_telefono']) && $payload['contacto_telefono'] !== ($anterior['crm_contacto_telefono'] ?? null)) {
            return true;
        }

        if (!empty($payload['contacto_email']) && $payload['contacto_email'] !== ($anterior['crm_contacto_email'] ?? null)) {
            return true;
        }

        if (!empty($payload['fuente']) && $payload['fuente'] !== ($anterior['crm_fuente'] ?? null)) {
            return true;
        }

        return false;
    }

    private function obtenerNombreUsuario(?int $usuarioId): ?string
    {
        if (!$usuarioId) {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT nombre FROM users WHERE id = :id LIMIT 1');
        $stmt->bindValue(':id', $usuarioId, PDO::PARAM_INT);
        $stmt->execute();

        $nombre = $stmt->fetchColumn();

        return $nombre ? (string) $nombre : null;
    }

    private function obtenerTareaPorId(int $solicitudId, int $tareaId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, titulo, descripcion, estado, assigned_to, due_date, remind_at, completed_at'
            . ' FROM solicitud_crm_tareas WHERE id = :id AND solicitud_id = :solicitud_id LIMIT 1'
        );
        $stmt->bindValue(':id', $tareaId, PDO::PARAM_INT);
        $stmt->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
        $stmt->execute();

        $tarea = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$tarea) {
            return null;
        }

        $tarea['assigned_to_nombre'] = $this->obtenerNombreUsuario(isset($tarea['assigned_to']) ? (int) $tarea['assigned_to'] : null);

        return $tarea;
    }

    private function safeObtenerDetalleSolicitud(int $solicitudId): ?array
    {
        try {
            return $this->obtenerDetalleSolicitud($solicitudId);
        } catch (Throwable $exception) {
            return null;
        }
    }

    private function obtenerDetalleSolicitud(int $solicitudId): ?array
    {
        $sql = <<<'SQL'
            SELECT
                sp.id,
                sp.hc_number,
                sp.form_id,
                sp.estado,
                sp.prioridad,
                sp.doctor,
                sp.procedimiento,
                sp.ojo,
                sp.created_at,
                sp.observacion,
                sp.turno,
                cd.fecha AS fecha_consulta,
                pd.afiliacion,
                pd.celular AS paciente_celular,
                CONCAT(TRIM(pd.fname), ' ', TRIM(pd.mname), ' ', TRIM(pd.lname), ' ', TRIM(pd.lname2)) AS paciente_nombre,
                detalles.crm_lead_id AS crm_lead_id,
                detalles.pipeline_stage AS crm_pipeline_stage,
                detalles.fuente AS crm_fuente,
                detalles.contacto_email AS crm_contacto_email,
                detalles.contacto_telefono AS crm_contacto_telefono,
                detalles.responsable_id AS crm_responsable_id,
                detalles.followers AS crm_followers,
                responsable.nombre AS crm_responsable_nombre,
                responsable.email AS crm_responsable_email,
                responsable.profile_photo AS crm_responsable_avatar,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ''
                      AND LOWER(TRIM(sp.doctor)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS doctor_avatar,
                cl.status  AS crm_lead_status,
                cl.source  AS crm_lead_source,
                cl.updated_at AS crm_lead_updated_at,
                COALESCE(notas.total_notas, 0) AS crm_total_notas,
                COALESCE(adjuntos.total_adjuntos, 0) AS crm_total_adjuntos,
                COALESCE(tareas.tareas_pendientes, 0) AS crm_tareas_pendientes,
                COALESCE(tareas.tareas_total, 0) AS crm_tareas_total,
                tareas.proximo_vencimiento AS crm_proximo_vencimiento
            FROM solicitud_procedimiento sp
            INNER JOIN patient_data pd ON sp.hc_number = pd.hc_number
            LEFT JOIN consulta_data cd ON sp.hc_number = cd.hc_number AND sp.form_id = cd.form_id
            LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
            LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
            LEFT JOIN crm_leads cl ON detalles.crm_lead_id = cl.id
            LEFT JOIN (
                SELECT solicitud_id, COUNT(*) AS total_notas
                FROM solicitud_crm_notas
                GROUP BY solicitud_id
            ) notas ON notas.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id, COUNT(*) AS total_adjuntos
                FROM solicitud_crm_adjuntos
                GROUP BY solicitud_id
            ) adjuntos ON adjuntos.solicitud_id = sp.id
            LEFT JOIN (
                SELECT solicitud_id,
                       COUNT(*) AS tareas_total,
                       SUM(CASE WHEN estado IN ('pendiente','en_progreso') THEN 1 ELSE 0 END) AS tareas_pendientes,
                       MIN(CASE WHEN estado IN ('pendiente','en_progreso') THEN due_date END) AS proximo_vencimiento
                FROM solicitud_crm_tareas
                GROUP BY solicitud_id
            ) tareas ON tareas.solicitud_id = sp.id
            WHERE sp.id = :solicitud_id
            LIMIT 1
        SQL;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':solicitud_id' => $solicitudId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['crm_responsable_avatar'] = $this->formatProfilePhoto($row['crm_responsable_avatar'] ?? null);
        $row['doctor_avatar'] = $this->formatProfilePhoto($row['doctor_avatar'] ?? null);

        $row['crm_pipeline_stage'] = $this->normalizarEtapa($row['crm_pipeline_stage'] ?? null);

        $row['seguidores'] = $this->decodificarSeguidores($row['crm_followers'] ?? null);
        unset($row['crm_followers']);

        $row['dias_en_estado'] = $this->calcularDiasEnEstado($row['created_at'] ?? null);

        return $row;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('~^https?://~i', $path)) {
            return $path;
        }

        return function_exists('asset') ? asset($path) : $path;
    }

    private function sincronizarLead(int $solicitudId, array $detalle, array $payload, ?int $usuarioId): ?int
    {
        $leadId = null;

        if (!empty($payload['crm_lead_id'])) {
            $leadId = (int) $payload['crm_lead_id'];
        } elseif (!empty($detalle['crm_lead_id'])) {
            $leadId = (int) $detalle['crm_lead_id'];
        }

        $nombre = trim((string) ($detalle['paciente_nombre'] ?? ''));
        if ($nombre === '') {
            $nombre = 'Solicitud #' . $solicitudId;
        }

        $leadData = [
            'name' => $nombre,
            'email' => $payload['contacto_email'] ?? ($detalle['crm_contacto_email'] ?? null),
            'phone' => $payload['contacto_telefono'] ?? ($detalle['crm_contacto_telefono'] ?? $detalle['paciente_celular'] ?? null),
            'source' => $payload['fuente'] ?? ($detalle['crm_fuente'] ?? null),
            'assigned_to' => $payload['responsable_id'] ?? ($detalle['crm_responsable_id'] ?? null),
            'status' => $this->mapearEtapaALeadStatus($payload['etapa'] ?? ($detalle['crm_pipeline_stage'] ?? null)),
            'notes' => $detalle['observacion'] ?? null,
        ];

        if ($leadId) {
            $actualizado = $this->leadModel->update($leadId, $leadData);
            if ($actualizado) {
                return (int) $actualizado['id'];
            }
            $leadId = null;
        }

        $creado = $this->leadModel->create($leadData, (int) ($usuarioId ?? 0));
        return $creado ? (int) $creado['id'] : null;
    }

    private function buildLeadUrl(int $leadId): string
    {
        return '/crm?lead=' . $leadId;
    }

    private function mapearEtapaALeadStatus(?string $etapa): string
    {
        return $this->leadConfig->normalizeStage($etapa);
    }

    private function obtenerUsuariosPorIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT id, nombre, email FROM users WHERE id IN ($placeholders) ORDER BY nombre");
        $stmt->execute($ids);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerNotas(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT n.id, n.nota, n.created_at, n.autor_id, u.nombre AS autor_nombre
             FROM solicitud_crm_notas n
             LEFT JOIN users u ON n.autor_id = u.id
             WHERE n.solicitud_id = :solicitud_id
             ORDER BY n.created_at DESC
             LIMIT 100'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerAdjuntos(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT a.id, a.nombre_original, a.ruta_relativa, a.mime_type, a.tamano_bytes, a.descripcion, a.created_at, a.subido_por, u.nombre AS subido_por_nombre
             FROM solicitud_crm_adjuntos a
             LEFT JOIN users u ON a.subido_por = u.id
             WHERE a.solicitud_id = :solicitud_id
             ORDER BY a.created_at DESC'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

        $adjuntos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($adjuntos as &$adjunto) {
            $ruta = (string) ($adjunto['ruta_relativa'] ?? '');
            if ($ruta !== '' && function_exists('asset')) {
                $adjunto['url'] = asset($ruta);
            } else {
                $adjunto['url'] = $ruta;
            }
        }
        unset($adjunto);

        return $adjuntos;
    }

    private function obtenerTareas(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT t.id, t.titulo, t.descripcion, t.estado, t.assigned_to, t.created_by, t.due_date, t.remind_at, t.created_at, t.completed_at,
                    asignado.nombre AS assigned_name, creador.nombre AS created_name
             FROM solicitud_crm_tareas t
             LEFT JOIN users asignado ON t.assigned_to = asignado.id
             LEFT JOIN users creador ON t.created_by = creador.id
             WHERE t.solicitud_id = :solicitud_id
             ORDER BY
                CASE WHEN t.estado IN ("pendiente", "en_progreso") THEN 0 ELSE 1 END,
                t.due_date IS NULL,
                t.due_date ASC,
                t.created_at DESC'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function obtenerCamposPersonalizados(int $solicitudId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, meta_key, meta_value, meta_type, created_at, updated_at
             FROM solicitud_crm_meta
             WHERE solicitud_id = :solicitud_id
             ORDER BY meta_key'
        );
        $stmt->execute([':solicitud_id' => $solicitudId]);

        $result = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $result[] = [
                'id' => (int) $row['id'],
                'key' => $row['meta_key'],
                'value' => $row['meta_value'],
                'type' => $row['meta_type'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $result;
    }

    private function guardarCamposPersonalizados(int $solicitudId, array $campos): void
    {
        $limpios = [];
        foreach ($campos as $campo) {
            if (!is_array($campo)) {
                continue;
            }

            $key = $this->normalizarClave($campo['key'] ?? null);
            if ($key === null) {
                continue;
            }

            $valor = $this->normalizarTexto($campo['value'] ?? null);
            $tipo = $this->normalizarTipoCampo($campo['type'] ?? 'texto');

            $limpios[$key] = [
                'value' => $valor,
                'type' => $tipo,
            ];
        }

        $stmtDelete = $this->pdo->prepare('DELETE FROM solicitud_crm_meta WHERE solicitud_id = :solicitud_id');
        $stmtDelete->execute([':solicitud_id' => $solicitudId]);

        if (empty($limpios)) {
            return;
        }

        $stmtInsert = $this->pdo->prepare(
            'INSERT INTO solicitud_crm_meta (solicitud_id, meta_key, meta_value, meta_type)
             VALUES (:solicitud_id, :meta_key, :meta_value, :meta_type)'
        );

        foreach ($limpios as $key => $info) {
            $stmtInsert->bindValue(':solicitud_id', $solicitudId, PDO::PARAM_INT);
            $stmtInsert->bindValue(':meta_key', $key, PDO::PARAM_STR);
            $stmtInsert->bindValue(':meta_value', $info['value'], $info['value'] !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmtInsert->bindValue(':meta_type', $info['type'], PDO::PARAM_STR);
            $stmtInsert->execute();
        }
    }

    private function normalizarEtapa(?string $etapa): string
    {
        return $this->leadConfig->normalizeStage($etapa);
    }

    private function normalizarTexto(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim(strip_tags((string) $valor));

        return $valor === '' ? null : $valor;
    }

    private function normalizarSeguidores($seguidores): array
    {
        if (!is_array($seguidores)) {
            return [];
        }

        $ids = [];
        foreach ($seguidores as $seguidor) {
            if ($seguidor === '' || $seguidor === null) {
                continue;
            }

            $ids[] = (int) $seguidor;
        }

        return array_values(array_unique(array_filter($ids, static fn($id) => $id > 0)));
    }

    private function normalizarEstadoTarea(?string $estado): string
    {
        $estado = trim((string) $estado);
        foreach (self::ESTADOS_TAREA_VALIDOS as $valido) {
            if (strcasecmp($estado, $valido) === 0) {
                return $valido;
            }
        }

        return 'pendiente';
    }

    private function normalizarFecha(?string $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }

        $fecha = trim($fecha);
        $formatos = ['Y-m-d', 'd-m-Y', 'd/m/Y'];

        foreach ($formatos as $formato) {
            $dt = DateTimeImmutable::createFromFormat($formato, $fecha);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d');
            }
        }

        return null;
    }

    private function normalizarFechaHora(?string $fechaHora): ?string
    {
        if ($fechaHora === null || $fechaHora === '') {
            return null;
        }

        $fechaHora = trim($fechaHora);
        $formatos = ['Y-m-d H:i', 'Y-m-d\TH:i', 'd-m-Y H:i', 'd/m/Y H:i'];

        foreach ($formatos as $formato) {
            $dt = DateTimeImmutable::createFromFormat($formato, $fechaHora);
            if ($dt instanceof DateTimeImmutable) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        return null;
    }

    private function normalizarClave($clave): ?string
    {
        if ($clave === null) {
            return null;
        }

        $clave = trim((string) $clave);
        if ($clave === '') {
            return null;
        }

        $clave = preg_replace('/[^A-Za-z0-9_\- ]+/', '', $clave);

        return $clave === '' ? null : $clave;
    }

    private function normalizarTipoCampo(?string $tipo): string
    {
        $tipo = strtolower(trim((string) $tipo));
        $permitidos = ['texto', 'numero', 'fecha', 'lista'];

        return in_array($tipo, $permitidos, true) ? $tipo : 'texto';
    }

    private function decodificarSeguidores(?string $json): array
    {
        if ($json === null || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        return $this->normalizarSeguidores($decoded);
    }

    private function calcularDiasEnEstado(?string $fechaCreacion): ?int
    {
        if (empty($fechaCreacion)) {
            return null;
        }

        try {
            $inicio = new DateTimeImmutable($fechaCreacion);
            $hoy = new DateTimeImmutable('now');
            $diff = $inicio->diff($hoy);

            return (int) $diff->days;
        } catch (\Throwable) {
            return null;
        }
    }
}
