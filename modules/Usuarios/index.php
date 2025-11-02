<?php

/**
 * Inicializador para el módulo de Usuarios y Roles.
 * Se encarga de preparar las tablas necesarias en la base de datos.
 */
if (!isset($pdo) || !$pdo instanceof PDO) {
    return;
}

try {
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS roles (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            description TEXT NULL,
            permissions TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    SQL);
} catch (Throwable $e) {
    // No interrumpir la carga del módulo si falla la creación de la tabla.
}

try {
    $columnCheck = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'role_id'");
    if ($columnCheck->execute() && !$columnCheck->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role_id INT NULL AFTER permisos");
        $pdo->exec("ALTER TABLE users ADD CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL");
    }
} catch (Throwable $e) {
    // Si no es posible añadir la columna o la clave foránea simplemente continuamos.
}
