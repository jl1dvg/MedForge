<?php
$layout = __DIR__ . '/../layouts/base.php';
$header = '';

ob_start();
?>
<table class="trans">
    <tr style="height: 18px">
        <td class="morado_tr" colspan="138">A. DATOS DEL ESTABLECIMIENTO Y USUARIO</td>
    </tr>
    <tr style="height: 16px">
        <td class="verde_tr" colspan="27">INSTITUCIÓN DEL SISTEMA</td>
        <td class="verde_tr" colspan="45">ESTABLECIMIENTO DE SALUD</td>
        <td class="verde_tr" colspan="40">NÚMERO DE HISTORIA CLÍNICA ÚNICA</td>
        <td class="verde_tr" colspan="26">NÚMERO DE ARCHIVO</td>
    </tr>
    <tr style="height: 16px">
        <td class="blanco_tr" colspan="27"><?php echo $afiliacion; ?></td>
        <td class="blanco_tr" colspan="45">CIVE</td>
        <td class="blanco_tr" colspan="40"><?php echo $hc_number; ?></td>
        <td class="blanco_tr" colspan="26"><?php echo $hc_number; ?></td>
    </tr>
    <tr style="height: 16px">
        <td class="verde_tr" colspan="23" rowspan="2">PRIMER APELLIDO</td>
        <td class="verde_tr" colspan="21" rowspan="2">SEGUNDO APELLIDO</td>
        <td class="verde_tr" colspan="25" rowspan="2">PRIMER NOMBRE</td>
        <td class="verde_tr" colspan="24" rowspan="2">SEGUNDO NOMBRE</td>
        <td class="verde_tr" colspan="8" rowspan="2">SEXO</td>
        <td class="verde_tr" colspan="16" rowspan="2">FECHA NACIMIENTO</td>
        <td class="verde_tr" colspan="12" rowspan="2">EDAD</td>
        <td class="verde_tr" colspan="9">CONDICIÓN EDAD</td>
    </tr>
    <tr style="height: 11px">
        <td class="blanco_tr" colspan="2">H</td>
        <td class="blanco_tr" colspan="2">D</td>
        <td class="blanco_tr" colspan="2">M</td>
        <td class="blanco_tr" colspan="3">A</td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_tr" colspan="23"><?php echo $lname; ?></td>
        <td class="blanco_tr" colspan="21"><?php echo $lname2; ?></td>
        <td class="blanco_tr" colspan="25"><?php echo $fname; ?></td>
        <td class="blanco_tr" colspan="24"><?php echo $mname; ?></td>
        <td class="blanco_tr" colspan="8"><?php echo substr($sexo, 0, 1); ?></td>
        <td class="blanco_tr" colspan="16"><?php echo $fecha_nacimiento; ?></td>
        <td class="blanco_tr"
            colspan="12"><?php echo $edadPaciente; ?></td>
        <td class="blanco_tr" colspan="2"></td>
        <td class="blanco_tr" colspan="2"></td>
        <td class="blanco_tr" colspan="2"></td>
        <td class="blanco_tr" colspan="3">X</td>
    </tr>
    <tr style="height: 17px">
        <td class="verde_tr" colspan="9">FECHA:</td>
        <td class="blanco_tr" colspan="18">
            <?php echo $fecha_inicio; ?>
        </td>
        <td class="verde_tr" colspan="6">TALLA (cm)</td>
        <td class="blanco_tr" colspan="14"></td>
        <td class="verde_tr" colspan="14">PESO (kg)</td>
        <td class="blanco_tr" colspan="11"></td>
        <td class="verde_tr" colspan="4">IMC</td>
        <td class="blanco_tr" colspan="18"></td>
        <td class="verde_tr" colspan="14">GRUPO Y FACTOR</td>
        <td class="blanco_tr" colspan="4"></td>
        <td class="verde_tr" colspan="20">CONSENTIMIENTO INFORMADO</td>
        <td class="verde_tr">SI</td>
        <td class="blanco_tr">X</td>
        <td class="verde_tr" colspan="2">NO</td>
        <td class="blanco_tr" colspan="2"></td>
    </tr>
</table>
<table class="trans">
    <tr style="height: 18px">
        <td class="morado_tr" colspan="138">B. SERVICIO Y PRIORIDAD DE ATENCIÓN</td>
    </tr>
    <tr style="height: 12px">
        <td class="verde_left_tr" colspan="13">DIAGNÓSTICO PREOPERATORIO</td>
        <td class="blanco_tr" style="text-align: left"
            colspan="29">
            <?php echo strtoupper(substr($diagnosticos_previos[0], 6)); ?>
        </td>
        <td class="verde_left_tr" colspan="3">CIE</td>
        <td class="blanco_tr" style="text-align: left" colspan="16">
            <?php echo strtoupper(substr($diagnosticos_previos[0], 0, 4)); ?>
        </td>
        <td class="verde_left_tr" colspan="11">CIRUGÍA PROPUESTA</td>
        <td class="blanco_tr" style="text-align: left" colspan="38">
            <?php
            echo strtoupper(
                $nombre_procedimiento_proyectado
                    ? $nombre_procedimiento_proyectado . ' ' . $lateralidad
                    : $realizedProcedure
            );
            ?>
        </td>
        <td class="verde_left_tr" colspan="8">ESPECIALIDAD</td>
        <td class="verde_left_tr" colspan="11" rowspan="4">PRIORIDAD</td>
        <td class="blanco_left_tr" colspan="6">EMERGENTE</td>
        <td class="blanco_tr" colspan="3"></td>
    </tr>
    <tr style="height: 11px">
        <td class="verde_left_tr" colspan="13">DIAGNÓSTICO POSTOPERATORIO</td>
        <td class="blanco_left_tr" style="text-align: left" colspan="29"><?php
            echo substr($diagnostic1, 6);
            ?>
        </td>
        <td class="verde_left_tr" colspan="3">CIE</td>
        <td class="blanco_left_tr" style="text-align: left" colspan="16"><?php
            echo substr($diagnostic1, 0, 4);
            ?>
        </td>
        <td class="verde_left_tr" colspan="11">CIRUGÍA REALIZADA</td>
        <td class="blanco_left_tr" style="text-align: left" colspan="38">
            <?php echo $realizedProcedure;
            ?>
        </td>
        <td class="blanco_left_tr" colspan="8">Oftalmología</td>
        <td class="blanco_left_tr" colspan="6">URGENTE</td>
        <td class="blanco_left_tr" colspan="3"></td>
    </tr>
    <tr style="height: 11px">
        <td class="verde_left_tr" colspan="7">ANESTESIÓLOGO</td>
        <td class="blanco_left_tr" style="text-align: left"
            colspan="35"><?php echo $anestesiologo; ?></td>
        <td class="verde_left_tr" colspan="6">AYUDANTE (S)</td>
        <td class="blanco_left_tr" colspan="24"><?php echo $ayudante_anestesia; ?></td>
        <td class="verde_left_tr" colspan="19">INSTRUMENTISTA</td>
        <td class="blanco_left_tr" colspan="19">
            <?php
            echo $instrumentista; ?>
        </td>
        <td class="verde_left_tr" colspan="8">QUIRÓFANO</td>
        <td class="blanco_left_tr" colspan="6">ELECTIVO</td>
        <td class="blanco_left_tr" colspan="3">X</td>
    </tr>
    <tr style="height: 11px">
        <td class="verde_left_tr" colspan="7">CIRUJANO</td>
        <td class="blanco_left_tr" style="text-align: left" colspan="35"><?php echo $mainSurgeon; ?></td>
        <td class="verde_left_tr" colspan="6"><?php
            if (empty($assistantSurgeon1)) {
                echo strtoupper('AYUDANTE (S)');
            } else {
                echo strtoupper('CIRUJANO 2');
            } ?></td>
        <td class="blanco_left_tr"
            colspan="24"><?php
            if (empty($assistantSurgeon1)) {
                echo strtoupper($ayudante);
            } else {
                echo strtoupper($assistantSurgeon1);
            } ?></td>
        <td class="verde_left_tr" colspan="19">CIRCULANTE</td>
        <td class="blanco_left_tr" colspan="19"><?php
            echo $circulante;
            ?></td>
        </td>
        <td class="blanco_left_tr" colspan="8">1</td>
        <td class="blanco_left_tr" colspan="6"></td>
        <td class="blanco_left_tr" colspan="3"></td>
    </tr>
</table>
<table class="trans">
    <tr style="height: 18px">
        <td class="morado_tr" colspan="138">C. REGIÓN OPERATORIA</td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="6"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="8"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="5">CABEZA</td>
        <td class="blanco_left_tr" colspan="2">X</td>
        <td class="blanco_left_tr" colspan="3">ORGANOS DE LOS SENTIDOS</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="5">CUELLO</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="8">COLUMNA</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="10">TORAX</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="6">ABDOMEN</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="7">PELVIS</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="7">EXTREMIDADES SUPERIORES</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="15">EXTREMIDADES INFERIORES</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="8"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4">PERINEAL</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="6"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="7"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="3"></td>
    </tr>
</table>
<table class="trans">
    <tr style="height: 18px">
        <td class="morado_tr" colspan="138">D. REGISTRO TRANSANESTÉSICO</td>
    </tr>
    <tr style="height: 12px">
        <td class="s36" colspan="30">AGENTE INHALATORIO / INFUSIÓN CONTINUA</td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon"
                             style="height: 6px"></img></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s40"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
    </tr>
    <tr style="height: 12px">
        <td class="s41" colspan="30">O2: 3L</td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s41" colspan="30"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s41" colspan="30"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s41" colspan="30"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s41" colspan="30"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s41" colspan="30"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s36" colspan="30">PARAMETROS DE MONITOREO ANESTÉSICO</td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s39"></td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s38" colspan="2"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2"></td>
        <td class="s38"></td>
        <td class="s40"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s44" colspan="30">ONDA DELTA PP</td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s44" colspan="30">SATURACION O2</td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s44" colspan="30">CAPNOMETRÍA</td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s42"></td>
        <td class="s38"></td>
        <td class="s38"></td>
        <td class="s43"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s44" colspan="30">RELAJACIÓN NEUROMUSCULAR</td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s44" colspan="30">PROFUNDIDAD ANESTÉSICA</td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s42" colspan="3"></td>
        <td class="s43" colspan="3"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s45"></td>
        <td class="s46"></td>
        <td class="s46"></td>
        <td class="s47"></td>
        <td class="s48" colspan="108"></td>
    </tr>
    <tr style="height: 15px">
        <td class="s49" colspan="27" rowspan="2">SIMBOLOGÍA</td>
        <td class="s50" rowspan="2">T°</td>
        <td class="s51" rowspan="2">PV</td>
        <td class="s51" rowspan="2">TA<br>P. / R.</td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s52"></td>
        <td class="s53"></td>
    </tr>
    <tr style="height: 15px">
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s39"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
        <td class="s37"></td>
        <td class="s37"></td>
        <td class="s38" colspan="2">15</td>
        <td class="s38"></td>
        <td class="s38" colspan="2">30</td>
        <td class="s37"></td>
        <td class="s38" colspan="2">45</td>
        <td class="s38"></td>
        <td class="s54"><img src="https://cdn-icons-png.flaticon.com/512/626/626075.png" alt="Icon" style="height: 6px">
        </td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:89px;left:-1px">INICIO ANESTESIA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s59"><img src="https://cdn-icons-png.flaticon.com/512/545/545678.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">240</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:63px;left:-1px">INDUCCIÓN</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/32/32178.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:76px;left:-1px">INICIO CIRUGÍA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/565/565762.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">220</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:76px;left:-1px">FIN DE CIRUGÍA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/565/565762.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:89px;left:-1px">FIN DE ANESTESIA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/2198/2198359.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">200</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:24px;left:-1px">TAS</div>
        </td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/32/32195.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:24px;left:-1px">TAD</div>
        </td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/3838/3838683.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">180</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:24px;left:-1px">TAM</div>
        </td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s66">
            <img src="https://cdn-icons-png.flaticon.com/512/32/32178.png" style="height: 8px">
        </td>
        <td class="s58"></td>
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:76px;left:-1px">SIN NEGRITAS</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s67">17</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:102px;left:-1px">FRECUENCIA CARDÍACA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s66">
            <img src="https://cdn-icons-png.flaticon.com/512/0/14.png" style="height: 8px">
        </td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">160</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:76px;left:-1px">TEMPERATURA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s66"><img src="https://cdn-icons-png.flaticon.com/512/649/649731.png" style="height: 8px">
        </td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">42</td>
        <td class="s67">15</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:24px;left:-1px">PVC</div>
        </td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"><img src="https://cdn-icons-png.flaticon.com/512/702/702845.png" style="height: 8px"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">140</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:128px;left:-1px">RESPIRACIÓN ESPONTÁNEA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">41</td>
        <td class="s67">13</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:115px;left:-1px">RESPIRACIÓN ASISTIDA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">120</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:128px;left:-1px">RESPIRACIÓN CONTROLADA</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">40</td>
        <td class="s67">11</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:63px;left:-1px">TORNIQUETE</div>
        </td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">100</td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s70"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s55 softmerge">
            <div class="softmerge-inner" style="width:37px;left:-1px">FETO</div>
        </td>
        <td class="s56"></td>
        <td class="s57"></td>
        <td class="s57"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">38</td>
        <td class="s67">9</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">80</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">37</td>
        <td class="s67">7</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s58"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">60</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s58"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">36</td>
        <td class="s67">5</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s58"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">40</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s67">35</td>
        <td class="s67">3</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">20</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s67">1</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s62" rowspan="2">0</td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s71"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s58"></td>
        <td class="s60"></td>
        <td class="s61"></td>
        <td class="s61"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s64"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s63"></td>
        <td class="s65"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s72"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s73"></td>
        <td class="s74"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s69"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s68"></td>
        <td class="s70"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s75" colspan="30" rowspan="3">DROGAS ADMINISTRADAS</td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
        <td class="blanco_tr" colspan="12" rowspan="3"></td>
    </tr>
    <tr style="height: 12px">
    </tr>
    <tr style="height: 12px">
    </tr>
    <tr style="height: 12px">
        <td class="s77" colspan="30">POSICIÓN</td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
        <td class="blanco_tr" colspan="12"></td>
    </tr>
</table>
<table class="trans">
    <tr style="height: 18px">
        <td class="morado_tr" colspan="8">E. DROGAS ADMINISTRADAS</td>
    </tr>
    <?php
    // Verificamos que existan medicamentos
    if (!empty($medicamentos)) {
        $contadorFila = 1;
        $columnaActual = 1;
        foreach ($medicamentos as $medicamento) {
        if ((stripos($medicamento['responsable'], 'anest') !== false || stripos($medicamento['responsable'], 'enf') !== false) &&
            (stripos($medicamento['via'], 'topica') === false)) {

                $nombre_medicamento = htmlspecialchars($medicamento['medicamento'] ?? 'N/A');
                $dosis = htmlspecialchars($medicamento['dosis'] ?? 'N/A');

                // Iniciar nueva fila si corresponde
                if ($columnaActual == 1) {
                    echo "<tr style='height: 10px'>";
                }

                echo "<td class='blanco_left_tr' style='font-size: 4px'>{$contadorFila}</td>";
                echo "<td class='blanco_left_tr' style='font-size: 4px'>{$nombre_medicamento} {$dosis}</td>";

                if ($columnaActual == 4) {
                    echo "</tr>";
                    $columnaActual = 1;
                } else {
                    $columnaActual++;
                }

                $contadorFila++;
            }
        }

        // Completar la fila si no se llenaron 4 columnas
        if ($columnaActual != 1) {
            for ($i = $columnaActual; $i <= 4; $i++) {
                echo "<td class='blanco_left_tr'></td><td class='blanco_left_tr'></td>";
            }
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='8'>No se encontraron medicamentos administrados.</td></tr>";
    }
    ?>
</table>
<table class="trans">
    <tr style="height: 17px">
        <td class="morado_tr" colspan="138">F. TÉCNICA ANESTÉSICA</td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_tr" colspan="49"><b>GENERAL</b></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_tr" colspan="50"><b>REGIONAL</b></td>
        <td class="blanco_tr"></td>
        <td class="blanco_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_tr" colspan="33"><b>SEDO - ANALGESIA</b></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="25">SISTEMA</td>
        <td class="blanco_left_tr" colspan="24">APARATO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="9">ASEPSIA CON:</td>
        <td class="blanco_tr" colspan="21"><b>ALCOHOL</b></td>
        <td class="blanco_left_tr" colspan="5">HABÓN CON:</td>
        <td class="blanco_left_tr" colspan="15"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="4">ABIERTO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="6">SEMICERRADO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="12">CERRADO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="9">CIRCUITO CIRCULAR</td>
        <td class="blanco_left_tr" colspan="5"></td>
        <td class="blanco_left_tr" colspan="6">UNIDIRECCIONAL</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="9">LOCAL ASISTIDA</td>
        <td class="blanco_left_tr" colspan="21"><b>BLOQUEO RETROBULBAR</b></td>
        <td class="blanco_left_tr" colspan="5">INTRAVENOSA</td>
        <td class="blanco_tr" colspan="15"><b>X</b></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="49">MANEJO DE VÍA AÉREA</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_tr" colspan="50"><b>TRONCULAR</b></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="13">MÁSCARA FACIAL</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="13">SUPRAGLÓTICA</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="13">TRAQUEOTOMO</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11">BLOQUEO DE NERVIO</td>
        <td class="blanco_left_tr" colspan="21"></td>
        <td class="blanco_left_tr" colspan="9">No. INTENTOS</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="49">INTUBACIÓN</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11">BLOQUEO DEL PLEXO</td>
        <td class="blanco_left_tr" colspan="21"></td>
        <td class="blanco_left_tr" colspan="9">No. INTENTOS</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="5">NASAL</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="5">ORAL</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="9">SUBMENTONEANA</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="9">VISIÓN DIRECTA</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="5">A CIEGAS</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11">ANESTÉSICO LOCAL</td>
        <td class="blanco_tr" colspan="21"><b>
                <?php
                if ($tipo_anestesia == 'OTROS') {
                    echo $tipo_anestesia . ' - TOPICA';
                } else {
                    echo $tipo_anestesia;
                }
                ?>
            </b></td>
        <td class="blanco_left_tr" colspan="9">COADYUVANTE</td>
        <td class="blanco_left_tr" colspan="9"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="49">TIPO DE TUBO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11">TIPO DE AGUJA</td>
        <td class="blanco_tr" colspan="21"><b>
                <?php
                if ($tipo_anestesia == 'OTROS') {
                    echo ' - ';
                } else {
                    echo '25X1';
                }
                ?>
            </b></td>
        <td class="blanco_left_tr" colspan="9">EQUIPO</td>
        <td class="blanco_left_tr" colspan="9"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="8">CONVENCIONAL</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="10">PREFORMADO ORAL</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="9">PREFORMADO NASAL</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="7">REFORZADO</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_tr" colspan="50"><b>NEUROAXIAL</b></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="8">DOBLE LUMEN</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="7">DIÁMETRO</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="3">BALÓN</td>
        <td class="blanco_left_tr">SI</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="3">NO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11">TAPONAMIENTO</td>
        <td class="blanco_left_tr">SI</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2">NO</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="6">RAQUÍDEA</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="5">EPIDURAL</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="4">CAUDAL</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="5"></td>
        <td class="blanco_left_tr" colspan="4">CATETER</td>
        <td class="blanco_left_tr" colspan="5"></td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="5"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr">
            <div class="softmerge-inner" style="width:128px;left:-1px">EQUIPO PARA INTUBACIÓN</div>
        </td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="39"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="6">TIPO AGUJA</td>
        <td class="blanco_left_tr" colspan="9"></td>
        <td class="blanco_left_tr" colspan="19">NÚMERO AGUJA</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="9">No. INTENTOS</td>
        <td class="blanco_left_tr" colspan="5"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="5">CORMACK:</td>
        <td class="blanco_left_tr" colspan="2">I</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="2">II</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2">III</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2">IV</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="9">NÚMERO DE INTENTOS</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="6">BORBOTAJE</td>
        <td class="blanco_left_tr" colspan="3">SI</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2">NO</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4">ACCESO</td>
        <td class="blanco_left_tr" colspan="4">MEDIAL</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4">LATERAL</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="7">SITIO DE PUNCIÓN</td>
        <td class="blanco_left_tr" colspan="11"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33"></td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="21">INDUCCIÓN</td>
        <td class="blanco_left_tr" colspan="28">MANTENIMIENTO</td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11"></td>
        <td class="blanco_left_tr" colspan="21"></td>
        <td class="blanco_left_tr" colspan="9"></td>
        <td class="blanco_left_tr" colspan="9"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="33">ESCALA DE RAMSAY</td>
    </tr>
    <tr style="height: 12px">
        <td class="blanco_left_tr" colspan="9">INHALATORIA</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="6">INTRAVENOSA</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="6">INHALATORIA</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr" colspan="6">INTRAVENOSA</td>
        <td class="blanco_left_tr" colspan="4"></td>
        <td class="blanco_left_tr" colspan="6">BALANCEADA</td>
        <td class="blanco_left_tr" colspan="3"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="11">DERMATOMA</td>
        <td class="blanco_left_tr" colspan="21"></td>
        <td class="blanco_left_tr" colspan="9">POSICIÓN</td>
        <td class="blanco_left_tr" colspan="9"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr"></td>
        <td class="blanco_left_tr" colspan="4">1</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4">2</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4">3</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="4">4</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="3">5</td>
        <td class="blanco_left_tr" colspan="2"></td>
        <td class="blanco_left_tr" colspan="2">6</td>
        <td class="blanco_left_tr" colspan="2"></td>
    </tr>
    <tr style="height: 12px">
        <td class="s107"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s107"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s107"></td>
        <td class="s109"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s108"></td>
        <td class="s110"></td>
    </tr>
</table>
<table style="border: none">
    <tr style="height: 21px">
        <td class="s138">SNS-MSP / HCU-form.018A/2021</td>
        <td class="s140">TRANSANESTÉSICO (1)</td>
    </tr>
</table>
<pagebreak>
    <table class="trans">
        <tr style="height: 18px">
            <td class="morado_tr" colspan="42">G. ACCESOS VASCULARES</td>
            <td class="morado_tr" colspan="48">H. REPOSICIÓN VOLÉMICA (ml)</td>
            <td class="morado_tr" colspan="48">I. PERDIDAS</td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6"><b>TIPO</b></td>
            <td class="blanco_tr" colspan="7"><b>CALIBRE</b></td>
            <td class="blanco_tr" colspan="29"><b>SITIO</b></td>
            <td class="blanco_left_tr" colspan="12">DEXTROSA 5%</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">SANGRE</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">SANGRADO</td>
            <td class="blanco_left_tr" colspan="12"><b>MINIMO</b></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6">IV PERIFÉRICO 1</td>
            <td class="blanco_tr" colspan="7"><b>"22"</b></td>
            <td class="blanco_left_tr" colspan="29"><b>ANTEBRAZO</b></td>
            <td class="blanco_left_tr" colspan="12">DEXTROSA 10%</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">PLASMA</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">DIURESIS</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6">IV PERIFÉRICO 2</td>
            <td class="blanco_left_tr" colspan="7"></td>
            <td class="blanco_left_tr" colspan="29"></td>
            <td class="blanco_left_tr" colspan="12">DEXTROSA 50%</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">PLAQUETAS</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">OTROS</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6">IV PERIFÉRICO 3</td>
            <td class="blanco_left_tr" colspan="7"></td>
            <td class="blanco_left_tr" colspan="29"></td>
            <td class="blanco_left_tr" colspan="12">DEXTROSA EN SS</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">CRIOPRECIPITADOS</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12">TOTAL</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6">IV CENTRAL</td>
            <td class="blanco_left_tr" colspan="7"></td>
            <td class="blanco_left_tr" colspan="29"></td>
            <td class="blanco_left_tr" colspan="12">SS 0.9%</td>
            <td class="blanco_tr" colspan="12"><b>1000 ML</b></td>
            <td class="blanco_left_tr" colspan="12" rowspan="2">OTROS</td>
            <td class="blanco_left_tr" colspan="12"><b></b></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6">INTRA ARTERIAL</td>
            <td class="blanco_left_tr" colspan="7"></td>
            <td class="blanco_left_tr" colspan="29"></td>
            <td class="blanco_left_tr" colspan="12">LACTATO RINGER</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_left_tr" colspan="6">OTRO</td>
            <td class="blanco_left_tr" colspan="7"></td>
            <td class="blanco_left_tr" colspan="29"></td>
            <td class="blanco_left_tr" colspan="12">EXPANSORES</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"><b>TOTAL</b></td>
            <td class="blanco_tr" colspan="12"><b>1000 ML</b></td>
            <td class="blanco_left_tr" colspan="12">BALANCE</td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="s124"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s124"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s124"></td>
            <td class="s126"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s125"></td>
            <td class="s127"></td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 18px">
            <td class="morado_tr" colspan="22">J. DATOS DEL RECIÉN NACIDO</td>
            <td class="morado_tr" colspan="116">K. TIEMPOS TRANSCURRIDOS</td>
        </tr>
        <tr style="height: 16px">
            <td class="blanco_left_tr" colspan="4" rowspan="2">APGAR</td>
            <td class="blanco_left_tr" colspan="6">FETO MUERTO</td>
            <td class="blanco_left_tr" colspan="2"></td>
            <td class="blanco_left_tr" colspan="8">5 MINUTOS</td>
            <td class="blanco_left_tr" colspan="2"></td>
            <td class="verde_tr" colspan="14">DURACIÓN ANESTESIA</td>
            <td class="blanco_left_tr" colspan="14">
                <?php echo $totalHoras ?? ''; ?>
            </td>
            <td class="verde_tr" colspan="13">DURACIÓN DE CIRUGÍA</td>
            <td class="blanco_left_tr" colspan="27">
                <?php echo $totalHorasConDescuento ?? ''; ?>
            </td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 16px">
            <td class="blanco_left_tr" colspan="6">1 MINUTO</td>
            <td class="blanco_left_tr" colspan="2"></td>
            <td class="blanco_left_tr" colspan="8">10 MINUTOS</td>
            <td class="blanco_left_tr" colspan="2"></td>
            <td class="blanco_left_tr" colspan="8"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
            <td class="blanco_left_tr" colspan="12"></td>
        </tr>
        <tr style="height: 12px">
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
            <td class="s92"></td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 12px">
            <td class="morado_tr" colspan="68">L. TÉCNICAS ESPECIALES</td>
        </tr>
        <tr style="height: 23px">
            <td class="blanco_tr" colspan="11">HEMODILUCIÓN</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">AUTOTRANSFUSIÓN</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">HIPOTENSIÓN</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">HIPOTERMIA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="13">CIRCULACIÓN EXTRACORPÓREA</td>
            <td class="blanco_tr" colspan="3"></td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 12px">
            <td class="morado_tr" colspan="68">M. MANTENIMIENTO TEMPERATURA CORPORAL</td>
        </tr>
        <tr style="height: 17px">
            <td class="blanco_tr" colspan="11">MANTA TÉRMICA</td>
            <td class="blanco_tr" colspan="2">X</td>
            <td class="blanco_tr" colspan="11">CALENTAMIENTO DE FLUIDOS</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="4">OTROS</td>
            <td class="blanco_tr" colspan="38"></td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 20px">
            <td class="morado_tr" colspan="68">N. INCIDENTES</td>
        </tr>
        <tr style="height: 23px">
            <td class="blanco_tr" colspan="11">ACTIVIDAD ELÉCTRICA SIN PULSO</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">ARRITMIA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">ASISTOLIA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">BRADICARDIA INESTABLE</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="13">TROMBOEMBOLIA PULMONAR</td>
            <td class="blanco_tr" colspan="3"></td>
        </tr>
        <tr style="height: 16px">
            <td class="blanco_tr" colspan="11">HIPERTERMIA MALIGNA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">ANAFILAXIA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">ISQUEMIA MIOCÁRDICA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">HIPOXEMIA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="13">NEUMOTÓRAX</td>
            <td class="blanco_tr" colspan="3"></td>
        </tr>
        <tr style="height: 16px">
            <td class="blanco_tr" colspan="11">BRONCOESPASMO</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">DESPERTAR PROLONGADO</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">EMBOLIA AÉREA VENOSA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="11">REACCIÓN A LA TRANSFUSIÓN</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="13">LARINGOESPASMO</td>
            <td class="blanco_tr" colspan="3"></td>
        </tr>
        <tr style="height: 23px">
            <td class="blanco_tr" colspan="11">DIFICULTAD DE LA TÉCNICA</td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="55">OTROS</td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 20px">
            <td class="morado_tr" colspan="68">O. RESULTADO DE EXÁMENES DE LABORATORIO</td>
        </tr>
        <tr style="height: 16px">
            <td class="blanco_tr"></td>
            <td class="blanco_tr" colspan="4">HORA</td>
            <td class="blanco_tr" colspan="5">pH</td>
            <td class="blanco_tr" colspan="5">PO2</td>
            <td class="blanco_tr" colspan="5">PCO2</td>
            <td class="blanco_tr" colspan="5">HCO3</td>
            <td class="blanco_tr" colspan="5">EB</td>
            <td class="blanco_tr" colspan="5">SAT. O2</td>
            <td class="blanco_tr" colspan="5">LACTATO</td>
            <td class="blanco_tr" colspan="5">GLUCOSA</td>
            <td class="blanco_tr" colspan="4">Na</td>
            <td class="blanco_tr" colspan="3">K</td>
            <td class="blanco_tr" colspan="3">Cl</td>
            <td class="blanco_tr" colspan="3">HCTO</td>
            <td class="blanco_tr" colspan="2">HB</td>
            <td class="blanco_tr" colspan="8">OTRO</td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">1</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">2</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">3</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">4</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">5</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">6</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">7</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">7</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">9</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
        <tr style="height: 12px">
            <td class="blanco_tr">10</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="5"></td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="3"></td>
            <td class="blanco_tr" colspan="2"></td>
            <td class="blanco_tr" colspan="8"></td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 20px">
            <td class="morado_tr" colspan="68">P. OBSERVACIONES</td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68">Pasa a sala de recuperación</td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68"><br></td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68"><br></td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68"><br></td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68"><br></td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68"><br></td>
        </tr>
        <tr style="height: 15px">
            <td class="blanco_tr" colspan="68"><br></td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 20px">
            <td class="morado_tr" colspan="68">R. CONDICIÓN DE EGRESO</td>
        </tr>
        <tr style="height: 23px">
            <td class="blanco_tr" colspan="13" rowspan="2">CONDICIONES AL SALIR</td>
            <td class="blanco_tr" colspan="7">EXTUBADO</td>
            <td class="blanco_tr" colspan="4"></td>
            <td class="blanco_tr" colspan="7" rowspan="2">CONDUCIDO A:</td>
            <td class="blanco_tr" colspan="7" rowspan="2">UNIDAD DE CUIDADOS POST ANESTÉSICOS</td>
            <td class="blanco_tr" colspan="3" rowspan="2">X</td>
            <td class="blanco_tr" colspan="6" rowspan="2">UNIDAD CUIDADOS INTENSIVOS</td>
            <td class="blanco_tr" colspan="3" rowspan="2"></td>
            <td class="blanco_tr" colspan="7" rowspan="2">CRÍTICOS DE EMERGENCIA</td>
            <td class="blanco_tr" colspan="3" rowspan="2"></td>
            <td class="blanco_tr" colspan="5" rowspan="2">MORGUE</td>
            <td class="blanco_tr" colspan="3" rowspan="2"></td>
        </tr>
        <tr style="height: 23px">
            <td class="blanco_tr" colspan="7">INTUBADO</td>
            <td class="blanco_tr" colspan="4"></td>
        </tr>
        <tr style="height: 23px">
            <td class="blanco_tr" colspan="13">CONSTANTES VITALES DE ENTREGA</td>
            <td class="blanco_tr" colspan="14">TA: 103/96</td>
            <td class="blanco_tr" colspan="10">FC: 74</td>
            <td class="blanco_tr" colspan="10">FR: 20</td>
            <td class="blanco_tr" colspan="12">SAT. O2: 99%</td>
            <td class="blanco_tr" colspan="9">T: 37.5°</td>
        </tr>
    </table>
    <table class="trans">
        <tr style="height: 20px">
            <td class="morado_tr" colspan="68">S. DATOS DEL PROFESIONAL RESPONSABLE</td>
        </tr>
        <tr style="height: 50px">
            <td class="verde_tr" style="height: 40px" colspan="3">HORA</td>
            <td class="blanco_tr" colspan="5"><?php echo $hora_inicio; ?></td>
            <td class="verde_tr" colspan="12">NOMBRE Y APELLIDO DEL PROFESIONAL</td>
            <td class="blanco_tr"
                colspan="18"><?php echo $anestesiologo_data['nombre']; ?></td>
            <td class="verde_tr" colspan="4">FIRMA</td>
            <td class="blanco_tr"
                colspan="12"><?php echo "<img src='" . htmlspecialchars($anestesiologo_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
                ?></td>
            <td class="verde_tr" colspan="4">SELLO Y CÓDIGO</td>
            <td class="blanco_tr" colspan="10"><?php echo $anestesiologo_data['cedula']; ?></td>
        </tr>
    </table>
    <table style="border: none">
        <tr style="height: 21px">
            <td class="s138">SNS-MSP / HCU-form.018A/2021</td>
            <td class="s140">TRANSANESTÉSICO (2)</td>
        </tr>
    </table>
</pagebreak>
<?php
$content = ob_get_clean();
$title = 'Registro Transanestésico';

include $layout;
