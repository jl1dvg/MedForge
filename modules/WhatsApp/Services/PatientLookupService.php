<?php

namespace Modules\WhatsApp\Services;

use PDO;

use function array_key_exists;
use function is_string;
use function preg_replace;
use function trim;

class PatientLookupService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findLocalByHistoryNumber(string $historyNumber): ?array
    {
        $historyNumber = trim($historyNumber);
        if ($historyNumber === '') {
            return null;
        }

        $stmt = $this->pdo->prepare('SELECT * FROM patient_data WHERE hc_number = :hc LIMIT 1');
        $stmt->execute([':hc' => $historyNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $fullName = $this->resolveFullName($row);

        $result = [
            'hc_number' => $row['hc_number'],
            'identifier' => $row['hc_number'],
        ];

        if ($fullName !== '') {
            $result['full_name'] = $fullName;
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function resolveFullName(array $row): string
    {
        $preferredKeys = [
            'full_name',
            'fullname',
            'nombre_completo',
            'nombreCompleto',
            'razon_social',
            'business_name',
            'name',
            'nombre',
        ];

        foreach ($preferredKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '') {
                return $this->normalizeWhitespace($value);
            }
        }

        $parts = [];
        $partKeys = [
            'fname',
            'mname',
            'lname',
            'lname2',
            'first_name',
            'middle_name',
            'last_name',
            'second_last_name',
            'primer_nombre',
            'segundo_nombre',
            'primer_apellido',
            'segundo_apellido',
        ];

        foreach ($partKeys as $key) {
            if (!array_key_exists($key, $row)) {
                continue;
            }

            $value = trim((string) $row[$key]);
            if ($value !== '') {
                $parts[] = $value;
            }
        }

        $fullName = $this->normalizeWhitespace(implode(' ', $parts));
        if ($fullName !== '') {
            return $fullName;
        }

        if (isset($row['hc_number']) && is_string($row['hc_number'])) {
            return trim($row['hc_number']);
        }

        return '';
    }

    private function normalizeWhitespace(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
