<?php

namespace Modules\Codes\Controllers;

use Core\BaseController;
use Helpers\CodeService;
use Helpers\SearchBuilder;
use Models\CodeCategory;
use Models\CodeType;
use Models\Price;
use Models\PriceLevel;
use Models\RelatedCode;
use Models\Tarifario;
use PDO;
use RuntimeException;
use Throwable;

class CodesController extends BaseController
{
    private Tarifario $tarifario;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->tarifario = new Tarifario($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.view', 'codes.manage', 'administrativo']);

        $types = (new CodeType($this->pdo))->allActive();
        $cats = (new CodeCategory($this->pdo))->allActive();
        $filters = SearchBuilder::filtersFromRequest($_GET);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pagesize = 100;
        $offset = ($page - 1) * $pagesize;

        $rows = $this->tarifario->search($filters, $offset, $pagesize);
        $total = $this->tarifario->count($filters);

        $this->render(BASE_PATH . '/modules/Codes/views/index.php', [
            'pageTitle' => 'Códigos',
            'types' => $types,
            'cats' => $cats,
            'rows' => $rows,
            'f' => $filters,
            'page' => $page,
            'pagesize' => $pagesize,
            'total' => $total,
            'styles' => [
                'https://cdn.datatables.net/1.13.8/css/jquery.dataTables.min.css',
                'https://cdn.datatables.net/rowgroup/1.3.1/css/rowGroup.dataTables.min.css',
            ],
            'scripts' => [
                'https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js',
                'https://cdn.datatables.net/rowgroup/1.3.1/js/dataTables.rowGroup.min.js',
                'js/pages/codes-index.js',
            ],
        ]);
    }

    public function create(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        $types = (new CodeType($this->pdo))->allActive();
        $cats = (new CodeCategory($this->pdo))->allActive();
        $priceLevels = class_exists(PriceLevel::class) ? (new PriceLevel($this->pdo))->active() : [];

        $csrf = $this->generateCsrfToken();

        $this->render(BASE_PATH . '/modules/Codes/views/form.php', [
            'pageTitle' => 'Nuevo código',
            'types' => $types,
            'cats' => $cats,
            'priceLevels' => $priceLevels,
            'prices' => [],
            'rels' => [],
            'code' => null,
            '_csrf' => $csrf,
        ]);
    }

    public function store(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);
        $this->verifyCsrf();

        $post = $_POST;
        $service = new CodeService($this->pdo);

        if ($service->isDuplicate($post['codigo'], $post['code_type'] ?? null, $post['modifier'] ?? null, null)) {
            http_response_code(422);
            echo "Duplicado: (codigo, code_type, modifier) debe ser único.";
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $id = $this->tarifario->create($this->sanitizePayload($post));

            if (!empty($post['prices']) && class_exists(Price::class)) {
                (new Price($this->pdo))->upsertMany($id, $post['prices']);
            }

            $service->saveHistory('new', $this->currentUserName(), $id);
            $this->pdo->commit();
            header('Location: /codes');
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo 'Error al crear: ' . $exception->getMessage();
        }
    }

    public function edit(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);

        $code = $this->tarifario->findById($id);
        if (!$code) {
            http_response_code(404);
            echo 'No encontrado';
            return;
        }

        $types = (new CodeType($this->pdo))->allActive();
        $cats = (new CodeCategory($this->pdo))->allActive();
        $rels = (new RelatedCode($this->pdo))->listFor($id);
        $priceLevels = class_exists(PriceLevel::class) ? (new PriceLevel($this->pdo))->active() : [];
        $prices = class_exists(Price::class) ? (new Price($this->pdo))->listFor($id) : [];
        $csrf = $this->generateCsrfToken();

        $this->render(BASE_PATH . '/modules/Codes/views/form.php', [
            'pageTitle' => 'Editar código',
            'types' => $types,
            'cats' => $cats,
            'code' => $code,
            'rels' => $rels,
            'priceLevels' => $priceLevels,
            'prices' => $prices,
            '_csrf' => $csrf,
        ]);
    }

    public function update(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);
        $this->verifyCsrf();

        $post = $_POST;
        $service = new CodeService($this->pdo);

        if ($service->isDuplicate($post['codigo'], $post['code_type'] ?? null, $post['modifier'] ?? null, $id)) {
            http_response_code(422);
            echo "Duplicado: (codigo, code_type, modifier) debe ser único.";
            return;
        }

        $this->pdo->beginTransaction();

        try {
            $this->tarifario->update($id, $this->sanitizePayload($post));

            if (!empty($post['prices']) && class_exists(Price::class)) {
                (new Price($this->pdo))->upsertMany($id, $post['prices']);
            }

            $service->saveHistory('update', $this->currentUserName(), $id);
            $this->pdo->commit();
            header('Location: /codes/' . $id . '/edit');
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo 'Error al actualizar: ' . $exception->getMessage();
        }
    }

    public function destroy(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);
        $this->verifyCsrf();

        $this->pdo->beginTransaction();

        try {
            if (class_exists(RelatedCode::class)) {
                (new RelatedCode($this->pdo))->removeAllFor($id);
            }

            if (class_exists(Price::class)) {
                (new Price($this->pdo))->deleteFor($id);
            }

            $this->tarifario->delete($id);
            (new CodeService($this->pdo))->saveHistory('delete', $this->currentUserName(), $id);
            $this->pdo->commit();
            header('Location: /codes');
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            http_response_code(500);
            echo 'Error al eliminar: ' . $exception->getMessage();
        }
    }

    public function toggleActive(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);
        $this->verifyCsrf();

        $row = $this->tarifario->findById($id);
        if (!$row) {
            http_response_code(404);
            echo 'No encontrado';
            return;
        }

        $newValue = $row['active'] ? 0 : 1;
        $stmt = $this->pdo->prepare('UPDATE tarifario_2014 SET active = :active WHERE id = :id');
        $stmt->execute([
            ':active' => $newValue,
            ':id' => $id,
        ]);

        (new CodeService($this->pdo))->saveHistory('update', $this->currentUserName(), $id);
        header('Location: /codes/' . $id . '/edit');
    }

    public function addRelation(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);
        $this->verifyCsrf();

        $relatedId = (int)($_POST['related_id'] ?? 0);
        if ($relatedId <= 0) {
            http_response_code(422);
            echo 'related_id requerido';
            return;
        }

        $relationType = $_POST['relation_type'] ?? 'maps_to';
        (new RelatedCode($this->pdo))->add($id, $relatedId, $relationType);
        (new CodeService($this->pdo))->saveHistory('update', $this->currentUserName(), $id);
        header('Location: /codes/' . $id . '/edit');
    }

    public function removeRelation(int $id): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.manage', 'administrativo']);
        $this->verifyCsrf();

        $relatedId = (int)($_POST['related_id'] ?? 0);
        if ($relatedId <= 0) {
            http_response_code(422);
            echo 'related_id requerido';
            return;
        }

        (new RelatedCode($this->pdo))->remove($id, $relatedId);
        (new CodeService($this->pdo))->saveHistory('update', $this->currentUserName(), $id);
        header('Location: /codes/' . $id . '/edit');
    }

    public function datatable(): void
    {
        $this->requireAuth();
        $this->requirePermission(['codes.view', 'codes.manage', 'administrativo']);

        header('Content-Type: application/json; charset=utf-8');

        $request = $_GET;
        $draw = (int)($request['draw'] ?? 0);
        $start = (int)($request['start'] ?? 0);
        $length = (int)($request['length'] ?? 25);
        $length = $length > 0 ? $length : 25;

        $orderIndex = (int)($request['order'][0]['column'] ?? 0);
        $orderDir = strtolower($request['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        $columns = [
            0 => 'codigo',
            1 => 'modifier',
            2 => 'active',
            3 => 'superbill',
            4 => 'reportable',
            5 => 'financial_reporting',
            6 => 'code_type',
            7 => 'descripcion',
            8 => 'short_description',
            9 => 'id',
            10 => 'valor_facturar_nivel1',
            11 => 'valor_facturar_nivel2',
            12 => 'valor_facturar_nivel3',
            13 => 'id',
        ];
        $orderBy = $columns[$orderIndex] ?? 'codigo';

        $filters = SearchBuilder::filtersFromRequest($request);
        $searchValue = trim($request['search']['value'] ?? '');

        if ($searchValue !== '') {
            if (empty($filters['q'])) {
                $filters['q'] = $searchValue;
            } else {
                $filters['q'] .= ' ' . $searchValue;
            }
        }

        $total = $this->tarifario->count([]);
        $filtered = $this->tarifario->count($filters);
        $rows = $this->tarifario->searchOrdered($filters, $start, $length, $orderBy, $orderDir);

        $cats = (new CodeCategory($this->pdo))->allActive();
        $catMap = [];
        foreach ($cats as $cat) {
            $slug = $cat['slug'] ?? '';
            if ($slug !== '') {
                $catMap[$slug] = $cat['title'] ?? $slug;
            }
        }

        $front = $this->frontControllerBase();
        $data = [];

        foreach ($rows as $row) {
            $id = (int)$row['id'];
            $data[] = [
                'codigo' => $row['codigo'],
                'modifier' => $row['modifier'] ?? '',
                'active_text' => !empty($row['active']) ? 'Sí' : 'No',
                'category' => $catMap[$row['superbill'] ?? ''] ?? ($row['superbill'] ?? ''),
                'reportable_text' => !empty($row['reportable']) ? 'Sí' : 'No',
                'finrep_text' => !empty($row['financial_reporting']) ? 'Sí' : 'No',
                'code_type' => $row['code_type'] ?? '',
                'descripcion' => $row['descripcion'] ?? '',
                'short_description' => $row['short_description'] ?? '',
                'related' => '',
                'valor1' => number_format((float)($row['valor_facturar_nivel1'] ?? 0), 2),
                'valor2' => number_format((float)($row['valor_facturar_nivel2'] ?? 0), 2),
                'valor3' => number_format((float)($row['valor_facturar_nivel3'] ?? 0), 2),
                'acciones' => '<a href="' . htmlspecialchars($front . '/codes/' . $id . '/edit', ENT_QUOTES, 'UTF-8') . '" class="btn btn-sm btn-outline-primary">Editar</a>',
            ];
        }

        echo json_encode([
            'draw' => $draw,
            'recordsTotal' => $total,
            'recordsFiltered' => $filtered,
            'data' => $data,
        ]);
    }

    private function sanitizePayload(array $input): array
    {
        return [
            'codigo' => $input['codigo'],
            'descripcion' => $input['descripcion'] ?? '',
            'short_description' => $input['short_description'] ?? '',
            'code_type' => $input['code_type'] ?? null,
            'modifier' => $input['modifier'] ?? null,
            'superbill' => $input['superbill'] ?? null,
            'active' => !empty($input['active']),
            'reportable' => !empty($input['reportable']),
            'financial_reporting' => !empty($input['financial_reporting']),
            'revenue_code' => $input['revenue_code'] ?? null,
            'precio_nivel1' => $input['precio_nivel1'] ?? null,
            'precio_nivel2' => $input['precio_nivel2'] ?? null,
            'precio_nivel3' => $input['precio_nivel3'] ?? null,
            'anestesia_nivel1' => $input['anestesia_nivel1'] ?? null,
            'anestesia_nivel2' => $input['anestesia_nivel2'] ?? null,
            'anestesia_nivel3' => $input['anestesia_nivel3'] ?? null,
        ];
    }

    private function generateCsrfToken(): string
    {
        $token = bin2hex(random_bytes(16));
        $_SESSION['_codes_csrf'] = $token;

        return $token;
    }

    private function verifyCsrf(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $token = $_POST['_csrf'] ?? '';
        if (
            !$token
            || !isset($_SESSION['_codes_csrf'])
            || !hash_equals($_SESSION['_codes_csrf'], $token)
        ) {
            http_response_code(419);
            throw new RuntimeException('CSRF inválido');
        }
    }

    private function currentUserName(): string
    {
        $candidates = [
            $_SESSION['username'] ?? null,
            $_SESSION['auth_user'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return trim($candidate);
            }
        }

        return 'system';
    }

    private function frontControllerBase(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        if (strpos($script, '/public/index.php') !== false) {
            return '/public/index.php';
        }

        return '';
    }
}
