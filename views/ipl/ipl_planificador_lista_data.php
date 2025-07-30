<?php
if (isset($scraperResponse['fecha_registro']) && isset($scraperResponse['fecha_vigencia']) && $scraperResponse['fecha_registro'] && $scraperResponse['fecha_vigencia']) {
    $fechaRegistro = new DateTime($scraperResponse['fecha_registro']);
    $fechaVigencia = new DateTime($scraperResponse['fecha_vigencia']);

    echo "<div class='mt-3'><h5>🧾 Planificación de sesiones IPL simuladas:</h5>";
    $interval = new DateInterval('P1M');
    $fecha_inicio = clone $fechaRegistro;
    echo "<ul>";
    $contador = 1;
    while ($fecha_inicio <= $fechaVigencia) {
        $fecha_ideal = clone $fecha_inicio;

        // Humanizar: sumar 0 a 2 días aleatorios
        $dias_extra = rand(0, 2);
        $fecha_ideal->modify("+{$dias_extra} days");

        // Evitar sábado (6) y domingo (7)
        while ((int)$fecha_ideal->format('N') >= 6) {
            $fecha_ideal->modify('+1 day');
        }

        echo "<li>Sesión IPL {$contador}: " . $fecha_ideal->format('d F Y') . "</li>";
        $contador++;

        // Siguiente mes desde la fecha original + N meses
        $fecha_inicio->modify('first day of next month');
    }
    echo "</ul>";
    echo "<p class='text-muted'>📌 Observación: Fechas generadas automáticamente para facturación mensual dentro del periodo de vigencia, humanizadas y evitando sábados y domingos.</p></div>";
}

?>