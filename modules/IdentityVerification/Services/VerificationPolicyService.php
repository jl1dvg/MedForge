<?php

namespace Modules\IdentityVerification\Services;

use Models\SettingsModel;
use PDO;
use RuntimeException;
use Throwable;

class VerificationPolicyService
{
    private const DEFAULTS = [
        'validity_days' => 365,
        'face' => [
            'approve' => 80.0,
            'reject' => 40.0,
        ],
        'signature' => [
            'approve' => 80.0,
            'reject' => 40.0,
        ],
        'single' => [
            'approve' => 85.0,
            'reject' => 40.0,
        ],
        'auto_escalate' => true,
        'escalation_channel' => 'crm_ticket',
        'escalation_priority' => 'alta',
        'escalation_assignee' => null,
        'generate_pdf' => true,
        'pdf_signature' => [
            'certificate' => '',
            'key' => '',
            'password' => '',
            'name' => '',
            'location' => '',
            'reason' => 'Consentimiento de atención verificado',
            'image' => '',
        ],
    ];

    private ?SettingsModel $settingsModel = null;
    private ?array $cache = null;

    public function __construct(PDO $pdo)
    {
        try {
            $this->settingsModel = new SettingsModel($pdo);
        } catch (RuntimeException) {
            $this->settingsModel = null;
        }
    }

    public function getValidityDays(): int
    {
        $config = $this->load();

        return max(0, (int) ($config['validity_days'] ?? self::DEFAULTS['validity_days']));
    }

    public function getFaceApproveThreshold(): float
    {
        $config = $this->load();

        return (float) ($config['face']['approve'] ?? self::DEFAULTS['face']['approve']);
    }

    public function getFaceRejectThreshold(): float
    {
        $config = $this->load();

        return (float) ($config['face']['reject'] ?? self::DEFAULTS['face']['reject']);
    }

    public function getSignatureApproveThreshold(): float
    {
        $config = $this->load();

        return (float) ($config['signature']['approve'] ?? self::DEFAULTS['signature']['approve']);
    }

    public function getSignatureRejectThreshold(): float
    {
        $config = $this->load();

        return (float) ($config['signature']['reject'] ?? self::DEFAULTS['signature']['reject']);
    }

    public function getSingleApproveThreshold(): float
    {
        $config = $this->load();

        return (float) ($config['single']['approve'] ?? self::DEFAULTS['single']['approve']);
    }

    public function getSingleRejectThreshold(): float
    {
        $config = $this->load();

        return (float) ($config['single']['reject'] ?? self::DEFAULTS['single']['reject']);
    }

    public function shouldAutoEscalate(): bool
    {
        $config = $this->load();

        return (bool) ($config['auto_escalate'] ?? self::DEFAULTS['auto_escalate']);
    }

    public function getEscalationChannel(): string
    {
        $config = $this->load();

        return (string) ($config['escalation_channel'] ?? self::DEFAULTS['escalation_channel']);
    }

    public function getEscalationPriority(): string
    {
        $config = $this->load();

        return (string) ($config['escalation_priority'] ?? self::DEFAULTS['escalation_priority']);
    }

    public function getEscalationAssignee(): ?int
    {
        $config = $this->load();
        $assignee = $config['escalation_assignee'] ?? self::DEFAULTS['escalation_assignee'];

        if ($assignee === null || $assignee === '') {
            return null;
        }

        return (int) $assignee ?: null;
    }

    public function shouldGeneratePdf(): bool
    {
        $config = $this->load();

        return (bool) ($config['generate_pdf'] ?? self::DEFAULTS['generate_pdf']);
    }

    /**
     * @return array{certificate:string,key:string,password:string,name:string,location:string,reason:string,image:string}
     */
    public function getPdfSignatureConfig(): array
    {
        $config = $this->load();
        $signature = $config['pdf_signature'] ?? [];

        return [
            'certificate' => (string) ($signature['certificate'] ?? self::DEFAULTS['pdf_signature']['certificate']),
            'key' => (string) ($signature['key'] ?? self::DEFAULTS['pdf_signature']['key']),
            'password' => (string) ($signature['password'] ?? self::DEFAULTS['pdf_signature']['password']),
            'name' => (string) ($signature['name'] ?? self::DEFAULTS['pdf_signature']['name']),
            'location' => (string) ($signature['location'] ?? self::DEFAULTS['pdf_signature']['location']),
            'reason' => (string) ($signature['reason'] ?? self::DEFAULTS['pdf_signature']['reason']),
            'image' => (string) ($signature['image'] ?? self::DEFAULTS['pdf_signature']['image']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $config = self::DEFAULTS;

        if (!($this->settingsModel instanceof SettingsModel)) {
            $this->cache = $config;

            return $this->cache;
        }

        try {
            $options = $this->settingsModel->getOptions([
                'identity_verification_validity_days',
                'identity_verification_face_approve_threshold',
                'identity_verification_face_reject_threshold',
                'identity_verification_signature_approve_threshold',
                'identity_verification_signature_reject_threshold',
                'identity_verification_single_approve_threshold',
                'identity_verification_single_reject_threshold',
                'identity_verification_auto_escalate',
                'identity_verification_escalation_channel',
                'identity_verification_escalation_priority',
                'identity_verification_escalation_assignee',
                'identity_verification_generate_pdf',
                'identity_verification_pdf_signature_certificate',
                'identity_verification_pdf_signature_key',
                'identity_verification_pdf_signature_password',
                'identity_verification_pdf_signature_name',
                'identity_verification_pdf_signature_location',
                'identity_verification_pdf_signature_reason',
                'identity_verification_pdf_signature_image',
            ]);

            $validityRaw = $options['identity_verification_validity_days'] ?? null;
            if ($validityRaw !== null && $validityRaw !== '') {
                $config['validity_days'] = max(0, (int) $validityRaw);
            }

            $this->applyThreshold($config['face'], $options, 'identity_verification_face_approve_threshold', 'identity_verification_face_reject_threshold');
            $this->applyThreshold($config['signature'], $options, 'identity_verification_signature_approve_threshold', 'identity_verification_signature_reject_threshold');
            $this->applyThreshold($config['single'], $options, 'identity_verification_single_approve_threshold', 'identity_verification_single_reject_threshold');

            $config['auto_escalate'] = ($options['identity_verification_auto_escalate'] ?? '1') === '1';

            $channel = trim((string) ($options['identity_verification_escalation_channel'] ?? ''));
            if ($channel !== '') {
                $config['escalation_channel'] = $channel;
            }

            $priority = trim((string) ($options['identity_verification_escalation_priority'] ?? ''));
            if ($priority !== '') {
                $config['escalation_priority'] = $priority;
            }

            $assigneeRaw = $options['identity_verification_escalation_assignee'] ?? null;
            if ($assigneeRaw !== null && $assigneeRaw !== '') {
                $config['escalation_assignee'] = (int) $assigneeRaw ?: null;
            }

            $config['generate_pdf'] = ($options['identity_verification_generate_pdf'] ?? '1') === '1';

            $config['pdf_signature'] = [
                'certificate' => trim((string) ($options['identity_verification_pdf_signature_certificate'] ?? '')),
                'key' => trim((string) ($options['identity_verification_pdf_signature_key'] ?? '')),
                'password' => (string) ($options['identity_verification_pdf_signature_password'] ?? ''),
                'name' => trim((string) ($options['identity_verification_pdf_signature_name'] ?? '')),
                'location' => trim((string) ($options['identity_verification_pdf_signature_location'] ?? '')),
                'reason' => trim((string) ($options['identity_verification_pdf_signature_reason'] ?? self::DEFAULTS['pdf_signature']['reason'])),
                'image' => trim((string) ($options['identity_verification_pdf_signature_image'] ?? '')),
            ];
        } catch (Throwable) {
            // Mantener valores por defecto ante errores de carga
        }

        $this->cache = $config;

        return $this->cache;
    }

    /**
     * @param array{approve: float, reject: float} $target
     * @param array<string, mixed> $options
     */
    private function applyThreshold(array &$target, array $options, string $approveKey, string $rejectKey): void
    {
        $approveRaw = $options[$approveKey] ?? null;
        if ($approveRaw !== null && $approveRaw !== '') {
            $target['approve'] = (float) $approveRaw;
        }

        $rejectRaw = $options[$rejectKey] ?? null;
        if ($rejectRaw !== null && $rejectRaw !== '') {
            $target['reject'] = (float) $rejectRaw;
        }
    }
}
