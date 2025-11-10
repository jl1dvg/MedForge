-- Agrega columna hc_number a entidades CRM y asegura unicidad en patient_data

ALTER TABLE crm_leads
    ADD COLUMN IF NOT EXISTS hc_number VARCHAR(50) NULL AFTER id;

UPDATE crm_leads
SET hc_number = CONCAT('HC-LEAD-', id)
WHERE hc_number IS NULL OR hc_number = '';

ALTER TABLE crm_leads
    MODIFY COLUMN hc_number VARCHAR(50) NOT NULL;

ALTER TABLE crm_leads
    ADD UNIQUE INDEX IF NOT EXISTS ux_crm_leads_hc_number (hc_number);

ALTER TABLE crm_customers
    ADD COLUMN IF NOT EXISTS hc_number VARCHAR(50) NULL AFTER id;

UPDATE crm_customers
SET hc_number = CONCAT('HC-CUST-', id)
WHERE hc_number IS NULL OR hc_number = '';

ALTER TABLE crm_customers
    MODIFY COLUMN hc_number VARCHAR(50) NOT NULL;

ALTER TABLE crm_customers
    ADD UNIQUE INDEX IF NOT EXISTS ux_crm_customers_hc_number (hc_number);

ALTER TABLE patient_data
    MODIFY COLUMN hc_number VARCHAR(50) NOT NULL;

ALTER TABLE patient_data
    ADD UNIQUE INDEX IF NOT EXISTS ux_patient_data_hc_number (hc_number);
