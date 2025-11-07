<?php

declare(strict_types=1);

defined('BASEPATH') or exit('No direct script access allowed');

class Migration_Version_341 extends CI_Migration
{
    public function up(): void
    {
        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `medforge_cron_tasks` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `slug` VARCHAR(100) NOT NULL,
                `name` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `schedule_interval` INT UNSIGNED NOT NULL DEFAULT 300,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `last_run_at` DATETIME NULL,
                `next_run_at` DATETIME NULL,
                `last_status` VARCHAR(20) NULL,
                `last_message` TEXT NULL,
                `last_output` LONGTEXT NULL,
                `last_error` TEXT NULL,
                `last_duration_ms` INT UNSIGNED NULL,
                `failure_count` INT UNSIGNED NOT NULL DEFAULT 0,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY `idx_slug` (`slug`),
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->db->query(
            'CREATE TABLE IF NOT EXISTS `medforge_cron_logs` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `task_id` INT UNSIGNED NOT NULL,
                `started_at` DATETIME NOT NULL,
                `finished_at` DATETIME NULL,
                `status` ENUM("running","success","failed","skipped") NOT NULL DEFAULT "running",
                `message` TEXT NULL,
                `output` LONGTEXT NULL,
                `error` TEXT NULL,
                `duration_ms` INT UNSIGNED NULL,
                `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_task_started` (`task_id`, `started_at`),
                CONSTRAINT `fk_medforge_cron_logs_task`
                    FOREIGN KEY (`task_id`) REFERENCES `medforge_cron_tasks` (`id`)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }
}
