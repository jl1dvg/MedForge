<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AuthMigrationController
{
    public function status(): JsonResponse
    {
        $legacyCompatSession = (bool) config('auth_migration.write_legacy_compat_session', true);
        $acceptLegacySession = (bool) config('auth_migration.accept_legacy_session', true);

        return response()->json([
            'module' => 'auth',
            'status' => 'in_progress',
            'ready' => !$legacyCompatSession && !$acceptLegacySession,
            'checks' => [
                'legacy_session_bridge' => $acceptLegacySession,
                'legacy_compat_session_write' => $legacyCompatSession,
                'app_auth_is_native' => true,
                'unified_logout' => true,
                'migration_status_endpoint' => true,
            ],
            'message' => ($legacyCompatSession || $acceptLegacySession)
                ? 'Auth/Sesión en transición controlada. El fallback de compatibilidad legacy sigue habilitado.'
                : 'Auth/Sesión lista para operar sin bridge legacy.',
        ], 200);
    }
}
