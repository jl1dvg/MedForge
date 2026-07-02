<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Cirugias\Services\CirugiasDashboardService;
use App\Modules\Shared\Support\AfiliacionDimensionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;
use ReflectionClass;
use Throwable;

/**
 * [DIAGNÓSTICO — Fase 0+1] Compara, en el mismo proceso y contra los mismos
 * datos, el payload del Reporte Ejecutivo de Cirugías generado por:
 *
 *   A) el comportamiento PRE-migración: CirugiasDashboardService construido
 *      saltando el constructor nuevo (Reflection) e inyectando un PDO crudo
 *      directamente en la propiedad $db — exactamente como se obtenía antes
 *      de que el constructor pidiera ConnectionInterface.
 *   B) el comportamiento POST-migración: CirugiasDashboardService resuelto
 *      por el Service Container (app(...)), el código tal como quedó en el PR.
 *
 * Como el diff de la migración solo tocó el constructor (ver PR de Fase 0+1),
 * A y B ejecutan literalmente los mismos métodos, el mismo SQL, contra la
 * misma conexión — si hay una sola diferencia en el payload, es evidencia de
 * un bug real introducido por la migración (o de un cambio de datos entre
 * ambas invocaciones, que es por qué corren en el mismo proceso, sin pausa).
 *
 * Uso en staging, ANTES de mergear a main:
 *   php artisan cirugias:verify-report-migration --desde=2026-06-01 --hasta=2026-06-30 --sede=""
 *
 * Este comando es temporal — eliminar después de confirmar la migración.
 */
class CirugiasVerifyReportMigration extends Command
{
    protected $signature = 'cirugias:verify-report-migration
                            {--desde= : Fecha inicio YYYY-MM-DD (default: 30 días atrás)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--sede= : Filtro de sede exacto usado por el reporte (default: vacío = todas)}';

    protected $description = '[DIAGNÓSTICO] Compara el payload del Reporte Ejecutivo de Cirugías pre/post migración PDO->ConnectionInterface, en el mismo proceso y contra los mismos datos.';

    public function handle(): int
    {
        $desde = $this->option('desde') ?: now()->subDays(30)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sede = (string) ($this->option('sede') ?? '');

        $this->info("Comparando payload del Reporte Ejecutivo de Cirugías");
        $this->line("  Rango:  {$desde} → {$hasta}");
        $this->line("  Sede:   " . ($sede === '' ? '(todas)' : $sede));
        $this->line('');
        $this->flushOutput();

        // --- [1/6] Construir instancias (legacy bypass + container) ---------
        $stageStart = $this->stageStart(0, 'Preparando instancias (legacy bypass + container)');
        try {
            $pdo = DB::connection()->getPdo();

            // A) Comportamiento PRE-migración: bypass del constructor nuevo,
            //    inyección directa de PDO crudo — simula new CirugiasDashboardService($pdo).
            $before = $this->buildLegacyStyleInstance($pdo);

            // B) Comportamiento POST-migración: resuelto por el container.
            $after = app(CirugiasDashboardService::class);
        } catch (Throwable $e) {
            $this->stageFailed(0, $stageStart, $e->getMessage());
            $this->error('No se pudo preparar la comparación: ' . $e->getMessage());
            return self::FAILURE;
        }
        $this->stageDone(0, $stageStart);

        // --- [1/6] Construyendo payload legacy -------------------------------
        $stageStart = $this->stageStart(1, 'Construyendo payload legacy...');
        try {
            $t0 = microtime(true);
            $payloadBefore = $before->buildReportPayload($desde, $hasta, $sede);
            $tBefore = microtime(true) - $t0;
        } catch (Throwable $e) {
            $this->stageFailed(1, $stageStart, $e->getMessage());
            $this->error('buildReportPayload() [legacy] lanzó una excepción: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
        $this->stageDone(1, $stageStart);
        $this->line(sprintf('      buildReportPayload() [legacy]: %.3fs', $tBefore));
        $this->flushOutput();

        // --- [2/6] Construyendo payload container ----------------------------
        $stageStart = $this->stageStart(2, 'Construyendo payload container...');
        try {
            $t1 = microtime(true);
            $payloadAfter = $after->buildReportPayload($desde, $hasta, $sede);
            $tAfter = microtime(true) - $t1;
        } catch (Throwable $e) {
            $this->stageFailed(2, $stageStart, $e->getMessage());
            $this->error('buildReportPayload() [container] lanzó una excepción: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
        $this->stageDone(2, $stageStart);
        $this->line(sprintf('      buildReportPayload() [container]: %.3fs', $tAfter));
        $this->line('');
        $this->flushOutput();

        // El diff se calcula una sola vez sobre el payload completo (misma
        // lógica de siempre); las etapas 3-5 solo agrupan y muestran ese
        // resultado ya calculado — no vuelven a comparar nada distinto.
        $diffs = $this->diffPayloads($payloadBefore, $payloadAfter);

        $kpiChecks = [
            'metrics' => 'KPIs',
            'synth'   => 'Totales (synth)',
        ];
        $seriesChecks = [
            'produccionMensual' => 'Series (producción mensual)',
            'trazabilidad'      => 'Series (trazabilidad)',
        ];
        $tableChecks = [
            'topProcedimientos' => 'Tabla: top procedimientos',
            'topCirujanos'      => 'Tabla: top cirujanos',
            'topSolicitantes'   => 'Tabla: top solicitantes',
            'porConvenio'       => 'Tabla: por convenio',
        ];

        $allOk = true;

        // --- [3/6] Comparando KPIs -------------------------------------------
        $stageStart = $this->stageStart(3, 'Comparando KPIs...');
        $allOk = $this->printChecks($kpiChecks, $diffs, $payloadBefore) && $allOk;
        $this->stageDone(3, $stageStart);
        $this->flushOutput();

        // --- [4/6] Comparando series -------------------------------------------
        $stageStart = $this->stageStart(4, 'Comparando series...');
        $allOk = $this->printChecks($seriesChecks, $diffs, $payloadBefore) && $allOk;
        $this->stageDone(4, $stageStart);
        $this->flushOutput();

        // --- [5/6] Comparando tablas -------------------------------------------
        $stageStart = $this->stageStart(5, 'Comparando tablas...');
        $allOk = $this->printChecks($tableChecks, $diffs, $payloadBefore) && $allOk;
        $this->stageDone(5, $stageStart);
        $this->flushOutput();

        // --- [6/6] Finalizado ---------------------------------------------------
        $stageStart = $this->stageStart(6, 'Finalizado.');
        $this->line('');

        if ($allOk && $diffs === []) {
            $this->info('RESULTADO: payload idéntico. Migración verificada sin diferencias funcionales.');
            $this->stageDone(6, $stageStart);
            return self::SUCCESS;
        }

        $this->error('RESULTADO: se encontraron diferencias. NO mergear hasta investigar (ver detalle arriba).');
        $this->line('');
        $this->line('Diff completo (primeras 30 rutas):');
        foreach (array_slice($diffs, 0, 30) as $path) {
            $this->line("  {$path}");
        }
        $this->stageDone(6, $stageStart);

        return self::FAILURE;
    }

    /**
     * Imprime el resultado ✓/✗ de un subconjunto de $checks contra el diff ya
     * calculado. No recalcula nada — solo agrupa y muestra. Devuelve false si
     * encontró alguna diferencia dentro de este grupo.
     *
     * @param array<string,string> $checks
     * @param array<int,string> $diffs
     */
    private function printChecks(array $checks, array $diffs, array $payloadBefore): bool
    {
        $groupOk = true;

        foreach ($checks as $key => $label) {
            $keyDiffs = array_filter($diffs, fn (string $path) => str_starts_with($path, $key));
            if ($keyDiffs === []) {
                $countBefore = is_array($payloadBefore[$key] ?? null) && array_is_list($payloadBefore[$key])
                    ? count($payloadBefore[$key]) : null;
                $suffix = $countBefore !== null ? " ({$countBefore} registros)" : '';
                $this->info("      ✓ {$label} — idénticos{$suffix}");
            } else {
                $groupOk = false;
                $this->error("      ✗ {$label} — " . count($keyDiffs) . ' diferencia(s)');
                foreach (array_slice($keyDiffs, 0, 5) as $path) {
                    $this->line("          {$path}");
                }
            }
        }

        return $groupOk;
    }

    /**
     * Imprime "[n/6] {label}" y devuelve el timestamp de inicio para medir
     * cuánto tarda la etapa. n=0 se usa para la preparación previa a [1/6]
     * y no cuenta en el numerador visible al usuario.
     */
    private function stageStart(int $n, string $label): float
    {
        $prefix = $n === 0 ? '[prep]' : "[{$n}/6]";
        $this->line("{$prefix} {$label}");
        $this->flushOutput();

        return microtime(true);
    }

    private function stageDone(int $n, float $start): void
    {
        $elapsed = microtime(true) - $start;
        $prefix = $n === 0 ? '[prep]' : "[{$n}/6]";
        $this->line(sprintf('%s OK (%.3f s)', $prefix, $elapsed));
        $this->line('');
        $this->flushOutput();
    }

    private function stageFailed(int $n, float $start, string $reason): void
    {
        $elapsed = microtime(true) - $start;
        $prefix = $n === 0 ? '[prep]' : "[{$n}/6]";
        $this->line(sprintf('%s FALLÓ tras %.3f s: %s', $prefix, $elapsed, $reason));
        $this->line('');
        $this->flushOutput();
    }

    /**
     * Fuerza el vaciado del buffer de salida. Necesario para ver el progreso
     * en tiempo real cuando el proceso queda colgado esperando una operación
     * externa (ej. una query MySQL) — sin esto, el output puede quedar
     * retenido en el buffer del stream y no llegar a la terminal hasta que
     * el proceso termine (o nunca, si hay que matarlo a mano).
     */
    private function flushOutput(): void
    {
        @flush();

        if (defined('STDOUT') && is_resource(STDOUT)) {
            @fflush(STDOUT);
        }
    }

    private function buildLegacyStyleInstance(PDO $pdo): CirugiasDashboardService
    {
        $reflection = new ReflectionClass(CirugiasDashboardService::class);
        /** @var CirugiasDashboardService $instance */
        $instance = $reflection->newInstanceWithoutConstructor();

        $dbProp = $reflection->getProperty('db');
        $dbProp->setAccessible(true);
        $dbProp->setValue($instance, $pdo);

        $afiliacionProp = $reflection->getProperty('afiliacionDimensions');
        $afiliacionProp->setAccessible(true);
        $afiliacionProp->setValue($instance, app(AfiliacionDimensionService::class));

        return $instance;
    }

    /**
     * @return array<int,string> rutas (dot-notation) donde el payload difiere
     */
    private function diffPayloads(array $before, array $after, string $prefix = ''): array
    {
        $diffs = [];
        $keys = array_unique(array_merge(array_keys($before), array_keys($after)));

        foreach ($keys as $key) {
            $path = $prefix === '' ? (string) $key : "{$prefix}.{$key}";

            if (!array_key_exists($key, $before)) {
                $diffs[] = "{$path}: falta en payload PRE-migración";
                continue;
            }
            if (!array_key_exists($key, $after)) {
                $diffs[] = "{$path}: falta en payload POST-migración";
                continue;
            }

            $valBefore = $before[$key];
            $valAfter = $after[$key];

            // generatedAt siempre difiere (now()) — se excluye explícitamente
            if ($key === 'generatedAt') {
                continue;
            }

            if (is_array($valBefore) && is_array($valAfter)) {
                $diffs = array_merge($diffs, $this->diffPayloads($valBefore, $valAfter, $path));
                continue;
            }

            if ($valBefore !== $valAfter) {
                $diffs[] = "{$path}: PRE=" . json_encode($valBefore) . ' POST=' . json_encode($valAfter);
            }
        }

        return $diffs;
    }
}
