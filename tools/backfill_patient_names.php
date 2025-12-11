#!/usr/bin/env php
<?php

require_once __DIR__ . '/../bootstrap.php';

$pdo = $GLOBALS['pdo'] ?? null;

if (!$pdo instanceof PDO) {
    fwrite(STDERR, "No se pudo establecer conexión a la base de datos.\n");
    exit(1);
}

$options = getopt('', ['limit::', 'dry-run', 'help']);

if (isset($options['help'])) {
    echo "Uso: php tools/backfill_patient_names.php [--limit=N] [--dry-run]\n";
    exit(0);
}

$limit = null;
if (isset($options['limit']) && $options['limit'] !== '') {
    if (!ctype_digit((string) $options['limit'])) {
        fwrite(STDERR, "--limit debe ser un entero.\n");
        exit(1);
    }
    $limit = (int) $options['limit'];
}

$dryRun = array_key_exists('dry-run', $options);

$sql = 'SELECT hc_number, first_name, middle_name, last_name, second_last_name,
               fname, mname, lname, lname2,
               TRIM(CONCAT_WS(" ", fname, mname, lname, lname2)) AS legacy_full_name
        FROM patient_data';
if ($limit !== null) {
    $sql .= ' LIMIT ' . (int) $limit;
}

$stmt = $pdo->query($sql);
$processed = $updated = $ambiguous = 0;

if (!$stmt) {
    fwrite(STDERR, "No se pudo leer patient_data.\n");
    exit(1);
}

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $processed++;
    $names = [
        'first_name' => trim((string) ($row['first_name'] ?? '')),
        'middle_name' => trim((string) ($row['middle_name'] ?? '')),
        'last_name' => trim((string) ($row['last_name'] ?? '')),
        'second_last_name' => trim((string) ($row['second_last_name'] ?? '')),
    ];

    if ($names['first_name'] !== '' || $names['last_name'] !== '') {
        continue;
    }

    $source = trim((string) ($row['legacy_full_name'] ?? ''));
    if ($source === '') {
        $ambiguous++;
        fwrite(STDERR, "[AMBIGUO] HC {$row['hc_number']} sin nombre disponible.\n");
        continue;
    }

    $parts = preg_split('/\s+/', $source) ?: [];
    $parts = array_values(array_filter($parts, static fn($p) => $p !== ''));

    $parsed = [
        'first_name' => '',
        'middle_name' => '',
        'last_name' => '',
        'second_last_name' => '',
    ];

    if (count($parts) >= 4) {
        $parsed['first_name'] = array_shift($parts);
        $parsed['middle_name'] = array_shift($parts);
        $parsed['second_last_name'] = array_pop($parts);
        $parsed['last_name'] = array_pop($parts);
    } elseif (count($parts) === 3) {
        [$parsed['first_name'], $parsed['middle_name'], $parsed['last_name']] = $parts;
    } elseif (count($parts) === 2) {
        [$parsed['first_name'], $parsed['last_name']] = $parts;
    } elseif (count($parts) === 1) {
        $parsed['first_name'] = $parts[0];
        $ambiguous++;
        fwrite(STDERR, "[AMBIGUO] HC {$row['hc_number']} nombre incompleto '{$source}'.\n");
    }

    foreach ($parsed as $key => $value) {
        $parsed[$key] = mb_substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 100);
    }

    if ($dryRun) {
        $updated++;
        continue;
    }

    $update = $pdo->prepare(
        'UPDATE patient_data SET first_name = :first, middle_name = :middle, last_name = :last, second_last_name = :second_last,
            fname = COALESCE(NULLIF(fname, ""), :first),
            mname = COALESCE(NULLIF(mname, ""), :middle),
            lname = COALESCE(NULLIF(lname, ""), :last),
            lname2 = COALESCE(NULLIF(lname2, ""), :second_last)
         WHERE hc_number = :hc'
    );

    $update->execute([
        ':first' => $parsed['first_name'],
        ':middle' => $parsed['middle_name'],
        ':last' => $parsed['last_name'],
        ':second_last' => $parsed['second_last_name'],
        ':hc' => $row['hc_number'],
    ]);

    $updated++;
}

echo "Pacientes procesados: {$processed}\n";
if ($dryRun) {
    echo "Actualizaciones simuladas: {$updated}\n";
} else {
    echo "Registros actualizados: {$updated}\n";
}

echo "Casos ambiguos: {$ambiguous}\n";
