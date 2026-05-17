<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ProtocolosMigrationRoutesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('procedimientos');

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

        Schema::create('procedimientos', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->string('categoria')->nullable();
            $table->string('membrete')->nullable();
            $table->string('cirugia')->nullable();
            $table->string('imagen_link')->nullable();
        });
    }

    public function test_guest_is_redirected_to_login_for_v2_protocolos(): void
    {
        $response = $this->get('/v2/protocolos');

        $response->assertRedirect('/auth/login?auth_required=1');
    }

    public function test_authenticated_user_without_protocol_permissions_gets_forbidden(): void
    {
        $user = $this->createUser(['dashboard.view']);

        $response = $this->actingAsLegacyUser($user)->get('/v2/protocolos');

        $response->assertForbidden();
    }

    public function test_authenticated_user_with_protocol_permissions_can_open_index(): void
    {
        $user = $this->createUser(['protocolos.templates.view']);

        $response = $this->actingAsLegacyUser($user)->get('/v2/protocolos');

        $response->assertOk();
        $response->assertSee('Editor de Protocolos');
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

