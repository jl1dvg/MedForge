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
 * Ejecuta getTopDoctoresSolicitudesRealizadas() de forma real (sin tocar su
 * lógica ni su SQL), captura el texto EXACTO de la consulta que PDO terminó
 * preparando ($stmt->queryString) y los bindings realmente usados, y corre
 * sobre ESE mismo texto:
 *
 *   1) EXPLAIN FORMAT=JSON <consulta real capturada>
 *   2) SHOW INDEX FROM <tabla> para cada tabla real referenciada en el FROM/
 *      JOIN de esa consulta (las derived tables entre paréntesis se ignoran,
 *      solo interesan tablas físicas).
 *
 * No reconstruye el SQL a mano — usa literalmente lo que
 * CirugiasDashboardService::getTopDoctoresSolicitudesRealizadas() manda a
 * PDO en producción. La captura está gateada por un flag apagado por
 * defecto (CirugiasDashboardService::enableDiagCapture()); no afecta la
 * ejecución real del reporte.
 *
 * Eliminar junto con el resto de la instrumentación TEMP-DIAG en
 * CirugiasDashboardService al cerrar el diagnóstico de rendimiento.
 *
 * Uso en staging:
 *   /usr/bin/php8.3-cli artisan cirugias:explain-top-doctores --desde=2026-06-01 --hasta=2026-06-30
 * ============================================================================
 */
class CirugiasExplainTopDoctores extends Command
{
    protected $signature = 'cirugias:explain-top-doctores
                            {--desde= : Fecha inicio YYYY-MM-DD (default: 30 días atrás)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--sede= : Filtro de sede exacto (default: vacío = todas)}
                            {--limit=10 : Top N doctores}';

    protected $description = '[DIAGNÓSTICO] Captura el SQL real de getTopDoctoresSolicitudesRealizadas() y corre EXPLAIN FORMAT=JSON + SHOW INDEX sobre él.';

    public function handle(CirugiasDashboardService $service): int
    {
        $desde = $this->option('desde') ?: now()->subDays(30)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sede = (string) ($this->option('sede') ?? '');
        $limit = (int) $this->option('limit');

        $this->info('Capturando SQL real de getTopDoctoresSolicitudesRealizadas()');
        $this->line("  Rango:  {$desde} → {$hasta}");
        $this->line("  Sede:   " . ($sede === '' ? '(todas)' : $sede));
        $this->line('');

        CirugiasDashboardService::getResetDiagCapturedQuery(); // limpia residuos previos
        CirugiasDashboardService::enableDiagCapture();

        try {
            // Ejecución real, sin cambios: el resultado no se usa aquí, solo
            // nos interesa que el método corra su flujo normal para capturar
            // el SQL/bindings exactos que produce.
            $service->getTopDoctoresSolicitudesRealizadas($desde, $hasta, $limit, '', '', $sede);
        } catch (Throwable $e) {
            $this->error('getTopDoctoresSolicitudesRealizadas() lanzó una excepción: ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        $captured = CirugiasDashboardService::getResetDiagCapturedQuery();

        if ($captured === null) {
            $this->error('No se capturó ninguna consulta (¿el método retornó antes de llegar al prepare()? revisar tableExists(solicitud_crm_meta)).');
            return self::FAILURE;
        }

        $sql = $captured['sql'];
        $bindings = $captured['bindings'];

        $this->line('SQL real capturado ($stmt->queryString):');
        $this->line('');
        $this->line($sql);
        $this->line('');

        $pdo = DB::connection()->getPdo();

        // --- EXPLAIN FORMAT=JSON sobre el SQL real, mismos bindings -----
        $this->info('EXPLAIN FORMAT=JSON (consulta real, mismos bindings):');
        $this->line('');
        try {
            $explainStmt = $pdo->prepare('EXPLAIN FORMAT=JSON ' . $sql);
            foreach ($bindings as $key => $value) {
                $explainStmt->bindValue($key, $value, is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR);
            }
            $explainStmt->execute();
            $explainJson = $explainStmt->fetchColumn();
            $this->line((string) $explainJson);
        } catch (Throwable $e) {
            $this->error('EXPLAIN falló: ' . $e->getMessage());
        }
        $this->line('');

        // --- SHOW INDEX de cada tabla física referenciada en FROM/JOIN --
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

        return self::SUCCESS;
    }

    /**
     * Extrae nombres de tabla física de un FROM/JOIN, ignorando derived
     * tables entre paréntesis (después de FROM/JOIN debe seguir un
     * identificador, no un paréntesis de subconsulta).
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
