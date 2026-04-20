<?php

$layout = __DIR__ . '/../layouts/report_simple.php';
$data = is_array($data ?? null) ? $data : [];

$esc = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$lineBreaks = static fn($value): string => nl2br($esc($value), false);
$fill = static fn($value, string $fallback = '________________'): string => trim((string) $value) !== '' ? $esc($value) : $fallback;

$certificadoNumero = trim((string) ($data['certificado_numero'] ?? ''));
$ciudadEmision = trim((string) ($data['ciudad_emision'] ?? ''));
$fechaEmisionLegible = trim((string) ($data['fecha_emision_legible'] ?? ''));
$procedimiento = trim((string) ($data['procedimiento'] ?? ''));
$tratamiento = trim((string) ($data['tratamiento'] ?? ''));
$tipoContingencia = trim((string) ($data['tipo_contingencia'] ?? ''));
$diagnosticoIngreso = trim((string) ($data['diagnostico_ingreso'] ?? ''));
$diagnosticoEgreso = trim((string) ($data['diagnostico_egreso'] ?? ''));
$fechaCirugiaLegible = trim((string) ($data['fecha_cirugia_legible'] ?? ''));
$fechaEgresoLegible = trim((string) ($data['fecha_egreso_legible'] ?? ''));

$paciente = is_array($data['paciente'] ?? null) ? $data['paciente'] : [];
$doctor = is_array($data['doctor'] ?? null) ? $data['doctor'] : [];
$reposo = is_array($data['reposo'] ?? null) ? $data['reposo'] : [];

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
    margin: 12mm 13mm;
}

body {
    font-family: Arial, sans-serif;
    color: #111;
    font-size: 10pt;
    line-height: 1.35;
}

.sheet {
    border: 1px solid #222;
    padding: 8mm 8mm 6mm 8mm;
}

.header-table,
.grid,
.signature-table {
    width: 100%;
    border-collapse: collapse;
}

.header-table td,
.signature-table td {
    border: none;
    vertical-align: top;
}

.header-right {
    text-align: right;
    font-size: 9pt;
}

.title {
    margin: 3mm 0 5mm 0;
    text-align: center;
    font-size: 16pt;
    font-weight: 700;
    letter-spacing: 0.3px;
}

.intro {
    margin: 0 0 4mm 0;
    text-align: justify;
}

.grid {
    margin: 0 0 4mm 0;
}

.grid td,
.grid th {
    border: 1px solid #222;
    padding: 5px 6px;
    vertical-align: top;
    font-size: 9.4pt;
}

.grid th {
    width: 26%;
    text-align: left;
    background: #f4f4f4;
    font-weight: 700;
}

.block-label {
    font-weight: 700;
}

.reposo-box {
    border: 1px solid #222;
    padding: 4mm;
    margin: 0 0 6mm 0;
    text-align: justify;
}

.signature-wrap {
    margin-top: 8mm;
}

.signature-table img {
    display: block;
    margin: 0 auto 2mm auto;
    max-height: 22mm;
    max-width: 70mm;
}

.signature-line {
    width: 74mm;
    border-top: 1px solid #222;
    margin: 2mm auto 2mm auto;
}

.signature-meta {
    margin: 0.8mm 0;
    text-align: center;
    font-size: 9pt;
}

.footer {
    margin-top: 6mm;
    text-align: center;
    font-size: 8pt;
}
CSS;

ob_start();
?>
<div class="sheet">
    <table class="header-table">
        <tr>
            <td></td>
            <td class="header-right">
                <?php if ($ciudadEmision !== ''): ?>
                    <div><?= $esc($ciudadEmision) ?>, <?= $esc($fechaEmisionLegible) ?></div>
                <?php else: ?>
                    <div><?= $esc($fechaEmisionLegible) ?></div>
                <?php endif; ?>
                <div><strong>Certificado:</strong> <?= $esc($certificadoNumero) ?></div>
            </td>
        </tr>
    </table>

    <h1 class="title">CERTIFICADO MEDICO</h1>

    <p class="intro">
        Yo, <strong><?= $fill($doctor['nombre'] ?? 'Medico tratante') ?></strong>, certifico que el/la paciente antes identificado(a)
        fue atendido(a) en esta institucion y requiere el presente certificado medico postquirurgico.
    </p>

    <table class="grid">
        <tr>
            <th>Historia clinica</th>
            <td><?= $fill($paciente['historia_clinica'] ?? '') ?></td>
            <th>Cedula</th>
            <td><?= $fill($paciente['identificacion'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Paciente</th>
            <td colspan="3"><?= $fill($paciente['nombre'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Domicilio</th>
            <td colspan="3"><?= $fill($paciente['domicilio'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Telefono</th>
            <td><?= $fill($paciente['telefono'] ?? '') ?></td>
            <th>Sexo / Edad</th>
            <td><?= $fill(trim((string) (($paciente['sexo'] ?? '') . (($paciente['edad'] ?? null) !== null && $paciente['edad'] !== '' ? ' / ' . $paciente['edad'] . ' años' : '')))) ?></td>
        </tr>
        <tr>
            <th>Institucion / Empresa</th>
            <td><?= $fill($paciente['empresa_institucion'] ?? '') ?></td>
            <th>Puesto de trabajo</th>
            <td><?= $fill($paciente['puesto_trabajo'] ?? '') ?></td>
        </tr>
        <tr>
            <th>Tipo de contingencia</th>
            <td colspan="3"><?= $fill($tipoContingencia, 'No especificado') ?></td>
        </tr>
        <tr>
            <th>Diagnostico de ingreso</th>
            <td colspan="3"><?= $fill($diagnosticoIngreso, 'Sin diagnostico consignado') ?></td>
        </tr>
        <tr>
            <th>Tratamiento</th>
            <td colspan="3"><?= $lineBreaks(trim($tratamiento) !== '' ? $tratamiento : 'Manejo postoperatorio y reposo segun indicacion medica.') ?></td>
        </tr>
        <tr>
            <th>Procedimiento</th>
            <td colspan="3"><?= $fill($procedimiento, 'Procedimiento quirurgico') ?></td>
        </tr>
        <tr>
            <th>Fecha de ingreso / atencion</th>
            <td><?= $fill($fechaCirugiaLegible) ?></td>
            <th>Fecha de egreso</th>
            <td><?= $fill($fechaEgresoLegible) ?></td>
        </tr>
        <tr>
            <th>Diagnostico de egreso</th>
            <td colspan="3"><?= $fill($diagnosticoEgreso, 'Sin diagnostico consignado') ?></td>
        </tr>
    </table>

    <div class="reposo-box">
        <span class="block-label">Dias de reposo:</span>
        <?= $esc((string) ($reposo['dias'] ?? '0')) ?> (<?= $esc((string) ($reposo['dias_en_letras'] ?? '')) ?>)
        desde <strong><?= $fill($reposo['desde_legible'] ?? '') ?></strong>
        hasta <strong><?= $fill($reposo['hasta_legible'] ?? '') ?></strong>, inclusive.
    </div>

    <div class="signature-wrap">
        <table class="signature-table">
            <tr>
                <td>
                    <?php if ($signaturePath !== ''): ?>
                        <img src="<?= $esc($signaturePath) ?>" alt="Firma digital">
                    <?php endif; ?>
                    <?php if ($sealPath !== ''): ?>
                        <img src="<?= $esc($sealPath) ?>" alt="Sello medico">
                    <?php endif; ?>
                    <div class="signature-line"></div>
                    <p class="signature-meta"><strong><?= $fill($doctor['nombre'] ?? 'Medico tratante') ?></strong></p>
                    <p class="signature-meta"><?= $fill($doctor['especialidad'] ?? '') ?></p>
                    <p class="signature-meta">Cedula: <?= $fill($doctor['cedula'] ?? '') ?></p>
                    <p class="signature-meta">Registro medico: <?= $fill($doctor['cedula'] ?? '') ?></p>
                </td>
            </tr>
        </table>
    </div>
</div>
<?php
$content = ob_get_clean();
$title = 'Certificado medico';

include $layout;
