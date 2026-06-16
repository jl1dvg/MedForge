<?php

namespace App\Modules\Pacientes\Services;

use PDO;
use PDOException;

class MedicoTratanteResolver
{
    public function __construct(private readonly PDO $db)
    {
    }

    public function resolve(string $hcNumber): ?array
    {
        return $this->resolveMany([$hcNumber])[$hcNumber] ?? null;
    }

    /**
     * @param array<int,string> $hcNumbers
     * @return array<string,array<string,mixed>>
     */
    public function resolveMany(array $hcNumbers): array
    {
        $hcNumbers = array_values(array_unique(array_filter(array_map('strval', $hcNumbers))));
        if ($hcNumbers === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($hcNumbers), '?'));

        try {
            $stmt = $this->db->prepare(<<<SQL
                SELECT
                    pp.hc_number,
                    pp.id AS procedimiento_id,
                    pp.fecha,
                    pp.hora,
                    u.id AS medico_id,
                    COALESCE(NULLIF(TRIM(u.nombre), ''), NULLIF(TRIM(u.full_name), '')) AS nombre,
                    COALESCE(NULLIF(TRIM(u.subespecialidad), ''), NULLIF(TRIM(u.especialidad), '')) AS especialidad
                FROM procedimiento_proyectado pp
                INNER JOIN users u
                    ON UPPER(TRIM(pp.doctor)) = UPPER(TRIM(u.nombre))
                    OR UPPER(TRIM(pp.doctor)) = UPPER(TRIM(u.full_name))
                WHERE pp.hc_number IN ({$placeholders})
                  AND COALESCE(pp.sigcenter_present, 1) = 1
                  AND pp.doctor IS NOT NULL
                  AND TRIM(pp.doctor) <> ''
            SQL);
            $stmt->execute($hcNumbers);
        } catch (PDOException) {
            return [];
        }

        $groups = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $especialidad = trim((string) ($row['especialidad'] ?? ''));
            if (!$this->isEspecialidadTratante($especialidad)) {
                continue;
            }

            $hcNumber = (string) ($row['hc_number'] ?? '');
            $medicoId = (string) ($row['medico_id'] ?? '');
            $nombre = trim((string) ($row['nombre'] ?? ''));
            if ($hcNumber === '' || $medicoId === '' || $nombre === '') {
                continue;
            }

            $key = $hcNumber . '|' . $medicoId;
            $latestKey = $this->latestKey($row);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'hc_number' => $hcNumber,
                    'id' => (int) $medicoId,
                    'nombre' => $nombre,
                    'especialidad' => $especialidad,
                    'procedimientos_count' => 0,
                    'ultima_fecha' => null,
                    '_latest_key' => '',
                ];
            }

            $groups[$key]['procedimientos_count']++;
            if ($latestKey > $groups[$key]['_latest_key']) {
                $groups[$key]['_latest_key'] = $latestKey;
                $groups[$key]['ultima_fecha'] = $row['fecha'] !== null ? (string) $row['fecha'] : null;
            }
        }

        $resolved = [];
        foreach ($groups as $group) {
            $hcNumber = $group['hc_number'];
            $current = $resolved[$hcNumber] ?? null;
            if (
                $current === null
                || $group['procedimientos_count'] > $current['procedimientos_count']
                || (
                    $group['procedimientos_count'] === $current['procedimientos_count']
                    && $group['_latest_key'] > $current['_latest_key']
                )
            ) {
                $resolved[$hcNumber] = $group;
            }
        }

        foreach ($resolved as &$row) {
            unset($row['hc_number'], $row['_latest_key']);
            $row['confirmado'] = true;
        }

        return $resolved;
    }

    private function isEspecialidadTratante(string $especialidad): bool
    {
        $value = strtolower(trim($especialidad));
        $value = strtr($value, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        if (str_contains($value, 'optometr') || str_contains($value, 'administrativo')) {
            return false;
        }

        return str_contains($value, 'cirujano') && str_contains($value, 'oftalm');
    }

    /**
     * @param array<string,mixed> $row
     */
    private function latestKey(array $row): string
    {
        return sprintf(
            '%s %s %012d',
            (string) ($row['fecha'] ?? ''),
            (string) ($row['hora'] ?? ''),
            (int) ($row['procedimiento_id'] ?? 0)
        );
    }
}
