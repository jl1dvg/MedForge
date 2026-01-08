<?php
require_once '../../bootstrap.php'; // conexión y utilidades
use Controllers\DerivacionController;

$data = json_decode(file_get_contents("php://input"), true);
$formId = $data['form_id'] ?? null;
$codigo = $data['codigo_derivacion'] ?? null;
$hcNumber = $data['hc_number'] ?? null;
$fechaRegistro = $data['fecha_registro'] ?? null;
$fechaVigencia = $data['fecha_vigencia'] ?? null;
$referido = $data['referido'] ?? null;
$diagnostico = $data['diagnostico'] ?? null;
$sedeNombre = $data['sede'] ?? null;
$parentescoNombre = $data['parentesco'] ?? null;
$archivoBase64 = $data['archivo_base64'] ?? null;
$archivoPath = $data['archivo_path'] ?? null;

/**
 * Guarda un PDF de derivación en storage/derivaciones/{hc}/{codigo}/
 * y devuelve la ruta relativa guardada en disco.
 */
function normalizarSegmento(?string $valor, string $fallback): string
{
    $segmento = trim((string) $valor);
    $segmento = preg_replace('/[^A-Za-z0-9_-]+/', '_', $segmento);
    return $segmento !== '' ? $segmento : $fallback;
}

function normalizarCodigoDerivacion(?string $codigo): string
{
    $codigoRaw = trim((string) $codigo);
    if ($codigoRaw !== '') {
        $codigoRaw = explode('SECUENCIAL', $codigoRaw)[0];
    }
    $codigoRaw = trim($codigoRaw);
    return normalizarSegmento($codigoRaw, 'SIN_CODIGO');
}

function construirRutaDerivacion(?string $hcNumber, ?string $codigoDerivacion): ?string
{
    if (!$hcNumber || !$codigoDerivacion) {
        return null;
    }

    $safeHc = normalizarSegmento($hcNumber, 'SIN_HC');
    $safeCodigo = normalizarCodigoDerivacion($codigoDerivacion);
    $filename = sprintf('derivacion_%s_%s.pdf', $safeHc, $safeCodigo);

    return sprintf('storage/derivaciones/%s/%s/%s', $safeHc, $safeCodigo, $filename);
}

function guardarArchivoDerivacion(?string $base64, ?string $hcNumber, ?string $codigoDerivacion): ?string
{
    if (!$base64 || !$hcNumber || !$codigoDerivacion) {
        return null;
    }

    $safeHc = normalizarSegmento($hcNumber, 'SIN_HC');
    $safeCodigo = normalizarCodigoDerivacion($codigoDerivacion);
    $filename = sprintf('derivacion_%s_%s.pdf', $safeHc, $safeCodigo);

    $targetDir = __DIR__ . "/../../storage/derivaciones/{$safeHc}/{$safeCodigo}";
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        error_log("❌ No se pudo crear el directorio para derivaciones: {$targetDir}");
        return null;
    }

    $bin = base64_decode($base64);
    if ($bin === false) {
        error_log("❌ No se pudo decodificar base64 del archivo de derivación (hc {$hcNumber}).");
        return null;
    }

    $targetFile = "{$targetDir}/{$filename}";
    if (file_put_contents($targetFile, $bin) === false) {
        error_log("❌ No se pudo guardar el PDF de derivación en {$targetFile}");
        return null;
    }

    // Ruta relativa que se guardará en la tabla para luego servir/descargar.
    return "storage/derivaciones/{$safeHc}/{$safeCodigo}/{$filename}";
}

function buscarArchivoEnBD(PDO $pdo, ?string $hcNumber, ?string $codigoDerivacion): ?string
{
    if (!$hcNumber || !$codigoDerivacion) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT f.archivo_derivacion_path
         FROM derivaciones_forms f
         INNER JOIN derivaciones_referral_forms rf ON rf.form_id = f.id
         INNER JOIN derivaciones_referrals r ON r.id = rf.referral_id
         WHERE f.hc_number = :hc
           AND r.referral_code = :codigo
           AND f.archivo_derivacion_path IS NOT NULL
           AND f.archivo_derivacion_path <> ''
         ORDER BY f.id DESC
         LIMIT 1"
    );
    $stmt->execute([
        ':hc' => $hcNumber,
        ':codigo' => $codigoDerivacion,
    ]);
    $path = $stmt->fetchColumn();
    if ($path) {
        return $path;
    }

    $stmtLegacy = $pdo->prepare(
        "SELECT archivo_derivacion_path
         FROM derivaciones_form_id
         WHERE hc_number = :hc
           AND cod_derivacion = :codigo
           AND archivo_derivacion_path IS NOT NULL
           AND archivo_derivacion_path <> ''
         ORDER BY id DESC
         LIMIT 1"
    );
    $stmtLegacy->execute([
        ':hc' => $hcNumber,
        ':codigo' => $codigoDerivacion,
    ]);

    $legacyPath = $stmtLegacy->fetchColumn();

    return $legacyPath ?: null;
}

function archivoExisteEnDisco(string $rutaRelativa): bool
{
    $rutaNormalizada = ltrim($rutaRelativa, '/');
    $rutaAbsoluta = BASE_PATH . '/' . $rutaNormalizada;
    return is_file($rutaAbsoluta);
}

$archivoPath = is_string($archivoPath) ? trim($archivoPath) : $archivoPath;

if (!$archivoPath) {
    $archivoDb = buscarArchivoEnBD($pdo, $hcNumber, $codigo);
    if ($archivoDb && archivoExisteEnDisco($archivoDb)) {
        $archivoPath = $archivoDb;
        error_log("♻️ Reutilizando archivo derivación desde BD: {$archivoPath}");
    }
}

if (!$archivoPath) {
    $rutaEsperada = construirRutaDerivacion($hcNumber, $codigo);
    if ($rutaEsperada && archivoExisteEnDisco($rutaEsperada)) {
        $archivoPath = $rutaEsperada;
        error_log("♻️ Archivo derivación encontrado en disco sin ruta en BD: {$archivoPath}");
    }
}

// Si no viene archivo_path, intentamos guardar base64.
if (!$archivoPath) {
    $archivoPath = guardarArchivoDerivacion($archivoBase64, $hcNumber, $codigo);
    if ($archivoPath) {
        error_log("✅ PDF derivación guardado desde base64: {$archivoPath}");
    }
}

if ($formId && $codigo) {
    $controller = new DerivacionController($pdo);
    $resultado = $controller->guardarDerivacion(
        $codigo,
        $formId,
        $hcNumber,
        $fechaRegistro,
        $fechaVigencia,
        $referido,
        $diagnostico,
        $sedeNombre,
        $parentescoNombre,
        $archivoPath
    );
    if ($resultado) {
        error_log("✅ Derivación guardada/actualizada form_id={$formId} hc={$hcNumber} codigo={$codigo} archivo_path={$archivoPath}");
        echo json_encode([
            "success" => true,
            "archivo_path" => $archivoPath,
            "form_id" => $formId,
            "hc_number" => $hcNumber,
            "derivacion_id" => $resultado
        ]);
    } else {
        error_log("❌ Falló el guardado de derivación: $codigo - $formId");
        echo json_encode(["success" => false]);
    }
} else {
    error_log("❌ Datos incompletos para guardar derivación. form_id={$formId} codigo={$codigo} hc={$hcNumber}");
    echo json_encode([
        "success" => false,
        "message" => "Datos incompletos",
        "form_id" => $formId,
        "hc_number" => $hcNumber,
        "archivo_path" => $archivoPath
    ]);
}
