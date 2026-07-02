<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Models\Cirugia;
use App\Modules\Cirugias\Services\CirugiaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class CirugiasReadController
{
    public function __construct(private readonly CirugiaService $service)
    {
    }

    public function staffOptions(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesion expirada'], 401);
        }

        return response()->json([
            'data' => $this->service->obtenerStaffPorEspecialidad(),
        ]);
    }

    public function searchProcedimientos(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'data' => []], 401);
        }

        $q = trim((string) $request->query('q', ''));
        if (strlen($q) < 2) {
            return response()->json(['ok' => true, 'data' => []]);
        }

        $pattern = '%' . $q . '%';
        $rows = DB::table('tarifario_2014')
            ->select(['codigo', 'descripcion'])
            ->where(function ($builder) use ($pattern): void {
                $builder->where('codigo', 'like', $pattern)
                        ->orWhere('descripcion', 'like', $pattern);
            })
            ->orderBy('codigo')
            ->limit(20)
            ->get()
            ->map(static fn(object $r): array => ['codigo' => $r->codigo, 'descripcion' => $r->descripcion])
            ->all();

        return response()->json(['ok' => true, 'data' => $rows]);
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!Auth::check()) {
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
                'user_id' => Auth::id(),
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
        if (!Auth::check()) {
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
            // Prefer direct 'codigo'/'nombre' keys (React wizard format).
            // Fall back to parsing 'procInterno' for legacy records ("LABEL - CODE - Nombre").
            $codigo = trim((string) ($p['codigo'] ?? ''));
            $nombre = trim((string) ($p['nombre'] ?? ''));

            if ($codigo === '' && $nombre === '') {
                $codigoStr = (string) ($p['procInterno'] ?? '');
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

        // Insumos/medicamentos: usa el JSON guardado si existe; si no, cae a la
        // plantilla del procedimiento (misma lógica que el wizard legacy).
        $insumos = $this->service->obtenerInsumosPorProtocolo($cirugia->procedimiento_id ?? null, $cirugia->insumos ?? null);
        $medicamentos = $this->service->obtenerMedicamentosConfigurados($cirugia->medicamentos ?? null, $cirugia->procedimiento_id ?? null);

        // Catálogo de insumos disponibles (real, filtrado por afiliación como en el legacy).
        $insumosDisponibles = $this->service->obtenerInsumosDisponibles((string) ($cirugia->afiliacion ?? ''));
        // Catálogo de medicamentos disponibles (real, tabla medicamentos).
        $medicamentosDisponibles = array_column($this->service->obtenerOpcionesMedicamentos(), 'medicamento');

        return response()->json([
            // Patient identity (for wizard step 1)
            'hc_number'  => $cirugia->hc_number,
            'fname'      => $cirugia->fname,
            'mname'      => $cirugia->mname,
            'lname'      => $cirugia->lname,
            'lname2'     => $cirugia->lname2,
            'fecha_nacimiento' => $cirugia->fecha_nacimiento,
            // Procedure
            'procedimiento_id'        => $cirugia->procedimiento_id,
            'procedimiento_proyectado' => $cirugia->procedimiento_proyectado,
            'membrete'    => $cirugia->membrete,
            'lateralidad' => $this->normalizeLateralidad((string) ($cirugia->lateralidad ?? '')),
            // Tiempos
            'fecha_inicio' => $cirugia->fecha_inicio,
            'fecha_fin'    => $cirugia->fecha_fin ?? '',
            'hora_inicio'  => $cirugia->hora_inicio,
            'hora_fin'     => $cirugia->hora_fin,
            'duracion'     => $duracion,
            'tipo_anestesia' => $cirugia->tipo_anestesia,
            // Operatorio
            'dieresis'    => $cirugia->dieresis,
            'exposicion'  => $cirugia->exposicion,
            'hallazgo'    => $cirugia->hallazgo,
            'operatorio'  => $cirugia->operatorio,
            'complicaciones_operatorio' => $cirugia->complicaciones_operatorio,
            // Collections
            'diagnosticos'         => $diagnosticos,
            'diagnosticos_previos' => json_decode((string) ($cirugia->diagnosticos_previos ?? '[]'), true) ?: [],
            'procedimientos'       => $procedimientos,
            'insumos'              => $insumos,
            'medicamentos'         => $medicamentos,
            'insumosDisponibles'       => $insumosDisponibles,
            'medicamentosDisponibles'  => $medicamentosDisponibles,
            // Staff
            'staff'     => $staff,
            // Audit
            'auditoria' => $auditoria,
        ]);
    }

    private function normalizeLateralidad(string $raw): string
    {
        $u = strtoupper(trim($raw));
        if (str_contains($u, 'AMBOS') || $u === 'AO' || str_contains($u, 'BILATERAL')) return 'AO';
        if (str_contains($u, 'IZQUIERDO') || $u === 'OI' || str_contains($u, 'LEFT'))  return 'OI';
        if (str_contains($u, 'DERECHO')   || $u === 'OD' || str_contains($u, 'RIGHT')) return 'OD';
        return $raw;
    }

    private function buildDoctorName(?string $firstName, ?string $lastName): string
    {
        $first = trim((string) ($firstName ?? ''));
        $last  = trim((string) ($lastName ?? ''));
        if ($first === '' && $last === '') return '';
        return 'Dr. ' . implode(' ', array_filter([$first, $last]));
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function buildDatatableRow(array $row): array
    {
        $esc = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

        $cirugia = new Cirugia($row);
        $estado = $cirugia->getEstado();
        $printed = (int) ($row['printed'] ?? 0);
        $alertasCount = (int) ($row['alertas_count'] ?? 0);

        $formId = (string) ($row['form_id'] ?? '');
        $hcNumber = (string) ($row['hc_number'] ?? '');

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

        $lateralidadRaw = strtoupper(trim((string) ($row['lateralidad'] ?? '')));
        $lateralidad = match (true) {
            str_contains($lateralidadRaw, 'AMBOS') || str_contains($lateralidadRaw, 'AO') || str_contains($lateralidadRaw, 'BILATERAL') => 'AO',
            str_contains($lateralidadRaw, 'IZQUIERDO') || str_contains($lateralidadRaw, 'OI') || str_contains($lateralidadRaw, 'LEFT') => 'OI',
            str_contains($lateralidadRaw, 'DERECHO') || str_contains($lateralidadRaw, 'OD') || str_contains($lateralidadRaw, 'RIGHT') => 'OD',
            default => '',
        };

        // 'estado' values: revisado / no revisado / incompleto.
        // Every row here comes from protocolo_data, so "incompleto" means the
        // protocol was started but is pending completion — never "sin_protocolo".
        $auditStatus = match ($estado) {
            'revisado'    => 'conforme',
            'no revisado' => 'por_revisar',
            default       => $alertasCount > 0 ? 'alertas' : 'por_revisar',
        };

        return [
            'form_id'            => $formId,
            'hc_number'          => $hcNumber,
            'full_name'          => $cirugia->getNombreCompleto(),
            'edad'               => $row['edad'] !== null ? (int) $row['edad'] : null,
            'afiliacion_label'   => $afiliacion,
            'afiliacion_categoria' => $categoria,
            'sede'               => (string) ($row['sede'] ?? ''),
            'fecha_inicio'       => $fechaInicio,
            'membrete'           => (string) ($row['membrete'] ?? ''),
            'lateralidad'        => $lateralidad,
            'printed'            => $printed,
            'status'             => (int) ($row['status'] ?? 0),
            'alertas_count'      => $alertasCount,
            'audit_status'       => $auditStatus,
            'protocolo_iniciado' => $auditStatus !== 'sin_protocolo',
            'cirujano_display'   => $this->buildDoctorName($row['cirujano_first_name'] ?? null, $row['cirujano_last_name'] ?? null),
            'revisado_por'       => $this->buildDoctorName($row['firmado_first_name'] ?? null, $row['firmado_last_name'] ?? null),
            'revisado_fecha'     => (string) ($row['fecha_firma'] ?? ''),
            'huella_display'     => $this->buildDoctorName($row['huella_first_name'] ?? null, $row['huella_last_name'] ?? null),
            'huella_evento'      => (string) ($row['huella_evento'] ?? ''),
            'huella_fecha'       => (string) ($row['huella_fecha'] ?? ''),
        ];
    }
}
