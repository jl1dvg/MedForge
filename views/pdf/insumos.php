 <body>
    <table>
        <tr>
            <td class="morado" colspan="2"><b>QUIRÓFANO</b>
            </td>
        </tr>
        <tr>
            <td class="verde_left"><b>Fecha:</b></td>
            <td class="blanco_left"><?php echo $fecha_inicio; ?></td>
            ></td></tr>
        <tr>
            <td class="verde_left"><b>Nombre:</b></td>
            <td class="blanco_left"><?php echo $fname . " " . $mname . " " . $lname . " " . $lname2; ?></td>
        </tr>
        <tr>
            <td class="verde_left"><b>Cirugía realizada:</b></td>
            <td class="blanco_left"><?php echo $realizedProcedure; ?></td>
        </tr>
        <tr>
            <td class="verde">MEDICAMENTOS/INSUMOS/EQUIPOS UTILIZADOS EN EL PROCEDIMIENTO</td>
            <td class="verde">CANTIDAD</td>
        </tr>
        <?php if (!empty($datos['insumos'])): ?>
            <?php
            $categoriaActual = '';
            foreach ($datos['insumos'] as $insumo):
                if ($insumo['categoria'] !== $categoriaActual):
                    $categoriaActual = $insumo['categoria'];
                    ?>
                    <tr>
                        <td class="verde" colspan="2"><b><?= htmlspecialchars($categoriaActual) ?></b></td>
                    </tr>
                <?php endif; ?>
                <tr>
                    <td class="blanco_left_mini"><?= htmlspecialchars($insumo['nombre']) ?></td>
                    <td class="blanco_left_mini"><?= htmlspecialchars($insumo['cantidad']) ?></td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="2">No se encontraron insumos para mostrar.</td></tr>
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

    </body>
<?php
if (!empty($debug_log)) {
    echo "\n<!-- DEBUG INFO: \n" . implode("\n", $debug_log) . "\n-->\n";
}
?>