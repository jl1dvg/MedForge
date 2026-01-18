CREATE TABLE IF NOT EXISTS mail_profiles (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    slug VARCHAR(100) NOT NULL,
    name VARCHAR(150) NOT NULL,
    engine VARCHAR(50) DEFAULT 'phpmailer',
    smtp_host VARCHAR(191) DEFAULT NULL,
    smtp_port INT UNSIGNED DEFAULT NULL,
    smtp_encryption VARCHAR(20) DEFAULT NULL,
    smtp_username VARCHAR(191) DEFAULT NULL,
    smtp_password VARCHAR(191) DEFAULT NULL,
    from_address VARCHAR(191) DEFAULT NULL,
    from_name VARCHAR(191) DEFAULT NULL,
    reply_to_address VARCHAR(191) DEFAULT NULL,
    reply_to_name VARCHAR(191) DEFAULT NULL,
    header LONGTEXT DEFAULT NULL,
    footer LONGTEXT DEFAULT NULL,
    signature LONGTEXT DEFAULT NULL,
    smtp_timeout_seconds INT UNSIGNED DEFAULT 15,
    smtp_debug_enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_allow_self_signed TINYINT(1) NOT NULL DEFAULT 0,
    active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mail_profiles_slug (slug),
    KEY idx_mail_profiles_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mail_profile_assignments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    context VARCHAR(100) NOT NULL,
    profile_slug VARCHAR(100) NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_mail_profile_assignments_context (context),
    KEY idx_mail_profile_assignments_profile (profile_slug),
    CONSTRAINT fk_mail_profile_assignments_profile
        FOREIGN KEY (profile_slug) REFERENCES mail_profiles (slug)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO mail_profiles (slug, name, engine, active)
VALUES
    ('notificaciones', 'Notificaciones', 'phpmailer', 1),
    ('coordinacion_quirurgica', 'Coordinación quirúrgica', 'phpmailer', 1),
    ('imagenes', 'Imágenes', 'phpmailer', 1),
    ('admisiones', 'Admisiones', 'phpmailer', 1),
    ('comercial', 'Comercial', 'phpmailer', 1),
    ('auditoria', 'Auditoría', 'phpmailer', 1),
    ('facturacion', 'Facturación', 'phpmailer', 1)
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    engine = VALUES(engine),
    active = VALUES(active);

INSERT INTO mail_profile_assignments (context, profile_slug)
VALUES
    ('solicitudes', 'coordinacion_quirurgica'),
    ('imagenes', 'imagenes'),
    ('crm', 'notificaciones'),
    ('billing', 'facturacion'),
    ('auditoria', 'facturacion')
ON DUPLICATE KEY UPDATE
    profile_slug = VALUES(profile_slug);
