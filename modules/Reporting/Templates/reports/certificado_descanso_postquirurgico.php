<?php

$layout = __DIR__ . '/../layouts/report_simple.php';
$data = is_array($data ?? null) ? $data : [];

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');

$certificadoNumero = trim((string) ($data['certificado_numero'] ?? ''));
$fechaEmisionLegible = trim((string) ($data['fecha_emision_legible'] ?? ''));
$fechaCirugiaLegible = trim((string) ($data['fecha_cirugia_legible'] ?? ''));
$procedimiento = trim((string) ($data['procedimiento'] ?? ''));
$observaciones = trim((string) ($data['observaciones'] ?? ''));

$paciente = is_array($data['paciente'] ?? null) ? $data['paciente'] : [];
$doctor = is_array($data['doctor'] ?? null) ? $data['doctor'] : [];
$reposo = is_array($data['reposo'] ?? null) ? $data['reposo'] : [];
$diagnosticos = is_array($data['diagnosticos'] ?? null) ? $data['diagnosticos'] : [];

$diagnosticos = array_values(array_filter(array_map(static function ($item): string {
    return trim((string) $item);
}, $diagnosticos), static fn(string $item): bool => $item !== ''));

if ($diagnosticos === []) {
    $diagnosticos[] = 'Diagnostico postquirurgico.';
}

$reposoDias = (int) ($reposo['dias'] ?? 0);
$reposoDesde = trim((string) ($reposo['desde_legible'] ?? ''));
$reposoHasta = trim((string) ($reposo['hasta_legible'] ?? ''));

$resolveAsset = static function (?string $path): string {
    $path = trim((string) ($path ?? ''));
    if ($path === '') {
        return '';
    }

    if (str_starts_with($path, 'data:image/')) {
        return $path;
    }

    if (function_exists('asset')) {
        return (string) asset($path);
    }

    return $path;
};

$signaturePath = $resolveAsset($doctor['signature_path'] ?? '');
$sealPath = $resolveAsset($doctor['firma'] ?? '');

$styles = <<<'CSS'
@page {
    margin: 16mm;
}

body {
    font-family: Arial, sans-serif;
    color: #1c2434;
    font-size: 11pt;
    line-height: 1.45;
}

.cert-wrap {
    border: 1px solid #c8d2e3;
    padding: 10mm 10mm 8mm 10mm;
}

.cert-top {
    width: 100%;
    border: none;
    border-collapse: collapse;
    margin-bottom: 8mm;
}

.cert-top td {
    border: none;
    vertical-align: top;
    font-size: 9pt;
}

.cert-title {
    text-align: center;
    font-size: 14pt;
    font-weight: 700;
    letter-spacing: 0.4px;
    margin: 0 0 4mm 0;
}

.cert-number {
    text-align: right;
}

.cert-block {
    margin: 0 0 5mm 0;
    text-align: justify;
}

.data-grid {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #c8d2e3;
    margin: 0 0 6mm 0;
}

.data-grid th,
.data-grid td {
    border: 1px solid #c8d2e3;
    padding: 6px 7px;
    font-size: 10pt;
    text-align: left;
}

.data-grid th {
    width: 28%;
    background: #eef3fb;
    font-weight: 700;
}

.diag-title {
    font-weight: 700;
    margin: 0 0 2mm 0;
}

.diag-list {
    margin: 0 0 5mm 5mm;
    padding: 0;
}

.diag-list li {
    margin-bottom: 1.7mm;
}

.reposo-box {
    border: 1px solid #99b0d1;
    background: #f5f8fe;
    padding: 4mm 5mm;
    margin-bottom: 5mm;
    text-align: justify;
}

.firma-wrap {
    margin-top: 12mm;
}

.firma-table {
    width: 100%;
    border: none;
    border-collapse: collapse;
}

.firma-table td {
    border: none;
    text-align: center;
    vertical-align: top;
}

.firma-table img {
    max-height: 22mm;
    max-width: 75mm;
    display: block;
    margin: 0 auto 1mm auto;
}

.firma-line {
    border-top: 1px solid #6e7f9e;
    width: 70mm;
    margin: 1mm auto 2mm auto;
}

.firma-meta {
    font-size: 9pt;
    color: #2d3e5f;
    margin: 0.4mm 0;
}

.footer-note {
    margin-top: 8mm;
    font-size: 8pt;
    color: #5f6f8a;
    text-align: center;
}
CSS;

ob_start();
?>
<div class="cert-wrap">
    <table class="cert-top">
        <tr>
            <td>
                <strong>Fecha de emision:</strong> <?= $esc($fechaEmisionLegible) ?>
            </td>
            <td class="cert-number">
                <strong>Certificado:</strong> <?= $esc($certificadoNumero) ?>
            </td>
        </tr>
    </table>

    <h1 class="cert-title">CERTIFICADO MEDICO DE DESCANSO POSTQUIRURGICO</h1>

    <p class="cert-block">
        Yo, <strong><?= $esc($doctor['nombre'] ?? 'Medico tratante') ?></strong>,
        certifico que el/la paciente <strong><?= $esc($paciente['nombre'] ?? '') ?></strong>
        fue sometido(a) a procedimiento quirurgico de
        <strong><?= $esc($procedimiento !== '' ? $procedimiento : 'atencion quirurgica') ?></strong>
        <?= $fechaCirugiaLegible !== '' ? ('con fecha ' . $esc($fechaCirugiaLegible)) : '' ?>.
    </p>

    <table class="data-grid">
        <tr>
            <th>Paciente</th>
            <td><?= $esc($paciente['nombre'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Historia clinica / ID</th>
            <td><?= $esc($paciente['identificacion'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Edad / Sexo</th>
            <td>
                <?= $esc(($paciente['edad'] ?? '') !== null && $paciente['edad'] !== '' ? (string) $paciente['edad'] . ' años' : 'No registrado') ?>
                /
                <?= $esc($paciente['sexo'] ?? 'No especificado') ?>
            </td>
        </tr>
        <tr>
            <th>Afiliacion</th>
            <td><?= $esc($paciente['afiliacion'] ?? '') ?></td>
        </tr>
    </table>

    <p class="diag-title">Diagnostico(s) relacionado(s):</p>
    <ul class="diag-list">
        <?php foreach ($diagnosticos as $diag): ?>
            <li><?= $esc($diag) ?></li>
        <?php endforeach; ?>
    </ul>

    <div class="reposo-box">
        Se indica descanso medico por <strong><?= $esc($reposoDias) ?> dia(s)</strong>,
        desde <strong><?= $esc($reposoDesde) ?></strong>
        hasta <strong><?= $esc($reposoHasta) ?></strong>, inclusive.
        La reincorporacion a actividades habituales debe realizarse de forma progresiva y bajo control medico.
    </div>

    <p class="cert-block">
        <strong>Observaciones:</strong> <?= $esc($observaciones) ?>
    </p>

    <div class="firma-wrap">
        <table class="firma-table">
            <tr>
                <td>
                    <?php if ($signaturePath !== ''): ?>
                        <img src="<?= $esc($signaturePath) ?>" alt="Firma digital">
                    <?php endif; ?>
                    <?php if ($sealPath !== ''): ?>
                        <img src="<?= $esc($sealPath) ?>" alt="Sello medico">
                    <?php endif; ?>
                    <div class="firma-line"></div>
                    <p class="firma-meta"><strong><?= $esc($doctor['nombre'] ?? 'Medico tratante') ?></strong></p>
                    <?php if (!empty($doctor['especialidad'] ?? '')): ?>
                        <p class="firma-meta"><?= $esc($doctor['especialidad']) ?></p>
                    <?php endif; ?>
                    <?php if (!empty($doctor['cedula'] ?? '')): ?>
                        <p class="firma-meta">Cedula: <?= $esc($doctor['cedula']) ?></p>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>

    <p class="footer-note">
        Documento emitido por MedForge para soporte clinico y administrativo.
    </p>
</div>
<?php
$content = ob_get_clean();
$title = 'Certificado de descanso postquirurgico';

include $layout;
