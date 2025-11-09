<?php

namespace Modules\Agenda\Models;

use PDO;

class AgendaModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
     * @param array{
     *     fecha_inicio: string,
     *     fecha_fin: string,
     *     doctor: string|null,
     *     estado: string|null,
     *     sede: string|null,
     *     solo_con_visita: bool
     * } $filters
     *
     * @return array<int, array<string, mixed>>
     */
    public function listarAgenda(array $filters): array
    {
        $sql = <<<'SQL'
            SELECT
                pp.id,
                pp.form_id,
                pp.hc_number,
                TRIM(CONCAT_WS(' ', pd.fname, pd.mname, pd.lname, pd.lname2)) AS paciente,
                pp.procedimiento_proyectado AS procedimiento,
                pp.doctor,
                pp.fecha,
                pp.hora,
                pp.estado_agenda,
                pp.sede_departamento,
                pp.id_sede,
                pp.afiliacion,
                v.id AS visita_id,
                v.fecha_visita,
                v.hora_llegada,
                COALESCE(DATE(pp.fecha), v.fecha_visita) AS fecha_agenda
            FROM procedimiento_proyectado pp
            LEFT JOIN patient_data pd ON pd.hc_number = pp.hc_number
            LEFT JOIN visitas v ON v.id = pp.visita_id
            WHERE 1 = 1
        SQL;

        $params = [
            ':fecha_inicio' => $filters['fecha_inicio'],
            ':fecha_fin' => $filters['fecha_fin'],
        ];

        $sql .= "\n AND COALESCE(DATE(pp.fecha), v.fecha_visita) BETWEEN :fecha_inicio AND :fecha_fin";

        if ($filters['solo_con_visita']) {
            $sql .= "\n AND pp.visita_id IS NOT NULL";
        }

        if ($filters['doctor'] !== null) {
            $sql .= "\n AND pp.doctor = :doctor";
            $params[':doctor'] = $filters['doctor'];
        }

        if ($filters['estado'] !== null) {
            $sql .= "\n AND pp.estado_agenda = :estado";
            $params[':estado'] = $filters['estado'];
        }

        if ($filters['sede'] !== null) {
            $sql .= "\n AND (pp.id_sede = :sede OR pp.sede_departamento = :sede)";
            $params[':sede'] = $filters['sede'];
        }

        $sql .= "\n ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['paciente'] = $row['paciente'] !== null && trim((string) $row['paciente']) !== ''
                ? trim((string) $row['paciente'])
                : null;
            $row['hora_agenda'] = $this->resolverHoraDesdeRow($row);
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    public function listarEstadosAgenda(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT estado_agenda FROM procedimiento_proyectado WHERE estado_agenda IS NOT NULL AND estado_agenda != '' ORDER BY estado_agenda"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * @return array<int, string>
     */
    public function listarDoctores(): array
    {
        $stmt = $this->db->query(
            "SELECT DISTINCT doctor FROM procedimiento_proyectado WHERE doctor IS NOT NULL AND doctor != '' ORDER BY doctor"
        );

        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    }

    /**
     * @return array<int, array{id_sede: string|null, sede_departamento: string|null}>
     */
    public function listarSedes(): array
    {
        $stmt = $this->db->query(
            <<<'SQL'
            SELECT DISTINCT
                NULLIF(pp.id_sede, '') AS id_sede,
                NULLIF(pp.sede_departamento, '') AS sede_departamento
            FROM procedimiento_proyectado pp
            WHERE
                (pp.id_sede IS NOT NULL AND pp.id_sede != '')
                OR (pp.sede_departamento IS NOT NULL AND pp.sede_departamento != '')
            ORDER BY sede_departamento ASC, id_sede ASC
            SQL
        );

        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        $rows = array_values(array_filter($rows, static function (array $row): bool {
            return ($row['id_sede'] ?? null) !== null || ($row['sede_departamento'] ?? null) !== null;
        }));

        return array_map(static function (array $row): array {
            return [
                'id_sede' => $row['id_sede'] ?? null,
                'sede_departamento' => $row['sede_departamento'] ?? null,
            ];
        }, $rows);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function obtenerVisita(int $visitaId): ?array
    {
        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT
                v.id,
                v.hc_number,
                v.fecha_visita,
                v.hora_llegada,
                v.usuario_registro,
                pd.fname,
                pd.mname,
                pd.lname,
                pd.lname2,
                pd.afiliacion,
                pd.celular,
                pd.fecha_nacimiento
            FROM visitas v
            LEFT JOIN patient_data pd ON pd.hc_number = v.hc_number
            WHERE v.id = :visita
            LIMIT 1
            SQL
        );
        $stmt->execute([':visita' => $visitaId]);
        $visita = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$visita) {
            return null;
        }

        $visita['paciente'] = trim(implode(' ', array_filter([
            $visita['fname'] ?? null,
            $visita['mname'] ?? null,
            $visita['lname'] ?? null,
            $visita['lname2'] ?? null,
        ])));

        $visita['procedimientos'] = $this->obtenerProcedimientosPorVisita($visitaId);

        return $visita;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function obtenerProcedimientosPorVisita(int $visitaId): array
    {
        $stmt = $this->db->prepare(
            <<<'SQL'
            SELECT
                pp.id,
                pp.form_id,
                pp.procedimiento_proyectado AS procedimiento,
                pp.doctor,
                pp.fecha,
                pp.hora,
                pp.estado_agenda,
                pp.afiliacion,
                pp.sede_departamento,
                pp.id_sede,
                v.hora_llegada,
                COALESCE(DATE(pp.fecha), v.fecha_visita) AS fecha_agenda
            FROM procedimiento_proyectado pp
            LEFT JOIN visitas v ON v.id = pp.visita_id
            WHERE pp.visita_id = :visita
            ORDER BY fecha_agenda ASC, pp.hora ASC, pp.fecha ASC, v.hora_llegada ASC, pp.form_id ASC
            SQL
        );
        $stmt->execute([':visita' => $visitaId]);
        $procedimientos = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $formIds = array_column($procedimientos, 'form_id');
        $historial = $this->obtenerHistorialEstados($formIds);

        foreach ($procedimientos as &$procedimiento) {
            $formId = $procedimiento['form_id'];
            $procedimiento['historial_estados'] = $historial[$formId] ?? [];
            $procedimiento['hora_agenda'] = $this->resolverHoraDesdeRow($procedimiento);
        }

        return $procedimientos;
    }

    /**
     * @param array<int, string> $formIds
     * @return array<string, array<int, array{estado: string, fecha_hora_cambio: string}>>
     */
    private function obtenerHistorialEstados(array $formIds): array
    {
        if ($formIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($formIds), '?'));
        $sql = sprintf(
            'SELECT form_id, estado, fecha_hora_cambio FROM procedimiento_proyectado_estado WHERE form_id IN (%s) ORDER BY form_id ASC, fecha_hora_cambio ASC',
            $placeholders
        );
        $stmt = $this->db->prepare($sql);
        $stmt->execute($formIds);

        $historial = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $formId = $row['form_id'];
            $historial[$formId][] = [
                'estado' => $row['estado'],
                'fecha_hora_cambio' => $row['fecha_hora_cambio'],
            ];
        }

        return $historial;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolverHoraDesdeRow(array $row): ?string
    {
        $candidatos = [
            $row['hora'] ?? null,
            $row['fecha'] ?? null,
            $row['hora_llegada'] ?? null,
        ];

        foreach ($candidatos as $valor) {
            $hora = $this->formatearHora($valor);
            if ($hora !== null) {
                return $hora;
            }
        }

        return null;
    }

    private function formatearHora(null|string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }

        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        if (preg_match('/\b(\d{2}:\d{2})(?::\d{2})?\b/', $valor, $matches) === 1) {
            return $matches[1];
        }

        $timestamp = strtotime($valor);
        if ($timestamp !== false) {
            return date('H:i', $timestamp);
        }

        return null;
    }
}
