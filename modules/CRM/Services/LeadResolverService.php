<?php

namespace Modules\CRM\Services;

use Modules\CRM\Models\LeadModel;
use Modules\Shared\Services\PatientIdentityService;
use PDO;
use RuntimeException;

class LeadResolverService
{
    private LeadModel $leadModel;
    private PatientIdentityService $identityService;

    public function __construct(PDO $pdo)
    {
        $this->leadModel = new LeadModel($pdo);
        $this->identityService = new PatientIdentityService($pdo);
    }

    /**
     * @param array<string, mixed> $context
     */
    public function getOrCreateLeadId(array $context, ?int $userId = null): int
    {
        $hcNumber = $this->identityService->normalizeHcNumber((string)($context['hc_number'] ?? ''));
        if ($hcNumber === '') {
            throw new RuntimeException('No se pudo resolver el lead: hc_number es obligatorio.');
        }

        $candidate = [
            'name' => $this->sanitizeString($context['name'] ?? null),
            'email' => $this->sanitizeString($context['email'] ?? null),
            'phone' => $this->sanitizeString($context['phone'] ?? null),
            'source' => $this->sanitizeString($context['source'] ?? null),
            'notes' => $this->sanitizeString($context['notes'] ?? null),
            'status' => $this->sanitizeString($context['status'] ?? null),
            'assigned_to' => $this->nullableInt($context['assigned_to'] ?? null),
        ];

        $existing = $this->leadModel->findByHcNumber($hcNumber);
        if ($existing) {
            $updates = $this->collectMissingFields($existing, $candidate);
            if (!empty($updates)) {
                $this->leadModel->update($hcNumber, $updates);
                $existing = $this->leadModel->findByHcNumber($hcNumber) ?? $existing;
            }

            return (int) $existing['id'];
        }

        $payload = array_filter(
            array_merge(['hc_number' => $hcNumber], $candidate),
            static fn($value) => $value !== null && $value !== ''
        );

        $lead = $this->leadModel->create($payload, (int) ($userId ?? 0));

        return (int) $lead['id'];
    }

    /**
     * @param array<string, mixed> $existing
     * @param array<string, mixed> $candidate
     *
     * @return array<string, mixed>
     */
    private function collectMissingFields(array $existing, array $candidate): array
    {
        $updates = [];

        foreach (['name', 'email', 'phone', 'source', 'notes'] as $field) {
            if ($candidate[$field] !== null && $candidate[$field] !== '' && empty($existing[$field])) {
                $updates[$field] = $candidate[$field];
            }
        }

        if ($candidate['assigned_to'] && empty($existing['assigned_to'])) {
            $updates['assigned_to'] = $candidate['assigned_to'];
        }

        if ($candidate['status'] && empty($existing['status'])) {
            $updates['status'] = $candidate['status'];
        }

        return $updates;
    }

    private function sanitizeString(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === '' ? null : $value;
    }

    private function nullableInt($value): ?int
    {
        return ($value === null || $value === '' || !is_numeric($value)) ? null : (int) $value;
    }
}
