ALTER TABLE `users`
    ADD COLUMN `firma_mime` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `firma`,
    ADD COLUMN `firma_size` int unsigned DEFAULT NULL AFTER `firma_mime`,
    ADD COLUMN `firma_hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `firma_size`,
    ADD COLUMN `firma_updated_at` datetime DEFAULT NULL AFTER `firma_hash`,
    ADD COLUMN `firma_updated_by` int DEFAULT NULL AFTER `firma_updated_at`,
    ADD COLUMN `signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `profile_photo`,
    ADD COLUMN `signature_mime` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `signature_path`,
    ADD COLUMN `signature_size` int unsigned DEFAULT NULL AFTER `signature_mime`,
    ADD COLUMN `signature_hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `signature_size`,
    ADD COLUMN `signature_updated_at` datetime DEFAULT NULL AFTER `signature_hash`,
    ADD COLUMN `signature_updated_by` int DEFAULT NULL AFTER `signature_updated_at`;
