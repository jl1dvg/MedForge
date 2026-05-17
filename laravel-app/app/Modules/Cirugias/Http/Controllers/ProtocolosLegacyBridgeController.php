<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

use App\Modules\Cirugias\Services\ProtocolosTemplateWriteService;
use App\Modules\Shared\Support\LegacyCurrentUser;
use App\Modules\Shared\Support\LegacyPermissionResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->pdo = $pdo;
        $this->writeService = new ProtocolosTemplateWriteService($pdo);
    }

    public function index(Request $request): Response
    {
        if (!$this->canAny($request, self::READ_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        return $this->renderLegacy($request, 'index');
    }

    public function create(Request $request): Response
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        return $this->renderLegacy($request, 'create');
    }

    public function edit(Request $request): Response
    {
        if (!$this->canAny($request, self::WRITE_PERMISSIONS)) {
            return response('Acceso denegado', 403);
        }

        return $this->renderLegacy($request, 'edit');
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

        $token = (string) $request->input('csrf_token', '');
        $sessionToken = isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : '';

        if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
            return redirect('/v2/protocolos?error=1');
        }

        $id = trim((string) $request->input('id', ''));
        if ($id === '') {
            return redirect('/v2/protocolos?error=1');
        }

        return $this->writeService->deleteProtocol($id)
            ? redirect('/v2/protocolos?deleted=1')
            : redirect('/v2/protocolos?error=1');
    }

    private function renderLegacy(Request $request, string $method): Response
    {
        $this->bootstrapLegacyRuntime($request);

        try {
            ob_start();
            $this->makeLegacyController()->{$method}();
            $output = ob_get_clean() ?: '';
        } catch (Throwable $exception) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return response('Error interno en protocolos.', 500);
        }

        return response($output);
    }

    private function bootstrapLegacyRuntime(Request $request): void
    {
        require_once base_path('../bootstrap.php');

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

        $arrayFields = ['codigos', 'lateralidades', 'selectores_codigos', 'funciones', 'trabajadores', 'nombres_staff', 'selectores_staff'];
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

    private function makeLegacyController(): \Modules\EditorProtocolos\Controllers\EditorController
    {
        return new \Modules\EditorProtocolos\Controllers\EditorController($this->pdo);
    }
}
