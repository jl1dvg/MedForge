<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class WhatsappFlowmakerV3Test extends TestCase
{
    public function test_v3_flowmaker_route_renders_react_shell(): void
    {
        $response = $this
            ->withoutMiddleware()
            ->get('/v3/whatsapp/flowmaker');

        $response
            ->assertOk()
            ->assertSee('flowmaker-v3-root', false)
            ->assertSee('window.__FLOWMAKER_V3__', false)
            ->assertSee('fallbackV2', false)
            ->assertSee('WhatsApp V3 - Flowmaker', false);
    }

    public function test_v2_flowmaker_route_still_points_to_existing_controller(): void
    {
        $request = Request::create('/v2/whatsapp/flowmaker', 'GET');
        $route = Route::getRoutes()->match($request);

        $this->assertSame('App\Modules\Whatsapp\Http\Controllers\WhatsappUiController@flowmaker', $route->getActionName());
    }
}
