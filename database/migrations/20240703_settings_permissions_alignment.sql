-- Unifica ajustes y permisos entre Perfex y MedForge
-- Ejecutar en un contexto MySQL/MariaDB con autocommit habilitado

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

-- ===============================
-- Sección 1: Tabla de ajustes
-- ===============================
SET @has_app_settings := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'app_settings'
);

SET @sql_create_app_settings := IF(
    @has_app_settings = 0,
    'CREATE TABLE app_settings (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        category VARCHAR(100) DEFAULT NULL,
        name VARCHAR(191) NOT NULL,
        value LONGTEXT DEFAULT NULL,
        type VARCHAR(50) DEFAULT ''text'',
        autoload TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uq_app_settings_name (name),
        KEY idx_app_settings_category (category),
        KEY idx_app_settings_autoload (autoload)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "app_settings ya existe"'
);
PREPARE stmt_create_app_settings FROM @sql_create_app_settings;
EXECUTE stmt_create_app_settings;
DEALLOCATE PREPARE stmt_create_app_settings;

-- Asegurar columnas esperadas
SET @sql_category_column := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'app_settings'
          AND COLUMN_NAME = 'category'
    ),
    'SELECT "category column ok"',
    'ALTER TABLE app_settings ADD COLUMN category VARCHAR(100) DEFAULT NULL AFTER id'
);
PREPARE stmt_category_column FROM @sql_category_column;
EXECUTE stmt_category_column;
DEALLOCATE PREPARE stmt_category_column;

SET @sql_type_column := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'app_settings'
          AND COLUMN_NAME = 'type'
    ),
    'SELECT "type column ok"',
    'ALTER TABLE app_settings ADD COLUMN type VARCHAR(50) DEFAULT ''text'' AFTER value'
);
PREPARE stmt_type_column FROM @sql_type_column;
EXECUTE stmt_type_column;
DEALLOCATE PREPARE stmt_type_column;

SET @sql_autoload_column := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'app_settings'
          AND COLUMN_NAME = 'autoload'
    ),
    'SELECT "autoload column ok"',
    'ALTER TABLE app_settings ADD COLUMN autoload TINYINT(1) NOT NULL DEFAULT 0 AFTER type'
);
PREPARE stmt_autoload_column FROM @sql_autoload_column;
EXECUTE stmt_autoload_column;
DEALLOCATE PREPARE stmt_autoload_column;

-- Cargar opciones desde la tabla clásica de Perfex (tbloptions)
SET @has_tbloptions := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tbloptions'
);

SET @sql_migrate_options := IF(
    @has_tbloptions = 0,
    'SELECT "tbloptions no existe, omitiendo migración de ajustes"',
    'INSERT INTO app_settings (category, name, value, type, autoload)
     SELECT
         CASE
             WHEN latest.name IN (''companyname'',''company_legal_name'',''companyaddress'',''company_city'',''company_country'',''company_vat'',''companyphone'',''companyemail'',''companywebsite'') THEN ''general''
             WHEN latest.name LIKE ''company_logo%'' OR latest.name IN (''companysignature'',''pdf_text_color'',''pdf_table_heading_color'',''admin_default_theme'') THEN ''branding''
             WHEN latest.name LIKE ''smtp_%'' OR latest.name IN (''mail_engine'',''email_header'',''email_footer'',''email_signature'',''email_from_name'',''email_from_address'') THEN ''email''
             WHEN latest.name IN (''default_language'',''timezone'',''dateformat'',''time_format'',''default_currency'') THEN ''localization''
             WHEN latest.name LIKE ''notifications_%'' THEN ''notifications''
             ELSE ''legacy''
         END AS category,
         latest.name,
         latest.value,
         ''text'' AS type,
         CASE
             WHEN EXISTS (
                 SELECT 1
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = ''tbloptions''
                   AND COLUMN_NAME = ''autoload''
             ) THEN COALESCE(latest.autoload, 0)
             ELSE 0
         END AS autoload
     FROM (
         SELECT t.name, t.value, t.autoload
         FROM tbloptions t
         JOIN (
             SELECT name, MAX(id) AS max_id
             FROM tbloptions
             GROUP BY name
         ) AS grouped ON grouped.name = t.name AND grouped.max_id = t.id
     ) AS latest
     ON DUPLICATE KEY UPDATE
         value = VALUES(value),
         category = CASE
             WHEN app_settings.category IN (''legacy'', ''general'') THEN VALUES(category)
             ELSE app_settings.category
         END,
         autoload = VALUES(autoload)'
);
PREPARE stmt_migrate_options FROM @sql_migrate_options;
EXECUTE stmt_migrate_options;
DEALLOCATE PREPARE stmt_migrate_options;

-- ===============================
-- Sección 2: Roles y permisos
-- ===============================
SET @has_roles_table := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'roles'
);

SET @sql_create_roles := IF(
    @has_roles_table = 0,
    'CREATE TABLE roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        description TEXT NULL,
        permissions LONGTEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_roles_name (name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT "roles ya existe"'
);
PREPARE stmt_create_roles FROM @sql_create_roles;
EXECUTE stmt_create_roles;
DEALLOCATE PREPARE stmt_create_roles;

-- Garantizar índice único por nombre
SET @sql_roles_name_index := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'roles'
          AND INDEX_NAME = 'uq_roles_name'
    ),
    'SELECT "índice uq_roles_name presente"',
    'ALTER TABLE roles ADD UNIQUE KEY uq_roles_name (name)'
);
PREPARE stmt_roles_name_index FROM @sql_roles_name_index;
EXECUTE stmt_roles_name_index;
DEALLOCATE PREPARE stmt_roles_name_index;

-- Columna permisos en users
SET @sql_users_permisos := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'permisos'
    ),
    'ALTER TABLE users MODIFY COLUMN permisos LONGTEXT NULL',
    'ALTER TABLE users ADD COLUMN permisos LONGTEXT NULL AFTER subespecialidad'
);
PREPARE stmt_users_permisos FROM @sql_users_permisos;
EXECUTE stmt_users_permisos;
DEALLOCATE PREPARE stmt_users_permisos;

SET @sql_users_role_id := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND COLUMN_NAME = 'role_id'
    ),
    'SELECT "role_id ya existe"',
    'ALTER TABLE users ADD COLUMN role_id INT NULL AFTER permisos'
);
PREPARE stmt_users_role_id FROM @sql_users_role_id;
EXECUTE stmt_users_role_id;
DEALLOCATE PREPARE stmt_users_role_id;

SET @sql_users_role_fk := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.REFERENTIAL_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND CONSTRAINT_NAME = 'fk_users_roles'
    ),
    'SELECT "fk_users_roles ya existe"',
    'ALTER TABLE users ADD CONSTRAINT fk_users_roles FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL'
);
PREPARE stmt_users_role_fk FROM @sql_users_role_fk;
EXECUTE stmt_users_role_fk;
DEALLOCATE PREPARE stmt_users_role_fk;

-- Preparar migración desde tablas legado
SET @has_tblstaff := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblstaff'
);
SET @has_tblstaffpermissions := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblstaffpermissions'
);
SET @has_tblrolepermissions := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblrolepermissions'
);
SET @has_tblpermissions := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblpermissions'
);
SET @has_tblroles := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblroles'
);

SET @should_map_permissions := (
    @has_tblstaff > 0 AND @has_tblpermissions > 0 AND @has_tblstaffpermissions > 0
);
SET @should_map_roles := (
    @has_tblroles > 0 AND @has_tblpermissions > 0 AND @has_tblrolepermissions > 0
);

SET @sql_drop_temp_map := IF(@should_map_permissions, 'DROP TEMPORARY TABLE IF EXISTS legacy_permission_map', 'SELECT 1');
PREPARE stmt_drop_temp_map FROM @sql_drop_temp_map;
EXECUTE stmt_drop_temp_map;
DEALLOCATE PREPARE stmt_drop_temp_map;

SET @sql_create_temp_map := IF(
    @should_map_permissions,
    'CREATE TEMPORARY TABLE legacy_permission_map (
        legacy VARCHAR(191) NOT NULL,
        modern VARCHAR(191) NOT NULL,
        PRIMARY KEY (legacy, modern)
    ) ENGINE=MEMORY DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
    'SELECT 1'
);
PREPARE stmt_create_temp_map FROM @sql_create_temp_map;
EXECUTE stmt_create_temp_map;
DEALLOCATE PREPARE stmt_create_temp_map;

SET @sql_seed_temp_map := IF(
    @should_map_permissions,
    'INSERT IGNORE INTO legacy_permission_map (legacy, modern) VALUES
        (''customers'', ''pacientes.view''),
        (''customers'', ''pacientes.manage''),
        (''leads'', ''pacientes.view''),
        (''projects'', ''cirugias.manage''),
        (''contracts'', ''cirugias.view''),
        (''subscriptions'', ''cirugias.view''),
        (''tasks'', ''cirugias.manage''),
        (''invoices'', ''administrativo''),
        (''estimates'', ''administrativo''),
        (''payments'', ''administrativo''),
        (''items'', ''insumos.manage''),
        (''expenses'', ''insumos.manage''),
        (''reports'', ''reportes.view''),
        (''staff'', ''admin.usuarios''),
        (''roles'', ''admin.roles''),
        (''settings'', ''settings.manage''),
        (''gdpr'', ''administrativo'')',
    'SELECT 1'
);
PREPARE stmt_seed_temp_map FROM @sql_seed_temp_map;
EXECUTE stmt_seed_temp_map;
DEALLOCATE PREPARE stmt_seed_temp_map;

SET @sql_staff_permissions_tmp := IF(
    @should_map_permissions,
    'CREATE TEMPORARY TABLE tmp_staff_permissions AS
        SELECT
            s.email AS email,
            COALESCE(m.modern, CONCAT(''legacy.'', p.shortname)) AS permission
        FROM tblstaffpermissions sp
        INNER JOIN tblpermissions p ON p.permissionid = sp.permissionid
        INNER JOIN tblstaff s ON s.staffid = sp.staffid
        LEFT JOIN legacy_permission_map m ON m.legacy = p.shortname COLLATE utf8mb4_unicode_ci
        WHERE COALESCE(sp.can_view, 0) = 1
           OR COALESCE(sp.can_view_own, 0) = 1
           OR COALESCE(sp.can_edit, 0) = 1
           OR COALESCE(sp.can_create, 0) = 1
           OR COALESCE(sp.can_delete, 0) = 1',
    'SELECT 1'
);
PREPARE stmt_staff_permissions_tmp FROM @sql_staff_permissions_tmp;
EXECUTE stmt_staff_permissions_tmp;
DEALLOCATE PREPARE stmt_staff_permissions_tmp;

SET @sql_staff_permissions_json := IF(
    @should_map_permissions,
    'CREATE TEMPORARY TABLE tmp_staff_permissions_json AS
        SELECT email,
               CAST(JSON_ARRAYAGG(DISTINCT permission) AS CHAR) AS permissions
        FROM tmp_staff_permissions
        GROUP BY email',
    'SELECT 1'
);
PREPARE stmt_staff_permissions_json FROM @sql_staff_permissions_json;
EXECUTE stmt_staff_permissions_json;
DEALLOCATE PREPARE stmt_staff_permissions_json;

SET @sql_update_user_permissions := IF(
    @should_map_permissions,
    'UPDATE users u
        INNER JOIN tmp_staff_permissions_json t ON t.email = u.email
        SET u.permisos = CASE
            WHEN u.permisos IS NULL OR TRIM(u.permisos) = '''' THEN t.permissions
            ELSE t.permissions
        END',
    'SELECT 1'
);
PREPARE stmt_update_user_permissions FROM @sql_update_user_permissions;
EXECUTE stmt_update_user_permissions;
DEALLOCATE PREPARE stmt_update_user_permissions;

SET @sql_seed_roles := IF(
    @should_map_roles,
    'INSERT INTO roles (id, name, description, permissions, created_at, updated_at)
        SELECT
            r.roleid,
            r.name,
            r.description,
            CAST(COALESCE(JSON_ARRAYAGG(DISTINCT COALESCE(m.modern, CONCAT(''legacy.'', p.shortname))), JSON_ARRAY()) AS CHAR) AS permissions,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        FROM tblroles r
        LEFT JOIN tblrolepermissions rp ON rp.roleid = r.roleid
        LEFT JOIN tblpermissions p ON p.permissionid = rp.permissionid
        LEFT JOIN legacy_permission_map m ON m.legacy = p.shortname COLLATE utf8mb4_unicode_ci
        GROUP BY r.roleid, r.name, r.description
    ON DUPLICATE KEY UPDATE
        name = VALUES(name),
        description = VALUES(description),
        permissions = VALUES(permissions),
        updated_at = CURRENT_TIMESTAMP',
    'SELECT 1'
);
PREPARE stmt_seed_roles FROM @sql_seed_roles;
EXECUTE stmt_seed_roles;
DEALLOCATE PREPARE stmt_seed_roles;

SET @staff_role_column := (
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblstaff'
      AND COLUMN_NAME IN ('role', 'roleid', 'role_id')
    LIMIT 1
);

SET @sql_update_user_roles := IF(
    @should_map_roles AND @staff_role_column IS NOT NULL,
    CONCAT('UPDATE users u
            INNER JOIN tblstaff s ON s.email = u.email
            SET u.role_id = s.', @staff_role_column, '
            WHERE s.', @staff_role_column, ' IS NOT NULL AND s.', @staff_role_column, ' <> 0'),
    'SELECT 1'
);
PREPARE stmt_update_user_roles FROM @sql_update_user_roles;
EXECUTE stmt_update_user_roles;
DEALLOCATE PREPARE stmt_update_user_roles;

SET @staff_admin_column := (
    SELECT COLUMN_NAME
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'tblstaff'
      AND COLUMN_NAME IN ('admin', 'is_admin')
    LIMIT 1
);

SET @sql_update_superusers := IF(
    @has_tblstaff > 0 AND @staff_admin_column IS NOT NULL,
    CONCAT('UPDATE users u
            INNER JOIN tblstaff s ON s.email = u.email
            SET u.permisos = ''["superuser"]''
            WHERE COALESCE(s.', @staff_admin_column, ', 0) = 1'),
    'SELECT 1'
);
PREPARE stmt_update_superusers FROM @sql_update_superusers;
EXECUTE stmt_update_superusers;
DEALLOCATE PREPARE stmt_update_superusers;

SET @sql_drop_tmp_staff_permissions := IF(@should_map_permissions, 'DROP TEMPORARY TABLE IF EXISTS tmp_staff_permissions', 'SELECT 1');
PREPARE stmt_drop_tmp_staff_permissions FROM @sql_drop_tmp_staff_permissions;
EXECUTE stmt_drop_tmp_staff_permissions;
DEALLOCATE PREPARE stmt_drop_tmp_staff_permissions;

SET @sql_drop_tmp_staff_permissions_json := IF(@should_map_permissions, 'DROP TEMPORARY TABLE IF EXISTS tmp_staff_permissions_json', 'SELECT 1');
PREPARE stmt_drop_tmp_staff_permissions_json FROM @sql_drop_tmp_staff_permissions_json;
EXECUTE stmt_drop_tmp_staff_permissions_json;
DEALLOCATE PREPARE stmt_drop_tmp_staff_permissions_json;

SET @sql_drop_temp_map_finalize := IF(@should_map_permissions, 'DROP TEMPORARY TABLE IF EXISTS legacy_permission_map', 'SELECT 1');
PREPARE stmt_drop_temp_map_finalize FROM @sql_drop_temp_map_finalize;
EXECUTE stmt_drop_temp_map_finalize;
DEALLOCATE PREPARE stmt_drop_temp_map_finalize;

-- Asegurar valor por defecto para permisos vacíos
UPDATE users
SET permisos = '[]'
WHERE permisos IS NULL OR TRIM(permisos) = '';

-- Índice auxiliar para búsquedas por rol
SET @sql_users_role_index := IF(
    EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'users'
          AND INDEX_NAME = 'idx_users_role_id'
    ),
    'SELECT "idx_users_role_id existente"',
    'CREATE INDEX idx_users_role_id ON users (role_id)'
);
PREPARE stmt_users_role_index FROM @sql_users_role_index;
EXECUTE stmt_users_role_index;
DEALLOCATE PREPARE stmt_users_role_index;
