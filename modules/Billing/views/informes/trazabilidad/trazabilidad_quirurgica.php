<?php
// Legacy billing report view relocated under modules/Billing/views/informes.
// Función para visualizar eventos quirúrgicos de manera uniforme
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__, 5) . '/bootstrap.php';
} // Aquí debes conectar PDO a $db
require_once '../../../helpers/trazabilidad_helpers.php';

use Controllers\TrazabilidadController;
use Helpers\TrazabilidadHelpers;

$controller = new TrazabilidadController($pdo);

// Cambia por un número de HC real para probar
$hc_number = '0901244087';

$datos = $controller->mostrarTodosLosProcedimientos($hc_number);

// Agrupar trazabilidad por form_id
$procesos = $controller->obtenerProcesos($hc_number);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Trazabilidad Quirúrgica</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
        }

        .box {
            border: 1px solid #ccc;
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .title {
            font-weight: bold;
            margin-bottom: 10px;
        }

        .item {
            margin-left: 20px;
        }

        .highlight {
            color: green;
        }

        .warning {
            color: red;
        }
    </style>
</head>
<body>
<h1>Trazabilidad Quirúrgica para HC <?= htmlspecialchars($hc_number) ?></h1>


<?php
// 1. Primero, ordenar todos los formularios por fecha
$datos_ordenados = $datos;
usort($datos_ordenados, function ($a, $b) {
    $fA = !empty($a['fecha']) ? $a['fecha'] : (!empty($a['fecha_consulta']) ? $a['fecha_consulta'] : null);
    $fB = !empty($b['fecha']) ? $b['fecha'] : (!empty($b['fecha_consulta']) ? $b['fecha_consulta'] : null);
    return strcmp((string)$fA, (string)$fB);
});

// 2. Buscar secuencias completas por episodio quirúrgico
$episodios = TrazabilidadHelpers::construirEpisodios($datos_ordenados);
$utilizados = array_merge(...array_map(fn($ep) => [
    $ep['solicitud']['form_id'],
    $ep['biometria']['form_id'],
    $ep['anestesia']['form_id'],
    $ep['programada']['form_id'],
    $ep['realizada']['form_id']
], $episodios));

// Mostrar episodios quirúrgicos agrupados
foreach ($episodios as $idx => $ep) {
    $ojo = $ep['programada']['ojo'] ? $ep['programada']['ojo'] : '';
    ?>
    <div class="box">
        <div class="title">🧬 Episodio Quirúrgico #<?= $idx + 1 ?><?= $ojo ? " – $ojo" : "" ?></div>
        <ul style="margin-left:0;padding-left:18px;">
            <li>📄 Solicitud de
                biometría: <?= $ep['solicitud']['fecha'] ? $ep['solicitud']['fecha'] : 'Fecha no registrada' ?>
                (Formulario <?= $ep['solicitud']['form_id'] ?>)
            </li>
            <li>🔬 Biometría
                realizada: <?= $ep['biometria']['fecha'] ? $ep['biometria']['fecha'] : 'Fecha no registrada' ?>
                (Formulario <?= $ep['biometria']['form_id'] ?>)
            </li>
            <li>📅 Control
                anestésico: <?= $ep['anestesia']['fecha'] ? $ep['anestesia']['fecha'] : 'Fecha no registrada' ?>
                (Formulario <?= $ep['anestesia']['form_id'] ?>)
            </li>
            <li>🛏️ Cirugía
                efectuada: <?= $ep['realizada']['fecha'] ? $ep['realizada']['fecha'] : 'Fecha no registrada' ?>
                (Formulario <?= $ep['realizada']['form_id'] ?>)
            </li>
            <li><strong>🗓️ Timeline:</strong><br>
                <?= $ep['solicitud']['fecha'] ? '📄 ' . $ep['solicitud']['fecha'] . ' → Solicitud<br>' : '' ?>
                <?= $ep['biometria']['fecha'] ? '🔬 ' . $ep['biometria']['fecha'] . ' → Biometría<br>' : '' ?>
                <?= $ep['anestesia']['fecha'] ? '💉 ' . $ep['anestesia']['fecha'] . ' → Anestesia<br>' : '' ?>
                <?= $ep['realizada']['fecha'] ? '🏥 ' . $ep['realizada']['fecha'] . ' → Cirugía<br>' : '' ?>
            </li>
        </ul>
        <?php
        // Calcular tiempos
        $t1 = $ep['solicitud']['fecha'];
        $t2 = $ep['biometria']['fecha'];
        $t3 = $ep['anestesia']['fecha'];
        $t4 = $ep['realizada']['fecha'];
        ?>
        <?php
        $alertas = [];

        if ($t1 && $t4 && (new DateTime($t1))->diff(new DateTime($t4))->days > 60) {
            $alertas[] = "Más de 60 días entre solicitud y cirugía.";
        }
        if ($t2 && $t3 && (new DateTime($t3) < new DateTime($t2))) {
            $alertas[] = "Anestesia registrada antes que la biometría.";
        }
        if (!$t2 || !$t3 || !$t4) {
            $alertas[] = "Faltan formularios clave.";
        }

        foreach ($alertas as $msg) {
            echo "<div class='warning'>⚠️ $msg</div>";
        }
        ?>
        <div><strong>📈 Tiempos:</strong></div>
        <ul style="margin-left:0;padding-left:18px;">
            <?= TrazabilidadHelpers::imprimirIntervalo("Entre solicitud y biometría", $t1 ? new DateTime($t1) : null, $t2 ? new DateTime($t2) : null) ?>
            <?= TrazabilidadHelpers::imprimirIntervalo("Entre biometría y anestesia", $t2 ? new DateTime($t2) : null, $t3 ? new DateTime($t3) : null) ?>
            <?= TrazabilidadHelpers::imprimirIntervalo("Entre anestesia y cirugía", $t3 ? new DateTime($t3) : null, $t4 ? new DateTime($t4) : null) ?>
        </ul>
        <?php
        function renderBar($label, $inicio, $fin) {
            if ($inicio && $fin) {
                $dias = $inicio->diff($fin)->days;
                $ancho = min(100, $dias * 2); // 2px por día, máx 100%
                echo "<div style='margin:4px 0'><span style='display:inline-block;width:100px;'>{$label}:</span>
                        <div style='display:inline-block;width:{$ancho}px;height:10px;background:blue' title='{$dias} días'></div> ({$dias} días)</div>";
            }
        }

        renderBar("Solicitud → Biometría", $t1 ? new DateTime($t1) : null, $t2 ? new DateTime($t2) : null);
        renderBar("Biometría → Anestesia", $t2 ? new DateTime($t2) : null, $t3 ? new DateTime($t3) : null);
        renderBar("Anestesia → Cirugía", $t3 ? new DateTime($t3) : null, $t4 ? new DateTime($t4) : null);
        ?>
    </div>
    <?php
}
?>

<?php
echo TrazabilidadHelpers::renderFormulariosRestantes($datos_ordenados, $utilizados, $controller);
?>

<?php
// Calcular tiempo entre último punto 1 (solicitud biometría con fecha real)
// y primer punto 2 (biometría realizada)
$ultimaSolicitud = null;
$realizacion = null;

foreach ($procesos as $p) {
    if (!empty($p['biometria_fecha']) && $p['biometria_fecha'] !== 'SOLICITADA') {
        $ultimaSolicitud = new DateTime($p['biometria_fecha']);
    }
    if (!empty($p['biometria_realizada_fecha']) && !$realizacion && $ultimaSolicitud) {
        $realizacion = new DateTime($p['biometria_realizada_fecha']);
        break;
    }
}

if ($ultimaSolicitud && $realizacion) {
    echo TrazabilidadHelpers::imprimirIntervalo("📈 Tiempo total entre solicitud de biometría (punto 1) y su realización (punto 2)", $ultimaSolicitud, $realizacion);
}

// Calcular tiempo entre biometría realizada (punto 2) y cirugía programada (punto 3)
$biometriaRealizada = null;
$cirugiaProgramada = null;

foreach ($procesos as $p) {
    if (!empty($p['biometria_realizada_fecha'])) {
        $biometriaRealizada = new DateTime($p['biometria_realizada_fecha']);
    }
    if (!empty($p['cirugia_fecha']) && $biometriaRealizada) {
        $cirugiaProgramada = new DateTime($p['cirugia_fecha']);
        break;
    }
}

if ($biometriaRealizada && $cirugiaProgramada) {
    echo TrazabilidadHelpers::imprimirIntervalo("📈 Tiempo entre biometría realizada (punto 2) y cirugía programada (punto 3)", $biometriaRealizada, $cirugiaProgramada);
}

// Punto 4: Control anestésico
$fechaControlAnestesico = null;
foreach ($datos as $formulario) {
    if (
        isset($formulario['motivo_consulta']) &&
        stripos($formulario['motivo_consulta'], 'anest') !== false &&
        !empty($formulario['fecha_consulta'])
    ) {
        echo "<div class='item'>📋 " . htmlspecialchars($formulario['procedimiento_proyectado'] ?? ("Formulario " . $formulario['form_id'])) . ": " . (new DateTime($formulario['fecha_consulta']))->format('Y-m-d') . "</div>";
        $fechaControlAnestesico = new DateTime($formulario['fecha_consulta']);
        if (isset($biometriaRealizada)) {
            echo TrazabilidadHelpers::imprimirIntervalo("📈 Tiempo entre biometría realizada (punto 2) y control anestésico (punto 4)", $biometriaRealizada, $fechaControlAnestesico);
        }
        break;
    }
}

$fechaCirugiaEfectuada = null;
// Detectar y mostrar cirugía efectuada (punto 5), permitiendo AGENDADO con cirugia no vacía
$fechaCirugiaEfectuada = null;
$cirugiaRealizadaForm = null;
$cirugiaProgramada = null;
// Buscar la cirugía programada (AGENDADO)
foreach ($datos as $form) {
    if (
        isset($form['estado_agenda']) &&
        strtoupper($form['estado_agenda']) === 'AGENDADO' &&
        !empty($form['fecha']) &&
        !empty($form['cirugia']) &&
        strtoupper(trim($form['cirugia'])) !== 'SELECCIONE'
    ) {
        $cirugiaProgramada = $form;
        break;
    }
}

// Buscar la cirugía efectuada (REALIZADO)
foreach ($datos as $form) {
    if (
        isset($form['estado_agenda']) &&
        strtoupper($form['estado_agenda']) === 'REALIZADO' &&
        !empty($form['fecha']) &&
        !empty($form['cirugia']) &&
        strtoupper(trim($form['cirugia'])) !== 'SELECCIONE'
    ) {
        $cirugiaTexto = strtolower($form['cirugia']);
        $proyectadoTexto = strtolower($form['procedimiento_proyectado'] ?? '');
        $palabrasClaveCirugia = ['facoemulsificacion', 'implante'];
        $tienePalabraClave = false;
        foreach ($palabrasClaveCirugia as $clave) {
            if (stripos($cirugiaTexto, $clave) !== false || stripos($proyectadoTexto, $clave) !== false) {
                $tienePalabraClave = true;
                break;
            }
        }
        // Evitar si solo tiene plan o solicitado
        $soloPlan = !empty($form['plan']) && empty($form['cirugia']) && empty($form['solicitado']);
        $soloSolicitado = !empty($form['solicitado']) && empty($form['cirugia']);
        if ($tienePalabraClave && !$soloPlan && !$soloSolicitado) {
            $fechaCirugiaEfectuada = new DateTime($form['fecha']);
            $cirugiaRealizadaForm = $form;
            break;
        }
    }
}

// Si no hay cirugía efectuada REALIZADO, inferir por AGENDADO si corresponde
if (empty($fechaCirugiaEfectuada) && !empty($cirugiaProgramada)) {
    foreach ($datos as $form) {
        if (
            isset($form['estado_agenda']) &&
            strtoupper($form['estado_agenda']) === 'AGENDADO' &&
            !empty($form['fecha']) &&
            !empty($form['cirugia']) &&
            strtoupper(trim($form['cirugia'])) !== 'SELECCIONE' &&
            $form['fecha'] === $cirugiaProgramada['fecha']
        ) {
            $fechaCirugiaEfectuada = new DateTime($form['fecha']);
            $cirugiaRealizadaForm = $form;
            $cirugiaRealizadaForm['estado_agenda'] = 'REALIZADO (inferido)';
            break;
        }
    }
}

if ($fechaCirugiaEfectuada && $cirugiaRealizadaForm) {
    // Mostrar siempre la cirugía efectuada (desde agenda)
    $estadoCirugia = isset($cirugiaRealizadaForm['estado_agenda']) ? $cirugiaRealizadaForm['estado_agenda'] : '';
    $etiqueta = (stripos($estadoCirugia, 'inferido') !== false) ? '🏥 Cirugía efectuada (inferido por fecha y programación)' : '🏥 Cirugía efectuada (desde agenda)';
    echo "<div class='item'>{$etiqueta}: " . $fechaCirugiaEfectuada->format('Y-m-d') . "</div>";

    // Buscar referencia cercana
    $fechaReferencia = null;
    $referencia = '';
    if (isset($cirugiaProgramada)) {
        $fechaReferencia = new DateTime($cirugiaProgramada['fecha']);
        $referencia = 'cirugía programada (punto 3)';
    }
    if (isset($fechaControlAnestesico)) {
        if (
            !$fechaReferencia ||
            abs($fechaControlAnestesico->getTimestamp() - $fechaCirugiaEfectuada->getTimestamp()) < abs($fechaReferencia->getTimestamp() - $fechaCirugiaEfectuada->getTimestamp())
        ) {
            $fechaReferencia = $fechaControlAnestesico;
            $referencia = 'control anestésico (punto 4)';
        }
    }
    if ($fechaReferencia) {
        echo TrazabilidadHelpers::imprimirIntervalo("📈 Tiempo entre $referencia y cirugía efectuada (punto 5)", $fechaReferencia, $fechaCirugiaEfectuada);
    }
    // Imprimir tiempo entre biometría realizada y cirugía efectuada (si aplica)
    if (isset($biometriaRealizada)) {
        echo TrazabilidadHelpers::imprimirIntervalo("📈 Tiempo entre biometría realizada (punto 2) y cirugía efectuada (punto 5)", $biometriaRealizada, $fechaCirugiaEfectuada);
    }
}
?>
</body>
</html>