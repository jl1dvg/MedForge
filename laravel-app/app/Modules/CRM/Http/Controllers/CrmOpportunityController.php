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

        $limit          = min(max((int) $request->query('limit', 25), 1), 100);
        $offset         = max((int) $request->query('offset', 0), 0);
        $stage          = trim((string) $request->query('stage', ''));
        $source         = trim((string) $request->query('source', ''));
        $phase          = trim((string) $request->query('phase', ''));
        $search         = trim((string) $request->query('search', ''));
        $afiliacion     = trim((string) $request->query('afiliacion', ''));  // particular|privado|fundacional|publico
        $urgent         = filter_var($request->query('urgent', false), FILTER_VALIDATE_BOOLEAN);
        $includePublico = filter_var($request->query('include_publico', false), FILTER_VALIDATE_BOOLEAN);

        $query = CrmOpportunity::query()->with('contact');

        // Affiliation filter — computed live from the most recent solicitud for this patient
        // Classification SQL: IESS/ISSFA/ISSPOL/MSP → publico | particular → particular | else → privado
        $afiliacionExpr = "CASE
            WHEN LOWER(sp_afil.afiliacion) REGEXP 'iess|issfa|isspol|msp|ministerio|salud.publica|red.publica'
                THEN 'publico'
            WHEN LOWER(sp_afil.afiliacion) LIKE '%particular%'
                THEN 'particular'
            WHEN LOWER(sp_afil.afiliacion) LIKE '%fundaci%'
                THEN 'fundacional'
            WHEN sp_afil.afiliacion IS NULL OR TRIM(sp_afil.afiliacion) = ''
                THEN 'sin_dato'
            ELSE 'privado'
        END";

        if ($afiliacion !== '') {
            // Filter by specific category — join to most recent solicitud per contact
            $query->whereExists(function ($sub) use ($afiliacion, $afiliacionExpr): void {
                $sub->selectRaw('1')
                    ->from('crm_contacts as cc_afil')
                    ->leftJoin(
                        DB::raw('(SELECT hc_number, afiliacion FROM solicitud_procedimiento s1
                                  WHERE s1.id = (SELECT MAX(id) FROM solicitud_procedimiento WHERE hc_number = s1.hc_number)
                                 ) AS sp_afil'),
                        'sp_afil.hc_number', '=', 'cc_afil.cedula'
                    )
                    ->whereColumn('cc_afil.id', 'crm_opportunities.contact_id')
                    ->whereRaw("({$afiliacionExpr}) = ?", [$afiliacion]);
            });
        } elseif (!$includePublico) {
            // Exclude public affiliation by default (using same live classification)
            $query->whereNotExists(function ($sub) use ($afiliacionExpr): void {
                $sub->selectRaw('1')
                    ->from('crm_contacts as cc_afil')
                    ->leftJoin(
                        DB::raw('(SELECT hc_number, afiliacion FROM solicitud_procedimiento s1
                                  WHERE s1.id = (SELECT MAX(id) FROM solicitud_procedimiento WHERE hc_number = s1.hc_number)
                                 ) AS sp_afil'),
                        'sp_afil.hc_number', '=', 'cc_afil.cedula'
                    )
                    ->whereColumn('cc_afil.id', 'crm_opportunities.contact_id')
                    ->whereRaw("({$afiliacionExpr}) = 'publico'");
            });
        }

        if ($stage !== '') {
            $query->where('stage', $stage);
        }
        if ($source !== '') {
            // WhatsApp filter: direct source OR any whatsapp activity (e.g. patient came via WA but has a clinical opp)
            if ($source === 'whatsapp') {
                $query->where(fn ($q) => $q
                    ->where('source', 'whatsapp')
                    ->orWhereHas('activities', fn ($a) => $a->where('type', 'whatsapp'))
                );
            } else {
                $query->where('source', $source);
            }
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
