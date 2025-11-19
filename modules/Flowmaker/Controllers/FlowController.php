<?php

namespace Modules\Flowmaker\Controllers;

use Core\BaseController;
use Modules\Flowmaker\Repositories\FlowRepository;
use PDO;

class FlowController extends BaseController
{
    private FlowRepository $flows;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->flows = new FlowRepository($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();

        $flows = $this->flows->all();
        $status = $_SESSION['flowmaker_flash'] ?? null;
        unset($_SESSION['flowmaker_flash']);

        $this->render('modules/Flowmaker/views/flows/index.php', [
            'pageTitle' => 'Flowmaker',
            'flows' => $flows,
            'status' => $status,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect('/flowmaker/flows');

            return;
        }

        $name = trim((string) ($_POST['name'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));

        if ($name === '') {
            $_SESSION['flowmaker_flash'] = ['type' => 'danger', 'message' => 'El nombre es obligatorio.'];
            $this->redirect('/flowmaker/flows');

            return;
        }

        $this->flows->create([
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'created_by' => $_SESSION['user_id'] ?? null,
            'updated_by' => $_SESSION['user_id'] ?? null,
        ]);

        $_SESSION['flowmaker_flash'] = ['type' => 'success', 'message' => 'Flujo creado correctamente.'];
        $this->redirect('/flowmaker/flows');
    }

    public function update(int $flowId): void
    {
        $this->requireAuth();

        if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            $this->redirect('/flowmaker/flows');

            return;
        }

        $flow = $this->flows->find($flowId);
        if (!$flow) {
            $_SESSION['flowmaker_flash'] = ['type' => 'danger', 'message' => 'El flujo indicado no existe.'];
            $this->redirect('/flowmaker/flows');

            return;
        }

        $name = trim((string) ($_POST['name'] ?? $flow['name']));
        $description = trim((string) ($_POST['description'] ?? (string) ($flow['description'] ?? '')));

        if ($name === '') {
            $_SESSION['flowmaker_flash'] = ['type' => 'danger', 'message' => 'El nombre es obligatorio.'];
            $this->redirect('/flowmaker/flows');

            return;
        }

        $this->flows->update($flowId, [
            'name' => $name,
            'description' => $description !== '' ? $description : null,
            'updated_by' => $_SESSION['user_id'] ?? null,
        ]);

        $_SESSION['flowmaker_flash'] = ['type' => 'success', 'message' => 'Flujo actualizado correctamente.'];
        $this->redirect('/flowmaker/flows');
    }

    public function delete(int $flowId): void
    {
        $this->requireAuth();

        $flow = $this->flows->find($flowId);
        if (!$flow) {
            $_SESSION['flowmaker_flash'] = ['type' => 'danger', 'message' => 'El flujo indicado no existe.'];
            $this->redirect('/flowmaker/flows');

            return;
        }

        $this->flows->delete($flowId);
        $_SESSION['flowmaker_flash'] = ['type' => 'success', 'message' => 'Flujo eliminado.'];
        $this->redirect('/flowmaker/flows');
    }

    private function redirect(string $path): void
    {
        if (!headers_sent()) {
            header('Location: ' . $path);
        }
        exit;
    }
}
