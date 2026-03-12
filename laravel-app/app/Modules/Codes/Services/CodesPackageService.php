<?php

declare(strict_types=1);

namespace App\Modules\Codes\Services;

use Illuminate\Support\Str;
use PDO;
use RuntimeException;

class CodesPackageService
{
    public function __construct(
        private readonly PDO $pdo
    ) {
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['active'])) {
            $where[] = 'p.active = 1';
        }

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.name LIKE :search OR p.description LIKE :search)';
            $params[':search'] = '%' . $search . '%';
        }

        $sql = '
            SELECT
                p.*,
                COUNT(i.id) AS items_count,
                COALESCE(SUM(i.quantity * i.unit_price), 0) AS computed_total
            FROM crm_packages p
            LEFT JOIN crm_package_items i ON i.package_id = p.id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY p.id
            ORDER BY p.updated_at DESC, p.name ASC
            LIMIT :limit OFFSET :offset
        ';

        $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 50;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['computed_total'] = (float) ($row['computed_total'] ?? 0);
            $row['items_count'] = (int) ($row['items_count'] ?? 0);
            $row['total_items'] = (int) ($row['total_items'] ?? 0);
            $row['total_amount'] = (float) ($row['total_amount'] ?? 0);
            $row['active'] = (int) ($row['active'] ?? 0);
            if (isset($row['tags']) && is_string($row['tags']) && trim($row['tags']) !== '') {
                $decoded = json_decode($row['tags'], true);
                $row['tags'] = is_array($decoded) ? $decoded : null;
            } else {
                $row['tags'] = null;
            }
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM crm_packages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$package) {
            return null;
        }

        $package['id'] = (int) ($package['id'] ?? 0);
        $package['total_items'] = (int) ($package['total_items'] ?? 0);
        $package['total_amount'] = (float) ($package['total_amount'] ?? 0);
        $package['active'] = (int) ($package['active'] ?? 0);
        if (isset($package['tags']) && is_string($package['tags']) && trim($package['tags']) !== '') {
            $decoded = json_decode($package['tags'], true);
            $package['tags'] = is_array($decoded) ? $decoded : null;
        } else {
            $package['tags'] = null;
        }

        $package['items'] = $this->itemsFor($id);

        return $package;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload, int $userId): array
    {
        $items = $this->sanitizeItems($payload['items'] ?? []);
        if ($items === []) {
            throw new RuntimeException('El paquete debe contener al menos un ítem');
        }

        $slug = $this->generateUniqueSlug((string) ($payload['name'] ?? ''));
        $totals = $this->calculateTotals($items);
        $tags = $this->normalizeTags($payload['tags'] ?? null);

        $stmt = $this->pdo->prepare('
            INSERT INTO crm_packages
            (slug, name, description, category, tags, total_items, total_amount, active, created_by, updated_by)
            VALUES (:slug, :name, :description, :category, :tags, :total_items, :total_amount, :active, :created_by, :updated_by)
        ');

        $stmt->execute([
            ':slug' => $slug,
            ':name' => trim((string) ($payload['name'] ?? '')) ?: 'Paquete sin título',
            ':description' => $this->nullableText($payload['description'] ?? null),
            ':category' => $this->nullableText($payload['category'] ?? null),
            ':tags' => $tags !== [] ? json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':total_items' => $totals['count'],
            ':total_amount' => $totals['total'],
            ':active' => !empty($payload['active']) ? 1 : 0,
            ':created_by' => $userId > 0 ? $userId : null,
            ':updated_by' => $userId > 0 ? $userId : null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->replaceItems($id, $items);

        return $this->find($id) ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    public function update(int $id, array $payload, int $userId): ?array
    {
        $package = $this->find($id);
        if ($package === null) {
            return null;
        }

        $items = $this->sanitizeItems($payload['items'] ?? []);
        if ($items === []) {
            throw new RuntimeException('El paquete debe contener al menos un ítem');
        }

        $name = trim((string) ($payload['name'] ?? ($package['name'] ?? '')));
        if ($name === '') {
            $name = 'Paquete sin título';
        }

        $slug = $this->generateUniqueSlug($name, $id);
        $totals = $this->calculateTotals($items);
        $tags = $this->normalizeTags($payload['tags'] ?? ($package['tags'] ?? null));

        $stmt = $this->pdo->prepare('
            UPDATE crm_packages SET
                slug = :slug,
                name = :name,
                description = :description,
                category = :category,
                tags = :tags,
                total_items = :total_items,
                total_amount = :total_amount,
                active = :active,
                updated_by = :updated_by
            WHERE id = :id
        ');

        $stmt->execute([
            ':slug' => $slug,
            ':name' => $name,
            ':description' => $this->nullableText($payload['description'] ?? ($package['description'] ?? null)),
            ':category' => $this->nullableText($payload['category'] ?? ($package['category'] ?? null)),
            ':tags' => $tags !== [] ? json_encode($tags, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            ':total_items' => $totals['count'],
            ':total_amount' => $totals['total'],
            ':active' => !empty($payload['active']) ? 1 : 0,
            ':updated_by' => $userId > 0 ? $userId : null,
            ':id' => $id,
        ]);

        $this->replaceItems($id, $items);

        return $this->find($id);
    }

    public function delete(int $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM crm_packages WHERE id = :id');

        return $stmt->execute([':id' => $id]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsFor(int $packageId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM crm_package_items
            WHERE package_id = :package_id
            ORDER BY sort_order ASC, id ASC
        ');
        $stmt->execute([':package_id' => $packageId]);

        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($items as &$item) {
            $item['id'] = isset($item['id']) ? (int) $item['id'] : null;
            $item['package_id'] = (int) ($item['package_id'] ?? 0);
            $item['code_id'] = isset($item['code_id']) ? (int) $item['code_id'] : null;
            $item['quantity'] = (float) ($item['quantity'] ?? 0);
            $item['unit_price'] = (float) ($item['unit_price'] ?? 0);
            $item['discount_percent'] = (float) ($item['discount_percent'] ?? 0);
            $item['sort_order'] = (int) ($item['sort_order'] ?? 0);
        }
        unset($item);

        return $items;
    }

    /**
     * @param mixed $items
     * @return array<int, array{description:string,quantity:float,unit_price:float,discount_percent:float,code_id:?int,sort_order:int}>
     */
    private function sanitizeItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $clean = [];
        $position = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $quantity = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));
            $codeId = isset($item['code_id']) && is_numeric($item['code_id']) ? (int) $item['code_id'] : null;

            $clean[] = [
                'description' => $description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'discount_percent' => $discount,
                'code_id' => $codeId ?: null,
                'sort_order' => $position++,
            ];
        }

        return $clean;
    }

    /**
     * @param array<int, array{description:string,quantity:float,unit_price:float,discount_percent:float,code_id:?int,sort_order:int}> $items
     * @return array{total:float,count:int}
     */
    private function calculateTotals(array $items): array
    {
        $total = 0.0;

        foreach ($items as $item) {
            $line = $item['quantity'] * $item['unit_price'];
            $line -= $line * ($item['discount_percent'] / 100);
            $total += $line;
        }

        return [
            'total' => round($total, 2),
            'count' => count($items),
        ];
    }

    /**
     * @param array<int, array{description:string,quantity:float,unit_price:float,discount_percent:float,code_id:?int,sort_order:int}> $items
     */
    private function replaceItems(int $packageId, array $items): void
    {
        $this->pdo
            ->prepare('DELETE FROM crm_package_items WHERE package_id = :package_id')
            ->execute([':package_id' => $packageId]);

        $insert = $this->pdo->prepare('
            INSERT INTO crm_package_items
            (package_id, code_id, description, quantity, unit_price, discount_percent, sort_order)
            VALUES (:package_id, :code_id, :description, :quantity, :unit_price, :discount_percent, :sort_order)
        ');

        foreach ($items as $item) {
            $insert->execute([
                ':package_id' => $packageId,
                ':code_id' => $item['code_id'],
                ':description' => $item['description'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':discount_percent' => $item['discount_percent'],
                ':sort_order' => $item['sort_order'],
            ]);
        }
    }

    private function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'paquete';
        }

        $slug = $base;
        $i = 2;
        while ($this->slugExists($slug, $excludeId)) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $sql = 'SELECT id FROM crm_packages WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($excludeId !== null && $excludeId > 0) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $excludeId;
        }

        $sql .= ' LIMIT 1';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<int, string>
     */
    private function normalizeTags(mixed $rawTags): array
    {
        if (is_string($rawTags)) {
            $rawTags = array_map('trim', explode(',', $rawTags));
        }

        if (!is_array($rawTags)) {
            return [];
        }

        $tags = [];
        foreach ($rawTags as $tag) {
            $tag = trim((string) $tag);
            if ($tag === '') {
                continue;
            }
            $tags[$tag] = $tag;
        }

        return array_values($tags);
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}

