<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Cirugias\Http\Controllers\CirugiasReadController;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

class CirugiasDatatableDiagnose extends Command
{
    protected $signature = 'cirugias:diagnose-datatable
                            {--desde= : Fecha inicio YYYY-MM-DD (default: últimos 90 días)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--rows=5  : Cuántas filas de muestra mostrar}';

    protected $description = '[DIAGNÓSTICO] Simula /v2/cirugias/datatable y muestra conteos, muestra de filas y distribución de tabs React.';

    public function handle(): int
    {
        $desde = $this->option('desde') ?: now()->subDays(90)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sampleSize = max(1, (int) $this->option('rows'));

        $this->info("Simulando /v2/cirugias/datatable  [{$desde} → {$hasta}]");
        $this->line('');

        // ── Simular el request que React envía ──────────────────────────────
        $request = Request::create('/v2/cirugias/datatable', 'POST', [
            'draw'              => '1',
            'start'             => '0',
            'length'            => '500',
            'search'            => ['value' => ''],
            'order'             => [['column' => '4', 'dir' => 'desc']],
            'fecha_inicio'      => $desde,
            'fecha_fin'         => $hasta,
            'afiliacion'        => '',
            'afiliacion_categoria' => '',
            'sede'              => '',
        ]);

        // ── Llamar el controller directamente ───────────────────────────────
        $controller = new CirugiasReadController();

        // El controller verifica Auth::check(); lo saltamos inyectando el guard
        // de forma temporal para diagnóstico (solo lectura, sin modificar datos).
        \Illuminate\Support\Facades\Auth::shouldUse('web');

        try {
            $response = $controller->datatable($request);
        } catch (\Throwable $e) {
            $this->error('ERROR al llamar datatable(): ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        $payload = json_decode($response->getContent(), true);

        if (!empty($payload['error'])) {
            $this->error('El endpoint retornó error: ' . $payload['error']);
            return self::FAILURE;
        }

        $rows   = $payload['data'] ?? [];
        $total  = $payload['recordsTotal'] ?? 0;
        $filtered = $payload['recordsFiltered'] ?? 0;

        // ── 1. Totales ───────────────────────────────────────────────────────
        $this->info("══ 1. TOTALES ══════════════════════════════════════════");
        $this->table(['Campo', 'Valor'], [
            ['recordsTotal',    $total],
            ['recordsFiltered', $filtered],
            ['rows en payload', count($rows)],
            ['Rango de fechas', "{$desde} → {$hasta}"],
        ]);

        // ── 2. Muestra de filas ──────────────────────────────────────────────
        $this->line('');
        $this->info("══ 2. PRIMERAS {$sampleSize} FILAS (campos clave) ══════════════════");

        $FIELDS = ['form_id','hc_number','full_name','fecha_inicio',
                   'audit_status','alertas_count','status','protocolo_iniciado',
                   'lateralidad','afiliacion_label'];

        $sample = array_slice($rows, 0, $sampleSize);
        if (empty($sample)) {
            $this->warn('No hay filas en el payload.');
        } else {
            $tableRows = [];
            foreach ($sample as $row) {
                $tableRows[] = [
                    $row['form_id']           ?? '—',
                    $row['hc_number']         ?? '—',
                    mb_substr((string)($row['full_name'] ?? '—'), 0, 20),
                    $row['fecha_inicio']      ?? '—',
                    $row['audit_status']      ?? 'AUSENTE',
                    $row['alertas_count']     ?? 'AUSENTE',
                    isset($row['status'])          ? $row['status']          : 'AUSENTE',
                    isset($row['protocolo_iniciado']) ? ($row['protocolo_iniciado'] ? 'true' : 'false') : 'AUSENTE',
                    $row['lateralidad']       ?? 'AUSENTE',
                    mb_substr((string)($row['afiliacion_label'] ?? 'AUSENTE'), 0, 15),
                ];
            }
            $this->table(
                ['form_id','hc_number','nombre','fecha','audit_status','alertas','status','prot_inic','lat','afiliacion'],
                $tableRows
            );
        }

        // ── 3. Distribución por audit_status ────────────────────────────────
        $this->line('');
        $this->info("══ 3. DISTRIBUCIÓN POR audit_status ════════════════════");

        $dist = ['conforme' => 0, 'por_revisar' => 0, 'alertas' => 0, 'sin_protocolo' => 0, 'AUSENTE' => 0];
        foreach ($rows as $row) {
            $as = $row['audit_status'] ?? null;
            if ($as === null) {
                $dist['AUSENTE']++;
            } elseif (isset($dist[$as])) {
                $dist[$as]++;
            } else {
                $dist[$as] = ($dist[$as] ?? 0) + 1;
            }
        }
        $distTable = [];
        foreach ($dist as $key => $count) {
            $distTable[] = [$key, $count, $count > 0 ? str_repeat('█', min(40, $count)) : ''];
        }
        $this->table(['audit_status', 'count', 'bar'], $distTable);

        // ── 4. Simulación de tabs React ──────────────────────────────────────
        $this->line('');
        $this->info("══ 4. SIMULACIÓN TABS REACT (misma lógica que normaliseRow + inTab) ══");

        $tabs = ['revisados' => 0, 'sin-protocolo' => 0, 'auditoria' => 0, 'por-revisar' => 0];
        $campoAusentCount = 0;

        foreach ($rows as $row) {
            $auditStatus = $row['audit_status'] ?? null;

            if ($auditStatus === null) {
                $campoAusentCount++;
                continue;
            }

            // Reproduce exactamente normaliseRow() de app.jsx
            $status           = $auditStatus === 'conforme' ? 1 : 0;
            $protocoloIniciado = $auditStatus !== 'sin_protocolo';
            $alertasCount     = (int) ($row['alertas_count'] ?? 0);

            if ($auditStatus === 'conforme') {
                $auditObjStatus = 'ok';
            } elseif ($auditStatus === 'alertas') {
                $auditObjStatus = 'error';
            } elseif ($auditStatus === 'por_revisar') {
                $auditObjStatus = 'warning';
            } else {
                $auditObjStatus = null; // sin_protocolo → audit = null
            }

            // inTab() de app.jsx
            if ($status === 1) {
                $tabs['revisados']++;
            } elseif ($status === 0 && !$protocoloIniciado) {
                $tabs['sin-protocolo']++;
            } elseif ($status === 0 && $protocoloIniciado && $auditObjStatus === 'error') {
                $tabs['auditoria']++;
            } else {
                $tabs['por-revisar']++;
            }
        }

        $tabTable = [];
        foreach ($tabs as $tab => $count) {
            $tabTable[] = [$tab, $count, $count > 0 ? str_repeat('█', min(40, $count)) : ''];
        }
        $this->table(['Tab', 'Filas', 'bar'], $tabTable);

        if ($campoAusentCount > 0) {
            $this->warn("{$campoAusentCount} filas sin campo 'audit_status' — no clasificadas en ninguna tab.");
        }

        $tabSum = array_sum($tabs);
        $this->line('');
        $this->line("Total clasificadas en tabs: {$tabSum} / " . count($rows) . " rows del payload");

        // ── 5. Verificar campos presentes en primera fila ────────────────────
        $this->line('');
        $this->info("══ 5. CAMPOS PRESENTES EN PRIMERA FILA ═════════════════");
        if (!empty($rows)) {
            $first = $rows[0];
            $check = [];
            foreach ($FIELDS as $f) {
                $check[] = [$f, array_key_exists($f, $first) ? '✓ presente' : '✗ AUSENTE', array_key_exists($f, $first) ? (is_null($first[$f]) ? 'null' : substr((string)$first[$f], 0, 40)) : ''];
            }
            $this->table(['campo', 'estado', 'valor'], $check);
        }

        $this->line('');
        $this->info('Diagnóstico completado.');
        return self::SUCCESS;
    }
}
