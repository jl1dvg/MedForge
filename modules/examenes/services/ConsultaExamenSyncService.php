<?php

namespace Modules\Examenes\Services;

use DateTimeImmutable;
use PDO;
use PDOException;

class ConsultaExamenSyncService
{
    private PDO $pdo;
    /** @var array<string, bool>|null */
    private ?array $consultaDataColumns = null;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function syncFromPayload(string $formId, string $hcNumber, ?string $doctor, ?string $solicitante, ?string $fechaConsulta, array $examenes): int
    {
        $fecha = $this->parseFecha($fechaConsulta);
        $normalizados = $this->normalizeExamenes($examenes);

        return $this->syncNormalized($formId, $hcNumber, $doctor, $solicitante, $fecha, $normalizados);
    }

    public function syncFromConsultaRow(array $row): int
    {
        $formId = (string) ($row['form_id'] ?? '');
        $hcNumber = (string) ($row['hc_number'] ?? '');

        if ($formId === '' || $hcNumber === '') {
            return 0;
        }

        $doctor = $this->resolveDoctorFromRow($row);
        $solicitante = isset($row['solicitante']) ? $this->sanitizeText($row['solicitante']) : $doctor;
        $fecha = $this->parseFecha($row['fecha'] ?? $row['fecha_consulta'] ?? null);

        $normalizados = $this->normalizeExamenes($this->decodeExamenes($row['examenes'] ?? []));

        return $this->syncNormalized($formId, $hcNumber, $doctor, $solicitante, $fecha, $normalizados);
    }

    /**
     * @param callable|null $callback Recibe (array $row, array $normalizados, bool $persisted, bool $skipped)
     */
    public function backfillFromConsultaData(?string $hcNumber = null, ?string $formId = null, ?int $limit = null, bool $dryRun = false, ?callable $callback = null): array
    {
        $columns = $this->buildConsultaDataSelectColumns('cd');
        $columns[] = 'pp.doctor AS doctor_pp';
        $sql = sprintf(
            'SELECT %s FROM consulta_data cd LEFT JOIN procedimiento_proyectado pp ON pp.form_id = cd.form_id WHERE cd.examenes IS NOT NULL',
            implode(', ', $columns)
        );
        $params = [];

        if ($hcNumber !== null && $hcNumber !== '') {
            $sql .= ' AND hc_number = :hc';
            $params[':hc'] = $hcNumber;
        }

        if ($formId !== null && $formId !== '') {
            $sql .= ' AND form_id = :form';
            $params[':form'] = $formId;
        }

        $orderParts = [];
        if ($this->consultaDataHasColumn('fecha')) {
            $orderParts[] = 'cd.fecha ASC';
        }
        if ($this->consultaDataHasColumn('fecha_consulta')) {
            $orderParts[] = 'cd.fecha_consulta ASC';
        }
        $orderParts[] = 'cd.form_id ASC';
        $sql .= ' ORDER BY ' . implode(', ', $orderParts);

        if ($limit !== null) {
            $sql .= ' LIMIT :limit';
        }

        $stmt = $this->pdo->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        if ($limit !== null) {
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        }

        $stmt->execute();

        $stats = [
            'processed' => 0,
            'with_exams' => 0,
            'persisted' => 0,
            'skipped' => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats['processed']++;

            $examenes = $this->decodeExamenes($row['examenes'] ?? []);
            $normalizados = $this->normalizeExamenes($examenes);
            $doctor = $this->resolveDoctorFromRow($row);
            $solicitante = isset($row['solicitante']) ? $this->sanitizeText($row['solicitante']) : $doctor;
            $fecha = $this->parseFecha($row['fecha'] ?? $row['fecha_consulta'] ?? null);

            if (empty($normalizados)) {
                $stats['skipped']++;
                if (!$dryRun) {
                    $this->syncNormalized(
                        (string) $row['form_id'],
                        (string) $row['hc_number'],
                        $doctor,
                        $solicitante,
                        $fecha,
                        []
                    );
                }
                if ($callback !== null) {
                    $callback($row, [], false, true);
                }
                continue;
            }

            $stats['with_exams'] += count($normalizados);

            if ($dryRun) {
                if ($callback !== null) {
                    $callback($row, $normalizados, false, false);
                }
                continue;
            }

            $inserted = $this->syncNormalized(
                (string) $row['form_id'],
                (string) $row['hc_number'],
                $doctor,
                $solicitante,
                $fecha,
                $normalizados
            );

            $stats['persisted'] += $inserted;

            if ($callback !== null) {
                $callback($row, $normalizados, true, false);
            }
        }

        return $stats;
    }

    private function decodeExamenes($examenesRaw): array
    {
        if (is_array($examenesRaw)) {
            return $examenesRaw;
        }

        if (!is_string($examenesRaw)) {
            return [];
        }

        $texto = trim($examenesRaw);
        if ($texto === '' || $texto === '[]' || strtolower($texto) === 'null') {
            return [];
        }

        $decoded = json_decode($texto, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return [];
    }

    private function normalizeExamenes(array $examenes): array
    {
        $normalizados = [];
        foreach ($examenes as $examen) {
            if (!is_array($examen)) {
                continue;
            }
            $normalizado = $this->normalizarExamen($examen);
            if ($normalizado === null) {
                continue;
            }
            $normalizados[] = $normalizado;
        }

        if (empty($normalizados)) {
            return $normalizados;
        }

        return $this->dedupeNormalizedExamenes($normalizados);
    }

    /**
     * @param array<int, array<string, mixed>> $normalizados
     * @return array<int, array<string, mixed>>
     */
    private function dedupeNormalizedExamenes(array $normalizados): array
    {
        $dedupeKey = static function (array $item): string {
            $codigo = $item['codigo'] ?? '';
            $nombre = $item['nombre'] ?? '';

            if (function_exists('mb_substr')) {
                $nombre = mb_substr($nombre, 0, 120, 'UTF-8');
            } else {
                $nombre = substr($nombre, 0, 120);
            }

            return sprintf('%s|%s', $codigo ?? '', $nombre);
        };

        $resultado = [];
        $vistos = [];

        foreach ($normalizados as $item) {
            $key = $dedupeKey($item);
            if (isset($vistos[$key])) {
                continue;
            }

            $vistos[$key] = true;
            $resultado[] = $item;
        }

        return $resultado;
    }

    private function syncNormalized(string $formId, string $hcNumber, ?string $doctor, ?string $solicitante, ?DateTimeImmutable $fecha, array $normalizados): int
    {
        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM consulta_examenes WHERE form_id = :form_id AND hc_number = :hc');
            $deleteStmt->execute([
                ':form_id' => $formId,
                ':hc' => $hcNumber,
            ]);

            if (empty($normalizados)) {
                $this->pdo->commit();
                return 0;
            }

            $insertSql = 'INSERT INTO consulta_examenes (form_id, hc_number, consulta_fecha, doctor, solicitante, examen_codigo, examen_nombre, lateralidad, prioridad, observaciones, estado, turno, created_at, updated_at)'
                . ' VALUES (:form_id, :hc, :fecha, :doctor, :solicitante, :codigo, :nombre, :lateralidad, :prioridad, :observaciones, :estado, :turno, NOW(), NOW())';
            $insertStmt = $this->pdo->prepare($insertSql);

            foreach ($normalizados as $item) {
                $insertStmt->execute([
                    ':form_id' => $formId,
                    ':hc' => $hcNumber,
                    ':fecha' => $fecha?->format('Y-m-d H:i:s'),
                    ':doctor' => $doctor,
                    ':solicitante' => $solicitante,
                    ':codigo' => $item['codigo'],
                    ':nombre' => $item['nombre'],
                    ':lateralidad' => $item['lateralidad'],
                    ':prioridad' => $item['prioridad'],
                    ':observaciones' => $item['observaciones'],
                    ':estado' => $item['estado'],
                    ':turno' => $item['turno'],
                ]);
            }

            $this->pdo->commit();

            return count($normalizados);
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }

        return 0;
    }

    private function normalizarExamen(array $examen): ?array
    {
        $nombre = $this->sanitizeText($examen['nombre'] ?? $examen['examen'] ?? $examen['descripcion'] ?? '');
        if ($nombre === null) {
            return null;
        }

        $codigo = $this->sanitizeText($examen['codigo'] ?? $examen['id'] ?? $examen['code'] ?? null);
        $lateralidad = $this->sanitizeText($examen['lateralidad'] ?? $examen['ojo'] ?? null);
        $prioridad = $this->sanitizeText($examen['prioridad'] ?? $examen['urgencia'] ?? null);
        $observaciones = $this->sanitizeText($examen['observaciones'] ?? $examen['nota'] ?? $examen['notas'] ?? null, true);
        $estado = $this->normalizarEstado($examen['estado'] ?? $examen['status'] ?? null);
        $turno = $this->normalizeTurno($examen['turno'] ?? $examen['orden'] ?? null);

        return [
            'codigo' => $codigo,
            'nombre' => $nombre,
            'lateralidad' => $lateralidad,
            'prioridad' => $prioridad,
            'observaciones' => $observaciones,
            'estado' => $estado,
            'turno' => $turno,
        ];
    }

    private function parseFecha($valor): ?DateTimeImmutable
    {
        if (empty($valor)) {
            return null;
        }

        if ($valor instanceof DateTimeImmutable) {
            return $valor;
        }

        $string = is_string($valor) ? trim($valor) : '';
        if ($string === '') {
            return null;
        }

        $formats = ['Y-m-d H:i:s', 'Y-m-d', 'd/m/Y', 'd-m-Y'];
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $string);
            if ($dt instanceof DateTimeImmutable) {
                if ($format === 'Y-m-d') {
                    $dt = $dt->setTime(0, 0);
                }
                return $dt;
            }
        }

        $timestamp = strtotime($string);
        if ($timestamp !== false) {
            return (new DateTimeImmutable())->setTimestamp($timestamp);
        }

        return null;
    }

    private function sanitizeText($valor, bool $allowEmpty = false): ?string
    {
        if ($valor === null) {
            return null;
        }

        if (is_array($valor) || is_object($valor)) {
            return null;
        }

        $texto = trim((string) $valor);
        if ($texto === '') {
            return $allowEmpty ? '' : null;
        }

        if (strcasecmp($texto, 'SELECCIONE') === 0) {
            return null;
        }

        return $texto;
    }

    private function normalizarEstado($estado): string
    {
        $texto = $this->sanitizeText($estado) ?? '';
        if ($texto === '') {
            return 'Pendiente';
        }

        $mapa = [
            'pendiente' => 'Pendiente',
            'en proceso' => 'En proceso',
            'en progreso' => 'En proceso',
            'completado' => 'Completado',
            'completa' => 'Completado',
            'listo' => 'Completado',
            'cancelado' => 'Cancelado',
        ];

        $clave = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);
        return $mapa[$clave] ?? $texto;
    }

    private function normalizeTurno($turno): ?int
    {
        if ($turno === null || $turno === '') {
            return null;
        }

        if (is_numeric($turno)) {
            $int = (int) $turno;
            return $int > 0 ? $int : null;
        }

        return null;
    }

    /**
     * @return string[]
     */
    private function buildConsultaDataSelectColumns(string $alias = 'consulta_data'): array
    {
        $prefix = $alias !== '' ? rtrim($alias, '.') . '.' : '';
        $columns = [
            $prefix . 'form_id',
            $prefix . 'hc_number',
        ];

        if ($this->consultaDataHasColumn('fecha')) {
            $columns[] = $prefix . 'fecha';
        }

        if ($this->consultaDataHasColumn('fecha_consulta')) {
            $columns[] = $prefix . 'fecha_consulta';
        }

        if ($this->consultaDataHasColumn('doctor')) {
            $columns[] = $prefix . 'doctor';
        } else {
            $columns[] = 'NULL AS doctor';
        }

        if ($this->consultaDataHasColumn('solicitante')) {
            $columns[] = $prefix . 'solicitante';
        } else {
            $columns[] = 'NULL AS solicitante';
        }

        if ($this->consultaDataHasColumn('examenes')) {
            $columns[] = $prefix . 'examenes';
        } else {
            $columns[] = 'NULL AS examenes';
        }

        return $columns;
    }

    private function resolveDoctorFromRow(array $row): ?string
    {
        $candidates = [];

        if (array_key_exists('doctor', $row)) {
            $candidates[] = $row['doctor'];
        }

        if (array_key_exists('doctor_pp', $row)) {
            $candidates[] = $row['doctor_pp'];
        }

        if (array_key_exists('procedimiento_doctor', $row)) {
            $candidates[] = $row['procedimiento_doctor'];
        }

        foreach ($candidates as $candidate) {
            $sanitized = $this->sanitizeText($candidate);
            if ($sanitized !== null) {
                return $sanitized;
            }
        }

        return null;
    }

    private function consultaDataHasColumn(string $column): bool
    {
        if ($this->consultaDataColumns === null) {
            $this->consultaDataColumns = $this->fetchConsultaDataColumns();
        }

        return $this->consultaDataColumns[strtolower($column)] ?? false;
    }

    /**
     * @return array<string, bool>
     */
    private function fetchConsultaDataColumns(): array
    {
        $sql = "SELECT COLUMN_NAME FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'consulta_data'";
        $stmt = $this->pdo->query($sql);

        $columns = [];
        if ($stmt !== false) {
            while ($name = $stmt->fetchColumn()) {
                if (!is_string($name)) {
                    continue;
                }
                $columns[strtolower($name)] = true;
            }
        }

        return $columns;
    }
}
