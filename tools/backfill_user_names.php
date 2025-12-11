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
    echo "Uso: php tools/backfill_user_names.php [--limit=N] [--dry-run]\n";
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

$sql = 'SELECT id, username, nombre, first_name, middle_name, last_name, second_last_name
        FROM users';
if ($limit !== null) {
    $sql .= ' LIMIT ' . (int) $limit;
}

$stmt = $pdo->query($sql);
if (!$stmt) {
    fwrite(STDERR, "No se pudo leer users.\n");
    exit(1);
}

$processed = $updated = $ambiguous = 0;

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

    $source = trim((string) ($row['nombre'] ?? ''));
    if ($source === '') {
        $ambiguous++;
        fwrite(STDERR, "[AMBIGUO] Usuario {$row['id']} ({$row['username']}) sin nombre disponible.\n");
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
        fwrite(STDERR, "[AMBIGUO] Usuario {$row['id']} ({$row['username']}) nombre incompleto '{$source}'.\n");
    }

    foreach ($parsed as $key => $value) {
        $parsed[$key] = mb_substr(trim(preg_replace('/\s+/', ' ', $value)), 0, 100);
    }

    if ($dryRun) {
        $updated++;
        continue;
    }

    $update = $pdo->prepare(
        'UPDATE users
         SET first_name = :first, middle_name = :middle, last_name = :last, second_last_name = :second_last
         WHERE id = :id'
    );

    $update->execute([
        ':first' => $parsed['first_name'],
        ':middle' => $parsed['middle_name'],
        ':last' => $parsed['last_name'],
        ':second_last' => $parsed['second_last_name'],
        ':id' => $row['id'],
    ]);

    $updated++;
}

echo "Usuarios procesados: {$processed}\n";
if ($dryRun) {
    echo "Actualizaciones simuladas: {$updated}\n";
} else {
    echo "Registros actualizados: {$updated}\n";
}

echo "Casos ambiguos: {$ambiguous}\n";
