<?php

declare(strict_types=1);

namespace App\Modules\Shared\Support;

use PDO;

class AfiliacionDimensionService
{
    /** @var array<string,bool> */
    private array $tableExistsCache = [];

    /** @var array<string,bool> */
    private array $columnExistsCache = [];

    public function __construct(private readonly PDO $db)
    {
    }

    /**
     * @return array{
     *   join:string,
     *   categoria_expr:string,
     *   empresa_key_expr:string,
     *   empresa_label_expr:string,
     *   seguro_key_expr:string,
     *   seguro_label_expr:string
     * }
     */
    public function buildContext(string $rawAffiliationExpr, string $mapAlias = 'acm'): array
    {
        $afiliacionNormExpr = $this->normalizeSqlKey($rawAffiliationExpr);
        $categoriaFallbackExpr = $this->fallbackCategoriaExpr($afiliacionNormExpr);
        $empresaFallbackKeyExpr = $this->fallbackEmpresaKeyExpr($afiliacionNormExpr);
        $empresaFallbackLabelExpr = $this->fallbackEmpresaLabelExpr($rawAffiliationExpr, $afiliacionNormExpr);
        $seguroFallbackKeyExpr = "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'sin_convenio'
            ELSE {$afiliacionNormExpr}
        END";
        $seguroFallbackLabelExpr = "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'Sin convenio'
            ELSE TRIM(COALESCE({$rawAffiliationExpr}, ''))
        END";

        if (
            $this->tableExists('afiliacion_categoria_map')
            && $this->columnExists('afiliacion_categoria_map', 'afiliacion_norm')
            && $this->columnExists('afiliacion_categoria_map', 'afiliacion_raw')
            && $this->columnExists('afiliacion_categoria_map', 'categoria')
        ) {
            $join = "LEFT JOIN afiliacion_categoria_map {$mapAlias}
                     ON ({$mapAlias}.afiliacion_norm COLLATE utf8mb4_unicode_ci)
                      = ({$afiliacionNormExpr} COLLATE utf8mb4_unicode_ci)";

            $categoriaExpr = "LOWER(COALESCE(NULLIF({$mapAlias}.categoria, ''), {$categoriaFallbackExpr}))";
            $empresaSourceExpr = $this->columnExists('afiliacion_categoria_map', 'empresa_seguro')
                ? "COALESCE(NULLIF(TRIM({$mapAlias}.empresa_seguro), ''), {$empresaFallbackLabelExpr})"
                : $empresaFallbackLabelExpr;
            $empresaKeyExpr = "CASE
                WHEN {$afiliacionNormExpr} = '' THEN 'sin_convenio'
                ELSE " . $this->normalizeSqlKey($empresaSourceExpr) . "
            END";
            $empresaLabelExpr = "CASE
                WHEN {$afiliacionNormExpr} = '' THEN 'Sin convenio'
                ELSE {$empresaSourceExpr}
            END";
            $seguroKeyExpr = "CASE
                WHEN {$afiliacionNormExpr} = '' THEN 'sin_convenio'
                ELSE COALESCE(NULLIF({$mapAlias}.afiliacion_norm, ''), {$seguroFallbackKeyExpr})
            END";
            $seguroLabelExpr = "CASE
                WHEN {$afiliacionNormExpr} = '' THEN 'Sin convenio'
                ELSE COALESCE(NULLIF(TRIM({$mapAlias}.afiliacion_raw), ''), {$seguroFallbackLabelExpr})
            END";

            return [
                'join' => $join,
                'categoria_expr' => $categoriaExpr,
                'empresa_key_expr' => $empresaKeyExpr,
                'empresa_label_expr' => $empresaLabelExpr,
                'seguro_key_expr' => $seguroKeyExpr,
                'seguro_label_expr' => $seguroLabelExpr,
            ];
        }

        return [
            'join' => '',
            'categoria_expr' => $categoriaFallbackExpr,
            'empresa_key_expr' => $empresaFallbackKeyExpr,
            'empresa_label_expr' => $empresaFallbackLabelExpr,
            'seguro_key_expr' => $seguroFallbackKeyExpr,
            'seguro_label_expr' => $seguroFallbackLabelExpr,
        ];
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function getCategoriaOptions(string $allLabel = 'Todas las categorías'): array
    {
        $options = [
            ['value' => '', 'label' => $allLabel],
            ['value' => 'publico', 'label' => 'Pública'],
            ['value' => 'privado', 'label' => 'Privada'],
            ['value' => 'particular', 'label' => 'Particular'],
            ['value' => 'fundacional', 'label' => 'Fundacional'],
            ['value' => 'otros', 'label' => 'Otros'],
        ];

        if (
            !$this->tableExists('afiliacion_categoria_map')
            || !$this->columnExists('afiliacion_categoria_map', 'categoria')
        ) {
            return $options;
        }

        $stmt = $this->db->query('SELECT DISTINCT categoria FROM afiliacion_categoria_map WHERE categoria IS NOT NULL AND TRIM(categoria) <> \'\' ORDER BY categoria ASC');
        if (!$stmt) {
            return $options;
        }

        $seen = [];
        foreach ($options as $option) {
            $seen[(string) ($option['value'] ?? '')] = true;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $value = strtolower(trim((string) ($row['categoria'] ?? '')));
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = ['value' => $value, 'label' => $this->formatCategoriaLabel($value)];
            $seen[$value] = true;
        }

        return $options;
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function getEmpresaOptions(string $allLabel = 'Todas las empresas'): array
    {
        $options = [
            ['value' => '', 'label' => $allLabel],
            ['value' => 'sin_convenio', 'label' => 'Sin convenio'],
        ];

        if (!$this->tableExists('afiliacion_categoria_map')) {
            return $options;
        }

        $select = $this->columnExists('afiliacion_categoria_map', 'empresa_seguro')
            ? "COALESCE(NULLIF(TRIM(empresa_seguro), ''), NULLIF(TRIM(afiliacion_raw), ''), 'Sin convenio') AS empresa_label"
            : "COALESCE(NULLIF(TRIM(afiliacion_raw), ''), 'Sin convenio') AS empresa_label";

        $stmt = $this->db->query("SELECT {$select} FROM afiliacion_categoria_map ORDER BY empresa_label ASC");
        if (!$stmt) {
            return $options;
        }

        $seen = [];
        foreach ($options as $option) {
            $seen[(string) ($option['value'] ?? '')] = true;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $label = $this->resolveEmpresaLabelFromRaw(trim((string) ($row['empresa_label'] ?? '')));
            $value = $this->normalizeEmpresaFilter($label);
            if ($label === '') {
                $label = 'Sin convenio';
            }
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = ['value' => $value, 'label' => $label];
            $seen[$value] = true;
        }

        return $options;
    }

    /**
     * @return array<int,array{value:string,label:string}>
     */
    public function getSeguroOptions(string $allLabel = 'Todos los seguros'): array
    {
        $options = [
            ['value' => '', 'label' => $allLabel],
            ['value' => 'sin_convenio', 'label' => 'Sin convenio'],
        ];

        if (
            !$this->tableExists('afiliacion_categoria_map')
            || !$this->columnExists('afiliacion_categoria_map', 'afiliacion_norm')
            || !$this->columnExists('afiliacion_categoria_map', 'afiliacion_raw')
        ) {
            return $options;
        }

        $stmt = $this->db->query(
            "SELECT afiliacion_norm, COALESCE(NULLIF(TRIM(afiliacion_raw), ''), 'Sin convenio') AS afiliacion_label
             FROM afiliacion_categoria_map
             ORDER BY afiliacion_label ASC"
        );
        if (!$stmt) {
            return $options;
        }

        $seen = [];
        foreach ($options as $option) {
            $seen[(string) ($option['value'] ?? '')] = true;
        }

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $label = trim((string) ($row['afiliacion_label'] ?? ''));
            $value = $this->normalizeSeguroFilter((string) ($row['afiliacion_norm'] ?? $label));
            if ($label === '') {
                $label = 'Sin convenio';
            }
            if ($value === '' || isset($seen[$value])) {
                continue;
            }

            $options[] = ['value' => $value, 'label' => $label];
            $seen[$value] = true;
        }

        return $options;
    }

    public function normalizeCategoriaFilter(string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === 'publica') {
            return 'publico';
        }
        if ($value === 'privada') {
            return 'privado';
        }

        return $value;
    }

    public function normalizeEmpresaFilter(string $value): string
    {
        return $this->normalizeKeyValue($value);
    }

    public function normalizeSeguroFilter(string $value): string
    {
        return $this->normalizeKeyValue($value);
    }

    public function resolveEmpresaLabel(string $value): string
    {
        return $this->resolveEmpresaLabelFromRaw(trim($value));
    }

    public function formatCategoriaLabel(string $key): string
    {
        return match ($key) {
            'publico' => 'Pública',
            'privado' => 'Privada',
            'particular' => 'Particular',
            'fundacional' => 'Fundacional',
            'otros' => 'Otros',
            default => ucwords(str_replace('_', ' ', $key)),
        };
    }

    private function normalizeKeyValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $normalized = strtolower(strtr($value, [
            'Á' => 'A',
            'É' => 'E',
            'Í' => 'I',
            'Ó' => 'O',
            'Ú' => 'U',
            'Ñ' => 'N',
            'á' => 'a',
            'é' => 'e',
            'í' => 'i',
            'ó' => 'o',
            'ú' => 'u',
            'ñ' => 'n',
        ]));
        $normalized = str_replace([' ', '-'], '_', $normalized);
        $normalized = preg_replace('/_+/', '_', $normalized) ?? $normalized;
        $normalized = trim($normalized, '_');

        if ($normalized === '' || $normalized === 'sin_afiliacion' || $normalized === 'sin_afiliaciones') {
            return 'sin_convenio';
        }
        if ($normalized === 'sin_convenio') {
            return 'sin_convenio';
        }

        return $normalized;
    }

    private function fallbackCategoriaExpr(string $afiliacionNormExpr): string
    {
        return "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'otros'
            WHEN {$afiliacionNormExpr} LIKE '%particular%' THEN 'particular'
            WHEN {$afiliacionNormExpr} LIKE '%fundacion%' OR {$afiliacionNormExpr} LIKE '%fundacional%' THEN 'fundacional'
            WHEN {$afiliacionNormExpr} REGEXP 'iess|issfa|isspol|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|publico|msp|ministerio_salud' THEN 'publico'
            ELSE 'privado'
        END";
    }

    private function fallbackEmpresaKeyExpr(string $afiliacionNormExpr): string
    {
        return "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'sin_convenio'
            WHEN {$afiliacionNormExpr} REGEXP '(^|_)iess($|_)|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|hijos_dependientes|conyuge|pensionista' THEN 'iess'
            WHEN {$afiliacionNormExpr} LIKE 'issfa%' THEN 'issfa'
            WHEN {$afiliacionNormExpr} LIKE 'isspol%' THEN 'isspol'
            WHEN {$afiliacionNormExpr} LIKE 'salud%' THEN 'salud'
            WHEN {$afiliacionNormExpr} LIKE 'salus%' THEN 'salus'
            WHEN {$afiliacionNormExpr} LIKE 'msp%' OR {$afiliacionNormExpr} LIKE '%ministerio_salud%' THEN 'msp'
            ELSE {$afiliacionNormExpr}
        END";
    }

    private function fallbackEmpresaLabelExpr(string $rawAffiliationExpr, string $afiliacionNormExpr): string
    {
        return "CASE
            WHEN {$afiliacionNormExpr} = '' THEN 'Sin convenio'
            WHEN {$afiliacionNormExpr} REGEXP '(^|_)iess($|_)|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|hijos_dependientes|conyuge|pensionista' THEN 'IESS'
            WHEN {$afiliacionNormExpr} LIKE 'issfa%' THEN 'ISSFA'
            WHEN {$afiliacionNormExpr} LIKE 'isspol%' THEN 'ISSPOL'
            WHEN {$afiliacionNormExpr} LIKE 'salud%' THEN 'SALUD'
            WHEN {$afiliacionNormExpr} LIKE 'salus%' THEN 'SALUS'
            WHEN {$afiliacionNormExpr} LIKE 'msp%' OR {$afiliacionNormExpr} LIKE '%ministerio_salud%' THEN 'MSP'
            ELSE TRIM(COALESCE({$rawAffiliationExpr}, ''))
        END";
    }

    private function resolveEmpresaLabelFromRaw(string $value): string
    {
        $normalized = $this->normalizeKeyValue($value);

        return match (true) {
            $normalized === '', $normalized === 'sin_convenio' => 'Sin convenio',
            preg_match('/(^|_)iess($|_)|seguro_general|seguro_campesino|jubilado|montepio|contribuyente|voluntario|hijos_dependientes|conyuge|pensionista/', $normalized) === 1 => 'IESS',
            str_starts_with($normalized, 'issfa') => 'ISSFA',
            str_starts_with($normalized, 'isspol') => 'ISSPOL',
            str_starts_with($normalized, 'salud') => 'SALUD',
            str_starts_with($normalized, 'salus') => 'SALUS',
            str_starts_with($normalized, 'msp') || str_contains($normalized, 'ministerio_salud') => 'MSP',
            default => $value !== '' ? $value : 'Sin convenio',
        };
    }

    private function normalizeSqlText(string $expr): string
    {
        return "LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE({$expr}, 'Á', 'A'), 'É', 'E'), 'Í', 'I'), 'Ó', 'O'), 'Ú', 'U'), 'Ñ', 'N'), 'á', 'a'), 'é', 'e'), 'í', 'i'), 'ó', 'o'), 'ú', 'u'), 'ñ', 'n'))";
    }

    private function normalizeSqlKey(string $expr): string
    {
        $normalized = $this->normalizeSqlText($expr);

        return "REPLACE(REPLACE(TRIM({$normalized}), ' ', '_'), '-', '_')";
    }

    private function tableExists(string $table): bool
    {
        if (array_key_exists($table, $this->tableExistsCache)) {
            return $this->tableExistsCache[$table];
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.tables
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
             LIMIT 1'
        );
        $stmt->execute([':table_name' => $table]);

        return $this->tableExistsCache[$table] = (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $key = $table . '.' . $column;
        if (array_key_exists($key, $this->columnExistsCache)) {
            return $this->columnExistsCache[$key];
        }

        $stmt = $this->db->prepare(
            'SELECT 1
             FROM information_schema.columns
             WHERE table_schema = DATABASE()
               AND table_name = :table_name
               AND column_name = :column_name
             LIMIT 1'
        );
        $stmt->execute([
            ':table_name' => $table,
            ':column_name' => $column,
        ]);

        return $this->columnExistsCache[$key] = (bool) $stmt->fetchColumn();
    }
}
