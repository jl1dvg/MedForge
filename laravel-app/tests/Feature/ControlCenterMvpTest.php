<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Support\LegacySessionAuth;
use App\Modules\ControlCenter\Services\ControlCenterService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ControlCenterMvpTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();

        foreach ([
            'control_center_audit_logs',
            'control_center_usage_metrics',
            'control_center_deployments',
            'control_center_releases',
            'control_center_service_snapshots',
            'control_center_services',
            'control_center_instance_features',
            'control_center_features',
            'control_center_operational_states',
            'control_center_contracts',
            'control_center_plans',
            'control_center_instances',
            'control_center_organizations',
            'users',
            'roles',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('nombre')->default('');
            $table->string('email')->default('');
            $table->string('password')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('profile_photo')->nullable();
            $table->timestamps();
        });

        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_07_01_000000_create_control_center_tables.php',
            '--realpath' => false,
        ])->run();
        $this->artisan('migrate', [
            '--path' => 'database/migrations/2026_07_01_010000_add_real_data_foundations_to_control_center_tables.php',
            '--realpath' => false,
        ])->run();

        $this->artisan('db:seed', [
            '--class' => 'Database\\Seeders\\ControlCenterSeeder',
        ])->run();
    }

    public function test_control_center_requires_its_own_view_permission(): void
    {
        $settingsUser = $this->createUser(['settings.manage']);
        $controlUser = $this->createUser(['control_center.view']);

        $this->actingAsLegacyUser($settingsUser)
            ->get('/v2/control-center')
            ->assertForbidden();

        $this->actingAsLegacyUser($controlUser)
            ->get('/v2/control-center')
            ->assertOk()
            ->assertSee('control-center-root');
    }

    public function test_operational_state_change_is_persisted_and_audited(): void
    {
        $user = $this->createUser(['control_center.view', 'control_center.state.manage']);

        $response = $this->actingAsLegacyUser($user)->postJson('/v2/control-center/instances/1/state', [
            'state' => 'readonly',
            'reason' => 'Factura vencida',
            'confirm' => 'readonly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.state.state', 'readonly');

        $this->assertDatabaseHas('control_center_operational_states', [
            'instance_id' => 1,
            'state' => 'readonly',
            'reason' => 'Factura vencida',
        ]);

        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => 1,
            'event_type' => 'state',
            'action' => 'state.changed',
        ]);
    }

    public function test_readonly_operational_state_blocks_write_requests(): void
    {
        $user = $this->createUser(['dashboard.view']);

        config(['control_center.instance_slug' => 'cive-production']);

        $this->actingAsLegacyUser($this->createUser(['control_center.view', 'control_center.state.manage']))
            ->postJson('/v2/control-center/instances/1/state', [
                'state' => 'readonly',
                'reason' => 'Mora superior a 30 dias',
                'confirm' => 'readonly',
            ])
            ->assertOk();

        $this->actingAsLegacyUser($user)
            ->postJson('/feedback/api/report', ['message' => 'blocked'])
            ->assertStatus(423);
    }

    public function test_feature_flag_update_is_persisted_and_audited(): void
    {
        $user = $this->createUser(['control_center.view', 'control_center.features.manage']);

        $response = $this->actingAsLegacyUser($user)->postJson('/v2/control-center/instances/1/features', [
            'features' => [
                ['key' => 'ia', 'enabled' => false, 'reason' => 'Pa Pausa operativa'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.features.0.key', 'ia')
            ->assertJsonPath('data.features.0.enabled', false);

        $this->assertDatabaseHas('control_center_instance_features', [
            'instance_id' => 1,
            'enabled' => 0,
        ]);

        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => 1,
            'event_type' => 'feature',
            'action' => 'feature.updated',
        ]);
    }

    public function test_control_center_seeder_is_idempotent_and_preserves_instance_operational_data(): void
    {
        DB::table('control_center_instances')->where('slug', 'cive-production')->update(['status' => 'readonly']);
        DB::table('control_center_instance_features')
            ->where('instance_id', 1)
            ->where('feature_id', DB::table('control_center_features')->where('key', 'ia')->value('id'))
            ->update(['enabled' => false, 'override_reason' => 'Manual staging override']);

        $organizationCount = DB::table('control_center_organizations')->count();
        $instanceCount = DB::table('control_center_instances')->count();
        $featureRows = DB::table('control_center_instance_features')->count();
        $contractRows = DB::table('control_center_contracts')->count();
        $stateRows = DB::table('control_center_operational_states')->count();
        $serviceSnapshotRows = DB::table('control_center_service_snapshots')->count();
        $deploymentRows = DB::table('control_center_deployments')->count();
        $usageRows = DB::table('control_center_usage_metrics')->count();
        $auditRows = DB::table('control_center_audit_logs')->count();

        $this->artisan('db:seed', [
            '--class' => 'Database\\Seeders\\ControlCenterSeeder',
        ])->run();

        $this->assertSame($organizationCount, DB::table('control_center_organizations')->count());
        $this->assertSame($instanceCount, DB::table('control_center_instances')->count());
        $this->assertSame($featureRows, DB::table('control_center_instance_features')->count());
        $this->assertSame($contractRows, DB::table('control_center_contracts')->count());
        $this->assertSame($stateRows, DB::table('control_center_operational_states')->count());
        $this->assertSame($serviceSnapshotRows, DB::table('control_center_service_snapshots')->count());
        $this->assertSame($deploymentRows, DB::table('control_center_deployments')->count());
        $this->assertSame($usageRows, DB::table('control_center_usage_metrics')->count());
        $this->assertSame($auditRows, DB::table('control_center_audit_logs')->count());
        $this->assertDatabaseHas('control_center_instances', [
            'slug' => 'cive-production',
            'status' => 'readonly',
        ]);
        $this->assertDatabaseHas('control_center_instance_features', [
            'instance_id' => 1,
            'enabled' => 0,
            'override_reason' => 'Manual staging override',
        ]);
    }

    public function test_control_center_admin_can_create_and_update_organization_with_audit(): void
    {
        $user = $this->createUser(['control_center.view', 'control_center.clients.manage']);

        $create = $this->actingAsLegacyUser($user)->postJson('/v2/control-center/organizations', [
            'slug' => 'vision-real',
            'name' => 'Vision Real',
            'legal_name' => 'Vision Real S.A.',
            'ruc' => '1799999999001',
            'city' => 'Quito',
            'country' => 'Ecuador',
            'admin_contact_name' => 'Ana Admin',
            'admin_contact_email' => 'admin@visionreal.test',
            'admin_contact_phone' => '+593 99 999 9999',
            'technical_contact_name' => 'Tito Tech',
            'technical_contact_email' => 'tech@visionreal.test',
            'internal_notes' => 'Cliente creado desde UI',
            'source' => 'manual',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.organization.slug', 'vision-real')
            ->assertJsonPath('data.organization.country', 'Ecuador')
            ->assertJsonPath('data.organization.admin_contact.email', 'admin@visionreal.test')
            ->assertJsonPath('data.organization.data_quality.source', 'manual');

        $id = $create->json('data.organization.id');

        $this->actingAsLegacyUser($user)->patchJson("/v2/control-center/organizations/{$id}", [
            'name' => 'Vision Real Ecuador',
            'technical_contact_email' => 'soporte@visionreal.test',
            'source' => 'real',
        ])->assertOk()
            ->assertJsonPath('data.organization.name', 'Vision Real Ecuador')
            ->assertJsonPath('data.organization.technical_contact.email', 'soporte@visionreal.test')
            ->assertJsonPath('data.organization.data_quality.source', 'real');

        $this->assertDatabaseHas('control_center_audit_logs', [
            'organization_id' => $id,
            'event_type' => 'organization',
            'action' => 'organization.created',
        ]);
        $this->assertDatabaseHas('control_center_audit_logs', [
            'organization_id' => $id,
            'event_type' => 'organization',
            'action' => 'organization.updated',
        ]);
    }

    public function test_control_center_admin_can_create_and_update_instance_with_initial_state_and_audit(): void
    {
        $user = $this->createUser(['control_center.view', 'control_center.clients.manage']);
        $organizationId = DB::table('control_center_organizations')->where('slug', 'cive')->value('id');

        $create = $this->actingAsLegacyUser($user)->postJson('/v2/control-center/instances', [
            'organization_id' => $organizationId,
            'slug' => 'dra-alvarez-production',
            'name' => 'Dra. Alvarez Produccion',
            'environment' => 'production',
            'domain' => 'draalvarez.medforge.ec',
            'admin_url' => 'https://draalvarez.medforge.ec/admin',
            'server_label' => 'uiserver',
            'database_host' => 'localhost',
            'database_name' => 'medforge_draalvarez',
            'timezone' => 'America/Guayaquil',
            'current_version' => '2026.07.1',
            'release_channel' => 'stable',
            'status' => 'maintenance',
            'notes' => 'Pendiente validacion de DNS',
            'generate_telemetry_token' => true,
            'source' => 'manual',
        ]);

        $create->assertCreated()
            ->assertJsonPath('data.instance.slug', 'dra-alvarez-production')
            ->assertJsonPath('data.instance.status', 'maintenance')
            ->assertJsonPath('data.instance.timezone', 'America/Guayaquil')
            ->assertJsonPath('data.telemetry_token_visible_once', true);

        $instanceId = $create->json('data.instance.id');
        $token = (string) $create->json('data.telemetry_token');
        $this->assertNotSame('', $token);

        $this->assertDatabaseHas('control_center_operational_states', [
            'instance_id' => $instanceId,
            'state' => 'maintenance',
            'reason' => 'Estado inicial de instancia',
        ]);
        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => $instanceId,
            'event_type' => 'state',
            'action' => 'state.initialized',
        ]);
        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => $instanceId,
            'event_type' => 'instance',
            'action' => 'instance.created',
        ]);
        $this->assertSame(hash('sha256', $token), DB::table('control_center_instances')->where('id', $instanceId)->value('telemetry_token_hash'));

        $this->actingAsLegacyUser($user)->patchJson("/v2/control-center/instances/{$instanceId}", [
            'domain' => 'dra-alvarez.medforge.ec',
            'server_label' => 'uiserver-2',
            'database_name' => 'medforge_dra_alvarez',
            'timezone' => 'America/Lima',
            'status' => 'production',
            'notes' => 'DNS validado',
        ])->assertOk()
            ->assertJsonPath('data.instance.server_label', 'uiserver-2')
            ->assertJsonPath('data.instance.timezone', 'America/Lima')
            ->assertJsonPath('data.instance.status', 'production');

        $this->assertDatabaseHas('control_center_operational_states', [
            'instance_id' => $instanceId,
            'state' => 'production',
            'reason' => 'Estado actualizado desde edicion de instancia',
        ]);
        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => $instanceId,
            'event_type' => 'instance',
            'action' => 'instance.updated',
        ]);
    }

    public function test_control_center_admin_can_rotate_telemetry_token_once_with_audit(): void
    {
        $user = $this->createUser(['control_center.view', 'control_center.clients.manage']);
        $instanceId = DB::table('control_center_instances')->where('slug', 'cive-production')->value('id');

        $response = $this->actingAsLegacyUser($user)->postJson("/v2/control-center/instances/{$instanceId}/rotate-telemetry-token", [
            'reason' => 'Onboarding agente staging',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.instance.slug', 'cive-production')
            ->assertJsonPath('data.telemetry_token_visible_once', true);

        $token = (string) $response->json('data.telemetry_token');
        $this->assertStringStartsWith('mfcc_', $token);
        $this->assertSame(hash('sha256', $token), DB::table('control_center_instances')->where('id', $instanceId)->value('telemetry_token_hash'));

        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => $instanceId,
            'event_type' => 'security',
            'action' => 'telemetry_token.rotated',
        ]);
    }

    public function test_control_center_onboarding_writes_require_clients_manage_permission(): void
    {
        $user = $this->createUser(['control_center.view']);
        $instanceId = DB::table('control_center_instances')->where('slug', 'cive-production')->value('id');

        $this->actingAsLegacyUser($user)->postJson('/v2/control-center/organizations', [
            'slug' => 'blocked-org',
            'name' => 'Blocked Org',
        ])->assertForbidden();

        $this->actingAsLegacyUser($user)->postJson('/v2/control-center/instances', [
            'organization_id' => 1,
            'slug' => 'blocked-instance',
            'name' => 'Blocked Instance',
        ])->assertForbidden();

        $this->actingAsLegacyUser($user)
            ->postJson("/v2/control-center/instances/{$instanceId}/rotate-telemetry-token")
            ->assertForbidden();
    }

    public function test_control_center_frontend_does_not_ship_active_mock_clients_or_placeholder_onboarding(): void
    {
        $source = file_get_contents(resource_path('js/control-center/main.jsx'));

        $this->assertStringContainsString('let CC_CLIENTS = [];', $source);
        $this->assertStringContainsString('No existen organizaciones registradas', $source);
        $this->assertStringContainsString('Nueva organización', $source);
        $this->assertStringNotContainsString('CreateOrganizationPlaceholder', $source);
    }

    public function test_signed_instance_telemetry_updates_services_usage_version_and_audit(): void
    {
        DB::table('control_center_instances')->where('slug', 'cive-production')->update([
            'telemetry_token_hash' => hash('sha256', 'secret-token'),
        ]);

        $response = $this->postJson('/v2/control-center/telemetry/heartbeat', [
            'instance_slug' => 'cive-production',
            'app_version' => '2026.07.1',
            'environment' => 'production',
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_ok' => true,
            'queue_ok' => false,
            'cache_ok' => true,
            'storage_ok' => true,
            'scheduler_ok' => true,
            'last_backup_at' => '2026-07-01T10:00:00Z',
            'checked_at' => '2026-07-01T10:05:00Z',
            'usage' => [
                ['metric' => 'ai_tokens', 'value' => 2500, 'unit' => 'tokens', 'period_start' => '2026-07-01', 'period_end' => '2026-07-31', 'cost' => 0.32],
            ],
        ], [
            'Authorization' => 'Bearer secret-token',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.instance.current_version', '2026.07.1')
            ->assertJsonPath('data.telemetry_status', 'degraded');

        $this->assertDatabaseHas('control_center_service_snapshots', [
            'instance_id' => 1,
            'state' => 'degraded',
            'source' => 'telemetry',
        ]);
        $this->assertDatabaseHas('control_center_usage_metrics', [
            'instance_id' => 1,
            'metric' => 'ai_tokens',
            'value' => 2500,
            'source' => 'telemetry',
            'idempotency_key' => 'cive-production:ai_tokens:2026-07-01:2026-07-31:telemetry',
        ]);
        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => 1,
            'event_type' => 'telemetry',
            'action' => 'telemetry.heartbeat',
        ]);
    }

    public function test_signed_telemetry_rejects_invalid_token(): void
    {
        DB::table('control_center_instances')->where('slug', 'cive-production')->update([
            'telemetry_token_hash' => hash('sha256', 'secret-token'),
        ]);

        $this->postJson('/v2/control-center/telemetry/heartbeat', [
            'instance_slug' => 'cive-production',
            'checked_at' => '2026-07-01T10:05:00Z',
        ], [
            'Authorization' => 'Bearer wrong-token',
        ])->assertUnauthorized();
    }

    public function test_record_deployment_service_is_idempotent_and_audited(): void
    {
        /** @var ControlCenterService $service */
        $service = app(ControlCenterService::class);

        $service->recordDeployment('cive-production', '2026.07.2', 'installed', 'abc1234', 'staging deploy', '2026-07-01 12:00:00');
        $service->recordDeployment('cive-production', '2026.07.2', 'installed', 'abc1234', 'staging deploy', '2026-07-01 12:00:00');

        $instanceId = DB::table('control_center_instances')->where('slug', 'cive-production')->value('id');

        $this->assertSame(1, DB::table('control_center_deployments')
            ->where('instance_id', $instanceId)
            ->where('version', '2026.07.2')
            ->where('commit_sha', 'abc1234')
            ->count());
        $this->assertDatabaseHas('control_center_instances', [
            'id' => $instanceId,
            'current_version' => '2026.07.2',
        ]);
        $this->assertDatabaseHas('control_center_audit_logs', [
            'instance_id' => $instanceId,
            'event_type' => 'deployment',
            'action' => 'deployment.recorded',
        ]);
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createUser(array $permissions): User
    {
        return User::query()->create([
            'username' => 'user_' . uniqid(),
            'nombre' => 'Usuario Demo',
            'email' => 'demo+' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'permisos' => json_encode($permissions),
        ]);
    }

    private function actingAsLegacyUser(User $user)
    {
        $permissions = json_decode((string) $user->permisos, true);
        $sessionId = LegacySessionAuth::writeCompatibilitySession([
            'usuario' => $user->username,
            'user_id' => $user->id,
            'permisos' => is_array($permissions) ? $permissions : [],
        ]);

        return $this->actingAs($user)->withCookie('PHPSESSID', $sessionId);
    }
}
