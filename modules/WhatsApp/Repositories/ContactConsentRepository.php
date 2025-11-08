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

            return $record === false ? null : $record;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumberAndCedula(string $waNumber, string $cedula): ?array
    {
            $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contact_consent WHERE wa_number = :number AND cedula = :cedula LIMIT 1');
            $stmt->execute([':number' => $waNumber, ':cedula' => $cedula]);

            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            return $record === false ? null : $record;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function startOrUpdate(array $payload): bool
    {
        $sql = <<<SQL
            INSERT INTO whatsapp_contact_consent (wa_number, cedula, patient_hc_number, patient_full_name, consent_status, consent_source, consent_asked_at, extra_payload)
            VALUES (:wa_number, :cedula, :hc, :name, :status, :source, :asked_at, :payload)
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

            return $stmt->execute([
                ':wa_number' => $payload['wa_number'],
                ':cedula' => $payload['cedula'],
                ':hc' => $payload['patient_hc_number'] ?? null,
                ':name' => $payload['patient_full_name'] ?? null,
                ':status' => $payload['consent_status'] ?? 'pending',
                ':source' => $payload['consent_source'] ?? 'local',
                ':asked_at' => $payload['consent_asked_at'] ?? (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                ':payload' => $encodedPayload,
            ]);
    }

    public function markConsent(string $waNumber, string $cedula, bool $accepted): bool
    {
        $status = $accepted ? 'accepted' : 'declined';
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET consent_status = :status, consent_responded_at = NOW() WHERE wa_number = :number AND cedula = :cedula'
        );

            return $stmt->execute([
                ':status' => $status,
                ':number' => $waNumber,
                ':cedula' => $cedula,
            ]);
    }

    public function markPendingResponse(string $waNumber, string $cedula): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET consent_status = "pending", consent_responded_at = NULL WHERE wa_number = :number AND cedula = :cedula'
        );

            $stmt->execute([
                ':number' => $waNumber,
                ':cedula' => $cedula,
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
}
