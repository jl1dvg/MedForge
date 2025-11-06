ALTER TABLE `users`
    ADD COLUMN `profile_photo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL AFTER `firma`;
