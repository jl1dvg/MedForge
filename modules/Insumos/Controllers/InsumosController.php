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

        $this->render(
            __DIR__ . '/../views/index.php',
            [
                'pageTitle' => 'Insumos',
            ]
        );
    }

    public function medicamentos(): void
    {
        $this->requireAuth();

        $this->render(
            __DIR__ . '/../views/medicamentos.php',
            [
                'pageTitle' => 'Medicamentos',
            ]
        );
    }

    public function listar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada.'], 401);
            return;
        }

        $this->json([
            'success' => true,
            'insumos' => $this->service->listarInsumos(),
        ]);
    }

    public function listarMedicamentos(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada.'], 401);
            return;
        }

        $this->json([
            'success' => true,
            'medicamentos' => $this->service->listarMedicamentos(),
        ]);
    }

    public function guardar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada.'], 401);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->json(['success' => false, 'message' => 'JSON inválido.'], 400);
            return;
        }

        $resultado = $this->service->guardar($payload);
        $this->json($resultado, $resultado['success'] ? 200 : 422);
    }

    public function guardarMedicamento(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada.'], 401);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        if (!is_array($payload)) {
            $this->json(['success' => false, 'message' => 'JSON inválido.'], 400);
            return;
        }

        $resultado = $this->service->guardarMedicamento($payload);
        $this->json($resultado, $resultado['success'] ? 200 : 422);
    }

    public function eliminarMedicamento(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada.'], 401);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true);
        $id = is_array($payload) && isset($payload['id']) ? (int) $payload['id'] : 0;

        $resultado = $this->service->eliminarMedicamento($id);
        $this->json($resultado, $resultado['success'] ? 200 : 422);
    }
}
