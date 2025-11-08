-- Creación del módulo de certificación biométrica de pacientes
-- Ejecutar en MySQL/MariaDB

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_identity_certifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    patient_id VARCHAR(80) NOT NULL,
    document_number VARCHAR(40) NOT NULL,
    document_type VARCHAR(30) NOT NULL DEFAULT 'cedula',
    signature_path VARCHAR(255) DEFAULT NULL,
    signature_template JSON DEFAULT NULL,
    document_signature_path VARCHAR(255) DEFAULT NULL,
    document_front_path VARCHAR(255) DEFAULT NULL,
    document_back_path VARCHAR(255) DEFAULT NULL,
    face_image_path VARCHAR(255) DEFAULT NULL,
    face_template JSON DEFAULT NULL,
    status ENUM('pending','verified','revoked') NOT NULL DEFAULT 'verified',
    last_verification_at DATETIME DEFAULT NULL,
    last_verification_result ENUM('approved','rejected','manual_review') DEFAULT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_patient_identity_certifications_patient (patient_id),
    KEY idx_patient_identity_certifications_document (document_number),
    CONSTRAINT fk_patient_identity_certifications_patient
        FOREIGN KEY (patient_id)
        REFERENCES patient_data (hc_number)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS patient_identity_checkins (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    certification_id BIGINT UNSIGNED NOT NULL,
    verified_signature_score DECIMAL(5,2) DEFAULT NULL,
    verified_face_score DECIMAL(5,2) DEFAULT NULL,
    verification_result ENUM('approved','rejected','manual_review') NOT NULL DEFAULT 'manual_review',
    metadata JSON DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_patient_identity_checkins_certification (certification_id),
    CONSTRAINT fk_patient_identity_checkins_certification
        FOREIGN KEY (certification_id)
        REFERENCES patient_identity_certifications (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registrar permisos específicos del módulo si la tabla existe
SET @has_permissions_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'permissions'
);

SET @sql_insert_permissions := IF(
    @has_permissions_table = 0,
    'SELECT "permissions table missing"',
    'INSERT IGNORE INTO permissions (name, label) VALUES
        (''pacientes.verification.view'', ''Pacientes - Certificación biométrica: Ver''),
        (''pacientes.verification.manage'', ''Pacientes - Certificación biométrica: Gestionar'')'
);
PREPARE stmt_insert_permissions FROM @sql_insert_permissions;
EXECUTE stmt_insert_permissions;
DEALLOCATE PREPARE stmt_insert_permissions;

-- Añadir permisos a roles administrativos si corresponde
SET @has_roles_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'roles'
);

SET @sql_update_roles := IF(
    @has_roles_table = 0,
    'SELECT "roles table missing"',
    'UPDATE roles
        SET permissions =
            CASE
                WHEN permissions IS NULL OR permissions = '''' THEN ''["pacientes.verification.view","pacientes.verification.manage"]''
                WHEN JSON_CONTAINS(permissions, ''"superuser"'') THEN permissions
                ELSE JSON_ARRAY_DISTINCT(JSON_ARRAY_APPEND(
                    JSON_ARRAY_APPEND(permissions, ''$'', ''pacientes.verification.view''),
                    ''$'', ''pacientes.verification.manage''
                ))
            END
        WHERE JSON_CONTAINS(permissions, ''"administrativo"'')
           OR JSON_CONTAINS(permissions, ''"superuser"'')
    '
);
PREPARE stmt_update_roles FROM @sql_update_roles;
EXECUTE stmt_update_roles;
DEALLOCATE PREPARE stmt_update_roles;
