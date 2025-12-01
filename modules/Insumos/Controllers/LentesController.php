<?php

namespace Modules\Insumos\Controllers;

use Core\BaseController;
use Modules\Insumos\Models\LenteModel;
use PDO;

class LentesController extends BaseController
{
    private LenteModel $model;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->model = new LenteModel($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->render(__DIR__ . '/../views/lentes.php', [
            'pageTitle' => 'Catálogo de Lentes',
        ]);
    }

    public function listar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'lentes' => [], 'error' => 'Sesión expirada'], 401);
            return;
        }
        $this->json(['success' => true, 'lentes' => $this->model->listar()]);
    }

    public function guardar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }

        $payload = $_POST;
        $raw = file_get_contents('php://input');
        if ($raw) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $payload = array_merge($payload, $decoded);
            }
        }

        $resultado = $this->model->guardar($payload);
        $status = ($resultado['success'] ?? false) ? 200 : 422;
        $this->json($resultado, $status);
    }

    public function eliminar(): void
    {
        if (!$this->isAuthenticated()) {
            $this->json(['success' => false, 'message' => 'Sesión expirada'], 401);
            return;
        }
        $id = isset($_POST['id']) ? (int)$_POST['id'] : null;
        if (!$id) {
            $this->json(['success' => false, 'message' => 'ID requerido'], 400);
            return;
        }
        $ok = $this->model->eliminar($id);
        $this->json(['success' => $ok]);
    }
}
