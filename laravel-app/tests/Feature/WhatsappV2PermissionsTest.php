<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Support\LegacyPermissionCatalog;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappV2PermissionsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('users');
        Schema::dropIfExists('roles');

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('permissions')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username');
            $table->string('first_name')->default('');
            $table->string('last_name')->default('');
            $table->string('nombre')->default('');
            $table->string('email')->default('');
            $table->string('password')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->boolean('whatsapp_notify')->default(false);
            $table->string('profile_photo')->nullable();
            $table->timestamps();
        });
    }

    public function test_hub_shows_notification_panel_when_user_has_permission_and_opt_in(): void
    {
        $user = $this->createUser(['whatsapp.templates.manage', 'whatsapp.notifications.receive'], true);

        $response = $this->getAsLegacyUser($user, '/v2/whatsapp');

        $response->assertOk();
        $response->assertSee('id="kanbanNotificationPanel"', false);
    }

    public function test_hub_hides_notification_panel_without_user_opt_in(): void
    {
        $user = $this->createUser(['whatsapp.templates.manage', 'whatsapp.notifications.receive'], false);

        $response = $this->getAsLegacyUser($user, '/v2/whatsapp');

        $response->assertOk();
        $response->assertDontSee('id="kanbanNotificationPanel"', false);
    }

    public function test_notification_permission_is_registered_in_catalog(): void
    {
        $this->assertArrayHasKey('whatsapp.notifications.receive', LegacyPermissionCatalog::all());
    }

    /**
     * @param array<int, string> $permissions
     */
    private function createUser(array $permissions, bool $whatsappNotify): User
    {
        return User::query()->create([
            'username' => 'user_' . uniqid(),
            'nombre' => 'Usuario Demo',
            'email' => 'demo+' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'permisos' => json_encode($permissions),
            'whatsapp_notify' => $whatsappNotify,
        ]);
    }

    /**
     * @param array<int, string> $withoutMiddleware
     */
    private function getAsLegacyUser(User $user, string $uri)
    {
        $permissions = json_decode((string) $user->permisos, true);
        $sessionId = LegacySessionAuth::writeCompatibilitySession([
            'usuario' => $user->username,
            'user_id' => $user->id,
            'permisos' => is_array($permissions) ? $permissions : [],
        ]);

        return $this->actingAs($user)
            ->withCookie('PHPSESSID', $sessionId)
            ->get($uri);
    }
}
