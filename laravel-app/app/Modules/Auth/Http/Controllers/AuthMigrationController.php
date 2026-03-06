<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AuthMigrationController
{
    public function status(): JsonResponse
    {
        return response()->json([
            'module' => 'auth',
            'status' => 'in_progress',
            'ready' => true,
            'checks' => [
                'legacy_session_bridge' => true,
                'unified_logout' => true,
                'migration_status_endpoint' => true,
            ],
            'message' => 'Auth/Sesión en transición controlada. Endpoint operativo para monitoreo.',
        ], 200);
    }
}
