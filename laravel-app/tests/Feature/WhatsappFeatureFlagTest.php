<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use Tests\TestCase;

class WhatsappFeatureFlagTest extends TestCase
{
    public function test_ui_route_redirects_to_legacy_when_flag_is_disabled(): void
    {
        config()->set('whatsapp.migration.enabled', false);
        config()->set('whatsapp.migration.ui.enabled', false);
        config()->set('whatsapp.migration.fallback_to_legacy', true);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/chat');

        $response->assertRedirect('/whatsapp/chat');
    }

    public function test_api_route_returns_service_unavailable_when_api_read_flag_is_disabled(): void
    {
        config()->set('whatsapp.migration.enabled', false);
        config()->set('whatsapp.migration.api.read_enabled', false);
        config()->set('whatsapp.migration.fallback_to_legacy', true);

        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->getJson('/v2/whatsapp/api/conversations');

        $response
            ->assertStatus(503)
            ->assertJsonPath('ok', false)
            ->assertJsonPath('fallback', 'legacy')
            ->assertJsonPath('legacy_target', '/whatsapp/api/conversations');
    }
}
