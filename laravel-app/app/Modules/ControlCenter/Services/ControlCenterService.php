<?php

namespace App\Modules\ControlCenter\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ControlCenterService
{
    public const STATES = ['production', 'maintenance', 'readonly', 'suspended'];

    public function __construct(private readonly OperationalStateResolver $stateResolver)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function overview(): array
    {
        $organizations = DB::table('control_center_organizations')->count();
        $instances = DB::table('control_center_instances')->get();
        $states = $instances->groupBy('status')->map->count();
        $serviceCounts = DB::table('control_center_service_snapshots')
            ->select('state', DB::raw('count(*) as total'))
            ->groupBy('state')
            ->pluck('total', 'state');

        return [
            'summary' => [
                'organizations_total' => $organizations,
                'instances_total' => $instances->count(),
                'production' => (int) ($states['production'] ?? 0),
                'maintenance' => (int) ($states['maintenance'] ?? 0),
                'readonly' => (int) ($states['readonly'] ?? 0),
                'suspended' => (int) ($states['suspended'] ?? 0),
                'services_degraded' => (int) ($serviceCounts['degraded'] ?? 0),
                'updates_available' => (int) DB::table('control_center_deployments')->where('status', 'update_available')->count(),
            ],
            'organizations' => $this->organizationsQuery()->get()->map(fn ($row): array => $this->organizationCard($row))->values()->all(),
            'instances' => $this->instancesQuery()->get()->map(fn ($row): array => $this->instanceCard($row))->values()->all(),
            'services' => $this->services(),
            'usage' => $this->usageTotals(),
            'audit' => $this->auditQuery(limit: 6)->get()->map(fn ($row): array => $this->auditRow($row))->all(),
        ];
    }

    public function organizations(Request $request): LengthAwarePaginator
    {
        $query = $this->organizationsQuery();
        if ($request->filled('q')) {
            $search = '%' . $request->string('q')->toString() . '%';
            $query->where(function ($inner) use ($search): void {
                $inner->where('o.name', 'like', $search)
                    ->orWhere('o.slug', 'like', $search)
                    ->orWhere('o.legal_name', 'like', $search);
            });
        }

        return $query->orderBy('o.name')
            ->paginate($this->perPage($request))
            ->through(fn ($row): array => $this->organizationCard($row));
    }

    public function instances(Request $request): LengthAwarePaginator
    {
        $query = $this->instancesQuery();
        if ($request->filled('state')) {
            $query->where('i.status', $request->string('state')->toString());
        }
        if ($request->filled('organization_id')) {
            $query->where('i.organization_id', $request->integer('organization_id'));
        }
        if ($request->filled('q')) {
            $search = '%' . $request->string('q')->toString() . '%';
            $query->where(function ($inner) use ($search): void {
                $inner->where('i.name', 'like', $search)
                    ->orWhere('i.slug', 'like', $search)
                    ->orWhere('i.domain', 'like', $search)
                    ->orWhere('o.name', 'like', $search);
            });
        }

        return $query->orderBy('o.name')->orderBy('i.environment')
            ->paginate($this->perPage($request))
            ->through(fn ($row): array => $this->instanceCard($row));
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(int $id): array
    {
        $organization = $this->organizationsQuery()->where('o.id', $id)->first();
        abort_if($organization === null, 404);

        return [
            'organization' => $this->organizationCard($organization),
            'instances' => $this->instancesQuery()->where('i.organization_id', $id)->get()->map(fn ($row): array => $this->instanceCard($row))->all(),
            'contracts' => $this->contracts($id),
            'usage' => $this->usage(organizationId: $id),
            'audit' => $this->audit(organizationId: $id, limit: 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function instance(int $id): array
    {
        $instance = $this->instancesQuery()->where('i.id', $id)->first();
        abort_if($instance === null, 404);

        return [
            'organization' => $this->organizationCard($this->organizationsQuery()->where('o.id', $instance->organization_id)->first()),
            'instance' => $this->instanceCard($instance),
            'state' => $this->currentState((int) $instance->id),
            'features' => $this->features((int) $instance->id),
            'services' => $this->services((int) $instance->id),
            'deployments' => $this->deployments(instanceId: (int) $instance->id),
            'usage' => $this->usage(instanceId: (int) $instance->id),
            'audit' => $this->audit(instanceId: (int) $instance->id, limit: 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changeState(int $instanceId, Request $request): array
    {
        $validated = $request->validate([
            'state' => ['required', 'string', 'in:' . implode(',', self::STATES)],
            'reason' => ['nullable', 'string', 'max:2000'],
            'customer_message' => ['nullable', 'string', 'max:2000'],
            'confirm' => ['nullable', 'string'],
        ]);

        if ($validated['state'] !== 'production' && ($validated['confirm'] ?? null) !== $validated['state']) {
            throw ValidationException::withMessages(['confirm' => 'Confirma el estado operativo solicitado.']);
        }

        $instance = $this->findInstance($instanceId);
        $before = $this->currentState($instanceId);
        $now = Carbon::now();

        DB::table('control_center_operational_states')
            ->where('instance_id', $instanceId)
            ->whereNull('ends_at')
            ->update(['ends_at' => $now, 'updated_at' => $now]);

        $stateId = DB::table('control_center_operational_states')->insertGetId([
            'instance_id' => $instanceId,
            'state' => $validated['state'],
            'starts_at' => $now,
            'ends_at' => null,
            'reason' => $validated['reason'] ?? null,
            'customer_message' => $validated['customer_message'] ?? null,
            'changed_by_user_id' => Auth::id(),
            'changed_by_name' => $this->actorName(),
            'source' => 'manual',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('control_center_instances')->where('id', $instanceId)->update([
            'status' => $validated['state'],
            'updated_at' => $now,
        ]);

        $after = $this->currentState($instanceId);
        $this->auditLog((int) $instance->organization_id, $instanceId, 'state', 'state.changed', 'operational_state', $stateId, $before, $after, $request);
        $this->stateResolver->forget($instance->slug ?? null);

        return [
            'instance' => $this->instanceCard($this->instancesQuery()->where('i.id', $instanceId)->first()),
            'state' => $after,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function features(int $instanceId): array
    {
        return DB::table('control_center_features as f')
            ->leftJoin('control_center_instance_features as ife', function ($join) use ($instanceId): void {
                $join->on('ife.feature_id', '=', 'f.id')->where('ife.instance_id', '=', $instanceId);
            })
            ->select(['f.id', 'f.key', 'f.name', 'f.description', 'f.module', 'f.risk_level', 'f.requires_review', 'f.default_enabled', 'ife.enabled', 'ife.override_reason', 'ife.updated_at'])
            ->orderBy('f.module')
            ->orderBy('f.name')
            ->get()
            ->map(fn ($feature): array => [
                'id' => (int) $feature->id,
                'key' => (string) $feature->key,
                'name' => (string) $feature->name,
                'description' => $feature->description,
                'module' => $feature->module,
                'risk_level' => $feature->risk_level,
                'requires_review' => (bool) $feature->requires_review,
                'enabled' => $feature->enabled === null ? (bool) $feature->default_enabled : (bool) $feature->enabled,
                'override_reason' => $feature->override_reason,
                'updated_at' => $feature->updated_at,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function updateFeatures(int $instanceId, Request $request): array
    {
        $validated = $request->validate([
            'features' => ['required', 'array', 'min:1'],
            'features.*.key' => ['required', 'string'],
            'features.*.enabled' => ['required', 'boolean'],
            'features.*.reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $instance = $this->findInstance($instanceId);
        $before = $this->features($instanceId);
        $now = Carbon::now();
        $featureIds = DB::table('control_center_features')->pluck('id', 'key');

        foreach ($validated['features'] as $featureInput) {
            $key = $featureInput['key'];
            if (!isset($featureIds[$key])) {
                throw ValidationException::withMessages(['features' => "Feature no registrada: {$key}"]);
            }

            DB::table('control_center_instance_features')->updateOrInsert(
                [
                    'instance_id' => $instanceId,
                    'feature_id' => $featureIds[$key],
                    'environment' => 'production',
                ],
                [
                    'enabled' => (bool) $featureInput['enabled'],
                    'overridden_by_user_id' => Auth::id(),
                    'override_reason' => $featureInput['reason'] ?? null,
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $after = $this->features($instanceId);
        $this->auditLog((int) $instance->organization_id, $instanceId, 'feature', 'feature.updated', 'instance_feature', $instanceId, $before, $after, $request);

        return ['features' => $after];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function services(?int $instanceId = null): array
    {
        $query = DB::table('control_center_service_snapshots as ss')
            ->join('control_center_services as s', 's.id', '=', 'ss.service_id')
            ->join('control_center_instances as i', 'i.id', '=', 'ss.instance_id')
            ->join('control_center_organizations as o', 'o.id', '=', 'i.organization_id')
            ->select(['ss.*', 's.key', 's.name', 's.icon', 'i.name as instance_name', 'i.slug as instance_slug', 'o.name as organization_name']);

        if ($instanceId !== null) {
            $query->where('ss.instance_id', $instanceId);
        }

        return $query->orderBy('s.name')->orderBy('i.name')->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'instance_id' => (int) $row->instance_id,
            'instance_name' => $row->instance_name,
            'instance_slug' => $row->instance_slug,
            'organization_name' => $row->organization_name,
            'key' => $row->key,
            'name' => $row->name,
            'icon' => $row->icon,
            'state' => $row->state,
            'latency_ms' => $row->latency_ms === null ? null : (int) $row->latency_ms,
            'uptime_pct' => $row->uptime_pct === null ? null : (float) $row->uptime_pct,
            'message' => $row->message,
            'checked_at' => $row->checked_at,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plans(): array
    {
        return DB::table('control_center_plans')->orderBy('monthly_price')->get()->map(fn ($plan): array => [
            'id' => (int) $plan->id,
            'code' => $plan->code,
            'name' => $plan->name,
            'monthly_price' => $plan->monthly_price === null ? null : (float) $plan->monthly_price,
            'currency' => $plan->currency,
            'user_limit' => $plan->user_limit,
            'ai_token_limit' => $plan->ai_token_limit,
            'whatsapp_message_limit' => $plan->whatsapp_message_limit,
            'storage_gb_limit' => $plan->storage_gb_limit,
            'sla_target' => $plan->sla_target === null ? null : (float) $plan->sla_target,
            'support_level' => $plan->support_level,
            'modules' => $this->decodeJson($plan->modules_json),
            'is_active' => (bool) $plan->is_active,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deployments(?int $instanceId = null): array
    {
        $query = DB::table('control_center_deployments as d')
            ->join('control_center_instances as i', 'i.id', '=', 'd.instance_id')
            ->join('control_center_organizations as o', 'o.id', '=', 'i.organization_id')
            ->leftJoin('control_center_releases as r', 'r.id', '=', 'd.release_id')
            ->select(['d.*', 'i.name as instance_name', 'i.slug as instance_slug', 'o.name as organization_name', 'r.title as release_title']);

        if ($instanceId !== null) {
            $query->where('d.instance_id', $instanceId);
        }

        return $query->orderByDesc('d.deployed_at')->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'instance_id' => (int) $row->instance_id,
            'instance_name' => $row->instance_name,
            'instance_slug' => $row->instance_slug,
            'organization_name' => $row->organization_name,
            'version' => $row->version,
            'available_version' => $row->available_version,
            'channel' => $row->channel,
            'status' => $row->status,
            'release_title' => $row->release_title,
            'deployed_at' => $row->deployed_at,
            'scheduled_at' => $row->scheduled_at,
            'responsible' => $row->responsible,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function usage(?int $organizationId = null, ?int $instanceId = null): array
    {
        $query = DB::table('control_center_usage_metrics as u')
            ->leftJoin('control_center_organizations as o', 'o.id', '=', 'u.organization_id')
            ->leftJoin('control_center_instances as i', 'i.id', '=', 'u.instance_id')
            ->select(['u.*', 'o.name as organization_name', 'i.name as instance_name', 'i.slug as instance_slug']);

        if ($organizationId !== null) {
            $query->where('u.organization_id', $organizationId);
        }
        if ($instanceId !== null) {
            $query->where('u.instance_id', $instanceId);
        }

        return $query->orderBy('u.metric')->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'organization_id' => $row->organization_id === null ? null : (int) $row->organization_id,
            'organization_name' => $row->organization_name,
            'instance_id' => $row->instance_id === null ? null : (int) $row->instance_id,
            'instance_name' => $row->instance_name,
            'instance_slug' => $row->instance_slug,
            'metric' => $row->metric,
            'period_start' => $row->period_start,
            'period_end' => $row->period_end,
            'value' => (float) $row->value,
            'unit' => $row->unit,
            'cost' => $row->cost === null ? null : (float) $row->cost,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function audit(?int $organizationId = null, ?int $instanceId = null, int $limit = 50): array
    {
        return $this->auditQuery($organizationId, $instanceId, $limit)->get()->map(fn ($row): array => $this->auditRow($row))->all();
    }

    private function organizationsQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('control_center_organizations as o')
            ->leftJoin('control_center_contracts as co', function ($join): void {
                $join->on('co.organization_id', '=', 'o.id')->whereNull('co.instance_id');
            })
            ->leftJoin('control_center_plans as p', 'p.id', '=', 'co.plan_id')
            ->select(['o.*', 'p.name as plan_name', 'co.payment_status', 'co.contract_status']);
    }

    private function instancesQuery(): \Illuminate\Database\Query\Builder
    {
        return DB::table('control_center_instances as i')
            ->join('control_center_organizations as o', 'o.id', '=', 'i.organization_id')
            ->leftJoin('control_center_contracts as co', function ($join): void {
                $join->on('co.organization_id', '=', 'o.id')->whereNull('co.instance_id');
            })
            ->leftJoin('control_center_plans as p', 'p.id', '=', 'co.plan_id')
            ->select(['i.*', 'o.name as organization_name', 'o.slug as organization_slug', 'o.color as organization_color', 'o.initials as organization_initials', 'o.city as organization_city', 'p.name as plan_name', 'co.payment_status', 'co.contract_status']);
    }

    private function findInstance(int $id): object
    {
        $instance = DB::table('control_center_instances')->where('id', $id)->first();
        abort_if($instance === null, 404);

        return $instance;
    }

    /**
     * @return array<string, mixed>
     */
    private function organizationCard(?object $organization): array
    {
        if ($organization === null) {
            return [];
        }

        return [
            'id' => (int) $organization->id,
            'slug' => (string) $organization->slug,
            'name' => (string) $organization->name,
            'legal_name' => $organization->legal_name ?? null,
            'commercial_name' => $organization->commercial_name ?? null,
            'city' => $organization->city ?? null,
            'timezone' => $organization->timezone ?? 'America/Guayaquil',
            'color' => $organization->color ?? '#006b75',
            'initials' => $organization->initials ?? mb_substr((string) $organization->name, 0, 2),
            'plan_name' => $organization->plan_name ?? null,
            'payment_status' => $organization->payment_status ?? null,
            'contract_status' => $organization->contract_status ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function instanceCard(object $instance): array
    {
        return [
            'id' => (int) $instance->id,
            'organization_id' => (int) $instance->organization_id,
            'organization_name' => $instance->organization_name ?? null,
            'organization_slug' => $instance->organization_slug ?? null,
            'organization_color' => $instance->organization_color ?? '#006b75',
            'organization_initials' => $instance->organization_initials ?? null,
            'organization_city' => $instance->organization_city ?? null,
            'slug' => (string) $instance->slug,
            'name' => (string) $instance->name,
            'domain' => $instance->domain ?? null,
            'admin_url' => $instance->admin_url ?? null,
            'environment' => $instance->environment ?? 'production',
            'server_label' => $instance->server_label ?? null,
            'database_name' => $instance->database_name ?? null,
            'database_host' => $instance->database_host ?? null,
            'status' => $instance->status ?? 'production',
            'current_version' => $instance->current_version ?? null,
            'release_channel' => $instance->release_channel ?? 'stable',
            'last_activity_at' => $instance->last_activity_at ?? null,
            'plan_name' => $instance->plan_name ?? null,
            'payment_status' => $instance->payment_status ?? null,
            'contract_status' => $instance->contract_status ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentState(int $instanceId): array
    {
        $state = DB::table('control_center_operational_states')
            ->where('instance_id', $instanceId)
            ->whereNull('ends_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($state === null) {
            $instance = $this->findInstance($instanceId);

            return ['state' => $instance->status ?? 'production', 'reason' => null, 'changed_by_name' => null, 'starts_at' => null];
        }

        return [
            'id' => (int) $state->id,
            'state' => (string) $state->state,
            'reason' => $state->reason,
            'customer_message' => $state->customer_message,
            'changed_by_user_id' => $state->changed_by_user_id === null ? null : (int) $state->changed_by_user_id,
            'changed_by_name' => $state->changed_by_name,
            'source' => $state->source,
            'starts_at' => $state->starts_at,
            'ends_at' => $state->ends_at,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contracts(int $organizationId): array
    {
        return DB::table('control_center_contracts as co')
            ->leftJoin('control_center_plans as p', 'p.id', '=', 'co.plan_id')
            ->where('co.organization_id', $organizationId)
            ->select(['co.*', 'p.name as plan_name'])
            ->get()
            ->map(fn ($row): array => [
                'id' => (int) $row->id,
                'organization_id' => (int) $row->organization_id,
                'instance_id' => $row->instance_id === null ? null : (int) $row->instance_id,
                'plan_name' => $row->plan_name,
                'payment_status' => $row->payment_status,
                'contract_status' => $row->contract_status,
                'scope' => $row->scope,
                'starts_at' => $row->starts_at,
                'ends_at' => $row->ends_at,
            ])
            ->all();
    }

    private function auditQuery(?int $organizationId = null, ?int $instanceId = null, int $limit = 50): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('control_center_audit_logs as a')
            ->leftJoin('control_center_organizations as o', 'o.id', '=', 'a.organization_id')
            ->leftJoin('control_center_instances as i', 'i.id', '=', 'a.instance_id')
            ->select(['a.*', 'o.name as organization_name', 'o.slug as organization_slug', 'i.name as instance_name', 'i.slug as instance_slug'])
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit);

        if ($organizationId !== null) {
            $query->where('a.organization_id', $organizationId);
        }
        if ($instanceId !== null) {
            $query->where('a.instance_id', $instanceId);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function auditRow(object $row): array
    {
        return [
            'id' => (int) $row->id,
            'organization_id' => $row->organization_id === null ? null : (int) $row->organization_id,
            'organization_name' => $row->organization_name,
            'organization_slug' => $row->organization_slug,
            'instance_id' => $row->instance_id === null ? null : (int) $row->instance_id,
            'instance_name' => $row->instance_name,
            'instance_slug' => $row->instance_slug,
            'event_type' => $row->event_type,
            'action' => $row->action,
            'actor_user_id' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
            'actor_name' => $row->actor_name,
            'target_type' => $row->target_type,
            'target_id' => $row->target_id === null ? null : (int) $row->target_id,
            'before' => $this->decodeJson($row->before_json),
            'after' => $this->decodeJson($row->after_json),
            'created_at' => $row->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function usageTotals(): array
    {
        $rows = DB::table('control_center_usage_metrics')
            ->select('metric', 'unit', DB::raw('sum(value) as total'), DB::raw('sum(cost) as cost'))
            ->groupBy('metric', 'unit')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $totals[$row->metric] = [
                'value' => (float) $row->total,
                'unit' => $row->unit,
                'cost' => $row->cost === null ? null : (float) $row->cost,
            ];
        }

        return $totals;
    }

    private function auditLog(int $organizationId, int $instanceId, string $eventType, string $action, string $targetType, int $targetId, mixed $before, mixed $after, Request $request): void
    {
        DB::table('control_center_audit_logs')->insert([
            'organization_id' => $organizationId,
            'instance_id' => $instanceId,
            'event_type' => $eventType,
            'action' => $action,
            'actor_user_id' => Auth::id(),
            'actor_name' => $this->actorName(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_json' => json_encode($before),
            'after_json' => json_encode($after),
            'metadata_json' => json_encode(['source' => 'control_center_mvp']),
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'created_at' => Carbon::now(),
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(max((int) $request->integer('per_page', 25), 1), 100);
    }

    private function actorName(): ?string
    {
        $user = Auth::user();
        if ($user === null) {
            return null;
        }

        return $user->nombre ?: $user->username ?: $user->email;
    }

    private function decodeJson(mixed $json): mixed
    {
        if (!is_string($json) || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }
}
