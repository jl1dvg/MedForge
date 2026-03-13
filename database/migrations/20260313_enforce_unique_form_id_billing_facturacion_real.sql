SET @schema_name := DATABASE();

SET @table_exists := (
    SELECT COUNT(*)
    FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'billing_facturacion_real'
);

SET @sql := IF(
    @table_exists = 1,
    'DELETE b1
     FROM billing_facturacion_real b1
     INNER JOIN billing_facturacion_real b2
       ON b1.form_id = b2.form_id
      AND (
            COALESCE(b1.updated_at, b1.scraped_at) < COALESCE(b2.updated_at, b2.scraped_at)
            OR (
                COALESCE(b1.updated_at, b1.scraped_at) = COALESCE(b2.updated_at, b2.scraped_at)
                AND b1.id < b2.id
            )
          )',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @form_duplicates := IF(
    @table_exists = 1,
    (
        SELECT COUNT(*)
        FROM (
            SELECT form_id
            FROM billing_facturacion_real
            GROUP BY form_id
            HAVING COUNT(*) > 1
        ) dup
    ),
    0
);

SET @uq_form_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'billing_facturacion_real'
      AND INDEX_NAME = 'uq_billing_facturacion_real_form_id'
);

SET @sql := IF(
    @table_exists = 1 AND @uq_form_exists = 0 AND @form_duplicates = 0,
    'ALTER TABLE billing_facturacion_real ADD UNIQUE KEY uq_billing_facturacion_real_form_id (form_id)',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @idx_form_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @schema_name
      AND TABLE_NAME = 'billing_facturacion_real'
      AND INDEX_NAME = 'idx_billing_facturacion_real_form'
);

SET @sql := IF(
    @table_exists = 1 AND @idx_form_exists > 0,
    'ALTER TABLE billing_facturacion_real DROP INDEX idx_billing_facturacion_real_form',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @table_exists = 1,
    'SELECT form_id, COUNT(*) AS total
     FROM billing_facturacion_real
     WHERE form_id IS NOT NULL
     GROUP BY form_id
     HAVING COUNT(*) > 1',
    'SELECT NULL AS form_id, 0 AS total LIMIT 0'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
