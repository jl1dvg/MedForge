<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Support\LegacySessionAuth;
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
