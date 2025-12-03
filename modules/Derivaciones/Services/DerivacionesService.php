<?php

namespace Modules\Derivaciones\Services;

use PDO;

class DerivacionesService
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    /**
    * Retorna derivaciones paginadas para DataTable.
    *
    * @return array{total:int, filtrados:int, datos:array<int, array<string, mixed>>}
    */
    public function obtenerPaginadas(
        int $start,
        int $length,
        string $search,
        string $orderColumn,
        string $orderDir
    ): array {
        $search = trim($search);
        $orderDir = strtoupper($orderDir) === 'ASC' ? 'ASC' : 'DESC';
        $start = max(0, $start);
        $length = max(1, $length);

        $columnMap = [
            'fecha_creacion' => 'd.fecha_creacion',
            'cod_derivacion' => 'd.cod_derivacion',
            'form_id' => 'd.form_id',
            'hc_number' => 'd.hc_number',
            'paciente_nombre' => 'paciente_nombre',
            'referido' => 'd.referido',
            'fecha_registro' => 'd.fecha_registro',
            'fecha_vigencia' => 'd.fecha_vigencia',
            'archivo' => 'd.archivo_derivacion_path',
            'diagnostico' => 'd.diagnostico',
            'sede' => 'd.sede',
            'parentesco' => 'd.parentesco',
        ];
        $orderColumn = $columnMap[$orderColumn] ?? 'd.fecha_creacion';

        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE (
                d.cod_derivacion LIKE :q1 OR
                d.form_id LIKE :q2 OR
                d.hc_number LIKE :q3 OR
                COALESCE(CONCAT_WS(' ', p.fname, p.mname, p.lname, p.lname2), '') LIKE :q4 OR
                d.referido LIKE :q5 OR
                d.diagnostico LIKE :q6 OR
                d.sede LIKE :q7 OR
                d.parentesco LIKE :q8
            )";
            $like = '%' . $search . '%';
            $params = [
                ':q1' => $like,
                ':q2' => $like,
                ':q3' => $like,
                ':q4' => $like,
                ':q5' => $like,
                ':q6' => $like,
                ':q7' => $like,
                ':q8' => $like,
            ];
        }

        $total = (int) $this->db->query('SELECT COUNT(*) FROM derivaciones_form_id')->fetchColumn();

        $stmtCount = $this->db->prepare(
            "SELECT COUNT(*) FROM derivaciones_form_id d
             LEFT JOIN patient_data p ON p.hc_number = d.hc_number
             $where"
        );
        $stmtCount->execute($params);
        $filtrados = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT
                d.id,
                d.cod_derivacion,
                d.form_id,
                d.hc_number,
                d.fecha_creacion,
                d.fecha_registro,
                d.fecha_vigencia,
                d.referido,
                d.diagnostico,
                d.sede,
                d.parentesco,
                d.archivo_derivacion_path,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', p.fname, p.mname, p.lname, p.lname2)), ''), 'Paciente sin nombre') AS paciente_nombre
            FROM derivaciones_form_id d
            LEFT JOIN patient_data p ON p.hc_number = d.hc_number
            $where
            ORDER BY $orderColumn $orderDir
            LIMIT $start, $length
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $datos = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $datos[] = $this->formatearFila($row);
        }

        return [
            'total' => $total,
            'filtrados' => $filtrados,
            'datos' => $datos,
        ];
    }

    public function buscarPorId(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM derivaciones_form_id WHERE id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function formatearFila(array $row): array
    {
        $archivoHtml = '--';
        if (!empty($row['archivo_derivacion_path'])) {
            $archivoHtml = sprintf(
                '<a href="/derivaciones/archivo/%d" class="btn btn-sm btn-primary" target="_blank" rel="noopener">Ver PDF</a>',
                (int) $row['id']
            );
        }

        $accionesHtml = sprintf(
            '<button class="btn btn-sm btn-warning js-scrap-derivacion" data-form-id="%s" data-hc="%s">Actualizar</button>',
            htmlspecialchars((string) $row['form_id'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars((string) $row['hc_number'], ENT_QUOTES, 'UTF-8')
        );

        return [
            'fecha_creacion' => $row['fecha_creacion'],
            'cod_derivacion' => $row['cod_derivacion'],
            'form_id' => $row['form_id'],
            'hc_number' => $row['hc_number'],
            'paciente_nombre' => $row['paciente_nombre'],
            'referido' => $row['referido'],
            'fecha_registro' => $row['fecha_registro'],
            'fecha_vigencia' => $row['fecha_vigencia'],
            'archivo_html' => $archivoHtml,
            'acciones_html' => $accionesHtml,
            'diagnostico' => $row['diagnostico'],
            'sede' => $row['sede'],
            'parentesco' => $row['parentesco'],
        ];
    }
}
