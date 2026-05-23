<?php

declare(strict_types=1);

namespace App\Modules\IdentityVerification\Services;

use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class MissingEvidenceEscalationService
{
    public function __construct(
        private readonly VerificationPolicyService $policy
    ) {
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

        $subject = $this->buildSubject($certification, $reason);
        $message = $this->buildMessage($certification, $reason, $context);
        $assignee = $this->policy->getEscalationAssignee();
        $priority = $this->policy->getEscalationPriority();
        $userId = $context['user_id'] ?? null;
        $userId = is_numeric($userId) ? (int) $userId : null;

        $existing = $this->findActiveTicketBySubject($subject);
        if ($existing !== null) {
            $this->addMessage($existing, $userId, $message);
            return;
        }

        try {
            $this->createTicket([
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

    private function createTicket(array $data, ?int $userId): void
    {
        $status = strtolower(trim((string) ($data['status'] ?? 'abierto')));
        $priority = strtolower(trim((string) ($data['priority'] ?? 'media')));
        $assigned = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;

        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO crm_tickets
                (subject, status, priority, assigned_to, created_by)
            VALUES
                (:subject, :status, :priority, :assigned_to, :created_by)
        ");

        $stmt->bindValue(':subject', trim((string) ($data['subject'] ?? '')));
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':priority', $priority);
        $stmt->bindValue(':assigned_to', $assigned, $assigned !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $userId, $userId !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        $ticketId = (int) $pdo->lastInsertId();

        if (!empty($data['message'])) {
            $this->addMessage($ticketId, $userId, $data['message']);
        }
    }

    private function addMessage(int $ticketId, ?int $authorId, string $message): void
    {
        $pdo = DB::connection()->getPdo();
        $stmt = $pdo->prepare("
            INSERT INTO crm_ticket_messages (ticket_id, author_id, message)
            VALUES (:ticket_id, :author_id, :message)
        ");

        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':author_id' => $authorId ?: null,
            ':message' => trim($message),
        ]);

        $pdo->prepare('UPDATE crm_tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = :id')
            ->execute([':id' => $ticketId]);
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
            sprintf('Enlace de certificación: %s', $this->buildCertificationUrl((string) $patientId)),
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
        $base = rtrim(config('app.url', ''), '/');

        return $base . '/pacientes/certificaciones?patient_id=' . urlencode($patientId);
    }

    private function findActiveTicketBySubject(string $subject): ?int
    {
        $pdo = DB::connection()->getPdo();
        $sql = "SELECT id, status FROM crm_tickets WHERE subject = :subject ORDER BY created_at DESC LIMIT 1";
        $stmt = $pdo->prepare($sql);
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
