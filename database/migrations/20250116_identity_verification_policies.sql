-- Extiende el catálogo del módulo de certificación biométrica para soportar caducidad y políticas configurables

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE patient_identity_certifications
    MODIFY status ENUM('pending','verified','revoked','expired') NOT NULL DEFAULT 'verified',
    ADD COLUMN expired_at DATETIME DEFAULT NULL AFTER status,
    MODIFY last_verification_result ENUM('approved','rejected','manual_review','expired') DEFAULT NULL;

-- Ajusta registros inconsistentes para evitar certificados "verificados" sin biometría
UPDATE patient_identity_certifications
SET status = 'pending'
WHERE status = 'verified'
  AND (
        signature_path IS NULL
        OR signature_template IS NULL
        OR face_image_path IS NULL
        OR face_template IS NULL
        OR document_number IS NULL
    );
