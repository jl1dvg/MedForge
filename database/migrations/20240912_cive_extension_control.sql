-- Gestión centralizada de CIVE Extension
CREATE TABLE IF NOT EXISTS cive_extension_health_checks (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    endpoint VARCHAR(255) NOT NULL,
    method VARCHAR(16) NOT NULL DEFAULT 'GET',
    status_code SMALLINT DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    latency_ms INT DEFAULT NULL,
    error_message TEXT DEFAULT NULL,
    response_excerpt TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_cive_health_endpoint (endpoint),
    KEY idx_cive_health_success (success),
    KEY idx_cive_health_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
