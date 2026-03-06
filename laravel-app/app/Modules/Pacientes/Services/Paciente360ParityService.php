<?php

namespace App\Modules\Pacientes\Services;

use InvalidArgumentException;
use PDO;
use Throwable;

class Paciente360ParityService
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * @return array{
     *     section:string,
     *     summary:array<string,int>,
     *     filters:array<string,string>,
     *     total_rows:int,
     *     rows:array<int,array<string,mixed>>
     * }
     */
    public function getSection(string $hcNumber, string $section, int $limit = 25, array $filters = []): array
    {
        $hcNumber = $this->normalizeHcNumber($hcNumber);
        if ($hcNumber === '') {
            throw new InvalidArgumentException('hc_number inválido.');
        }

        $section = strtolower(trim($section));
        $limit = max(1, min(100, $limit));
        $filters = $this->normalizeFilters($filters);
        $hasActiveFilters = $this->hasActiveFilters($filters);
        $queryLimit = $hasActiveFilters ? max(150, $limit * 10) : $limit;

        $rows = match ($section) {
            'solicitudes' => $this->fetchSolicitudes($hcNumber, $queryLimit),
            'examenes' => $this->fetchExamenes($hcNumber, $queryLimit),
            'agenda' => $this->fetchAgenda($hcNumber, $queryLimit),
            'consultas' => $this->fetchConsultas($hcNumber, $queryLimit),
            'protocolos' => $this->fetchProtocolos($hcNumber, $queryLimit),
            'prefacturas' => $this->fetchPrefacturas($hcNumber, $queryLimit),
            'derivaciones' => $this->fetchDerivaciones($hcNumber, $queryLimit),
            'recetas' => $this->fetchRecetas($hcNumber, $queryLimit),
            'crm' => $this->fetchCrm($hcNumber, $queryLimit),
            default => throw new InvalidArgumentException(
                'Sección no soportada. Usa: solicitudes, examenes, agenda, consultas, protocolos, prefacturas, derivaciones, recetas o crm.'
            ),
        };

        $rows = $this->applyFiltersToRows($rows, $filters);
        $filteredTotalRows = count($rows);
        $rows = array_slice($rows, 0, $limit);

        $rows = array_map(function (array $row) use ($section, $hcNumber): array {
            return $this->decorateRow($section, $hcNumber, $row);
        }, $rows);

        $summary = $this->buildSummary($hcNumber);

        return [
            'section' => $section,
            'summary' => $summary,
            'filters' => $filters,
            'total_rows' => $hasActiveFilters ? $filteredTotalRows : ($summary[$section] ?? count($rows)),
            'rows' => $rows,
        ];
    }

    /**
     * @return array<string,int>
     */
    private function buildSummary(string $hcNumber): array
    {
        $crmLeads = $this->safeCount(
            'SELECT COUNT(*) FROM crm_leads WHERE hc_number = :hc',
            [':hc' => $hcNumber]
        );
        $crmProyectos = $this->safeCount(
            'SELECT COUNT(*) FROM crm_projects WHERE hc_number = :hc',
            [':hc' => $hcNumber]
        );
        $crmTareas = $this->safeCount(
            'SELECT COUNT(*) FROM crm_tasks WHERE hc_number = :hc',
            [':hc' => $hcNumber]
        );

        return [
            'solicitudes' => $this->safeCount(
                'SELECT COUNT(*) FROM solicitud_procedimiento WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'examenes' => $this->safeCount(
                'SELECT COUNT(*) FROM consulta_examenes WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'agenda' => $this->safeCount(
                'SELECT COUNT(*) FROM procedimiento_proyectado WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'consultas' => $this->safeCount(
                'SELECT COUNT(*) FROM consulta_data WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'protocolos' => $this->safeCount(
                'SELECT COUNT(*) FROM protocolo_data WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'prefacturas' => $this->safeCount(
                'SELECT COUNT(*) FROM prefactura_paciente WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'derivaciones' => $this->safeCount(
                'SELECT COUNT(*) FROM derivaciones_forms WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ) + $this->safeCount(
                'SELECT COUNT(*) FROM derivaciones_form_id WHERE hc_number = :hc',
                [':hc' => $hcNumber]
            ),
            'recetas' => $this->safeCount(
                'SELECT COUNT(*)
                 FROM recetas_items re
                 WHERE EXISTS (
                    SELECT 1
                    FROM procedimiento_proyectado pp
                    WHERE pp.form_id = re.form_id
                      AND pp.hc_number = :hc
                 )',
                [':hc' => $hcNumber]
            ),
            'crm' => $crmLeads + $crmProyectos + $crmTareas,
            'crm_leads' => $crmLeads,
            'crm_proyectos' => $crmProyectos,
            'crm_tareas' => $crmTareas,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchSolicitudes(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT
                sp.id,
                sp.form_id,
                sp.created_at,
                sp.estado,
                sp.prioridad,
                sp.procedimiento,
                sp.doctor,
                sp.ojo
            FROM solicitud_procedimiento sp
            WHERE sp.hc_number = :hc
            ORDER BY sp.created_at DESC, sp.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'form_id' => (string)($row['form_id'] ?? ''),
                'fecha' => (string)($row['created_at'] ?? ''),
                'estado' => (string)($row['estado'] ?? ''),
                'prioridad' => (string)($row['prioridad'] ?? ''),
                'procedimiento' => (string)($row['procedimiento'] ?? ''),
                'doctor' => (string)($row['doctor'] ?? ''),
                'ojo' => (string)($row['ojo'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchExamenes(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT
                ce.id,
                ce.form_id,
                ce.consulta_fecha,
                ce.created_at,
                ce.estado,
                ce.prioridad,
                ce.examen_nombre,
                ce.examen_codigo,
                ce.doctor,
                ce.turno
            FROM consulta_examenes ce
            WHERE ce.hc_number = :hc
            ORDER BY COALESCE(ce.consulta_fecha, ce.created_at) DESC, ce.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'form_id' => (string)($row['form_id'] ?? ''),
                'fecha' => (string)($row['consulta_fecha'] ?? ($row['created_at'] ?? '')),
                'estado' => (string)($row['estado'] ?? ''),
                'prioridad' => (string)($row['prioridad'] ?? ''),
                'examen' => (string)($row['examen_nombre'] ?? ($row['examen_codigo'] ?? '')),
                'doctor' => (string)($row['doctor'] ?? ''),
                'turno' => (string)($row['turno'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchAgenda(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT
                pp.form_id,
                pp.hc_number,
                pp.procedimiento_proyectado AS procedimiento,
                pp.doctor,
                pp.fecha,
                pp.hora,
                pp.estado_agenda,
                pp.sede_departamento,
                pp.id_sede,
                v.fecha_visita,
                v.hora_llegada
            FROM procedimiento_proyectado pp
            LEFT JOIN visitas v ON v.id = pp.visita_id
            WHERE pp.hc_number = :hc
            ORDER BY COALESCE(pp.fecha, v.fecha_visita) DESC, pp.form_id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        $historialByForm = $this->fetchAgendaStatusHistory(array_column($rows, 'form_id'));

        return array_map(
            static function (array $row) use ($historialByForm): array {
                $formId = (string)($row['form_id'] ?? '');

                return [
                    'form_id' => $formId,
                    'fecha' => (string)($row['fecha'] ?? ($row['fecha_visita'] ?? '')),
                    'hora' => (string)($row['hora'] ?? ($row['hora_llegada'] ?? '')),
                    'estado' => (string)($row['estado_agenda'] ?? ''),
                    'procedimiento' => (string)($row['procedimiento'] ?? ''),
                    'doctor' => (string)($row['doctor'] ?? ''),
                    'sede' => (string)($row['sede_departamento'] ?? ($row['id_sede'] ?? '')),
                    'historial_estados' => $historialByForm[$formId] ?? [],
                ];
            },
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchConsultas(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM consulta_data
            WHERE hc_number = :hc
            ORDER BY fecha DESC, form_id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'form_id' => (string)($row['form_id'] ?? ''),
                'fecha' => (string)($row['fecha'] ?? ($row['created_at'] ?? '')),
                'motivo_consulta' => (string)($row['motivo_consulta'] ?? ''),
                'enfermedad_actual' => (string)($row['enfermedad_actual'] ?? ''),
                'plan' => (string)($row['plan'] ?? ''),
                'diagnosticos' => (string)($row['diagnosticos'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchProtocolos(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM protocolo_data
            WHERE hc_number = :hc
            ORDER BY fecha_inicio DESC, form_id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'form_id' => (string)($row['form_id'] ?? ''),
                'fecha_inicio' => (string)($row['fecha_inicio'] ?? ''),
                'membrete' => (string)($row['membrete'] ?? ''),
                'status' => (string)($row['status'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchPrefacturas(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT *
            FROM prefactura_paciente
            WHERE hc_number = :hc
            ORDER BY fecha_creacion DESC, id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'form_id' => (string)($row['form_id'] ?? ''),
                'fecha_creacion' => (string)($row['fecha_creacion'] ?? ''),
                'cod_derivacion' => (string)($row['cod_derivacion'] ?? ''),
                'fecha_vigencia' => (string)($row['fecha_vigencia'] ?? ''),
                'referido' => (string)($row['referido'] ?? ''),
                'sede' => (string)($row['sede'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchDerivaciones(string $hcNumber, int $limit): array
    {
        $rows = [];

        $sqlModern = <<<'SQL'
            SELECT
                f.id,
                f.iess_form_id AS form_id,
                f.hc_number,
                COALESCE(r.referral_code, '') AS cod_derivacion,
                COALESCE(f.fecha_creacion, f.created_at, f.fecha_registro) AS fecha_evento,
                COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                f.referido,
                f.diagnostico,
                f.sede,
                f.parentesco
            FROM derivaciones_forms f
            LEFT JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
            LEFT JOIN derivaciones_referrals r ON r.id = rf.referral_id
            WHERE f.hc_number = :hc
            ORDER BY fecha_evento DESC, f.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sqlModern);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $modernRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($modernRows as $row) {
                $rows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'form_id' => (string)($row['form_id'] ?? ''),
                    'codigo' => (string)($row['cod_derivacion'] ?? ''),
                    'fecha' => (string)($row['fecha_evento'] ?? ''),
                    'fecha_vigencia' => (string)($row['fecha_vigencia'] ?? ''),
                    'referido' => (string)($row['referido'] ?? ''),
                    'diagnostico' => (string)($row['diagnostico'] ?? ''),
                    'sede' => (string)($row['sede'] ?? ''),
                    'parentesco' => (string)($row['parentesco'] ?? ''),
                    'origen' => 'nuevo',
                ];
            }
        } catch (Throwable) {
            // Ignorar: esquema nuevo de derivaciones puede no existir en algunos entornos.
        }

        $sqlLegacy = <<<'SQL'
            SELECT
                id,
                form_id,
                cod_derivacion,
                COALESCE(fecha_creacion, fecha_registro) AS fecha_evento,
                fecha_vigencia,
                referido,
                diagnostico,
                sede,
                parentesco
            FROM derivaciones_form_id
            WHERE hc_number = :hc
            ORDER BY fecha_evento DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sqlLegacy);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $legacyRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($legacyRows as $row) {
                $rows[] = [
                    'id' => (int)($row['id'] ?? 0),
                    'form_id' => (string)($row['form_id'] ?? ''),
                    'codigo' => (string)($row['cod_derivacion'] ?? ''),
                    'fecha' => (string)($row['fecha_evento'] ?? ''),
                    'fecha_vigencia' => (string)($row['fecha_vigencia'] ?? ''),
                    'referido' => (string)($row['referido'] ?? ''),
                    'diagnostico' => (string)($row['diagnostico'] ?? ''),
                    'sede' => (string)($row['sede'] ?? ''),
                    'parentesco' => (string)($row['parentesco'] ?? ''),
                    'origen' => 'legacy',
                ];
            }
        } catch (Throwable) {
            // Ignorar: puede existir solo el esquema nuevo.
        }

        if ($rows === []) {
            return [];
        }

        $seen = [];
        $unique = [];
        foreach ($rows as $row) {
            $key = implode('|', [
                trim((string)($row['form_id'] ?? '')),
                trim((string)($row['codigo'] ?? '')),
                trim((string)($row['fecha'] ?? '')),
                trim((string)($row['referido'] ?? '')),
            ]);

            if ($key === '|||') {
                continue;
            }

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $unique[] = $row;
        }

        usort($unique, static function (array $a, array $b): int {
            return strtotime((string)($b['fecha'] ?? '')) <=> strtotime((string)($a['fecha'] ?? ''));
        });

        return array_slice($unique, 0, $limit);
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchRecetas(string $hcNumber, int $limit): array
    {
        $sql = <<<'SQL'
            SELECT
                re.id,
                re.form_id,
                re.created_at,
                re.updated_at,
                re.producto,
                re.dosis,
                re.pauta,
                re.cantidad,
                re.total_farmacia,
                re.estado_receta,
                re.vias,
                (
                    SELECT pp.doctor
                    FROM procedimiento_proyectado pp
                    WHERE pp.form_id = re.form_id
                      AND pp.hc_number = :hc_doctor
                    ORDER BY pp.id DESC
                    LIMIT 1
                ) AS doctor,
                (
                    SELECT pp.procedimiento_proyectado
                    FROM procedimiento_proyectado pp
                    WHERE pp.form_id = re.form_id
                      AND pp.hc_number = :hc_proc
                    ORDER BY pp.id DESC
                    LIMIT 1
                ) AS procedimiento
            FROM recetas_items re
            WHERE EXISTS (
                SELECT 1
                FROM procedimiento_proyectado pp_exists
                WHERE pp_exists.form_id = re.form_id
                  AND pp_exists.hc_number = :hc_exists
            )
            ORDER BY re.created_at DESC, re.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':hc_doctor', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':hc_proc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':hc_exists', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }

        return array_map(
            static fn(array $row): array => [
                'id' => (int)($row['id'] ?? 0),
                'form_id' => (string)($row['form_id'] ?? ''),
                'fecha' => (string)($row['created_at'] ?? ($row['updated_at'] ?? '')),
                'producto' => (string)($row['producto'] ?? ''),
                'dosis' => (string)($row['dosis'] ?? ($row['pauta'] ?? '')),
                'cantidad' => (string)($row['cantidad'] ?? ''),
                'total_farmacia' => (string)($row['total_farmacia'] ?? ''),
                'estado' => (string)($row['estado_receta'] ?? ''),
                'via' => (string)($row['vias'] ?? ''),
                'doctor' => (string)($row['doctor'] ?? ''),
                'procedimiento' => (string)($row['procedimiento'] ?? ''),
            ],
            $rows
        );
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function fetchCrm(string $hcNumber, int $limit): array
    {
        $rows = [];

        $leadSql = <<<'SQL'
            SELECT
                l.id,
                l.name,
                l.status,
                l.source,
                l.created_at,
                l.updated_at,
                u.nombre AS responsable
            FROM crm_leads l
            LEFT JOIN users u ON u.id = l.assigned_to
            WHERE l.hc_number = :hc
            ORDER BY l.updated_at DESC, l.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($leadSql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $leadRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($leadRows as $row) {
                $rows[] = [
                    'tipo' => 'Lead',
                    'id' => (int)($row['id'] ?? 0),
                    'fecha' => (string)($row['updated_at'] ?? ($row['created_at'] ?? '')),
                    'titulo' => (string)($row['name'] ?? ''),
                    'estado' => (string)($row['status'] ?? ''),
                    'detalle' => (string)($row['source'] ?? ''),
                    'responsable' => (string)($row['responsable'] ?? ''),
                    'form_id' => '',
                ];
            }
        } catch (Throwable) {
            // Ignorar: CRM puede no estar desplegado completo.
        }

        $projectSql = <<<'SQL'
            SELECT
                p.id,
                p.title,
                p.status,
                p.form_id,
                p.created_at,
                p.updated_at,
                u.nombre AS responsable
            FROM crm_projects p
            LEFT JOIN users u ON u.id = p.owner_id
            WHERE p.hc_number = :hc
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($projectSql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $projectRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($projectRows as $row) {
                $rows[] = [
                    'tipo' => 'Proyecto',
                    'id' => (int)($row['id'] ?? 0),
                    'fecha' => (string)($row['updated_at'] ?? ($row['created_at'] ?? '')),
                    'titulo' => (string)($row['title'] ?? ''),
                    'estado' => (string)($row['status'] ?? ''),
                    'detalle' => '',
                    'responsable' => (string)($row['responsable'] ?? ''),
                    'form_id' => (string)($row['form_id'] ?? ''),
                ];
            }
        } catch (Throwable) {
            // Ignorar: CRM puede no estar desplegado completo.
        }

        $taskSql = <<<'SQL'
            SELECT
                t.id,
                t.title,
                t.status,
                t.priority,
                t.form_id,
                t.source_module,
                t.created_at,
                t.updated_at,
                u.nombre AS responsable
            FROM crm_tasks t
            LEFT JOIN users u ON u.id = t.assigned_to
            WHERE t.hc_number = :hc
            ORDER BY t.updated_at DESC, t.id DESC
            LIMIT :limit
        SQL;

        try {
            $stmt = $this->db->prepare($taskSql);
            $stmt->bindValue(':hc', $hcNumber, PDO::PARAM_STR);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            $taskRows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            foreach ($taskRows as $row) {
                $detalle = trim(implode(' · ', array_filter([
                    (string)($row['priority'] ?? ''),
                    (string)($row['source_module'] ?? ''),
                ], static fn(string $value): bool => $value !== '')));

                $rows[] = [
                    'tipo' => 'Tarea',
                    'id' => (int)($row['id'] ?? 0),
                    'fecha' => (string)($row['updated_at'] ?? ($row['created_at'] ?? '')),
                    'titulo' => (string)($row['title'] ?? ''),
                    'estado' => (string)($row['status'] ?? ''),
                    'detalle' => $detalle,
                    'responsable' => (string)($row['responsable'] ?? ''),
                    'form_id' => (string)($row['form_id'] ?? ''),
                ];
            }
        } catch (Throwable) {
            // Ignorar: CRM puede no estar desplegado completo.
        }

        if ($rows === []) {
            return [];
        }

        usort($rows, static function (array $a, array $b): int {
            return strtotime((string)($b['fecha'] ?? '')) <=> strtotime((string)($a['fecha'] ?? ''));
        });

        return array_slice($rows, 0, $limit);
    }

    /**
     * @param array<string,mixed> $filters
     * @return array{date_from:string,date_to:string,estado:string,search:string}
     */
    private function normalizeFilters(array $filters): array
    {
        return [
            'date_from' => trim((string)($filters['date_from'] ?? '')),
            'date_to' => trim((string)($filters['date_to'] ?? '')),
            'estado' => trim((string)($filters['estado'] ?? '')),
            'search' => trim((string)($filters['search'] ?? '')),
        ];
    }

    /**
     * @param array{date_from:string,date_to:string,estado:string,search:string} $filters
     */
    private function hasActiveFilters(array $filters): bool
    {
        return $filters['date_from'] !== ''
            || $filters['date_to'] !== ''
            || $filters['estado'] !== ''
            || $filters['search'] !== '';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     * @param array{date_from:string,date_to:string,estado:string,search:string} $filters
     * @return array<int,array<string,mixed>>
     */
    private function applyFiltersToRows(array $rows, array $filters): array
    {
        if (!$this->hasActiveFilters($filters)) {
            return $rows;
        }

        $dateFromTs = $filters['date_from'] !== '' ? strtotime($filters['date_from'] . ' 00:00:00') : null;
        $dateToTs = $filters['date_to'] !== '' ? strtotime($filters['date_to'] . ' 23:59:59') : null;
        $estadoNeedle = strtolower($filters['estado']);
        $searchNeedle = strtolower($filters['search']);

        $filtered = [];
        foreach ($rows as $row) {
            if ($estadoNeedle !== '') {
                $estadoCandidates = [
                    strtolower(trim((string)($row['estado'] ?? ''))),
                    strtolower(trim((string)($row['status'] ?? ''))),
                ];
                if (!in_array($estadoNeedle, $estadoCandidates, true)) {
                    continue;
                }
            }

            if ($searchNeedle !== '') {
                $haystackParts = [];
                foreach ($row as $value) {
                    if (is_scalar($value)) {
                        $haystackParts[] = strtolower(trim((string)$value));
                    }
                }
                $haystack = implode(' | ', array_filter($haystackParts, static fn(string $part): bool => $part !== ''));
                if ($haystack === '' || strpos($haystack, $searchNeedle) === false) {
                    continue;
                }
            }

            if ($dateFromTs !== null || $dateToTs !== null) {
                $rowDate = $this->resolveRowDate($row);
                if ($rowDate === null) {
                    continue;
                }

                if ($dateFromTs !== null && $rowDate < $dateFromTs) {
                    continue;
                }
                if ($dateToTs !== null && $rowDate > $dateToTs) {
                    continue;
                }
            }

            $filtered[] = $row;
        }

        return $filtered;
    }

    /**
     * @param array<string,mixed> $row
     */
    private function resolveRowDate(array $row): ?int
    {
        $dateCandidates = [
            $row['fecha'] ?? null,
            $row['fecha_inicio'] ?? null,
            $row['fecha_creacion'] ?? null,
            $row['fecha_evento'] ?? null,
            $row['updated_at'] ?? null,
            $row['created_at'] ?? null,
        ];

        foreach ($dateCandidates as $candidate) {
            $value = trim((string)$candidate);
            if ($value === '') {
                continue;
            }

            $timestamp = strtotime($value);
            if ($timestamp !== false) {
                return $timestamp;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    private function decorateRow(string $section, string $hcNumber, array $row): array
    {
        $formId = trim((string)($row['form_id'] ?? ''));
        $recordId = isset($row['id']) ? trim((string)$row['id']) : '';

        $row['section'] = $section;
        $row['hc_number'] = $hcNumber;
        $row['record_id'] = $recordId;
        $row['links'] = $this->buildLinks($section, $hcNumber, $formId, $recordId);

        return $row;
    }

    /**
     * @return array<string,string>
     */
    private function buildLinks(string $section, string $hcNumber, string $formId, string $recordId): array
    {
        $links = [
            'paciente' => '/pacientes/detalles?hc_number=' . rawurlencode($hcNumber),
        ];

        if (in_array($section, ['solicitudes', 'prefacturas'], true)) {
            $links['modulo'] = '/solicitudes';
            if ($formId !== '') {
                $links['derivacion'] = '/solicitudes/derivacion?hc_number=' . rawurlencode($hcNumber) . '&form_id=' . rawurlencode($formId);
            }
        } elseif ($section === 'examenes') {
            $links['modulo'] = '/examenes';
            if ($formId !== '') {
                $links['derivacion'] = '/examenes/derivacion?hc_number=' . rawurlencode($hcNumber) . '&form_id=' . rawurlencode($formId);
            }
        } elseif ($section === 'agenda') {
            $links['modulo'] = '/agenda';
        } elseif ($section === 'derivaciones') {
            $links['modulo'] = '/derivaciones';
            if ($recordId !== '') {
                $links['archivo'] = '/derivaciones/archivo/' . rawurlencode($recordId);
            }
        } elseif ($section === 'recetas') {
            $links['modulo'] = '/farmacia';
        } elseif ($section === 'crm') {
            $links['modulo'] = '/crm';
        }

        return $links;
    }

    /**
     * @param array<int,mixed> $formIds
     * @return array<string,array<int,array{estado:string,fecha_hora_cambio:string}>>
     */
    private function fetchAgendaStatusHistory(array $formIds): array
    {
        $formIds = array_values(array_filter(array_map(static fn($value): string => trim((string)$value), $formIds)));
        if ($formIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $sql = sprintf(
            'SELECT form_id, estado, fecha_hora_cambio FROM procedimiento_proyectado_estado WHERE form_id IN (%s) ORDER BY form_id ASC, fecha_hora_cambio ASC',
            $placeholders
        );

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($formIds);
        } catch (Throwable) {
            return [];
        }

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $formId = (string)($row['form_id'] ?? '');
            if ($formId === '') {
                continue;
            }

            $result[$formId][] = [
                'estado' => (string)($row['estado'] ?? ''),
                'fecha_hora_cambio' => (string)($row['fecha_hora_cambio'] ?? ''),
            ];
        }

        return $result;
    }

    /**
     * @param array<string,mixed> $params
     */
    private function safeCount(string $sql, array $params): int
    {
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return (int)$stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function normalizeHcNumber(string $value): string
    {
        return strtoupper(trim($value));
    }
}
