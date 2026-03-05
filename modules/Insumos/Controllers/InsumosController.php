<?php

namespace Modules\Insumos\Controllers;

use Core\BaseController;
use Modules\Insumos\Services\InsumoService;
use PDO;

class InsumosController extends BaseController
{
    private InsumoService $service;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->service = new InsumoService($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render(__DIR__ . '/../views/index.php', [
            'pageTitle' => 'Catálogo de Insumos',
        ]);
    }

    public function listar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'insumos' => [], 'message' => 'Sesión expirada'], 401);
            return;
        }

        $this->json([
            'success' => true,
            'insumos' => $this->service->listarInsumos(),
        ]);
    }

    public function guardar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $resultado = $this->service->guardar($this->requestPayload());
        $status = ($resultado['success'] ?? false) ? 200 : 422;
        $this->json($resultado, $status);
    }

    public function medicamentos(): void
    {
        $this->requireAuth();
        $this->render(__DIR__ . '/../views/medicamentos.php', [
            'pageTitle' => 'Catálogo de Medicamentos',
        ]);
    }

    public function listarMedicamentos(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'medicamentos' => [], 'message' => 'Sesión expirada'], 401);
            return;
        }

        $this->json([
            'success' => true,
            'medicamentos' => $this->service->listarMedicamentos(),
        ]);
    }

    public function guardarMedicamento(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $resultado = $this->service->guardarMedicamento($this->requestPayload());
        $status = ($resultado['success'] ?? false) ? 200 : 422;
        $this->json($resultado, $status);
    }

    public function eliminarMedicamento(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $this->requestPayload();
        $id = isset($payload['id']) ? (int) $payload['id'] : 0;
        $resultado = $this->service->eliminarMedicamento($id);
        $status = ($resultado['success'] ?? false) ? 200 : 422;
        $this->json($resultado, $status);
    }

    private function requestPayload(): array
    {
        $payload = $_POST;
        $raw = file_get_contents('php://input');

        if ($raw === false || trim($raw) === '') {
            return is_array($payload) ? $payload : [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $payload = array_merge(is_array($payload) ? $payload : [], $decoded);
        }

        return is_array($payload) ? $payload : [];
    }
}
