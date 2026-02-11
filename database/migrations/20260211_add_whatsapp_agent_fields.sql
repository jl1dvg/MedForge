SET @tbl := 'users';

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'whatsapp_number'),
    'SELECT "whatsapp_number ya existe"',
    'ALTER TABLE users ADD COLUMN whatsapp_number VARCHAR(32) NULL AFTER email'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (SELECT 1 FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = @tbl AND column_name = 'whatsapp_notify'),
    'SELECT "whatsapp_notify ya existe"',
    'ALTER TABLE users ADD COLUMN whatsapp_notify TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_number'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
