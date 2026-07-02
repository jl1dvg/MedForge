<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\Cirugias\Services\CirugiaService;
use Illuminate\Console\Command;

class CirugiasDatatableDiagnose extends Command
{
    protected $signature = 'cirugias:diagnose-datatable
                            {--desde= : Fecha inicio YYYY-MM-DD (default: últimos 90 días)}
                            {--hasta= : Fecha fin YYYY-MM-DD (default: hoy)}
                            {--rows=5  : Cuántas filas de muestra mostrar}';

    protected $description = '[DIAGNÓSTICO] Simula /v2/cirugias/datatable y muestra conteos, muestra de filas y distribución de tabs React.';

    public function handle(CirugiaService $service): int
    {
        $desde = $this->option('desde') ?: now()->subDays(90)->format('Y-m-d');
        $hasta = $this->option('hasta') ?: now()->format('Y-m-d');
        $sampleSize = max(1, (int) $this->option('rows'));

        $this->info("Simulando /v2/cirugias/datatable  [{$desde} → {$hasta}]");
        $this->line('');

        try {
            $result = $service->obtenerCirugiasPaginadas(
                start: 0,
                length: 500,
                search: '',
                orderColumn: 'fecha_inicio',
                orderDir: 'DESC',
                filters: [
                    'fecha_inicio' => $desde,
                    'fecha_fin'    => $hasta,
                    'afiliacion'   => '',
                    'afiliacion_categoria' => '',
                    'sede'         => '',
                ]
            );
        } catch (\Throwable $e) {
            $this->error('ERROR al llamar obtenerCirugiasPaginadas(): ' . $e->getMessage());
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        $rawRows      = $result['data'] ?? [];
        $total        = $result['recordsTotal'] ?? 0;
        $filtered     = $result['recordsFiltered'] ?? 0;

        // ── Construir las filas tal como buildDatatableRow() en el controller ──
        // Reproduce la misma lógica para audit_status, lateralidad, etc.
        $rows = array_map(function (array $row): array {
            $estado      = $this->resolveEstado($row);
            $alertas     = (int) ($row['alertas_count'] ?? 0);
            $printed     = (int) ($row['printed'] ?? 0);

            $auditStatus = match ($estado) {
                'revisado'    => 'conforme',
                'no revisado' => 'por_revisar',
                default       => $alertas > 0 ? 'alertas' : 'por_revisar',
            };

            $lateralidadRaw = strtoupper(trim((string) ($row['lateralidad'] ?? '')));
            $lateralidad = match (true) {
                str_contains($lateralidadRaw, 'AMBOS') || $lateralidadRaw === 'AO' || str_contains($lateralidadRaw, 'BILATERAL') => 'AO',
                str_contains($lateralidadRaw, 'IZQUIERDO') || $lateralidadRaw === 'OI' || str_contains($lateralidadRaw, 'LEFT') => 'OI',
                str_contains($lateralidadRaw, 'DERECHO') || $lateralidadRaw === 'OD' || str_contains($lateralidadRaw, 'RIGHT') => 'OD',
                default => $lateralidadRaw,
            };

            $afiliacion = trim((string) ($row['afiliacion_label'] ?? $row['afiliacion'] ?? '')) ?: 'Sin convenio';

            $fechaRaw   = (string) ($row['fecha_inicio'] ?? '');
            $ts         = $fechaRaw !== '' ? strtotime($fechaRaw) : false;
            $fecha      = $ts ? date('d/m/Y', $ts) : $fechaRaw;

            return [
                'form_id'            => (string) ($row['form_id'] ?? ''),
                'hc_number'          => (string) ($row['hc_number'] ?? ''),
                'full_name'          => trim(implode(' ', array_filter([
                    $row['fname'] ?? '', $row['lname'] ?? '', $row['lname2'] ?? '',
                ]))),
                'fecha_inicio'       => $fecha,
                'audit_status'       => $auditStatus,
                'alertas_count'      => $alertas,
                'status'             => (int) ($row['status'] ?? 0),
                'protocolo_iniciado' => $auditStatus !== 'sin_protocolo',
                'lateralidad'        => $lateralidad,
                'afiliacion_label'   => $afiliacion,
                'afiliacion_categoria' => (string) ($row['afiliacion_categoria'] ?? ''),
                'sede'               => (string) ($row['sede'] ?? ''),
                'printed'            => $printed,
                'edad'               => $row['edad'] !== null ? (int) $row['edad'] : null,
            ];
        }, $rawRows);

        // ── 1. Totales ───────────────────────────────────────────────────────
        $this->info("══ 1. TOTALES ══════════════════════════════════════════");
        $this->table(['Campo', 'Valor'], [
            ['recordsTotal',    $total],
            ['recordsFiltered', $filtered],
            ['rows en payload', count($rows)],
            ['Rango',           "{$desde} → {$hasta}"],
        ]);

        // ── 2. Muestra de filas ──────────────────────────────────────────────
        $this->line('');
        $this->info("══ 2. PRIMERAS {$sampleSize} FILAS ══════════════════════════════════");

        $sample = array_slice($rows, 0, $sampleSize);
        if (empty($sample)) {
            $this->warn('No hay filas en el payload para el rango de fechas indicado.');
        } else {
            $tableRows = [];
            foreach ($sample as $r) {
                $tableRows[] = [
                    $r['form_id'],
                    $r['hc_number'],
                    mb_substr($r['full_name'], 0, 18),
                    $r['fecha_inicio'],
                    $r['audit_status'],
                    $r['alertas_count'],
                    $r['status'],
                    $r['protocolo_iniciado'] ? 'sí' : 'no',
                    $r['lateralidad'] ?: '—',
                    mb_substr($r['afiliacion_label'], 0, 12),
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
        $dist = ['conforme' => 0, 'por_revisar' => 0, 'alertas' => 0, 'sin_protocolo' => 0];
        foreach ($rows as $r) {
            $as = $r['audit_status'];
            $dist[$as] = ($dist[$as] ?? 0) + 1;
        }
        $distTable = [];
        foreach ($dist as $key => $count) {
            $distTable[] = [$key, $count, str_repeat('█', min(40, $count))];
        }
        $this->table(['audit_status', 'count', 'bar'], $distTable);

        // ── 4. Simulación de tabs React ──────────────────────────────────────
        $this->line('');
        $this->info("══ 4. SIMULACIÓN TABS REACT (lógica exacta de normaliseRow + inTab) ══");
        $tabs = ['revisados' => 0, 'sin-protocolo' => 0, 'auditoria/alertas' => 0, 'por-revisar' => 0];

        foreach ($rows as $r) {
            $auditStatus      = $r['audit_status'];
            $status           = $auditStatus === 'conforme' ? 1 : 0;
            $protocoloIniciado = $auditStatus !== 'sin_protocolo';
            $auditObjStatus   = match ($auditStatus) {
                'conforme'    => 'ok',
                'alertas'     => 'error',
                'por_revisar' => 'warning',
                default       => null,
            };

            if ($status === 1) {
                $tabs['revisados']++;
            } elseif (!$protocoloIniciado) {
                $tabs['sin-protocolo']++;
            } elseif ($protocoloIniciado && $auditObjStatus === 'error') {
                $tabs['auditoria/alertas']++;
            } else {
                $tabs['por-revisar']++;
            }
        }

        $tabTotal = array_sum($tabs);
        $tabTable = [];
        foreach ($tabs as $tab => $count) {
            $pct = $tabTotal > 0 ? round($count / $tabTotal * 100) : 0;
            $tabTable[] = [$tab, $count, "{$pct}%", str_repeat('█', min(40, $count))];
        }
        $this->table(['Tab', 'Filas', '%', 'bar'], $tabTable);
        $this->line("Total clasificadas: {$tabTotal} / " . count($rows));

        // ── 5. Verificar campos clave en primera fila raw ────────────────────
        $this->line('');
        $this->info("══ 5. CAMPOS CLAVE EN PRIMERA FILA RAW (antes de buildDatatableRow) ══");
        if (!empty($rawRows)) {
            $first  = $rawRows[0];
            $needed = ['form_id','hc_number','fname','lname','fecha_inicio','status',
                       'printed','lateralidad','afiliacion_label','afiliacion_categoria',
                       'sede','edad','alertas_count',
                       'cirujano_first_name','firmado_first_name','huella_first_name'];
            $check  = [];
            foreach ($needed as $f) {
                $present = array_key_exists($f, $first);
                $val     = $present ? (is_null($first[$f]) ? 'null' : mb_substr((string) $first[$f], 0, 35)) : '';
                $check[] = [$f, $present ? '✓' : '✗ AUSENTE', $val];
            }
            $this->table(['campo', 'estado', 'valor'], $check);
        }

        $this->line('');
        $this->info('Diagnóstico completado.');
        return self::SUCCESS;
    }

    /**
     * Reproduce Cirugia::getEstado() sin instanciar el modelo completo
     * usando solo los campos que el SELECT devuelve.
     */
    private function resolveEstado(array $row): string
    {
        $status = (int) ($row['status'] ?? 0);
        if ($status === 1) {
            return 'revisado';
        }

        $cirujano = trim((string) ($row['cirujano_first_name'] ?? ''));
        $membrete  = strtoupper(trim((string) ($row['membrete'] ?? '')));

        if ($membrete === '' || str_contains($membrete, 'CENTER') || str_contains($membrete, 'UNDEFINED')) {
            return 'incompleto';
        }
        if ($cirujano !== '') {
            return 'no revisado';
        }

        return 'incompleto';
    }
}
