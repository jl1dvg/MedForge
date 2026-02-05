<?php

namespace Modules\Examenes\Services;

use DateInterval;
use DateTimeImmutable;
use PDO;
use PDOException;
use RuntimeException;

class ExamenCalendarBlockService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listarPorExamen(int $examenId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT id, doctor, sala, fecha_inicio, fecha_fin, motivo, created_by, created_at
                 FROM examen_crm_calendar_blocks
                 WHERE examen_id = :id
                 ORDER BY fecha_inicio DESC, id DESC'
            );
            $stmt->bindValue(':id', $examenId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $exception) {
            if ($this->esTablaInexistente($exception)) {
                return [];
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function registrar(int $examenId, array $payload, ?int $usuarioId = null): array
    {
        $examen = $this->obtenerExamenBasico($examenId);
        if ($examen === null) {
            throw new RuntimeException('No se encontró el examen para bloquear agenda');
        }

        $inicio = $this->normalizarFecha($payload['fecha_inicio'] ?? $examen['fecha_programada'] ?? null);
        $fin = $this->normalizarFecha($payload['fecha_fin'] ?? null);
        $duracionMinutos = isset($payload['duracion_minutos']) ? (int) $payload['duracion_minutos'] : 0;

        if ($inicio === null) {
            throw new RuntimeException('La fecha/hora de inicio es obligatoria');
        }

        if ($fin === null) {
            $fin = $inicio->add(new DateInterval(sprintf('PT%dM', max(15, $duracionMinutos ?: 60))));
        }

        if ($fin <= $inicio) {
            throw new RuntimeException('La hora de fin debe ser posterior al inicio');
        }

        $doctor = $this->normalizarTexto($payload['doctor'] ?? $examen['doctor'] ?? '');
        $sala = $this->normalizarTexto($payload['sala'] ?? $payload['quirofano'] ?? $examen['quirofano'] ?? '');
        $motivo = $this->normalizarTexto($payload['motivo'] ?? null);

        $stmt = $this->pdo->prepare(
            'INSERT INTO examen_crm_calendar_blocks (examen_id, doctor, sala, fecha_inicio, fecha_fin, motivo, created_by)
             VALUES (:examen_id, :doctor, :sala, :fecha_inicio, :fecha_fin, :motivo, :created_by)'
        );

        $stmt->bindValue(':examen_id', $examenId, PDO::PARAM_INT);
        $stmt->bindValue(':doctor', $doctor !== null ? $doctor : null, $doctor !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':sala', $sala !== null ? $sala : null, $sala !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':fecha_inicio', $inicio->format('Y-m-d H:i:s'));
        $stmt->bindValue(':fecha_fin', $fin->format('Y-m-d H:i:s'));
        $stmt->bindValue(':motivo', $motivo, $motivo !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $usuarioId, $usuarioId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        $id = (int) $this->pdo->lastInsertId();

        return [
            'id' => $id,
            'examen_id' => $examenId,
            'doctor' => $doctor,
            'sala' => $sala,
            'fecha_inicio' => $inicio->format(DateTimeImmutable::ATOM),
            'fecha_fin' => $fin->format(DateTimeImmutable::ATOM),
            'motivo' => $motivo,
            'created_by' => $usuarioId,
        ];
    }

    private function normalizarFecha(mixed $value): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        $try = [
            'Y-m-d H:i:s',
            DateTimeImmutable::ATOM,
            'Y-m-d\TH:i',
            'd/m/Y H:i',
            'd-m-Y H:i',
            'Y-m-d',
        ];

        foreach ($try as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, (string) $value);
            if ($dt instanceof DateTimeImmutable) {
                return $dt;
            }
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (\Exception $exception) {
            return null;
        }
    }

    private function normalizarTexto(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);
        return $value === '' ? null : $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function obtenerExamenBasico(int $id): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT
                ce.id,
                ce.doctor,
                ce.hc_number,
                ce.form_id,
                COALESCE(ce.consulta_fecha, cd.fecha) AS fecha_programada,
                cd.quirofano
             FROM consulta_examenes ce
             LEFT JOIN consulta_data cd ON cd.hc_number = ce.hc_number AND cd.form_id = ce.form_id
             WHERE ce.id = :id
             LIMIT 1'
        );
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if ($row && isset($row['fecha_programada']) && !empty($row['fecha_programada'])) {
            $row['fecha_programada'] = (string) $row['fecha_programada'];
        }

        return $row ?: null;
    }

    private function esTablaInexistente(PDOException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'examen_crm_calendar_blocks');
    }
}

