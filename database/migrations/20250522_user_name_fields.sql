-- Añade campos de nombre normalizados y restricciones básicas para users
ALTER TABLE users
    ADD COLUMN first_name VARCHAR(100) NOT NULL DEFAULT '' AFTER username,
    ADD COLUMN middle_name VARCHAR(100) NOT NULL DEFAULT '' AFTER first_name,
    ADD COLUMN last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER middle_name,
    ADD COLUMN second_last_name VARCHAR(100) NOT NULL DEFAULT '' AFTER last_name,
    ADD COLUMN full_name VARCHAR(255) GENERATED ALWAYS AS (
        TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, second_last_name))
    ) STORED;

ALTER TABLE users
    ADD INDEX idx_users_names (last_name, first_name, username);

-- Normaliza datos existentes a espacios simples
UPDATE users
SET
    first_name = TRIM(REGEXP_REPLACE(COALESCE(NULLIF(first_name, ''), ''), '\\s+', ' ')),
    middle_name = TRIM(REGEXP_REPLACE(COALESCE(NULLIF(middle_name, ''), ''), '\\s+', ' ')),
    last_name = TRIM(REGEXP_REPLACE(COALESCE(NULLIF(last_name, ''), ''), '\\s+', ' ')),
    second_last_name = TRIM(REGEXP_REPLACE(COALESCE(NULLIF(second_last_name, ''), ''), '\\s+', ' ')),
    nombre = TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, second_last_name));

DROP TRIGGER IF EXISTS users_bi_names;
DROP TRIGGER IF EXISTS users_bu_names;

DELIMITER //
CREATE TRIGGER users_bi_names
BEFORE INSERT ON users
FOR EACH ROW
BEGIN
    SET NEW.first_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.first_name, ''), '\\s+', ' '));
    SET NEW.middle_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.middle_name, ''), '\\s+', ' '));
    SET NEW.last_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.last_name, ''), '\\s+', ' '));
    SET NEW.second_last_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.second_last_name, ''), '\\s+', ' '));

    IF CHAR_LENGTH(NEW.first_name) > 100 OR CHAR_LENGTH(NEW.middle_name) > 100
        OR CHAR_LENGTH(NEW.last_name) > 100 OR CHAR_LENGTH(NEW.second_last_name) > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nombre excede el límite de 100 caracteres.';
    END IF;

    IF NEW.first_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' OR
       NEW.middle_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' OR
       NEW.last_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' OR
       NEW.second_last_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nombre contiene caracteres no permitidos.';
    END IF;

    SET NEW.nombre = TRIM(CONCAT_WS(' ', NEW.first_name, NEW.middle_name, NEW.last_name, NEW.second_last_name));
END//
DELIMITER ;

DELIMITER //
CREATE TRIGGER users_bu_names
BEFORE UPDATE ON users
FOR EACH ROW
BEGIN
    SET NEW.first_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.first_name, ''), '\\s+', ' '));
    SET NEW.middle_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.middle_name, ''), '\\s+', ' '));
    SET NEW.last_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.last_name, ''), '\\s+', ' '));
    SET NEW.second_last_name = TRIM(REGEXP_REPLACE(COALESCE(NEW.second_last_name, ''), '\\s+', ' '));

    IF CHAR_LENGTH(NEW.first_name) > 100 OR CHAR_LENGTH(NEW.middle_name) > 100
        OR CHAR_LENGTH(NEW.last_name) > 100 OR CHAR_LENGTH(NEW.second_last_name) > 100 THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nombre excede el límite de 100 caracteres.';
    END IF;

    IF NEW.first_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' OR
       NEW.middle_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' OR
       NEW.last_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' OR
       NEW.second_last_name REGEXP '[^A-Za-zÁÉÍÓÚáéíóúÜüÑñ\-\.\'""\s]' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Nombre contiene caracteres no permitidos.';
    END IF;

    SET NEW.nombre = TRIM(CONCAT_WS(' ', NEW.first_name, NEW.middle_name, NEW.last_name, NEW.second_last_name));
END//
DELIMITER ;
