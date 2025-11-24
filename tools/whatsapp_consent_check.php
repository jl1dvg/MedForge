#!/usr/bin/env php
<?php

declare(strict_types=1);

use Modules\WhatsApp\Config\WhatsAppSettings;
use Modules\Autoresponder\Repositories\AutoresponderFlowRepository;
use Modules\WhatsApp\Repositories\ContactConsentRepository;
use Modules\WhatsApp\Support\AutoresponderFlow;

$autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($autoload)) {
    require_once $autoload;
}

function usage(string $script): void
{
    $script = basename($script);
    echo <<<TXT
Uso: php {$script} <wa_number> [cedula]

Pasos automatizados:
  1. Busca el número en la tabla whatsapp_contact_consent.
  2. Comprueba que consent_status sea "accepted" y que la cédula almacenada coincida con la ingresada.
  3. Si está en estado "pending", revisa que el escenario "Captura de cédula" siga incluyendo la acción store_consent y que exista el índice UNIQUE (wa_number, cedula).
TXT;
    echo PHP_EOL;
}

function connect(): PDO
{
    try {
        /** @var PDO $pdo */
        $pdo = require __DIR__ . '/../config/database.php';
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        return $pdo;
    } catch (Throwable $exception) {
        fwrite(STDERR, "[error] No fue posible conectar a la base de datos: {$exception->getMessage()}" . PHP_EOL);
        fwrite(STDERR, "        Verifica DB_HOST, DB_NAME, DB_USER y DB_PASSWORD." . PHP_EOL);
        exit(1);
    }
}

function fetchConsentRecord(ContactConsentRepository $repository, string $number): ?array
{
    $record = $repository->findByNumber($number);
    if ($record === null) {
        return null;
    }

    if (!isset($record['identifier']) && isset($record['cedula'])) {
        $record['identifier'] = $record['cedula'];
    }

    return $record;
}

function checkScenarioStoreConsent(PDO $pdo): array
{
    $settings = new WhatsAppSettings($pdo);
    $brand = $settings->getBrandName();

    $flowRepository = new AutoresponderFlowRepository($pdo);
    $overrides = $flowRepository->load();

    $resolved = AutoresponderFlow::resolve($brand, $overrides);
    $scenarios = $resolved['scenarios'] ?? [];

    foreach ($scenarios as $scenario) {
        if (($scenario['id'] ?? '') !== 'captura_cedula') {
            continue;
        }

        $actions = $scenario['actions'] ?? [];
        foreach ($actions as $action) {
            if (($action['type'] ?? '') === 'store_consent') {
                return ['ok' => true, 'scenario' => $scenario];
            }
        }

        return ['ok' => false, 'scenario' => $scenario];
    }

    return ['ok' => false, 'scenario' => null];
}

function checkDuplicateKeyIndex(PDO $pdo): bool
{
    try {
        $stmt = $pdo->query("SHOW INDEX FROM whatsapp_contact_consent WHERE Key_name = 'uniq_contact_identifier'");
        if ($stmt === false) {
            return false;
        }

        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[(int) $row['Seq_in_index']] = $row['Column_name'];
        }

        if (empty($columns)) {
            return false;
        }

        ksort($columns);
        $expected = ['wa_number', 'cedula'];

        return array_values($columns) === $expected;
    } catch (PDOException $exception) {
        fwrite(STDERR, "[warn] No fue posible verificar el índice UNIQUE: {$exception->getMessage()}" . PHP_EOL);

        return false;
    }
}

$script = array_shift($argv);
$flags = array_filter($argv, static fn($arg) => in_array($arg, ['-h', '--help'], true));
if (!empty($flags)) {
    usage($script ?? 'whatsapp_consent_check.php');
    exit(0);
}

$waNumber = $argv[0] ?? null;
if (!is_string($waNumber) || trim($waNumber) === '') {
    usage($script ?? 'whatsapp_consent_check.php');
    exit(1);
}
$waNumber = trim($waNumber);
$identifierInput = isset($argv[1]) ? trim((string) $argv[1]) : null;

$pdo = connect();
$repository = new ContactConsentRepository($pdo);
$record = fetchConsentRecord($repository, $waNumber);

if ($record === null) {
    echo "[x] No existe un consentimiento registrado para {$waNumber}." . PHP_EOL;
    exit(2);
}

echo "[✓] Registro encontrado para {$waNumber}." . PHP_EOL;
$status = (string) ($record['consent_status'] ?? 'pending');
$storedIdentifier = trim((string) ($record['identifier'] ?? ''));
if ($storedIdentifier === '') {
    $storedIdentifier = trim((string) ($record['cedula'] ?? ''));
}

if ($status === 'accepted') {
    if ($identifierInput !== null && $storedIdentifier !== '' && $storedIdentifier !== $identifierInput) {
        echo "[!] El consentimiento está aceptado pero la cédula almacenada ({$storedIdentifier}) no coincide con la ingresada ({$identifierInput})." . PHP_EOL;
        exit(3);
    }

    echo "[✓] consent_status=accepted y la cédula coincide." . PHP_EOL;
    if ($storedIdentifier !== '') {
        echo "     Cédula registrada: {$storedIdentifier}" . PHP_EOL;
    }
    exit(0);
}

if ($status === 'declined') {
    echo "[!] El consentimiento está marcado como declined." . PHP_EOL;
    if ($storedIdentifier !== '') {
        echo "     Última cédula registrada: {$storedIdentifier}" . PHP_EOL;
    }
    exit(4);
}

echo "[!] El consentimiento sigue en estado pending." . PHP_EOL;
if ($storedIdentifier !== '') {
    echo "     Última cédula registrada: {$storedIdentifier}" . PHP_EOL;
}

$scenarioCheck = checkScenarioStoreConsent($pdo);
if ($scenarioCheck['ok']) {
    echo "[✓] La acción store_consent está presente en el escenario 'Captura de cédula'." . PHP_EOL;
} else {
    echo "[x] No se encontró la acción store_consent en el escenario 'Captura de cédula'." . PHP_EOL;
}

if (checkDuplicateKeyIndex($pdo)) {
    echo "[✓] El índice UNIQUE (wa_number, cedula) está configurado correctamente." . PHP_EOL;
} else {
    echo "[x] Verifica el índice UNIQUE (wa_number, cedula) en la tabla whatsapp_contact_consent." . PHP_EOL;
}

exit(5);
