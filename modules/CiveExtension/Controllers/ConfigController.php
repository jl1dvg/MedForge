<?php

namespace Modules\CiveExtension\Controllers;

use Core\BaseController;
use Modules\CiveExtension\Services\ConfigService;
use Throwable;

class ConfigController extends BaseController
{
    public function show(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'success' => false,
                'message' => 'No autenticado.'
            ], 401);
            return;
        }

        $service = new ConfigService($this->pdo);

        try {
            $config = $service->getExtensionConfig();
        } catch (Throwable $exception) {
            error_log('ConfigController error: ' . $exception->getMessage());
            $this->json([
                'success' => false,
                'message' => 'No fue posible recuperar la configuración.'
            ], 500);
            return;
        }

        $this->json([
            'success' => true,
            'config' => $config,
        ]);
    }
}
