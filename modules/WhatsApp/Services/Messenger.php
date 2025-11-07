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

    public function __construct(PDO $pdo, ?TransportInterface $transport = null)
    {
        $this->settings = new WhatsAppSettings($pdo);
        $this->transport = $transport ?? new CloudApiTransport();
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

            $allSucceeded = $this->transport->send($config, $payload) && $allSucceeded;
        }

        return $allSucceeded;
    }
}
