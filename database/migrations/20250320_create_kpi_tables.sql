-- Almacena snapshots de KPIs centralizados para dashboards y reportes

CREATE TABLE IF NOT EXISTS kpi_snapshots (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    kpi_key VARCHAR(150) NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    period_granularity VARCHAR(20) NOT NULL,
    dimension_hash CHAR(64) NOT NULL,
    dimensions_json JSON NULL,
    value NUMERIC(18,4) NOT NULL DEFAULT 0,
    numerator NUMERIC(18,4) NULL,
    denominator NUMERIC(18,4) NULL,
    extra_json JSON NULL,
    computed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source_version VARCHAR(50) NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_kpi_period_dimension (kpi_key, period_start, period_end, dimension_hash),
    KEY idx_kpi_key (kpi_key),
    KEY idx_period (period_start, period_end)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS kpi_dimensions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    dimension_key VARCHAR(120) NOT NULL,
    raw_value VARCHAR(255) NOT NULL,
    normalized_value VARCHAR(255) NOT NULL,
    metadata_json JSON NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_dimension (dimension_key, normalized_value),
    KEY idx_dimension_raw (dimension_key, raw_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
