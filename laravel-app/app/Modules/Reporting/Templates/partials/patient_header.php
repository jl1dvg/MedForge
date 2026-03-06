<?php
/**
 * Shared header for patient information tables.
 *
 * @var array $patient
 */
$defaults = [
    'title' => 'A. DATOS DEL ESTABLECIMIENTO Y USUARIO / PACIENTE',
    'afiliacion' => '',
    'hc_number' => '',
    'archive_number' => null,
    'facility_name' => 'CIVE',
    'lname' => '',
    'lname2' => '',
    'fname' => '',
    'mname' => '',
    'sexo' => '',
    'fecha_nacimiento' => '',
    'edad' => '',
];

$patient = array_merge($defaults, $patient ?? []);
$patient['archive_number'] = $patient['archive_number'] ?? $patient['hc_number'];
?>
<table>
    <tr>
        <td colspan="71" class="morado">
            <?= htmlspecialchars((string) $patient['title'], ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <tr>
        <td colspan="15" height="27" class="verde">INSTITUCIÓN DEL SISTEMA</td>
        <td colspan="6" class="verde">UNICÓDIGO</td>
        <td colspan="18" class="verde">ESTABLECIMIENTO DE SALUD</td>
        <td colspan="18" class="verde">NÚMERO DE HISTORIA CLÍNICA ÚNICA</td>
        <td colspan="14" class="verde" style="border-right: none">NÚMERO DE ARCHIVO</td>
    </tr>
    <tr>
        <td colspan="15" height="27" class="blanco">
            <?= htmlspecialchars((string) $patient['afiliacion'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="6" class="blanco">&nbsp;</td>
        <td colspan="18" class="blanco">
            <?= htmlspecialchars((string) $patient['facility_name'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="18" class="blanco">
            <?= htmlspecialchars((string) $patient['hc_number'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="14" class="blanco" style="border-right: none">
            <?= htmlspecialchars((string) $patient['archive_number'], ENT_QUOTES, 'UTF-8') ?>
        </td>
    </tr>
    <tr>
        <td colspan="15" rowspan="2" height="41" class="verde" style="height:31.0pt;">PRIMER APELLIDO</td>
        <td colspan="13" rowspan="2" class="verde">SEGUNDO APELLIDO</td>
        <td colspan="13" rowspan="2" class="verde">PRIMER NOMBRE</td>
        <td colspan="10" rowspan="2" class="verde">SEGUNDO NOMBRE</td>
        <td colspan="3" rowspan="2" class="verde">SEXO</td>
        <td colspan="6" rowspan="2" class="verde">FECHA NACIMIENTO</td>
        <td colspan="3" rowspan="2" class="verde">EDAD</td>
        <td colspan="8" class="verde" style="border-right: none; border-bottom: none">CONDICIÓN EDAD <font class="font7">(MARCAR)</font></td>
    </tr>
    <tr>
        <td colspan="2" height="17" class="verde">H</td>
        <td colspan="2" class="verde">D</td>
        <td colspan="2" class="verde">M</td>
        <td colspan="2" class="verde" style="border-right: none">A</td>
    </tr>
    <tr>
        <td colspan="15" height="27" class="blanco">
            <?= htmlspecialchars((string) $patient['lname'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="13" class="blanco">
            <?= htmlspecialchars((string) $patient['lname2'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="13" class="blanco">
            <?= htmlspecialchars((string) $patient['fname'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="10" class="blanco">
            <?= htmlspecialchars((string) $patient['mname'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="3" class="blanco">
            <?= htmlspecialchars((string) $patient['sexo'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="6" class="blanco">
            <?= htmlspecialchars((string) $patient['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="3" class="blanco">
            <?= htmlspecialchars((string) $patient['edad'], ENT_QUOTES, 'UTF-8') ?>
        </td>
        <td colspan="2" class="blanco">&nbsp;</td>
        <td colspan="2" class="blanco">&nbsp;</td>
        <td colspan="2" class="blanco">&nbsp;</td>
        <td colspan="2" class="blanco" style="border-right: none">&nbsp;</td>
    </tr>
</table>
