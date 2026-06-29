<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WhatsappOperationalQueuesFrontendTest extends TestCase
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
            $table->string('middle_name')->default('');
            $table->string('last_name')->default('');
            $table->string('second_last_name')->default('');
            $table->timestamp('birth_date')->nullable();
            $table->string('password')->default('');
            $table->string('email')->default('');
            $table->string('nombre')->default('');
            $table->string('cedula')->default('');
            $table->string('registro')->default('');
            $table->string('sede')->default('');
            $table->string('especialidad')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('whatsapp_number')->nullable();
            $table->boolean('whatsapp_notify')->default(false);
            $table->string('profile_photo')->nullable();
        });

        DB::table('users')->insert([
            'id'          => 99,
            'username'    => 'supervisor.test',
            'password'    => bcrypt('secret'),
            'email'       => 'supervisor@example.com',
            'nombre'      => 'Supervisor Test',
            'cedula'      => '99',
            'registro'    => 'R99',
            'sede'        => 'Matriz',
            'especialidad'=> 'NA',
            'permisos'    => json_encode(['whatsapp.manage', 'whatsapp.chat.supervise']),
        ]);

        config()->set('whatsapp.migration.enabled', true);
        config()->set('whatsapp.migration.ui.enabled', true);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function bypassAuthMiddleware(): array
    {
        return [
            LegacySessionBridge::class,
            RequireLegacySession::class,
            RequireLegacyPermission::class,
        ];
    }

    // ── 1. Ruta principal devuelve 200 para usuario autorizado ────────────────

    public function test_hot_opportunities_route_returns_200_for_authorized_user(): void
    {
        $this->actingAs(User::query()->findOrFail(99));

        $response = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/hot-opportunities');

        $response->assertOk();
    }

    // ── 2. Alias /operational-queues renderiza la misma vista ─────────────────

    public function test_operational_queues_alias_returns_200(): void
    {
        $this->actingAs(User::query()->findOrFail(99));

        $response = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/operational-queues');

        $response->assertOk();
    }

    // ── 3. Ambas rutas renderizan la misma vista ──────────────────────────────

    public function test_both_routes_render_same_view(): void
    {
        $this->actingAs(User::query()->findOrFail(99));

        $r1 = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/hot-opportunities');
        $r2 = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/operational-queues');

        $r1->assertOk();
        $r2->assertOk();
        // Both load the same React root mount point
        $r1->assertSee('<div id="root"></div>', false);
        $r2->assertSee('<div id="root"></div>', false);
    }

    // ── 4. Vista contiene referencia al endpoint operacional ──────────────────

    public function test_view_references_operational_queues_api_endpoint(): void
    {
        $this->actingAs(User::query()->findOrFail(99));

        $response = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/hot-opportunities');

        $response->assertOk();
        $response->assertSee('operational-queues', false);
    }

    // ── 5. Vista carga el JS de la app ───────────────────────────────────────

    public function test_view_loads_app_js(): void
    {
        $this->actingAs(User::query()->findOrFail(99));

        $response = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/hot-opportunities');

        $response->assertOk();
        $response->assertSee('/js/whatsapp-hot-opps/app.js', false);
    }

    // ── 6. Vista no es pública (sin auth redirige) ────────────────────────────

    public function test_hot_opportunities_is_not_public(): void
    {
        // Without actingAs + without bypassing middleware → should not return 200
        $response = $this->withoutMiddleware([
            LegacySessionBridge::class,
            RequireLegacySession::class,
            // Keep RequireLegacyPermission active
        ])->get('/v2/whatsapp/hot-opportunities');

        // Either redirect or 403 — not 200
        $this->assertNotSame(200, $response->getStatusCode());
    }

    // ── 7. Vista contiene los 4 bloques operacionales en config ──────────────

    public function test_view_contains_operational_queue_config(): void
    {
        $this->actingAs(User::query()->findOrFail(99));

        $response = $this->withoutMiddleware($this->bypassAuthMiddleware())
            ->get('/v2/whatsapp/hot-opportunities');

        $response->assertOk();
        // Config passed from controller
        $response->assertSee('HOT_OPPS_CONFIG', false);
        $response->assertSee('apiUrl', false);
        $response->assertSee('chatUrl', false);
    }
}
