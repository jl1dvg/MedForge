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
    public const DATA_SOURCES = ['real', 'manual', 'seed', 'telemetry', 'pipeline', 'placeholder', 'pending'];

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
    public function createOrganization(Request $request): array
    {
        $validated = $request->validate([
            'slug' => ['required', 'string', 'max:120', 'unique:control_center_organizations,slug'],
            'name' => ['required', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:220'],
            'ruc' => ['nullable', 'string', 'max:32'],
            'commercial_name' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:24'],
            'initials' => ['nullable', 'string', 'max:12'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);

        $now = Carbon::now();
        $id = DB::table('control_center_organizations')->insertGetId([
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'legal_name' => $validated['legal_name'] ?? null,
            'ruc' => $validated['ruc'] ?? null,
            'commercial_name' => $validated['commercial_name'] ?? null,
            'city' => $validated['city'] ?? null,
            'timezone' => $validated['timezone'] ?? 'America/Guayaquil',
            'color' => $validated['color'] ?? null,
            'initials' => $validated['initials'] ?? null,
            'source' => $validated['source'] ?? 'manual',
            'last_verified_at' => $now,
            'metadata_json' => json_encode(['source' => $validated['source'] ?? 'manual']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $organization = $this->organizationsQuery()->where('o.id', $id)->first();
        $this->auditLog($id, null, 'organization', 'organization.created', 'organization', $id, null, $this->organizationCard($organization), $request, ['source' => $validated['source'] ?? 'manual']);

        return ['organization' => $this->organizationCard($organization)];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateOrganization(int $id, Request $request): array
    {
        $organization = $this->organizationsQuery()->where('o.id', $id)->first();
        abort_if($organization === null, 404);
        $before = $this->organizationCard($organization);

        $validated = $request->validate([
            'slug' => ['sometimes', 'string', 'max:120'],
            'name' => ['sometimes', 'string', 'max:180'],
            'legal_name' => ['nullable', 'string', 'max:220'],
            'ruc' => ['nullable', 'string', 'max:32'],
            'commercial_name' => ['nullable', 'string', 'max:180'],
            'city' => ['nullable', 'string', 'max:120'],
            'timezone' => ['nullable', 'string', 'max:80'],
            'color' => ['nullable', 'string', 'max:24'],
            'initials' => ['nullable', 'string', 'max:12'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);
        $this->assertUniqueSlug('control_center_organizations', $validated['slug'] ?? null, $id);

        $updates = $this->onlyProvided($validated, ['slug', 'name', 'legal_name', 'ruc', 'commercial_name', 'city', 'timezone', 'color', 'initials', 'source']);
        $updates['last_verified_at'] = Carbon::now();
        $updates['updated_at'] = Carbon::now();

        DB::table('control_center_organizations')->where('id', $id)->update($updates);
        $afterRow = $this->organizationsQuery()->where('o.id', $id)->first();
        $after = $this->organizationCard($afterRow);
        $this->auditLog($id, null, 'organization', 'organization.updated', 'organization', $id, $before, $after, $request, ['source' => $validated['source'] ?? ($after['data_quality']['source'] ?? 'manual')]);

        return ['organization' => $after];
    }

    /**
     * @return array<string, mixed>
     */
    public function createInstance(Request $request): array
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:control_center_organizations,id'],
            'slug' => ['required', 'string', 'max:120', 'unique:control_center_instances,slug'],
            'name' => ['required', 'string', 'max:180'],
            'domain' => ['nullable', 'string', 'max:180', 'unique:control_center_instances,domain'],
            'admin_url' => ['nullable', 'string', 'max:240'],
            'environment' => ['nullable', 'string', 'max:60'],
            'server_label' => ['nullable', 'string', 'max:120'],
            'database_name' => ['nullable', 'string', 'max:120'],
            'database_host' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', 'string', 'in:' . implode(',', self::STATES)],
            'current_version' => ['nullable', 'string', 'max:80'],
            'release_channel' => ['nullable', 'string', 'max:80'],
            'telemetry_token' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);

        $now = Carbon::now();
        $id = DB::table('control_center_instances')->insertGetId([
            'organization_id' => $validated['organization_id'],
            'slug' => $validated['slug'],
            'name' => $validated['name'],
            'domain' => $validated['domain'] ?? null,
            'admin_url' => $validated['admin_url'] ?? null,
            'environment' => $validated['environment'] ?? 'production',
            'server_label' => $validated['server_label'] ?? null,
            'database_name' => $validated['database_name'] ?? null,
            'database_host' => $validated['database_host'] ?? null,
            'status' => $validated['status'] ?? 'production',
            'current_version' => $validated['current_version'] ?? null,
            'release_channel' => $validated['release_channel'] ?? 'stable',
            'telemetry_token_hash' => isset($validated['telemetry_token']) ? hash('sha256', $validated['telemetry_token']) : null,
            'telemetry_status' => 'pending',
            'source' => $validated['source'] ?? 'manual',
            'last_verified_at' => $now,
            'metadata_json' => json_encode(['source' => $validated['source'] ?? 'manual']),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $instance = $this->instancesQuery()->where('i.id', $id)->first();
        $this->auditLog((int) $validated['organization_id'], $id, 'instance', 'instance.created', 'instance', $id, null, $this->instanceCard($instance), $request);

        return ['instance' => $this->instanceCard($instance)];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateInstance(int $id, Request $request): array
    {
        $instance = $this->instancesQuery()->where('i.id', $id)->first();
        abort_if($instance === null, 404);
        $before = $this->instanceCard($instance);

        $validated = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:control_center_organizations,id'],
            'slug' => ['sometimes', 'string', 'max:120'],
            'name' => ['sometimes', 'string', 'max:180'],
            'domain' => ['nullable', 'string', 'max:180'],
            'admin_url' => ['nullable', 'string', 'max:240'],
            'environment' => ['nullable', 'string', 'max:60'],
            'server_label' => ['nullable', 'string', 'max:120'],
            'database_name' => ['nullable', 'string', 'max:120'],
            'database_host' => ['nullable', 'string', 'max:180'],
            'status' => ['nullable', 'string', 'in:' . implode(',', self::STATES)],
            'current_version' => ['nullable', 'string', 'max:80'],
            'release_channel' => ['nullable', 'string', 'max:80'],
            'telemetry_token' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);
        $this->assertUniqueSlug('control_center_instances', $validated['slug'] ?? null, $id);
        $this->assertUniqueValue('control_center_instances', 'domain', $validated['domain'] ?? null, $id);

        $updates = $this->onlyProvided($validated, ['organization_id', 'slug', 'name', 'domain', 'admin_url', 'environment', 'server_label', 'database_name', 'database_host', 'status', 'current_version', 'release_channel', 'source']);
        if (isset($validated['telemetry_token'])) {
            $updates['telemetry_token_hash'] = hash('sha256', $validated['telemetry_token']);
        }
        $updates['last_verified_at'] = Carbon::now();
        $updates['updated_at'] = Carbon::now();

        DB::table('control_center_instances')->where('id', $id)->update($updates);
        $afterRow = $this->instancesQuery()->where('i.id', $id)->first();
        $after = $this->instanceCard($afterRow);
        $this->auditLog((int) $after['organization_id'], $id, 'instance', 'instance.updated', 'instance', $id, $before, $after, $request);
        $this->stateResolver->forget($after['slug'] ?? null);

        return ['instance' => $after];
    }

    /**
     * @return array<string, mixed>
     */
    public function createPlan(Request $request): array
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80', 'unique:control_center_plans,code'],
            'name' => ['required', 'string', 'max:160'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'ai_token_limit' => ['nullable', 'integer', 'min:0'],
            'whatsapp_message_limit' => ['nullable', 'integer', 'min:0'],
            'storage_gb_limit' => ['nullable', 'integer', 'min:0'],
            'sla_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'support_level' => ['nullable', 'string', 'max:120'],
            'modules' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);

        $now = Carbon::now();
        $id = DB::table('control_center_plans')->insertGetId([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'monthly_price' => $validated['monthly_price'] ?? null,
            'currency' => $validated['currency'] ?? 'USD',
            'user_limit' => $validated['user_limit'] ?? null,
            'ai_token_limit' => $validated['ai_token_limit'] ?? null,
            'whatsapp_message_limit' => $validated['whatsapp_message_limit'] ?? null,
            'storage_gb_limit' => $validated['storage_gb_limit'] ?? null,
            'sla_target' => $validated['sla_target'] ?? null,
            'support_level' => $validated['support_level'] ?? null,
            'modules_json' => json_encode($validated['modules'] ?? []),
            'is_active' => $validated['is_active'] ?? true,
            'source' => $validated['source'] ?? 'manual',
            'last_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $plan = $this->planById($id);
        $this->auditLog(null, null, 'plan', 'plan.created', 'plan', $id, null, $plan, $request);

        return ['plan' => $plan];
    }

    /**
     * @return array<string, mixed>
     */
    public function updatePlan(int $id, Request $request): array
    {
        $before = $this->planById($id);
        abort_if($before === [], 404);

        $validated = $request->validate([
            'code' => ['sometimes', 'string', 'max:80'],
            'name' => ['sometimes', 'string', 'max:160'],
            'monthly_price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'user_limit' => ['nullable', 'integer', 'min:0'],
            'ai_token_limit' => ['nullable', 'integer', 'min:0'],
            'whatsapp_message_limit' => ['nullable', 'integer', 'min:0'],
            'storage_gb_limit' => ['nullable', 'integer', 'min:0'],
            'sla_target' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'support_level' => ['nullable', 'string', 'max:120'],
            'modules' => ['nullable', 'array'],
            'is_active' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);
        $this->assertUniqueValue('control_center_plans', 'code', $validated['code'] ?? null, $id);

        $updates = $this->onlyProvided($validated, ['code', 'name', 'monthly_price', 'currency', 'user_limit', 'ai_token_limit', 'whatsapp_message_limit', 'storage_gb_limit', 'sla_target', 'support_level', 'is_active', 'source']);
        if (array_key_exists('modules', $validated)) {
            $updates['modules_json'] = json_encode($validated['modules']);
        }
        $updates['last_verified_at'] = Carbon::now();
        $updates['updated_at'] = Carbon::now();

        DB::table('control_center_plans')->where('id', $id)->update($updates);
        $after = $this->planById($id);
        $this->auditLog(null, null, 'plan', 'plan.updated', 'plan', $id, $before, $after, $request);

        return ['plan' => $after];
    }

    /**
     * @return array<string, mixed>
     */
    public function createContract(Request $request): array
    {
        $validated = $request->validate([
            'organization_id' => ['required', 'integer', 'exists:control_center_organizations,id'],
            'instance_id' => ['nullable', 'integer', 'exists:control_center_instances,id'],
            'plan_id' => ['nullable', 'integer', 'exists:control_center_plans,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', 'max:80'],
            'contract_status' => ['nullable', 'string', 'max:80'],
            'scope' => ['nullable', 'string', 'max:80'],
            'billing_contact' => ['nullable', 'array'],
            'technical_contact' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);

        $now = Carbon::now();
        $id = DB::table('control_center_contracts')->insertGetId([
            'organization_id' => $validated['organization_id'],
            'instance_id' => $validated['instance_id'] ?? null,
            'plan_id' => $validated['plan_id'] ?? null,
            'starts_at' => $validated['starts_at'] ?? null,
            'ends_at' => $validated['ends_at'] ?? null,
            'payment_status' => $validated['payment_status'] ?? 'current',
            'contract_status' => $validated['contract_status'] ?? 'active',
            'scope' => $validated['scope'] ?? (($validated['instance_id'] ?? null) === null ? 'organization' : 'instance'),
            'billing_contact_json' => json_encode($validated['billing_contact'] ?? []),
            'technical_contact_json' => json_encode($validated['technical_contact'] ?? []),
            'notes' => $validated['notes'] ?? null,
            'source' => $validated['source'] ?? 'manual',
            'last_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $contract = $this->contractById($id);
        $this->auditLog((int) $validated['organization_id'], $validated['instance_id'] ?? null, 'contract', 'contract.created', 'contract', $id, null, $contract, $request);

        return ['contract' => $contract];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateContract(int $id, Request $request): array
    {
        $before = $this->contractById($id);
        abort_if($before === [], 404);

        $validated = $request->validate([
            'organization_id' => ['sometimes', 'integer', 'exists:control_center_organizations,id'],
            'instance_id' => ['nullable', 'integer', 'exists:control_center_instances,id'],
            'plan_id' => ['nullable', 'integer', 'exists:control_center_plans,id'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'payment_status' => ['nullable', 'string', 'max:80'],
            'contract_status' => ['nullable', 'string', 'max:80'],
            'scope' => ['nullable', 'string', 'max:80'],
            'billing_contact' => ['nullable', 'array'],
            'technical_contact' => ['nullable', 'array'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);

        $updates = $this->onlyProvided($validated, ['organization_id', 'instance_id', 'plan_id', 'starts_at', 'ends_at', 'payment_status', 'contract_status', 'scope', 'notes', 'source']);
        if (array_key_exists('billing_contact', $validated)) {
            $updates['billing_contact_json'] = json_encode($validated['billing_contact']);
        }
        if (array_key_exists('technical_contact', $validated)) {
            $updates['technical_contact_json'] = json_encode($validated['technical_contact']);
        }
        $updates['last_verified_at'] = Carbon::now();
        $updates['updated_at'] = Carbon::now();

        DB::table('control_center_contracts')->where('id', $id)->update($updates);
        $after = $this->contractById($id);
        $this->auditLog((int) ($after['organization_id'] ?? $before['organization_id']), $after['instance_id'] ?? null, 'contract', 'contract.updated', 'contract', $id, $before, $after, $request);

        return ['contract' => $after];
    }

    /**
     * @return array<string, mixed>
     */
    public function createService(Request $request): array
    {
        $validated = $request->validate([
            'key' => ['required', 'string', 'max:80', 'unique:control_center_services,key'],
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);

        $now = Carbon::now();
        $id = DB::table('control_center_services')->insertGetId([
            'key' => $validated['key'],
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'source' => $validated['source'] ?? 'manual',
            'last_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $service = $this->serviceById($id);
        $this->auditLog(null, null, 'service', 'service.created', 'service', $id, null, $service, $request);

        return ['service' => $service];
    }

    /**
     * @return array<string, mixed>
     */
    public function updateService(int $id, Request $request): array
    {
        $before = $this->serviceById($id);
        abort_if($before === [], 404);

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:80'],
            'name' => ['sometimes', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'icon' => ['nullable', 'string', 'max:80'],
            'is_active' => ['nullable', 'boolean'],
            'source' => ['nullable', 'string', 'in:' . implode(',', self::DATA_SOURCES)],
        ]);
        $this->assertUniqueValue('control_center_services', 'key', $validated['key'] ?? null, $id);

        $updates = $this->onlyProvided($validated, ['key', 'name', 'description', 'icon', 'is_active', 'source']);
        $updates['last_verified_at'] = Carbon::now();
        $updates['updated_at'] = Carbon::now();

        DB::table('control_center_services')->where('id', $id)->update($updates);
        $after = $this->serviceById($id);
        $this->auditLog(null, null, 'service', 'service.updated', 'service', $id, $before, $after, $request);

        return ['service' => $after];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordTelemetryHeartbeat(Request $request): array
    {
        $validated = $request->validate([
            'instance_slug' => ['required', 'string', 'exists:control_center_instances,slug'],
            'app_version' => ['nullable', 'string', 'max:80'],
            'environment' => ['nullable', 'string', 'max:80'],
            'php_version' => ['nullable', 'string', 'max:80'],
            'laravel_version' => ['nullable', 'string', 'max:80'],
            'db_ok' => ['nullable', 'boolean'],
            'queue_ok' => ['nullable', 'boolean'],
            'cache_ok' => ['nullable', 'boolean'],
            'storage_ok' => ['nullable', 'boolean'],
            'scheduler_ok' => ['nullable', 'boolean'],
            'last_backup_at' => ['nullable', 'date'],
            'checked_at' => ['required', 'date'],
            'usage' => ['nullable', 'array'],
            'usage.*.metric' => ['required_with:usage', 'string', 'max:120'],
            'usage.*.value' => ['required_with:usage', 'numeric'],
            'usage.*.unit' => ['required_with:usage', 'string', 'max:40'],
            'usage.*.period_start' => ['required_with:usage', 'date'],
            'usage.*.period_end' => ['nullable', 'date'],
            'usage.*.cost' => ['nullable', 'numeric'],
        ]);

        $instance = DB::table('control_center_instances')->where('slug', $validated['instance_slug'])->first();
        abort_if($instance === null, 404);
        $token = $request->bearerToken();
        if (!$this->validTelemetryToken($token, $instance->telemetry_token_hash ?? null)) {
            abort(401, 'Token de telemetria invalido.');
        }

        $checkedAt = Carbon::parse($validated['checked_at']);
        $serviceStates = [
            'database' => (bool) ($validated['db_ok'] ?? true),
            'queue' => (bool) ($validated['queue_ok'] ?? true),
            'cache' => (bool) ($validated['cache_ok'] ?? true),
            'storage' => (bool) ($validated['storage_ok'] ?? true),
            'scheduler' => (bool) ($validated['scheduler_ok'] ?? true),
        ];
        $telemetryStatus = in_array(false, $serviceStates, true) ? 'degraded' : 'operational';
        $now = Carbon::now();

        DB::transaction(function () use ($instance, $validated, $checkedAt, $serviceStates, $telemetryStatus, $now): void {
            foreach ($serviceStates as $serviceKey => $ok) {
                $serviceId = $this->ensureService($serviceKey, $this->serviceName($serviceKey), 'telemetry');
                DB::table('control_center_service_snapshots')->updateOrInsert(
                    [
                        'instance_id' => (int) $instance->id,
                        'service_id' => $serviceId,
                    ],
                    [
                        'state' => $ok ? 'operational' : 'degraded',
                        'latency_ms' => null,
                        'uptime_pct' => null,
                        'message' => $ok ? 'Reporte de telemetria OK' : 'Reporte de telemetria degradado',
                        'checked_at' => $checkedAt,
                        'source' => 'telemetry',
                        'is_stale' => false,
                        'last_verified_at' => $checkedAt,
                        'metadata_json' => json_encode([
                            'payload_key' => $serviceKey,
                            'reported_value' => $ok,
                        ]),
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]
                );
            }

            DB::table('control_center_instances')->where('id', (int) $instance->id)->update([
                'environment' => $validated['environment'] ?? $instance->environment,
                'current_version' => $validated['app_version'] ?? $instance->current_version,
                'last_seen_at' => $checkedAt,
                'last_backup_at' => isset($validated['last_backup_at']) ? Carbon::parse($validated['last_backup_at']) : $instance->last_backup_at,
                'last_activity_at' => $checkedAt,
                'telemetry_status' => $telemetryStatus,
                'telemetry_json' => json_encode([
                    'php_version' => $validated['php_version'] ?? null,
                    'laravel_version' => $validated['laravel_version'] ?? null,
                    'services' => $serviceStates,
                ]),
                'source' => 'telemetry',
                'last_verified_at' => $checkedAt,
                'updated_at' => $now,
            ]);

            foreach (($validated['usage'] ?? []) as $metric) {
                $this->upsertUsageMetric((int) $instance->organization_id, (int) $instance->id, $metric, 'telemetry', $checkedAt);
            }
        });

        $after = $this->instancesQuery()->where('i.id', (int) $instance->id)->first();
        $this->auditLog((int) $instance->organization_id, (int) $instance->id, 'telemetry', 'telemetry.heartbeat', 'instance', (int) $instance->id, null, $this->instanceCard($after), $request, ['source' => 'telemetry'], 'Instance telemetry');

        return [
            'instance' => $this->instanceCard($after),
            'telemetry_status' => $telemetryStatus,
            'services' => $this->services((int) $instance->id),
            'usage' => $this->usage(instanceId: (int) $instance->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function recordDeployment(string $instanceSlug, string $version, string $status, ?string $commitSha, ?string $actor, ?string $deployedAt = null, string $source = 'pipeline'): array
    {
        $instance = DB::table('control_center_instances')->where('slug', $instanceSlug)->first();
        if ($instance === null) {
            throw ValidationException::withMessages(['instance' => "Instancia no registrada: {$instanceSlug}"]);
        }

        $now = Carbon::now();
        $deployedAtCarbon = $deployedAt === null ? $now : Carbon::parse($deployedAt);
        $channel = $instance->release_channel ?? 'stable';
        $releaseId = DB::table('control_center_releases')->where('version', $version)->value('id');
        if ($releaseId === null) {
            $releaseId = DB::table('control_center_releases')->insertGetId([
                'version' => $version,
                'channel' => $channel,
                'title' => "Release {$version}",
                'status' => 'available',
                'released_at' => $deployedAtCarbon,
                'created_by' => $actor,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $idempotencyKey = implode(':', [$instanceSlug, $version, $status, $commitSha ?: 'no-commit', $deployedAtCarbon->toDateTimeString()]);
        DB::table('control_center_deployments')->updateOrInsert(
            ['idempotency_key' => $idempotencyKey],
            [
                'instance_id' => (int) $instance->id,
                'release_id' => $releaseId,
                'version' => $version,
                'available_version' => null,
                'channel' => $channel,
                'status' => $status,
                'deployed_at' => $deployedAtCarbon,
                'scheduled_at' => null,
                'responsible' => $actor,
                'source' => $source,
                'commit_sha' => $commitSha,
                'last_verified_at' => $deployedAtCarbon,
                'metadata_json' => json_encode(['source' => $source, 'commit_sha' => $commitSha]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        DB::table('control_center_instances')->where('id', (int) $instance->id)->update([
            'current_version' => $version,
            'last_verified_at' => $deployedAtCarbon,
            'updated_at' => $now,
        ]);

        $deployment = DB::table('control_center_deployments')->where('idempotency_key', $idempotencyKey)->first();
        $this->auditLog((int) $instance->organization_id, (int) $instance->id, 'deployment', 'deployment.recorded', 'deployment', (int) $deployment->id, null, [
            'version' => $version,
            'status' => $status,
            'commit_sha' => $commitSha,
            'source' => $source,
        ], null, ['source' => $source], $actor ?: 'Deployment pipeline');

        return ['deployment' => $this->deployments(instanceId: (int) $instance->id)[0] ?? null];
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
            'source' => $this->value($row, 'source') ?? 'manual',
            'is_stale' => (bool) ($this->value($row, 'is_stale') ?? false),
            'data_quality' => $this->dataQuality($row),
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
            'data_quality' => $this->dataQuality($plan),
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
            'commit_sha' => $this->value($row, 'commit_sha'),
            'source' => $this->value($row, 'source') ?? 'manual',
            'data_quality' => $this->dataQuality($row),
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
            'source' => $this->value($row, 'source') ?? 'manual',
            'source_ref' => $this->value($row, 'source_ref'),
            'measured_at' => $this->value($row, 'measured_at'),
            'data_quality' => $this->dataQuality($row),
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
            'ruc' => $organization->ruc ?? null,
            'city' => $organization->city ?? null,
            'timezone' => $organization->timezone ?? 'America/Guayaquil',
            'color' => $organization->color ?? '#006b75',
            'initials' => $organization->initials ?? mb_substr((string) $organization->name, 0, 2),
            'plan_name' => $organization->plan_name ?? null,
            'payment_status' => $organization->payment_status ?? null,
            'contract_status' => $organization->contract_status ?? null,
            'data_quality' => $this->dataQuality($organization),
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
            'last_seen_at' => $this->value($instance, 'last_seen_at'),
            'last_backup_at' => $this->value($instance, 'last_backup_at'),
            'telemetry_status' => $this->value($instance, 'telemetry_status') ?? 'pending',
            'telemetry' => $this->decodeJson($this->value($instance, 'telemetry_json')),
            'plan_name' => $instance->plan_name ?? null,
            'payment_status' => $instance->payment_status ?? null,
            'contract_status' => $instance->contract_status ?? null,
            'data_quality' => $this->dataQuality($instance),
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
            'metadata' => $this->decodeJson($row->metadata_json),
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
                'source' => 'aggregated',
            ];
        }

        return $totals;
    }

    /**
     * @param array<string, mixed> $values
     * @return array<string, mixed>
     */
    private function onlyProvided(array $values, array $keys): array
    {
        $provided = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                $provided[$key] = $values[$key];
            }
        }

        return $provided;
    }

    private function assertUniqueSlug(string $table, ?string $slug, int $ignoreId): void
    {
        $this->assertUniqueValue($table, 'slug', $slug, $ignoreId);
    }

    private function assertUniqueValue(string $table, string $column, mixed $value, int $ignoreId): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $exists = DB::table($table)->where($column, $value)->where('id', '!=', $ignoreId)->exists();
        if ($exists) {
            throw ValidationException::withMessages([$column => 'El valor ya existe.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function dataQuality(object $row): array
    {
        $source = $this->value($row, 'source') ?? $this->metadataSource($row) ?? 'manual';

        return [
            'source' => $source,
            'source_label' => match ($source) {
                'real' => 'Real',
                'seed' => 'Seed MVP',
                'telemetry' => 'Telemetria',
                'pipeline' => 'Pipeline',
                'placeholder' => 'Placeholder visual',
                'pending' => 'Pendiente de integracion',
                default => 'Manual',
            },
            'is_manual' => in_array($source, ['manual', 'seed'], true),
            'last_verified_at' => $this->value($row, 'last_verified_at') ?? $this->value($row, 'updated_at'),
        ];
    }

    private function metadataSource(object $row): ?string
    {
        $metadata = $this->decodeJson($this->value($row, 'metadata_json'));
        if (!is_array($metadata)) {
            return null;
        }

        $source = (string) ($metadata['source'] ?? '');
        if (str_contains($source, 'seed')) {
            return 'seed';
        }

        return in_array($source, self::DATA_SOURCES, true) ? $source : null;
    }

    private function value(object $row, string $property): mixed
    {
        return property_exists($row, $property) ? $row->{$property} : null;
    }

    private function validTelemetryToken(?string $token, ?string $hash): bool
    {
        return is_string($token)
            && $token !== ''
            && is_string($hash)
            && $hash !== ''
            && hash_equals($hash, hash('sha256', $token));
    }

    private function ensureService(string $key, string $name, string $source): int
    {
        $serviceId = DB::table('control_center_services')->where('key', $key)->value('id');
        if ($serviceId !== null) {
            return (int) $serviceId;
        }

        $now = Carbon::now();

        return (int) DB::table('control_center_services')->insertGetId([
            'key' => $key,
            'name' => $name,
            'description' => null,
            'icon' => null,
            'is_active' => true,
            'source' => $source,
            'last_verified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function serviceName(string $key): string
    {
        return match ($key) {
            'database' => 'Base de datos',
            'queue' => 'Colas',
            'cache' => 'Cache',
            'storage' => 'Storage',
            'scheduler' => 'Scheduler',
            default => ucfirst($key),
        };
    }

    /**
     * @param array<string, mixed> $metric
     */
    private function upsertUsageMetric(int $organizationId, int $instanceId, array $metric, string $source, Carbon $measuredAt): void
    {
        $periodEnd = $metric['period_end'] ?? $metric['period_start'];
        $idempotencyKey = implode(':', [
            DB::table('control_center_instances')->where('id', $instanceId)->value('slug'),
            $metric['metric'],
            Carbon::parse($metric['period_start'])->toDateString(),
            Carbon::parse($periodEnd)->toDateString(),
            $source,
        ]);
        $now = Carbon::now();

        DB::table('control_center_usage_metrics')->updateOrInsert(
            ['idempotency_key' => $idempotencyKey],
            [
                'organization_id' => $organizationId,
                'instance_id' => $instanceId,
                'metric' => $metric['metric'],
                'period_start' => Carbon::parse($metric['period_start'])->toDateString(),
                'period_end' => Carbon::parse($periodEnd)->toDateString(),
                'value' => $metric['value'],
                'unit' => $metric['unit'],
                'cost' => $metric['cost'] ?? null,
                'source' => $source,
                'source_ref' => $idempotencyKey,
                'measured_at' => $measuredAt,
                'last_verified_at' => $measuredAt,
                'metadata_json' => json_encode(['source' => $source]),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function planById(int $id): array
    {
        $plan = DB::table('control_center_plans')->where('id', $id)->first();
        if ($plan === null) {
            return [];
        }

        return [
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
            'data_quality' => $this->dataQuality($plan),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function contractById(int $id): array
    {
        $contract = DB::table('control_center_contracts as co')
            ->leftJoin('control_center_plans as p', 'p.id', '=', 'co.plan_id')
            ->where('co.id', $id)
            ->select(['co.*', 'p.name as plan_name'])
            ->first();
        if ($contract === null) {
            return [];
        }

        return [
            'id' => (int) $contract->id,
            'organization_id' => (int) $contract->organization_id,
            'instance_id' => $contract->instance_id === null ? null : (int) $contract->instance_id,
            'plan_id' => $contract->plan_id === null ? null : (int) $contract->plan_id,
            'plan_name' => $contract->plan_name,
            'payment_status' => $contract->payment_status,
            'contract_status' => $contract->contract_status,
            'scope' => $contract->scope,
            'starts_at' => $contract->starts_at,
            'ends_at' => $contract->ends_at,
            'billing_contact' => $this->decodeJson($contract->billing_contact_json),
            'technical_contact' => $this->decodeJson($contract->technical_contact_json),
            'notes' => $contract->notes,
            'data_quality' => $this->dataQuality($contract),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serviceById(int $id): array
    {
        $service = DB::table('control_center_services')->where('id', $id)->first();
        if ($service === null) {
            return [];
        }

        return [
            'id' => (int) $service->id,
            'key' => $service->key,
            'name' => $service->name,
            'description' => $service->description,
            'icon' => $service->icon,
            'is_active' => (bool) $service->is_active,
            'data_quality' => $this->dataQuality($service),
        ];
    }

    /**
     * @param array<string, mixed> $metadata
     */
    private function auditLog(?int $organizationId, ?int $instanceId, string $eventType, string $action, string $targetType, int $targetId, mixed $before, mixed $after, ?Request $request = null, array $metadata = [], ?string $actorName = null): void
    {
        DB::table('control_center_audit_logs')->insert([
            'organization_id' => $organizationId,
            'instance_id' => $instanceId,
            'event_type' => $eventType,
            'action' => $action,
            'actor_user_id' => $request === null ? null : Auth::id(),
            'actor_name' => $actorName ?? $this->actorName(),
            'target_type' => $targetType,
            'target_id' => $targetId,
            'before_json' => json_encode($before),
            'after_json' => json_encode($after),
            'metadata_json' => json_encode(array_merge(['source' => 'control_center_real_data'], $metadata)),
            'ip_address' => $request?->ip(),
            'user_agent' => $request === null ? null : (string) $request->userAgent(),
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
