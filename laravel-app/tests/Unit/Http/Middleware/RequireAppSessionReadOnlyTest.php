<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Middleware;

use App\Http\Middleware\RequireAppSession;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class RequireAppSessionReadOnlyTest extends TestCase
{
    private RequireAppSession $middleware;
    private \Closure $next;
    private bool $nextCalled;

    protected function setUp(): void
    {
        parent::setUp();

        $this->middleware = new RequireAppSession();
        $this->nextCalled = false;
        $this->next = function () {
            $this->nextCalled = true;
            return response('ok', 200);
        };
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function actingAsUser(): User
    {
        $user = (new User())->forceFill(['id' => 1]);
        $user->exists = true;
        Auth::login($user);

        return $user;
    }

    public function test_blocks_write_when_mode_is_forced_on(): void
    {
        $this->actingAsUser();
        config(['medforge-readonly.mode' => 'on']);

        $request = Request::create('/billing/no-facturados/crear', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(423, $response->getStatusCode());
    }

    public function test_allows_write_when_mode_is_forced_off_even_inside_date_window(): void
    {
        $this->actingAsUser();
        config([
            'medforge-readonly.mode' => 'off',
            'medforge-readonly.start_date' => '2026-07-01 00:00:00',
            'medforge-readonly.end_date' => '2026-07-31 23:59:59',
        ]);
        Carbon::setTestNow('2026-07-15');

        $request = Request::create('/billing/no-facturados/crear', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_blocks_write_in_auto_mode_inside_date_window(): void
    {
        $this->actingAsUser();
        config([
            'medforge-readonly.mode' => 'auto',
            'medforge-readonly.start_date' => '2026-07-01 00:00:00',
            'medforge-readonly.end_date' => '2026-07-31 23:59:59',
        ]);
        Carbon::setTestNow('2026-07-15');

        $request = Request::create('/cirugias/wizard/guardar', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(423, $response->getStatusCode());
        $data = json_decode((string) $response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    public function test_allows_write_in_auto_mode_outside_date_window(): void
    {
        $this->actingAsUser();
        config([
            'medforge-readonly.mode' => 'auto',
            'medforge-readonly.start_date' => '2026-07-01 00:00:00',
            'medforge-readonly.end_date' => '2026-07-31 23:59:59',
        ]);
        Carbon::setTestNow('2026-06-15');

        $request = Request::create('/cirugias/wizard/guardar', 'POST');
        $request->headers->set('Accept', 'application/json');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_allows_reads_even_when_read_only_mode_is_forced_on(): void
    {
        $this->actingAsUser();
        config(['medforge-readonly.mode' => 'on']);

        $request = Request::create('/v2/feedback', 'GET');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertTrue($this->nextCalled);
        $this->assertEquals(200, $response->getStatusCode());
    }

    public function test_unauthenticated_requests_still_redirect_to_login_regardless_of_read_only_mode(): void
    {
        config(['medforge-readonly.mode' => 'on']);

        $request = Request::create('/cirugias/wizard/guardar', 'POST');

        $response = $this->middleware->handle($request, $this->next);

        $this->assertFalse($this->nextCalled);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringContainsString('/auth/login', $response->headers->get('Location'));
    }
}
