ALTER TABLE `users`
    ADD COLUMN `birth_date` date DEFAULT NULL AFTER `second_last_name`,
    ADD COLUMN `national_id_encrypted` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `cedula`,
    ADD COLUMN `passport_number_encrypted` text COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `national_id_encrypted`,
    ADD COLUMN `seal_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' AFTER `firma_updated_by`,
    ADD COLUMN `seal_status_updated_at` datetime DEFAULT NULL AFTER `seal_status`,
    ADD COLUMN `seal_status_updated_by` int DEFAULT NULL AFTER `seal_status_updated_at`,
    ADD COLUMN `signature_status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT 'pending' AFTER `signature_updated_by`,
    ADD COLUMN `signature_status_updated_at` datetime DEFAULT NULL AFTER `signature_status`,
    ADD COLUMN `signature_status_updated_by` int DEFAULT NULL AFTER `signature_status_updated_at`;
