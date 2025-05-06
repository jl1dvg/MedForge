<BODY>
<TABLE>
    <tr>
        <td colspan='71' class='morado'>A. DATOS DEL ESTABLECIMIENTO
            Y USUARIO / PACIENTE
        </td>
    </tr>
    <tr>
        <td colspan='15' height='27' class='verde'>INSTITUCIÓN DEL SISTEMA</td>
        <td colspan='6' class='verde'>UNICÓDIGO</td>
        <td colspan='18' class='verde'>ESTABLECIMIENTO DE SALUD</td>
        <td colspan='18' class='verde'>NÚMERO DE HISTORIA CLÍNICA ÚNICA</td>
        <td colspan='14' class='verde' style='border-right: none'>NÚMERO DE ARCHIVO</td>
    </tr>
    <tr>
        <td colspan='15' height='27' class='blanco'><?php echo $afiliacion; ?></td>
        <td colspan='6' class='blanco'>&nbsp;</td>
        <td colspan='18' class='blanco'>CIVE</td>
        <td colspan='18' class='blanco'><?php echo $hc_number; ?></td>
        <td colspan='14' class='blanco' style='border-right: none'><?php echo $hc_number; ?></td>
    </tr>
    <tr>
        <td colspan='15' rowspan='2' height='41' class='verde' style='height:31.0pt;'>PRIMER APELLIDO</td>
        <td colspan='13' rowspan='2' class='verde'>SEGUNDO APELLIDO</td>
        <td colspan='13' rowspan='2' class='verde'>PRIMER NOMBRE</td>
        <td colspan='10' rowspan='2' class='verde'>SEGUNDO NOMBRE</td>
        <td colspan='3' rowspan='2' class='verde'>SEXO</td>
        <td colspan='6' rowspan='2' class='verde'>FECHA NACIMIENTO</td>
        <td colspan='3' rowspan='2' class='verde'>EDAD</td>
        <td colspan='8' class='verde' style='border-right: none; border-bottom: none'>CONDICIÓN EDAD <font
                    class='font7'>(MARCAR)</font></td>
    </tr>
    <tr>
        <td colspan='2' height='17' class='verde'>H</td>
        <td colspan='2' class='verde'>D</td>
        <td colspan='2' class='verde'>M</td>
        <td colspan='2' class='verde' style='border-right: none'>A</td>
    </tr>
    <tr>
        <td colspan='15' height='27' class='blanco'><?php echo $lname; ?></td>
        <td colspan='13' class='blanco'><?php echo $lname2; ?></td>
        <td colspan='13' class='blanco'><?php echo $fname; ?></td>
        <td colspan='10' class='blanco'><?php echo $mname; ?></td>
        <td colspan='3' class='blanco'><?php echo $sexo; ?></td>
        <td colspan='6' class='blanco'><?php echo $fecha_nacimiento; ?></td>
        <td colspan='3' class='blanco'><?php echo $edadPaciente; ?></td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco'>&nbsp;</td>
        <td colspan='2' class='blanco' style='border-right: none'>&nbsp;</td>
    </tr>
</TABLE>
<table>
    <tr>
        <td class="morado" colspan="24">B. CONSTANTES VITALES</td>
    </tr>
    <tr>
        <td class="verde" colspan="3">FECHA</td>
        <td class="blanco_left" colspan="3"><?php echo $fechaDia . '/' . $fechaMes . '/' . $fechaAno; ?></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
    </tr>
    <tr>
        <td class="verde" colspan="3">DÍA DE INTERNACIÓN</td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
    </tr>
    <tr>
        <td class="verde" colspan="3">DÍA POST QUIRÚRGICO</td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
        <td class="blanco_left" colspan="3"></td>
    </tr>
    <tr>
        <td class="verde" rowspan="2">PULSO</td>
        <td class="verde" rowspan="2">TEMP</td>
        <td class="verde"></td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
        <td class="verde">AM</td>
        <td class="verde">PM</td>
        <td class="verde">HS</td>
    </tr>
    <tr>
        <td class="verde" rowspan="2">HORA</td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
        <td class="blanco_left" rowspan="2"></td>
    </tr>
    <tr>
        <td class="verde" rowspan="2">HORA</td>
        <td class="verde" rowspan="2">HORA</td>
    </tr>
    <tr>
        <td class="verde"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">140</td>
        <td rowspan="2" class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">130</td>
        <td rowspan="2" class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">120</td>
        <td rowspan="2" class="cyan_left" style="border: none">42</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">110</td>
        <td rowspan="2" class="cyan_left" style="border: none">41</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr><tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">100</td>
        <td rowspan="2" class="cyan_left" style="border: none">40</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">90</td>
        <td rowspan="2" class="cyan_left" style="border: none">39</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">80</td>
        <td rowspan="2" class="cyan_left" style="border: none">38</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">70</td>
        <td rowspan="2" class="cyan_left" style="border: none">37</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">60</td>
        <td rowspan="2" class="cyan_left" style="border: none">36</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left" style="border: none"></td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td rowspan="2" class="cyan_left" style="border: none">50</td>
        <td rowspan="2" class="cyan_left" style="border: none">35</td>
        <td class="cyan_left"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
    <tr>
        <td class="cyan_left" style="border-top: 2px solid #808080"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
        <td class="blanco_left_remini"></td>
    </tr>
</table>
<table>
    <tr>
        <td class="cyan_left" width="14.5%">F. RESPIRATORIA X min</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">PULSIOXIMETRÍA %</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">PRESIÓN SISTÓLICA</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">PRESIÓN DIASTÓLICA</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">RESPONSABLE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
</table>
<table>
    <tr>
        <td colspan='8' class='morado'>C. MEDIDAS ANTROPOMÉTRICAS</td>
    </tr>
    <tr>
        <td class='cyan_left' width="14.5%">PESO (kg)</td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
    </tr>
    <tr>
        <td class='cyan_left'>TALLA (cm)</td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
    </tr>
    <tr>
        <td class='cyan_left'>PERÍMETRO CEFÁLICO (cm)</td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
    </tr>
    <tr>
        <td class='cyan_left'>PERÍMETRO ABDOMINAL (cm)</td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
    </tr>
    <tr>
        <td class='cyan_left'>OTROS ESPECIFIQUE</td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
        <td class='blanco_left_mini'></td>
    </tr>
</table>
<table>
    <tr>
        <td class="morado" colspan="9">D. INGESTA - ELIMINACIÓN / BALANCE HÍDRICO</td>
    </tr>
    <tr>
        <td class="cyan_left" rowspan="4" width="2%">INGRESOS ML</td>
        <td class="cyan_left" width="12.5%">ENTERAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">PARENTERAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">VÍA ORAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">TOTAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left" rowspan="6">ELIMINACIONES ML</td>
        <td class="cyan_left">ORINA</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">DRENAJE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">VÓMITO</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">DIARREAS</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">OTROS ESPECIFIQUE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">TOTAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left" colspan="2"><b>BALANCE HÍDRICO TOTAL</b></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left" colspan="2">DIETA PRESCRITA</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left" colspan="2">NÚMERO DE COMIDAS</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left" colspan="2">NÚMERO DE MICCIONES</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left" colspan="2">NÚMERO DE DEPOSICIONES</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
</table>
<table>
    <tr>
        <td class="morado" colspan="8">E. CUIDADOS GENERALES</td>
    </tr>
    <tr>
        <td class="cyan_left" width="12.5%">ASEO</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">BAÑO</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">REPOSO ESPECIFIQUE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">POSICIÓN ESPECIFIQUE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">OTROS ESPECIFIQUE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
</table>
<table>
    <tr>
        <td class="morado" colspan="8">F. FECHA DE COLOCACIÓN DE DISPOSITIVOS MÉDICOS (aaaa-mm-dd)</td>
    </tr>
    <tr>
        <td class="cyan_left" width="12.5%">VÍA CENTRAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">VÍA PERIFÉRICA</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">SONDA NASOGÁSTRICA</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">SONDA VESICAL</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">OTROS ESPECIFIQUE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
    <tr>
        <td class="cyan_left">RESPONSABLE</td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
        <td class="blanco_left_mini"></td>
    </tr>
</table>
<table style='border: none'>
    <TR>
        <TD colspan='6' HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                               COLOR='#000000'>SNS-MSP/HCU-form.020/2021</FONT></B>
        </TD>
        <TD colspan='3' ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR='#000000'>CONSTANTES VITALES / BALANCE HÍDRICO (1)</FONT></B>
        </TD>
    </TR>
    ]
</TABLE>
</BODY>