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
 * Este comando y los timers TEMP-DIAG agregados dentro de
 * CirugiasDashboardService::buildReportPayload() (propiedad estática
 * $diagTimings y su getter getResetDiagTimings()) existen únicamente para
 * medir cuánto tiempo real consume cada método interno de buildReportPayload().
 * No cambian SQL ni lógica de negocio — solo miden con microtime() alrededor
 * de cada llamada ya existente.
 *
 * Cuando el diagnóstico de rendimiento esté cerrado, eliminar:
 *   - este archivo completo,
 *   - la propiedad self::$diagTimings y el método getResetDiagTimings() en
 *     CirugiasDashboardService,
 *   - las líneas marcadas "// TEMP-DIAG" dentro de buildReportPayload().
 *
 * Uso en staging:
 *   /usr/bin/php8.3-cli artisan cirugias:profile-report --desde=2026-06-01 --hasta=2026-06-30 --sede=""
 * ============================================================================
 */
class CirugiasProfileReportPayload extends Command
{
    protected $signature = 'cirugias:profile-report
                            {--desde= : Fecha inicio YYYY-MM-DD (default: 30 días atrás)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--sede= : Filtro de sede exacto usado por el reporte (default: vacío = todas)}';

    protected $description = '[DIAGNÓSTICO] Mide el tiempo real por método dentro de CirugiasDashboardService::buildReportPayload().';

    public function handle(CirugiasDashboardService $service): int
    {
        $desde = $this->option('desde') ?: now()->subDays(30)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sede = (string) ($this->option('sede') ?? '');

        $this->info('Perfilando buildReportPayload()');
        $this->line("  Rango:  {$desde} → {$hasta}");
        $this->line("  Sede:   " . ($sede === '' ? '(todas)' : $sede));
        $this->line('');

        CirugiasDashboardService::getResetDiagTimings(); // limpia residuos de una corrida previa en el mismo proceso

        $t0 = microtime(true);
        try {
            $service->buildReportPayload($desde, $hasta, $sede);
        } catch (Throwable $e) {
            $this->error('buildReportPayload() lanzó una excepción: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
        $total = microtime(true) - $t0;

        $timings = CirugiasDashboardService::getResetDiagTimings();
        arsort($timings);

        $this->line('Tiempo por método (mayor a menor):');
        $this->line('');
        foreach ($timings as $method => $seconds) {
            $pct = $total > 0 ? ($seconds / $total * 100) : 0;
            $this->line(sprintf('  %-45s %8.1f s   (%5.1f%%)', $method . '()', $seconds, $pct));
        }

        $sumMedido = array_sum($timings);
        $overhead = $total - $sumMedido;

        $this->line('');
        $this->line(sprintf('  %-45s %8.1f s', 'TOTAL buildReportPayload()', $total));
        $this->line(sprintf('  %-45s %8.1f s   (composición de payload / PHP puro)', 'overhead no medido', $overhead));
        $this->line('');

        return self::SUCCESS;
    }
}
