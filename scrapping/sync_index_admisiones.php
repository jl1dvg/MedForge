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

function loadLaravelAppEnvFallback(): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    if (envValue('SIGCENTER_DB_USERNAME') !== null) {
        return;
    }

    $laravelEnvDir = dirname(__DIR__) . '/laravel-app';
    $laravelEnvFile = $laravelEnvDir . '/.env';

    if (!is_file($laravelEnvFile) || !class_exists(\Dotenv\Dotenv::class)) {
        return;
    }

    try {
        $dotenv = \Dotenv\Dotenv::createImmutable($laravelEnvDir);
        if (method_exists($dotenv, 'safeLoad')) {
            $dotenv->safeLoad();
        } else {
            $dotenv->load();
        }
    } catch (Throwable $e) {
        // Si el fallback falla, dejamos que la validación normal reporte el faltante.
    }
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

function createSigcenterPdo(): PDO
{
    loadLaravelAppEnvFallback();

    $host = envValue('SIGCENTER_DB_HOST') ?? '127.0.0.1';
    $port = envValue('SIGCENTER_DB_PORT') ?? '3306';
    $database = envValue('SIGCENTER_DB_DATABASE') ?? 'inmicrocsa';
    $username = envValue('SIGCENTER_DB_USERNAME') ?? '';
    $password = envValue('SIGCENTER_DB_PASSWORD') ?? '';
    $charset = envValue('SIGCENTER_DB_CHARSET') ?? 'utf8mb4';
    $socket = envValue('SIGCENTER_DB_SOCKET') ?? '';

    if ($username === '') {
        throw new RuntimeException('SIGCENTER_DB_USERNAME no configurado.');
    }

    $dsn = $socket !== ''
        ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=%s', $socket, $database, $charset)
        : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $database, $charset);

    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function fetchIndexAdmisionesRows(PDO $pdo, string $startDate, string $endDate): array
{
    $sql = <<<'SQL'
SELECT
    CAST(dsp.id AS CHAR) AS pedido_id,
    DATE_FORMAT(COALESCE(ad.FECHA_INICIO, dsp.fecha_registro), '%d-%m-%Y') AS fecha_grupo,
    COALESCE(ad.FECHA_INICIO, dsp.fecha_registro) AS fecha_evento,

    TRIM(COALESCE(p.numero_historia_clinica, '')) AS hc_number,
    TRIM(COALESCE(oe.codigo_pedido, '')) AS codigo_examen,

    TRIM(COALESCE(p.APELLIDOS, '')) AS apellidos,
    TRIM(COALESCE(p.NOMBRES, '')) AS nombres,
    TRIM(CONCAT_WS(' ', p.APELLIDOS, p.NOMBRES)) AS paciente_full,
    TRIM(COALESCE(p.EMAIL, '')) AS email,
    p.FECHA_NAC AS fecha_nac,
    TRIM(COALESCE(p.SEXO, '')) AS sexo,
    TRIM(COALESCE(ca.nombre, '')) AS ciudad,
    TRIM(COALESCE(af.NOMBRE, '')) AS afiliacion,
    TRIM(COALESCE(p.TELEFONO, '')) AS telefono,

    TRIM(CONCAT_WS(
        '',
        tp.NOMBRE,
        ' - ',
        prd.codigo,
        ' - ',
        proc.NOMBRE,
        CASE WHEN ojo.NOMBRE IS NOT NULL THEN CONCAT(' - ', ojo.NOMBRE) ELSE '' END
    )) AS procedimiento,

    TRIM(CONCAT_WS(' ', trab.APELLIDOS, trab.NOMBRES)) AS doctor_agenda,
    TRIM(COALESCE(dptoAgenda.NOMBRE, '')) AS agenda_dpto,

    CASE
        WHEN UPPER(COALESCE(dptoAgenda.NOMBRE, '')) LIKE '%CEIBOS%' THEN 'CEIBOS'
        WHEN UPPER(COALESCE(dptoAgenda.NOMBRE, '')) LIKE '%MATRIZ%' THEN 'MATRIZ'
        ELSE ''
    END AS sede_departamento,

    TRIM(COALESCE(cie.CIE10, '')) AS cie10,
    TRIM(COALESCE(et.NOMBRE, '')) AS estado_agenda,

    CASE dsp.estado_id
        WHEN 1 THEN 'GENERADAS'
        WHEN 2 THEN 'ATENDIDAS'
        WHEN 3 THEN 'REVISADAS'
        WHEN 4 THEN 'ENVIADAS'
        ELSE CAST(dsp.estado_id AS CHAR)
    END AS estado,

    TRIM(COALESCE(procd.NOMBRE, '')) AS referido_prefactura_por,
    TRIM(COALESCE(ref.nombre, '')) AS especificar_referido_prefactura,
    TRIM(COALESCE(dspac.cod_derivacion, '')) AS codigo_derivacion,
    TRIM(COALESCE(dspac.num_secuencial_derivacion, '')) AS num_secuencial_derivacion,
    TRIM(COALESCE(dm.nroOda, '')) AS prefactura

FROM doc_solicitud_procedimientos dsp
INNER JOIN doc_solicitud_paciente dspac
    ON dspac.id = dsp.doc_solicitud_pacienteId
INNER JOIN paciente p
    ON p.ID_PACIENTE = dspac.pacienteId
INNER JOIN procedimiento proc
    ON proc.ID_PROCEDIMIENTO = dsp.procedimientoId
INNER JOIN tipo_procedimiento tp
    ON tp.ID_TIPO_PROCEDIMIENTO = proc.ID_TIPO_PROCEDIMIENTO
LEFT JOIN ciudad_aux ca
    ON ca.id = p.ciudad_id
LEFT JOIN afiliacion af
    ON af.ID_AFILIACION = dspac.afiliacionId
LEFT JOIN agenda_doctor ad
    ON ad.ID_AGENDA_DOCTOR = dsp.agenda_doctorId
LEFT JOIN paciente_procedimiento pp
    ON pp.ID_PACIENTE_PROCEDIMIENTO = ad.ID_PACIENTE_PROCEDIMIENTO
LEFT JOIN estado_turno et
    ON et.ID_ESTADO_TURNO = pp.ID_ESTADO_TURNO
LEFT JOIN sede_departamento sd
    ON sd.ID_SEDE_DEPARTAMENTO = ad.ID_SEDE_DEPARTAMENTO
LEFT JOIN departamento dptoAgenda
    ON dptoAgenda.ID_DEPARTAMENTO = sd.ID_DEPARTAMENTO
LEFT JOIN trabajador trab
    ON trab.ID_TRABAJADOR = ad.ID_TRABAJADOR
LEFT JOIN procedencia procd
    ON procd.ID_PROCEDENCIA = dspac.procedencia_id
LEFT JOIN referido ref
    ON ref.id = dspac.referido_id
LEFT JOIN doc_motivo dm
    ON dm.id = dspac.motivo_id
LEFT JOIN orden_examen oe
    ON oe.docSolicitudProcedimiento_id = dsp.id
LEFT JOIN ojo
    ON ojo.ID_OJO = dsp.ojo_id
LEFT JOIN productos prd
    ON prd.procedimiento_id = dsp.procedimientoId
LEFT JOIN (
    SELECT
        dr.solicitud_id,
        GROUP_CONCAT(DISTINCT CONCAT_WS(' - ', enf.codigo, enf.nombre, oj.descripcion) SEPARATOR ', ') AS CIE10
    FROM diagnostico_reporte dr
    INNER JOIN enfermedades enf
        ON dr.diagnostico_id = enf.idEnfermedades
    LEFT JOIN ojo oj
        ON dr.ojo_id = oj.ID_OJO
    GROUP BY dr.solicitud_id
) cie
    ON cie.solicitud_id = dsp.id

WHERE DATE(COALESCE(ad.FECHA_INICIO, dsp.fecha_registro))
      BETWEEN :start_date AND :end_date
GROUP BY dsp.id
ORDER BY COALESCE(ad.FECHA_INICIO, dsp.fecha_registro), dsp.id
SQL;

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':start_date' => $startDate,
        ':end_date' => $endDate,
    ]);

    return $stmt->fetchAll();
}

function buildPayload(array $row): array
{
    $surnameParts = splitSurnames($row['apellidos'] ?? '');
    $nameParts = splitGivenNames($row['nombres'] ?? '');
    $fechaEventoRaw = trim((string) ($row['fecha_evento'] ?? ''));
    $fecha = '';
    $hora = '';

    if ($fechaEventoRaw !== '') {
        try {
            $fechaEvento = new DateTimeImmutable($fechaEventoRaw);
            $fecha = $fechaEvento->format('Y-m-d');
            $hora = $fechaEvento->format('H:i:s');
        } catch (Throwable $e) {
            $fecha = '';
        }
    }

    $sedeDepartamento = normalizeWhitespace($row['sede_departamento'] ?? '');
    if ($sedeDepartamento === '') {
        $sedeDepartamento = inferSedeFromAgendaDpto($row['agenda_dpto'] ?? '');
    }

    $payload = [
        'hcNumber' => normalizeWhitespace($row['hc_number'] ?? ''),
        'form_id' => normalizeWhitespace($row['pedido_id'] ?? ''),
        'procedimiento_proyectado' => normalizeWhitespace($row['procedimiento'] ?? ''),
        'doctor' => normalizeWhitespace($row['doctor_agenda'] ?? ''),
        'cie10' => normalizeWhitespace($row['cie10'] ?? ''),
        'estado_agenda' => normalizeWhitespace($row['estado_agenda'] ?? ''),
        'estado' => normalizeWhitespace($row['estado'] ?? ''),
        'codigo_derivacion' => normalizeWhitespace($row['codigo_derivacion'] ?? ''),
        'num_secuencial_derivacion' => normalizeWhitespace($row['num_secuencial_derivacion'] ?? ''),
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
        'nombre_completo' => normalizeWhitespace($row['paciente_full'] ?? ''),
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

    $pdoSigcenter = createSigcenterPdo();
    $rows = fetchIndexAdmisionesRows($pdoSigcenter, $startDate->format('Y-m-d'), $endDate->format('Y-m-d'));

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
