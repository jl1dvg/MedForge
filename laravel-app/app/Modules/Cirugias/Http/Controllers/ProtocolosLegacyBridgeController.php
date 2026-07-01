<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Services\ProtocolosTemplateWriteService;
use App\Modules\Cirugias\Services\ProtocolosTemplateReadService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionResolver;
use Illuminate\Http\JsonResponse;
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

    public function index(Request $request): View|Response
    {
        if (!$this->canAny($request, self::READ_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        $this->bootstrapLegacyRuntime($request);

        return $this->renderApp($request, ['name' => 'list']);
    }

    public function create(Request $request): View|Response
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        $this->bootstrapLegacyRuntime($request);
        $categoria = trim((string) $request->query('categoria', ''));
        $protocolo = $this->readService->crearProtocoloVacio($categoria !== '' ? $categoria : null);

        return $this->renderApp($request, ['name' => 'new', 'protocolo' => $this->toWizardShape($protocolo)]);
    }

    public function edit(Request $request): View|Response
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
                return $this->renderApp($request, ['name' => 'list', 'error' => 'No se encontró el protocolo a duplicar.']);
            }

            $protocolo = $original;
            $protocolo['id'] = '';
            $protocolo['codigos'] = $this->readService->obtenerCodigosDeProcedimiento($duplicarId);
            $protocolo['staff'] = $this->readService->obtenerStaffDeProcedimiento($duplicarId);
            $protocolo['insumos'] = $this->readService->obtenerInsumosDeProtocolo($duplicarId);
            $protocolo['medicamentos'] = $this->readService->obtenerMedicamentosDeProtocolo($duplicarId);

            return $this->renderApp($request, [
                'name' => 'edit',
                'protocolo' => $this->toWizardShape($protocolo),
                'duplicandoDe' => $original['membrete'] ?? null,
            ]);
        }

        if ($id === '') {
            return $this->renderApp($request, ['name' => 'list', 'error' => 'Selecciona un protocolo para editar.']);
        }

        $protocolo = $this->readService->obtenerProtocoloPorId($id);
        if (!$protocolo) {
            return $this->renderApp($request, ['name' => 'list', 'error' => 'No se encontró el protocolo solicitado.']);
        }

        $protocolo['codigos'] = $this->readService->obtenerCodigosDeProcedimiento($id);
        $protocolo['staff'] = $this->readService->obtenerStaffDeProcedimiento($id);
        $protocolo['insumos'] = $this->readService->obtenerInsumosDeProtocolo($id);
        $protocolo['medicamentos'] = $this->readService->obtenerMedicamentosDeProtocolo($id);

        return $this->renderApp($request, ['name' => 'edit', 'protocolo' => $this->toWizardShape($protocolo)]);
    }

    public function store(Request $request): JsonResponse
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
                'message' => $result ? 'Protocolo guardado exitosamente.' : 'Error al guardar el protocolo.',
                'generated_id' => $payload['id'] ?? null,
            ], $result ? 200 : 500);
        } catch (Throwable) {
            return response()->json([
                'success' => false,
                'message' => 'Excepción capturada al guardar el protocolo.',
            ], 500);
        }
    }

    public function delete(Request $request): JsonResponse
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response()->json(['success' => false, 'message' => 'Acceso denegado'], 403);
        }

        $this->bootstrapLegacyRuntime($request);

        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return response()->json(['success' => false, 'message' => 'Falta el identificador del protocolo.'], 422);
        }

        $result = $this->writeService->deleteProtocol($id);

        return response()->json([
            'success' => $result,
            'message' => $result ? 'Protocolo eliminado correctamente.' : 'No se pudo eliminar el protocolo.',
        ], $result ? 200 : 500);
    }

    /**
     * Renderiza el shell React único (lista + wizard) con la config inicial embebida.
     */
    private function renderApp(Request $request, array $route): View
    {
        $canManage = $this->canAny($request, self::WRITE_PERMISSIONS);

        $config = [
            'route' => $route,
            'canManage' => $canManage,
            'currentUser' => LegacyCurrentUser::resolve($request),
            'catalogo' => $this->readService->obtenerProtocolosCatalogo(),
            'catalogos' => [
                'categorias' => config('protocolos.categorias'),
                'funcionesStaff' => config('protocolos.funciones_staff'),
                'funcionEspecialidad' => config('protocolos.funcion_especialidad'),
                'vias' => config('protocolos.vias'),
                'responsables' => config('protocolos.responsables'),
                'plantillasBase' => config('protocolos.plantillas_base'),
                'sugerenciasStaff' => config('protocolos.sugerencias_staff'),
                'sugerenciasInsumos' => config('protocolos.sugerencias_insumos'),
                'sugerenciasMedicamentos' => config('protocolos.sugerencias_medicamentos'),
                'operatorioSugerido' => config('protocolos.operatorio_sugerido'),
                'insumosDisponibles' => $this->readService->obtenerInsumosDisponibles(),
                'opcionesMedicamentos' => $this->readService->obtenerOpcionesMedicamentos(),
            ],
            'endpoints' => [
                'catalogo' => '/v2/protocolos',
                'guardar' => '/v2/protocolos/guardar',
                'eliminar' => '/v2/protocolos/eliminar',
                'nuevo' => '/v2/protocolos/crear',
                'editar' => '/v2/protocolos/editar',
                'searchCodigos' => '/v2/cirugias/search-procedimientos',
                'staffOptions' => '/v2/cirugias/staff-options',
            ],
        ];

        return view('protocolos.index', [
            'pageTitle' => 'Protocolos quirúrgicos',
            'appConfig' => $config,
        ]);
    }

    /**
     * Adapta el shape plano de la lectura legacy (codigos/staff con columnas SQL crudas)
     * al shape que consume el wizard React ({codigo,nombre} / {funcion,nombre,trabajador_id}).
     */
    private function toWizardShape(array $protocolo): array
    {
        $protocolo['codigos'] = array_map(
            static fn (array $c): array => ['codigo' => $c['codigo'] ?? '', 'nombre' => $c['nombre'] ?? ''],
            $protocolo['codigos'] ?? []
        );
        $protocolo['staff'] = array_map(
            static fn (array $s): array => [
                'funcion' => $s['funcion'] ?? '',
                'nombre' => $s['nombre'] ?? '',
                'trabajador_id' => isset($s['trabajador_id']) && $s['trabajador_id'] !== null ? (int) $s['trabajador_id'] : null,
            ],
            $protocolo['staff'] ?? []
        );

        return $protocolo;
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
        $payload = [];
        $stringFields = [
            'id', 'cirugia', 'membrete', 'dieresis', 'exposicion', 'hallazgo', 'horas',
            'imagen_link', 'operatorio', 'pre_evolucion', 'pre_indicacion', 'post_evolucion', 'post_indicacion',
            'alta_evolucion', 'alta_indicacion',
        ];
        foreach ($stringFields as $field) {
            $payload[$field] = isset($input[$field]) ? trim((string) $input[$field]) : '';
        }

        $payload['categoriaQX'] = trim((string) ($input['categoria'] ?? $input['categoriaQX'] ?? ''));

        $payload['codigos'] = is_array($input['codigos'] ?? null) ? $input['codigos'] : [];
        $payload['staff'] = is_array($input['staff'] ?? null) ? $input['staff'] : [];
        $payload['insumos'] = is_array($input['insumos'] ?? null) ? $input['insumos'] : [];
        $payload['medicamentos'] = is_array($input['medicamentos'] ?? null) ? $input['medicamentos'] : [];

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
        if ($payload['horas'] === '' || !is_numeric($payload['horas'])) {
            $error = 'La duración estimada debe ser numérica.';
            return false;
        }
        if ($payload['imagen_link'] !== '' && filter_var($payload['imagen_link'], FILTER_VALIDATE_URL) === false) {
            $error = 'El enlace de imagen no tiene un formato válido.';
            return false;
        }
        if (count(array_filter($payload['codigos'], static fn ($c) => trim((string) ($c['nombre'] ?? '')) !== '')) === 0) {
            $error = 'Agrega al menos un código quirúrgico.';
            return false;
        }
        if ($payload['operatorio'] === '') {
            $error = 'Describe la técnica operatoria.';
            return false;
        }
        $error = null;
        return true;
    }
}
