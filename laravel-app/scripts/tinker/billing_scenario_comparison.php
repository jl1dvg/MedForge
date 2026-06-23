<?php

/**
 * Script temporal de análisis — NO modifica datos ni código de producción.
 *
 * Ejecutar con:
 *   php artisan tinker
 *   >>> require base_path('scripts/tinker/billing_scenario_comparison.php');
 *
 * O en una sola línea desde shell:
 *   php artisan tinker --execute="require base_path('scripts/tinker/billing_scenario_comparison.php');"
 *
 * Ajusta $query abajo con el mismo rango/sede que el Reporte Ejecutivo actual.
 */

use App\Modules\Examenes\Services\ImagenesUiService;

$query = [
    'fecha_inicio' => '2026-05-01', // <-- ajustar
    'fecha_fin'    => '2026-06-16', // <-- ajustar
    'sede'         => '',           // <-- ajustar si aplica
];

$service = app(ImagenesUiService::class);
$ref = new ReflectionClass($service);

$call = function (string $method, ...$args) use ($service, $ref) {
    $m = $ref->getMethod($method);
    $m->setAccessible(true);
    return $m->invokeArgs($service, $args);
};

// ── Escenario A (lógica actual): reusa imagenesDashboard() público tal cual ──
$resultA = $service->imagenesDashboard($query);
$filters = $resultA['filters'];
$rowsA   = $resultA['rows'];           // ya decoradas, facturado=real>public (mergeImagenBillingEvidence real)
$metaA   = $resultA['dashboard']['meta'];

// ── Escenario B (propuesto): mismas filas crudas, source gateada por categoría ──
$solicitudes = $call('fetchImagenesSolicitudPipeline', $filters);

$rowsB = [];
foreach ($rowsA as $row) {
    $categoria = strtolower(trim((string)($row['afiliacion_categoria'] ?? '')));

    if ($categoria === 'publico') {
        $source = trim((string)($row['public_billing_id'] ?? '')) !== ''
            || trim((string)($row['public_fecha_facturacion'] ?? '')) !== ''
            || (int)($row['public_procedimientos_facturados'] ?? 0) > 0
            || (float)($row['public_total_produccion'] ?? 0) > 0
            ? 'public'
            : null;
    } else {
        $source = trim((string)($row['real_billing_id'] ?? '')) !== ''
            || trim((string)($row['real_numero_factura'] ?? '')) !== ''
            || trim((string)($row['real_factura_id'] ?? '')) !== ''
            || trim((string)($row['real_fecha_facturacion'] ?? '')) !== ''
            || trim((string)($row['real_fecha_atencion'] ?? '')) !== ''
            || (int)($row['real_procedimientos_facturados'] ?? 0) > 0
            || (float)($row['real_total_produccion'] ?? 0) > 0
            ? 'real'
            : null;
    }

    $row['facturado']                 = $source !== null ? 1 : 0;
    $row['billing_id']                = $source !== null ? trim((string)($row[$source . '_billing_id'] ?? '')) : null;
    $row['fecha_facturacion']         = $source !== null ? ($row[$source . '_fecha_facturacion'] ?? null) : null;
    $row['fecha_atencion']            = $source === 'real' ? ($row['real_fecha_atencion'] ?? null) : null;
    $row['total_produccion']          = $source !== null ? (float)($row[$source . '_total_produccion'] ?? 0) : 0.0;
    $row['procedimientos_facturados'] = $source !== null ? (int)($row[$source . '_procedimientos_facturados'] ?? 0) : 0;
    $row['numero_factura']            = $source === 'real' ? trim((string)($row['real_numero_factura'] ?? '')) : null;
    $row['factura_id']                = $source === 'real' ? trim((string)($row['real_factura_id'] ?? '')) : null;
    $row['estado_facturacion_raw']    = $source === 'real' ? trim((string)($row['real_estado_facturacion_raw'] ?? '')) : null;
    $row['billing_source']            = $source;

    // Re-derivar estados con la lógica real del servicio, ahora que 'facturado' cambió.
    $row['estado_realizacion'] = $call('resolveImagenRealizationState', $row);
    $row['estado_facturacion'] = $call('resolveImagenBillingState',
        $row['estado_realizacion'],
        (int)($row['facturado'] ?? 0) === 1,
        (string)($row['estado_facturacion_raw'] ?? '')
    );
    $row['examen_realizado'] = $call('isImagenEstadoRealizado', $row['estado_realizacion']);

    $rowsB[] = $row;
}

$dashboardB = $call('buildImagenesDashboardSummary', $rowsB, $filters, $solicitudes);
$metaB = $dashboardB['meta'];

// ── Totales A vs B (vía meta real del servicio, 3 buckets publico/privado/otros) ──
function pct_diff($a, $b) {
    $a = (float)$a; $b = (float)$b;
    $diff = $b - $a;
    $pct = $a != 0.0 ? round(($diff / abs($a)) * 100, 2) : ($b != 0 ? null : 0.0);
    return [round($diff, 2), $pct];
}

echo "\n===== 1-4. TOTALES =====\n";
foreach ([
    'Facturados'             => ['facturados', null],
    'Producción facturada'   => ['produccion_facturada', null],
    'Pendientes facturar'    => ['pendientes_facturar', null],
    'Pendiente estimado'     => ['monto_pendiente_estimado', null],
] as $label => [$key, $_]) {
    $a = $metaA[$key] ?? array_sum(array_column($rowsA, 'facturado')); // fallback si 'facturados' no está en meta (bug conocido)
    $b = $metaB[$key] ?? null;
    if ($key === 'facturados') {
        $a = count(array_filter($rowsA, fn($r) => (int)($r['facturado'] ?? 0) === 1 || ($r['estado_facturacion'] ?? '') === 'FACTURADA'));
        $b = count(array_filter($rowsB, fn($r) => (int)($r['facturado'] ?? 0) === 1 || ($r['estado_facturacion'] ?? '') === 'FACTURADA'));
    }
    [$diff, $pct] = pct_diff($a, $b);
    printf("%-22s A=%-12s B=%-12s diff=%-10s pct=%s%%\n", $label, $a, $b, $diff, $pct ?? 'n/a');
}

// ── 5-6-7. Desglose por categoría FINA (publico/privado/particular/fundacional/otros) ──
function tally(array $rows): array {
    $out = [];
    foreach ($rows as $row) {
        $cat = strtolower(trim((string)($row['afiliacion_categoria'] ?? ''))) ?: 'otros';
        if (!isset($out[$cat])) {
            $out[$cat] = ['facturados' => 0, 'produccion' => 0.0, 'pendientes_facturar' => 0];
        }
        $facturado = (int)($row['facturado'] ?? 0) === 1 || ($row['estado_facturacion'] ?? '') === 'FACTURADA';
        if ($facturado) {
            $out[$cat]['facturados']++;
            $out[$cat]['produccion'] += (float)($row['total_produccion'] ?? 0);
        }
        if (($row['estado_facturacion'] ?? '') === 'PENDIENTE_FACTURAR') {
            $out[$cat]['pendientes_facturar']++;
        }
    }
    return $out;
}

$tallyA = tally($rowsA);
$tallyB = tally($rowsB);
$allCats = array_unique(array_merge(array_keys($tallyA), array_keys($tallyB)));
sort($allCats);

echo "\n===== 5-6-7. DESGLOSE POR CATEGORÍA (publico/privado/particular/fundacional/otros) =====\n";
foreach ($allCats as $cat) {
    $a = $tallyA[$cat] ?? ['facturados' => 0, 'produccion' => 0.0, 'pendientes_facturar' => 0];
    $b = $tallyB[$cat] ?? ['facturados' => 0, 'produccion' => 0.0, 'pendientes_facturar' => 0];
    echo "\n-- categoria: {$cat} --\n";
    [$dF, $pF] = pct_diff($a['facturados'], $b['facturados']);
    [$dP, $pP] = pct_diff($a['produccion'], $b['produccion']);
    [$dPF, $pPF] = pct_diff($a['pendientes_facturar'], $b['pendientes_facturar']);
    printf("  Facturados            A=%-8s B=%-8s diff=%-8s pct=%s%%\n", $a['facturados'], $b['facturados'], $dF, $pF ?? 'n/a');
    printf("  Producción facturada  A=%-8s B=%-8s diff=%-8s pct=%s%%\n", $a['produccion'], $b['produccion'], $dP, $pP ?? 'n/a');
    printf("  Pendientes facturar   A=%-8s B=%-8s diff=%-8s pct=%s%%\n", $a['pendientes_facturar'], $b['pendientes_facturar'], $dPF, $pPF ?? 'n/a');
}

// ── 8-9-10. Evidencia cruzada (no depende de escenario, son las filas crudas) ──
$ambasFuentes = 0;
$publicosConReal = 0;
$noPublicosConPublic = 0;
foreach ($rowsA as $row) {
    $categoria = strtolower(trim((string)($row['afiliacion_categoria'] ?? '')));
    $hasReal = trim((string)($row['real_billing_id'] ?? '')) !== ''
        || trim((string)($row['real_numero_factura'] ?? '')) !== ''
        || trim((string)($row['real_factura_id'] ?? '')) !== ''
        || trim((string)($row['real_fecha_facturacion'] ?? '')) !== ''
        || (float)($row['real_total_produccion'] ?? 0) > 0;
    $hasPublic = trim((string)($row['public_billing_id'] ?? '')) !== ''
        || trim((string)($row['public_fecha_facturacion'] ?? '')) !== ''
        || (float)($row['public_total_produccion'] ?? 0) > 0;

    if ($hasReal && $hasPublic) {
        $ambasFuentes++;
    }
    if ($categoria === 'publico' && $hasReal) {
        $publicosConReal++;
    }
    if ($categoria !== 'publico' && $hasPublic) {
        $noPublicosConPublic++;
    }
}

echo "\n===== 8-9-10. EVIDENCIA CRUZADA =====\n";
echo "Registros con ambas fuentes (real + public) para el mismo form_id: {$ambasFuentes}\n";
echo "Registros PUBLICOS con evidencia en billing_facturacion_real (deberian usar solo billing_main): {$publicosConReal}\n";
echo "Registros NO publicos con evidencia en billing_main (deberian usar solo billing_facturacion_real): {$noPublicosConPublic}\n";

echo "\nTotal filas analizadas: " . count($rowsA) . "\n";
echo "Listo. No se modificó ningún dato ni código de producción.\n";
