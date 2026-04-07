<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Models\Cirugia;
use App\Modules\Cirugias\Services\CirugiaService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDO;
use Throwable;

class CirugiasReadController
{
    private CirugiaService $service;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->service = new CirugiaService($pdo);
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => 'Sesion expirada',
            ], 401);
        }

        try {
            $draw = (int) $request->input('draw', 1);
            $start = max((int) $request->input('start', 0), 0);
            $length = (int) $request->input('length', 25);
            $search = trim((string) data_get($request->all(), 'search.value', ''));
            $orderColumnIndex = (int) data_get($request->all(), 'order.0.column', 4);
            $orderDir = (string) data_get($request->all(), 'order.0.dir', 'desc');

            $columnMap = [
                0 => 'form_id',
                1 => 'hc_number',
                2 => 'full_name',
                3 => 'afiliacion',
                4 => 'fecha_inicio',
                5 => 'membrete',
            ];
            $orderColumn = $columnMap[$orderColumnIndex] ?? 'fecha_inicio';

            $result = $this->service->obtenerCirugiasPaginadas(
                $start,
                $length,
                $search,
                $orderColumn,
                strtoupper($orderDir),
                [
                    'fecha_inicio' => (string) $request->input('fecha_inicio', ''),
                    'fecha_fin' => (string) $request->input('fecha_fin', ''),
                    'afiliacion' => (string) $request->input('afiliacion', ''),
                    'afiliacion_categoria' => (string) $request->input('afiliacion_categoria', ''),
                    'sede' => (string) $request->input('sede', ''),
                ]
            );

            $data = array_map(fn(array $row): array => $this->buildDatatableRow($row), $result['data']);

            return response()->json([
                'draw' => $draw,
                'recordsTotal' => (int) ($result['recordsTotal'] ?? 0),
                'recordsFiltered' => (int) ($result['recordsFiltered'] ?? 0),
                'data' => $data,
            ]);
        } catch (Throwable $exception) {
            Log::error('cirugias.read.datatable.error', [
                'user_id' => LegacySessionAuth::userId($request),
                'error' => $exception->getMessage(),
            ]);

            $errorDetail = trim($exception->getMessage()) !== ''
                ? ('No se pudo cargar la tabla de cirugias: ' . $exception->getMessage())
                : 'No se pudo cargar la tabla de cirugias';

            return response()->json([
                'draw' => (int) $request->input('draw', 1),
                'recordsTotal' => 0,
                'recordsFiltered' => 0,
                'data' => [],
                'error' => $errorDetail,
            ], 500);
        }
    }

    public function protocolo(Request $request): JsonResponse
    {
        if (!LegacySessionAuth::isAuthenticated($request)) {
            return response()->json(['error' => 'Sesion expirada'], 401);
        }

        $formId = trim((string) $request->query('form_id', ''));
        $hcNumber = trim((string) $request->query('hc_number', ''));

        if ($formId === '' || $hcNumber === '') {
            return response()->json(['error' => 'Faltan parámetros'], 400);
        }

        $cirugia = $this->service->obtenerCirugiaPorId($formId, $hcNumber);
        if (!$cirugia) {
            return response()->json(['error' => 'No se encontró el protocolo'], 404);
        }

        $diagnosticosRaw = json_decode((string) ($cirugia->diagnosticos ?? '[]'), true) ?: [];
        $diagnosticos = array_map(static function (array $d): array {
            $cie10 = '';
            $detalle = '';

            if (isset($d['idDiagnostico'])) {
                $partes = explode(' - ', (string) $d['idDiagnostico'], 2);
                $cie10 = trim((string) ($partes[0] ?? ''));
                $detalle = trim((string) ($partes[1] ?? ''));
            }

            return [
                'cie10' => $cie10,
                'detalle' => $detalle,
            ];
        }, $diagnosticosRaw);

        $procedimientosRaw = json_decode((string) ($cirugia->procedimientos ?? '[]'), true) ?: [];
        $procedimientos = array_map(static function (array $p): array {
            $codigo = '';
            $nombre = '';
            $codigoStr = (string) ($p['codigo'] ?? $p['procInterno'] ?? '');

            if ($codigoStr !== '') {
                if (preg_match('/-\s*(\d+)\s*-\s*(.*)/', $codigoStr, $match)) {
                    $codigo = trim((string) ($match[1] ?? ''));
                    $nombre = trim((string) ($match[2] ?? ''));
                } else {
                    $partes = explode(' - ', $codigoStr, 3);
                    $codigo = trim((string) ($partes[1] ?? ''));
                    $nombre = trim((string) ($partes[2] ?? ''));
                }
            }

            return [
                'codigo' => $codigo,
                'nombre' => $nombre,
            ];
        }, $procedimientosRaw);

        $staff = [
            'Cirujano principal' => $cirugia->cirujano_1,
            'Instrumentista' => $cirugia->instrumentista,
            'Cirujano 2' => $cirugia->cirujano_2,
            'Circulante' => $cirugia->circulante,
            'Primer ayudante' => $cirugia->primer_ayudante,
            'Segundo ayudante' => $cirugia->segundo_ayudante,
            'Tercer ayudante' => $cirugia->tercer_ayudante,
            'Anestesiólogo' => $cirugia->anestesiologo,
            'Ayudante anestesia' => $cirugia->ayudante_anestesia,
        ];

        $duracion = '';
        if ($cirugia->hora_inicio && $cirugia->hora_fin) {
            $inicio = strtotime((string) $cirugia->hora_inicio);
            $fin = strtotime((string) $cirugia->hora_fin);
            if ($inicio && $fin && $fin > $inicio) {
                $diff = $fin - $inicio;
                $duracion = floor($diff / 3600) . 'h ' . floor(($diff % 3600) / 60) . 'm';
            }
        }

        $auditoria = $this->service->obtenerAuditoriaProtocolo($cirugia);

        return response()->json([
            'fecha_inicio' => $cirugia->fecha_inicio,
            'hora_inicio' => $cirugia->hora_inicio,
            'hora_fin' => $cirugia->hora_fin,
            'duracion' => $duracion,
            'dieresis' => $cirugia->dieresis,
            'exposicion' => $cirugia->exposicion,
            'hallazgo' => $cirugia->hallazgo,
            'operatorio' => $cirugia->operatorio,
            'comentario' => $cirugia->complicaciones_operatorio,
            'diagnosticos' => $diagnosticos,
            'procedimientos' => $procedimientos,
            'staff' => $staff,
            'auditoria' => $auditoria,
        ]);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function buildDatatableRow(array $row): array
    {
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $cirugia = new Cirugia($row);
        $estado = $cirugia->getEstado();
        $printed = (int) ($row['printed'] ?? 0);

        $badgeEstado = match ($estado) {
            'revisado' => "<span class='badge bg-success'><i class='fa fa-check'></i></span>",
            'no revisado' => "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>",
            default => "<span class='badge bg-danger'><i class='fa fa-times'></i></span>",
        };
        $badgePrinted = $printed ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>" : '';

        $formId = (string) ($row['form_id'] ?? '');
        $hcNumber = (string) ($row['hc_number'] ?? '');
        $formIdEsc = $esc($formId);
        $hcNumberEsc = $esc($hcNumber);
        $formIdJs = json_encode($formId, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
        $hcNumberJs = json_encode($hcNumber, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

        $protocoloHtml = '<a href="#" class="btn btn-app btn-info" '
            . 'title="Ver protocolo quirurgico" '
            . 'data-bs-toggle="modal" data-bs-target="#resultModal" '
            . 'data-form-id="' . $formIdEsc . '" '
            . 'data-hc-number="' . $hcNumberEsc . '" '
            . 'onclick="loadProtocolData(this)">'
            . $badgeEstado . '<i class="mdi mdi-file-document"></i> Protocolo</a>';

        $descansoOnClick = 'emitirCertificadoDescanso(' . $formIdJs . ', ' . $hcNumberJs . ')';
        $descansoHtml = '<a class="btn btn-app btn-warning" '
            . 'title="Generar certificado de descanso postquirurgico" '
            . 'onclick="' . $esc($descansoOnClick) . '">'
            . '<i class="mdi mdi-file-document-box"></i> Descanso</a>';

        $printOnClick = $estado === 'revisado'
            ? 'togglePrintStatus(' . $formIdJs . ', ' . $hcNumberJs . ', this, ' . $printed . ')'
            : "Swal.fire({ icon: 'warning', title: 'Pendiente revision', text: 'Debe revisar el protocolo antes de imprimir.' })";

        $imprimirHtml = '<a class="btn btn-app btn-primary ' . ($printed ? 'active' : '') . '" '
            . 'title="Imprimir protocolo" onclick="' . $esc($printOnClick) . '">'
            . $badgePrinted . '<i class="fa fa-print"></i> Imprimir</a>';

        $fechaInicioRaw = (string) ($row['fecha_inicio'] ?? '');
        $fechaInicio = '';
        if ($fechaInicioRaw !== '') {
            $ts = strtotime($fechaInicioRaw);
            $fechaInicio = $ts ? date('d/m/Y', $ts) : $fechaInicioRaw;
        }

        $afiliacion = trim((string) ($row['afiliacion_label'] ?? $row['afiliacion'] ?? ''));
        if ($afiliacion === '') {
            $afiliacion = 'Sin convenio';
        }
        $categoria = trim((string) ($row['afiliacion_categoria'] ?? ''));
        $afiliacionHtml = $esc($afiliacion);
        if ($categoria !== '') {
            $afiliacionHtml .= '<span class="d-block text-muted fs-11">Categoria: ' . $esc(ucfirst($categoria)) . '</span>';
        }

        return [
            'form_id' => $esc((string) ($row['form_id'] ?? '')),
            'hc_number' => $esc((string) ($row['hc_number'] ?? '')),
            'full_name' => $esc($cirugia->getNombreCompleto()),
            'afiliacion_html' => $afiliacionHtml,
            'fecha_inicio' => $esc($fechaInicio),
            'membrete' => $esc((string) ($row['membrete'] ?? '')),
            'protocolo_html' => $protocoloHtml,
            'descanso_html' => $descansoHtml,
            'imprimir_html' => $imprimirHtml,
        ];
    }
}
