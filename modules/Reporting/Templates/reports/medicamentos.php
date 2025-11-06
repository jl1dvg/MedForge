<?php
$layout = __DIR__ . '/../layouts/base.php';
$patient = [
    'afiliacion' => $afiliacion ?? '',
    'hc_number' => $hc_number ?? '',
    'archive_number' => $hc_number ?? '',
    'lname' => $lname ?? '',
    'lname2' => $lname2 ?? '',
    'fname' => $fname ?? '',
    'mname' => $mname ?? '',
    'sexo' => $sexo ?? '',
    'fecha_nacimiento' => $fecha_nacimiento ?? '',
    'edad' => $edadPaciente ?? '',
];

ob_start();
include __DIR__ . '/../partials/patient_header.php';
$header = ob_get_clean();

ob_start();
?>
<table>
    <tr>
        <td class="verde" colspan="10">ALERGIA A MEDICAMENTOS</td>
        <td class="verde" colspan="2">SI</td>
        <td class="blanco_left" colspan="2"></td>
        <td class="verde" colspan="2">NO</td>
        <td class="blanco_left" colspan="2"></td>
        <td class="verde" colspan="7">DESCRIBA:</td>
        <td class="blanco_left" colspan="52"></td>
    </tr>
</table>
<table>
    <tr>
        <td class="morado" colspan="77">B. ADMINISTRACIÓN DE MEDICAMENTOS PRESCRITOS</td>
    </tr>
    <tr>
        <td class="morado" colspan="17" style="border-top: 1px solid #808080; border-right: 1px solid #808080;">1.
            MEDICAMENTO
        </td>
        <td class="morado" colspan="60" style="border-top: 1px solid #808080;">2. ADMINISTRACIÓN</td>
    </tr>
    <tr>
        <td class="verde" colspan="17">FECHA</td>
        <td class="blanco" colspan="15"><?php echo $fechaDia . "/" . $fechaMes . "/" . $fechaAno; ?></td>
        <td class="blanco" colspan="15"></td>
        <td class="blanco" colspan="15"></td>
        <td class="blanco" colspan="15"></td>
    </tr>
    <tr>
        <td class="verde" colspan="17">DOSIS, VIA, FRECUENCIA</td>
        <td class="verde" colspan="6">HORA</td>
        <td class="verde" colspan="9">RESPONSABLE</td>
        <td class="verde" colspan="6">HORA</td>
        <td class="verde" colspan="9">RESPONSABLE</td>
        <td class="verde" colspan="6">HORA</td>
        <td class="verde" colspan="9">RESPONSABLE</td>
        <td class="verde" colspan="6">HORA</td>
        <td class="verde" colspan="9">RESPONSABLE</td>
    </tr>
    <?php
    // Resolver lista de medicamentos independientemente de cómo llegue
    $medicamentosList = [];
    if (isset($medicamentos) && is_array($medicamentos)) {
        $medicamentosList = $medicamentos;
    } elseif (isset($datos) && is_array($datos) && isset($datos['medicamentos']) && is_array($datos['medicamentos'])) {
        $medicamentosList = $datos['medicamentos'];
    } elseif (isset($datos) && is_array($datos) && isset($datos['medicamentos']) && is_string($datos['medicamentos'])) {
        $raw = trim((string)$datos['medicamentos']);
        if ($raw !== '' && strtoupper($raw) !== 'NULL' && $raw !== '[]') {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $medicamentosList = $decoded;
            }
        }
    }
    ?>
    <?php if (!empty($medicamentosList)): ?>
        <?php foreach ($medicamentosList as $medicamento): ?>
            <tr>
                <td class="blanco_left" colspan="17">
                    <?= htmlspecialchars((string)($medicamento['medicamento'] ?? '')) ?>,
                    <?= htmlspecialchars((string)($medicamento['dosis'] ?? '')) ?>,
                    <?= htmlspecialchars((string)($medicamento['via'] ?? '')) ?>,
                    <?= htmlspecialchars((string)($medicamento['frecuencia'] ?? '')) ?>
                </td>
                <td class="blanco" colspan="6"><?= htmlspecialchars((string)($medicamento['hora'] ?? '')) ?></td>
                <td class="blanco" colspan="9"><?= htmlspecialchars((string)($medicamento['responsable'] ?? '')) ?></td>
                <td class="blanco" colspan="6"></td>
                <td class="blanco" colspan="9"></td>
                <td class="blanco" colspan="6"></td>
                <td class="blanco" colspan="9"></td>
                <td class="blanco" colspan="6"></td>
                <td class="blanco" colspan="9"></td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="2">No se encontraron medicamentos para mostrar.</td>
        </tr>
    <?php endif; ?>
</table>
<table style="border: none">
    <TR>
        <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                               COLOR="#000000"></FONT></B>
        </TD>
        <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">ADMINISTRACION DE MEDICAMENTOS
                    (1)</FONT></B>
        </TD>
    </TR>
</table>
<?php
$content = ob_get_clean();
$title = 'Formulario Medicamentos';

include $layout;
?>