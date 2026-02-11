<?php

namespace Modules\WhatsApp\Services;

use Modules\WhatsApp\Contracts\TransportInterface;

class CloudApiTransport implements TransportInterface
{
    private const GRAPH_BASE_URL = 'https://graph.facebook.com/';

    /**
     * @param array<string, string> $config
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function send(array $config, array $payload)
    {
        $endpoint = self::GRAPH_BASE_URL . rtrim($config['api_version'], '/') . '/' . $config['phone_number_id'] . '/messages';
        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE);
        if ($encoded === false) {
            error_log('No fue posible codificar el payload de WhatsApp Cloud API.');

            return null;
        }

        $handle = curl_init($endpoint);
        if ($handle === false) {
            error_log('No fue posible iniciar la solicitud cURL para WhatsApp Cloud API.');

            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $config['access_token'],
                'Content-Type: application/json',
            ],
            CURLOPT_POSTFIELDS => $encoded,
            CURLOPT_TIMEOUT => 15,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);
            error_log('Error en la solicitud a WhatsApp Cloud API: ' . $error);

            return null;
        }

        $httpCode = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log('WhatsApp Cloud API respondió con código ' . $httpCode . ': ' . $response);

            return null;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        return ['raw' => $response];
    }
}
