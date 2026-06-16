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
        $users = $this->validUsersByNameTokens();
        if ($users === []) {
            return [];
        }

        try {
            $stmt = $this->db->prepare(<<<SQL
                SELECT
                    pp.hc_number,
                    pp.id AS procedimiento_id,
                    pp.fecha,
                    pp.hora,
                    pp.doctor
                FROM procedimiento_proyectado pp
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
            $hcNumber = (string) ($row['hc_number'] ?? '');
            $doctor = trim((string) ($row['doctor'] ?? ''));
            $user = $this->matchUser($doctor, $users);
            if ($hcNumber === '' || $user === null) {
                continue;
            }

            $key = $hcNumber . '|' . (string) $user['id'];
            $latestKey = $this->latestKey($row);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'hc_number' => $hcNumber,
                    'id' => (int) $user['id'],
                    'nombre' => (string) $user['nombre'],
                    'especialidad' => (string) $user['especialidad'],
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

    /**
     * @return array<int,array{id:int,nombre:string,especialidad:string,tokens:array<int,string>}>
     */
    private function validUsersByNameTokens(): array
    {
        try {
            $stmt = $this->db->query(<<<'SQL'
                SELECT id, nombre, full_name, especialidad, subespecialidad
                FROM users
                WHERE ((nombre IS NOT NULL AND TRIM(nombre) <> '')
                    OR (full_name IS NOT NULL AND TRIM(full_name) <> ''))
            SQL);
        } catch (PDOException) {
            return [];
        }

        $users = [];
        foreach (($stmt?->fetchAll(PDO::FETCH_ASSOC) ?: []) as $row) {
            $especialidad = trim((string) ($row['especialidad'] ?? ''));
            $subespecialidad = trim((string) ($row['subespecialidad'] ?? ''));
            if (!$this->isEspecialidadTratante($especialidad . ' ' . $subespecialidad)) {
                continue;
            }

            $nombre = trim((string) ($row['nombre'] ?: ($row['full_name'] ?? '')));
            $tokens = $this->nameTokens($nombre);
            if ($nombre === '' || count($tokens) < 2) {
                continue;
            }

            $users[] = [
                'id' => (int) ($row['id'] ?? 0),
                'nombre' => $nombre,
                'especialidad' => $especialidad !== '' ? $especialidad : $subespecialidad,
                'tokens' => $tokens,
            ];
        }

        return $users;
    }

    /**
     * @param array<int,array{id:int,nombre:string,especialidad:string,tokens:array<int,string>}> $users
     * @return array{id:int,nombre:string,especialidad:string,tokens:array<int,string>}|null
     */
    private function matchUser(string $doctor, array $users): ?array
    {
        $doctorTokens = $this->nameTokens($doctor);
        if (count($doctorTokens) < 2) {
            return null;
        }

        $doctorSet = array_fill_keys($doctorTokens, true);
        foreach ($users as $user) {
            $matched = 0;
            foreach ($user['tokens'] as $token) {
                if (isset($doctorSet[$token])) {
                    $matched++;
                }
            }

            if ($matched === count($user['tokens'])) {
                return $user;
            }
        }

        return null;
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
     * @return array<int,string>
     */
    private function nameTokens(string $name): array
    {
        $name = strtolower(trim($name));
        $name = strtr($name, [
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]);
        $tokens = preg_split('/[^a-z0-9]+/', $name) ?: [];
        $tokens = array_values(array_unique(array_filter($tokens, static fn(string $token): bool => $token !== '')));
        sort($tokens);

        return $tokens;
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
