<?php

namespace Tests\Feature;

use Tests\TestCase;

class SolicitudesExtensionRoutesTest extends TestCase
{
    public function test_extension_guardar_aliases_are_routed(): void
    {
        foreach ([
            '/api/solicitudes/guardar.php',
            '/api/solicitudes/guardar',
            '/solicitudes/guardar.php',
            '/solicitudes/guardar',
        ] as $path) {
            $response = $this
                ->postJson($path, [], [
                    'Origin' => 'https://cive.ddns.net:8085',
                ]);

            $this->assertNotSame(404, $response->getStatusCode(), "Route {$path} should be registered.");
        }
    }
}
