<?php

namespace Modules\Billing\Controllers;

use Controllers\BillingController as LegacyBillingController;
use Core\BaseController;
use Modules\Billing\Services\BillingViewService;
use Modules\Pacientes\Services\PacienteService;
use Models\BillingSriDocumentModel;
use PDO;

class BillingController extends BaseController
{
    private BillingViewService $service;
    private \Modules\Billing\Services\NoFacturadosService $noFacturadosService;
    private LegacyBillingController $legacyController;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);

        $this->legacyController = new LegacyBillingController($pdo);
        $pacienteService = new PacienteService($pdo);
        $sriDocumentModel = new BillingSriDocumentModel($pdo);
        $this->service = new BillingViewService(
            $this->legacyController,
            $pacienteService,
            $sriDocumentModel
        );
        $this->noFacturadosService = new \Modules\Billing\Services\NoFacturadosService($pdo);
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

    public function noFacturados(): void
    {
        $this->requireAuth();

        $clasificados = $this->service->obtenerProcedimientosNoFacturados();

        $this->render('modules/Billing/views/no_facturados.php', [
            'pageTitle' => 'Procedimientos no facturados',
            'quirurgicosRevisados' => $clasificados['quirurgicosRevisados'],
            'quirurgicosNoRevisados' => $clasificados['quirurgicosNoRevisados'],
            'noQuirurgicos' => $clasificados['noQuirurgicos'],
        ]);
    }

    public function crearDesdeNoFacturado(): void
    {
        $this->requireAuth();

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            if (!headers_sent()) {
                http_response_code(405);
            }
            echo 'Método no permitido.';
            return;
        }

        $formId = trim((string)($_POST['form_id'] ?? ''));
        $hcNumber = trim((string)($_POST['hc_number'] ?? ''));

        if ($formId === '' || $hcNumber === '') {
            if (!headers_sent()) {
                http_response_code(400);
            }
            echo 'Faltan parámetros.';
            return;
        }

        $billingModel = new \Models\BillingMainModel($this->pdo);
        $existing = $billingModel->findByFormId($formId);
        if ($existing) {
            $this->redirectToDetalle($formId);
            return;
        }

        try {
            $billingId = $billingModel->insert($hcNumber, $formId);

            $stmtFecha = $this->pdo->prepare('SELECT fecha_inicio FROM protocolo_data WHERE form_id = ?');
            $stmtFecha->execute([$formId]);
            $fechaInicio = $stmtFecha->fetchColumn();
            if ($fechaInicio) {
                $billingModel->updateFechaCreacion($billingId, $fechaInicio);
            }

            $preview = $this->legacyController->prepararPreviewFacturacion($formId, $hcNumber);

            $procedimientosModel = new \Models\BillingProcedimientosModel($this->pdo);
            foreach ($preview['procedimientos'] ?? [] as $procedimiento) {
                $procedimientosModel->insertar($billingId, [
                    'id' => $procedimiento['id'] ?? null,
                    'procCodigo' => $procedimiento['procCodigo'] ?? '',
                    'procDetalle' => $procedimiento['procDetalle'] ?? '',
                    'procPrecio' => $procedimiento['procPrecio'] ?? 0,
                ]);
            }

            $insumosModel = new \Models\BillingInsumosModel($this->pdo);
            foreach ($preview['insumos'] ?? [] as $insumo) {
                $insumosModel->insertar($billingId, [
                    'id' => $insumo['id'] ?? null,
                    'codigo' => $insumo['codigo'] ?? '',
                    'nombre' => $insumo['nombre'] ?? '',
                    'cantidad' => $insumo['cantidad'] ?? 0,
                    'precio' => $insumo['precio'] ?? 0,
                    'iva' => $insumo['iva'] ?? 1,
                ]);
            }

            $derechosModel = new \Models\BillingDerechosModel($this->pdo);
            foreach ($preview['derechos'] ?? [] as $derecho) {
                $derechosModel->insertar($billingId, [
                    'id' => $derecho['id'] ?? null,
                    'codigo' => $derecho['codigo'] ?? '',
                    'detalle' => $derecho['detalle'] ?? '',
                    'cantidad' => $derecho['cantidad'] ?? 0,
                    'iva' => $derecho['iva'] ?? 0,
                    'precioAfiliacion' => $derecho['precioAfiliacion'] ?? 0,
                ]);
            }

            $oxigenoModel = new \Models\BillingOxigenoModel($this->pdo);
            foreach ($preview['oxigeno'] ?? [] as $oxigeno) {
                $oxigenoModel->insertar($billingId, [
                    'codigo' => $oxigeno['codigo'] ?? '',
                    'nombre' => $oxigeno['nombre'] ?? '',
                    'tiempo' => $oxigeno['tiempo'] ?? 0,
                    'litros' => $oxigeno['litros'] ?? 0,
                    'valor1' => $oxigeno['valor1'] ?? 0,
                    'valor2' => $oxigeno['valor2'] ?? 0,
                    'precio' => $oxigeno['precio'] ?? 0,
                ]);
            }

            $anestesiaModel = new \Models\BillingAnestesiaModel($this->pdo);
            foreach ($preview['anestesia'] ?? [] as $anestesia) {
                $anestesiaModel->insertar($billingId, [
                    'codigo' => $anestesia['codigo'] ?? '',
                    'nombre' => $anestesia['nombre'] ?? '',
                    'tiempo' => $anestesia['tiempo'] ?? 0,
                    'valor2' => $anestesia['valor2'] ?? 0,
                    'precio' => $anestesia['precio'] ?? 0,
                ]);
            }
        } catch (\Throwable $exception) {
            if (!headers_sent()) {
                http_response_code(500);
            }
            error_log('Error al crear la facturación desde no facturado: ' . $exception->getMessage());
            echo 'Ocurrió un error al crear la facturación.';
            return;
        }

        $this->redirectToDetalle($formId);
    }

    public function apiNoFacturados(): void
    {
        $this->requireAuth();

        $draw = (int)($_GET['draw'] ?? 0);
        $start = (int)($_GET['start'] ?? 0);
        $length = (int)($_GET['length'] ?? 25);

        $filters = [
            'fecha_desde' => $_GET['fecha_desde'] ?? null,
            'fecha_hasta' => $_GET['fecha_hasta'] ?? null,
            'afiliacion' => $_GET['afiliacion'] ?? null,
            'estado_revision' => $_GET['estado_revision'] ?? null,
            'tipo' => $_GET['tipo'] ?? null,
            'busqueda' => $_GET['busqueda'] ?? null,
            'procedimiento' => $_GET['procedimiento'] ?? null,
            'valor_min' => $_GET['valor_min'] ?? null,
            'valor_max' => $_GET['valor_max'] ?? null,
        ];

        $resultado = $this->noFacturadosService->listar($filters, $start, $length);

        header('Content-Type: application/json');
        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $resultado['recordsTotal'],
            'recordsFiltered' => $resultado['recordsFiltered'],
            'data' => $resultado['data'],
            'summary' => $resultado['summary'],
        ]);
    }

    private function redirectToDetalle(string $formId): void
    {
        $target = '/billing/detalle?form_id=' . urlencode($formId);
        header('Location: ' . $target);
        exit;
    }
}
