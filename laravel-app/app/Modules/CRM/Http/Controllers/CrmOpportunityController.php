<?php

namespace App\Modules\CRM\Http\Controllers;

use App\Models\CrmOpportunity;
use App\Modules\CRM\Services\CrmActivityService;
use App\Modules\CRM\Services\CrmOpportunityService;
use App\Modules\CRM\Services\CrmOpportunityValueResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CrmOpportunityController
{
    private const SOURCE_TYPE_TO_SOURCE = [
        'solicitud_procedimiento' => 'solicitud',
        'consulta_examenes'       => 'examen',
        'whatsapp_lead'           => 'whatsapp',
    ];

    private const LEGACY_SOURCE_TYPE = 'legacy_crm_lead';

    public function __construct(
        private readonly CrmOpportunityService $opportunityService,
        private readonly CrmActivityService $activityService,
        private readonly CrmOpportunityValueResolver $valueResolver,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if (!Auth::check()) {
            return response()->json(['error' => 'Sesión expirada'], 401);
        }

        $limit          = min(max((int) $request->query('limit', 25), 1), 2000);
        $offset         = max((int) $request->query('offset', 0), 0);
        $stage          = trim((string) $request->query('stage', ''));
        $source         = trim((string) $request->query('source', ''));
        $phase          = trim((string) $request->query('phase', ''));
        $search         = trim((string) $request->query('search', ''));
        $afiliacion     = trim((string) $request->query('afiliacion', ''));
        $urgent         = filter_var($request->query('urgent', false), FILTER_VALIDATE_BOOLEAN);
        $includeClosed  = filter_var($request->query('include_closed', false), FILTER_VALIDATE_BOOLEAN);

        $query = CrmOpportunity::query();

        $this->applyPatientAffiliationFilter($query, $afiliacion);

        // Default: active pipeline only (ganado/perdido are historical)
        if ($stage !== '') {
            $query->where('stage', $stage);
        } elseif (!$includeClosed) {
            $query->whereNotIn('stage', ['ganado', 'perdido']);
        }
        if ($source !== '') {
            $this->applyEffectiveSourceFilter($query, $source);
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
            ->limit($limit)->offset($offset)
            ->with('contact')
            ->get();
        $this->appendEffectiveSource($rows, $source);

        $sourceMap = $this->bulkLoadSourceData($rows);

        $data = $rows->map(function (CrmOpportunity $opp) use ($sourceMap): array {
            $src = $sourceMap[$opp->id] ?? null;
            return array_merge($opp->toArray(), [
                // valor_estimado surfaces at the root level for easy frontend consumption.
                // It is computed dynamically — see CrmOpportunityValueResolver for the logic.
                'valor_estimado' => $src['valor_estimado'] ?? 0,
                'source_data'    => $src ? [
                    'procedimiento' => $src['procedimiento'],
                    'ojo'           => $src['ojo'],
                    'doctor'        => $src['doctor'],
                ] : null,
            ]);
        });

        return response()->json([
            'data' => $data,
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

    /**
     * Bulk-loads source data for known source_types.
     * Delegates valor_estimado calculation to CrmOpportunityValueResolver.
     * Unknown types (legacy_crm_lead, whatsapp_lead, manual, null) return null — no crash.
     *
     * @param  Collection<int, CrmOpportunity>  $rows
     * @return array<int, array{procedimiento:string|null,ojo:string|null,doctor:string|null,valor_estimado:float}|null>
     */
    private function bulkLoadSourceData(Collection $rows): array
    {
        $map    = [];
        $byType = $rows->groupBy('source_type');

        // ── solicitud_procedimiento ──────────────────────────────────────────
        if ($byType->has('solicitud_procedimiento')) {
            $idsByOpp  = $byType['solicitud_procedimiento']->pluck('source_id', 'id');
            $sourceIds = $idsByOpp->values()->filter()->unique()->values();

            $resolved = $this->valueResolver->resolveForSolicitudes($sourceIds);

            foreach ($idsByOpp as $oppId => $procId) {
                $map[(int) $oppId] = $procId ? ($resolved[(int) $procId] ?? null) : null;
            }
        }

        // ── consulta_examenes ─────────────────────────────────────────────────
        // valor_estimado = 0 until a clear tarifario linkage for exams is defined.
        if ($byType->has('consulta_examenes')) {
            $idsByOpp  = $byType['consulta_examenes']->pluck('source_id', 'id');
            $sourceIds = $idsByOpp->values()->filter()->unique()->values();

            if ($sourceIds->isNotEmpty()) {
                $exams = DB::table('consulta_examenes')
                    ->whereIn('id', $sourceIds)
                    ->select(['id', 'examen_nombre', 'lateralidad'])
                    ->get()
                    ->keyBy('id');

                foreach ($idsByOpp as $oppId => $examId) {
                    $exam = $examId ? ($exams[(int) $examId] ?? null) : null;
                    $map[(int) $oppId] = $exam ? [
                        'procedimiento'  => $exam->examen_nombre,
                        'ojo'            => $exam->lateralidad,
                        'doctor'         => null,
                        'valor_estimado' => 0.0,
                    ] : null;
                }
            }
        }

        // legacy_crm_lead, whatsapp_lead, manual, null → source_data = null (sin crash)

        return $map;
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

    private function applyEffectiveSourceFilter(Builder $query, string $source): void
    {
        $sourceTypes = array_keys(array_filter(
            self::SOURCE_TYPE_TO_SOURCE,
            static fn (string $mappedSource): bool => $mappedSource === $source,
        ));

        $query->where(function (Builder $q) use ($source, $sourceTypes): void {
            $q->where(function (Builder $direct) use ($source): void {
                $direct->where('source', $source);

                if ($source === 'whatsapp') {
                    $direct->where(function (Builder $legacy) {
                        $legacy->whereNull('source_type')
                            ->orWhere('source_type', '<>', self::LEGACY_SOURCE_TYPE);
                    });
                }
            });

            if ($sourceTypes !== []) {
                $q->orWhereIn('source_type', $sourceTypes);
            }

            $this->orWhereOperationalSource($q, $source);

            $q->orWhereHas('activities', function (Builder $activity) use ($source, $sourceTypes): void {
                $activity->where('type', $source);

                if ($sourceTypes !== []) {
                    $activity->orWhereIn('source_type', $sourceTypes);
                }
            });
        });
    }

    private function orWhereOperationalSource(Builder $query, string $source): void
    {
        foreach ($this->operationalSourceTables($source) as [$tableName, $columnName]) {
            if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, $columnName)) {
                continue;
            }

            $query->orWhereExists(function ($sub) use ($tableName, $columnName): void {
                $sub->selectRaw('1')
                    ->from($tableName)
                    ->whereColumn("{$tableName}.{$columnName}", 'crm_opportunities.id');
            });
        }
    }

    /**
     * Existing manual opportunities can later receive Solicitud/Examen/WhatsApp
     * activities. The central CRM list needs that operational signal for filters
     * and labels without overwriting the original stored source.
     */
    private function appendEffectiveSource(Collection $rows, string $selectedSource): void
    {
        $ids = $rows->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $operationalSources = $this->operationalSourcesFor($ids, $selectedSource);

        $activitySources = DB::table('crm_activities')
            ->select('opportunity_id', 'type', 'source_type', 'created_at')
            ->whereIn('opportunity_id', $ids)
            ->where(function ($q): void {
                $q->whereIn('type', ['whatsapp', 'solicitud', 'examen'])
                    ->orWhereIn('source_type', array_keys(self::SOURCE_TYPE_TO_SOURCE));
            })
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('opportunity_id');

        $rows->each(function (CrmOpportunity $opportunity) use ($activitySources, $operationalSources, $selectedSource): void {
            $effectiveSources = $this->effectiveSourcesFor(
                $opportunity,
                $activitySources->get($opportunity->id, collect()),
                collect($operationalSources->get($opportunity->id, [])),
            );

            $opportunity->setAttribute(
                'effective_source',
                $this->preferredEffectiveSource($effectiveSources, $selectedSource),
            );
            $opportunity->setAttribute('effective_sources', $effectiveSources);
        });
    }

    private function operationalSourcesFor(array $opportunityIds, string $selectedSource): Collection
    {
        $sources = collect();
        $sourceOrder = in_array($selectedSource, ['solicitud', 'examen'], true)
            ? [$selectedSource, ...array_values(array_diff(['solicitud', 'examen'], [$selectedSource]))]
            : ['solicitud', 'examen'];

        foreach ($sourceOrder as $source) {
            foreach ($this->operationalSourceTables($source) as [$tableName, $columnName]) {
                if (!Schema::hasTable($tableName) || !Schema::hasColumn($tableName, $columnName)) {
                    continue;
                }

                DB::table($tableName)
                    ->whereIn($columnName, $opportunityIds)
                    ->pluck($columnName)
                    ->each(static function ($opportunityId) use ($sources, $source): void {
                        $key = (int) $opportunityId;
                        $current = $sources->get($key, []);

                        if (!in_array($source, $current, true)) {
                            $current[] = $source;
                            $sources->put($key, $current);
                        }
                    });
            }
        }

        return $sources;
    }

    private function operationalSourceTables(string $source): array
    {
        return match ($source) {
            'solicitud' => [
                ['solicitud_procedimiento', 'crm_opportunity_id'],
                ['solicitud_crm_detalles', 'crm_opportunity_id'],
            ],
            'examen' => [
                ['consulta_examenes', 'crm_opportunity_id'],
                ['examen_crm_detalles', 'crm_opportunity_id'],
            ],
            default => [],
        };
    }

    private function effectiveSourcesFor(
        CrmOpportunity $opportunity,
        Collection $activitySources,
        Collection $operationalSources,
    ): array
    {
        $sources = [];

        foreach ($operationalSources as $operationalSource) {
            if (in_array($operationalSource, ['solicitud', 'examen'], true)) {
                $sources[] = $operationalSource;
            }
        }

        foreach ($activitySources as $activity) {
            $activitySource = self::SOURCE_TYPE_TO_SOURCE[$activity->source_type] ?? $activity->type;
            if (in_array($activitySource, ['solicitud', 'examen'], true)) {
                $sources[] = $activitySource;
            }
        }

        if (isset(self::SOURCE_TYPE_TO_SOURCE[$opportunity->source_type])
            && in_array(self::SOURCE_TYPE_TO_SOURCE[$opportunity->source_type], ['solicitud', 'examen'], true)
        ) {
            $sources[] = self::SOURCE_TYPE_TO_SOURCE[$opportunity->source_type];
        }

        if (in_array($opportunity->source, ['solicitud', 'examen'], true)) {
            $sources[] = $opportunity->source;
        }

        $sources = array_values(array_unique($sources));

        if ($sources !== []) {
            return $sources;
        }

        if ($opportunity->source_type === self::LEGACY_SOURCE_TYPE) {
            return ['legacy'];
        }

        if (isset(self::SOURCE_TYPE_TO_SOURCE[$opportunity->source_type])) {
            return [self::SOURCE_TYPE_TO_SOURCE[$opportunity->source_type]];
        }

        foreach ($activitySources as $activity) {
            if (isset(self::SOURCE_TYPE_TO_SOURCE[$activity->source_type])) {
                return [self::SOURCE_TYPE_TO_SOURCE[$activity->source_type]];
            }

            if (in_array($activity->type, ['whatsapp', 'solicitud', 'examen'], true)) {
                return [$activity->type];
            }
        }

        return [$opportunity->source ?: 'manual'];
    }

    private function preferredEffectiveSource(array $effectiveSources, string $selectedSource): string
    {
        if (in_array($selectedSource, $effectiveSources, true)) {
            return $selectedSource;
        }

        return $effectiveSources[0] ?? 'manual';
    }

    private function applyPatientAffiliationFilter(Builder $query, string $afiliacion): void
    {
        if ($afiliacion === 'publico') {
            $query->whereRaw('1 = 0');
            return;
        }

        $this->applyPublicoExclusion($query);

        if ($afiliacion !== '') {
            $query->whereExists(fn ($sub) => $this->patientAffiliationSubquery($sub, $afiliacion));
        }
    }

    /**
     * Permanently excludes opportunities whose afiliación resolves to categoria = 'publico'
     * in afiliacion_categoria_map.
     *
     * Two layers:
     *   1. crm_opportunities.afiliacion_tipo already resolved to 'publico'
     *   2. source_type = solicitud_procedimiento whose afiliacion maps to 'publico' via
     *      the canonical afiliacion_norm key (same normalization as AfiliacionDimensionService)
     *      with a raw LOWER/TRIM fallback for robustness.
     *
     * Does NOT depend on crm_contacts.cedula → patient_data JOIN (which silently passes
     * through contacts without a cedula match).
     * Does NOT exclude if no mapping is found (sin_dato passes through).
     */
    private function applyPublicoExclusion(Builder $query): void
    {
        // Layer 1: direct field on the opportunity row
        $query->where(function (Builder $q): void {
            $q->whereNull('afiliacion_tipo')
              ->orWhere('afiliacion_tipo', '!=', 'publico');
        });

        // Layer 2: solicitud_procedimiento source whose afiliación maps to publico
        $normExpr = $this->normalizeSqlKey('sp.afiliacion');
        $query->whereNotExists(function ($sub) use ($normExpr): void {
            $sub->selectRaw('1')
                ->from('solicitud_procedimiento as sp')
                ->join('afiliacion_categoria_map as acm', function ($join) use ($normExpr): void {
                    $join->on(DB::raw('acm.afiliacion_norm'), '=', DB::raw($normExpr))
                         ->orOn(
                             DB::raw('LOWER(TRIM(acm.afiliacion_raw))'),
                             '=',
                             DB::raw('LOWER(TRIM(sp.afiliacion))')
                         );
                })
                ->whereColumn('sp.id', 'crm_opportunities.source_id')
                ->whereRaw("crm_opportunities.source_type = 'solicitud_procedimiento'")
                ->where('acm.categoria', 'publico');
        });
    }

    /**
     * Canonical SQL normalization matching AfiliacionDimensionService::normalizeSqlKey().
     * Lowercases, strips accents, replaces spaces and dashes with underscores.
     */
    private function normalizeSqlKey(string $sqlExpr): string
    {
        $accents = [
            'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N',
            'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n',
        ];
        $normalized = $sqlExpr;
        foreach ($accents as $from => $to) {
            $normalized = "REPLACE({$normalized}, '{$from}', '{$to}')";
        }
        $normalized = "LOWER({$normalized})";
        $normalized = "REPLACE(REPLACE(TRIM({$normalized}), ' ', '_'), '-', '_')";
        return $normalized;
    }

    private function patientAffiliationSubquery($sub, string $tipo): void
    {
        $sub->selectRaw('1')
            ->from('crm_contacts as cc_afil')
            ->leftJoin('patient_data as pd_afil', 'pd_afil.hc_number', '=', 'cc_afil.cedula')
            ->whereColumn('cc_afil.id', 'crm_opportunities.contact_id')
            ->whereRaw($this->patientAffiliationCaseSql() . ' = ?', [$tipo]);
    }

    private function patientAffiliationCaseSql(): string
    {
        return "CASE
            WHEN LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%iess%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%issfa%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%isspol%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%msp%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%ministerio%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%salud%publica%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%red%publica%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%campesino%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%jubilado%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%seguro%general%'
                OR LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%seguro%voluntario%'
                THEN 'publico'
            WHEN LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%particular%'
                THEN 'particular'
            WHEN LOWER(COALESCE(pd_afil.afiliacion, '')) LIKE '%fundaci%'
                THEN 'fundacional'
            WHEN pd_afil.afiliacion IS NULL OR TRIM(pd_afil.afiliacion) = ''
                THEN 'sin_dato'
            ELSE 'privado'
        END";
    }
}
