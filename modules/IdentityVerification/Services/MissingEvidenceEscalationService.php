<?php

namespace Modules\IdentityVerification\Services;

use Modules\CRM\Models\TicketModel;
use PDO;
use RuntimeException;
use Throwable;

class MissingEvidenceEscalationService
{
    private ?TicketModel $tickets = null;

    public function __construct(
        private PDO $pdo,
        private VerificationPolicyService $policy
    ) {
        try {
            $this->tickets = new TicketModel($pdo);
        } catch (RuntimeException) {
            $this->tickets = null;
        }
    }

    /**
     * @param array<string, mixed> $certification
     * @param array<string, mixed> $context
     */
    public function escalate(array $certification, string $reason, array $context = []): void
    {
        if (!$this->policy->shouldAutoEscalate()) {
            return;
        }

        $channel = $this->policy->getEscalationChannel();
        if ($channel !== 'crm_ticket') {
            return;
        }

        if (!($this->tickets instanceof TicketModel)) {
            return;
        }

        $subject = $this->buildSubject($certification, $reason);
        $message = $this->buildMessage($certification, $reason, $context);
        $assignee = $this->policy->getEscalationAssignee();
        $priority = $this->policy->getEscalationPriority();
        $userId = $context['user_id'] ?? null;
        $userId = is_numeric($userId) ? (int) $userId : null;

        $existing = $this->findActiveTicketBySubject($subject);
        if ($existing !== null) {
            $this->tickets->addMessage($existing, $userId, $message);
            return;
        }

        try {
            $this->tickets->create([
                'subject' => $subject,
                'status' => 'abierto',
                'priority' => $priority,
                'assigned_to' => $assignee,
                'message' => $message,
            ], $userId);
        } catch (Throwable) {
            // Evitar romper flujo principal si no se puede escalar
        }
    }

    /**
     * @param array<string, mixed> $certification
     */
    private function buildSubject(array $certification, string $reason): string
    {
        $patientId = $certification['patient_id'] ?? 'Paciente';
        $normalizedReason = match ($reason) {
            'missing_face_capture' => 'Falta captura facial',
            'missing_signature_capture' => 'Falta captura de firma',
            'missing_biometrics' => 'Certificación incompleta',
            'expired_certification' => 'Certificación biométrica vencida',
            default => 'Seguimiento de certificación biométrica',
        };

        return sprintf('%s · HC %s', $normalizedReason, (string) $patientId);
    }

    /**
     * @param array<string, mixed> $certification
     * @param array<string, mixed> $context
     */
    private function buildMessage(array $certification, string $reason, array $context): string
    {
        $patientId = $certification['patient_id'] ?? 'Sin HC';
        $document = $certification['document_number'] ?? 'Sin documento';
        $documentType = strtoupper((string) ($certification['document_type'] ?? '')); 
        $fullName = $certification['full_name'] ?? ($context['patient_name'] ?? 'Paciente');
        $metadata = $context['metadata'] ?? [];
        $metadataLines = [];

        if (is_array($metadata)) {
            foreach ($metadata as $key => $value) {
                if (is_scalar($value)) {
                    $metadataLines[] = sprintf('- %s: %s', $key, (string) $value);
                }
            }
        }

        $reasonDescription = match ($reason) {
            'missing_face_capture' => 'El check-in facial requerido no se completó. Se registró una visita sin captura del rostro.',
            'missing_signature_capture' => 'La certificación solicita firma manuscrita y no se adjuntó en el check-in.',
            'missing_biometrics' => 'La certificación activa no cuenta con datos biométricos suficientes y debe completarse antes de atender al paciente.',
            'expired_certification' => 'La certificación superó la vigencia configurada y requiere recaptura de biometría.',
            default => 'Se detectó un incidente con la certificación biométrica del paciente.',
        };

        $lines = [
            $reasonDescription,
            '',
            sprintf('Paciente: %s', $fullName),
            sprintf('Historia clínica: %s', $patientId),
            sprintf('Documento: %s %s', $documentType, $document),
            sprintf('Enlace de certificación: %s', $this->buildCertificationUrl($patientId)),
        ];

        if ($metadataLines !== []) {
            $lines[] = '';
            $lines[] = 'Detalles adicionales:';
            $lines = array_merge($lines, $metadataLines);
        }

        return implode(PHP_EOL, $lines);
    }

    private function buildCertificationUrl(string $patientId): string
    {
        $base = rtrim(BASE_URL ?? '/', '/');

        return $base . '/pacientes/certificaciones?patient_id=' . urlencode($patientId);
    }

    private function findActiveTicketBySubject(string $subject): ?int
    {
        $sql = "SELECT id, status FROM crm_tickets WHERE subject = :subject ORDER BY created_at DESC LIMIT 1";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':subject' => $subject]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

        if (!$row) {
            return null;
        }

        if (isset($row['status']) && strtolower((string) $row['status']) === 'cerrado') {
            return null;
        }

        return isset($row['id']) ? (int) $row['id'] : null;
    }
}
