ALTER TABLE `protocolo_data`
    ADD COLUMN `protocolo_firmado_por` int DEFAULT NULL AFTER `status`,
    ADD COLUMN `fecha_firma` datetime DEFAULT NULL AFTER `protocolo_firmado_por`,
    ADD COLUMN `version` int unsigned NOT NULL DEFAULT 0 AFTER `fecha_firma`;

CREATE TABLE IF NOT EXISTS `protocolo_auditoria` (
    `id` int unsigned NOT NULL AUTO_INCREMENT,
    `protocolo_id` int unsigned DEFAULT NULL,
    `form_id` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `hc_number` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
    `evento` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
    `status` tinyint NOT NULL DEFAULT 0,
    `version` int unsigned NOT NULL DEFAULT 0,
    `usuario_id` int DEFAULT NULL,
    `creado_en` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_protocolo_auditoria_form` (`form_id`, `hc_number`),
    KEY `idx_protocolo_auditoria_protocolo` (`protocolo_id`),
    KEY `idx_protocolo_auditoria_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
