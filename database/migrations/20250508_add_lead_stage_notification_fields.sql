ALTER TABLE crm_leads
    ADD COLUMN last_stage_notified VARCHAR(191) DEFAULT NULL AFTER status,
    ADD COLUMN last_stage_notified_at DATETIME DEFAULT NULL AFTER last_stage_notified,
    ADD INDEX idx_crm_leads_last_stage_notified (last_stage_notified, last_stage_notified_at);
