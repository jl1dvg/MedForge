<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap.php';

function envValue(string $key): ?string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    if ($value === false || $value === null) {
        return null;
    }

    $value = trim((string) $value);
    return $value === '' ? null : $value;
}

function parseArgs(array $argv): array
{
    $options = getopt('', ['start:', 'end:', 'api-url::', 'quiet']);
    $start = trim((string) ($options['start'] ?? ''));
    $end = trim((string) ($options['end'] ?? ''));
    $apiUrl = trim((string) ($options['api-url'] ?? (getenv('MEDFORGE_API_URL') ?: 'https://asistentecive.consulmed.me')));
    $quiet = array_key_exists('quiet', $options);

    if ($start === '' || $end === '') {
        fwrite(STDERR, "Uso: php sync_index_admisiones.php --start YYYY-MM-DD --end YYYY-MM-DD [--api-url URL] [--quiet]\n");
        exit(2);
    }

    return [
        'start' => $start,
        'end' => $end,
        'api_url' => $apiUrl,
        'quiet' => $quiet,
    ];
}

function parseDateValue(string $value): DateTimeImmutable
{
    $date = DateTimeImmutable::createFromFormat('Y-m-d', $value);
    if (!$date || $date->format('Y-m-d') !== $value) {
        throw new InvalidArgumentException(sprintf('Fecha inválida: %s', $value));
    }

    return $date;
}

function normalizeWhitespace(?string $value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    return preg_replace('/\s+/u', ' ', $value) ?: '';
}

function inferSedeFromAgendaDpto(?string $value): string
{
    $value = mb_strtoupper(normalizeWhitespace($value), 'UTF-8');
    if ($value === '') {
        return '';
    }

    $posCeibos = mb_strrpos($value, 'CEIBOS', 0, 'UTF-8');
    $posMatriz = mb_strrpos($value, 'MATRIZ', 0, 'UTF-8');

    if ($posCeibos === false && $posMatriz === false) {
        return '';
    }

    if ($posCeibos !== false && ($posMatriz === false || $posCeibos > $posMatriz)) {
        return 'CEIBOS';
    }

    return 'MATRIZ';
}

function takeNameSegment(array $tokens, int $index): array
{
    if (!isset($tokens[$index])) {
        return ['', $index];
    }

    $upper = static fn (string $value): string => mb_strtoupper($value, 'UTF-8');

    if (isset($tokens[$index + 2])) {
        $first = $upper($tokens[$index]);
        $second = $upper($tokens[$index + 1]);
        if ($first === 'DE' && in_array($second, ['LA', 'LAS', 'LOS'], true)) {
            return [implode(' ', array_slice($tokens, $index, 3)), $index + 3];
        }
    }

    if (isset($tokens[$index + 1])) {
        $first = $upper($tokens[$index]);
        if (in_array($first, ['DE', 'DEL'], true)) {
            return [implode(' ', array_slice($tokens, $index, 2)), $index + 2];
        }
    }

    return [$tokens[$index], $index + 1];
}

function splitSurnames(?string $apellidos): array
{
    $apellidos = normalizeWhitespace($apellidos);
    if ($apellidos === '') {
        return ['lname' => '', 'lname2' => ''];
    }

    $tokens = preg_split('/\s+/u', $apellidos) ?: [];
    [$lname, $nextIndex] = takeNameSegment($tokens, 0);
    $remaining = array_slice($tokens, $nextIndex);
    $lname2 = normalizeWhitespace(implode(' ', $remaining));

    return [
        'lname' => $lname,
        'lname2' => $lname2,
    ];
}

function splitGivenNames(?string $nombres): array
{
    $nombres = normalizeWhitespace($nombres);
    if ($nombres === '') {
        return ['fname' => '', 'mname' => ''];
    }

    $tokens = preg_split('/\s+/u', $nombres) ?: [];
    $fname = $tokens[0] ?? '';
    $mname = normalizeWhitespace(implode(' ', array_slice($tokens, 1)));

    return [
        'fname' => $fname,
        'mname' => $mname,
    ];
}

function normalizeFechaGrupo(?string $value): string
{
    $value = normalizeWhitespace($value);
    if ($value === '') {
        return '';
    }

    $date = DateTimeImmutable::createFromFormat('d-m-Y', $value);
    if (!$date instanceof DateTimeImmutable) {
        return '';
    }

    return $date->format('Y-m-d');
}

function extractJsonObject(string $output): array
{
    $trimmed = trim($output);
    if ($trimmed === '') {
        throw new RuntimeException('El extractor Python no devolvió salida.');
    }

    $decoded = json_decode($trimmed, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $startPos = strrpos($trimmed, "\n{");
    if ($startPos !== false) {
        $candidate = trim(substr($trimmed, $startPos + 1));
        $decoded = json_decode($candidate, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    throw new RuntimeException('No se pudo parsear el JSON devuelto por el extractor Python.');
}

function runPythonExtractor(string $startDate, string $endDate): array
{
    $scriptPath = __DIR__ . '/scrape_index_admisiones.py';
    if (!is_file($scriptPath)) {
        throw new RuntimeException('No se encontró scrape_index_admisiones.py');
    }

    $pythonCandidates = ['python3', 'python'];
    $output = null;
    $exitCode = null;
    $lastError = 'No se encontró un intérprete Python ejecutable.';

    foreach ($pythonCandidates as $python) {
        $command = sprintf(
            '%s %s %s %s --quiet 2>&1',
            escapeshellcmd($python),
            escapeshellarg($scriptPath),
            escapeshellarg($startDate),
            escapeshellarg($endDate)
        );

        exec($command, $commandOutput, $commandExitCode);
        $joinedOutput = implode(PHP_EOL, $commandOutput);

        if ($commandExitCode === 127) {
            $lastError = sprintf('No se encontró el intérprete %s.', $python);
            continue;
        }

        if ($commandExitCode !== 0) {
            throw new RuntimeException(
                sprintf('El extractor Python falló con código %d: %s', $commandExitCode, trim($joinedOutput))
            );
        }

        $output = $joinedOutput;
        $exitCode = $commandExitCode;
        break;
    }

    if ($exitCode !== 0 || $output === null) {
        throw new RuntimeException($lastError);
    }

    $decoded = extractJsonObject($output);
    $rows = $decoded['rows'] ?? null;
    if (!is_array($rows)) {
        throw new RuntimeException('El extractor Python devolvió un JSON sin la clave rows.');
    }

    return $rows;
}

function resolveEventDateTime(array $row): array
{
    $fechaEventoRaw = normalizeWhitespace($row['fecha_evento'] ?? '');
    if ($fechaEventoRaw !== '') {
        try {
            $fechaEvento = new DateTimeImmutable($fechaEventoRaw);
            return [$fechaEvento->format('Y-m-d'), $fechaEvento->format('H:i:s')];
        } catch (Throwable $e) {
            // Seguimos con fallbacks basados en fecha_grupo y hora.
        }
    }

    $fecha = normalizeFechaGrupo($row['fecha_grupo'] ?? '');
    $hora = normalizeWhitespace($row['hora'] ?? '');

    if ($fecha !== '' && $hora !== '') {
        foreach (['Y-m-d H:i:s', 'Y-m-d H:i'] as $format) {
            $dateTime = DateTimeImmutable::createFromFormat($format, $fecha . ' ' . $hora);
            if ($dateTime instanceof DateTimeImmutable) {
                return [$dateTime->format('Y-m-d'), $dateTime->format('H:i:s')];
            }
        }
    }

    return [$fecha, $hora];
}

function buildPayload(array $row): array
{
    $apellidos = normalizeWhitespace($row['apellidos'] ?? '');
    $nombres = normalizeWhitespace($row['nombres'] ?? '');
    $pacienteFull = normalizeWhitespace($row['paciente_full'] ?? ($row['nombre_completo'] ?? ''));
    $surnameParts = $apellidos !== '' ? splitSurnames($apellidos) : [
        'lname' => normalizeWhitespace($row['lname'] ?? ''),
        'lname2' => normalizeWhitespace($row['lname2'] ?? ''),
    ];
    $nameParts = $nombres !== '' ? splitGivenNames($nombres) : [
        'fname' => normalizeWhitespace($row['fname'] ?? ''),
        'mname' => normalizeWhitespace($row['mname'] ?? ''),
    ];
    [$fecha, $hora] = resolveEventDateTime($row);

    $sedeDepartamento = normalizeWhitespace($row['sede_departamento'] ?? '');
    if ($sedeDepartamento === '') {
        $sedeDepartamento = inferSedeFromAgendaDpto($row['agenda_dpto'] ?? '');
    }

    $payload = [
        'hcNumber' => normalizeWhitespace($row['hc_number'] ?? ''),
        'form_id' => normalizeWhitespace($row['pedido_id'] ?? ''),
        'procedimiento_proyectado' => normalizeWhitespace($row['procedimiento'] ?? ''),
        'precio' => normalizeWhitespace($row['precio'] ?? ''),
        'doctor' => normalizeWhitespace($row['doctor_agenda'] ?? ''),
        'cie10' => normalizeWhitespace($row['cie10'] ?? ''),
        'estado_agenda' => normalizeWhitespace($row['estado_agenda'] ?? ''),
        'estado' => normalizeWhitespace($row['estado'] ?? ''),
        'codigo_derivacion' => normalizeWhitespace($row['codigo_derivacion'] ?? ''),
        'num_secuencial_derivacion' => normalizeWhitespace($row['num_secuencial_derivacion'] ?? ''),
        'codigo_examen' => normalizeWhitespace($row['codigo_examen'] ?? ''),
        'prefactura' => normalizeWhitespace($row['prefactura'] ?? ''),
        'fname' => $nameParts['fname'],
        'mname' => $nameParts['mname'],
        'lname' => $surnameParts['lname'],
        'lname2' => $surnameParts['lname2'],
        'email' => normalizeWhitespace($row['email'] ?? ''),
        'fecha_nacimiento' => trim((string) ($row['fecha_nac'] ?? '')),
        'sexo' => normalizeWhitespace($row['sexo'] ?? ''),
        'ciudad' => normalizeWhitespace($row['ciudad'] ?? ''),
        'afiliacion' => normalizeWhitespace($row['afiliacion'] ?? ''),
        'telefono' => normalizeWhitespace($row['telefono'] ?? ''),
        'fecha' => $fecha,
        'hora' => $hora,
        'nombre_completo' => $pacienteFull,
        'sede_departamento' => $sedeDepartamento,
        'referido_prefactura_por' => normalizeWhitespace($row['referido_prefactura_por'] ?? ''),
        'especificar_referido_prefactura' => normalizeWhitespace($row['especificar_referido_prefactura'] ?? ''),
    ];

    return array_filter($payload, static fn ($value): bool => $value !== null && $value !== '');
}

function postBatch(string $apiBaseUrl, array $batch): array
{
    $url = rtrim($apiBaseUrl, '/') . '/api/proyecciones/guardar_index_admisiones.php';
    $payload = json_encode($batch, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        throw new RuntimeException('No se pudo serializar el batch a JSON.');
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 120,
    ]);

    $responseBody = curl_exec($ch);
    $curlError = curl_error($ch);
    $statusCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($responseBody === false) {
        throw new RuntimeException('Error al enviar batch a API: ' . $curlError);
    }

    if (!in_array($statusCode, [200, 207], true)) {
        throw new RuntimeException(sprintf('Error al enviar a API: %d - %s', $statusCode, $responseBody));
    }

    $decoded = json_decode($responseBody, true);
    if (!is_array($decoded)) {
        throw new RuntimeException('La API devolvió una respuesta inválida.');
    }

    if ($statusCode === 207) {
        $errores = $decoded['errores'] ?? [];
        if (is_array($errores) && $errores !== []) {
            $messages = array_map(static function (array $error): string {
                return sprintf(
                    'index=%s message=%s',
                    (string) ($error['index'] ?? '?'),
                    (string) ($error['message'] ?? 'sin mensaje')
                );
            }, $errores);

            throw new RuntimeException('Error parcial al enviar a API (207): ' . implode(', ', $messages));
        }
    }

    return $decoded;
}

function chunkArray(array $items, int $size): array
{
    if ($size <= 0) {
        return [$items];
    }

    return array_chunk($items, $size);
}

try {
    $args = parseArgs($argv);
    $startDate = parseDateValue($args['start']);
    $endDate = parseDateValue($args['end']);
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('El rango es inválido: inicio mayor que fin.');
    }

    $rows = runPythonExtractor($startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

    $totalRows = count($rows);
    $sentRows = 0;
    $skippedRows = 0;
    $apiBatches = 0;
    $payloads = [];

    foreach ($rows as $row) {
        $payload = buildPayload($row);
        if (
            empty($payload['hcNumber'])
            || empty($payload['form_id'])
            || empty($payload['procedimiento_proyectado'])
            || empty($payload['fecha'])
        ) {
            $skippedRows++;
            continue;
        }
        $payloads[] = $payload;
    }

    foreach (chunkArray($payloads, 200) as $batch) {
        if ($batch === []) {
            continue;
        }
        postBatch($args['api_url'], $batch);
        $apiBatches++;
        $sentRows += count($batch);
    }

    $result = [
        'status' => 'success',
        'from' => $startDate->format('Y-m-d'),
        'to' => $endDate->format('Y-m-d'),
        'total_rows' => $totalRows,
        'sent_rows' => $sentRows,
        'skipped_rows' => $skippedRows,
        'api_batches' => $apiBatches,
    ];

    $jsonFlags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    echo $args['quiet']
        ? json_encode($result, $jsonFlags) . PHP_EOL
        : json_encode($result, $jsonFlags | JSON_PRETTY_PRINT) . PHP_EOL;

    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
