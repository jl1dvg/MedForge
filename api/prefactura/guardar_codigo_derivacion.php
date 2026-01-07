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
$archivoNombre = $data['archivo_nombre'] ?? null;
$archivoPath = $data['archivo_path'] ?? null;

/**
 * Guarda un PDF de derivación en storage/derivaciones/{hc}/{form}/
 * y devuelve la ruta relativa guardada en disco.
 */
function guardarArchivoDerivacion(?string $base64, ?string $nombre, ?string $hcNumber, ?string $formId): ?string
{
    if (!$base64 || !$nombre || !$hcNumber || !$formId) {
        return null;
    }

    $safeHc = preg_replace('/[^A-Za-z0-9_-]+/', '_', $hcNumber);
    $safeForm = preg_replace('/[^A-Za-z0-9_-]+/', '_', $formId);
    $safeName = basename($nombre);

    $targetDir = __DIR__ . "/../../storage/derivaciones/{$safeHc}/{$safeForm}";
    if (!is_dir($targetDir) && !mkdir($targetDir, 0775, true) && !is_dir($targetDir)) {
        error_log("❌ No se pudo crear el directorio para derivaciones: {$targetDir}");
        return null;
    }

    $bin = base64_decode($base64);
    if ($bin === false) {
        error_log("❌ No se pudo decodificar base64 del archivo de derivación (form_id {$formId}).");
        return null;
    }

    $targetFile = "{$targetDir}/{$safeName}";
    if (file_put_contents($targetFile, $bin) === false) {
        error_log("❌ No se pudo guardar el PDF de derivación en {$targetFile}");
        return null;
    }

    // Ruta relativa que se guardará en la tabla para luego servir/descargar.
    return "storage/derivaciones/{$safeHc}/{$safeForm}/{$safeName}";
}

// Si no viene archivo_path, intentamos guardar base64.
if (!$archivoPath) {
    $archivoPath = guardarArchivoDerivacion($archivoBase64, $archivoNombre, $hcNumber, $formId);
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
        error_log("✅ Derivación guardada form_id={$formId} hc={$hcNumber} archivo_path={$archivoPath}");
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
