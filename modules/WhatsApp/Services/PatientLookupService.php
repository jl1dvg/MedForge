<?php

namespace Modules\WhatsApp\Services;

use Modules\WhatsApp\Config\WhatsAppSettings;
use PDO;
use RuntimeException;

class PatientLookupService
{
    private PDO $pdo;
    private WhatsAppSettings $settings;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->settings = new WhatsAppSettings($pdo);
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

    /**
     * @return array<string, mixed>|null
     */
    public function lookupInRegistry(string $cedula): ?array
    {
        $cedula = trim($cedula);
        if ($cedula === '') {
            return null;
        }

        $config = $this->settings->get();
        $endpoint = trim((string) ($config['registry_lookup_url'] ?? ''));
        if ($endpoint === '') {
            return null;
        }

        $endpoint = str_replace('{{cedula}}', rawurlencode($cedula), $endpoint);

        $timeout = (int) ($config['registry_timeout'] ?? 10);
        if ($timeout <= 0) {
            $timeout = 10;
        }

        $headers = [
            'Accept: application/json',
        ];
        $token = trim((string) ($config['registry_token'] ?? ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
        }

        $handle = curl_init($endpoint);
        if ($handle === false) {
            throw new RuntimeException('No fue posible iniciar la conexión con el Registro Civil.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => $timeout,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);

            throw new RuntimeException('Error al consultar el Registro Civil: ' . $error);
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('El Registro Civil respondió con código ' . $status . '.');
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        $fullName = trim(preg_replace('/\s+/', ' ', (string) ($decoded['full_name'] ?? $decoded['name'] ?? '')));
        if ($fullName === '') {
            $fullName = $decoded['nombres'] ?? '';
            $apellidos = $decoded['apellidos'] ?? '';
            $fullName = trim($fullName . ' ' . $apellidos);
        }

        return [
            'hc_number' => $decoded['hc_number'] ?? null,
            'cedula' => $cedula,
            'full_name' => $fullName,
            'raw' => $decoded,
        ];
    }
}
