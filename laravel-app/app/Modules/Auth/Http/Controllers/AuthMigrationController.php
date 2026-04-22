<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AuthMigrationController
{
    public function status(): JsonResponse
    {
        $legacyCompatSession = filter_var((string) env('AUTH_WRITE_LEGACY_COMPAT_SESSION', '1'), FILTER_VALIDATE_BOOL);

        return response()->json([
            'module' => 'auth',
            'status' => 'in_progress',
            'ready' => !$legacyCompatSession,
            'checks' => [
                'legacy_session_bridge' => $legacyCompatSession,
                'legacy_compat_session_write' => $legacyCompatSession,
                'app_auth_is_native' => true,
                'unified_logout' => true,
                'migration_status_endpoint' => true,
            ],
            'message' => $legacyCompatSession
                ? 'Auth/Sesión en transición controlada. El bridge legacy sigue habilitado para módulos no migrados.'
                : 'Auth/Sesión lista para operar sin bridge legacy.',
        ], 200);
    }
}
