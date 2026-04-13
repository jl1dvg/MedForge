<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class CloudApiTransportService
{
    /**
     * @return array{wa_message_id:string,status:string,raw:array<string,mixed>}
     */
    public function sendText(string $phoneNumberId, string $accessToken, string $apiVersion, string $recipient, string $message, bool $previewUrl = false): array
    {
        if ((bool) config('whatsapp.transport.dry_run', false)) {
            return [
                'wa_message_id' => 'dry-run-' . bin2hex(random_bytes(8)),
                'status' => 'accepted',
                'raw' => [
                    'ok' => true,
                    'dry_run' => true,
                ],
            ];
        }

        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', 'https://graph.facebook.com'), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $endpoint = sprintf('%s/%s/%s/messages', $baseUrl, trim($apiVersion, '/'), rawurlencode($phoneNumberId));

        $response = Http::timeout($timeout)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'body' => $message,
                    'preview_url' => $previewUrl,
                ],
            ]);

        $payload = $response->json();
        if (!$response->successful()) {
            throw new RuntimeException('WhatsApp Cloud API error: ' . $response->status() . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $waMessageId = (string) data_get($payload, 'messages.0.id', '');
        if ($waMessageId === '') {
            throw new RuntimeException('WhatsApp Cloud API respondió sin message id.');
        }

        return [
            'wa_message_id' => $waMessageId,
            'status' => 'accepted',
            'raw' => is_array($payload) ? $payload : [],
        ];
    }
}
