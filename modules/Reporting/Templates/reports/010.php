<?php
$layout = __DIR__ . '/../layouts/base.php';
$patient = [
    'afiliacion' => $paciente['afiliacion'] ?? '',
    'hc_number' => $paciente['hc_number'] ?? '',
    'archive_number' => $paciente['hc_number'] ?? '',
    'lname' => $paciente['lname'] ?? '',
    'lname2' => $paciente['lname2'] ?? '',
    'fname' => $paciente['fname'] ?? '',
    'mname' => $paciente['mname'] ?? '',
    'sexo' => $paciente['sexo'] ?? '',
    'fecha_nacimiento' => $paciente['fecha_nacimiento'] ?? '',
    'edad' => $edadPaciente ?? '',
];

ob_start();
include __DIR__ . '/../partials/patient_header.php';
$header = ob_get_clean();

ob_start();
?>
<TABLE>
    <tr>
        <td colspan='10' class='morado'>B. SERVICIO Y PRIORIDAD DE ATENCIÓN
        </td>
    </tr>
    <tr>
        <td class="verde" colspan="2">DIAGNÓSTICO</td>
        <td class="verde">CIE</td>
        <td class="verde" rowspan="3">SERVICIO</td>
        <td class="verde">EMERGENCIA</td>
        <td class="blanco"></td>
        <td class="verde">ESPECIALIDAD</td>
        <td class="blanco">Oftalmolofía</td>
        <td class="verde" colspan="2">PRIORIDAD</td>
    </tr>
    <?php
    $cie10_1 = '';
    $detalle_1 = '';
    $cie10_2 = '';
    $detalle_2 = '';

    if (isset($diagnostico[0])) {
        $cie10_1 = $diagnostico[0]['dx_code'] ?? '';
        $detalle_1 = $diagnostico[0]['descripcion'] ?? '';
    }

    if (isset($diagnostico[1])) {
        $cie10_2 = $diagnostico[1]['dx_code'] ?? '';
        $detalle_2 = $diagnostico[1]['descripcion'] ?? '';
    }
    ?>
    <tr>
        <td class="verde" width="2%">1.</td>
        <td class="blanco" width="40%"><?= htmlspecialchars($detalle_1) ?></td>
        <td class="blanco" width="8%"><?= htmlspecialchars($cie10_1) ?></td>
        <td class="verde">CONSULTA EXTERNA</td>
        <td class="blanco">X</td>
        <td class="verde">SALA</td>
        <td class="blanco"></td>
        <td class="verde">URGENTE</td>
        <td class="blanco"></td>
    </tr>
    <tr>
        <td class="verde" width="2%">2.</td>
        <td class="blanco" width="40%"><?= htmlspecialchars($detalle_2) ?></td>
        <td class="blanco" width="8%"><?= htmlspecialchars($cie10_2) ?></td>
        <td class="verde">HOSPITALIZACIÓN</td>
        <td class="blanco"></td>
        <td class="verde">CAMA</td>
        <td class="blanco"></td>
        <td class="verde">RUTINA</td>
        <td class="blanco">X</td>
    </tr>
    <tr>
        <td class="blanco_left" colspan="10">TRATAMIENTO TERAPEUTICO (ESPECIFIQUE NOMBRE Y TIEMPO DE ADMINISTRACIÓN):
        </td>
    </tr>
</TABLE>
<table>
    <tr>
        <td colspan=79 class="morado">C. LISTADO DE EXÁMENES</td>
    </tr>
    <tr>
        <td colspan=26 class="morado_border">HEMATOLOGÍA</td>
        <td></td>
        <td colspan=18 class="morado_border">COAGULACIÓN Y HEMOSTASIA</td>
        <td></td>
        <td colspan=33 class="morado_border">QUÍMICA SANGUÍNEA</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">BIOMETRIA HEMÁTICA</td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=11 class="cyan_normal">FRAGILIDAD
            OSMÓTICA ERITROCITARIA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">TIEMPO DE
            PROTROMBINA (TP)
        </td>
        <td colspan=2 class=marcado>x</td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">GLUCOSA BASAL</td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=10 class="cyan_normal">BILIRRUBINA
            DIRECTA
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">HEMATOCRITO (HCTO)
        </td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=11 class="cyan_normal">METABISULFITO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">TIEMPO DE
            TROMBOPLASTINA PARCIAL (TTP)
        </td>
        <td colspan=2 class=marcado>x
        </td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">GLUCOSA POST
            PRANDIAL 2 HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">BILIRRUBINA
            INDIRECTA
        </td>
        <td colspan=7 class=xl154 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">HEMOGLOBINA (HB)</td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=11 class="cyan_normal">HEMATOZOOARIO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">TIEMPO DE TROMBINA
            (TT)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">GLUCOSA AL AZAR</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">COLESTEROL
            TOTAL
        </td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">PLAQUETAS
        </td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=11 class="cyan_normal">INVESTIGACIÓN
            DE LEISHMANIA
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">INR</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">SOBRECARGA GLUCOSA
            75<span style='mso-spacerun:yes'>  </span>gramos
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">LIPOPROTEÍNA
            DE ALTA DENSIDAD (HDL)
        </td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">RETICULOCITOS
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">EOSINÓFILO
            EN MOCO NASAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">FACTOR COAGULACIÓN
            VIII
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">TEST DE SULLIVAN
            (GLUCOSA 50 gramos)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">LIPOPROTEÍNA
            DE BAJA DENSIDAD (LDL)
        </td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">VELOCIDAD DE ERITROSEDIMENTACIÓN
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">FROTIS
            SANGRE PERIFERICA
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">FACTOR COAGULACIÓN
            IX
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">UREA</td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=10 class="cyan_normal">LIPOPROTEÍNA
            DE MUY BAJA DENSIDAD (VLDL)
        </td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">HIERRO SERICO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">ÁCIDO
            FÓLICO
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">FACTOR VON
            WILLEBRAND
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">CREATININA</td>
        <td colspan=2 class=marcado>x</td>
        <td colspan=10 class="cyan_normal">TRIGLICERIDOS</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">FIJACIÓN HIERRO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">VITAMINA B12</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">FIBRINOGENO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">ACIDO ÚRICO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">ALBUMINA</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">PORCENTAJE SATURACIÓN TRANSFERRINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">DIMERO-D</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">FOSFATASA ALCALINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">PROTEÍNAS TOTALES</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">TRANSFERRINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">IDENTIFICACION DE INHIBIDORES</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">DESHIDROGENASA LACTICA (LDH)</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">HEMOGLOBINA GLICOSILADA (HBA1C)</td>
        <td colspan=7 class=marcado>x</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">FERRITINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl118></td>
        <td colspan=14 class="cyan_normal">ASPARTATO AMINOTRANSFERASA (AST/TGO)</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">CPK TOTAL</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11></td>
        <td colspan=2></td>
        <td colspan=11></td>
        <td colspan=2></td>
        <td class=xl72></td>
        <td colspan=16 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">ALANINA
            AMINOTRANSFERASA (ALT/TGP)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">FRUCTOSAMINA</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=26 class="morado_border">INMUNOLOGÍA / INFECCIOSAS</td>
        <td class=xl72 colspan="20"></td>
        <td colspan=14 class="cyan_normal">GAMMA-GLUTARIL
            TRANSFERASA (GGT)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">PCR
            CUANTITATIVO
        </td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">COMPLEMENTO C3
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">ANTIGENO
            SUPERFICIE HEPATITIS B (HBSAG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=18 class=morado_border>ORINA</td>
        <td></td>
        <td colspan=14 class="cyan_normal">AMILASA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">COMPLEMENTO C4
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">ANTICUERPOS
            ANTICORE Ig-G (HBcAG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">ELEMENTAL Y
            MICROSCOPICO (EMO)
        </td>
        <td colspan=2 class=marcado>x
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">LIPASA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">IgA TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">ANTICUERPOS
            ANTICORE Ig-M (HBcAG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">GRAM GOTA FRESCA</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">BILIRRUBINA TOTAL</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">IgE TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">HEPATITIS
            C: HVC
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">OSMOLARIDAD URINARIA</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td colspan="34"></td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">IgG TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">VIH
            (1+2) CUALITATIVA
        </td>
        <td colspan=2 class=marcado>x
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">SODIO EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=33 class=morado_border>HECES</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">IgM TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">VIH
            (1+2) CUANTITATIVA
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">POTASIO EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">COPROLÓGICO /
            COPROPARASITARIO
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">CRIPTOSPORIDIUM</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">PROCALCITONINA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">HERPES
            1 (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">CLORO EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">COPROPARASITARIO POR
            CONCENTRACIÓN
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">OXIUROS</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">IL-6
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">HERPES
            1 (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">CALCIO URINARIO</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">COPRO SERIADO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">GARDIA-LAMBLIA
            ANTÍGENO
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">ANA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">HERPES
            2 (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">FOSFORO EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">INVESTIGACION DE
            POLIMORFONUCLEARES (PMN)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">INVESTIGACIÓN
            DE GRASAS
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">ANCA-C
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">HERPES
            2 (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">MAGNESIO EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">SANGRE OCULTA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">AZÚCARES
            REDUCTORES
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">ANCA-P
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">RUBEOLA
            (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">GLUCOSA EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">INVESTIGACIÓN DE pH</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">HELICOBACTER
            PYLORI
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTI-DNA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">RUBEOLA
            (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">UREA EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">ROTAVIRUS</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTI-CCP
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">TOXOPLASMA
            (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">CREATINA EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class=cyan_normal>ADENOVIRIS</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTI-SM</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">TOXOPLASMA
            (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">NITRÓGENO UREICO EN
            ORINA PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan="34"></td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTI-RO
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">CITOMEGALOVIRUS
            (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">ÁCIDO ÚRICO EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=33 class=morado_border>MARCADORES TUMORALES
        </td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTI-LA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">CITOMEGALOVIRUS
            (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">PROTEINAS EN ORINA
            PARCIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=6 class=cyan_normal>CEA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=9 class=cyan_normal>PSA
            LIBRE
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>HE4</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTI CARDIOLIPINA IgG
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">EPSTEIN
            BAR (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl117></td>
        <td colspan=16 class="cyan_normal">FÓSFORO EN ORINA 24
            HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=6 class=cyan_normal>AFP</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=9 class=cyan_normal>PSA
            TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>B-HCG
            LIBRE
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">ANTI CARDIOLIPINA IgM
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">EPSTEIN
            BAR (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">POTASIO EN ORINA 24
            HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=6 class=cyan_normal>CA 125</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=9 class=cyan_normal>β2
            -MICROGLOBULINA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>B-HCG
            CUANTITATIVA
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANTIFOSFOLIPIDOS IgG
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">DENGUE
            (IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">PROTEINAS EN ORINA
            24 HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=6 class=cyan_normal>CA 15.3</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=9 class=cyan_normal>ANTI-TPO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">ANTIFOSFOLIPIDOS IgM
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">DENGUE
            (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">DEPURACIÓN
            CREATININA (ORINA 24 HORAS)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=6 class=cyan_normal>CA 19.9</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=9 class=cyan_normal>ANTI-TG</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>FACTOR REUMATOIDEO (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">CLAMIDIA<span
                    style='mso-spacerun:yes'>  </span>(IgA)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">ÁCIDO ÚRICO EN ORINA
            24 HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=6 class=cyan_normal>CA 72.4</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=9 class=cyan_normal>TIROGLOBULINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">SFLT1 (MARCADOR DE PREECLAMPSIA)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">CLAMIDIA<span
                    style='mso-spacerun:yes'>  </span>(IgG)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">CALCIO EN ORINA 24
            HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td colspan="34"></td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">PIGF (MARCADOR DE PREECLAMPSIA)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">FTA-ABS</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">AMILASA EN ORINA 24
            HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=33 class=morado_border>CITOQUÍMICO
            Y BACTERIOLÓGICO DE LÍQUIDOS
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">ANTICUERPOS ANTICORE Ig-G (HBcAG)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">COBRE EN ORINA 24
            HORAS
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=10 class=cyan_normal>CEFALORRAQUIDEO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=6 class=cyan_normal>PLEURAL</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=6 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">HEPATITIS A (IgM)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">AZÚCARES
            REDUCTORES<span style='mso-spacerun:yes'> </span></td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=10 class=cyan_normal>ARTICULAR / SINOVIAL</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=6 class=cyan_normal>PERICÁRDICO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=6 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>HEPATITIS A TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class="cyan_normal">DROGAS DE ABUSO EN
            ORINA
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=10 class=cyan_normal>ASCÍTICO /
            PERITONEAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=6 class=cyan_normal>LÍQUIDO
            AMNIÓTICO
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=6 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td height=19 class=xl76 style='height:14.0pt'></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=16 class=cyan_normal>ALBUMINURIA</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
    </tr>
    <tr>
        <td colspan=26 class=morado_border>MARCADORES CARDIACOS/VASCULARES
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=33 class=morado_border>NIVELES DE FÁRMACOS TERAPÉUTICAS</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>CPK TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">TROPONINA
            T
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=16 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">ÁCIDO VALPROICO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>CK-MB
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">NT-proBNP</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td class=xl75></td>
        <td></td>
        <td colspan=14 class="cyan_normal">CARBAMAZEPINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">VANCOMICINA</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>CPK-NAC
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">MIOGLOBINA</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=18 class=morado_border>INMUNOSUPRESORES</td>
        <td></td>
        <td colspan=14 class="cyan_normal">FENOBARBITAL</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">AMIKACINA</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>TROPONINA I
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>CYCLOSPORINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>EVEROLIMUS</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">DIGOXINA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">LITIO</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 height=19 class=xl109 width=183 style='height:14.0pt;
  width:140pt'></td>
        <td colspan=2 class=xl208></td>
        <td colspan=11 class=xl109 width=164 style='width:125pt'></td>
        <td colspan=2 class=xl208></td>
        <td class=xl71 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>SIROLIMUS</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class=cyan_normal>FENITOÍNA SÓDICA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=26 class=morado_border>HORMONAS
        </td>
        <td class=xl70></td>
        <td colspan=7 class=cyan_normal>TACROLIMUS</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td colspan="34"></td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>T3</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">PROGESTERONA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td class=xl74></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td></td>
        <td colspan=33 class=morado_border>SEROLOGÍA</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">FT3
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">INSULINA</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl109 width=13 style='width:10pt'></td>
        <td colspan=18 class=morado_border>GASES Y ELECTROLITOS
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">AGLUTINACIONES
            FEBRILES
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">PCR
            SEMICUANTITATIVA
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">T4
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">ACTH</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl109 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>Na</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>Mg</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">ASTO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">MALARIA
            (PCR)
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">FT4
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">PROLACTINA</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl109 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>K</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>Li</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">FR-LÁTEX</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">SIFILIS
            (PCR)
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">TSH
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">VITAMINA
            D
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>Cl</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>GASOMETRÍA
            ARTERIAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">DENGUE (PCR)</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">HELICOBACTER
            PYLORI
        </td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">PTH
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">ESTRADIOL
            (E2)
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>Ca+</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>GASOMETRÍA
            VENOSA
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">CHLAMYDIA (PCR)</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">FSH
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">LH</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>Ca</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class="cyan_normal">PEPSINÓGENO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class="cyan_normal">&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>ANDROSTENEDIONA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">CORTISOL</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>P</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=14 class=cyan_normal>VDRL</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=10 class=cyan_normal>&nbsp;</td>
        <td colspan=7 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">FACTOR DE CRECIMIENTO INSULINOIDE TIPO 1 (IGF-1)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">TESTOSTERONA
            TOTAL
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>FACTOR DE UNION DEL FACTOR DE CRECIMIENTO T1 (IGFBP3)
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">TESTOSTERONA
            LIBRE
        </td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=18 class=morado_border>MEDICINA TRANSFUSIONAL</td>
        <td></td>
        <td colspan=33 class=morado_border>MICROBIOLOGÍA</td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">B-HCG CUALITATIVA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">DHEA-S</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>GRUPO Y FACTOR</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=33 class="cyan_normal">MUESTRA:
        </td>
    </tr>
    <tr>
        <td colspan=11 class="cyan_normal">B-HCG CUANTITATIVA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>COOMBS DIRECTO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td></td>
        <td colspan=33 class="cyan_normal">SITIO ANATÓMICO:
        </td>
    </tr>
    <tr>
        <td colspan=11 class=cyan_normal>HORMONA DE CRECIMIENTO
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class="cyan_normal">&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl73 width=13 style='width:10pt'></td>
        <td colspan=7 class=cyan_normal>COOMBS INDIRECTO</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class=cyan_normal>&nbsp;</td>
        <td colspan=2 class=marcado>&nbsp;
        </td>
        <td class=xl76></td>
        <td colspan=6 class="blanco_left">CULTIVO<span
                    style='mso-spacerun:yes'>  </span>Y ANTIBIOGRAMA
        </td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=7 class="blanco_left">CRISTALOGRAFIA</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=3 class=blanco_left>GRAM</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=4 class=blanco_left>FRESCO</td>
        <td colspan=27 class=marcado>&nbsp;</td>
    </tr>
    <tr>
        <td height=19 class=xl76 style='height:14.0pt'></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td class=xl76></td>
        <td colspan=33 class="blanco_left">ESTUDIO
            MICOLÓGICO (KOH) DE:
        </td>
    </tr>
    <tr>
        <td colspan=45 class=morado_border>BIOLOGÍA MOLECULAR Y GENÉTICA
        </td>
        <td></td>
        <td colspan=33 class=blanco_left>CULTIVO
            MICÓTICO DE:
        </td>
    </tr>
    <tr>
        <td colspan=45 class=blanco_left>&nbsp;
        </td>
        <td></td>
        <td class=blanco_left>1.</td>
        <td colspan=33 class=blanco_left>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=45 class=blanco_left>&nbsp;</td>
        <td></td>
        <td colspan=13 class=blanco_left>INVESTIGACIÓN PARAGONIMUS SPP</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=11 class=blanco_left>COLORACIÓN ZHIEL-NIELSSEN</td>
        <td colspan=7 class="marcado">&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=45 class="blanco_left">&nbsp;
        </td>
        <td></td>
        <td colspan=13 class=blanco_left>INVESTIGACIÓN HISTOPLASMA SPP</td>
        <td colspan=2 class=marcado>&nbsp;</td>
        <td colspan=18 class="blanco_left">&nbsp;
        </td>
    </tr>
</table>
<table>
    <tr>
        <td colspan=79 class="morado">D. DATOS DEL PROFESIONAL RESPONSABLE
        </td>
    </tr>
    <tr>
        <td colspan=11 class="verde">FECHA GENERACIÓN PEDIDO <font class="font9">(aaaa-mm-dd)</font></td>
        <td colspan=11 class=verde>HORA
            GENERACIÓN DEL PEDIDO <font class="font9">(hh:mm)</font></td>
        <td colspan=19 class=verde>PRIMER
            NOMBRE
        </td>
        <td colspan=18 class=verde>PRIMER
            APELLIDO
        </td>
        <td colspan=20 class=verde>SEGUNDO APELLIDO
        </td>
    </tr>
    <tr>
        <td colspan=11 class=blanco><?= htmlspecialchars($solicitud['created_at']); ?></td>
        <td colspan=11 class=blanco><?php //echo htmlspecialchars($createdAtTime); ?></td>
        <td colspan=19 class=blanco><?= htmlspecialchars($solicitud['doctor']); ?></td>
        <td colspan=18 class=blanco>&nbsp;</td>
        <td colspan=20 class=blanco>&nbsp;</td>
    </tr>
    <tr>
        <td colspan=17 class=verde>NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
        <td colspan=28 class=verde>FIRMA</td>
        <td colspan=34 class=verde>SELLO</td>
    </tr>
    <tr>
        <td colspan=17 class=blanco><?php //echo htmlspecialchars($cirujano_data['cedula']); ?></td>
        <td colspan=28
            class=blanco><?php //echo "<img src='" . htmlspecialchars($cirujano_data['firma']) . "' alt='Imagen de la firma' style='max-height: 25px;'>";
            ?></td>
        <td colspan=34 class=blanco>&nbsp;
        </td>
    </tr>
    <tr>
        <td colspan=11 class=verde>FECHA DE TOMA DE MUESTRA <font class="font9">(aaaa-mm-dd)</font></td>
        <td colspan=11 class=verde>HORA DE TOMA DE MUESTRA <font class="font9">(hh:mm)</font></td>
        <td colspan=29 class=verde>NOMBRE Y APELLIDO DE LA PERSONA QUE TOMA LA MUESTRA</td>
        <td colspan=28 class=verde>FIRMA</td>
    </tr>
    <tr>
        <td colspan=11 height=27 class=blanco>&nbsp;</td>
        <td colspan=11 class=blanco>&nbsp;</td>
        <td colspan=29 class=blanco>&nbsp;</td>
        <td colspan=28 class=blanco>&nbsp;</td>
    </tr>
</table>
<table style='border: none'>
    <TR>
        <TD colspan='6' HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                               COLOR='#000000'>SNS-MSP/HCU-form.010A/2021</FONT></B>
        </TD>
        <TD colspan='3' ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR='#000000'>LABORATORIO CLÍNICO - SOLICITUD
                    (1)</FONT></B>
        </TD>
    </TR>
</TABLE>
<pagebreak>
    <table>
        <tr>
            <td colspan=68 class="morado">A. VIH / ITS
            </td>
        </tr>
        <tr>
            <td colspan=12 class="verde">Prueba Rápida<span style='mso-spacerun:yes'> </span></td>
            <td colspan=2 class=marcado>x</td>
            <td colspan=12 class="verde">Elisa Automatizada<span style='mso-spacerun:yes'> </span></td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
            <td colspan=12 class="verde">CLIA<span
                        style='mso-spacerun:yes'> </span></td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
            <td colspan=10 class="verde">IFI</td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
            <td colspan=12 class="verde">Carga Viral<span
                        style='mso-spacerun:yes'> </span></td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
        </tr>
        <tr>
            <td colspan=12 class="verde">CD4
            </td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
            <td colspan=12 class="verde">Tamizaje
                de Sífilis<span style='mso-spacerun:yes'> </span></td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
            <td colspan=12 class="verde">VDRL</td>
            <td colspan=2 class="marcado">&nbsp;
            </td>
            <td colspan=10 class="verde">Hepatitis
                B (HBs-Ag)
            </td>
            <td colspan=16 class="marcado">&nbsp;
        </tr>
    </table>
    <table>
        <tr>
            <td colspan=68 class="morado">B. TUBERCULOSIS</td>
        </tr>
        <tr>
            <td colspan=68 class="verde" style="border-right: none">Tipo de afectado</td>
        </tr>
        <tr>
            <td colspan=68 class="blanco" style="border-right: none">&nbsp;</td>
        </tr>
        <tr>
            <td colspan=5 height=17 class=" blanco_tr
            ">Nuevo
            </td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=5 class="blanco_tr">Recaída</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=5 class="blanco_tr">Fracaso</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=14 class="blanco_tr">Pérdida en el seguimiento</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=4 class="blanco_tr">PVV</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=4 class="blanco_tr">PPL</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=9 class="blanco_tr">Niño &lt; 5 años</td>
            <td></td>
            <td colspan="2" class="marcado">&nbsp;</td>
            <td></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan=15 class="blanco_tr">Sospecha de Meningitis TB</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=22 class="blanco_tr">Alta sospecha clínica y/o radiológica BK (-)</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=8 class="blanco_tr">Comorbilidad</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=9 class="marcado">&nbsp;</td>
            <td colspan="4"></td>
        </tr>
        <tr>
            <td colspan=9 class="blanco_tr">Contacto TBR</td>
            <td colspan=3></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=49></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan=12 class="blanco_tr">Sospecha de TB EP</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=14 class="blanco_tr">Talento humano en salud</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=17 class="blanco_tr">Irregularidad en la toma del Tto</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=7 class="blanco_tr">Reversión</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan="5"></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan=7 class="blanco_tr">Embarazo</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=11 class="blanco_tr">BK (+) al 2do. mes</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=13 class="blanco_tr">Condiciones especiales</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=10 class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=4 class="blanco_tr">Otros</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=7 class="marcado">&nbsp;</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td colspan=36 class="verde">Antecedentes de tuberculosis</td>
            <td colspan=32 class="verde" style="border-right: none">Tipo de muestra</td>
        </tr>
        <tr>
            <td colspan=68 class="blanco" style="border-right: none">&nbsp;</td>
        </tr>
        <tr>
            <td></td>
            <td colspan=23></td>
            <td colspan=11 class="blanco_tr">TIPO DE RESISTENCIA</td>
            <td></td>
            <td colspan=30></td>
            <td></td>
        </tr>
        <tr>
            <td class="marcado">&nbsp;</td>
            <td colspan=7 class="blanco_tr">TB sensible</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=9 class="blanco_tr">TB resistente</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td></td>
            <td colspan=11 class="marcado">&nbsp;</td>
            <td></td>
            <td></td>
            <td colspan=5 class="blanco_tr">Esputo</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan="3"></td>
            <td colspan=4 class="blanco_tr">Otro</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td></td>
            <td colspan=12 class="marcado">&nbsp;</td>
            <td></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td colspan=68 class="verde" style="border-right: none">Solicitud para diagnóstico</td>
        </tr>
        <tr>
            <td colspan=68 class="blanco" style="border-right: none">&nbsp;</td>
        </tr>
        <tr>
            <td class="marcado">&nbsp;</td>
            <td colspan=3 class="blanco_tr">Ada</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=2></td>
            <td colspan=8 class="blanco_tr">Baciloscopia</td>
            <td class="marcado">&nbsp;</td>
            <td colspan=7 class="blanco_tr">Diagnóstico</td>
            <td colspan="2"></td>
            <td class="marcado">&nbsp;</td>
            <td colspan="2"></td>
            <td colspan=2 class="blanco_tr">No.</td>
            <td></td>
            <td colspan="2" class="marcado">&nbsp;</td>
            <td></td>
            <td></td>
            <td colspan=14 class="blanco_tr">Cultivo medio sólido (OK)</td>
            <td class="marcado">&nbsp;</td>
            <td colspan=7 class="blanco_tr">Diagnóstico</td>
            <td colspan="2" class="marcado">&nbsp;</td>
            <td colspan="2"></td>
            <td colspan=2 class="blanco_tr">No.</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td colspan=17></td>
            <td colspan=5 class="blanco_tr">Control</td>
            <td>&</td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=5 class="blanco_tr">No. Mes</td>
            <td></td>
            <td colspan="2" class="marcado">&nbsp;</td>
            <td colspan="17"></td>
            <td colspan=5 class="blanco_tr">Control</td>
            <td class="marcado">&nbsp;</td>
            <td></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td colspan=9 class="blanco_tr">PCR tiempo real</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=2></td>
            <td colspan=10 class="blanco_tr">Nitrato reductasa</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=2></td>
            <td colspan=12 class="blanco_tr">Cultivo medio líquido</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=2></td>
            <td colspan=10 class="blanco_tr">Genotipificación</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td></td>
            <td colspan=7 class="blanco_tr">Tipificación</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td></td>
            <td colspan=9 class="blanco_tr">(Xpert/MTB/RIF)</td>
            <td colspan=5></td>
            <td colspan=7 class="blanco_tr">(GRIESS)*</td>
            <td colspan=7></td>
            <td colspan=5 class="blanco_tr">(MGIT)</td>
            <td colspan=34></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
        <tr>
            <td></td>
            <td></td>
            <td colspan=10 class="blanco_tr">PSD 1ra. Línea</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=6></td>
            <td colspan=10 class="blanco_tr">PSD 1ra. Línea</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=4></td>
            <td colspan=10 class="blanco_tr">PSD 2da. Línea</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan=6></td>
            <td colspan=10 class="blanco_tr">PSD 2da. Línea</td>
            <td></td>
            <td class="marcado">&nbsp;</td>
            <td colspan="2"></td>
        </tr>
        <tr>
            <td colspan="2"></td>
            <td colspan=14 class="blanco_tr">(Proporciones-Medio sólido)</td>
            <td colspan=4></td>
            <td colspan=11 class="blanco_tr">(MGIT-Medio líquido)</td>
            <td colspan=5></td>
            <td colspan=14 class="blanco_tr">(Proporciones-Medio sólido)</td>
            <td colspan=4></td>
            <td colspan=11 class="blanco_tr">(MGIT-Medio líquido)</td>
            <td colspan="3"></td>
        </tr>
        <tr>
            <td colspan=68>&nbsp;</td>
        </tr>
    </table>
    <table>
        <tr>
            <td colspan=79 class="morado">C. DATOS DEL PROFESIONAL RESPONSABLE
            </td>
        </tr>
        <tr>
            <td colspan=11 class="verde">FECHA GENERACIÓN PEDIDO <font class="font9">(aaaa-mm-dd)</font></td>
            <td colspan=11 class=verde>HORA
                GENERACIÓN DEL PEDIDO <font class="font9">(hh:mm)</font></td>
            <td colspan=19 class=verde>PRIMER
                NOMBRE
            </td>
            <td colspan=18 class=verde>PRIMER
                APELLIDO
            </td>
            <td colspan=20 class=verde>SEGUNDO APELLIDO
            </td>
        </tr>
        <tr>
            <td colspan=11 class=blanco><?= htmlspecialchars($solicitud['created_at']); ?></td>
            <td colspan=11 class=blanco><?php //echo htmlspecialchars($createdAtTime); ?></td>
            <td colspan=19 class=blanco><?= htmlspecialchars($solicitud['doctor']); ?></td>
            <td colspan=18 class=blanco>&nbsp;</td>
            <td colspan=20 class=blanco>&nbsp;</td>
        </tr>
        <tr>
            <td colspan=17 class=verde>NÚMERO DE DOCUMENTO DE IDENTIFICACIÓN</td>
            <td colspan=28 class=verde>FIRMA</td>
            <td colspan=34 class=verde>SELLO</td>
        </tr>
        <tr>
            <td colspan=17 class=blanco><?php //echo strtoupper($cirujano_data['cedula']); ?>
            </td>
            <td colspan=28
                class=blanco><?php //echo "<img src='" . htmlspecialchars($cirujano_data['firma']) . "' alt='Imagen de la firma' style='max-height: 40px;'>";
                ?></td>
            <td colspan=34 class=blanco>&nbsp;
            </td>
        </tr>
        <tr>
            <td colspan=11 class=verde>FECHA DE TOMA DE MUESTRA <font class="font9">(aaaa-mm-dd)</font></td>
            <td colspan=11 class=verde>HORA DE TOMA DE MUESTRA <font class="font9">(hh:mm)</font></td>
            <td colspan=29 class=verde>NOMBRE Y APELLIDO DE LA PERSONA QUE TOMA LA MUESTRA</td>
            <td colspan=28 class=verde>FIRMA</td>
        </tr>
        <tr>
            <td colspan=11 height=27 class=blanco>&nbsp;</td>
            <td colspan=11 class=blanco>&nbsp;</td>
            <td colspan=29 class=blanco>&nbsp;</td>
            <td colspan=28 class=blanco>&nbsp;</td>
        </tr>
    </table>
    <table style='border: none'>
        <TR>
            <TD colspan='6' HEIGHT=24 ALIGN=LEFT VALIGN=M><B><FONT SIZE=1
                                                                   COLOR='#000000'>SNS-MSP/HCU-form.010A/2021</FONT></B>
            </TD>
            <TD colspan='3' ALIGN=RIGHT VALIGN=TOP><B><FONT SIZE=3 COLOR='#000000'>LABORATORIO CLÍNICO - SOLICITUD
                        (2)</FONT></B>
            </TD>
        </TR>
    </TABLE>
</pagebreak>

<?php
$content = ob_get_clean();
$title = 'Formulario 010 - Referencia';

include $layout;
