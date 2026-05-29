<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmOpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CrmOpportunityController
{
    public function __construct(
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmActivityService $activityService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $limit  = min(max((int) $request->query('limit', 25), 1), 100);
        $offset = max((int) $request->query('offset', 0), 0);
        $stage  = trim((string) $request->query('stage', ''));
        $source = trim((string) $request->query('source', ''));
        $phase  = trim((string) $request->query('phase', ''));
        $search = trim((string) $request->query('search', ''));
        $urgent         = filter_var($request->query('urgent', false), FILTER_VALIDATE_BOOLEAN);
        $includePublico = filter_var($request->query('include_publico', false), FILTER_VALIDATE_BOOLEAN);

        $query = CrmOpportunity::query()->with('contact');

        // Exclude public-affiliation opportunities by default
        if (!$includePublico) {
            $query->whereNotExists(function ($sub): void {
                $sub->select(DB::raw(1))
                    ->from('solicitud_procedimiento as sp_pub')
                    ->join('afiliacion_categoria_map as acm_pub', function ($join): void {
                        $join->on(
                            DB::raw('LOWER(TRIM(acm_pub.afiliacion_norm))'),
                            '=',
                            DB::raw('LOWER(TRIM(sp_pub.afiliacion))')
                        );
                    })
                    ->whereColumn('sp_pub.id', 'crm_opportunities.source_id')
                    ->whereRaw('crm_opportunities.source_type = ?', ['solicitud_procedimiento'])
                    ->where('acm_pub.categoria', 'publico');
            });
        }

        if ($stage !== '') {
            $query->where('stage', $stage);
        }
        if ($source !== '') {
            $query->where('source', $source);
        }
        if ($phase !== '') {
            $query->where('phase', $phase);
        }
        if ($search !== '') {
            $query->whereHas('contact', fn ($q) => $q->where('name', 'like', "%{$search}%")
                ->orWhere('cedula', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
            );
        }
        if ($urgent) {
            $staleDays = (int) config('crm.escalacion.dias_contactado', 7);
            $query->staleFor($staleDays * 24);
        }

        $total = $query->count();
        $rows  = $query->orderByRaw('COALESCE(last_activity_at, created_at) ASC')
            ->limit($limit)->offset($offset)->get();

        return response()->json([
            'data' => $rows,
            'meta' => ['total' => $total, 'limit' => $limit, 'offset' => $offset],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $opp = CrmOpportunity::query()->with(['contact', 'activities'])->findOrFail($id);

        return response()->json(['data' => $opp]);
    }

    public function store(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $validated = $request->validate([
            'contact_id' => 'required|integer',
            'title'      => 'required|string|max:255',
            'stage'      => 'sometimes|string|in:' . implode(',', CrmOpportunity::STAGES),
        ]);
        $opp = CrmOpportunity::query()->create([
            'contact_id' => $validated['contact_id'],
            'title'      => $validated['title'],
            'stage'      => $validated['stage'] ?? CrmOpportunity::STAGE_NUEVO,
            'source'     => 'manual',
        ]);
        $this->activityService->logSystemEvent($opp->id, 'Oportunidad creada manualmente');
        return response()->json(['data' => $opp], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }
        $opp = CrmOpportunity::query()->find($id);
        if (!$opp instanceof CrmOpportunity) {
            return response()->json(['error' => 'No encontrado'], 404);
        }

        $validated = $request->validate([
            'stage'       => 'sometimes|string|in:' . implode(',', CrmOpportunity::STAGES),
            'assigned_to' => 'sometimes|nullable|integer',
            'lost_reason' => 'sometimes|nullable|string|max:500',
        ]);

        try {
            if (isset($validated['stage'])) {
                $opp = $this->opportunityService->changeStage(
                    $opp,
                    $validated['stage'],
                    Auth::id(),
                    $validated['lost_reason'] ?? null,
                );
            }
            if (array_key_exists('assigned_to', $validated)) {
                $opp = $this->opportunityService->assign($opp, (int) $validated['assigned_to']);
            }
        } catch (\RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $opp->fresh(['contact', 'activities'])]);
    }
}
