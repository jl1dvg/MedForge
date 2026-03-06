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
</TABLE>

<?php
$content = ob_get_clean();
$title = 'Signos Vitales';

include $layout;
