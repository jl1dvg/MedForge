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
        $engine = strtolower((string) ($this->config['engine'] ?? 'phpmailer'));
        if ($engine !== 'phpmailer') {
            return false;
        }

        return $this->missingRequiredFields() === [];
    }

    /**
     * @return array{success: bool, error?: string}
     */
    public function sendPatientUpdate(string $recipient, string $subject, string $body): array
    {
        $this->refresh();

        $recipient = trim($recipient);
        if ($recipient === '' || !$this->isConfigured()) {
            $missing = $this->missingRequiredFields();
            $error = $recipient === '' ? 'El destinatario está vacío' : 'SMTP no configurado';
            if ($missing !== []) {
                $error .= ': falta ' . implode(', ', $missing);
            }

            return ['success' => false, 'error' => $error];
        }

        $mailer = $this->buildMailer();
        if ($mailer === null) {
            $message = 'No se pudo inicializar el cliente SMTP para enviar la notificación a ' . $recipient;
            error_log($message);

            return ['success' => false, 'error' => 'No se pudo preparar el cliente SMTP'];
        }

        try {
            $mailer->addAddress($recipient);
            $mailer->Subject = $subject !== '' ? $subject : 'Actualización de su atención';
            $mailer->Body = $this->buildHtmlBody($body);
            $mailer->AltBody = trim($body);
            $mailer->send();

            return ['success' => true];
        } catch (MailException $exception) {
            error_log('No se pudo enviar el correo de notificación a ' . $recipient . ': ' . $exception->getMessage());

            return ['success' => false, 'error' => $mailer->ErrorInfo ?: $exception->getMessage()];
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
        $mailer->Timeout = $this->config['timeout'] ?? 15;
        if ($this->config['debug_enabled'] ?? false) {
            $mailer->SMTPDebug = 2;
            $mailer->Debugoutput = static function (string $str, int $level): void {
                error_log('SMTP[' . $level . ']: ' . $str);
            };
        }

        $encryption = $this->config['encryption'];
        if ($encryption !== '') {
            $mailer->SMTPSecure = $encryption;
        }

        $mailer->SMTPAuth = $this->config['username'] !== '' || $this->config['password'] !== '';
        if ($mailer->SMTPAuth) {
            $mailer->Username = $this->config['username'];
            $mailer->Password = $this->config['password'];
        }

        if (($this->config['allow_self_signed'] ?? false) === true) {
            $mailer->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ];
        }

        $fromName = $this->config['from_name'] !== '' ? $this->config['from_name'] : $this->config['from_address'];
        $mailer->setFrom($this->config['from_address'], $fromName);
        if ($this->config['reply_to_address'] !== '') {
            $mailer->addReplyTo(
                $this->config['reply_to_address'],
                $this->config['reply_to_name'] ?: $fromName
            );
        }

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
            'email_reply_to_address',
            'email_reply_to_name',
            'email_header',
            'email_footer',
            'email_signature',
            'smtp_timeout_seconds',
            'smtp_debug_enabled',
            'smtp_allow_self_signed',
        ]);

        return [
            'engine' => strtolower((string) ($options['mail_engine'] ?? 'phpmailer')),
            'host' => trim((string) ($options['smtp_host'] ?? '')),
            'port' => (int) ($options['smtp_port'] ?? 0),
            'encryption' => $this->resolveEncryption($options['smtp_encryption'] ?? ''),
            'username' => trim((string) ($options['smtp_username'] ?? '')),
            'password' => (string) ($options['smtp_password'] ?? ''),
            'from_address' => trim((string) ($options['email_from_address'] ?? $options['smtp_email'] ?? '')),
            'from_name' => trim((string) ($options['email_from_name'] ?? '')),
            'reply_to_address' => trim((string) ($options['email_reply_to_address'] ?? '')),
            'reply_to_name' => trim((string) ($options['email_reply_to_name'] ?? '')),
            'header' => trim((string) ($options['email_header'] ?? '')),
            'footer' => trim((string) ($options['email_footer'] ?? '')),
            'signature' => trim((string) ($options['email_signature'] ?? '')),
            'timeout' => (int) ($options['smtp_timeout_seconds'] ?? 15),
            'debug_enabled' => (bool) ($options['smtp_debug_enabled'] ?? false),
            'allow_self_signed' => (bool) ($options['smtp_allow_self_signed'] ?? false),
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

    /**
     * @return list<string>
     */
    private function missingRequiredFields(): array
    {
        $missing = [];

        if ($this->config['host'] === '') {
            $missing[] = 'servidor SMTP';
        }

        if ($this->config['port'] <= 0) {
            $missing[] = 'puerto SMTP';
        }

        if ($this->config['from_address'] === '') {
            $missing[] = 'remitente (email_from_address o smtp_email)';
        }

        return $missing;
    }
}
