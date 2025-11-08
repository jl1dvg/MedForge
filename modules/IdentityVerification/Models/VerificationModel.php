<?php

namespace Modules\IdentityVerification\Models;

use PDO;

class VerificationModel
{
    private PDO $db;

    public function __construct(PDO $pdo)
    {
        $this->db = $pdo;
    }

    public function getRecent(int $limit = 25): array
    {
        $limit = max(1, min(100, $limit));
        $sql = "
            SELECT c.*, p.fname, p.mname, p.lname, p.lname2
            FROM patient_identity_certifications c
            LEFT JOIN patient_data p ON p.hc_number = c.patient_id
            ORDER BY c.updated_at DESC
            LIMIT :limit
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map([$this, 'hydrate'], $rows);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, p.fname, p.mname, p.lname, p.lname2
            FROM patient_identity_certifications c
            LEFT JOIN patient_data p ON p.hc_number = c.patient_id
            WHERE c.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function findByPatient(string $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT c.*, p.fname, p.mname, p.lname, p.lname2
            FROM patient_identity_certifications c
            LEFT JOIN patient_data p ON p.hc_number = c.patient_id
            WHERE c.patient_id = :patient
            LIMIT 1
        ");
        $stmt->bindValue(':patient', $patientId, PDO::PARAM_STR);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->hydrate($row) : null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO patient_identity_certifications (
                patient_id, document_number, document_type,
                signature_path, signature_template,
                document_signature_path, document_front_path, document_back_path,
                face_image_path, face_template, status, created_by, updated_by
            ) VALUES (
                :patient_id, :document_number, :document_type,
                :signature_path, :signature_template,
                :document_signature_path, :document_front_path, :document_back_path,
                :face_image_path, :face_template, :status, :created_by, :updated_by
            )
        ");

        $stmt->execute([
            ':patient_id' => $data['patient_id'],
            ':document_number' => $data['document_number'],
            ':document_type' => $data['document_type'] ?? 'cedula',
            ':signature_path' => $data['signature_path'] ?? null,
            ':signature_template' => $this->encodeTemplate($data['signature_template'] ?? null),
            ':document_signature_path' => $data['document_signature_path'] ?? null,
            ':document_front_path' => $data['document_front_path'] ?? null,
            ':document_back_path' => $data['document_back_path'] ?? null,
            ':face_image_path' => $data['face_image_path'] ?? null,
            ':face_template' => $this->encodeTemplate($data['face_template'] ?? null),
            ':status' => $data['status'] ?? 'verified',
            ':created_by' => $data['created_by'] ?? null,
            ':updated_by' => $data['updated_by'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE patient_identity_certifications SET
                document_number = :document_number,
                document_type = :document_type,
                signature_path = :signature_path,
                signature_template = :signature_template,
                document_signature_path = :document_signature_path,
                document_front_path = :document_front_path,
                document_back_path = :document_back_path,
                face_image_path = :face_image_path,
                face_template = :face_template,
                status = :status,
                updated_by = :updated_by
            WHERE id = :id
        ");

        $stmt->execute([
            ':document_number' => $data['document_number'],
            ':document_type' => $data['document_type'] ?? 'cedula',
            ':signature_path' => $data['signature_path'] ?? null,
            ':signature_template' => $this->encodeTemplate($data['signature_template'] ?? null),
            ':document_signature_path' => $data['document_signature_path'] ?? null,
            ':document_front_path' => $data['document_front_path'] ?? null,
            ':document_back_path' => $data['document_back_path'] ?? null,
            ':face_image_path' => $data['face_image_path'] ?? null,
            ':face_template' => $this->encodeTemplate($data['face_template'] ?? null),
            ':status' => $data['status'] ?? 'verified',
            ':updated_by' => $data['updated_by'] ?? null,
            ':id' => $id,
        ]);
    }

    public function touchVerificationMetadata(int $id, string $result): void
    {
        $stmt = $this->db->prepare("
            UPDATE patient_identity_certifications
            SET last_verification_at = NOW(),
                last_verification_result = :result
            WHERE id = :id
        ");
        $stmt->execute([
            ':result' => $result,
            ':id' => $id,
        ]);
    }

    public function logCheckin(int $certificationId, array $data): array
    {
        $stmt = $this->db->prepare("
            INSERT INTO patient_identity_checkins (
                certification_id, verified_signature_score, verified_face_score,
                verification_result, metadata, created_by
            ) VALUES (
                :certification_id, :signature_score, :face_score,
                :result, :metadata, :created_by
            )
        ");

        $stmt->execute([
            ':certification_id' => $certificationId,
            ':signature_score' => $data['verified_signature_score'] ?? null,
            ':face_score' => $data['verified_face_score'] ?? null,
            ':result' => $data['verification_result'] ?? 'manual_review',
            ':metadata' => $this->encodeTemplate($data['metadata'] ?? null),
            ':created_by' => $data['created_by'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();

        return $this->findCheckin($id) ?? [
            'id' => $id,
            'certification_id' => $certificationId,
            'verified_signature_score' => $data['verified_signature_score'] ?? null,
            'verified_face_score' => $data['verified_face_score'] ?? null,
            'verification_result' => $data['verification_result'] ?? 'manual_review',
            'metadata' => $data['metadata'] ?? null,
        ];
    }

    public function findCheckin(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM patient_identity_checkins
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$row) {
            return null;
        }

        if (isset($row['metadata'])) {
            $row['metadata'] = $this->decodeTemplate($row['metadata']);
        }

        return $row;
    }

    public function findPatientSummary(string $patientId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT hc_number, fname, mname, lname, lname2, cedula, celular, afiliacion
            FROM patient_data
            WHERE hc_number = :hc
            LIMIT 1
        ");
        $stmt->bindValue(':hc', $patientId, PDO::PARAM_STR);
        $stmt->execute();

        $patient = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        if (!$patient) {
            return null;
        }

        $patient['full_name'] = trim(implode(' ', array_filter([
            $patient['fname'] ?? null,
            $patient['mname'] ?? null,
            $patient['lname'] ?? null,
            $patient['lname2'] ?? null,
        ])));

        return $patient;
    }

    private function hydrate(array $row): array
    {
        $row['full_name'] = trim(implode(' ', array_filter([
            $row['fname'] ?? null,
            $row['mname'] ?? null,
            $row['lname'] ?? null,
            $row['lname2'] ?? null,
        ])));

        unset($row['fname'], $row['mname'], $row['lname'], $row['lname2']);

        $row['signature_template'] = $this->decodeTemplate($row['signature_template'] ?? null);
        $row['face_template'] = $this->decodeTemplate($row['face_template'] ?? null);

        if (isset($row['metadata'])) {
            $row['metadata'] = $this->decodeTemplate($row['metadata']);
        }

        return $row;
    }

    private function encodeTemplate($data): ?string
    {
        if ($data === null) {
            return null;
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function decodeTemplate($json)
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode((string) $json, true);
        return $decoded === null ? null : $decoded;
    }
}
