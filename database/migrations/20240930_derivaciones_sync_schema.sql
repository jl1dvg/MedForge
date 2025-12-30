-- Crea tablas normalizadas para derivaciones y facturación IESS
CREATE TABLE IF NOT EXISTS derivaciones_referrals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referral_code VARCHAR(120) NOT NULL,
    source VARCHAR(30) NOT NULL DEFAULT 'IESS',
    status VARCHAR(60) NULL,
    issued_by VARCHAR(255) NULL,
    priority VARCHAR(60) NULL,
    service_type VARCHAR(120) NULL,
    issued_at DATETIME NULL,
    valid_until DATETIME NULL,
    source_updated_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_derivaciones_referral_code (referral_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS derivaciones_forms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    iess_form_id VARCHAR(120) NOT NULL,
    hc_number VARCHAR(120) NULL,
    payer VARCHAR(30) NULL,
    afiliacion_raw VARCHAR(120) NULL,
    fecha_creacion DATETIME NULL,
    fecha_registro DATETIME NULL,
    fecha_vigencia DATETIME NULL,
    referido VARCHAR(255) NULL,
    diagnostico TEXT NULL,
    sede VARCHAR(255) NULL,
    parentesco VARCHAR(255) NULL,
    archivo_derivacion_path VARCHAR(500) NULL,
    source_updated_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_derivaciones_form (iess_form_id),
    KEY idx_derivaciones_form_hc (hc_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS derivaciones_referral_forms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referral_id BIGINT UNSIGNED NOT NULL,
    form_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(60) NULL,
    linked_at DATETIME NULL,
    notes TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_derivaciones_referral_form (referral_id, form_id),
    CONSTRAINT fk_derivaciones_referral FOREIGN KEY (referral_id) REFERENCES derivaciones_referrals(id) ON DELETE CASCADE,
    CONSTRAINT fk_derivaciones_form FOREIGN KEY (form_id) REFERENCES derivaciones_forms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS derivaciones_invoices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number VARCHAR(120) NOT NULL,
    referral_id BIGINT UNSIGNED NULL,
    form_id BIGINT UNSIGNED NULL,
    hc_number VARCHAR(120) NULL,
    total_amount DECIMAL(12,2) NULL DEFAULT 0,
    status VARCHAR(60) NULL,
    source VARCHAR(60) NOT NULL DEFAULT 'IESS',
    submitted_at DATETIME NULL,
    paid_at DATETIME NULL,
    rejection_reason TEXT NULL,
    source_updated_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_derivaciones_invoice (invoice_number, source),
    KEY idx_derivaciones_invoice_form (form_id),
    KEY idx_derivaciones_invoice_referral (referral_id),
    CONSTRAINT fk_derivaciones_invoice_referral FOREIGN KEY (referral_id) REFERENCES derivaciones_referrals(id) ON DELETE SET NULL,
    CONSTRAINT fk_derivaciones_invoice_form FOREIGN KEY (form_id) REFERENCES derivaciones_forms(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS derivaciones_sync_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_name VARCHAR(120) NOT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    status VARCHAR(30) NOT NULL,
    items_processed INT UNSIGNED NOT NULL DEFAULT 0,
    last_cursor BIGINT NULL,
    message TEXT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_derivaciones_sync_job (job_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS derivaciones_scrape_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    form_id VARCHAR(120) NOT NULL,
    hc_number VARCHAR(120) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'pending',
    attempts INT UNSIGNED NOT NULL DEFAULT 0,
    last_error TEXT NULL,
    next_retry_at DATETIME NULL,
    last_attempt_at DATETIME NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_derivaciones_scrape_form (form_id),
    KEY idx_derivaciones_scrape_status (status),
    KEY idx_derivaciones_scrape_next_retry (next_retry_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar datos existentes de derivaciones_form_id si la tabla está disponible
INSERT INTO derivaciones_referrals (referral_code, valid_until, source, source_updated_at)
SELECT DISTINCT d.cod_derivacion, d.fecha_vigencia, 'IESS', NOW()
FROM derivaciones_form_id d
WHERE d.cod_derivacion IS NOT NULL AND d.cod_derivacion <> ''
ON DUPLICATE KEY UPDATE
    valid_until = COALESCE(VALUES(valid_until), valid_until),
    source = COALESCE(VALUES(source), source),
    source_updated_at = VALUES(source_updated_at),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO derivaciones_forms (
    iess_form_id, hc_number, payer, afiliacion_raw, fecha_creacion, fecha_registro, fecha_vigencia,
    referido, diagnostico, sede, parentesco, archivo_derivacion_path, source_updated_at
)
SELECT DISTINCT
    d.form_id,
    d.hc_number,
    'IESS',
    NULL,
    d.fecha_creacion,
    d.fecha_registro,
    d.fecha_vigencia,
    d.referido,
    d.diagnostico,
    d.sede,
    d.parentesco,
    d.archivo_derivacion_path,
    NOW()
FROM derivaciones_form_id d
WHERE d.form_id IS NOT NULL AND d.form_id <> ''
ON DUPLICATE KEY UPDATE
    hc_number = VALUES(hc_number),
    payer = COALESCE(VALUES(payer), payer),
    afiliacion_raw = COALESCE(VALUES(afiliacion_raw), afiliacion_raw),
    fecha_creacion = VALUES(fecha_creacion),
    fecha_registro = VALUES(fecha_registro),
    fecha_vigencia = VALUES(fecha_vigencia),
    referido = VALUES(referido),
    diagnostico = VALUES(diagnostico),
    sede = VALUES(sede),
    parentesco = VALUES(parentesco),
    archivo_derivacion_path = VALUES(archivo_derivacion_path),
    source_updated_at = VALUES(source_updated_at),
    updated_at = CURRENT_TIMESTAMP;

INSERT INTO derivaciones_referral_forms (referral_id, form_id, status, linked_at, notes)
SELECT DISTINCT
    r.id,
    f.id,
    NULL,
    d.fecha_registro,
    'Sincronizado desde tabla legacy derivaciones_form_id'
FROM derivaciones_form_id d
JOIN derivaciones_referrals r ON r.referral_code = d.cod_derivacion
JOIN derivaciones_forms f ON f.iess_form_id = d.form_id
ON DUPLICATE KEY UPDATE
    linked_at = VALUES(linked_at),
    updated_at = CURRENT_TIMESTAMP;
