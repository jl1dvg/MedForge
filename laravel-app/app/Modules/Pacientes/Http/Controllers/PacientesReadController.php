<?php

namespace App\Modules\Pacientes\Http\Controllers;

use App\Modules\Pacientes\Services\PacientesParityService;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PDO;

class PacientesReadController
{
    private PacientesParityService $service;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->service = new PacientesParityService($pdo);
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            return $this->unauthenticatedJson(1);
        }

        $limit = min(max((int) $request->query('limit', 20), 1), 100);
        $offset = max((int) $request->query('offset', 0), 0);

        $rows = DB::select(
            'SELECT hc_number, fname, lname, lname2, afiliacion, fecha_nacimiento
             FROM patient_data
             ORDER BY hc_number DESC
             LIMIT ? OFFSET ?',
            [$limit, $offset]
        );

        return response()->json([
            'data' => $rows,
            'meta' => [
                'limit' => $limit,
                'offset' => $offset,
                'count' => count($rows),
            ],
        ]);
    }

    public function datatable(Request $request): JsonResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            return $this->unauthenticatedJson((int) $request->input('draw', 1));
        }

        $draw = (int) $request->input('draw', 1);
        $start = max((int) $request->input('start', 0), 0);
        $length = max((int) $request->input('length', 10), 1);
        $search = trim((string) data_get($request->all(), 'search.value', ''));
        $orderColumnIndex = (int) data_get($request->all(), 'order.0.column', 0);
        $orderDir = (string) data_get($request->all(), 'order.0.dir', 'asc');
        $columnMap = ['hc_number', 'ultima_fecha', 'full_name', 'afiliacion'];
        $orderColumn = $columnMap[$orderColumnIndex] ?? 'hc_number';

        $response = $this->service->obtenerPacientesPaginados(
            $start,
            $length,
            $search,
            $orderColumn,
            strtoupper($orderDir)
        );

        return response()->json([
            'draw' => $draw,
            'recordsTotal' => $response['recordsTotal'] ?? 0,
            'recordsFiltered' => $response['recordsFiltered'] ?? 0,
            'data' => $response['data'] ?? [],
        ]);
    }

    public function detalles(Request $request): JsonResponse|RedirectResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Sesión expirada'], 401);
            }

            return redirect('/auth/login?auth_required=1');
        }

        $hcNumber = trim((string) $request->input('hc_number', ''));
        if ($hcNumber === '') {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'hc_number es requerido'], 422);
            }

            return redirect('/v2/pacientes');
        }

        if ($request->isMethod('post') && $request->has('actualizar_paciente')) {
            $this->service->actualizarPaciente($hcNumber, $request->all(), $this->legacyUserId($request));
            return redirect('/v2/pacientes/detalles?hc_number=' . urlencode($hcNumber));
        }

        $context = $this->service->obtenerContextoPaciente($hcNumber);
        if ($context === []) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Paciente no encontrado'], 404);
            }

            return redirect('/v2/pacientes?not_found=1');
        }

        return response()->json([
            'data' => $context,
        ]);
    }

    public function flujo(Request $request): JsonResponse
    {
        if (!$this->isLegacyAuthenticated($request)) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $hcNumber = trim((string) $request->query('hc_number', ''));
        if ($hcNumber === '') {
            return response()->json(['error' => 'hc_number es requerido'], 422);
        }

        $columns = $this->tableColumns('prefactura_paciente');

        $formIdColumn = in_array('form_id', $columns, true)
            ? 'form_id'
            : (in_array('id', $columns, true) ? 'id AS form_id' : 'NULL AS form_id');
        $fechaCreacionColumn = in_array('fecha_creacion', $columns, true)
            ? 'fecha_creacion'
            : (in_array('created_at', $columns, true) ? 'created_at AS fecha_creacion' : 'NULL AS fecha_creacion');
        $fechaRegistroColumn = in_array('fecha_registro', $columns, true)
            ? 'fecha_registro'
            : 'NULL AS fecha_registro';
        $codDerivacionColumn = in_array('cod_derivacion', $columns, true)
            ? 'cod_derivacion'
            : 'NULL AS cod_derivacion';

        $orderBy = in_array('fecha_creacion', $columns, true)
            ? 'fecha_creacion'
            : (in_array('created_at', $columns, true) ? 'created_at' : (in_array('id', $columns, true) ? 'id' : 'hc_number'));

        $sql = sprintf(
            'SELECT %s, hc_number, %s, %s, %s
             FROM prefactura_paciente
             WHERE hc_number = ?
             ORDER BY %s DESC
             LIMIT 50',
            $formIdColumn,
            $fechaCreacionColumn,
            $fechaRegistroColumn,
            $codDerivacionColumn,
            $orderBy
        );

        try {
            $rows = DB::select($sql, [$hcNumber]);
        } catch (\Throwable) {
            $rows = [];
        }

        return response()->json([
            'data' => $rows,
            'meta' => [
                'count' => count($rows),
            ],
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function tableColumns(string $table): array
    {
        try {
            $rows = DB::select("SHOW COLUMNS FROM {$table}");
        } catch (\Throwable) {
            return [];
        }

        $columns = [];
        foreach ($rows as $row) {
            $field = (string) data_get((array) $row, 'Field', '');
            if ($field !== '') {
                $columns[] = $field;
            }
        }

        return $columns;
    }

    private function unauthenticatedJson(int $draw): JsonResponse
    {
        return response()->json([
            'draw' => $draw,
            'recordsTotal' => 0,
            'recordsFiltered' => 0,
            'data' => [],
            'error' => 'Sesión expirada',
        ], 401);
    }

    private function isLegacyAuthenticated(Request $request): bool
    {
        return LegacySessionAuth::isAuthenticated($request);
    }

    private function legacyUserId(Request $request): ?int
    {
        return LegacySessionAuth::userId($request);
    }

    /**
     * @return array<string, mixed>
     */
    private function readLegacyPhpSession(Request $request): array
    {
        return LegacySessionAuth::readSession($request);
    }
}
