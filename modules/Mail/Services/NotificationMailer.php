<?php

namespace Modules\Mail\Services;

use Models\SettingsModel;
use PDO;
use PHPMailer\PHPMailer\Exception as MailException;
use PHPMailer\PHPMailer\PHPMailer;

class NotificationMailer
{
    private SettingsModel $settings;

    /** @var array<string, mixed> */
    private array $config;

    public function __construct(PDO $pdo)
    {
        $this->settings = new SettingsModel($pdo);
        $this->config = $this->loadConfig();
    }

    public function isConfigured(): bool
    {
        if (($this->config['engine'] ?? 'phpmailer') !== 'phpmailer') {
            return false;
        }

        return $this->config['host'] !== ''
            && $this->config['from_address'] !== ''
            && $this->config['port'] > 0;
    }

    public function sendPatientUpdate(string $recipient, string $subject, string $body): void
    {
        $this->refresh();

        $recipient = trim($recipient);
        if ($recipient === '' || !$this->isConfigured()) {
            return;
        }

        $mailer = $this->buildMailer();
        if ($mailer === null) {
            return;
        }

        try {
            $mailer->addAddress($recipient);
            $mailer->Subject = $subject !== '' ? $subject : 'Actualización de su atención';
            $mailer->Body = $this->buildHtmlBody($body);
            $mailer->AltBody = trim($body);
            $mailer->send();
        } catch (MailException $exception) {
            error_log('No se pudo enviar el correo de notificación a ' . $recipient . ': ' . $exception->getMessage());
        }
    }

    private function buildHtmlBody(string $content): string
    {
        $parts = [];

        if ($this->config['header'] !== '') {
            $parts[] = $this->config['header'];
        }

        $parts[] = '<p>' . nl2br(htmlentities($content, ENT_QUOTES, 'UTF-8')) . '</p>';

        if ($this->config['signature'] !== '') {
            $parts[] = '<p>' . $this->config['signature'] . '</p>';
        }

        if ($this->config['footer'] !== '') {
            $parts[] = $this->config['footer'];
        }

        return implode("\n\n", $parts);
    }

    private function buildMailer(): ?PHPMailer
    {
        $host = $this->config['host'];
        $port = (int) $this->config['port'];

        if ($host === '' || $port <= 0) {
            return null;
        }

        $mailer = new PHPMailer(true);
        $mailer->CharSet = 'UTF-8';
        $mailer->isSMTP();
        $mailer->Host = $host;
        $mailer->Port = $port;
        $mailer->isHTML(true);

        $encryption = $this->config['encryption'];
        if ($encryption !== '') {
            $mailer->SMTPSecure = $encryption;
        }

        $mailer->SMTPAuth = $this->config['username'] !== '' || $this->config['password'] !== '';
        if ($mailer->SMTPAuth) {
            $mailer->Username = $this->config['username'];
            $mailer->Password = $this->config['password'];
        }

        $fromName = $this->config['from_name'] !== '' ? $this->config['from_name'] : $this->config['from_address'];
        $mailer->setFrom($this->config['from_address'], $fromName);

        return $mailer;
    }

    public function refresh(): void
    {
        $this->config = $this->loadConfig();
    }

    /**
     * @return array<string, mixed>
     */
    private function loadConfig(): array
    {
        $options = $this->settings->getOptions([
            'mail_engine',
            'smtp_host',
            'smtp_port',
            'smtp_encryption',
            'smtp_username',
            'smtp_password',
            'smtp_email',
            'email_from_name',
            'email_from_address',
            'email_header',
            'email_footer',
            'email_signature',
        ]);

        return [
            'engine' => $options['mail_engine'] ?? 'phpmailer',
            'host' => trim((string) ($options['smtp_host'] ?? '')),
            'port' => (int) ($options['smtp_port'] ?? 0),
            'encryption' => $this->resolveEncryption($options['smtp_encryption'] ?? ''),
            'username' => trim((string) ($options['smtp_username'] ?? '')),
            'password' => (string) ($options['smtp_password'] ?? ''),
            'from_address' => trim((string) ($options['email_from_address'] ?? $options['smtp_email'] ?? '')),
            'from_name' => trim((string) ($options['email_from_name'] ?? '')),
            'header' => trim((string) ($options['email_header'] ?? '')),
            'footer' => trim((string) ($options['email_footer'] ?? '')),
            'signature' => trim((string) ($options['email_signature'] ?? '')),
        ];
    }

    private function resolveEncryption(string $value): string
    {
        $normalized = strtolower(trim($value));

        return match ($normalized) {
            'ssl' => PHPMailer::ENCRYPTION_SMTPS,
            'tls' => PHPMailer::ENCRYPTION_STARTTLS,
            default => '',
        };
    }
}
