<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Services\ProtocolosTemplateWriteService;
use App\Modules\Cirugias\Services\ProtocolosTemplateReadService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Facades\DB;
use PDO;
use Throwable;

class ProtocolosLegacyBridgeController
{
    private const READ_PERMISSIONS = [
        'administrativo',
        'protocolos.manage',
        'protocolos.templates.view',
        'protocolos.templates.manage',
    ];

    private const WRITE_PERMISSIONS = [
        'administrativo',
        'protocolos.manage',
        'protocolos.templates.manage',
    ];

    private PDO $pdo;
    private ProtocolosTemplateWriteService $writeService;
    private ProtocolosTemplateReadService $readService;

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->pdo = $pdo;
        $this->writeService = new ProtocolosTemplateWriteService($pdo);
        $this->readService = new ProtocolosTemplateReadService($pdo);
    }

    public function index(Request $request): Response
    {
        if (!$this->canAny($request, self::READ_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        $this->bootstrapLegacyRuntime($request);
        return response()->view('protocolos.index', [
            'pageTitle' => 'Editor de Protocolos',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'procedimientosPorCategoria' => $this->readService->obtenerProcedimientosAgrupados(),
            'mensajeExito' => $request->query('deleted') !== null ? 'Protocolo eliminado correctamente.' : ($request->query('saved') !== null ? 'Protocolo guardado correctamente.' : null),
            'mensajeError' => $request->query('error') !== null ? 'No se pudo completar la operación solicitada.' : null,
            'canManage' => $this->canAny($request, self::WRITE_PERMISSIONS),
        ]);
    }

    public function create(Request $request): View|Response
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        $this->bootstrapLegacyRuntime($request);
        $categoria = trim((string) $request->query('categoria', ''));
        $protocolo = $this->readService->crearProtocoloVacio($categoria !== '' ? $categoria : null);

        return $this->viewEditLegacy($request, $protocolo, [
            'pageTitle' => 'Nuevo protocolo',
            'esNuevo' => true,
        ]);
    }

    public function edit(Request $request): View|RedirectResponse|Response
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        $this->bootstrapLegacyRuntime($request);
        $duplicarId = trim((string) $request->query('duplicar', ''));
        $id = trim((string) $request->query('id', ''));

        if ($duplicarId !== '') {
            $original = $this->readService->obtenerProtocoloPorId($duplicarId);
            if (!$original) {
                return redirect('/v2/protocolos?error=1');
            }

            $protocolo = $original;
            $protocolo['id'] = '';
            $protocolo['codigos'] = $this->readService->obtenerCodigosDeProcedimiento($duplicarId);
            $protocolo['staff'] = $this->readService->obtenerStaffDeProcedimiento($duplicarId);
            $protocolo['insumos'] = $this->readService->obtenerInsumosDeProtocolo($duplicarId);
            $protocolo['medicamentos'] = $this->readService->obtenerMedicamentosDeProtocolo($duplicarId);

            return $this->viewEditLegacy($request, $protocolo, [
                'pageTitle' => 'Duplicar protocolo',
                'duplicando' => true,
                'duplicarId' => $duplicarId,
            ]);
        }

        if ($id === '') {
            return redirect('/v2/protocolos?error=1');
        }

        $protocolo = $this->readService->obtenerProtocoloPorId($id);
        if (!$protocolo) {
            return redirect('/v2/protocolos?error=1');
        }

        $protocolo['codigos'] = $this->readService->obtenerCodigosDeProcedimiento($id);
        $protocolo['staff'] = $this->readService->obtenerStaffDeProcedimiento($id);
        $protocolo['insumos'] = $this->readService->obtenerInsumosDeProtocolo($id);
        $protocolo['medicamentos'] = $this->readService->obtenerMedicamentosDeProtocolo($id);

        return $this->viewEditLegacy($request, $protocolo, [
            'pageTitle' => 'Editar protocolo',
        ]);
    }

    public function store(Request $request): JsonResponse|Response
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado'], 403);
        }
        $this->bootstrapLegacyRuntime($request);
        $payload = $this->normalizePayload($request->all());

        if (!$this->isValidPayload($payload, $validationError)) {
            return response()->json(['success' => false, 'message' => $validationError], 422);
        }

        if ($payload['id'] === '' && $payload['cirugia'] !== '') {
            $payload['id'] = $this->writeService->generateUniqueIdFromSurgery($payload['cirugia']);
        }

        try {
            $result = $this->writeService->updateProtocol($payload);
            return response()->json([
                'success' => $result,
                'message' => $result ? 'Protocolo actualizado exitosamente.' : 'Error al actualizar el protocolo.',
                'generated_id' => $payload['id'] ?? null,
            ], $result ? 200 : 500);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Excepción capturada al guardar el protocolo.',
            ], 500);
        }
    }

    public function delete(Request $request): RedirectResponse
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return redirect('/v2/protocolos?error=1');
        }

        $this->bootstrapLegacyRuntime($request);

        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return redirect('/v2/protocolos?error=1');
        }

        return $this->writeService->deleteProtocol($id)
            ? redirect('/v2/protocolos?deleted=1')
            : redirect('/v2/protocolos?error=1');
    }

    private function bootstrapLegacyRuntime(Request $request): void
    {
        $currentUser = LegacyCurrentUser::resolve($request);
        $permissions = LegacyPermissionResolver::resolve($request);

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        $_SESSION['user_id'] = (int) ($currentUser['id'] ?? 0);
        $_SESSION['username'] = (string) ($currentUser['display_name'] ?? 'Usuario');
        $_SESSION['permisos'] = $permissions;
        $_SESSION['session_active'] = true;
        $_SESSION['last_activity_time'] = time();
        if (!isset($_SESSION['session_start_time'])) {
            $_SESSION['session_start_time'] = time();
        }
    }

    private function canAny(Request $request, array $permissions): bool
    {
        return LegacyPermissionResolver::canAny($request, $permissions);
    }

    private function normalizePayload(array $input): array
    {
        $payload = $input;
        $stringFields = [
            'id', 'cirugia', 'categoriaQX', 'membrete', 'dieresis', 'exposicion', 'hallazgo', 'horas',
            'imagen_link', 'operatorio', 'pre_evolucion', 'pre_indicacion', 'post_evolucion', 'post_indicacion',
            'alta_evolucion', 'alta_indicacion', 'insumos', 'medicamentos',
        ];
        foreach ($stringFields as $field) {
            $payload[$field] = isset($payload[$field]) ? trim((string) $payload[$field]) : '';
        }

        $arrayFields = ['codigos', 'lateralidades', 'selectores_codigos', 'funciones', 'trabajadores', 'nombres_staff'];
        foreach ($arrayFields as $field) {
            if (!isset($payload[$field]) || !is_array($payload[$field])) {
                $payload[$field] = [];
            }
        }

        return $payload;
    }

    private function isValidPayload(array $payload, ?string &$error = null): bool
    {
        if ($payload['cirugia'] === '') {
            $error = 'Debes ingresar el nombre corto del procedimiento.';
            return false;
        }
        if ($payload['membrete'] === '') {
            $error = 'Debes ingresar el título del protocolo.';
            return false;
        }
        if ($payload['categoriaQX'] === '') {
            $error = 'Debes seleccionar una categoría.';
            return false;
        }
        if ($payload['horas'] !== '' && !is_numeric($payload['horas'])) {
            $error = 'La duración estimada debe ser numérica.';
            return false;
        }
        if ($payload['imagen_link'] !== '' && filter_var($payload['imagen_link'], FILTER_VALIDATE_URL) === false) {
            $error = 'El enlace de imagen no tiene un formato válido.';
            return false;
        }
        $error = null;
        return true;
    }

    private function viewEditLegacy(Request $request, array $protocolo, array $context = []): View
    {
        return view('protocolos.edit', array_merge([
            'pageTitle' => $context['pageTitle'] ?? 'Editor de protocolos',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'protocolo' => $protocolo,
            'medicamentos' => $protocolo['medicamentos'] ?? [],
            'opcionesMedicamentos' => $this->readService->obtenerOpcionesMedicamentos(),
            'insumosDisponibles' => $this->readService->obtenerInsumosDisponibles(),
            'insumosPaciente' => $protocolo['insumos'] ?? ['equipos' => [], 'quirurgicos' => [], 'anestesia' => []],
            'codigos' => $protocolo['codigos'] ?? [],
            'staff' => $protocolo['staff'] ?? [],
            'vias' => ['INTRAVENOSA', 'VIA INFILTRATIVA', 'SUBCONJUNTIVAL', 'TOPICA', 'INTRAVITREA'],
            'responsables' => ['Asistente', 'Anestesiólogo', 'Cirujano Principal'],
            'duplicando' => $context['duplicando'] ?? false,
            'esNuevo' => $context['esNuevo'] ?? false,
            'duplicarId' => $context['duplicarId'] ?? null,
        ], $context));
    }
}
