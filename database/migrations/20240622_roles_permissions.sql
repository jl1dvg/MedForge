CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    description TEXT NULL,
    permissions LONGTEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @roles_name_index := (
    SELECT COUNT(1)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'roles'
      AND INDEX_NAME = 'roles_name_unique'
);
SET @sql_roles_index := IF(
    @roles_name_index = 0,
    'ALTER TABLE roles ADD UNIQUE KEY roles_name_unique (name)',
    'SELECT 1'
);
PREPARE stmt_roles_index FROM @sql_roles_index;
EXECUTE stmt_roles_index;
DEALLOCATE PREPARE stmt_roles_index;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS role_id INT NULL AFTER permisos;

ALTER TABLE users
    ADD INDEX IF NOT EXISTS idx_users_role_id (role_id);

SET @fk_users_roles := (
    SELECT CONSTRAINT_NAME
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND CONSTRAINT_NAME = 'fk_users_roles'
      AND REFERENCED_TABLE_NAME = 'roles'
    LIMIT 1
);
SET @sql_users_fk := IF(
    @fk_users_roles IS NULL,
    'ALTER TABLE users ADD CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt_users_fk FROM @sql_users_fk;
EXECUTE stmt_users_fk;
DEALLOCATE PREPARE stmt_users_fk;
