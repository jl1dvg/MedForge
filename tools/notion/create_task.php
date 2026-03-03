#!/usr/bin/env php
<?php

declare(strict_types=1);

function stderr(string $message): void
{
    fwrite(STDERR, $message . PHP_EOL);
}

$options = getopt('', [
    'title:',
    'project::',
    'status::',
    'priority::',
    'module::',
    'date::',
    'notes::',
    'commit::',
    'responsible::',
]);

$title = trim((string)($options['title'] ?? ''));
if ($title === '') {
    stderr('Uso: create_task.php --title "..." [--project "MedForge"] [--status "Hecho"] [--priority "Alta"] [--module "WhatsApp"] [--date "2026-03-03"] [--notes "..."] [--commit "..."] [--responsible "Patricio"]');
    exit(1);
}

$token = getenv('NOTION_TOKEN') ?: '';
$databaseId = getenv('NOTION_DATABASE_ID') ?: '';
if ($token === '' || $databaseId === '') {
    stderr('Faltan variables de entorno NOTION_TOKEN y/o NOTION_DATABASE_ID.');
    exit(1);
}

$project = trim((string)($options['project'] ?? 'MedForge'));
$status = trim((string)($options['status'] ?? 'Pendiente'));
$priority = trim((string)($options['priority'] ?? 'Media'));
$module = trim((string)($options['module'] ?? 'General'));
$date = trim((string)($options['date'] ?? date('Y-m-d')));
$notes = trim((string)($options['notes'] ?? ''));
$commit = trim((string)($options['commit'] ?? ''));
$responsible = trim((string)($options['responsible'] ?? 'Patricio'));

$properties = [
    'Tarea' => [
        'title' => [[
            'text' => ['content' => $title],
        ]],
    ],
    'Proyecto' => ['select' => ['name' => $project]],
    'Estado' => ['status' => ['name' => $status]],
    'Prioridad' => ['select' => ['name' => $priority]],
    'Módulo' => ['rich_text' => [[ 'text' => ['content' => $module] ]]],
    'Fecha' => ['date' => ['start' => $date]],
    'Notas técnicas' => ['rich_text' => $notes !== '' ? [[ 'text' => ['content' => $notes] ]] : []],
    'Commit' => ['rich_text' => $commit !== '' ? [[ 'text' => ['content' => $commit] ]] : []],
    'Responsable' => ['select' => ['name' => $responsible]],
];

$payload = [
    'parent' => ['database_id' => $databaseId],
    'properties' => $properties,
];

$ch = curl_init('https://api.notion.com/v1/pages');
if ($ch === false) {
    stderr('No se pudo inicializar cURL.');
    exit(1);
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $token,
        'Notion-Version: 2022-06-28',
        'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 30,
]);

$response = curl_exec($ch);
$statusCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($response === false || $error !== '') {
    stderr('Error de red al crear tarea en Notion: ' . $error);
    exit(1);
}

$data = json_decode($response, true);
if ($statusCode < 200 || $statusCode >= 300) {
    $message = is_array($data) ? (string)($data['message'] ?? 'Error desconocido') : 'Error desconocido';
    stderr('Notion API error (' . $statusCode . '): ' . $message);
    if (is_array($data)) {
        stderr(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }
    exit(1);
}

$url = is_array($data) ? (string)($data['url'] ?? '') : '';
$pageId = is_array($data) ? (string)($data['id'] ?? '') : '';

echo "NOTION_TASK_CREATED\n";
echo 'id=' . $pageId . "\n";
echo 'url=' . $url . "\n";
