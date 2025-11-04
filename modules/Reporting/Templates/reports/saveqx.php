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
        <td class="morado" colspan="70">B. DATOS DE LA CIRUGÍA</td>
    </tr>
    <tr>
        <td class="verde" colspan="13">FECHA<br><span style="font-size:8pt;font-family:Arial;font-weight:normal;">(aaaa-mm-dd)</span>
        </td>
        <td class="verde" colspan="41">PROCEDIMIENTO PROPUESTO</td>
        <td class="verde" colspan="16">QUIRÓFANO</td>
    </tr>
    <tr style="height: 35px">
        <td class="blanco" colspan="13"><?php echo $fechaDia . '/' . $fechaMes . '/' . $fechaAno; ?></td>
        <td class="blanco" colspan="41">
            <?php
            echo strtoupper(
                $nombre_procedimiento_proyectado
                    ? $nombre_procedimiento_proyectado . ' ' . $lateralidad
                    : $realizedProcedure
            );
            ?>
        </td>
        <td class="blanco" colspan="16">1</td>
    </tr>
</table>
<table>
    <tr>
        <td class="morado" colspan="23" width="33.33%"
            style="border-bottom: 1px solid #808080; border-right: 1px solid #808080;">C. ENTRADA<br><span
                    style=" font-size:8pt;font-family:Arial;font-weight:normal;
        ">(Antes de la inducción de la anestesia)</span>
        </td>
        <td class="morado" colspan="23" width="33.33%"
            style="border-bottom: 1px solid #808080; border-right: 1px solid #808080;">D. PAUSA QUIRÚRGICA<br><span
                    style=" font-size:8pt;font-family:Arial;font-weight:normal;
        ">(Antes de la incisión cutánea)</span>
        </td>
        <td class="morado" colspan="25" width="33.33%" style="border-bottom: 1px solid #808080;">E. SALIDA<br><span
                    style="font-size:8pt;font-family:Arial;font-weight:normal;">(Antes de que el paciente salga del quirófano)</span>
        </td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>El paciente ha
                confirmado</b></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>Confirrmación que todos
                los miembros del equipo se han presentado
                por su nombre y función</b>
        </td>
        <td class="blanco_unbordered" colspan="24"><b>El responsable de la lista de chequeo confirma<br>verbalmente con
                el equipo quirúrgico:</b>
        </td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="15">Su identidad</td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="15">Ojo a operar</td>
        <td class="blanco_unbordered" colspan="2">OD</td>
        <td class="blanco_unbordered" colspan="2"><?php echo ($lateralidad == 'OD') ? 'X' : ''; ?></td>
        <td class="blanco_unbordered" colspan="2">OI</td>
        <td class="blanco_unbordered" colspan="2"
            style="border-right: 1px solid #808080;"><?php echo ($lateralidad == 'OI') ? 'X' : ''; ?></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24" rowspan="3">El recuento FINAL de material blanco e<br>instrumental
            quirúrgico
            (previo al cierre) este<br>completo:
        </td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="15">El procedimiento</td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="15">Su consentimiento verbal y escrito</td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Responsable
                de la lista de chequeo confirma
                verbalmente
                con el
                equipo quirúrgico:</b>
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="3">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>Demarcación del sitio
                quirúrgico</b></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="8">NO PROCEDE</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" colspan="2" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="17"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"><b>Hubo necesidad de empaquetar al paciente</b></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="17">Identidad del paciente</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="3">NO</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Se ha
                completado el control formal del instrumental
                anestésico,
                medicación y riesgo anestésico</b>
        </td>
        <td class="blanco_unbordered" colspan="16">Sitio quirúrgico</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="16">Procedimiento (lateralidad)</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="18"><b>Registre el número de compresas</b></td>
        <td class="blanco_unbordered" colspan="5"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="16"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="16">Equipo de intubación</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>Previsión de eventos
                críticos</b></td>
        <td class="blanco_unbordered" colspan="24"><b>Nombre del procedimiento realizado</b></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="16">Equipo de aspiración de la vía aérea</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="16">El cirujano expresa:</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="16">Sistema de ventilación</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="16">Duración del procedimiento</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="5"></td>
        <td class="blanco_unbordered" colspan="9">Oxigeno</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="16">Pérdida prevista de sangre</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="5"></td>
        <td class="blanco_unbordered" colspan="9">Fármacos inhalados</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="5"></td>
        <td class="blanco_unbordered" colspan="9">Medicación</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="16" rowspan="2">El anestesiólogo expresa algún problema específico</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"><b>Clasificación de la herida</b></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="3" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="10">Limpia</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="7">Contaminada</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Pulsoxímetro
                colocado en el paciente y funcionando
        </td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="10">Limpia-contaminada</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="7">Sucia</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Equipo de
                enfermería y/o instrumentación<br>quirúrgica
                revisa:</b>
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="10">Toma de muestras</td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="17"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>Capnógrafo colocado y
                funcionando</b></td>
        <td class="blanco_unbordered" colspan="16" rowspan="3">Esterilidad (con resultado de<br>Indicadores e
            integradores<br>químicos
            internos y externos)
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24" rowspan="2"><b>Etiquetado de las muestras (nombres y apellidos<br>completos
                del
                paciente, historia clínica, fecha)</b>
        </td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="8">NO PROCEDE</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="5"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="4"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" colspan="9"></td>
    </tr>
    <tr style="height: 19px">
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>Tiene el paciente
                alergias conocidas</b></td>
        <td class="blanco_unbordered" colspan="16" rowspan="2">Recuento INICIAL de material<br>blanco e Instrumental
            quirúrgico.
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="4">Cuales</td>
        <td class="blanco_unbordered" colspan="9" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"><b>Identifique el tipo de muestra a enviar</b></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="16" rowspan="2">Dudas o problemas relacionados<br>con el instrumental y
            equipos.
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="6">Citoquímico</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">No.</td>
        <td class="blanco_unbordered" colspan="7"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"><b>Vía aérea difícil /
                riesgo de aspiración</b></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="4">Nombre:</td>
        <td class="blanco_unbordered" colspan="18"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="19" style="border-right: 1px solid #808080;">SI, y hay instrumental y
            equipos disponibles
        </td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="17"></td>
        <td class="blanco_unbordered" colspan="23" rowspan="2"
            style="border-left: 1px solid #808080; border-right: 1px solid #808080"><b>Se ha administrado profilaxis
                antibiótica en los<br>últimos
                60
                minutos</b>
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="6">Cultivos</td>
        <td></td>
        <td></td>
        <td></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">No.</td>
        <td class="blanco_unbordered" colspan="7"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="4">Nombre:</td>
        <td class="blanco_unbordered" colspan="18"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Riesgo de
                hemorragia &gt; 500 ml (7 ml/kg en
                niños)</b></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="9">NO PROCEDE</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="10">Anatomopatológico</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">No.</td>
        <td class="blanco_unbordered" colspan="7"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="19" rowspan="2" style="border-right: 1px solid #808080;">SI, y se ha
            previsto la disponibilidad de<br>acceso
            intravenoso y
            líquidos adecuados.
        </td>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Dispone de
                imágenes diagnosticas esenciales<br>Para el
                procedimiento quirúrgico</b>
        </td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="4">Nombre:</td>
        <td class="blanco_unbordered" colspan="18"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="9">NO PROCEDE</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="3">Otros:</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" rowspan="2" style="border-right: 1px solid #808080;"><b>Se ha
                confirmado la reserva de hemoderivados con el
                laboratorio</b>
        </td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="8">NO APLICA</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="23" style="border-right: 1px solid #808080;"></td>
        <td class="blanco_unbordered" colspan="24" rowspan="2"><b>Si hay problemas que resolver, relacionados con<br>el
                instrumental
                y los equipos</b>
        </td>
    </tr>
    <tr>
        <td class="morado" colspan="46" style="border-top: 1px solid #808080; border-right: 1px solid #808080">F. DATOS
            DE LOS PROFESIONALES RESPONSABLES
        </td>
    </tr>
    <tr style="height: 15px">
        <td class="verde" colspan="15" rowspan="2">NOMBRE COMPLETO DE LA PERSONA RESPONSABLE DE LA LISTA DE
            VERIFICACIÓN
        </td>
        <td class="verde" colspan="16" rowspan="2">NOMBRE COMPLETO DEL CIRUJANO</td>
        <td class="verde" colspan="15" rowspan="2">NOMBRE COMPLETO DEL ANESTESIÓLOGO</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="3">NO</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="24"></td>
        Cuáles:</td>
    </tr>
    <tr>
        <td class="blanco" colspan="15">LIC. <?php echo strtoupper($ayudante_anestesia_data['nombre']); ?></td>
        <td class="blanco" colspan="16">MD. <?php echo strtoupper($cirujano_data['nombre']); ?>
        <td class="blanco" colspan="15">MD. <?php echo strtoupper($anestesiologo_data['nombre']); ?></td>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="verde" colspan="15">FIRMA Y SELLO</td>
        <td class="verde" colspan="16">FIRMA Y SELLO</td>
        <td class="verde" colspan="15">FIRMA Y SELLO</td>
        <td class="blanco_unbordered" colspan="24" rowspan="3"><b>El cirujano, el anestesiólogo y el personal de<br>enfermería
                revisan los principales aspectos de la<br>recuperación del paciente.</b>
        </td>
    </tr>
    <tr>
        <td class="blanco" colspan="15"
            rowspan="5"><?php echo "<img src='" . htmlspecialchars($ayudante_anestesia_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
            ?>
        </td>
        <td class="blanco" colspan="16"
            rowspan="5"><?php echo "<img src='" . htmlspecialchars($cirujano_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
            ?></td>
        <td class="blanco" colspan="15"
            rowspan="5"><?php echo "<img src='" . htmlspecialchars($anestesiologo_data['firma']) . "' alt='Imagen de la firma' style='max-height: 70px;'>";
            ?></td>
    </tr>
    <tr>
        <td class="blanco"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="3"></td>
        <td class="blanco_unbordered" colspan="2">SI</td>
        <td class="blanco_unbordered" colspan="2">X</td>
        <td class="blanco_unbordered"></td>
        <td class="blanco_unbordered" colspan="3">NO</td>
        <td class="blanco_unbordered" colspan="2"></td>
        <td class="blanco_unbordered" colspan="11"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
    <tr>
        <td class="blanco_unbordered" colspan="24"></td>
    </tr>
</table>
<table style="border: none">
    <TR>
        <TD colspan="6" HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                               COLOR="#000000">SNS-MSP/HCU-form.060/2021</FONT></B>
        </TD>
        <TD colspan="3" ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR="#000000">LISTA VERIFICACIÓN CIRUGÍA SEGURA</FONT></B>
        </TD>
    </TR>
</table>

<?php
$content = ob_get_clean();
$title = 'Formulario SAVEQX';

include $layout;
