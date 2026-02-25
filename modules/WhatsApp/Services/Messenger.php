<?php

namespace Modules\WhatsApp\Services;

use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\WhatsApp\Contracts\TransportInterface;
use Modules\WhatsApp\Support\MessageSanitizer;
use Modules\WhatsApp\Support\PhoneNumberFormatter;
use PDO;

class Messenger
{
    private WhatsAppSettings $settings;
    private TransportInterface $transport;
    private ConversationService $conversations;
    /**
     * @var array<string, mixed>|null
     */
    private ?array $lastTransportError = null;
    /**
     * @var array<string, mixed>|null
     */
    private ?array $lastTransportResponse = null;

    public function __construct(PDO $pdo, ?TransportInterface $transport = null)
    {
        $this->settings = new WhatsAppSettings($pdo);
        $this->transport = $transport ?? new CloudApiTransport();
        $this->conversations = new ConversationService($pdo);
    }

    public function isEnabled(): bool
    {
        return $this->settings->isEnabled();
    }

    public function getBrandName(): string
    {
        return $this->settings->getBrandName();
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, mixed> $options
     */
    public function sendTextMessage($recipients, string $message, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $message = MessageSanitizer::sanitize($message);
        if ($message === '') {
            return false;
        }

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'text',
                'text' => [
                    'preview_url' => (bool) ($options['preview_url'] ?? false),
                    'body' => $message,
                ],
            ];

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null && $this->shouldRecord($options)) {
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'text', $message, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<int, array{id: string, title: string}> $buttons
     * @param array<string, mixed> $options
     */
    public function sendInteractiveButtons($recipients, string $message, array $buttons, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $message = MessageSanitizer::sanitize($message);
        if ($message === '') {
            return false;
        }

        $normalizedButtons = [];
        foreach ($buttons as $button) {
            if (!is_array($button)) {
                continue;
            }

            $id = trim((string) ($button['id'] ?? ''));
            $title = MessageSanitizer::sanitize((string) ($button['title'] ?? ''));

            if ($id === '' || $title === '') {
                continue;
            }

            $normalizedButtons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => $title,
                ],
            ];

            if (count($normalizedButtons) >= 3) {
                break;
            }
        }

        if (empty($normalizedButtons)) {
            return false;
        }

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $header = isset($options['header']) ? MessageSanitizer::sanitize((string) $options['header']) : '';
        $footer = isset($options['footer']) ? MessageSanitizer::sanitize((string) $options['footer']) : '';

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'button',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'buttons' => $normalizedButtons,
                    ],
                ],
            ];

            if ($header !== '') {
                $payload['interactive']['header'] = [
                    'type' => 'text',
                    'text' => $header,
                ];
            }

            if ($footer !== '') {
                $payload['interactive']['footer'] = [
                    'text' => $footer,
                ];
            }

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null && $this->shouldRecord($options)) {
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'interactive_buttons', $message, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<int, array<string, mixed>> $sections
     * @param array<string, mixed> $options
     */
    public function sendInteractiveList($recipients, string $message, array $sections, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $message = MessageSanitizer::sanitize($message);
        if ($message === '') {
            return false;
        }

        $normalizedSections = [];
        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }

            $title = MessageSanitizer::sanitize((string) ($section['title'] ?? ''));
            $rows = [];
            foreach (($section['rows'] ?? []) as $row) {
                if (!is_array($row)) {
                    continue;
                }

                $rowTitle = MessageSanitizer::sanitize((string) ($row['title'] ?? ''));
                $rowId = trim((string) ($row['id'] ?? ''));
                if ($rowTitle === '' || $rowId === '') {
                    continue;
                }

                $entry = [
                    'id' => $rowId,
                    'title' => $rowTitle,
                ];

                if (!empty($row['description'])) {
                    $description = MessageSanitizer::sanitize((string) $row['description']);
                    if ($description !== '') {
                        $entry['description'] = $description;
                    }
                }

                $rows[] = $entry;

                if (count($rows) >= 10) {
                    break;
                }
            }

            if (empty($rows)) {
                continue;
            }

            $normalizedSections[] = [
                'title' => $title === '' ? null : $title,
                'rows' => $rows,
            ];

            if (count($normalizedSections) >= 10) {
                break;
            }
        }

        if (empty($normalizedSections)) {
            return false;
        }

        $buttonLabel = MessageSanitizer::sanitize((string) ($options['button'] ?? 'Ver opciones'));
        if ($buttonLabel === '') {
            $buttonLabel = 'Ver opciones';
        }

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $header = isset($options['header']) ? MessageSanitizer::sanitize((string) $options['header']) : '';
        $footer = isset($options['footer']) ? MessageSanitizer::sanitize((string) $options['footer']) : '';

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'interactive',
                'interactive' => [
                    'type' => 'list',
                    'body' => [
                        'text' => $message,
                    ],
                    'action' => [
                        'button' => $buttonLabel,
                        'sections' => array_map(static function (array $section): array {
                            if ($section['title'] === null || $section['title'] === '') {
                                unset($section['title']);
                            }

                            return $section;
                        }, $normalizedSections),
                    ],
                ],
            ];

            if ($header !== '') {
                $payload['interactive']['header'] = [
                    'type' => 'text',
                    'text' => $header,
                ];
            }

            if ($footer !== '') {
                $payload['interactive']['footer'] = [
                    'text' => $footer,
                ];
            }

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null && $this->shouldRecord($options)) {
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'interactive_list', $message, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, mixed> $options
     */
    public function sendImageMessage($recipients, string $url, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $caption = isset($options['caption']) ? MessageSanitizer::sanitize((string) $options['caption']) : '';

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'image',
                'image' => [
                    'link' => $url,
                ],
            ];

            if ($caption !== '') {
                $payload['image']['caption'] = $caption;
            }

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null && $this->shouldRecord($options)) {
                $preview = $caption !== '' ? $caption : '[Imagen]';
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'image', $preview, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, mixed> $options
     */
    public function sendDocumentMessage($recipients, string $url, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $caption = isset($options['caption']) ? MessageSanitizer::sanitize((string) $options['caption']) : '';
        $filename = isset($options['filename']) ? MessageSanitizer::sanitize((string) $options['filename']) : '';

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'document',
                'document' => [
                    'link' => $url,
                ],
            ];

            if ($caption !== '') {
                $payload['document']['caption'] = $caption;
            }

            if ($filename !== '') {
                $payload['document']['filename'] = $filename;
            }

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null && $this->shouldRecord($options)) {
                $preview = $filename !== '' ? $filename : '[Documento]';
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'document', $preview, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, mixed> $options
     */
    public function sendAudioMessage($recipients, string $url, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $url = trim($url);
        if ($url === '') {
            return false;
        }

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'audio',
            'audio' => [
                'link' => $url,
            ],
        ];

        $response = $this->transport->send($config, $payload);
        $this->captureTransportDiagnostics();
        if ($response !== null && $this->shouldRecord($options)) {
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'audio', '[Audio]', $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, mixed> $options
     */
    public function sendLocationMessage($recipients, float $latitude, float $longitude, array $options = []): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $name = isset($options['name']) ? MessageSanitizer::sanitize((string) $options['name']) : '';
        $address = isset($options['address']) ? MessageSanitizer::sanitize((string) $options['address']) : '';

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'location',
                'location' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                ],
            ];

            if ($name !== '') {
                $payload['location']['name'] = $name;
            }

            if ($address !== '') {
                $payload['location']['address'] = $address;
            }

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null && $this->shouldRecord($options)) {
                $preview = sprintf('[Ubicación] %.6f, %.6f', $latitude, $longitude);
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'location', $preview, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @param string|array<int, string> $recipients
     * @param array<string, mixed> $template
     */
    public function sendTemplateMessage($recipients, array $template): bool
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return false;
        }

        $this->resetTransportDiagnostics();
        $name = trim((string) ($template['name'] ?? ''));
        $language = trim((string) ($template['language'] ?? ''));

        if ($name === '' || $language === '') {
            return false;
        }

        $components = $this->normalizeTemplateComponents($template['components'] ?? []);

        $recipients = PhoneNumberFormatter::normalizeRecipients($recipients, $config);
        if (empty($recipients)) {
            return false;
        }

        $payloadTemplate = [
            'name' => $name,
            'language' => ['code' => $language],
        ];

        if (!empty($components)) {
            $payloadTemplate['components'] = $components;
        }

        $allSucceeded = true;
        foreach ($recipients as $recipient) {
            $payload = [
                'messaging_product' => 'whatsapp',
                'to' => $recipient,
                'type' => 'template',
                'template' => $payloadTemplate,
            ];

            $response = $this->transport->send($config, $payload);
            $this->captureTransportDiagnostics();
            if ($response !== null) {
                $preview = '[Plantilla] ' . $name;
                $waMessageId = $this->extractMessageId($response);
                $this->conversations->recordOutgoing($recipient, 'template', $preview, $payload, $waMessageId);
            }

            $allSucceeded = ($response !== null) && $allSucceeded;
        }

        return $allSucceeded;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastTransportError(): ?array
    {
        return $this->lastTransportError;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getLastTransportResponse(): ?array
    {
        return $this->lastTransportResponse;
    }

    /**
     * @return array{content:string,mime_type:string,filename:string}|null
     */
    public function downloadMediaById(string $mediaId): ?array
    {
        $config = $this->settings->get();
        if (!$config['enabled']) {
            return null;
        }

        $mediaId = trim($mediaId);
        if ($mediaId === '') {
            return null;
        }

        $this->resetTransportDiagnostics();

        $apiVersion = trim((string) ($config['api_version'] ?? ''));
        $apiVersion = $apiVersion !== '' ? trim($apiVersion, '/') : 'v17.0';
        $token = (string) ($config['access_token'] ?? '');
        if (trim($token) === '') {
            $this->lastTransportError = [
                'type' => 'config',
                'message' => 'No hay token de acceso configurado para WhatsApp Cloud API.',
            ];

            return null;
        }

        $metadataUrl = 'https://graph.facebook.com/' . $apiVersion . '/' . rawurlencode($mediaId);
        $metadata = $this->requestJsonWithBearer($metadataUrl, $token);
        if ($metadata === null) {
            return null;
        }

        $downloadUrl = isset($metadata['url']) ? trim((string) $metadata['url']) : '';
        if ($downloadUrl === '') {
            $this->lastTransportError = [
                'type' => 'http',
                'message' => 'La respuesta de Meta no incluyó URL de descarga para el media ID.',
                'details' => $metadata,
            ];

            return null;
        }

        $binary = $this->requestBinaryWithBearer($downloadUrl, $token);
        if ($binary === null) {
            return null;
        }

        $mimeType = trim((string) ($metadata['mime_type'] ?? $binary['mime_type'] ?? 'application/octet-stream'));
        if ($mimeType === '') {
            $mimeType = 'application/octet-stream';
        }

        $filename = $this->resolveMediaFilename($mediaId, $metadata, $mimeType);

        return [
            'content' => $binary['body'],
            'mime_type' => $mimeType,
            'filename' => $filename,
        ];
    }

    private function resetTransportDiagnostics(): void
    {
        $this->lastTransportError = null;
        $this->lastTransportResponse = null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function requestJsonWithBearer(string $url, string $token): ?array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            $this->lastTransportError = [
                'type' => 'transport',
                'message' => 'No fue posible iniciar la solicitud de metadata de media.',
            ];

            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: application/json',
            ],
            CURLOPT_TIMEOUT => 20,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);

            $this->lastTransportError = [
                'type' => 'transport',
                'message' => 'Error al consultar metadata de media en Meta: ' . $error,
            ];

            return null;
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        curl_close($handle);

        $decoded = json_decode((string) $response, true);
        if ($status < 200 || $status >= 300) {
            $this->lastTransportError = [
                'type' => 'http',
                'http_code' => $status,
                'message' => 'Meta rechazó la consulta de media.',
                'details' => is_array($decoded) ? $decoded : ['raw' => (string) $response],
            ];

            return null;
        }

        if (!is_array($decoded)) {
            $this->lastTransportError = [
                'type' => 'decode',
                'http_code' => $status,
                'message' => 'No fue posible decodificar metadata de media.',
                'details' => ['raw' => (string) $response],
            ];

            return null;
        }

        return $decoded;
    }

    /**
     * @return array{body:string,mime_type:string}|null
     */
    private function requestBinaryWithBearer(string $url, string $token): ?array
    {
        $handle = curl_init($url);
        if ($handle === false) {
            $this->lastTransportError = [
                'type' => 'transport',
                'message' => 'No fue posible iniciar la descarga del archivo de media.',
            ];

            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $token,
                'Accept: */*',
            ],
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);

            $this->lastTransportError = [
                'type' => 'transport',
                'message' => 'Error al descargar archivo de media en Meta: ' . $error,
            ];

            return null;
        }

        $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
        $mimeType = (string) (curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?: '');
        curl_close($handle);

        if ($status < 200 || $status >= 300) {
            $decoded = json_decode((string) $response, true);
            $this->lastTransportError = [
                'type' => 'http',
                'http_code' => $status,
                'message' => 'Meta rechazó la descarga del archivo de media.',
                'details' => is_array($decoded) ? $decoded : ['raw' => (string) $response],
            ];

            return null;
        }

        return [
            'body' => (string) $response,
            'mime_type' => trim($mimeType),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function resolveMediaFilename(string $mediaId, array $metadata, string $mimeType): string
    {
        $filename = trim((string) ($metadata['filename'] ?? ''));
        if ($filename !== '') {
            return $filename;
        }

        $extension = $this->guessExtensionFromMime($mimeType);
        if ($extension !== '') {
            return 'media_' . $mediaId . '.' . $extension;
        }

        return 'media_' . $mediaId;
    }

    private function guessExtensionFromMime(string $mimeType): string
    {
        $mime = strtolower(trim($mimeType));
        if ($mime === '') {
            return '';
        }

        $map = [
            'audio/ogg' => 'ogg',
            'audio/opus' => 'opus',
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/aac' => 'aac',
            'audio/mp4' => 'm4a',
            'audio/x-m4a' => 'm4a',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
        ];

        if (isset($map[$mime])) {
            return $map[$mime];
        }

        if (str_contains($mime, '/')) {
            $parts = explode('/', $mime, 2);
            return preg_replace('/[^a-z0-9]+/', '', strtolower($parts[1])) ?: '';
        }

        return '';
    }

    private function captureTransportDiagnostics(): void
    {
        if ($this->transport instanceof CloudApiTransport) {
            $this->lastTransportError = $this->transport->getLastError();
            $this->lastTransportResponse = $this->transport->getLastResponse();
        }
    }

    /**
     * @param mixed $components
     * @return array<int, array<string, mixed>>
     */
    private function normalizeTemplateComponents($components): array
    {
        if (is_string($components)) {
            $decoded = json_decode($components, true);
            $components = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($components)) {
            return [];
        }

        $normalized = [];

        foreach ($components as $component) {
            if (!is_array($component)) {
                continue;
            }

            $type = strtoupper(trim((string) ($component['type'] ?? '')));
            if ($type === '') {
                continue;
            }

            $entry = ['type' => $type];

            if ($type === 'BUTTON') {
                if (isset($component['sub_type'])) {
                    $entry['sub_type'] = strtoupper(trim((string) $component['sub_type']));
                }

                if (isset($component['index'])) {
                    $entry['index'] = (int) $component['index'];
                }
            }

            if (!empty($component['parameters']) && is_array($component['parameters'])) {
                $parameters = [];
                foreach ($component['parameters'] as $parameter) {
                    if (!is_array($parameter)) {
                        continue;
                    }

                    $paramType = strtolower(trim((string) ($parameter['type'] ?? 'text')));
                    $param = ['type' => $paramType];

                    if (isset($parameter['text'])) {
                        $value = MessageSanitizer::sanitize((string) $parameter['text']);
                        if ($value === '') {
                            continue;
                        }
                        $param['text'] = $value;
                    }

                    if (isset($parameter['payload'])) {
                        $payloadValue = trim((string) $parameter['payload']);
                        if ($payloadValue === '') {
                            continue;
                        }
                        $param['payload'] = $payloadValue;
                    }

                    if (isset($parameter['currency'])) {
                        $param['currency'] = $parameter['currency'];
                    }

                    if (isset($parameter['date_time'])) {
                        $param['date_time'] = $parameter['date_time'];
                    }

                    if (count($param) > 1) {
                        $parameters[] = $param;
                    }
                }

                if (!empty($parameters)) {
                    $entry['parameters'] = $parameters;
                }
            }

            if (!empty($entry['parameters'])) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function shouldRecord(array $options): bool
    {
        return empty($options['skip_record']);
    }

    /**
     * @param array<string, mixed> $response
     */
    private function extractMessageId(array $response): ?string
    {
        if (isset($response['messages']) && is_array($response['messages']) && !empty($response['messages'][0]['id'])) {
            $id = (string) $response['messages'][0]['id'];

            return $id !== '' ? $id : null;
        }

        if (!empty($response['message_id'])) {
            $id = (string) $response['message_id'];

            return $id !== '' ? $id : null;
        }

        return null;
    }
}
