<?php

namespace App\Modules\Dashboard\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DashboardReadController
{
    public function summary(): JsonResponse
    {
        $patients = (int) (DB::selectOne('SELECT COUNT(*) AS total FROM patient_data')->total ?? 0);
        $users = (int) (DB::selectOne('SELECT COUNT(*) AS total FROM users')->total ?? 0);
        $protocols = (int) (DB::selectOne('SELECT COUNT(*) AS total FROM protocolo_data')->total ?? 0);

        return response()->json([
            'data' => [
                'patients_total' => $patients,
                'users_total' => $users,
                'protocols_total' => $protocols,
            ],
            'meta' => [
                'strategy' => 'strangler-v2',
                'source' => 'sql-legacy-phase-1',
            ],
        ]);
    }
}
