<?php

namespace App\Modules\Pacientes\Services;

use PDO;
use PDOException;

class SedePacienteResolver
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
     * @return array<string,array{id:string,nombre:string,origen:string}>
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
                SELECT hc_number, id_sede, sede_departamento
                FROM procedimiento_proyectado
                WHERE hc_number IN ({$placeholders})
                  AND COALESCE(sigcenter_present, 1) = 1
                  AND COALESCE(NULLIF(TRIM(id_sede), ''), NULLIF(TRIM(sede_departamento), '')) IS NOT NULL
                ORDER BY hc_number ASC, fecha ASC, hora ASC, id ASC
            SQL);
            $stmt->execute($hcNumbers);
        } catch (PDOException) {
            return [];
        }

        $resolved = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $hcNumber = (string) ($row['hc_number'] ?? '');
            if ($hcNumber === '' || isset($resolved[$hcNumber])) {
                continue;
            }

            $sede = $this->normalizeSede((string) ($row['id_sede'] ?: ($row['sede_departamento'] ?? '')));
            if ($sede === null) {
                continue;
            }

            $resolved[$hcNumber] = [
                'id' => $sede['id'],
                'nombre' => $sede['nombre'],
                'origen' => 'primera_atencion',
            ];
        }

        return $resolved;
    }

    /**
     * @return array{id:string,nombre:string}|null
     */
    private function normalizeSede(string $value): ?array
    {
        $normalized = strtolower(trim($value));
        $normalized = strtr($normalized, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);

        if (str_contains($normalized, 'ceibos')) {
            return ['id' => 'ceibos', 'nombre' => 'CEIBOS'];
        }

        if (str_contains($normalized, 'matriz')) {
            return ['id' => 'matriz', 'nombre' => 'MATRIZ'];
        }

        return null;
    }
}
