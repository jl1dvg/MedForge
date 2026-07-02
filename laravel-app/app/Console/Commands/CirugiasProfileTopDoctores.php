<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Cirugias\Services\CirugiasDashboardService;
use Illuminate\Console\Command;
use Throwable;

/**
 * ============================================================================
 *  ⚠️  INSTRUMENTACIÓN TEMPORAL DE DIAGNÓSTICO — ELIMINAR AL CERRAR EL CASO ⚠️
 * ============================================================================
 * Mide el tiempo de cada bloque interno de
 * CirugiasDashboardService::getTopDoctoresSolicitudesRealizadas():
 *   1) pp (procedimiento_proyectado agrupado por form_id), sola
 *   2) pp_sede_agg (derived table de sede), sola
 *   3) meta (solicitud_crm_meta agrupado por solicitud_id), sola
 *   4) base con todos los joins + filtro de fecha, sin el INNER JOIN a meta
 *   5) base INNER JOIN meta, sin GROUP BY/ORDER BY/LIMIT final
 *   6) la query real completa (GROUP BY + ORDER BY + LIMIT), tal como corre hoy
 *
 * Los bloques 1-5 son queries adicionales de solo lectura (COUNT) que NO
 * participan en el resultado real — activadas únicamente por
 * CirugiasDashboardService::enableDiagBlockTiming(), apagado por defecto.
 * La query real (bloque 6) es exactamente la misma que corre siempre; no se
 * modificó ni un carácter de su SQL.
 *
 * Eliminar junto con la instrumentación TEMP-DIAG en CirugiasDashboardService
 * cuando cierre el diagnóstico de rendimiento.
 *
 * Uso en staging:
 *   /usr/bin/php8.3-cli artisan cirugias:profile-top-doctores --desde=2026-06-01 --hasta=2026-06-30
 * ============================================================================
 */
class CirugiasProfileTopDoctores extends Command
{
    protected $signature = 'cirugias:profile-top-doctores
                            {--desde= : Fecha inicio YYYY-MM-DD (default: 30 días atrás)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--sede= : Filtro de sede exacto (default: vacío = todas)}
                            {--limit=10 : Top N doctores}';

    protected $description = '[DIAGNÓSTICO] Mide el tiempo por bloque interno (derived tables, joins, query final) de getTopDoctoresSolicitudesRealizadas().';

    public function handle(CirugiasDashboardService $service): int
    {
        $desde = $this->option('desde') ?: now()->subDays(30)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sede = (string) ($this->option('sede') ?? '');
        $limit = (int) $this->option('limit');

        $this->info('Perfilando getTopDoctoresSolicitudesRealizadas() por bloques');
        $this->line("  Rango:  {$desde} → {$hasta}");
        $this->line("  Sede:   " . ($sede === '' ? '(todas)' : $sede));
        $this->line('');

        CirugiasDashboardService::getResetDiagBlockTimings(); // limpia residuos previos
        CirugiasDashboardService::enableDiagBlockTiming();

        $t0 = microtime(true);
        try {
            $service->getTopDoctoresSolicitudesRealizadas($desde, $hasta, $limit, '', '', $sede);
        } catch (Throwable $e) {
            $this->error('getTopDoctoresSolicitudesRealizadas() lanzó una excepción: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
        $total = microtime(true) - $t0;

        $timings = CirugiasDashboardService::getResetDiagBlockTimings();

        $labels = [
            '1_pp_doctor_alone'         => 'pp (procedimiento_proyectado agrupado por form_id), sola',
            '2_pp_sede_agg_alone'       => 'pp_sede_agg (derived table de sede), sola',
            '3_meta_alone'              => 'meta (solicitud_crm_meta agrupado por solicitud_id), sola',
            '4_base_con_joins_alone'    => 'base (sp + joins + filtro fecha), sin JOIN a meta',
            '5_base_join_meta_alone'    => 'base INNER JOIN meta, sin GROUP BY/ORDER BY/LIMIT',
            '6_query_completa_real'     => 'query real completa (GROUP BY + ORDER BY + LIMIT)',
        ];

        $this->line('Tiempo por bloque (orden de ejecución):');
        $this->line('');
        foreach ($labels as $key => $label) {
            if (!array_key_exists($key, $timings)) {
                $this->line(sprintf('  %-55s %s', $label, '(omitido — no aplica con este dataset)'));
                continue;
            }
            $seconds = $timings[$key];
            $filas = $timings["{$key}_filas"] ?? null;
            $pct = $total > 0 ? ($seconds / $total * 100) : 0;
            $filasTxt = $filas !== null ? sprintf(' | %s filas', number_format((int) $filas)) : '';
            $this->line(sprintf('  %-55s %8.2f s   (%5.1f%%)%s', $label, $seconds, $pct, $filasTxt));
        }

        $this->line('');
        $this->line(sprintf('  %-55s %8.2f s', 'TOTAL comando (incluye 1-6, secuencial)', $total));
        $this->line('');

        return self::SUCCESS;
    }
}
