<?php
/**
 * Plantilla de cobertura para Ecuasanitas.
 *
 * Variables disponibles (provenientes de SolicitudDataFormatter::enrich):
 * - string $hc_number
 * - array $paciente
 * - array $aseguradora
 * - list<string> $diagnosticoLista
 */

$layout = __DIR__ . '/../layouts/base.php';

$aseguradoraNombre = $aseguradoraNombre ?? ($aseguradora['nombre'] ?? 'Ecuasanitas');
$historiaClinica = $hc_number ?? ($paciente['hc_number'] ?? '');
$fechaNacimiento = $paciente['fecha_nacimiento'] ?? '';
$nombreCompleto = $paciente['full_name']
    ?? trim(implode(' ', array_filter([
        $paciente['fname'] ?? '',
        $paciente['mname'] ?? '',
        $paciente['lname'] ?? '',
        $paciente['lname2'] ?? '',
    ])));

$listaDiagnosticos = [];
if (!empty($diagnosticoLista) && is_array($diagnosticoLista)) {
    $listaDiagnosticos = $diagnosticoLista;
} elseif (!empty($diagnostico) && is_array($diagnostico)) {
    foreach ($diagnostico as $item) {
        if (!is_array($item)) {
            continue;
        }

        $codigo = trim((string) ($item['dx_code'] ?? $item['codigo'] ?? ''));
        $descripcion = trim((string) ($item['descripcion'] ?? $item['nombre'] ?? ''));

        if ($codigo !== '' && $descripcion !== '') {
            $listaDiagnosticos[] = sprintf('%s - %s', $codigo, $descripcion);
            continue;
        }

        if ($descripcion !== '') {
            $listaDiagnosticos[] = $descripcion;
            continue;
        }

        if ($codigo !== '') {
            $listaDiagnosticos[] = $codigo;
        }
    }
}

ob_start();
?>
<div class="ecuasanitas-cover">
    <h1 class="report-title">Cobertura de servicios - Ecuasanitas</h1>

    <table class="summary-table">
        <tbody>
            <tr>
                <th>Entidad aseguradora</th>
                <td><?= htmlspecialchars((string) $aseguradoraNombre, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th>Número de historia clínica</th>
                <td><?= htmlspecialchars((string) $historiaClinica, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th>Nombre del paciente</th>
                <td><?= htmlspecialchars((string) $nombreCompleto, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
            <tr>
                <th>Fecha de nacimiento</th>
                <td><?= htmlspecialchars((string) $fechaNacimiento, ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        </tbody>
    </table>

    <section class="diagnosis-section">
        <h2>Diagnósticos de la solicitud</h2>
        <?php if ($listaDiagnosticos !== []): ?>
            <ol>
                <?php foreach ($listaDiagnosticos as $diagnosticoItem): ?>
                    <li><?= htmlspecialchars((string) $diagnosticoItem, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ol>
        <?php else: ?>
            <p class="empty-placeholder">No se registraron diagnósticos para la solicitud.</p>
        <?php endif; ?>
    </section>
</div>
<style>
    .ecuasanitas-cover {
        font-size: 9pt;
        line-height: 1.4;
        color: #1a1a1a;
    }
    .ecuasanitas-cover .report-title {
        text-align: center;
        font-size: 14pt;
        font-weight: bold;
        margin-bottom: 12px;
    }
    .ecuasanitas-cover .summary-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 16px;
    }
    .ecuasanitas-cover .summary-table th,
    .ecuasanitas-cover .summary-table td {
        border: 0.5pt solid #5a5a5a;
        padding: 6px 8px;
        text-align: left;
        vertical-align: top;
    }
    .ecuasanitas-cover .summary-table th {
        width: 35%;
        background-color: #f3f3f3;
        font-weight: bold;
    }
    .ecuasanitas-cover .diagnosis-section h2 {
        font-size: 11pt;
        margin: 0 0 8px;
        text-transform: uppercase;
    }
    .ecuasanitas-cover .diagnosis-section ol {
        padding-left: 18px;
        margin: 0;
    }
    .ecuasanitas-cover .diagnosis-section li {
        margin-bottom: 4px;
    }
    .ecuasanitas-cover .empty-placeholder {
        font-style: italic;
        color: #666;
    }
</style>
<?php
$content = ob_get_clean();
$header = null;
$title = 'Cobertura Ecuasanitas';

include $layout;
