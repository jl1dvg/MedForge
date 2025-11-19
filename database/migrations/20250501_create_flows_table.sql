-- Flows table used by Modules/Wpbox y Flowmaker.
-- Ejecuta este script manualmente si tu instalación no tiene las migraciones de Laravel.

CREATE TABLE IF NOT EXISTS `flows` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `flow_data` LONGTEXT NULL,
  `created_at` TIMESTAMP NULL DEFAULT NULL,
  `updated_at` TIMESTAMP NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `flows_company_id_index` (`company_id`),
  CONSTRAINT `flows_company_id_foreign`
    FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
