ALTER TABLE `users`
    ADD COLUMN `firma_created_at` datetime DEFAULT NULL AFTER `firma_hash`,
    ADD COLUMN `firma_created_by` int DEFAULT NULL AFTER `firma_created_at`,
    ADD COLUMN `firma_verified_at` datetime DEFAULT NULL AFTER `firma_updated_by`,
    ADD COLUMN `firma_verified_by` int DEFAULT NULL AFTER `firma_verified_at`,
    ADD COLUMN `firma_deleted_at` datetime DEFAULT NULL AFTER `firma_verified_by`,
    ADD COLUMN `firma_deleted_by` int DEFAULT NULL AFTER `firma_deleted_at`,
    ADD COLUMN `signature_created_at` datetime DEFAULT NULL AFTER `signature_hash`,
    ADD COLUMN `signature_created_by` int DEFAULT NULL AFTER `signature_created_at`,
    ADD COLUMN `signature_verified_at` datetime DEFAULT NULL AFTER `signature_updated_by`,
    ADD COLUMN `signature_verified_by` int DEFAULT NULL AFTER `signature_verified_at`,
    ADD COLUMN `signature_deleted_at` datetime DEFAULT NULL AFTER `signature_verified_by`,
    ADD COLUMN `signature_deleted_by` int DEFAULT NULL AFTER `signature_deleted_at`;

CREATE TABLE IF NOT EXISTS `user_media_history` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` int NOT NULL,
    `media_type` enum('seal', 'signature') NOT NULL,
    `version` int unsigned NOT NULL DEFAULT 1,
    `action` enum('upload', 'replace', 'delete', 'verify', 'restore') NOT NULL,
    `path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `mime` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `size` int unsigned DEFAULT NULL,
    `hash` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `previous_path` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `status` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `acted_by` int DEFAULT NULL,
    `acted_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_media_history_user_media` (`user_id`, `media_type`),
    KEY `user_media_history_version` (`user_id`, `media_type`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
