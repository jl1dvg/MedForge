<?php
require_once __DIR__ . '/../../bootstrap.php';

use Controllers\ReporteCirugiasController;

header('Content-Type: application/json');

$form_id = $_GET['form_id'] ?? '';
$hc_number = $_GET['hc_number'] ?? '';

if (empty($form_id) || empty($hc_number)) {
    echo json_encode(['error' => 'Faltan parámetros']);
    exit;
}

$controller = new ReporteCirugiasController($pdo);
$cirugia = $controller->obtenerCirugiaPorId($form_id, $hc_number);

if (!$cirugia) {
    echo json_encode(['error' => 'No se encontró el protocolo']);
    exit;
}

// Parsear y normalizar JSONs
$diagnosticosRaw = json_decode($cirugia->diagnosticos ?? '[]', true);
$procedimientosRaw = json_decode($cirugia->procedimientos ?? '[]', true);

// Normalizar diagnosticos (separar código y detalle)
$diagnosticos = array_map(function ($d) {
    $cie10 = '';
    $detalle = '';

    if (isset($d['idDiagnostico'])) {
        $partes = explode(' - ', $d['idDiagnostico'], 2);
        $cie10 = trim($partes[0]);
        $detalle = $partes[1] ?? '';
    }

    return [
        'cie10' => $cie10,
        'detalle' => $detalle
    ];
}, $diagnosticosRaw);

// Normalizar procedimientos (extraer código y nombre)
$procedimientos = array_map(function ($p) {
    $codigo = '';
    $nombre = '';

    $codigoStr = $p['codigo'] ?? $p['procInterno'] ?? '';

    if ($codigoStr) {
        // Buscar patrón CIRUGIAS - 66984 - NOMBRE...
        if (preg_match('/-\s*(\d+)\s*-\s*(.*)/', $codigoStr, $match)) {
            $codigo = trim($match[1]);
            $nombre = trim($match[2]);
        } else {
            $partes = explode(' - ', $codigoStr, 3);
            $codigo = $partes[1] ?? '';
            $nombre = $partes[2] ?? '';
        }
    }

    return [
        'codigo' => $codigo,
        'nombre' => $nombre
    ];
}, $procedimientosRaw);

// Staff
$staff = [
    'Cirujano principal' => $cirugia->cirujano_1,
    'Instrumentista' => $cirugia->instrumentista,
    'Cirujano 2' => $cirugia->cirujano_2,
    'Circulante' => $cirugia->circulante,
    'Primer ayudante' => $cirugia->primer_ayudante,
    'Segundo ayudante' => $cirugia->segundo_ayudante,
    'Tercer ayudante' => $cirugia->tercer_ayudante,
    'Anestesiólogo' => $cirugia->anestesiologo,
    'Ayudante anestesia' => $cirugia->ayudante_anestesia,
];

// Calcular duración
$duracion = '';
if ($cirugia->hora_inicio && $cirugia->hora_fin) {
    $inicio = strtotime($cirugia->hora_inicio);
    $fin = strtotime($cirugia->hora_fin);
    if ($fin > $inicio) {
        $diff = $fin - $inicio;
        $duracion = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
    }
}

echo json_encode([
    'fecha_inicio' => $cirugia->fecha_inicio,
    'hora_inicio' => $cirugia->hora_inicio,
    'hora_fin' => $cirugia->hora_fin,
    'duracion' => $duracion,
    'dieresis' => $cirugia->dieresis,
    'exposicion' => $cirugia->exposicion,
    'hallazgo' => $cirugia->hallazgo,
    'operatorio' => $cirugia->operatorio,
    'comentario' => $cirugia->complicaciones_operatorio,
    'diagnosticos' => $diagnosticos,
    'procedimientos' => $procedimientos,
    'staff' => $staff
]);