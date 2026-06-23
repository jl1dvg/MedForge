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
     * @param array<int, array{id:string,title:string}> $buttons
     * @return array{wa_message_id:string,status:string,raw:array<string,mixed>}
     */
    public function sendInteractiveButtons(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion,
        string $recipient,
        string $message,
        array $buttons,
        ?string $header = null,
        ?string $footer = null,
    ): array {
        $buttonRows = [];
        foreach (array_slice($buttons, 0, 3) as $button) {
            $id = trim((string) ($button['id'] ?? ''));
            $title = trim((string) ($button['title'] ?? ''));
            if ($id === '' || $title === '') {
                continue;
            }
            $buttonRows[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => mb_substr($id, 0, 256),
                    'title' => mb_substr($title, 0, 20),
                ],
            ];
        }

        if ($buttonRows === []) {
            return $this->sendText($phoneNumberId, $accessToken, $apiVersion, $recipient, $message);
        }

        $interactive = [
            'type' => 'button',
            'body' => ['text' => mb_substr($message, 0, 1024)],
            'action' => ['buttons' => $buttonRows],
        ];

        if ($header !== null && trim($header) !== '') {
            $interactive['header'] = ['type' => 'text', 'text' => mb_substr(trim($header), 0, 60)];
        }
        if ($footer !== null && trim($footer) !== '') {
            $interactive['footer'] = ['text' => mb_substr(trim($footer), 0, 60)];
        }

        return $this->sendInteractivePayload($phoneNumberId, $accessToken, $apiVersion, $recipient, $interactive);
    }

    /**
     * @param array<int, array{title:string,rows:array<int,array{id:string,title:string,description?:string}>}> $sections
     * @return array{wa_message_id:string,status:string,raw:array<string,mixed>}
     */
    public function sendInteractiveList(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion,
        string $recipient,
        string $message,
        array $sections,
        string $buttonText = 'Seleccionar',
        ?string $footer = null,
    ): array {
        $normalizedSections = [];
        foreach ($sections as $section) {
            $rows = [];
            foreach (array_slice($section['rows'] ?? [], 0, 10) as $row) {
                $id = trim((string) ($row['id'] ?? ''));
                $title = trim((string) ($row['title'] ?? ''));
                if ($id === '' || $title === '') {
                    continue;
                }

                $normalized = [
                    'id' => mb_substr($id, 0, 200),
                    'title' => mb_substr($title, 0, 24),
                ];
                $description = trim((string) ($row['description'] ?? ''));
                if ($description !== '') {
                    $normalized['description'] = mb_substr($description, 0, 72);
                }
                $rows[] = $normalized;
            }

            if ($rows === []) {
                continue;
            }

            $normalizedSections[] = [
                'title' => mb_substr(trim((string) ($section['title'] ?? 'Opciones')) ?: 'Opciones', 0, 24),
                'rows' => $rows,
            ];
        }

        if ($normalizedSections === []) {
            return $this->sendText($phoneNumberId, $accessToken, $apiVersion, $recipient, $message);
        }

        $interactive = [
            'type' => 'list',
            'body' => ['text' => mb_substr($message, 0, 1024)],
            'action' => [
                'button' => mb_substr(trim($buttonText) !== '' ? trim($buttonText) : 'Seleccionar', 0, 20),
                'sections' => $normalizedSections,
            ],
        ];

        if ($footer !== null && trim($footer) !== '') {
            $interactive['footer'] = ['text' => mb_substr(trim($footer), 0, 60)];
        }

        return $this->sendInteractivePayload($phoneNumberId, $accessToken, $apiVersion, $recipient, $interactive);
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

    /**
     * @param array<string, mixed> $interactive
     * @return array{wa_message_id:string,status:string,raw:array<string,mixed>}
     */
    private function sendInteractivePayload(
        string $phoneNumberId,
        string $accessToken,
        string $apiVersion,
        string $recipient,
        array $interactive,
    ): array {
        if ((bool) config('whatsapp.transport.dry_run', false)) {
            return [
                'wa_message_id' => 'dry-run-' . bin2hex(random_bytes(8)),
                'status' => 'accepted',
                'raw' => [
                    'ok' => true,
                    'dry_run' => true,
                    'type' => 'interactive',
                    'interactive' => $interactive,
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
                'type' => 'interactive',
                'interactive' => $interactive,
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

        // Chrome MediaRecorder audio is detected by PHP finfo as video/webm (webm container).
        // WhatsApp rejects video/webm for audio messages; normalize to audio/webm.
        if ($type === 'audio' && $resolvedMimeType === 'video/webm') {
            $resolvedMimeType = 'audio/webm';
        }

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
            'webm' => 'audio/webm',
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
