<?php

namespace Modules\Examenes\Services;

use DateTimeImmutable;
use PDO;
use PDOException;

class ConsultaExamenSyncService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function syncFromPayload(string $formId, string $hcNumber, ?string $doctor, ?string $solicitante, ?string $fechaConsulta, array $examenes): void
    {
        $fecha = $this->parseFecha($fechaConsulta);
        $this->sync($formId, $hcNumber, $doctor, $solicitante, $fecha, $examenes);
    }

    public function syncFromConsultaRow(array $row): void
    {
        $formId = (string) ($row['form_id'] ?? '');
        $hcNumber = (string) ($row['hc_number'] ?? '');

        if ($formId === '' || $hcNumber === '') {
            return;
        }

        $doctor = isset($row['doctor']) ? $this->sanitizeText($row['doctor']) : null;
        $solicitante = isset($row['solicitante']) ? $this->sanitizeText($row['solicitante']) : $doctor;
        $fecha = $this->parseFecha($row['fecha'] ?? $row['fecha_consulta'] ?? null);

        $examenesRaw = $row['examenes'] ?? [];
        if (is_string($examenesRaw)) {
            $decoded = json_decode($examenesRaw, true);
            $examenes = is_array($decoded) ? $decoded : [];
        } elseif (is_array($examenesRaw)) {
            $examenes = $examenesRaw;
        } else {
            $examenes = [];
        }

        $this->sync($formId, $hcNumber, $doctor, $solicitante, $fecha, $examenes);
    }

    public function backfillFromConsultaData(): int
    {
        $stmt = $this->pdo->query('SELECT form_id, hc_number, doctor, fecha, examenes FROM consulta_data WHERE examenes IS NOT NULL AND examenes != "[]"');
        $procesadas = 0;

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->syncFromConsultaRow($row);
            $procesadas++;
        }

        return $procesadas;
    }

    private function sync(string $formId, string $hcNumber, ?string $doctor, ?string $solicitante, ?DateTimeImmutable $fecha, array $examenes): void
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

        $this->pdo->beginTransaction();

        try {
            $deleteStmt = $this->pdo->prepare('DELETE FROM consulta_examenes WHERE form_id = :form_id AND hc_number = :hc');
            $deleteStmt->execute([
                ':form_id' => $formId,
                ':hc' => $hcNumber,
            ]);

            if (empty($normalizados)) {
                $this->pdo->commit();
                return;
            }

            $insertSql = 'INSERT INTO consulta_examenes (form_id, hc_number, consulta_fecha, doctor, solicitante, examen_codigo, examen_nombre, lateralidad, prioridad, observaciones, estado, turno, created_at, updated_at)
                          VALUES (:form_id, :hc, :fecha, :doctor, :solicitante, :codigo, :nombre, :lateralidad, :prioridad, :observaciones, :estado, :turno, NOW(), NOW())';
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
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
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
}
