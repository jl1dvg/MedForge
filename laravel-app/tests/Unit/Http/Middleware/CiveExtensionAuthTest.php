<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\CiveExtensionAuth;
use App\Models\AppSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CiveExtensionAuthTest extends TestCase
{
    private CiveExtensionAuth $middleware;
    private \Closure $next;
    private bool $nextCalled;

    protected function setUp(): void
    {
        parent::setUp();

        if (!Schema::hasTable('app_settings')) {
            Schema::create('app_settings', static function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->text('value')->nullable();
            });
        }

        AppSetting::query()->whereIn('name', [
            'cive_extension_extension_id_remote',
            'cive_extension_extension_id_local',
        ])->delete();

        $this->middleware = new CiveExtensionAuth();
        $this->nextCalled = false;
        $this->next = function () {
            $this->nextCalled = true;
            return response('ok', 200);
        };
        Cache::flush();
    }

    public function test_allows_request_from_known_remote_extension_id(): void
    {
        AppSetting::query()->insert([
            ['name' => 'cive_extension_extension_id_remote', 'value' => 'abcdefghijklmnopABCDEFGHIJKLMNOP'],
            ['name' => 'cive_extension_extension_id_local',  'value' => 'localidlocalidlocalidlocalidloca'],
        ]);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('Origin', 'chrome-extension://abcdefghijklmnopABCDEFGHIJKLMNOP');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_request_from_known_local_extension_id(): void
    {
        AppSetting::query()->insert([
            ['name' => 'cive_extension_extension_id_remote', 'value' => 'remoteid'],
            ['name' => 'cive_extension_extension_id_local',  'value' => 'localid'],
        ]);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('Origin', 'chrome-extension://localid');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_request_from_unknown_extension_id(): void
    {
        AppSetting::query()->insert([
            ['name' => 'cive_extension_extension_id_remote', 'value' => 'knownremoteid'],
        ]);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('Origin', 'chrome-extension://unknownextensionid');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(403, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertFalse($data['success']);
    }

    public function test_allows_request_with_valid_secret_key_and_no_origin(): void
    {
        config(['services.cive_extension.secret_key' => 'mysecretkey123']);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('X-CiveExtension-Key', 'mysecretkey123');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_rejects_request_with_invalid_secret_key(): void
    {
        config(['services.cive_extension.secret_key' => 'correctkey']);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('X-CiveExtension-Key', 'wrongkey');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_request_with_no_origin_and_no_key(): void
    {
        $request = Request::create('/consultas/guardar', 'POST');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }

    public function test_rejects_request_when_secret_key_not_configured(): void
    {
        config(['services.cive_extension.secret_key' => '']);

        $request = Request::create('/consultas/guardar', 'POST');
        $request->headers->set('X-CiveExtension-Key', 'anykey');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(401, $response->getStatusCode());
    }
}
