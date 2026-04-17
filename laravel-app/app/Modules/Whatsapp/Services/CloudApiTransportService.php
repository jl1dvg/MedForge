<?php

namespace App\Modules\Whatsapp\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
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

    /**
     * @return array{wa_message_id:string,status:string,raw:array<string,mixed>}
     */
    public function sendMedia(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion,
        string $recipient,
        string $type,
        string $link,
        ?string $caption = null,
        ?string $filename = null,
        ?string $mimeType = null,
        ?string $mediaDisk = null,
        ?string $mediaPath = null,
    ): array {
        if (!in_array($type, ['image', 'video', 'document', 'audio'], true)) {
            throw new RuntimeException('Tipo de media no soportado.');
        }

        if ((bool) config('whatsapp.transport.dry_run', false)) {
            return [
                'wa_message_id' => 'dry-run-' . bin2hex(random_bytes(8)),
                'status' => 'accepted',
                'raw' => [
                    'ok' => true,
                    'dry_run' => true,
                    'type' => $type,
                    $type => array_filter([
                        'link' => $link,
                        'caption' => $caption,
                        'filename' => $filename,
                    ], static fn ($value) => $value !== null && $value !== ''),
                ],
            ];
        }

        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', 'https://graph.facebook.com'), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $endpoint = sprintf('%s/%s/%s/messages', $baseUrl, trim($apiVersion, '/'), rawurlencode($phoneNumberId));

        $mediaId = null;
        if ($mediaDisk !== null && $mediaPath !== null && Storage::disk($mediaDisk)->exists($mediaPath)) {
            $mediaId = $this->uploadMediaAsset(
                $phoneNumberId,
                $accessToken,
                $apiVersion,
                $type,
                $mediaDisk,
                $mediaPath,
                $mimeType,
                $filename
            );
        }

        $mediaPayload = array_filter([
            'id' => $mediaId,
            'link' => $mediaId === null ? $link : null,
            'caption' => $caption,
            'filename' => $type === 'document' ? $filename : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $response = Http::timeout($timeout)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => $type,
                $type => $mediaPayload,
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

    /**
     * @param array<int, array<string, mixed>> $components
     * @return array{wa_message_id:string,status:string,raw:array<string,mixed>}
     */
    public function sendTemplate(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion,
        string $recipient,
        string $templateName,
        string $languageCode,
        array $components = [],
    ): array {
        if ((bool) config('whatsapp.transport.dry_run', false)) {
            return [
                'wa_message_id' => 'dry-run-' . bin2hex(random_bytes(8)),
                'status' => 'accepted',
                'raw' => [
                    'ok' => true,
                    'dry_run' => true,
                    'type' => 'template',
                    'template' => [
                        'name' => $templateName,
                        'language' => ['code' => $languageCode],
                        'components' => $components,
                    ],
                ],
            ];
        }

        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', 'https://graph.facebook.com'), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $endpoint = sprintf('%s/%s/%s/messages', $baseUrl, trim($apiVersion, '/'), rawurlencode($phoneNumberId));

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $recipient,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode,
                ],
            ],
        ];

        if ($components !== []) {
            $payload['template']['components'] = $components;
        }

        $response = Http::timeout($timeout)
            ->withToken($accessToken)
            ->acceptJson()
            ->post($endpoint, $payload);

        $responsePayload = $response->json();
        if (!$response->successful()) {
            throw new RuntimeException('WhatsApp Cloud API error: ' . $response->status() . ' ' . json_encode($responsePayload, JSON_UNESCAPED_UNICODE));
        }

        $waMessageId = (string) data_get($responsePayload, 'messages.0.id', '');
        if ($waMessageId === '') {
            throw new RuntimeException('WhatsApp Cloud API respondió sin message id.');
        }

        return [
            'wa_message_id' => $waMessageId,
            'status' => 'accepted',
            'raw' => is_array($responsePayload) ? $responsePayload : [],
        ];
    }

    private function uploadMediaAsset(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion,
        string $type,
        string $disk,
        string $path,
        ?string $mimeType = null,
        ?string $filename = null,
    ): string {
        $baseUrl = rtrim((string) config('whatsapp.transport.graph_base_url', 'https://graph.facebook.com'), '/');
        $timeout = max(5, (int) config('whatsapp.transport.timeout', 15));
        $endpoint = sprintf('%s/%s/%s/media', $baseUrl, trim($apiVersion, '/'), rawurlencode($phoneNumberId));
        $content = Storage::disk($disk)->get($path);
        $resolvedFilename = $filename ?: basename($path);
        $resolvedMimeType = $mimeType ?: $this->guessMimeType($resolvedFilename, $type);

        $response = Http::timeout($timeout)
            ->withToken($accessToken)
            ->attach('file', $content, $resolvedFilename, ['Content-Type' => $resolvedMimeType])
            ->post($endpoint, [
                'messaging_product' => 'whatsapp',
                'type' => $resolvedMimeType,
            ]);

        $payload = $response->json();
        if (!$response->successful()) {
            throw new RuntimeException('WhatsApp Cloud API media upload error: ' . $response->status() . ' ' . json_encode($payload, JSON_UNESCAPED_UNICODE));
        }

        $mediaId = trim((string) data_get($payload, 'id', ''));
        if ($mediaId === '') {
            throw new RuntimeException('WhatsApp Cloud API respondió sin media id.');
        }

        return $mediaId;
    }

    private function guessMimeType(string $filename, string $type): string
    {
        $extension = strtolower((string) pathinfo($filename, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            '3gp' => 'video/3gpp',
            'mp3' => 'audio/mpeg',
            'ogg', 'oga' => 'audio/ogg',
            'aac' => 'audio/aac',
            'amr' => 'audio/amr',
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain',
            default => match ($type) {
                'image' => 'image/jpeg',
                'video' => 'video/mp4',
                'audio' => 'audio/ogg',
                default => 'application/octet-stream',
            },
        };
    }
}
