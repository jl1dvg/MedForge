-- Refuerza unicidad de leads por historia clínica y vincula leads con contextos
-- Preflight: limpiar duplicados antes de agregar el UNIQUE
UPDATE crm_leads
SET hc_number = NULL
WHERE hc_number = '';

UPDATE crm_leads l
JOIN (
    SELECT hc_number, MIN(id) AS keep_id
    FROM crm_leads
    WHERE hc_number IS NOT NULL AND hc_number <> ''
    GROUP BY hc_number
    HAVING COUNT(*) > 1
) dup ON l.hc_number = dup.hc_number AND l.id <> dup.keep_id
SET l.hc_number = NULL;

ALTER TABLE crm_leads
    ADD UNIQUE KEY uq_crm_leads_hc_number (hc_number);

CREATE TABLE IF NOT EXISTS crm_lead_links (
    id INT NOT NULL AUTO_INCREMENT,
    lead_id INT NOT NULL,
    context_type VARCHAR(50) NOT NULL,
    context_id INT NOT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_crm_lead_links_context (context_type, context_id),
    KEY idx_crm_lead_links_lead (lead_id),
    CONSTRAINT fk_crm_lead_links_lead FOREIGN KEY (lead_id) REFERENCES crm_leads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO crm_lead_links (lead_id, context_type, context_id)
SELECT crm_lead_id, 'solicitud', solicitud_id
FROM solicitud_crm_detalles
WHERE crm_lead_id IS NOT NULL;

INSERT IGNORE INTO crm_lead_links (lead_id, context_type, context_id)
SELECT crm_lead_id, 'examen', examen_id
FROM examen_crm_detalles
WHERE crm_lead_id IS NOT NULL;
