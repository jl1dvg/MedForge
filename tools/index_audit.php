#!/usr/bin/env php
<?php

declare(strict_types=1);

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function connect(): PDO
{
    try {
        /** @var PDO $pdo */
        $pdo = require __DIR__ . '/../config/database.php';
        return $pdo;
    } catch (Throwable $e) {
        fwrite(STDERR, "[error] No fue posible conectar a la base de datos: {$e->getMessage()}" . PHP_EOL);
        fwrite(STDERR, "        Verifica las variables de entorno DB_HOST, DB_NAME, DB_USER y DB_PASSWORD." . PHP_EOL);
        exit(1);
    }
}

function fetchIndexes(PDO $pdo, string $table): array
{
    $sql = <<<SQL
        SELECT
            index_name,
            (MIN(non_unique) = 0) AS is_unique,
            GROUP_CONCAT(column_name ORDER BY seq_in_index SEPARATOR ', ') AS columns,
            index_type
        FROM information_schema.statistics
        WHERE table_schema = DATABASE()
          AND table_name = :table
        GROUP BY index_name, index_type
        ORDER BY index_name
    SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':table' => $table]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildCoverageMap(array $indexes): array
{
    $coverage = [];
    foreach ($indexes as $index) {
        $columns = array_map('trim', explode(',', (string) ($index['columns'] ?? '')));
        foreach ($columns as $position => $column) {
            if ($column === '') {
                continue;
            }
            $coverage[$column][] = [
                'index' => $index['index_name'],
                'position' => $position + 1,
                'unique' => (bool) $index['is_unique'],
            ];
        }
    }
    ksort($coverage);
    return $coverage;
}

function printTable(string $title, array $headers, array $rows): void
{
    echo PHP_EOL . $title . PHP_EOL;
    $widths = [];
    foreach ($headers as $header) {
        $widths[$header] = strlen($header);
    }
    foreach ($rows as $row) {
        foreach ($headers as $header) {
            $widths[$header] = max($widths[$header], strlen((string) ($row[$header] ?? '')));
        }
    }
    $line = [];
    foreach ($headers as $header) {
        $line[] = str_pad($header, $widths[$header]);
    }
    echo implode(' | ', $line) . PHP_EOL;
    $line = [];
    foreach ($headers as $header) {
        $line[] = str_repeat('-', $widths[$header]);
    }
    echo implode('-+-', $line) . PHP_EOL;
    foreach ($rows as $row) {
        $line = [];
        foreach ($headers as $header) {
            $line[] = str_pad((string) ($row[$header] ?? ''), $widths[$header]);
        }
        echo implode(' | ', $line) . PHP_EOL;
    }
}

function main(): void
{
    $tables = [
        'procedimiento_proyectado' => ['form_id', 'hc_number', 'fecha', 'estado_agenda'],
        'protocolo_data' => ['form_id', 'fecha_inicio', 'status'],
        'billing_main' => ['form_id'],
    ];

    $pdo = connect();

    foreach ($tables as $table => $columns) {
        $indexes = fetchIndexes($pdo, $table);
        if (!$indexes) {
            echo PHP_EOL . "Tabla {$table}: (sin índices registrados o sin privilegios)" . PHP_EOL;
            continue;
        }

        $rows = array_map(static function (array $index) {
            return [
                'index_name' => $index['index_name'],
                'unique' => ((bool) $index['is_unique']) ? 'sí' : 'no',
                'columns' => $index['columns'],
                'index_type' => $index['index_type'],
            ];
        }, $indexes);

        printTable("Tabla {$table}", ['index_name', 'unique', 'columns', 'index_type'], $rows);

        $coverage = buildCoverageMap($indexes);
        echo PHP_EOL . "Cobertura por columna:" . PHP_EOL;
        foreach ($columns as $column) {
            $entries = $coverage[$column] ?? [];
            if (!$entries) {
                echo sprintf(" - %s: sin índice asociado" . PHP_EOL, $column);
                continue;
            }
            foreach ($entries as $entry) {
                $label = $entry['unique'] ? 'UNIQUE' : 'INDEX';
                echo sprintf(
                    " - %s: %s (%s, posición %d)" . PHP_EOL,
                    $column,
                    $entry['index'],
                    $label,
                    $entry['position']
                );
            }
        }
    }
}

main();
