<?php

declare(strict_types=1);

namespace App\Modules\Cirugias\Http\Controllers;

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

    public function __construct()
    {
        /** @var PDO $pdo */
        $pdo = DB::connection()->getPdo();
        $this->pdo = $pdo;
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

        try {
            ob_start();
            $this->makeLegacyController()->store();
            $output = ob_get_clean() ?: '';
        } catch (Throwable $exception) {
            if (ob_get_level() > 0) {
                ob_end_clean();
            }

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar el protocolo.',
            ], 500);
        }

        return response($output, 200, ['Content-Type' => 'application/json']);
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

        $service = new \Modules\EditorProtocolos\Services\ProtocoloTemplateService($this->pdo);

        return $service->eliminarProtocolo($id)
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

    private function makeLegacyController(): \Modules\EditorProtocolos\Controllers\EditorController
    {
        return new \Modules\EditorProtocolos\Controllers\EditorController($this->pdo);
    }
}

