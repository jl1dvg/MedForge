CREATE TABLE IF NOT EXISTS turnero_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reset_by INT NULL,
    solicitudes_archived INT NOT NULL DEFAULT 0,
    examenes_archived INT NOT NULL DEFAULT 0,
    criteria JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_turnero_resets_created_at (created_at),
    CONSTRAINT fk_turnero_resets_user FOREIGN KEY (reset_by) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
