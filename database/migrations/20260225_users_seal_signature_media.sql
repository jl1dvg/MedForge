ALTER TABLE `users`
    ADD COLUMN `seal_signature_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `signature_deleted_by`,
    ADD COLUMN `seal_signature_mime` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `seal_signature_path`,
    ADD COLUMN `seal_signature_size` int unsigned DEFAULT NULL AFTER `seal_signature_mime`,
    ADD COLUMN `seal_signature_hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `seal_signature_size`,
    ADD COLUMN `seal_signature_created_at` datetime DEFAULT NULL AFTER `seal_signature_hash`,
    ADD COLUMN `seal_signature_created_by` int DEFAULT NULL AFTER `seal_signature_created_at`,
    ADD COLUMN `seal_signature_updated_at` datetime DEFAULT NULL AFTER `seal_signature_created_by`,
    ADD COLUMN `seal_signature_updated_by` int DEFAULT NULL AFTER `seal_signature_updated_at`,
    ADD COLUMN `seal_signature_verified_at` datetime DEFAULT NULL AFTER `seal_signature_updated_by`,
    ADD COLUMN `seal_signature_verified_by` int DEFAULT NULL AFTER `seal_signature_verified_at`,
    ADD COLUMN `seal_signature_deleted_at` datetime DEFAULT NULL AFTER `seal_signature_verified_by`,
    ADD COLUMN `seal_signature_deleted_by` int DEFAULT NULL AFTER `seal_signature_deleted_at`;
