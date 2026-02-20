<?php

namespace App\Modules\Auth\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AuthMigrationController
{
    public function status(): JsonResponse
    {
        return response()->json([
            'module' => 'auth',
            'status' => 'deferred',
            'message' => 'Auth y sesiones compartidas se migran en Wave C.',
        ], 501);
    }
}
