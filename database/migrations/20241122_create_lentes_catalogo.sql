-- Catálogo de lentes (marca/modelo/nombre y poder opcional)
CREATE TABLE IF NOT EXISTS lentes_catalogo (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marca VARCHAR(100) NOT NULL,
    modelo VARCHAR(150) NOT NULL,
    nombre VARCHAR(200) NOT NULL,
    poder VARCHAR(50) DEFAULT NULL,
    observacion VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_marca_modelo_nombre (marca, modelo, nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
