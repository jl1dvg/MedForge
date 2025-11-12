<?php

namespace Modules\Dashboard\Controllers;

use Core\View;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Modules\AI\Services\AIConfigService;
use Modules\KPI\Services\KpiQueryService;
use PDO;
use Throwable;

class DashboardController
{
    private $db;

    public function __construct($pdo)
    {
        $this->db = $pdo;
    }

    public function getAuthenticatedUser()
    {
        if (!isset($_SESSION['user_id'])) {
            // No redirige aquí, solo devuelve null
            return null;
        }

        $stmt = $this->db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetchColumn() ?: 'Invitado';
    }

    public function index()
    {
        try {
            if (!isset($_SESSION['user_id'])) {
                header('Location: /auth/login');
                exit;
            }

            $dateRange = $this->resolveDateRange();
            $username = $this->getAuthenticatedUser();
            $kpiService = new KpiQueryService($this->db);

            $procedimientos_dia = $this->getProcedimientosPorDia($dateRange['start'], $dateRange['end']);
            $top_procedimientos = $this->getTopProcedimientosDelPeriodo($dateRange['start'], $dateRange['end']);
            $cirugias_recientes = $this->getCirugiasRecientes($dateRange['start'], $dateRange['end']);
            $revision_estados = $this->getEstadosRevisionProtocolos($dateRange['start'], $dateRange['end']);
            $solicitudes_funnel = $this->getSolicitudesFunnel($dateRange['start'], $dateRange['end']);
            $crm_backlog = $this->getCrmBacklogStats($dateRange['start'], $dateRange['end']);
            $ai_summary = $this->getAiSummary();
            $kpiAggregates = $this->fetchDashboardKpiAggregates($kpiService, $dateRange['start'], $dateRange['end']);

            $data = [
                'username' => $username,
                'date_range' => $this->formatDateRangeForView($dateRange),
                'procedimientos_dia' => $procedimientos_dia,
                'fechas_json' => json_encode($procedimientos_dia['fechas']),
                'procedimientos_dia_json' => json_encode($procedimientos_dia['totales']),
                'solicitudes_funnel' => $solicitudes_funnel,
                'solicitudes_funnel_json' => json_encode($solicitudes_funnel, JSON_THROW_ON_ERROR),
                'crm_backlog' => $crm_backlog,
                'crm_backlog_json' => json_encode($crm_backlog, JSON_THROW_ON_ERROR),
                'top_procedimientos' => $top_procedimientos,
                'membretes_json' => json_encode($top_procedimientos['membretes']),
                'procedimientos_membrete_json' => json_encode($top_procedimientos['totales']),
                'plantillas' => $this->getPlantillasRecientes(),
                'diagnosticos_frecuentes' => $this->getDiagnosticosFrecuentes(),
                'solicitudes_quirurgicas' => $this->getUltimasSolicitudes($dateRange['start'], $dateRange['end']),
                'doctores_top' => $this->getTopDoctores($dateRange['start'], $dateRange['end']),
                'estadisticas_afiliacion' => $this->getEstadisticasPorAfiliacion($dateRange['start'], $dateRange['end']),
                'revision_estados' => $revision_estados,
                'revision_estados_json' => json_encode($revision_estados, JSON_THROW_ON_ERROR),
                'total_cirugias_periodo' => $this->getTotalCirugias($dateRange['start'], $dateRange['end']),
                'total_protocols' => $this->totalProtocolos(),
                'total_patients' => $this->totalPacientes(),
                'total_users' => $this->totalUsuarios(),
                'kpi_cards' => $this->buildKpiCards($kpiAggregates, $ai_summary),
                'ai_summary' => $ai_summary,
                'cirugias_recientes' => $cirugias_recientes,
            ];

            View::render(
                dirname(__DIR__) . '/views/index.php',
                array_merge($data, [
                    'pageTitle' => 'Dashboard',
                ])
            );

        } catch (Throwable $e) {
            file_put_contents(__DIR__ . '/error_dashboard.log', $e->getMessage() . "\n" . $e->getTraceAsString(), FILE_APPEND);
            http_response_code(500);
            echo "Error interno: " . htmlspecialchars($e->getMessage());
        }
    }

    public function totalPacientes()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM patient_data");
        return $stmt->fetchColumn() ?? 0;
    }

    public function totalUsuarios()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM users");
        return $stmt->fetchColumn() ?? 0;
    }

    public function totalProtocolos()
    {
        $stmt = $this->db->query("SELECT COUNT(*) as total FROM protocolo_data");
        return $stmt->fetchColumn() ?? 0;
    }

    public function getRecentCirugias(DateTimeInterface $start, DateTimeInterface $end, $limit = 8)
    {
        $sql = "SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion,
                       pr.fecha_inicio, pr.id, pr.membrete, pr.form_id
                FROM patient_data p
                INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
                WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
                ORDER BY pr.fecha_inicio DESC, pr.id DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $start->format('Y-m-d 00:00:00'));
        $stmt->bindValue(':fin', $end->format('Y-m-d 23:59:59'));
        $stmt->bindValue(':limit', (int) $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDiagnosticosFrecuentes(): array
    {
        $sql = "SELECT hc_number, diagnosticos FROM consulta_data WHERE diagnosticos IS NOT NULL AND diagnosticos != ''";
        $stmt = $this->db->prepare($sql);
        $stmt->execute();

        $conteoDiagnosticos = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hc = $row['hc_number'];
            $diagnosticos = json_decode($row['diagnosticos'], true);
            if (is_array($diagnosticos)) {
                foreach ($diagnosticos as $dx) {
                    $id = isset($dx['idDiagnostico']) ? strtoupper(str_replace('.', '', $dx['idDiagnostico'])) : 'SINID';
                    $desc = is_array($dx) && array_key_exists('descripcion', $dx) ? $dx['descripcion'] : 'Sin descripción';

                    if (stripos($id, 'Z') === 0) continue; // Excluir diagnósticos tipo Z

                    // Agrupación específica: unificar H25 y H251 como un solo diagnóstico
                    if ($id === 'H25' || $id === 'H251') {
                        $key = 'H25 | Catarata senil';
                    } else {
                        $key = $id . ' | ' . $desc;
                    }

                    $conteoDiagnosticos[$key][$hc] = true;
                }
            }
        }

        // Calcular cuántos pacientes únicos por diagnóstico
        $prevalencias = [];
        foreach ($conteoDiagnosticos as $key => $pacientes) {
            $prevalencias[$key] = count($pacientes);
        }

        // Ordenar y tomar los 9 más frecuentes
        arsort($prevalencias);
        return array_slice($prevalencias, 0, 9, true);
    }

    public function getProcedimientosPorDia(DateTimeInterface $start, DateTimeInterface $end)
    {
        $sql = "SELECT DATE(fecha_inicio) as fecha, COUNT(*) as total_procedimientos
            FROM protocolo_data
            WHERE fecha_inicio BETWEEN :inicio AND :fin
            GROUP BY DATE(fecha_inicio)
            ORDER BY fecha ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $fechas = [];
        $totales = [];

        if ($stmt && $stmt->rowCount() > 0) {
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $fechas[] = date('Y-m-d', strtotime($row['fecha']));
                $totales[] = $row['total_procedimientos'];
            }
        } else {
            $fechas = ['No data'];
            $totales = [0];
        }

        return [
            'fechas' => $fechas,
            'totales' => $totales,
        ];
    }

    public function getTopProcedimientosDelPeriodo(DateTimeInterface $start, DateTimeInterface $end)
    {
        $sql = "SELECT procedimiento_id, COUNT(*) as total_procedimientos
                FROM protocolo_data
                WHERE fecha_inicio BETWEEN :inicio AND :fin
                  AND procedimiento_id IS NOT NULL
                  AND procedimiento_id != ''
                GROUP BY procedimiento_id
                ORDER BY total_procedimientos DESC
                LIMIT 5";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $membretes = [];
        $totales = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $membretes[] = $row['procedimiento_id'];
            $totales[] = $row['total_procedimientos'];
        }

        return [
            'membretes' => $membretes ?: ['No data'],
            'totales' => $totales ?: [0],
        ];
    }

    public function getCirugiasRecientes(DateTimeInterface $start, DateTimeInterface $end, $limit = 8)
    {
        $sql = "SELECT p.hc_number, p.fname, p.lname, p.lname2, p.fecha_nacimiento, p.ciudad, p.afiliacion,
                   pr.fecha_inicio, pr.id, pr.membrete, pr.form_id
            FROM patient_data p
            INNER JOIN protocolo_data pr ON p.hc_number = pr.hc_number
            WHERE p.afiliacion != 'ALQUILER'
              AND pr.fecha_inicio BETWEEN :inicio AND :fin
            ORDER BY pr.fecha_inicio DESC, pr.id DESC
            LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $start->format('Y-m-d 00:00:00'));
        $stmt->bindValue(':fin', $end->format('Y-m-d 23:59:59'));
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getTotalCirugias(DateTimeInterface $start, DateTimeInterface $end)
    {
        $sql = "SELECT COUNT(*) as total
            FROM protocolo_data
            WHERE fecha_inicio BETWEEN :inicio AND :fin";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);
        return (int) $stmt->fetchColumn();
    }

    public function getPlantillasRecientes($limit = 20)
    {
        $sql = "SELECT id, membrete, cirugia, 
                   COALESCE(fecha_actualizacion, fecha_creacion) AS fecha,
                   CASE 
                       WHEN fecha_actualizacion IS NOT NULL THEN 'Modificado'
                       ELSE 'Creado'
                   END AS tipo
            FROM procedimientos
            ORDER BY fecha DESC
            LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getUltimasSolicitudes(DateTimeInterface $start, DateTimeInterface $end, $limit = 5)
    {
        $sql = "SELECT sp.id, sp.fecha, sp.procedimiento, p.fname, p.lname, p.hc_number, sp.estado, sp.prioridad,
                       sp.turno, detalles.pipeline_stage AS crm_pipeline_stage, detalles.responsable_id,
                       responsable.nombre AS responsable_nombre
                FROM solicitud_procedimiento sp
                JOIN patient_data p
                  ON sp.hc_number COLLATE utf8mb4_unicode_ci = p.hc_number COLLATE utf8mb4_unicode_ci
                LEFT JOIN solicitud_crm_detalles detalles ON detalles.solicitud_id = sp.id
                LEFT JOIN users responsable ON detalles.responsable_id = responsable.id
                WHERE sp.procedimiento IS NOT NULL
                  AND sp.procedimiento != ''
                  AND sp.procedimiento != 'SELECCIONE'
                  AND COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
                ORDER BY COALESCE(sp.created_at, sp.fecha) DESC
                LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':inicio', $start->format('Y-m-d 00:00:00'));
        $stmt->bindValue(':fin', $end->format('Y-m-d 23:59:59'));
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalStmt = $this->db->prepare("SELECT COUNT(*) as total
            FROM solicitud_procedimiento
            WHERE procedimiento IS NOT NULL
              AND procedimiento != ''
              AND procedimiento != 'SELECCIONE'
              AND COALESCE(created_at, fecha) BETWEEN :inicio AND :fin");
        $totalStmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);
        $total = (int) $totalStmt->fetchColumn();

        return [
            'solicitudes' => $result,
            'total' => $total
        ];
    }

    public function getTopDoctores(DateTimeInterface $start, DateTimeInterface $end)
    {
        $sql = "SELECT
                pr.cirujano_1,
                COUNT(*) AS total,
                (
                    SELECT u.profile_photo
                    FROM users u
                    WHERE u.profile_photo IS NOT NULL
                      AND u.profile_photo <> ''
                      AND (
                        LOWER(TRIM(u.nombre)) = LOWER(TRIM(pr.cirujano_1))
                        OR LOWER(TRIM(pr.cirujano_1)) LIKE CONCAT('%', LOWER(TRIM(u.nombre)), '%')
                        OR LOWER(TRIM(u.username)) = LOWER(TRIM(pr.cirujano_1))
                        OR LOWER(TRIM(u.email)) = LOWER(TRIM(pr.cirujano_1))
                      )
                    ORDER BY u.id ASC
                    LIMIT 1
                ) AS avatar_path
            FROM protocolo_data pr
            WHERE pr.cirujano_1 IS NOT NULL
              AND pr.cirujano_1 != ''
              AND pr.fecha_inicio BETWEEN :inicio AND :fin
            GROUP BY pr.cirujano_1
            ORDER BY total DESC
            LIMIT 5";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $doctores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($doctores as &$doctor) {
            $doctor['avatar'] = $this->formatProfilePhoto($doctor['avatar_path'] ?? null);
            unset($doctor['avatar_path']);
        }

        return $doctores;
    }

    private function formatProfilePhoto(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (preg_match('#^(?:https?:)?//#i', $path)) {
            return $path;
        }

        return asset(ltrim($path, '/'));
    }

    public function getEstadisticasPorAfiliacion(DateTimeInterface $start, DateTimeInterface $end)
    {
        $sql = "SELECT p.afiliacion, COUNT(*) as total_procedimientos
            FROM protocolo_data pr
            INNER JOIN patient_data p ON pr.hc_number = p.hc_number
            WHERE pr.fecha_inicio BETWEEN ? AND ?
            GROUP BY p.afiliacion";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $start->format('Y-m-d 00:00:00'),
            $end->format('Y-m-d 23:59:59'),
        ]);

        $afiliaciones = [];
        $totales = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $afiliaciones[] = $row['afiliacion'];
            $totales[] = $row['total_procedimientos'];
        }

        return [
            'afiliaciones' => $afiliaciones ?: ['No data'],
            'totales' => $totales ?: [0],
        ];
    }

    public function getEstadosRevisionProtocolos(DateTimeInterface $start, DateTimeInterface $end)
    {
        $sql = "SELECT pr.status, pr.membrete, pr.dieresis, pr.exposicion, pr.hallazgo, pr.operatorio,
                   pr.complicaciones_operatorio, pr.datos_cirugia, pr.procedimientos,
                   pr.lateralidad, pr.tipo_anestesia, pr.diagnosticos, pp.procedimiento_proyectado,
                   pr.cirujano_1, pr.instrumentista, pr.cirujano_2, pr.circulante, pr.primer_ayudante,
                   pr.anestesiologo, pr.segundo_ayudante, pr.ayudante_anestesia, pr.tercer_ayudante
            FROM protocolo_data pr
            LEFT JOIN procedimiento_proyectado pp ON pp.form_id = pr.form_id AND pp.hc_number = pr.hc_number
            WHERE pr.fecha_inicio BETWEEN :inicio AND :fin
            ORDER BY pr.fecha_inicio DESC, pr.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $incompletos = 0;
        $revisados = 0;
        $no_revisados = 0;

        $invalidValues = ['CENTER', 'undefined'];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $required = [
                $row['membrete'], $row['dieresis'], $row['exposicion'], $row['hallazgo'], $row['operatorio'],
                $row['complicaciones_operatorio'], $row['datos_cirugia'], $row['procedimientos'],
                $row['lateralidad'], $row['tipo_anestesia'], $row['diagnosticos'], $row['procedimiento_proyectado']
            ];
            $staff = [
                $row['cirujano_1'], $row['instrumentista'], $row['cirujano_2'], $row['circulante'],
                $row['primer_ayudante'], $row['anestesiologo'], $row['segundo_ayudante'],
                $row['ayudante_anestesia'], $row['tercer_ayudante']
            ];

            if ($row['status'] == 1) {
                $revisados++;
            } else {
                $invalid = false;
                foreach ($required as $field) {
                    foreach ($invalidValues as $v) {
                        if (!empty($field) && stripos($field, $v) !== false) {
                            $invalid = true;
                            break 2;
                        }
                    }
                }

                $staffCount = 0;
                if (!empty($row['cirujano_1'])) {
                    foreach ($staff as $field) {
                        foreach ($invalidValues as $v) {
                            if (!empty($field) && stripos($field, $v) !== false) {
                                $invalid = true;
                                break 2;
                            }
                        }
                        if (!empty($field)) $staffCount++;
                    }
                } else {
                    $invalid = true;
                }

                if (!$invalid && $staffCount >= 5) {
                    $no_revisados++;
                } else {
                    $incompletos++;
                }
            }
        }

        return [
            'incompletos' => $incompletos,
            'revisados' => $revisados,
            'no_revisados' => $no_revisados
        ];
    }

    private function resolveDateRange(): array
    {
        $endParam = $_GET['end_date'] ?? '';
        $startParam = $_GET['start_date'] ?? '';

        $today = new DateTimeImmutable('today');
        $defaultEnd = $today;
        $defaultStart = $today->sub(new DateInterval('P29D'));

        $end = $this->parseDate($endParam) ?? $defaultEnd;
        $start = $this->parseDate($startParam) ?? $defaultStart;

        if ($start > $end) {
            [$start, $end] = [$end->sub(new DateInterval('P29D')), $start];
        }

        return [
            'start' => $start,
            'end' => $end,
            'label' => $start->format('d/m/Y') . ' - ' . $end->format('d/m/Y'),
        ];
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $formats = ['Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $date = DateTimeImmutable::createFromFormat($format, $value);
            if ($date instanceof DateTimeImmutable) {
                return $date;
            }
        }

        return null;
    }

    private function getSolicitudesFunnel(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $sql = "SELECT sp.estado, sp.prioridad, sp.turno, sp.id
            FROM solicitud_procedimiento sp
            WHERE sp.procedimiento IS NOT NULL
              AND sp.procedimiento != ''
              AND sp.procedimiento != 'SELECCIONE'
              AND COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $etapas = [
            'recibido' => 0,
            'llamado' => 0,
            'en-atencion' => 0,
            'revision-codigos' => 0,
            'docs-completos' => 0,
            'aprobacion-anestesia' => 0,
            'listo-para-agenda' => 0,
            'otros' => 0,
        ];

        $totales = [
            'registradas' => 0,
            'agendadas' => 0,
            'urgentes_sin_turno' => 0,
        ];

        $prioridades = [
            'urgente' => 0,
            'alta' => 0,
            'normal' => 0,
            'otros' => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $totales['registradas']++;

            $estadoSlug = $this->slugify((string) ($row['estado'] ?? ''));
            if ($estadoSlug === '') {
                $estadoSlug = 'otros';
            }

            if (!array_key_exists($estadoSlug, $etapas)) {
                $estadoSlug = 'otros';
            }
            $etapas[$estadoSlug]++;

            $prioridad = $this->slugify((string) ($row['prioridad'] ?? ''));
            if (isset($prioridades[$prioridad])) {
                $prioridades[$prioridad]++;
            } else {
                $prioridades['otros']++;
            }

            $turno = trim((string) ($row['turno'] ?? ''));
            if ($turno !== '') {
                $totales['agendadas']++;
            }

            if ($prioridad === 'urgente' && $turno === '') {
                $totales['urgentes_sin_turno']++;
            }
        }

        $conversion = 0.0;
        if ($totales['registradas'] > 0) {
            $conversion = round(($totales['agendadas'] / $totales['registradas']) * 100, 1);
        }

        $conCirugia = $this->countSolicitudesConCirugia($start, $end);

        return [
            'etapas' => $etapas,
            'totales' => array_merge($totales, [
                'con_cirugia' => $conCirugia,
                'conversion_agendada' => $conversion,
            ]),
            'prioridades' => $prioridades,
        ];
    }

    private function countSolicitudesConCirugia(DateTimeInterface $start, DateTimeInterface $end): int
    {
        $sql = "SELECT COUNT(DISTINCT sp.id) AS total
            FROM solicitud_procedimiento sp
            INNER JOIN protocolo_data pr ON pr.form_id = sp.form_id AND pr.hc_number = sp.hc_number
            WHERE sp.procedimiento IS NOT NULL
              AND sp.procedimiento != ''
              AND sp.procedimiento != 'SELECCIONE'
              AND COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio_solicitud AND :fin_solicitud
              AND pr.fecha_inicio BETWEEN :inicio_protocolo AND :fin_protocolo";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio_solicitud' => $start->format('Y-m-d 00:00:00'),
            ':fin_solicitud' => $end->format('Y-m-d 23:59:59'),
            ':inicio_protocolo' => $start->format('Y-m-d 00:00:00'),
            ':fin_protocolo' => $end->format('Y-m-d 23:59:59'),
        ]);

        return (int) $stmt->fetchColumn();
    }

    private function getCrmBacklogStats(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $sql = "SELECT
                SUM(CASE WHEN t.estado IN ('pendiente', 'en_progreso') THEN 1 ELSE 0 END) AS pendientes,
                SUM(CASE WHEN t.estado = 'completado' THEN 1 ELSE 0 END) AS completadas,
                SUM(CASE WHEN t.estado IN ('pendiente', 'en_progreso') AND t.due_date < CURDATE() THEN 1 ELSE 0 END) AS vencidas,
                SUM(CASE WHEN t.estado IN ('pendiente', 'en_progreso') AND DATE(t.due_date) = CURDATE() THEN 1 ELSE 0 END) AS vencen_hoy
            FROM solicitud_crm_tareas t
            INNER JOIN solicitud_procedimiento sp ON sp.id = t.solicitud_id
            WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':inicio' => $start->format('Y-m-d 00:00:00'),
            ':fin' => $end->format('Y-m-d 23:59:59'),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $pendientes = (int) ($row['pendientes'] ?? 0);
        $completadas = (int) ($row['completadas'] ?? 0);
        $vencidas = (int) ($row['vencidas'] ?? 0);
        $vencenHoy = (int) ($row['vencen_hoy'] ?? 0);

        $total = $pendientes + $completadas;
        $avance = $total > 0 ? round(($completadas / $total) * 100, 1) : 0.0;

        return [
            'pendientes' => $pendientes,
            'completadas' => $completadas,
            'vencidas' => $vencidas,
            'vencen_hoy' => $vencenHoy,
            'avance' => $avance,
        ];
    }

    private function getAiSummary(): array
    {
        $service = new AIConfigService($this->db);
        $provider = $service->getActiveProvider();
        $features = [
            'consultas_enfermedad' => $service->isFeatureEnabled(AIConfigService::FEATURE_CONSULTAS_ENFERMEDAD),
            'consultas_plan' => $service->isFeatureEnabled(AIConfigService::FEATURE_CONSULTAS_PLAN),
        ];

        return [
            'provider' => $provider,
            'features' => $features,
            'provider_configured' => $provider !== '',
        ];
    }

    /**
     * @param array<string, array<string, mixed>> $kpiAggregates
     */
    private function fetchDashboardKpiAggregates(KpiQueryService $service, DateTimeInterface $start, DateTimeInterface $end): array
    {
        $keys = [
            'solicitudes.registradas',
            'solicitudes.agendadas',
            'solicitudes.urgentes_sin_turno',
            'solicitudes.con_cirugia',
            'solicitudes.conversion_agendada',
            'crm.tareas.vencidas',
            'crm.tareas.avance',
            'protocolos.revision.no_revisados',
            'protocolos.revision.incompletos',
        ];

        $aggregates = [];

        foreach ($keys as $key) {
            $aggregates[$key] = $service->getAggregatedValue($key, $start, $end, [], true) ?? ['value' => 0.0];
        }

        return $aggregates;
    }

    private function buildKpiCards(array $kpiAggregates, array $aiSummary): array
    {
        $registradas = (int) round($kpiAggregates['solicitudes.registradas']['value'] ?? 0);
        $agendadas = (int) round($kpiAggregates['solicitudes.agendadas']['value'] ?? 0);
        $conversion = (float) ($kpiAggregates['solicitudes.conversion_agendada']['value'] ?? 0.0);
        $conCirugia = (int) round($kpiAggregates['solicitudes.con_cirugia']['value'] ?? 0);
        $urgentesSinTurno = (int) round($kpiAggregates['solicitudes.urgentes_sin_turno']['value'] ?? 0);

        $crmVencidas = (int) round($kpiAggregates['crm.tareas.vencidas']['value'] ?? 0);
        $crmAvance = (float) ($kpiAggregates['crm.tareas.avance']['value'] ?? 0.0);
        $protocolosNoRevisados = (int) round($kpiAggregates['protocolos.revision.no_revisados']['value'] ?? 0);
        $protocolosIncompletos = (int) round($kpiAggregates['protocolos.revision.incompletos']['value'] ?? 0);

        $cards = [
            [
                'title' => 'Solicitudes registradas',
                'value' => $registradas,
                'description' => 'En este periodo',
                'icon' => 'svg-icon/color-svg/1.svg',
                'tag' => $conversion > 0 ? $conversion . '% agendadas' : 'Sin agenda registrada',
            ],
            [
                'title' => 'Agenda confirmada',
                'value' => $agendadas,
                'description' => 'Solicitudes con turno asignado',
                'icon' => 'svg-icon/color-svg/2.svg',
                'tag' => $conCirugia > 0 ? $conCirugia . ' con cirugía' : 'Sin cirugías vinculadas',
            ],
            [
                'title' => 'Urgentes sin turno',
                'value' => $urgentesSinTurno,
                'description' => 'Urgentes pendientes de agenda',
                'icon' => 'svg-icon/color-svg/3.svg',
                'tag' => $urgentesSinTurno > 0 ? 'Revisar backlog' : 'Todo al día',
            ],
        ];

        $cards[] = [
            'title' => 'Tareas CRM vencidas',
            'value' => $crmVencidas,
            'description' => 'Pendientes de seguimiento',
            'icon' => 'svg-icon/color-svg/4.svg',
            'tag' => $crmAvance . '% completadas',
        ];

        $cards[] = [
            'title' => 'Protocolos sin revisar',
            'value' => $protocolosNoRevisados,
            'description' => 'Listos para auditoría final',
            'icon' => 'svg-icon/color-svg/5.svg',
            'tag' => $protocolosIncompletos . ' incompletos',
        ];

        $cards[] = [
            'title' => 'Asistente IA',
            'value' => $aiSummary['provider_configured'] ? 'Activo' : 'Inactivo',
            'description' => $aiSummary['provider_configured'] ? strtoupper($aiSummary['provider']) : 'Configurar proveedor',
            'icon' => 'svg-icon/color-svg/6.svg',
            'tag' => $aiSummary['features']['consultas_enfermedad'] && $aiSummary['features']['consultas_plan']
                ? 'Consultas y planes habilitados'
                : 'Funciones limitadas',
        ];

        return $cards;
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/u', '-', $value);
        return trim($value ?? '', '-');
    }

    private function formatDateRangeForView(array $range): array
    {
        return [
            'label' => $range['label'],
            'start' => $range['start']->format('Y-m-d'),
            'end' => $range['end']->format('Y-m-d'),
        ];
    }
}
