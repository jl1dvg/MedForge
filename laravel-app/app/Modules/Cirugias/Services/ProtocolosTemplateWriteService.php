<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Services;

use PDO;
use Throwable;

class ProtocolosTemplateWriteService
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function existsProtocolId(string $id): bool
    {
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM procedimientos WHERE id = ?');
        $stmt->execute([$id]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function generateUniqueIdFromSurgery(string $surgery): string
    {
        $baseId = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '_', $surgery), '_'));
        if ($baseId === '') {
            $baseId = 'protocolo';
        }

        $candidate = $baseId;
        $counter = 1;
        while ($this->existsProtocolId($candidate)) {
            $candidate = $baseId . '_' . $counter;
            $counter++;
        }

        return $candidate;
    }

    public function updateProtocol(array $data): bool
    {
        try {
            $this->db->beginTransaction();

            $checkStmt = $this->db->prepare('SELECT COUNT(*) FROM procedimientos WHERE id = ?');
            $checkStmt->execute([$data['id']]);

            if ((int) $checkStmt->fetchColumn() === 0) {
                $this->db->prepare("INSERT INTO procedimientos (id, cirugia, categoria, membrete) VALUES (?, '', '', '')")->execute([$data['id']]);
                $this->db->prepare('INSERT INTO evolucion005 (id) VALUES (?)')->execute([$data['id']]);
            }

            $sql = "UPDATE procedimientos p
                    JOIN evolucion005 e ON p.id = e.id
                    SET p.cirugia = ?, p.categoria = ?, p.membrete = ?, p.dieresis = ?,
                        p.exposicion = ?, p.hallazgo = ?, p.horas = ?, p.imagen_link = ?,
                        p.operatorio = ?, e.pre_evolucion = ?, e.pre_indicacion = ?,
                        e.post_evolucion = ?, e.post_indicacion = ?, e.alta_evolucion = ?,
                        e.alta_indicacion = ?
                    WHERE p.id = ?";
            $this->db->prepare($sql)->execute([
                $data['cirugia'], $data['categoriaQX'], $data['membrete'], $data['dieresis'],
                $data['exposicion'], $data['hallazgo'], $data['horas'], $data['imagen_link'],
                $data['operatorio'], $data['pre_evolucion'], $data['pre_indicacion'],
                $data['post_evolucion'], $data['post_indicacion'], $data['alta_evolucion'],
                $data['alta_indicacion'], $data['id'],
            ]);

            $insumos = $data['insumos'] !== '' ? $data['insumos'] : json_encode(['equipos' => [], 'quirurgicos' => [], 'anestesia' => []]);
            $this->upsertJsonByProcedure('insumos_pack', 'insumos', $data['id'], $insumos);

            $medicamentos = $data['medicamentos'] !== '' ? $data['medicamentos'] : json_encode([]);
            $this->upsertJsonByProcedure('kardex', 'medicamentos', $data['id'], $medicamentos);

            $this->saveProcedureCodes($data['id'], $data);
            $this->saveProcedureStaff($data['id'], $data);

            $this->db->commit();

            return true;
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    public function deleteProtocol(string $id): bool
    {
        try {
            $this->db->beginTransaction();
            $this->db->prepare('DELETE FROM procedimientos_codigos WHERE procedimiento_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM procedimientos_tecnicos WHERE procedimiento_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM kardex WHERE procedimiento_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM insumos_pack WHERE procedimiento_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM evolucion005 WHERE id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM procedimientos WHERE id = ?')->execute([$id]);
            $this->db->commit();

            return true;
        } catch (Throwable) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    private function upsertJsonByProcedure(string $table, string $column, string $procedureId, string $payload): void
    {
        $check = $this->db->prepare("SELECT COUNT(*) FROM {$table} WHERE procedimiento_id = ?");
        $check->execute([$procedureId]);

        if ((int) $check->fetchColumn() > 0) {
            $stmt = $this->db->prepare("UPDATE {$table} SET {$column} = ? WHERE procedimiento_id = ?");
            $stmt->execute([$payload, $procedureId]);
            return;
        }

        $stmt = $this->db->prepare("INSERT INTO {$table} (procedimiento_id, {$column}) VALUES (?, ?)");
        $stmt->execute([$procedureId, $payload]);
    }

    private function saveProcedureCodes(string $procedureId, array $data): void
    {
        $codes = is_array($data['codigos'] ?? null) ? $data['codigos'] : [];
        $lateralities = is_array($data['lateralidades'] ?? null) ? $data['lateralidades'] : [];
        $selectors = $data['selectores_codigos'] ?? ($data['selectores'] ?? []);
        $selectors = is_array($selectors) ? $selectors : [];

        $this->db->prepare('DELETE FROM procedimientos_codigos WHERE procedimiento_id = ?')->execute([$procedureId]);
        $insert = $this->db->prepare('INSERT INTO procedimientos_codigos (procedimiento_id, nombre, lateralidad, selector) VALUES (?, ?, ?, ?)');

        foreach ($codes as $index => $code) {
            $name = trim((string) $code);
            if ($name === '') {
                continue;
            }

            $insert->execute([
                $procedureId,
                $name,
                (string) ($lateralities[$index] ?? ''),
                (string) ($selectors[$index] ?? ''),
            ]);
        }
    }

    private function saveProcedureStaff(string $procedureId, array $data): void
    {
        $functions = is_array($data['funciones'] ?? null) ? $data['funciones'] : [];
        $workers = is_array($data['trabajadores'] ?? null) ? $data['trabajadores'] : [];
        $names = is_array($data['nombres_staff'] ?? null) ? $data['nombres_staff'] : [];

        $this->db->prepare('DELETE FROM procedimientos_tecnicos WHERE procedimiento_id = ?')->execute([$procedureId]);
        $insert = $this->db->prepare('INSERT INTO procedimientos_tecnicos (procedimiento_id, funcion, trabajador, nombre, selector) VALUES (?, ?, ?, ?, ?)');

        foreach ($functions as $index => $function) {
            $function = trim((string) $function);
            $name = trim((string) ($names[$index] ?? ''));
            if ($function === '' || $name === '') {
                continue;
            }

            $workerSelector = trim((string) ($workers[$index] ?? ''));
            if ($workerSelector === '') {
                $workerSelector = '#select2-consultasubsecuente-trabajadorprotocolo-' . $index . '-doctor-container';
            }

            $insert->execute([
                $procedureId,
                $function,
                $workerSelector,
                $name,
                '#select2-consultasubsecuente-trabajadorprotocolo-' . $index . '-funcion-container',
            ]);
        }
    }
}
