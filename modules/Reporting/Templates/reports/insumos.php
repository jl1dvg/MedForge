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
            <td class="morado" colspan="2"><b>QUIRÓFANO</b>
            </td>
        </tr>
        <tr>
            <td class="verde_left"><b>Cirugía realizada:</b></td>
            <td class="blanco_left"><?php echo $realizedProcedure; ?></td>
        </tr>
        <tr>
            <td class="verde">MEDICAMENTOS/INSUMOS/EQUIPOS UTILIZADOS EN EL PROCEDIMIENTO</td>
            <td class="verde">CANTIDAD</td>
        </tr>

        <?php
        // Resolver lista de insumos independientemente de cómo llegue
        $insumosList = [];
        if (isset($insumos) && is_array($insumos)) {
            $insumosList = $insumos;
        } elseif (isset($datos) && is_array($datos) && isset($datos['insumos']) && is_array($datos['insumos'])) {
            $insumosList = $datos['insumos'];
        } elseif (isset($datos) && is_array($datos) && isset($datos['insumos']) && is_string($datos['insumos'])) {
            $raw = trim((string)$datos['insumos']);
            if ($raw !== '' && strtoupper($raw) !== 'NULL' && $raw !== '[]') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    $insumosList = $decoded;
                }
            }
        }
        ?>
        <?php if (!empty($insumosList)): ?>
            <?php
            $categoriaActual = '';
            foreach ($insumosList as $insumo):
                $cat = strtoupper(trim((string)($insumo['categoria'] ?? '')));
                $nombre = (string)($insumo['nombre'] ?? '');
                $cantidad = (string)($insumo['cantidad'] ?? '');
                if ($cat !== $categoriaActual):
                    $categoriaActual = $cat;
                    ?>
                    <tr>
                        <td class="verde" colspan="2"><b><?= htmlspecialchars($categoriaActual) ?></b></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="blanco_left_mini"><?= htmlspecialchars($nombre) ?></td>
                    <td class="blanco_left_mini"><?= htmlspecialchars($cantidad) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="2">No se encontraron insumos para mostrar.</td>
            </tr>
        <?php endif; ?>
    </table>
    <br>
    <br>
    <br>
    <br>
<?php
echo "<img src='" . htmlspecialchars($cirujano_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'><br>";
echo strtoupper($cirujano_data['nombre']);
echo "<br>";
?>
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
$title = 'Formulario Insumos Quirófano';

include $layout;
?>