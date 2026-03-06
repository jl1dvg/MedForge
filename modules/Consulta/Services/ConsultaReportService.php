<?php

namespace Modules\Consulta\Services;

use Modules\Consulta\Models\ConsultaModel;
use Modules\Pacientes\Services\PacienteService;
use PDO;
use Throwable;

class ConsultaReportService
{
    private ConsultaModel $consultaModel;
    private PacienteService $pacienteService;

    public function __construct(PDO $pdo)
    {
        $this->consultaModel = new ConsultaModel($pdo);
        $this->pacienteService = new PacienteService($pdo);
    }

    /**
     * @return array<string, mixed>
     */
    public function buildConsultaReportData(string $hc, string $form_id): array
    {
        if ($this->isConsultaDataV2Enabled()) {
            // Evita lock de sesión cuando legacy llama a /v2 dentro del mismo request.
            if (session_status() === PHP_SESSION_ACTIVE) {
                @session_write_close();
            }

            $payload = $this->fetchV2ConsultaDataPayload($form_id, $hc);
            $remoteData = $payload['data'] ?? null;
            if (is_array($remoteData) && $remoteData !== []) {
                return $remoteData;
            }
        }

        $paciente = $this->pacienteService->getPatientDetails($hc);
        $consulta = $this->consultaModel->obtenerConsultaConProcedimiento($form_id, $hc);
        $diagnostico = $this->consultaModel->obtenerDxDeConsulta($form_id);
        $dxDerivacion = $this->consultaModel->obtenerDxDerivacion($form_id);

        return [
            'paciente' => $paciente,
            'diagnostico' => $diagnostico,
            'consulta' => $consulta,
            'dx_derivacion' => $dxDerivacion,
        ];
    }

    private function isConsultaDataV2Enabled(): bool
    {
        $raw = $_ENV['REPORTING_V2_CONSULTA_DATA_ENABLED']
            ?? getenv('REPORTING_V2_CONSULTA_DATA_ENABLED')
            ?? null;
        if ($raw === null || trim((string) $raw) === '') {
            $raw = $this->readEnvFileValue('REPORTING_V2_CONSULTA_DATA_ENABLED');
        }

        return filter_var((string) ($raw ?? '0'), FILTER_VALIDATE_BOOLEAN);
    }

    private function readEnvFileValue(string $key): ?string
    {
        $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 3);
        $envPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.env';
        if (!is_readable($envPath)) {
            return null;
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            [$lineKey, $value] = array_pad(explode('=', $line, 2), 2, '');
            if (trim($lineKey) !== $key) {
                continue;
            }

            return trim($value, " \t\n\r\0\x0B\"'");
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function fetchV2ConsultaDataPayload(string $formId, string $hcNumber): ?array
    {
        $sessionId = (string) session_id();
        if ($sessionId === '') {
            return null;
        }

        $baseUrl = $this->resolveCurrentBaseUrl();
        if ($baseUrl === '') {
            return null;
        }

        $query = http_build_query([
            'form_id' => $formId,
            'hc_number' => $hcNumber,
        ], '', '&', PHP_QUERY_RFC3986);

        try {
            $requestToken = bin2hex(random_bytes(6));
        } catch (Throwable) {
            $requestToken = (string) mt_rand(100000, 999999);
        }

        $headers = [
            'Accept: application/json',
            'Cookie: PHPSESSID=' . $sessionId,
            'X-Request-Id: legacy-reporting-' . $requestToken,
        ];

        [$status, $body] = $this->httpGet(
            rtrim($baseUrl, '/') . '/v2/reports/consulta/data?' . $query,
            $headers,
            10
        );

        if ($status !== 200 || $body === '') {
            return null;
        }

        $payload = json_decode($body, true);
        if (!is_array($payload)) {
            return null;
        }

        return $payload;
    }

    /**
     * @param array<int, string> $headers
     * @return array{0:int,1:string}
     */
    private function httpGet(string $url, array $headers, int $timeoutSeconds): array
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => $timeoutSeconds,
                CURLOPT_FOLLOWLOCATION => false,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_close($ch);

            return [$status, is_string($raw) ? $raw : ''];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", $headers),
                'timeout' => $timeoutSeconds,
                'ignore_errors' => true,
            ],
        ]);

        $body = @file_get_contents($url, false, $context);
        $status = 0;
        foreach (($http_response_header ?? []) as $line) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', (string) $line, $matches)) {
                $status = (int) ($matches[1] ?? 0);
                break;
            }
        }

        return [$status, is_string($body) ? $body : ''];
    }

    private function resolveCurrentBaseUrl(): string
    {
        if (defined('BASE_URL')) {
            $base = trim((string) BASE_URL);
            if ($base !== '') {
                return rtrim($base, '/');
            }
        }

        $hostHeader = (string) ($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
        $host = trim((string) (explode(',', $hostHeader)[0] ?? ''));
        if ($host === '') {
            return '';
        }

        $proto = (string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '');
        if ($proto === '') {
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        }

        return $proto . '://' . $host;
    }
}
