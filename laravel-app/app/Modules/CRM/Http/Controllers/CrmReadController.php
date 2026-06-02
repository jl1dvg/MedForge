<?php

namespace App\Modules\CRM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmReadController
{
    public function leads(Request $request): JsonResponse
    {
        return $this->legacyLeadsDisabledResponse();
    }

    public function meta(Request $request): JsonResponse
    {
        return $this->legacyLeadsDisabledResponse();
    }

    public function metrics(Request $request): JsonResponse
    {
        return $this->legacyLeadsDisabledResponse();
    }

    private function legacyLeadsDisabledResponse(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        return response()->json([
            'error' => 'El flujo legacy crm_leads está deshabilitado. Usa crm_opportunities.',
        ], 410);
    }
}
