<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Cirugias\Services\CirugiasDashboardService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * ============================================================================
 *  ⚠️  INSTRUMENTACIÓN TEMPORAL DE DIAGNÓSTICO — ELIMINAR AL CERRAR EL CASO ⚠️
 * ============================================================================
 * Perfila getProgramacionKpis() con datos reales:
 *   1) Ejecuta el método tal cual (sin tocar su lógica) y mide su tiempo total.
 *   2) Captura el SQL final exacto ($stmt->queryString) y los bindings reales.
 *   3) Corre EXPLAIN FORMAT=JSON sobre ese mismo texto y bindings.
 *   4) SHOW INDEX de cada tabla física referenciada.
 *
 * La captura está gateada por CirugiasDashboardService::enableDiagCapture()
 * (apagado por defecto) — el reporte en producción no ejecuta nada extra.
 *
 * Eliminar junto con el resto de la instrumentación TEMP-DIAG al cerrar el
 * diagnóstico de rendimiento.
 *
 * Uso en staging:
 *   /usr/bin/php8.3-cli artisan cirugias:profile-programacion-kpis --desde=2026-06-01 --hasta=2026-06-30
 * ============================================================================
 */
class CirugiasProfileProgramacionKpis extends Command
{
    protected $signature = 'cirugias:profile-programacion-kpis
                            {--desde= : Fecha inicio YYYY-MM-DD (default: 30 días atrás)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--sede= : Filtro de sede exacto (default: vacío = todas)}
                            {--skip-explain : Solo medir tiempo, sin EXPLAIN ni SHOW INDEX}';

    protected $description = '[DIAGNÓSTICO] Mide getProgramacionKpis() y corre EXPLAIN FORMAT=JSON + SHOW INDEX sobre su SQL real.';

    public function handle(CirugiasDashboardService $service): int
    {
        $desde = $this->option('desde') ?: now()->subDays(30)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sede = (string) ($this->option('sede') ?? '');

        $this->info('Perfilando getProgramacionKpis()');
        $this->line("  Rango:  {$desde} → {$hasta}");
        $this->line("  Sede:   " . ($sede === '' ? '(todas)' : $sede));
        $this->line('');

        CirugiasDashboardService::getResetDiagCapturedQuery(); // limpia residuos previos
        CirugiasDashboardService::enableDiagCapture();

        $t0 = microtime(true);
        try {
            $kpis = $service->getProgramacionKpis($desde, $hasta, '', '', $sede);
        } catch (Throwable $e) {
            $this->error('getProgramacionKpis() lanzó una excepción: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }
        $total = microtime(true) - $t0;

        $this->line(sprintf('  Tiempo total getProgramacionKpis(): %.2f s', $total));
        $this->line(sprintf('  programadas=%d realizadas=%d', (int) ($kpis['programadas'] ?? 0), (int) ($kpis['realizadas'] ?? 0)));
        $this->line('');

        $captured = CirugiasDashboardService::getResetDiagCapturedQuery();

        if ($captured === null) {
            $this->error('No se capturó ninguna consulta (¿retornó antes del prepare()? revisar tableExists(solicitud_crm_meta) o rows vacíos no aplica — la captura ocurre antes del execute).');
            return self::FAILURE;
        }

        if ($this->option('skip-explain')) {
            return self::SUCCESS;
        }

        $sql = $captured['sql'];
        $bindings = $captured['bindings'];

        $this->line('SQL real capturado ($stmt->queryString):');
        $this->line('');
        $this->line($sql);
        $this->line('');

        $pdo = DB::connection()->getPdo();

        $this->info('EXPLAIN FORMAT=JSON (consulta real, mismos bindings):');
        $this->line('');
        try {
            $explainStmt = $pdo->prepare('EXPLAIN FORMAT=JSON ' . $sql);
            foreach ($bindings as $key => $value) {
                $explainStmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $explainStmt->execute();
            $this->line((string) $explainStmt->fetchColumn());
        } catch (Throwable $e) {
            $this->error('EXPLAIN falló: ' . $e->getMessage());
        }
        $this->line('');

        $tables = $this->extractRealTableNames($sql);
        $this->info('Tablas físicas detectadas en la consulta: ' . implode(', ', $tables));
        $this->line('');

        foreach ($tables as $table) {
            $this->line("SHOW INDEX FROM {$table}:");
            try {
                $rows = $pdo->query("SHOW INDEX FROM `{$table}`")->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    $this->line(sprintf(
                        '  %-12s seq=%-3s col=%-25s unique=%-5s type=%s',
                        $row['Key_name'] ?? '',
                        $row['Seq_in_index'] ?? '',
                        $row['Column_name'] ?? '',
                        ($row['Non_unique'] ?? 1) == 0 ? 'yes' : 'no',
                        $row['Index_type'] ?? ''
                    ));
                }
            } catch (Throwable $e) {
                $this->error("  No se pudo obtener SHOW INDEX de {$table}: " . $e->getMessage());
            }
            $this->line('');
        }

        // Tipos/collations de las columnas involucradas en el join pd/meta —
        // necesarios para decidir si el CAST(form_id AS CHAR) de la derived
        // table pd también puede eliminarse en una siguiente PR.
        $this->info('Tipos de columnas del join pd/meta (para evaluar el CAST de protocolo_data.form_id):');
        try {
            $colStmt = $pdo->query(
                "SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, CHARACTER_SET_NAME, COLLATION_NAME
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND ((TABLE_NAME = 'protocolo_data' AND COLUMN_NAME = 'form_id')
                     OR (TABLE_NAME = 'solicitud_crm_meta' AND COLUMN_NAME = 'meta_value'))"
            );
            foreach ($colStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
                $this->line(sprintf(
                    '  %s.%s: %s / %s / %s',
                    $row['TABLE_NAME'],
                    $row['COLUMN_NAME'],
                    $row['DATA_TYPE'],
                    $row['CHARACTER_SET_NAME'] ?? '(sin charset)',
                    $row['COLLATION_NAME'] ?? '(sin collation)'
                ));
            }
        } catch (Throwable $e) {
            $this->error('  No se pudieron obtener los tipos: ' . $e->getMessage());
        }
        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Extrae nombres de tabla física de un FROM/JOIN, ignorando derived
     * tables entre paréntesis.
     *
     * @return array<int,string>
     */
    private function extractRealTableNames(string $sql): array
    {
        preg_match_all('/\b(?:FROM|JOIN)\s+([a-zA-Z_][a-zA-Z0-9_]*)/', $sql, $matches);

        $tables = array_unique($matches[1] ?? []);
        sort($tables);

        return $tables;
    }
}
