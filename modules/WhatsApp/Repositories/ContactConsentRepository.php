<?php

namespace Modules\WhatsApp\Repositories;

use DateTimeImmutable;
use PDO;
use PDOException;

class ContactConsentRepository
{
    private PDO $pdo;
    private bool $available = true;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function isReady(): bool
    {
        return $this->available;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumber(string $waNumber): ?array
    {
        if (!$this->available) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contact_consent WHERE wa_number = :number ORDER BY updated_at DESC LIMIT 1');
            $stmt->execute([':number' => $waNumber]);

            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            return $record === false ? null : $record;
        } catch (PDOException $exception) {
            $this->handleStorageError($exception);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findByNumberAndCedula(string $waNumber, string $cedula): ?array
    {
        if (!$this->available) {
            return null;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT * FROM whatsapp_contact_consent WHERE wa_number = :number AND cedula = :cedula LIMIT 1');
            $stmt->execute([':number' => $waNumber, ':cedula' => $cedula]);

            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            return $record === false ? null : $record;
        } catch (PDOException $exception) {
            $this->handleStorageError($exception);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function startOrUpdate(array $payload): bool
    {
        if (!$this->available) {
            return false;
        }

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

        try {
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
        } catch (PDOException $exception) {
            $this->handleStorageError($exception);

            return false;
        }
    }

    public function markConsent(string $waNumber, string $cedula, bool $accepted): bool
    {
        if (!$this->available) {
            return false;
        }

        $status = $accepted ? 'accepted' : 'declined';
        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET consent_status = :status, consent_responded_at = NOW() WHERE wa_number = :number AND cedula = :cedula'
        );

        try {
            return $stmt->execute([
                ':status' => $status,
                ':number' => $waNumber,
                ':cedula' => $cedula,
            ]);
        } catch (PDOException $exception) {
            $this->handleStorageError($exception);

            return false;
        }
    }

    public function markPendingResponse(string $waNumber, string $cedula): void
    {
        if (!$this->available) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE whatsapp_contact_consent SET consent_status = "pending", consent_responded_at = NULL WHERE wa_number = :number AND cedula = :cedula'
        );

        try {
            $stmt->execute([
                ':number' => $waNumber,
                ':cedula' => $cedula,
            ]);
        } catch (PDOException $exception) {
            $this->handleStorageError($exception);
        }
    }

    public function purgeForNumber(string $waNumber): void
    {
        if (!$this->available) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('DELETE FROM whatsapp_contact_consent WHERE wa_number = :number');
            $stmt->execute([':number' => $waNumber]);
        } catch (PDOException $exception) {
            $this->handleStorageError($exception);
        }
    }

    private function handleStorageError(PDOException $exception): void
    {
        $code = $exception->getCode();
        if (in_array($code, ['42S02', '1146'], true)) {
            $this->available = false;
        }

        error_log('Repositorio de consentimiento de WhatsApp no disponible: ' . $exception->getMessage());
    }
}
