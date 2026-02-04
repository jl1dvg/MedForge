-- Clasificación de afiliaciones para filtros de billing (publico/privado/particular/fundacional)
CREATE TABLE IF NOT EXISTS afiliacion_categoria_map (
    id INT AUTO_INCREMENT PRIMARY KEY,
    afiliacion_raw VARCHAR(255) NOT NULL,
    afiliacion_norm VARCHAR(255) NOT NULL,
    categoria ENUM('publico','privado','particular','fundacional','otros') NOT NULL DEFAULT 'otros',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_afiliacion_norm (afiliacion_norm),
    KEY idx_categoria (categoria)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO afiliacion_categoria_map (afiliacion_raw, afiliacion_norm, categoria)
SELECT
    src.afiliacion_raw,
    src.afiliacion_norm,
    CASE
        WHEN src.afiliacion_norm LIKE '%particular%' THEN 'particular'
        WHEN src.afiliacion_norm LIKE '%fundacion%' OR src.afiliacion_norm LIKE '%fundacional%' THEN 'fundacional'
        WHEN src.afiliacion_norm REGEXP 'iess|issfa|isspol|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario' THEN 'publico'
        WHEN src.afiliacion_norm = '' THEN 'otros'
        ELSE 'privado'
    END AS categoria
FROM (
    SELECT
        TRIM(pd.afiliacion) AS afiliacion_raw,
        REPLACE(
            REPLACE(
                LOWER(
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    REPLACE(
                                        REPLACE(
                                            REPLACE(
                                                REPLACE(
                                                    REPLACE(
                                                        REPLACE(
                                                            REPLACE(
                                                                REPLACE(TRIM(pd.afiliacion), 'Á', 'A'),
                                                            'É', 'E'),
                                                        'Í', 'I'),
                                                    'Ó', 'O'),
                                                'Ú', 'U'),
                                            'Ñ', 'N'),
                                        'á', 'a'),
                                    'é', 'e'),
                                'í', 'i'),
                            'ó', 'o'),
                        'ú', 'u'),
                    'ñ', 'n')
                ),
                ' ',
                '_'
            ),
            '-',
            '_'
        ) AS afiliacion_norm
    FROM patient_data pd
    WHERE pd.afiliacion IS NOT NULL
      AND TRIM(pd.afiliacion) <> ''
) AS src
ON DUPLICATE KEY UPDATE
    afiliacion_raw = VALUES(afiliacion_raw),
    categoria = VALUES(categoria),
    updated_at = CURRENT_TIMESTAMP;
