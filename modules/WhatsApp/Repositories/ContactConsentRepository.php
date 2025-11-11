<?php

namespace Modules\WhatsApp\Repositories;

use DateTimeImmutable;
use PDO;
use PDOException;

class ContactConsentRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $waNumber): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contact_consent WHERE wa_number = :number ORDER BY updated_at DESC LIMIT 1');
        $stmt->execute([':number' => $waNumber]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record === false) {
            return null;
        }

        if (!isset($record['identifier']) && isset($record['cedula'])) {
            $record['identifier'] = $record['cedula'];
        }

        return $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumberAndIdentifier(string $waNumber, string $identifier): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contact_consent WHERE wa_number = :number AND cedula = :identifier LIMIT 1');
        $stmt->execute([':number' => $waNumber, ':identifier' => $identifier]);

        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($record === false) {
            return null;
        }

        if (!isset($record['identifier']) && isset($record['cedula'])) {
            $record['identifier'] = $record['cedula'];
        }

        return $record;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function startOrUpdate(array $payload): bool
    {
        $sql = <<<SQL
            INSERT INTO whatsapp_contact_consent (wa_number, cedula, patient_hc_number, patient_full_name, consent_status, consent_source, consent_asked_at, extra_payload)
            VALUES (:wa_number, :identifier, :hc, :name, :status, :source, :asked_at, :payload)
            ON DUPLICATE KEY UPDATE
                patient_hc_number = VALUES(patient_hc_number),
                patient_full_name = VALUES(patient_full_name),
                consent_status = VALUES(consent_status),
                consent_source = VALUES(consent_source),
                consent_asked_at = VALUES(consent_asked_at),
                extra_payload = VALUES(extra_payload)
        SQL;

        $stmt = $this->pdo->prepare($sql);

        $encodedPayload = null;
        if (isset($payload['extra_payload'])) {
            $encodedPayload = json_encode($payload['extra_payload'], JSON_UNESCAPED_UNICODE);
        }

        $identifier = $payload['identifier'] ?? $payload['cedula'] ?? null;
        if (!is_string($identifier)) {
            $identifier = '';
        }

        return $stmt->execute([
            ':wa_number' => $payload['wa_number'],
            ':identifier' => $identifier,
            ':hc' => $payload['patient_hc_number'] ?? null,
            ':name' => $payload['patient_full_name'] ?? null,
            ':status' => $payload['consent_status'] ?? 'pending',
            ':source' => $payload['consent_source'] ?? 'local',
            ':asked_at' => $payload['consent_asked_at'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ':payload' => $encodedPayload,
        ]);
    }

    public function markConsent(string $waNumber, string $identifier, bool $accepted): bool
    {
        $status = $accepted ? 'accepted' : 'declined';
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET consent_status = :status, consent_responded_at = NOW() WHERE wa_number = :number AND cedula = :identifier'
        );

        $stmt->execute([
            ':status' => $status,
            ':number' => $waNumber,
            ':identifier' => $identifier,
        ]);

        if ($stmt->rowCount() > 0) {
            return true;
        }

        return $this->insertMissingConsentRecord($waNumber, $identifier, $status);
    }

    public function markPendingResponse(string $waNumber, string $identifier): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET consent_status = "pending", consent_responded_at = NULL WHERE wa_number = :number AND cedula = :identifier'
        );

        $stmt->execute([
            ':number' => $waNumber,
            ':identifier' => $identifier,
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function updateExtraPayload(string $waNumber, string $identifier, ?array $payload): bool
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET extra_payload = :payload WHERE wa_number = :number AND cedula = :identifier LIMIT 1'
        );

        $encoded = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);

        return $stmt->execute([
            ':payload' => $encoded,
            ':number' => $waNumber,
            ':identifier' => $identifier,
        ]);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public function reassignIdentifier(
        string $waNumber,
        string $currentIdentifier,
        string $newIdentifier,
        ?string $historyNumber,
        ?string $fullName,
        string $source,
        ?array $payload
    ): bool {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET cedula = :newIdentifier, patient_hc_number = :hc, patient_full_name = :name, consent_source = :source, extra_payload = :payload WHERE wa_number = :number AND cedula = :current LIMIT 1'
        );

        $encoded = $payload === null ? null : json_encode($payload, JSON_UNESCAPED_UNICODE);

        return $stmt->execute([
            ':newIdentifier' => $newIdentifier,
            ':hc' => $historyNumber,
            ':name' => $fullName,
            ':source' => $source,
            ':payload' => $encoded,
            ':number' => $waNumber,
            ':current' => $currentIdentifier,
        ]);
    }

    public function purgeForNumber(string $waNumber): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM whatsapp_contact_consent WHERE wa_number = :number');
            $stmt->execute([':number' => $waNumber]);
        } catch (PDOException $exception) {
            error_log('No fue posible limpiar el historial de consentimiento de WhatsApp: ' . $exception->getMessage());
        }
    }

    private function insertMissingConsentRecord(string $waNumber, string $identifier, string $status): bool
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');

        $stmt = $this->pdo->prepare(
            'INSERT INTO whatsapp_contact_consent (wa_number, cedula, patient_hc_number, patient_full_name, consent_status, consent_source, consent_asked_at, consent_responded_at)
             VALUES (:number, :identifier, NULL, NULL, :status, :source, :asked_at, :responded_at)'
        );

        return $stmt->execute([
            ':number' => $waNumber,
            ':identifier' => $identifier,
            ':status' => $status,
            ':source' => 'manual',
            ':asked_at' => $now,
            ':responded_at' => $now,
        ]);
    }
}
