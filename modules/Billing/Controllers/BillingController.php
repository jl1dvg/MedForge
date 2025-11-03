<?php

namespace Modules\Billing\Controllers;

use Controllers\BillingController as LegacyBillingController;
use Core\BaseController;
use Modules\Billing\Services\BillingViewService;
use Modules\Pacientes\Services\PacienteService;
use PDO;

class BillingController extends BaseController
{
    private BillingViewService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $legacyController = new LegacyBillingController($pdo);
        $pacienteService = new PacienteService($pdo);
        $this->service = new BillingViewService($legacyController, $pacienteService);
    }

    public function index(): void
    {
        $this->requireAuth();

        $mes = $_GET['mes'] ?? null;
        $viewModel = $this->service->obtenerListadoFacturas($mes ?: null);

        $this->render('modules/Billing/views/index.php', [
            'pageTitle' => 'Gestión de facturas',
            'facturas' => $viewModel['facturas'],
            'mesSeleccionado' => $viewModel['mesSeleccionado'],
        ]);
    }

    public function detalle(): void
    {
        $this->requireAuth();

        $formId = $_GET['form_id'] ?? null;
        if (!$formId) {
            http_response_code(400);
            $this->render('modules/Billing/views/detalle_missing.php', [
                'pageTitle' => 'Factura no encontrada',
            ]);
            return;
        }

        $detalle = $this->service->obtenerDetalleFactura($formId);
        if (!$detalle) {
            http_response_code(404);
            $this->render('modules/Billing/views/detalle_missing.php', [
                'pageTitle' => 'Factura no encontrada',
                'formId' => $formId,
            ]);
            return;
        }

        $this->render('modules/Billing/views/detalle.php', [
            'pageTitle' => 'Detalle de factura',
            'detalle' => $detalle,
        ]);
    }
}
