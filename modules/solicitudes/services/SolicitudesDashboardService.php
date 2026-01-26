<?php

namespace Modules\Solicitudes\Services;

use DateTimeImmutable;
use PDO;

class SolicitudesDashboardService
{
    private PDO $db;
    private SolicitudEstadoService $estadoService;

    public function __construct(PDO $db, ?SolicitudEstadoService $estadoService = null)
    {
        $this->db = $db;
        $this->estadoService = $estadoService ?? new SolicitudEstadoService($db);
    }

    public function getSolicitudesPorMes(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(COALESCE(sp.created_at, sp.fecha), '%Y-%m') AS mes, COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
             GROUP BY DATE_FORMAT(COALESCE(sp.created_at, sp.fecha), '%Y-%m')
             ORDER BY mes ASC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $labels = [];
        $totals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['mes'];
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getSolicitudesPorProcedimiento(string $start, string $end, int $limit = 10): array
    {
        $stmt = $this->db->prepare(
            "SELECT CONCAT_WS(' · ',
                        NULLIF(TRIM(sp.procedimiento), ''),
                        NULLIF(TRIM(sp.producto), '')
                    ) AS procedimiento,
                    COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
               AND sp.procedimiento IS NOT NULL
               AND TRIM(sp.procedimiento) <> ''
               AND TRIM(sp.procedimiento) <> 'SELECCIONE'
             GROUP BY CONCAT_WS(' · ',
                        NULLIF(TRIM(sp.procedimiento), ''),
                        NULLIF(TRIM(sp.producto), '')
                    )
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $totals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['procedimiento'] ?: 'Sin procedimiento';
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getSolicitudesPorDoctor(string $start, string $end, int $limit = 15): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(TRIM(sp.doctor), ''), 'Sin asignar') AS doctor, COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
             GROUP BY COALESCE(NULLIF(TRIM(sp.doctor), ''), 'Sin asignar')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':inicio', $start);
        $stmt->bindValue(':fin', $end);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $labels = [];
        $totals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['doctor'];
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getSolicitudesPorAfiliacion(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(TRIM(sp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), 'Sin afiliación') AS afiliacion,
                    COUNT(*) AS total
             FROM solicitud_procedimiento sp
             LEFT JOIN patient_data pd ON sp.hc_number = pd.hc_number
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
             GROUP BY COALESCE(NULLIF(TRIM(sp.afiliacion), ''), NULLIF(TRIM(pd.afiliacion), ''), 'Sin afiliación')
             ORDER BY total DESC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $labels = [];
        $totals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['afiliacion'];
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getSolicitudesPorPrioridad(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(TRIM(sp.prioridad), ''), 'Sin prioridad') AS prioridad, COUNT(*) AS total
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin
             GROUP BY COALESCE(NULLIF(TRIM(sp.prioridad), ''), 'Sin prioridad')
             ORDER BY total DESC"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $labels = [];
        $totals = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $labels[] = $row['prioridad'];
            $totals[] = (int) $row['total'];
        }

        return ['labels' => $labels, 'totals' => $totals];
    }

    public function getKanbanMetrics(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT sp.id, sp.estado
             FROM solicitud_procedimiento sp
             WHERE COALESCE(sp.created_at, sp.fecha) BETWEEN :inicio AND :fin"
        );
        $stmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);

        $solicitudes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $enriched = $this->estadoService->enrichSolicitudes($solicitudes);

        $columns = $this->estadoService->getColumns();
        $wip = [];
        foreach ($columns as $slug => $meta) {
            $wip[$slug] = [
                'label' => $meta['label'],
                'total' => 0,
            ];
        }

        $progressBuckets = [
            '0-25' => 0,
            '25-50' => 0,
            '50-75' => 0,
            '75-100' => 0,
        ];
        $nextStages = [];
        $totalProgress = 0.0;
        $totalSolicitudes = count($enriched);
        $completed = 0;

        foreach ($enriched as $row) {
            $kanban = (string)($row['kanban_estado'] ?? $row['estado'] ?? '');
            if (!isset($wip[$kanban])) {
                $wip[$kanban] = [
                    'label' => $kanban !== '' ? $kanban : 'Sin estado',
                    'total' => 0,
                ];
            }
            $wip[$kanban]['total'] += 1;

            if ($kanban === 'completado') {
                $completed += 1;
            }

            $percent = (float)($row['checklist_progress']['percent'] ?? 0.0);
            $totalProgress += $percent;

            if ($percent < 25) {
                $progressBuckets['0-25'] += 1;
            } elseif ($percent < 50) {
                $progressBuckets['25-50'] += 1;
            } elseif ($percent < 75) {
                $progressBuckets['50-75'] += 1;
            } else {
                $progressBuckets['75-100'] += 1;
            }

            $nextSlug = (string)($row['kanban_next']['slug'] ?? '');
            $nextLabel = (string)($row['kanban_next']['label'] ?? '');
            if ($nextSlug !== '') {
                if (!isset($nextStages[$nextSlug])) {
                    $nextStages[$nextSlug] = [
                        'label' => $nextLabel !== '' ? $nextLabel : $nextSlug,
                        'total' => 0,
                    ];
                }
                $nextStages[$nextSlug]['total'] += 1;
            }
        }

        $avgProgress = $totalSolicitudes > 0 ? round($totalProgress / $totalSolicitudes, 2) : 0.0;

        return [
            'total' => $totalSolicitudes,
            'completed' => $completed,
            'avg_progress' => $avgProgress,
            'wip' => $wip,
            'progress_buckets' => $progressBuckets,
            'next_stages' => $nextStages,
        ];
    }

    public function getMailMetrics(string $start, string $end, int $limit = 10): array
    {
        $statusStmt = $this->db->prepare(
            "SELECT
                SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) AS sent,
                SUM(CASE WHEN status <> 'sent' THEN 1 ELSE 0 END) AS failed,
                COUNT(*) AS total
             FROM solicitud_mail_log
             WHERE COALESCE(sent_at, created_at) BETWEEN :inicio AND :fin"
        );
        $statusStmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);
        $status = $statusStmt->fetch(PDO::FETCH_ASSOC) ?: ['sent' => 0, 'failed' => 0, 'total' => 0];

        $templateStmt = $this->db->prepare(
            "SELECT COALESCE(NULLIF(TRIM(template_key), ''), 'Sin plantilla') AS template_key,
                    COUNT(*) AS total
             FROM solicitud_mail_log
             WHERE COALESCE(sent_at, created_at) BETWEEN :inicio AND :fin
             GROUP BY COALESCE(NULLIF(TRIM(template_key), ''), 'Sin plantilla')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $templateStmt->bindValue(':inicio', $start);
        $templateStmt->bindValue(':fin', $end);
        $templateStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $templateStmt->execute();

        $templateLabels = [];
        $templateTotals = [];
        while ($row = $templateStmt->fetch(PDO::FETCH_ASSOC)) {
            $templateLabels[] = $row['template_key'];
            $templateTotals[] = (int) $row['total'];
        }

        $attachmentStmt = $this->db->prepare(
            "SELECT AVG(attachment_size) AS avg_size, COUNT(attachment_size) AS count_with_attachment
             FROM solicitud_mail_log
             WHERE COALESCE(sent_at, created_at) BETWEEN :inicio AND :fin
               AND attachment_size IS NOT NULL"
        );
        $attachmentStmt->execute([
            ':inicio' => $start,
            ':fin' => $end,
        ]);
        $attachments = $attachmentStmt->fetch(PDO::FETCH_ASSOC) ?: ['avg_size' => null, 'count_with_attachment' => 0];

        $usersStmt = $this->db->prepare(
            "SELECT COALESCE(u.nombre, 'Sin usuario') AS usuario, COUNT(*) AS total
             FROM solicitud_mail_log sml
             LEFT JOIN users u ON u.id = sml.sent_by_user_id
             WHERE COALESCE(sml.sent_at, sml.created_at) BETWEEN :inicio AND :fin
             GROUP BY COALESCE(u.nombre, 'Sin usuario')
             ORDER BY total DESC
             LIMIT :limit"
        );
        $usersStmt->bindValue(':inicio', $start);
        $usersStmt->bindValue(':fin', $end);
        $usersStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $usersStmt->execute();

        $userLabels = [];
        $userTotals = [];
        while ($row = $usersStmt->fetch(PDO::FETCH_ASSOC)) {
            $userLabels[] = $row['usuario'];
            $userTotals[] = (int) $row['total'];
        }

        return [
            'status' => [
                'sent' => (int) $status['sent'],
                'failed' => (int) $status['failed'],
                'total' => (int) $status['total'],
            ],
            'templates' => [
                'labels' => $templateLabels,
                'totals' => $templateTotals,
            ],
            'attachments' => [
                'avg_size' => $attachments['avg_size'] !== null ? (float) $attachments['avg_size'] : null,
                'count_with_attachment' => (int) $attachments['count_with_attachment'],
            ],
            'users' => [
                'labels' => $userLabels,
                'totals' => $userTotals,
            ],
        ];
    }

    public function buildSummary(string $start, string $end): array
    {
        return [
            'range' => [
                'start' => $this->formatRangeDate($start),
                'end' => $this->formatRangeDate($end),
            ],
            'volumen' => [
                'por_mes' => $this->getSolicitudesPorMes($start, $end),
                'por_procedimiento' => $this->getSolicitudesPorProcedimiento($start, $end),
                'por_doctor' => $this->getSolicitudesPorDoctor($start, $end),
                'por_afiliacion' => $this->getSolicitudesPorAfiliacion($start, $end),
                'por_prioridad' => $this->getSolicitudesPorPrioridad($start, $end),
            ],
            'kanban' => $this->getKanbanMetrics($start, $end),
            'cobertura' => $this->getMailMetrics($start, $end),
        ];
    }

    private function formatRangeDate(string $value): string
    {
        try {
            return (new DateTimeImmutable($value))->format('Y-m-d');
        } catch (\Throwable) {
            return $value;
        }
    }
}
