<?php

namespace App\Console\Commands;

use App\Modules\ControlCenter\Services\InstanceTelemetryAgentService;
use Illuminate\Console\Command;

class ControlCenterSendTelemetryCommand extends Command
{
    protected $signature = 'control-center:send-telemetry
        {--endpoint= : Endpoint central /v2/control-center/telemetry/heartbeat}
        {--token= : Token de telemetria de la instancia}
        {--instance= : Slug de la instancia}
        {--app-version= : Version instalada a reportar}
        {--debug-http : Imprime diagnostico HTTP seguro y activa debug del cliente HTTP}';

    protected $description = 'Envia health y consumo real de esta instancia al Control Center central';

    public function handle(InstanceTelemetryAgentService $agent): int
    {
        $endpoint = $this->optionValue('endpoint', config('control_center.telemetry_endpoint'));
        $token = $this->optionValue('token', config('control_center.telemetry_token'));
        $instance = $this->optionValue('instance', config('control_center.instance_slug'));
        $appVersion = $this->optionValue('app-version', config('control_center.app_version'));
        $debugHttp = (bool) $this->option('debug-http');

        $this->line('Endpoint: ' . $endpoint);
        $this->line('Instancia: ' . $instance);
        $this->line('App version: ' . $appVersion);
        $this->line('token_present: ' . ($token !== '' ? 'yes' : 'no'));
        $this->line('token_prefix: ' . ($token !== '' ? substr($token, 0, 8) : '—'));
        $this->line('token_length: ' . strlen($token));
        $this->line('headers_contain_authorization: ' . ($token !== '' ? 'yes' : 'no'));

        if ($token === '') {
            $this->error('Configura CONTROL_CENTER_INSTANCE_TOKEN para enviar telemetria Control Center.');

            return self::FAILURE;
        }

        $payload = null;
        if ($debugHttp) {
            try {
                $payload = $agent->payload($instance, $appVersion);
            } catch (\Throwable $e) {
                $this->error('No se pudo construir el payload de telemetria: ' . $e->getMessage());

                return self::FAILURE;
            }

            $this->line('HTTP debug mode: on');
            $this->line('Endpoint final: ' . $endpoint);
            $this->line('Metodo: POST');
            $this->line('Headers enviados al Http client:');
            foreach ($this->sanitizeHeaders($agent->headersForToken($token)) as $name => $value) {
                $this->line($name . ': ' . $value);
            }
            $this->line('Payload JSON:');
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');
        }

        try {
            $result = $agent->send($endpoint, $token, $instance, $appVersion, $debugHttp, $payload);
        } catch (\InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $status = (int) ($result['http_status'] ?? 0);
        $responseText = $this->formatResponse($result['response'] ?? null, (string) ($result['body'] ?? ''));

        $this->line('HTTP status: ' . $status);
        $this->line('Respuesta del servidor:');
        $this->line($responseText);

        if (!($result['ok'] ?? false)) {
            $this->error('Error enviando telemetria.');

            return self::FAILURE;
        }

        $this->info('Telemetria enviada correctamente.');

        return self::SUCCESS;
    }

    private function optionValue(string $option, mixed $fallback): string
    {
        $value = $this->option($option) !== null ? $this->option($option) : $fallback;

        return trim((string) $value);
    }

    private function formatResponse(mixed $response, string $body): string
    {
        if (is_array($response)) {
            return json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
        }

        $body = trim($body);

        return $body !== '' ? $body : '(sin cuerpo)';
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function sanitizeHeaders(array $headers): array
    {
        if (isset($headers['Authorization'])) {
            $headers['Authorization'] = $this->maskAuthorizationHeader($headers['Authorization']);
        }

        return $headers;
    }

    private function maskAuthorizationHeader(string $header): string
    {
        $prefix = 'Bearer ';
        if (!str_starts_with($header, $prefix)) {
            return $header;
        }

        return $prefix . $this->maskToken(substr($header, strlen($prefix)));
    }

    private function maskToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '—';
        }

        if (strlen($token) <= 16) {
            return substr($token, 0, 4) . '...' . substr($token, -4);
        }

        return substr($token, 0, 8) . '...' . substr($token, -8);
    }
}
