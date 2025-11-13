CREATE TABLE IF NOT EXISTS billing_sri_documents (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    billing_id INT NOT NULL,
    estado VARCHAR(50) NOT NULL DEFAULT 'pendiente',
    clave_acceso VARCHAR(64) DEFAULT NULL,
    numero_autorizacion VARCHAR(64) DEFAULT NULL,
    xml_enviado LONGTEXT DEFAULT NULL,
    respuesta LONGTEXT DEFAULT NULL,
    errores LONGTEXT DEFAULT NULL,
    intentos INT UNSIGNED NOT NULL DEFAULT 0,
    last_sent_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_billing_sri_documents_billing FOREIGN KEY (billing_id) REFERENCES billing_main(id) ON DELETE CASCADE,
    INDEX idx_billing_sri_documents_billing (billing_id),
    INDEX idx_billing_sri_documents_estado (estado)
);
