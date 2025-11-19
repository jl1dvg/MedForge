CREATE TABLE IF NOT EXISTS flowmaker_flows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    flow_key VARCHAR(191) DEFAULT NULL,
    name VARCHAR(191) NOT NULL,
    description TEXT DEFAULT NULL,
    flow_data LONGTEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_flowmaker_flows_key (flow_key),
    KEY idx_flowmaker_flows_name (name),
    CONSTRAINT fk_flowmaker_flows_creator FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_flowmaker_flows_editor FOREIGN KEY (updated_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
