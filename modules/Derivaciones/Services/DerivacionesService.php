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
            'fecha_creacion' => 'COALESCE(f.fecha_creacion, f.created_at)',
            'cod_derivacion' => 'r.referral_code',
            'form_id' => 'f.iess_form_id',
            'hc_number' => 'f.hc_number',
            'paciente_nombre' => "COALESCE(NULLIF(TRIM(CONCAT_WS(' ', p.fname, p.mname, p.lname, p.lname2)), ''), 'Paciente sin nombre')",
            'referido' => 'f.referido',
            'fecha_registro' => 'f.fecha_registro',
            'fecha_vigencia' => 'COALESCE(r.valid_until, f.fecha_vigencia)',
            'archivo' => 'f.archivo_derivacion_path',
            'diagnostico' => 'f.diagnostico',
            'sede' => 'f.sede',
            'parentesco' => 'f.parentesco',
        ];
        $orderColumn = $columnMap[$orderColumn] ?? $columnMap['fecha_creacion'];

        $where = '';
        $params = [];

        if ($search !== '') {
            $where = "WHERE (
                r.referral_code LIKE :q1 OR
                f.iess_form_id LIKE :q2 OR
                f.hc_number LIKE :q3 OR
                COALESCE(CONCAT_WS(' ', p.fname, p.mname, p.lname, p.lname2), '') LIKE :q4 OR
                f.referido LIKE :q5 OR
                f.diagnostico LIKE :q6 OR
                f.sede LIKE :q7 OR
                f.parentesco LIKE :q8
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

        $total = (int) $this->db->query('SELECT COUNT(*) FROM derivaciones_referral_forms')->fetchColumn();

        $stmtCount = $this->db->prepare(
            "SELECT COUNT(*)
             FROM derivaciones_referral_forms df
             JOIN derivaciones_referrals r ON r.id = df.referral_id
             JOIN derivaciones_forms f ON f.id = df.form_id
             LEFT JOIN patient_data p ON p.hc_number = f.hc_number
             $where"
        );
        $stmtCount->execute($params);
        $filtrados = (int) $stmtCount->fetchColumn();

        $sql = "
            SELECT
                df.id,
                r.referral_code AS cod_derivacion,
                f.iess_form_id AS form_id,
                f.hc_number,
                COALESCE(f.fecha_creacion, f.created_at) AS fecha_creacion,
                f.fecha_registro,
                COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                f.referido,
                f.diagnostico,
                f.sede,
                f.parentesco,
                f.archivo_derivacion_path,
                COALESCE(NULLIF(TRIM(CONCAT_WS(' ', p.fname, p.mname, p.lname, p.lname2)), ''), 'Paciente sin nombre') AS paciente_nombre
            FROM derivaciones_referral_forms df
            JOIN derivaciones_referrals r ON r.id = df.referral_id
            JOIN derivaciones_forms f ON f.id = df.form_id
            LEFT JOIN patient_data p ON p.hc_number = f.hc_number
            $where
            ORDER BY $orderColumn $orderDir
            LIMIT :start, :length
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':start', $start, PDO::PARAM_INT);
        $stmt->bindValue(':length', $length, PDO::PARAM_INT);
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
            "SELECT df.id,
                    r.referral_code AS cod_derivacion,
                    f.iess_form_id AS form_id,
                    f.hc_number,
                    f.fecha_creacion,
                    f.fecha_registro,
                    COALESCE(r.valid_until, f.fecha_vigencia) AS fecha_vigencia,
                    f.referido,
                    f.diagnostico,
                    f.sede,
                    f.parentesco,
                    f.archivo_derivacion_path
             FROM derivaciones_referral_forms df
             JOIN derivaciones_referrals r ON r.id = df.referral_id
             JOIN derivaciones_forms f ON f.id = df.form_id
             WHERE df.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $this->ensureArchivoPath($row);
            return $row;
        }

        $legacyStmt = $this->db->prepare(
            "SELECT id,
                    cod_derivacion,
                    form_id,
                    hc_number,
                    fecha_creacion,
                    fecha_registro,
                    fecha_vigencia,
                    referido,
                    diagnostico,
                    sede,
                    parentesco,
                    archivo_derivacion_path
             FROM derivaciones_form_id
             WHERE id = :id
             LIMIT 1"
        );
        $legacyStmt->execute([':id' => $id]);
        $legacyRow = $legacyStmt->fetch(PDO::FETCH_ASSOC);

        if ($legacyRow) {
            $this->ensureArchivoPath($legacyRow);
        }

        return $legacyRow ?: null;
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

    private function ensureArchivoPath(array &$row): void
    {
        if (!empty($row['archivo_derivacion_path'])) {
            return;
        }

        $hcNumber = $row['hc_number'] ?? null;
        $codigo = $row['cod_derivacion'] ?? null;

        if (!$hcNumber || !$codigo) {
            return;
        }

        $safeHc = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $hcNumber));
        $safeCodigo = preg_replace('/[^A-Za-z0-9_-]+/', '_', trim((string) $codigo));

        if ($safeHc === '' || $safeCodigo === '') {
            return;
        }

        $path = sprintf(
            'storage/derivaciones/%s/%s/derivacion_%s_%s.pdf',
            $safeHc,
            $safeCodigo,
            $safeHc,
            $safeCodigo
        );

        $absolute = BASE_PATH . '/' . ltrim($path, '/');
        if (is_file($absolute)) {
            $row['archivo_derivacion_path'] = $path;
        }
    }
}
