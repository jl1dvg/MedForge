<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Database\Schema\Blueprint;
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
            'control_center_client_features',
            'control_center_features',
            'control_center_operational_states',
            'control_center_contracts',
            'control_center_plans',
            'control_center_clients',
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

        $response = $this->actingAsLegacyUser($user)->postJson('/v2/control-center/clients/1/state', [
            'state' => 'readonly',
            'reason' => 'Factura vencida',
            'confirm' => 'readonly',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.state.state', 'readonly');

        $this->assertDatabaseHas('control_center_operational_states', [
            'client_id' => 1,
            'state' => 'readonly',
            'reason' => 'Factura vencida',
        ]);

        $this->assertDatabaseHas('control_center_audit_logs', [
            'client_id' => 1,
            'event_type' => 'state',
            'action' => 'state.changed',
        ]);
    }

    public function test_readonly_operational_state_blocks_write_requests(): void
    {
        $user = $this->createUser(['dashboard.view']);

        config(['control_center.client_slug' => 'cive']);

        $this->actingAsLegacyUser($this->createUser(['control_center.view', 'control_center.state.manage']))
            ->postJson('/v2/control-center/clients/1/state', [
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

        $response = $this->actingAsLegacyUser($user)->postJson('/v2/control-center/clients/1/features', [
            'features' => [
                ['key' => 'ia', 'enabled' => false, 'reason' => 'Pa Pausa operativa'],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.features.0.key', 'ia')
            ->assertJsonPath('data.features.0.enabled', false);

        $this->assertDatabaseHas('control_center_client_features', [
            'client_id' => 1,
            'enabled' => 0,
        ]);

        $this->assertDatabaseHas('control_center_audit_logs', [
            'client_id' => 1,
            'event_type' => 'feature',
            'action' => 'feature.updated',
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
