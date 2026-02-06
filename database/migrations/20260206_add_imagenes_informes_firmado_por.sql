-- Firmante del informe de imágenes

ALTER TABLE imagenes_informes
    ADD COLUMN firmado_por INT DEFAULT NULL AFTER payload_json,
    ADD KEY idx_imagenes_informe_firmado (firmado_por),
    ADD CONSTRAINT fk_imagenes_informe_firmado FOREIGN KEY (firmado_por) REFERENCES users (id) ON DELETE SET NULL;
