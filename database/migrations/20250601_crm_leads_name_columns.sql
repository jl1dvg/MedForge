ALTER TABLE crm_leads
    ADD COLUMN IF NOT EXISTS first_name VARCHAR(150) DEFAULT NULL AFTER name,
    ADD COLUMN IF NOT EXISTS last_name VARCHAR(150) DEFAULT NULL AFTER first_name;

UPDATE crm_leads
SET
    first_name = COALESCE(first_name, NULLIF(TRIM(SUBSTRING_INDEX(name, ' ', 1)), '')),
    last_name = COALESCE(
        last_name,
        NULLIF(TRIM(SUBSTRING(name, LENGTH(SUBSTRING_INDEX(name, ' ', 1)) + 2)), '')
    )
WHERE name IS NOT NULL;
