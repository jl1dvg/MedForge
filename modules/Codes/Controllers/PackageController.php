<?php

namespace Modules\Codes\Controllers;

use Core\BaseController;
use Modules\Codes\Models\PackageModel;
use Models\Tarifario;
use PDO;
use RuntimeException;
use Throwable;

class PackageController extends BaseController
{
    private PackageModel $packages;
    private Tarifario $tarifario;
    private ?array $bodyCache = null;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->packages = new PackageModel($pdo);
        $this->tarifario = new Tarifario($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        $initialPackages = $this->packages->list(['limit' => 25]);

        $this->render(BASE_PATH . '/modules/Codes/views/packages.php', [
            'pageTitle' => 'Constructor de paquetes',
            'initialPackages' => $initialPackages,
            'scripts' => [
                'js/pages/code-packages.js',
            ],
            'styles' => [
                'css/pages/code-packages.css',
            ],
        ]);
    }

    public function list(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        $filters = [
            'active' => isset($_GET['active']) ? (int) $_GET['active'] : 0,
            'search' => $_GET['q'] ?? '',
            'limit' => isset($_GET['limit']) ? (int) $_GET['limit'] : 50,
            'offset' => isset($_GET['offset']) ? (int) $_GET['offset'] : 0,
        ];

        $list = $this->packages->list($filters);
        $this->json(['ok' => true, 'data' => $list]);
    }

    public function show(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        $package = $this->packages->find($id);
        if (!$package) {
            $this->json(['ok' => false, 'error' => 'Paquete no encontrado'], 404);
            return;
        }

        $this->json(['ok' => true, 'data' => $package]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        try {
            $payload = $this->getBody();
            $package = $this->packages->create($payload, $this->getCurrentUserId() ?? 0);
            $this->json(['ok' => true, 'data' => $package], 201);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo guardar el paquete'], 500);
        }
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        try {
            $payload = $this->getBody();
            $package = $this->packages->update($id, $payload, $this->getCurrentUserId() ?? 0);
            if (!$package) {
                $this->json(['ok' => false, 'error' => 'Paquete no encontrado'], 404);
                return;
            }

            $this->json(['ok' => true, 'data' => $package]);
        } catch (RuntimeException $exception) {
            $this->json(['ok' => false, 'error' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            $this->json(['ok' => false, 'error' => 'No se pudo actualizar el paquete'], 500);
        }
    }

    public function delete(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        $deleted = $this->packages->delete($id);

        if ($deleted) {
            $this->json(['ok' => true]);
        } else {
            $this->json(['ok' => false, 'error' => 'No se pudo eliminar el paquete'], 500);
        }
    }

    public function searchCodes(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.view', 'codes.manage', 'administrativo', 'crm.view', 'crm.manage']);

        try {
            $query = trim((string) ($_GET['q'] ?? ''));
            if ($query === '') {
                $this->json(['ok' => true, 'data' => []]);
                return;
            }

            $limit = isset($_GET['limit']) ? max(1, min(50, (int) $_GET['limit'])) : 15;
            $results = $this->tarifario->quickSearch($query, $limit);
            $this->json(['ok' => true, 'data' => $results]);
        } catch (Throwable $exception) {
            error_log(sprintf('[codes] searchCodes failed: %s', $exception->getMessage()));
            $this->json(
                [
                    'ok' => false,
                    'error' => 'No se pudieron buscar los códigos solicitados',
                    'details' => $exception->getMessage(),
                ],
                500
            );
        }
    }

    private function getBody(): array
    {
        if ($this->bodyCache !== null) {
            return $this->bodyCache;
        }

        $raw = file_get_contents('php://input');
        $decoded = json_decode($raw, true);

        if (is_array($decoded)) {
            $this->bodyCache = $decoded;
        } else {
            $this->bodyCache = $_POST;
        }

        return $this->bodyCache;
    }

    private function getCurrentUserId(): ?int
    {
        return isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }
}
