<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Shared\Support\LegacySessionAuth;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CronScheduleControllerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('cron_schedule');
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
            $table->string('nombre')->default('');
            $table->string('email')->default('');
            $table->string('password')->default('');
            $table->text('permisos')->nullable();
            $table->unsignedBigInteger('role_id')->nullable();
            $table->string('profile_photo')->nullable();
            $table->timestamps();
        });

        Schema::create('cron_schedule', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('name', 255);
            $table->string('command', 500);
            $table->enum('type', ['artisan', 'legacy'])->default('artisan');
            $table->string('cron_expression', 100)->default('*/15 * * * *');
            $table->boolean('enabled')->default(true);
            $table->boolean('run_in_background')->default(true);
            $table->boolean('without_overlapping')->default(true);
            $table->text('description')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status', 50)->nullable();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::table('cron_schedule')->insert([
            'slug'                => 'test-task',
            'name'                => 'Test Task',
            'command'             => 'test:command',
            'type'                => 'artisan',
            'cron_expression'     => '*/15 * * * *',
            'enabled'             => 1,
            'run_in_background'   => 1,
            'without_overlapping' => 1,
        ]);
    }

    public function test_toggle_disables_enabled_task(): void
    {
        $this->actingAsAdmin()
            ->post('/cron-manager/toggle/test-task')
            ->assertRedirect();

        $this->assertSame(0, (int) DB::table('cron_schedule')->where('slug', 'test-task')->value('enabled'));
    }

    public function test_toggle_enables_disabled_task(): void
    {
        DB::table('cron_schedule')->where('slug', 'test-task')->update(['enabled' => 0]);

        $this->actingAsAdmin()
            ->post('/cron-manager/toggle/test-task')
            ->assertRedirect();

        $this->assertSame(1, (int) DB::table('cron_schedule')->where('slug', 'test-task')->value('enabled'));
    }

    public function test_edit_updates_cron_expression(): void
    {
        $this->actingAsAdmin()
            ->post('/cron-manager/edit/test-task', [
                'cron_expression'     => '0 * * * *',
                'enabled'             => '1',
                'run_in_background'   => '1',
                'without_overlapping' => '1',
            ])
            ->assertRedirect();

        $this->assertSame('0 * * * *', DB::table('cron_schedule')->where('slug', 'test-task')->value('cron_expression'));
    }

    public function test_edit_rejects_invalid_cron_expression(): void
    {
        $this->actingAsAdmin()
            ->post('/cron-manager/edit/test-task', [
                'cron_expression' => 'not-valid',
                'enabled'         => '1',
            ])
            ->assertSessionHasErrors('cron_expression');
    }

    public function test_toggle_unknown_slug_redirects_with_error(): void
    {
        $this->actingAsAdmin()
            ->post('/cron-manager/toggle/does-not-exist')
            ->assertRedirect();
    }

    private function actingAsAdmin(): static
    {
        $user = User::query()->create([
            'username' => 'admin_' . uniqid(),
            'nombre'   => 'Admin Test',
            'email'    => 'admin+' . uniqid() . '@example.com',
            'password' => bcrypt('secret'),
            'permisos' => json_encode(['administrativo']),
        ]);

        $sessionId = LegacySessionAuth::writeCompatibilitySession([
            'usuario'  => $user->username,
            'user_id'  => $user->id,
            'permisos' => ['administrativo'],
        ]);

        return $this->actingAs($user)->withCookie('PHPSESSID', $sessionId);
    }
}
