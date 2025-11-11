<?php

namespace Modules\CiveExtension\Controllers;

use Core\BaseController;
use Modules\CiveExtension\Services\HealthCheckService;
use Throwable;

class HealthController extends BaseController
{
    public function run(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'success' => false,
                'message' => 'No autenticado.'
            ], 401);
            return;
        }

        $this->requirePermission(['settings.manage', 'administrativo']);

        $service = new HealthCheckService($this->pdo);

        try {
            $result = $service->runScheduledChecks(true);
        } catch (Throwable $exception) {
            error_log('HealthController run error: ' . $exception->getMessage());
            $this->json([
                'success' => false,
                'message' => 'No fue posible ejecutar los health checks.'
            ], 500);
            return;
        }

        $this->json([
            'success' => true,
            'result' => $result,
        ]);
    }

    public function index(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json([
                'success' => false,
                'message' => 'No autenticado.'
            ], 401);
            return;
        }

        $this->requirePermission(['settings.manage', 'administrativo']);

        $service = new HealthCheckService($this->pdo);

        try {
            $history = $service->latestResults(25);
        } catch (Throwable $exception) {
            error_log('HealthController index error: ' . $exception->getMessage());
            $this->json([
                'success' => false,
                'message' => 'No fue posible recuperar el historial de health checks.'
            ], 500);
            return;
        }

        $this->json([
            'success' => true,
            'history' => $history,
        ]);
    }
}
