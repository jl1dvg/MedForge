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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $this->conversations->recordOutgoing($recipient, 'text', $message, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $this->conversations->recordOutgoing($recipient, 'interactive_buttons', $message, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $this->conversations->recordOutgoing($recipient, 'interactive_list', $message, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $preview = $caption !== '' ? $caption : '[Imagen]';
                $this->conversations->recordOutgoing($recipient, 'image', $preview, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $preview = $filename !== '' ? $filename : '[Documento]';
                $this->conversations->recordOutgoing($recipient, 'document', $preview, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $preview = sprintf('[Ubicación] %.6f, %.6f', $latitude, $longitude);
                $this->conversations->recordOutgoing($recipient, 'location', $preview, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
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

        if (!empty($template['category'])) {
            $payloadTemplate['category'] = strtoupper((string) $template['category']);
        }

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

            $sent = $this->transport->send($config, $payload);
            if ($sent) {
                $preview = '[Plantilla] ' . $name;
                $this->conversations->recordOutgoing($recipient, 'template', $preview, $payload);
            }

            $allSucceeded = $sent && $allSucceeded;
        }

        return $allSucceeded;
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

            if (isset($component['sub_type'])) {
                $entry['sub_type'] = strtoupper(trim((string) $component['sub_type']));
            }

            if (isset($component['index'])) {
                $entry['index'] = (int) $component['index'];
            }

            if (!empty($component['parameters']) && is_array($component['parameters'])) {
                $parameters = [];
                foreach ($component['parameters'] as $parameter) {
                    if (!is_array($parameter)) {
                        continue;
                    }

                    $paramType = strtoupper(trim((string) ($parameter['type'] ?? 'TEXT')));
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

            if (!empty($entry['parameters']) || !in_array($type, ['BODY', 'HEADER', 'FOOTER'], true)) {
                $normalized[] = $entry;
            } elseif (in_array($type, ['BODY', 'HEADER', 'FOOTER'], true)) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }
}
