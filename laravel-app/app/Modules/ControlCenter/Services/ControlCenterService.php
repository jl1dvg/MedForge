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
        $clients = DB::table('control_center_clients')->get();
        $states = $clients->groupBy('status')->map->count();
        $serviceCounts = DB::table('control_center_service_snapshots')
            ->select('state', DB::raw('count(*) as total'))
            ->groupBy('state')
            ->pluck('total', 'state');

        return [
            'summary' => [
                'clients_total' => $clients->count(),
                'production' => (int) ($states['production'] ?? 0),
                'maintenance' => (int) ($states['maintenance'] ?? 0),
                'readonly' => (int) ($states['readonly'] ?? 0),
                'suspended' => (int) ($states['suspended'] ?? 0),
                'services_degraded' => (int) ($serviceCounts['degraded'] ?? 0),
                'updates_available' => (int) DB::table('control_center_deployments')->where('status', 'update_available')->count(),
            ],
            'clients' => $clients->map(fn ($client): array => $this->clientCard($client))->values()->all(),
            'services' => $this->services(),
            'usage' => $this->usageTotals(),
            'audit' => $this->auditQuery(limit: 6)->get()->map(fn ($row): array => $this->auditRow($row))->all(),
        ];
    }

    public function clients(Request $request): LengthAwarePaginator
    {
        $query = DB::table('control_center_clients as c')
            ->leftJoin('control_center_contracts as co', 'co.client_id', '=', 'c.id')
            ->leftJoin('control_center_plans as p', 'p.id', '=', 'co.plan_id')
            ->select(['c.*', 'p.name as plan_name', 'co.payment_status', 'co.contract_status']);

        if ($request->filled('state')) {
            $query->where('c.status', $request->string('state')->toString());
        }

        if ($request->filled('q')) {
            $search = '%' . $request->string('q')->toString() . '%';
            $query->where(function ($inner) use ($search): void {
                $inner->where('c.name', 'like', $search)
                    ->orWhere('c.slug', 'like', $search)
                    ->orWhere('c.domain', 'like', $search);
            });
        }

        $perPage = min(max((int) $request->integer('per_page', 25), 1), 100);

        return $query->orderBy('c.name')->paginate($perPage)->through(fn ($client): array => $this->clientCard($client));
    }

    /**
     * @return array<string, mixed>
     */
    public function client(int $id): array
    {
        $client = DB::table('control_center_clients as c')
            ->leftJoin('control_center_contracts as co', 'co.client_id', '=', 'c.id')
            ->leftJoin('control_center_plans as p', 'p.id', '=', 'co.plan_id')
            ->where('c.id', $id)
            ->select(['c.*', 'p.name as plan_name', 'p.code as plan_code', 'co.payment_status', 'co.contract_status', 'co.starts_at', 'co.ends_at'])
            ->first();

        abort_if($client === null, 404);

        return [
            'client' => $this->clientCard($client),
            'state' => $this->currentState((int) $client->id),
            'features' => $this->features((int) $client->id),
            'services' => $this->services((int) $client->id),
            'deployments' => $this->deployments(clientId: (int) $client->id),
            'usage' => $this->usage(clientId: (int) $client->id),
            'audit' => $this->audit(clientId: (int) $client->id, limit: 10),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function changeState(int $clientId, Request $request): array
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

        $client = $this->findClient($clientId);
        $before = $this->currentState($clientId);
        $now = Carbon::now();

        DB::table('control_center_operational_states')
            ->where('client_id', $clientId)
            ->whereNull('ends_at')
            ->update(['ends_at' => $now, 'updated_at' => $now]);

        $stateId = DB::table('control_center_operational_states')->insertGetId([
            'client_id' => $clientId,
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

        DB::table('control_center_clients')->where('id', $clientId)->update([
            'status' => $validated['state'],
            'updated_at' => $now,
        ]);

        $after = $this->currentState($clientId);
        $this->auditLog($clientId, 'state', 'state.changed', 'operational_state', $stateId, $before, $after, $request);
        $this->stateResolver->forget($client->slug ?? null);

        return [
            'client' => $this->clientCard($this->findClient($clientId)),
            'state' => $after,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function features(int $clientId): array
    {
        return DB::table('control_center_features as f')
            ->leftJoin('control_center_client_features as cf', function ($join) use ($clientId): void {
                $join->on('cf.feature_id', '=', 'f.id')->where('cf.client_id', '=', $clientId);
            })
            ->select(['f.id', 'f.key', 'f.name', 'f.description', 'f.module', 'f.risk_level', 'f.requires_review', 'f.default_enabled', 'cf.enabled', 'cf.override_reason', 'cf.updated_at'])
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
    public function updateFeatures(int $clientId, Request $request): array
    {
        $validated = $request->validate([
            'features' => ['required', 'array', 'min:1'],
            'features.*.key' => ['required', 'string'],
            'features.*.enabled' => ['required', 'boolean'],
            'features.*.reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->findClient($clientId);
        $before = $this->features($clientId);
        $now = Carbon::now();
        $featureIds = DB::table('control_center_features')->pluck('id', 'key');

        foreach ($validated['features'] as $featureInput) {
            $key = $featureInput['key'];
            if (!isset($featureIds[$key])) {
                throw ValidationException::withMessages(['features' => "Feature no registrada: {$key}"]);
            }

            DB::table('control_center_client_features')->updateOrInsert(
                [
                    'client_id' => $clientId,
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

        $after = $this->features($clientId);
        $this->auditLog($clientId, 'feature', 'feature.updated', 'client_feature', $clientId, $before, $after, $request);

        return ['features' => $after];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function services(?int $clientId = null): array
    {
        $query = DB::table('control_center_service_snapshots as ss')
            ->join('control_center_services as s', 's.id', '=', 'ss.service_id')
            ->join('control_center_clients as c', 'c.id', '=', 'ss.client_id')
            ->select(['ss.*', 's.key', 's.name', 's.icon', 'c.name as client_name', 'c.slug as client_slug']);

        if ($clientId !== null) {
            $query->where('ss.client_id', $clientId);
        }

        return $query->orderBy('s.name')->orderBy('c.name')->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'client_id' => (int) $row->client_id,
            'client_name' => $row->client_name,
            'client_slug' => $row->client_slug,
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
        return DB::table('control_center_plans')
            ->orderBy('monthly_price')
            ->get()
            ->map(fn ($plan): array => [
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
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function deployments(?int $clientId = null): array
    {
        $query = DB::table('control_center_deployments as d')
            ->join('control_center_clients as c', 'c.id', '=', 'd.client_id')
            ->leftJoin('control_center_releases as r', 'r.id', '=', 'd.release_id')
            ->select(['d.*', 'c.name as client_name', 'c.slug as client_slug', 'r.title as release_title']);

        if ($clientId !== null) {
            $query->where('d.client_id', $clientId);
        }

        return $query->orderByDesc('d.deployed_at')->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'client_id' => (int) $row->client_id,
            'client_name' => $row->client_name,
            'client_slug' => $row->client_slug,
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
    public function usage(?int $clientId = null): array
    {
        $query = DB::table('control_center_usage_metrics as u')
            ->leftJoin('control_center_clients as c', 'c.id', '=', 'u.client_id')
            ->select(['u.*', 'c.name as client_name', 'c.slug as client_slug']);

        if ($clientId !== null) {
            $query->where('u.client_id', $clientId);
        }

        return $query->orderBy('u.metric')->get()->map(fn ($row): array => [
            'id' => (int) $row->id,
            'client_id' => $row->client_id === null ? null : (int) $row->client_id,
            'client_name' => $row->client_name,
            'client_slug' => $row->client_slug,
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
    public function audit(?int $clientId = null, int $limit = 50): array
    {
        return $this->auditQuery($clientId, $limit)->get()->map(fn ($row): array => $this->auditRow($row))->all();
    }

    private function findClient(int $id): object
    {
        $client = DB::table('control_center_clients')->where('id', $id)->first();
        abort_if($client === null, 404);

        return $client;
    }

    /**
     * @return array<string, mixed>
     */
    private function clientCard(object $client): array
    {
        return [
            'id' => (int) $client->id,
            'slug' => (string) $client->slug,
            'name' => (string) $client->name,
            'legal_name' => $client->legal_name ?? null,
            'domain' => $client->domain ?? null,
            'admin_url' => $client->admin_url ?? null,
            'environment' => $client->environment ?? 'production',
            'server_label' => $client->server_label ?? null,
            'database_name' => $client->database_name ?? null,
            'database_host' => $client->database_host ?? null,
            'city' => $client->city ?? null,
            'timezone' => $client->timezone ?? 'America/Guayaquil',
            'status' => $client->status ?? 'production',
            'current_version' => $client->current_version ?? null,
            'release_channel' => $client->release_channel ?? 'stable',
            'color' => $client->color ?? '#006b75',
            'initials' => $client->initials ?? mb_substr((string) $client->name, 0, 2),
            'last_activity_at' => $client->last_activity_at ?? null,
            'plan_name' => $client->plan_name ?? null,
            'payment_status' => $client->payment_status ?? null,
            'contract_status' => $client->contract_status ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function currentState(int $clientId): array
    {
        $state = DB::table('control_center_operational_states')
            ->where('client_id', $clientId)
            ->whereNull('ends_at')
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();

        if ($state === null) {
            $client = $this->findClient($clientId);

            return ['state' => $client->status ?? 'production', 'reason' => null, 'changed_by_name' => null, 'starts_at' => null];
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

    private function auditQuery(?int $clientId = null, int $limit = 50): \Illuminate\Database\Query\Builder
    {
        $query = DB::table('control_center_audit_logs as a')
            ->leftJoin('control_center_clients as c', 'c.id', '=', 'a.client_id')
            ->select(['a.*', 'c.name as client_name', 'c.slug as client_slug'])
            ->orderByDesc('a.created_at')
            ->orderByDesc('a.id')
            ->limit($limit);

        if ($clientId !== null) {
            $query->where('a.client_id', $clientId);
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
            'client_id' => $row->client_id === null ? null : (int) $row->client_id,
            'client_name' => $row->client_name,
            'client_slug' => $row->client_slug,
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

    private function auditLog(int $clientId, string $eventType, string $action, string $targetType, int $targetId, mixed $before, mixed $after, Request $request): void
    {
        DB::table('control_center_audit_logs')->insert([
            'client_id' => $clientId,
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
