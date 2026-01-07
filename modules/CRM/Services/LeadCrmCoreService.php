<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\LeadModel;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class LeadCrmCoreService
{
    public const CONTEXT_SOLICITUD = 'solicitud';
    public const CONTEXT_EXAMEN = 'examen';

    private PDO $pdo;
    private LeadModel $leadModel;
    private LeadConfigurationService $config;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->leadModel = new LeadModel($pdo);
        $this->config = new LeadConfigurationService($pdo);
    }

    public function getOrCreateLeadByHcNumber(string $hcNumber, array $context = []): array
    {
        $existing = $this->leadModel->findByHcNumber($hcNumber);
        if ($existing) {
            return $existing;
        }

        $payload = [
            'hc_number' => $hcNumber,
            'name' => trim((string) ($context['name'] ?? 'Lead ' . $hcNumber)),
            'email' => $context['email'] ?? null,
            'phone' => $context['phone'] ?? null,
            'source' => $context['source'] ?? null,
            'assigned_to' => $context['assigned_to'] ?? null,
            'status' => $context['status'] ?? null,
            'notes' => $context['notes'] ?? null,
        ];

        $actorId = (int) ($context['actor_id'] ?? 0);

        try {
            $created = $this->leadModel->create($payload, $actorId);
            if ($created) {
                return $created;
            }
        } catch (RuntimeException $exception) {
            // Posible carrera: otro proceso insertó el lead.
            $duplicate = $this->leadModel->findByHcNumber($hcNumber);
            if ($duplicate) {
                return $duplicate;
            }

            throw $exception;
        } catch (PDOException $exception) {
            if ($this->isDuplicateKey($exception)) {
                $duplicate = $this->leadModel->findByHcNumber($hcNumber);
                if ($duplicate) {
                    return $duplicate;
                }
            }

            throw $exception;
        }

        throw new RuntimeException('No se pudo crear el lead para la historia clínica proporcionada.');
    }

    public function saveLeadFromContext(
        string $contextType,
        int $contextId,
        string $hcNumber,
        array $payload,
        ?int $actorId = null
    ): array {
        $payload = $this->normalizeStatusPayload($payload);
        $contextualPayload = $payload;
        $contextualPayload['actor_id'] = $actorId;

        $startedTransaction = false;
        if (!$this->pdo->inTransaction()) {
            $this->pdo->beginTransaction();
            $startedTransaction = true;
        }

        try {
            $lead = $this->getOrCreateLeadByHcNumber($hcNumber, $contextualPayload);
            $leadId = (int) $lead['id'];

            $this->linkLeadToContext($leadId, $contextType, $contextId);
            $this->updateContextLeadId($contextType, $contextId, $leadId);

            $payload = $this->applyContextRestrictions($contextType, $payload, $lead);

            $updated = $this->saveLeadDetails($leadId, $payload);
            if (!empty($payload['status'])) {
                $updated = $this->changeStage($leadId, (string) $payload['status'], $actorId);
            }

            if ($startedTransaction) {
                $this->pdo->commit();
            }

            return $updated;
        } catch (Throwable $exception) {
            if ($startedTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            error_log('CRM ▶ No se pudo guardar lead desde contexto: ' . ($exception->getMessage() ?: get_class($exception)));
            throw $exception;
        }
    }

    public function getResumen(int $leadId, ?string $contextType = null, ?int $contextId = null): array
    {
        $lead = $this->leadModel->findById($leadId);
        if (!$lead) {
            throw new RuntimeException('Lead no encontrado');
        }

        return [
            'lead' => $lead,
            'context' => [
                'type' => $contextType,
                'id' => $contextId,
                'link' => $this->findContextLink($leadId, $contextType, $contextId),
            ],
        ];
    }

    public function saveLeadDetails(int $leadId, array $payload): array
    {
        $payload = $this->normalizeStatusPayload($payload);
        $allowed = ['name', 'email', 'phone', 'source', 'assigned_to', 'notes', 'status'];
        $sanitized = array_intersect_key($payload, array_flip($allowed));

        if (isset($sanitized['status'])) {
            $sanitized['status'] = $this->config->normalizeStage($sanitized['status']);
        }

        $updated = $this->leadModel->updateById($leadId, $sanitized);
        if ($updated) {
            return $updated;
        }

        $lead = $this->leadModel->findById($leadId);
        if ($lead) {
            return $lead;
        }

        throw new RuntimeException('No se pudo guardar los detalles del lead');
    }

    public function changeStage(int $leadId, string $stage, ?int $actorId = null): array
    {
        $normalized = $this->config->normalizeStage($stage);
        $updated = $this->leadModel->updateStatusById($leadId, $normalized);
        if ($updated) {
            $this->ensureNextTaskOnStageChange($leadId, $normalized, $actorId);

            return $updated;
        }

        $lead = $this->leadModel->findById($leadId);
        if ($lead) {
            return $lead;
        }

        throw new RuntimeException('No se pudo actualizar la etapa del lead');
    }

    public function ensureNextTaskOnStageChange(int $leadId, string $stage, ?int $actorId = null): void
    {
        // Lugar para reglas de tareas automáticas o bloqueos configurables.
        // Por ahora no se crean tareas automáticas.
    }

    public function linkLeadToContext(int $leadId, string $contextType, int $contextId): void
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO crm_lead_links (lead_id, context_type, context_id)'
            . ' VALUES (:lead_id, :context_type, :context_id)'
            . ' ON DUPLICATE KEY UPDATE lead_id = VALUES(lead_id)'
        );

        $stmt->bindValue(':lead_id', $leadId, PDO::PARAM_INT);
        $stmt->bindValue(':context_type', trim($contextType));
        $stmt->bindValue(':context_id', $contextId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function findContextLink(int $leadId, ?string $contextType, ?int $contextId): ?array
    {
        $sql = 'SELECT id, lead_id, context_type, context_id, created_at FROM crm_lead_links WHERE lead_id = :lead_id';
        $params = [':lead_id' => $leadId];

        if ($contextType !== null) {
            $sql .= ' AND context_type = :context_type';
            $params[':context_type'] = $contextType;
        }

        if ($contextId !== null) {
            $sql .= ' AND context_id = :context_id';
            $params[':context_id'] = $contextId;
        }

        $sql .= ' ORDER BY id DESC LIMIT 1';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        $link = $stmt->fetch(PDO::FETCH_ASSOC);

        return $link ?: null;
    }

    private function updateContextLeadId(string $contextType, int $contextId, int $leadId): void
    {
        switch ($contextType) {
            case self::CONTEXT_SOLICITUD:
                $stmt = $this->pdo->prepare(
                    'UPDATE solicitud_crm_detalles SET crm_lead_id = :lead_id WHERE solicitud_id = :context_id'
                );
                break;
            case self::CONTEXT_EXAMEN:
                $stmt = $this->pdo->prepare(
                    'UPDATE examen_crm_detalles SET crm_lead_id = :lead_id WHERE examen_id = :context_id'
                );
                break;
            default:
                return;
        }

        $stmt->bindValue(':lead_id', $leadId, PDO::PARAM_INT);
        $stmt->bindValue(':context_id', $contextId, PDO::PARAM_INT);
        $stmt->execute();
    }

    private function normalizeStatusPayload(array $payload): array
    {
        if (
            (!isset($payload['status']) || $payload['status'] === null || $payload['status'] === '')
            && isset($payload['pipeline_stage'])
        ) {
            $payload['status'] = $payload['pipeline_stage'];
        }

        return $payload;
    }

    private function applyContextRestrictions(string $contextType, array $payload, array $lead): array
    {
        if (!in_array($contextType, [self::CONTEXT_SOLICITUD, self::CONTEXT_EXAMEN], true)) {
            return $payload;
        }

        foreach (['email', 'phone', 'source', 'assigned_to'] as $field) {
            if (!array_key_exists($field, $payload)) {
                continue;
            }

            $current = $lead[$field] ?? null;
            if ($current !== null && $current !== '') {
                unset($payload[$field]);
            }
        }

        return $payload;
    }

    private function isDuplicateKey(PDOException $exception): bool
    {
        $sqlState = $exception->getCode();
        if ($sqlState === '23000') {
            return true;
        }

        $message = mb_strtolower($exception->getMessage(), 'UTF-8');

        return str_contains($message, 'duplicate') || str_contains($message, 'duplicada') || str_contains($message, '1062');
    }
}
