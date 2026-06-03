<?php

namespace App\Modules\CRM\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CrmWriteController
{
    public function createLead(Request $request): JsonResponse
    {
        return $this->legacyLeadsDisabledResponse();
    }

    public function updateLead(Request $request): JsonResponse
    {
        return $this->legacyLeadsDisabledResponse();
    }

    public function updateLeadStatus(Request $request, int $id): JsonResponse
    {
        return $this->legacyLeadsDisabledResponse();
    }

    private function legacyLeadsDisabledResponse(): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['ok' => false, 'error' => 'Sesión expirada'], 401);
        }

        return response()->json([
            'ok' => false,
            'error' => 'El flujo legacy crm_leads está deshabilitado. Usa crm_opportunities.',
        ], 410);
    }
}
