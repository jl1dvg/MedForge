<?php

namespace Modules\WhatsApp\Services;

use PDO;

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
    public function findLocalByCedula(string $cedula): ?array
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            return null;
        }

        $stmt = $this->pdo->prepare(
            'SELECT hc_number, cedula, CONCAT_WS(" ", fname, mname, lname, lname2) AS full_name FROM patient_data WHERE cedula = :cedula LIMIT 1'
        );
        $stmt->execute([':cedula' => $cedula]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row['full_name'] = trim(preg_replace('/\s+/', ' ', (string) ($row['full_name'] ?? '')));

        return $row;
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

        $stmt = $this->pdo->prepare(
            'SELECT hc_number, cedula, CONCAT_WS(" ", fname, mname, lname, lname2) AS full_name FROM patient_data WHERE hc_number = :hc LIMIT 1'
        );
        $stmt->execute([':hc' => $historyNumber]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false) {
            return null;
        }

        $row['full_name'] = trim(preg_replace('/\s+/', ' ', (string) ($row['full_name'] ?? '')));

        return $row;
    }

}
