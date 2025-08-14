<?php

namespace Controllers;

use Models\Tarifario;
use Models\CodeType;
use Models\CodeCategory;
use Models\RelatedCode;
use Models\PriceLevel;
use Models\Price;
use Helpers\CodeService;
use Helpers\SearchBuilder;
use PDO;

class CodesController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    private function base(): string
    {
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        return rtrim($script, '/'); // "/public/index.php"
    }

    private function ensureCan(string $perm)
    {
        // TODO: reemplazar por tu ACL real
        // if (!current_user_can($perm)) { http_response_code(403); exit('No autorizado'); }
        return true;
    }

    private function verifyCsrf(): void
    {
        // TODO: reemplazar por tu CSRF real
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['_csrf'] ?? '';
            if (!$token || !isset($_SESSION['_csrf']) || $token !== $_SESSION['_csrf']) {
                http_response_code(419);
                exit('CSRF inválido');
            }
        }
    }

    private function view(string $tpl, array $data = []): void
    {
        extract($data);
        require __DIR__ . "/../views/{$tpl}.php";
    }

    // GET /codes
    public function index(): void
    {
        $this->ensureCan('codes.view');
        $tarifario = new Tarifario($this->db);
        $types = (new CodeType($this->db))->allActive();
        $cats = (new CodeCategory($this->db))->allActive();

        $f = SearchBuilder::filtersFromRequest($_GET);
        $page = max(1, (int)($_GET['page'] ?? 1));
        $pagesize = 100;
        $offset = ($page - 1) * $pagesize;

        $rows = $tarifario->search($f, $offset, $pagesize);
        $total = $tarifario->count($f);

        // Guardia de paginación: si la página solicitada excede el total, redirigir a la última válida
        $pages = max(1, (int)ceil($total / $pagesize));
        if ($page > $pages) {
            $q = $_GET;
            $q['page'] = $pages;
            header('Location: /codes?' . http_build_query($q));
            return;
        }

        $this->view('codes/index', compact('rows', 'types', 'cats', 'f', 'page', 'pagesize', 'total'));
    }

    // GET /codes/create
    public function create(): void
    {
        $this->ensureCan('codes.edit');
        $types = (new CodeType($this->db))->allActive();
        $cats = (new CodeCategory($this->db))->allActive();
        $priceLevels = class_exists(PriceLevel::class) ? (new PriceLevel($this->db))->active() : [];
        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        $this->view('codes/form', [
            'types' => $types,
            'cats' => $cats,
            'priceLevels' => $priceLevels,
            'rels' => [],          // <— agregar
            'prices' => [],        // <— agregar
            'code' => null,
            '_csrf' => $_SESSION['_csrf'],
        ]);
    }

    // POST /codes
    public function store(array $post): void
    {
        $this->ensureCan('codes.edit');
        $this->verifyCsrf();

        $svc = new CodeService($this->db);
        if ($svc->isDuplicate($post['codigo'], $post['code_type'] ?? null, $post['modifier'] ?? null, null)) {
            http_response_code(422);
            exit("Duplicado: (codigo, code_type, modifier) debe ser único.");
        }

        $this->db->beginTransaction();
        try {
            $tar = new Tarifario($this->db);
            $id = $tar->create([
                'codigo' => $post['codigo'],
                'descripcion' => $post['descripcion'] ?? '',
                'short_description' => $post['short_description'] ?? '',
                'code_type' => $post['code_type'] ?? null,
                'modifier' => $post['modifier'] ?? null,
                'superbill' => $post['superbill'] ?? null,
                'active' => !empty($post['active']),
                'reportable' => !empty($post['reportable']),
                'financial_reporting' => !empty($post['financial_reporting']),
                'revenue_code' => $post['revenue_code'] ?? null,
                // precios (opción columnas)
                'precio_nivel1' => $post['precio_nivel1'] ?? null,
                'precio_nivel2' => $post['precio_nivel2'] ?? null,
                'precio_nivel3' => $post['precio_nivel3'] ?? null,
                // anestesia (si las usas)
                'anestesia_nivel1' => $post['anestesia_nivel1'] ?? null,
                'anestesia_nivel2' => $post['anestesia_nivel2'] ?? null,
                'anestesia_nivel3' => $post['anestesia_nivel3'] ?? null,
            ]);

            // precios dinámicos (opción B)
            if (!empty($post['prices']) && class_exists(Price::class)) {
                (new Price($this->db))->upsertMany($id, $post['prices']); // ['nivel1'=>..., 'nivel2'=>...]
            }

            $svc->saveHistory('new', $this->userName(), $id);
            $this->db->commit();
            header("Location: /views/codes");
        } catch (\Throwable $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo "Error al crear: " . $e->getMessage();
        }
    }

    // GET /codes/{id}/edit
    public function edit(int $id): void
    {
        $this->ensureCan('codes.edit');
        $tar = new Tarifario($this->db);
        $code = $tar->findById($id);
        if (!$code) {
            http_response_code(404);
            exit('No encontrado');
        }

        $types = (new CodeType($this->db))->allActive();
        $cats = (new CodeCategory($this->db))->allActive();
        $rels = (new RelatedCode($this->db))->listFor($id);
        $priceLevels = class_exists(PriceLevel::class) ? (new PriceLevel($this->db))->active() : [];
        $prices = class_exists(Price::class) ? (new Price($this->db))->listFor($id) : [];

        $_SESSION['_csrf'] = bin2hex(random_bytes(16));
        $this->view('codes/form', compact('types', 'cats', 'code', 'rels', 'priceLevels', 'prices') + ['_csrf' => $_SESSION['_csrf']]);
    }

    // POST /codes/{id}
    public function update(int $id, array $post): void
    {
        $this->ensureCan('codes.edit');
        $this->verifyCsrf();

        $svc = new CodeService($this->db);
        if ($svc->isDuplicate($post['codigo'], $post['code_type'] ?? null, $post['modifier'] ?? null, $id)) {
            http_response_code(422);
            exit("Duplicado: (codigo, code_type, modifier) debe ser único.");
        }

        $this->db->beginTransaction();
        try {
            (new Tarifario($this->db))->update($id, [
                'codigo' => $post['codigo'],
                'descripcion' => $post['descripcion'] ?? '',
                'short_description' => $post['short_description'] ?? '',
                'code_type' => $post['code_type'] ?? null,
                'modifier' => $post['modifier'] ?? null,
                'superbill' => $post['superbill'] ?? null,
                'active' => !empty($post['active']),
                'reportable' => !empty($post['reportable']),
                'financial_reporting' => !empty($post['financial_reporting']),
                'revenue_code' => $post['revenue_code'] ?? null,
                // columnas
                'precio_nivel1' => $post['precio_nivel1'] ?? null,
                'precio_nivel2' => $post['precio_nivel2'] ?? null,
                'precio_nivel3' => $post['precio_nivel3'] ?? null,
                'anestesia_nivel1' => $post['anestesia_nivel1'] ?? null,
                'anestesia_nivel2' => $post['anestesia_nivel2'] ?? null,
                'anestesia_nivel3' => $post['anestesia_nivel3'] ?? null,
            ]);

            if (!empty($post['prices']) && class_exists(Price::class)) {
                (new Price($this->db))->upsertMany($id, $post['prices']);
            }

            $svc->saveHistory('update', $this->userName(), $id);
            $this->db->commit();
            header("Location: /views/codes");
        } catch (\Throwable $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo "Error al actualizar: " . $e->getMessage();
        }
    }

    // POST /codes/{id}/delete
    public function destroy(int $id): void
    {
        $this->ensureCan('codes.edit');
        $this->verifyCsrf();

        $this->db->beginTransaction();
        try {
            if (class_exists(RelatedCode::class)) {
                (new RelatedCode($this->db))->removeAllFor($id);
            }
            if (class_exists(Price::class)) {
                (new Price($this->db))->deleteFor($id);
            }
            (new Tarifario($this->db))->delete($id);

            // Guardar historia con snapshot vacío (o previo si prefieres)
            (new CodeService($this->db))->saveHistory('delete', $this->userName(), $id);

            $this->db->commit();
            header("Location: /views/codes");
        } catch (\Throwable $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo "Error al eliminar: " . $e->getMessage();
        }
    }

    // POST /codes/{id}/toggle
    public function toggleActive(int $id): void
    {
        $this->ensureCan('codes.edit');
        $this->verifyCsrf();

        $row = (new \Models\Tarifario($this->db))->findById($id);
        if (!$row) {
            http_response_code(404);
            exit('No encontrado');
        }
        $new = $row['active'] ? 0 : 1;

        $st = $this->db->prepare("UPDATE tarifario_2014 SET active=? WHERE id=?");
        $st->execute([$new, $id]);
        (new CodeService($this->db))->saveHistory('update', $this->userName(), $id);

        header("Location: /codes/{$id}/edit");
    }

    // POST /codes/{id}/relate
    public function addRelation(int $id, array $post): void
    {
        $this->ensureCan('codes.edit');
        $this->verifyCsrf();

        $relatedId = (int)($post['related_id'] ?? 0);
        if ($relatedId <= 0) {
            http_response_code(422);
            exit('related_id requerido');
        }
        (new RelatedCode($this->db))->add($id, $relatedId, $post['relation_type'] ?? 'maps_to');
        (new CodeService($this->db))->saveHistory('update', $this->userName(), $id);
        header("Location: /codes/{$id}/edit");
    }

    // POST /codes/{id}/relate/del
    public function removeRelation(int $id, array $post): void
    {
        $this->ensureCan('codes.edit');
        $this->verifyCsrf();

        $relatedId = (int)($post['related_id'] ?? 0);
        if ($relatedId <= 0) {
            http_response_code(422);
            exit('related_id requerido');
        }
        (new RelatedCode($this->db))->remove($id, $relatedId);
        (new CodeService($this->db))->saveHistory('update', $this->userName(), $id);
        header("Location: /codes/{$id}/edit");
    }

    private function userName(): string
    {
        return $_SESSION['auth_user'] ?? 'system';
    }
    public function datatable(array $req): void
    {
        // Cabecera JSON
        header('Content-Type: application/json; charset=utf-8');

        // Parámetros DT
        $draw   = (int)($req['draw']   ?? 0);
        $start  = (int)($req['start']  ?? 0);
        $length = (int)($req['length'] ?? 25);
        if ($length <= 0) { $length = 25; }
        $page = (int)floor($start / $length) + 1;
        $offset = $start;

        // Ordenamiento
        $orderColIdx = (int)($req['order'][0]['column'] ?? 0);
        $orderDir    = strtolower($req['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
        // Mapea el índice de columna DT -> nombre de columna en BD
        $cols = [
            0 => 'codigo',
            1 => 'modifier',
            2 => 'active',                 // lo renderizamos como Sí/No
            3 => 'superbill',
            4 => 'reportable',
            5 => 'financial_reporting',
            6 => 'code_type',
            7 => 'descripcion',
            8 => 'short_description',
            9 => 'id',                     // related (placeholder)
            10 => 'valor_facturar_nivel1',
            11 => 'valor_facturar_nivel2',
            12 => 'valor_facturar_nivel3',
            13 => 'id',                    // acciones
        ];
        $orderBy = $cols[$orderColIdx] ?? 'codigo';

        // Filtros propios + búsqueda global DT
        $f = SearchBuilder::filtersFromRequest($req);
        $searchValue = trim($req['search']['value'] ?? '');
        if ($searchValue !== '') {
            // si ya viene q en filtros, lo respetamos; si no, usamos el de DT
            if (empty($f['q'])) {
                $f['q'] = $searchValue;
            } else {
                // puedes concatenar si quieres
                $f['q'] .= ' ' . $searchValue;
            }
        }

        $tar = new \Models\Tarifario($this->db);

        // Totales
        // recordsTotal: sin filtros de búsqueda (solo estado global si decides)
        $total = $tar->count([]);                // total absoluto de la tabla
        // recordsFiltered: con filtros actuales
        $filtered = $tar->count($f);

        // Datos (paginados)
        // Nota: tu search() ordena por codigo ASC. Si quieres soportar orden dinámico, crea un searchOrder($f,$offset,$limit,$orderBy,$dir)
        // o ajusta search() para aceptar orderBy/dir. Aquí hacemos una salida rápida:
        $rows = $tar->search($f, $offset, $length);

        // Catálogo de categorías para mostrar título en vez de slug
        $cats = (new \Models\CodeCategory($this->db))->allActive();
        $catMap = [];
        foreach ($cats as $c) {
            $catMap[$c['slug']] = $c['title'];
        }

        // Armar respuesta
        $data = [];
        $front = $this->base(); // /public/index.php
        foreach ($rows as $r) {
            $id = (int)$r['id'];
            $data[] = [
                'codigo'             => $r['codigo'],
                'modifier'           => $r['modifier'] ?? '',
                'active_text'        => !empty($r['active']) ? 'Sí' : 'No',
                'category'           => $catMap[$r['superbill'] ?? ''] ?? ($r['superbill'] ?? ''),
                'reportable_text'    => !empty($r['reportable']) ? 'Sí' : 'No',
                'finrep_text'        => !empty($r['financial_reporting']) ? 'Sí' : 'No',
                'code_type'          => $r['code_type'] ?? '',
                'descripcion'        => $r['descripcion'] ?? '',
                'short_description'  => $r['short_description'] ?? '',
                'related'            => '', // TODO: puedes devolver conteo si quieres
                'valor1'             => number_format((float)($r['valor_facturar_nivel1'] ?? 0), 2),
                'valor2'             => number_format((float)($r['valor_facturar_nivel2'] ?? 0), 2),
                'valor3'             => number_format((float)($r['valor_facturar_nivel3'] ?? 0), 2),
                'acciones'           => '<a href="' . htmlspecialchars($front) . '/codes/' . $id . '/edit" class="btn btn-sm btn-outline-primary">Editar</a>',
            ];
        }

        echo json_encode([
            'draw'            => $draw,
            'recordsTotal'    => (int)$total,
            'recordsFiltered' => (int)$filtered,
            'data'            => $data,
        ]);
    }
}