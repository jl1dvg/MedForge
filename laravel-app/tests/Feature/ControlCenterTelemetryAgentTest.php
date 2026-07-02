<?php

namespace Tests\Feature;

use App\Modules\ControlCenter\Services\InstanceTelemetryAgentService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ControlCenterTelemetryAgentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['whatsapp_messages', 'whatsapp_conversations', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('username')->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_conversations', function (Blueprint $table): void {
            $table->id();
            $table->string('wa_number', 32)->nullable();
            $table->timestamps();
        });

        Schema::create('whatsapp_messages', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('conversation_id')->nullable();
            $table->string('direction', 16)->default('outbound');
            $table->timestamps();
        });

        Carbon::setTestNow(Carbon::parse('2026-07-15 10:30:00', 'America/Guayaquil'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_agent_posts_signed_health_and_usage_payload(): void
    {
        DB::table('users')->insert([
            ['username' => 'admin', 'created_at' => now(), 'updated_at' => now()],
            ['username' => 'doctor', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('whatsapp_conversations')->insert([
            ['id' => 1, 'wa_number' => '5931', 'created_at' => '2026-07-05 08:00:00', 'updated_at' => '2026-07-05 08:00:00'],
            ['id' => 2, 'wa_number' => '5932', 'created_at' => '2026-06-05 08:00:00', 'updated_at' => '2026-06-05 08:00:00'],
        ]);
        DB::table('whatsapp_messages')->insert([
            ['conversation_id' => 1, 'direction' => 'outbound', 'created_at' => '2026-07-05 08:01:00', 'updated_at' => '2026-07-05 08:01:00'],
            ['conversation_id' => 2, 'direction' => 'outbound', 'created_at' => '2026-06-05 08:01:00', 'updated_at' => '2026-06-05 08:01:00'],
        ]);

        config([
            'control_center.instance_slug' => 'cive-production',
            'control_center.telemetry_endpoint' => 'https://control.test/v2/control-center/telemetry/heartbeat',
            'control_center.telemetry_token' => 'agent-token',
            'control_center.app_version' => '2026.07.agent',
        ]);

        Http::fake([
            'https://control.test/v2/control-center/telemetry/heartbeat' => Http::response(['ok' => true, 'data' => ['telemetry_status' => 'operational']], 200),
        ]);

        $result = app(InstanceTelemetryAgentService::class)->send();

        $this->assertSame(200, $result['http_status']);
        Http::assertSent(function (Request $request): bool {
            $payload = $request->data();
            $usage = collect($payload['usage'] ?? [])->keyBy('metric');

            return $request->url() === 'https://control.test/v2/control-center/telemetry/heartbeat'
                && $request->hasHeader('Authorization', 'Bearer agent-token')
                && $payload['instance_slug'] === 'cive-production'
                && $payload['app_version'] === '2026.07.agent'
                && $payload['environment'] === app()->environment()
                && $payload['db_ok'] === true
                && $payload['cache_ok'] === true
                && $payload['storage_ok'] === true
                && $payload['queue_ok'] === true
                && $payload['scheduler_ok'] === true
                && $payload['checked_at'] === '2026-07-15T10:30:00-05:00'
                && (float) $usage['active_users']['value'] === 2.0
                && (float) $usage['whatsapp_messages']['value'] === 1.0
                && (float) $usage['whatsapp_conversations']['value'] === 1.0
                && $usage['active_users']['period_start'] === '2026-07-01'
                && $usage['active_users']['period_end'] === '2026-07-31';
        });
    }

    public function test_send_telemetry_command_prints_success_diagnostics(): void
    {
        config([
            'control_center.instance_slug' => 'cive-production',
            'control_center.telemetry_endpoint' => 'https://control.test/v2/control-center/telemetry/heartbeat',
            'control_center.telemetry_token' => 'agent-token',
            'control_center.app_version' => '2026.07.agent',
        ]);

        Http::fake([
            'https://control.test/v2/control-center/telemetry/heartbeat' => Http::response([
                'ok' => true,
                'data' => [
                    'telemetry_status' => 'healthy',
                    'instance' => ['slug' => 'cive-production'],
                ],
            ], 200),
        ]);

        $this->artisan('control-center:send-telemetry')
            ->expectsOutputToContain('Endpoint: https://control.test/v2/control-center/telemetry/heartbeat')
            ->expectsOutputToContain('Instancia: cive-production')
            ->expectsOutputToContain('HTTP status: 200')
            ->expectsOutputToContain('"telemetry_status": "healthy"')
            ->expectsOutputToContain('Telemetria enviada correctamente.')
            ->assertSuccessful();
    }

    public function test_send_telemetry_command_prints_failure_diagnostics_for_html_or_auth_errors(): void
    {
        config([
            'control_center.instance_slug' => 'cive-production',
            'control_center.telemetry_endpoint' => 'https://control.test/v2/control-center/telemetry/heartbeat',
            'control_center.telemetry_token' => 'agent-token',
            'control_center.app_version' => '2026.07.agent',
        ]);

        Http::fake([
            'https://control.test/v2/control-center/telemetry/heartbeat' => Http::response('<html>Login</html>', 403, ['Content-Type' => 'text/html']),
        ]);

        $this->artisan('control-center:send-telemetry')
            ->expectsOutputToContain('Endpoint: https://control.test/v2/control-center/telemetry/heartbeat')
            ->expectsOutputToContain('Instancia: cive-production')
            ->expectsOutputToContain('HTTP status: 403')
            ->expectsOutputToContain('<html>Login</html>')
            ->expectsOutputToContain('Error enviando telemetria.')
            ->assertFailed();
    }

    public function test_agent_requires_endpoint_token_and_instance_slug(): void
    {
        config([
            'control_center.instance_slug' => null,
            'control_center.telemetry_endpoint' => null,
            'control_center.telemetry_token' => null,
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('CONTROL_CENTER_TELEMETRY_ENDPOINT');

        app(InstanceTelemetryAgentService::class)->send();
    }
}
