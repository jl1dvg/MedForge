<?php

namespace Modules\Codes\Models;

use PDO;
use PDOException;
use RuntimeException;

class PackageModel
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['active'])) {
            $where[] = 'p.active = 1';
        }

        if (!empty($filters['search'])) {
            $where[] = '(p.name LIKE :search OR p.description LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = "
            SELECT
                p.*,
                COUNT(i.id) AS items_count,
                COALESCE(SUM(i.quantity * i.unit_price), 0) AS computed_total
            FROM crm_packages p
            LEFT JOIN crm_package_items i ON i.package_id = p.id
            WHERE " . implode(' AND ', $where) . "
            GROUP BY p.id
            ORDER BY p.updated_at DESC, p.name ASC
            LIMIT :limit OFFSET :offset";

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
        }

        return $rows;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM crm_packages WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $package = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$package) {
            return null;
        }

        $package['items'] = $this->itemsFor($id);

        return $package;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload, int $userId): array
    {
        $items = $this->sanitizeItems($payload['items'] ?? []);
        if (!$items) {
            throw new RuntimeException('El paquete debe contener al menos un ítem');
        }

        $slug = $this->generateUniqueSlug($payload['name'] ?? '');
        $totals = $this->calculateTotals($items);

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_packages
            (slug, name, description, category, tags, total_items, total_amount, active, created_by, updated_by)
            VALUES (:slug, :name, :description, :category, :tags, :total_items, :total_amount, :active, :created_by, :updated_by)
        ");

        $stmt->execute([
            ':slug' => $slug,
            ':name' => $payload['name'] ?? 'Paquete sin título',
            ':description' => $payload['description'] ?? null,
            ':category' => $payload['category'] ?? null,
            ':tags' => !empty($payload['tags']) ? json_encode($payload['tags'], JSON_UNESCAPED_UNICODE) : null,
            ':total_items' => $totals['count'],
            ':total_amount' => $totals['total'],
            ':active' => !empty($payload['active']) ? 1 : 0,
            ':created_by' => $userId ?: null,
            ':updated_by' => $userId ?: null,
        ]);

        $id = (int) $this->pdo->lastInsertId();
        $this->replaceItems($id, $items);

        return $this->find($id) ?? [];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function update(int $id, array $payload, int $userId): ?array
    {
        $package = $this->find($id);
        if (!$package) {
            return null;
        }

        $items = $this->sanitizeItems($payload['items'] ?? []);
        if (!$items) {
            throw new RuntimeException('El paquete debe contener al menos un ítem');
        }

        $slug = isset($payload['name']) ? $this->generateUniqueSlug($payload['name'], $id) : $package['slug'];
        $totals = $this->calculateTotals($items);

        $stmt = $this->pdo->prepare("
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
        ");

        $stmt->execute([
            ':slug' => $slug,
            ':name' => $payload['name'] ?? $package['name'],
            ':description' => $payload['description'] ?? null,
            ':category' => $payload['category'] ?? null,
            ':tags' => !empty($payload['tags']) ? json_encode($payload['tags'], JSON_UNESCAPED_UNICODE) : null,
            ':total_items' => $totals['count'],
            ':total_amount' => $totals['total'],
            ':active' => !empty($payload['active']) ? 1 : 0,
            ':updated_by' => $userId ?: null,
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

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeItems(array $items): array
    {
        $clean = [];
        $position = 0;

        foreach ($items as $item) {
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $quantity = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $discount = max(0, min(100, (float) ($item['discount_percent'] ?? 0)));
            $codeId = isset($item['code_id']) ? (int) $item['code_id'] : null;

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
     * @param array<int, array<string, mixed>> $items
     * @return array{total: float, count: int}
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
     * @param array<int, array<string, mixed>> $items
     */
    private function replaceItems(int $packageId, array $items): void
    {
        $this->pdo->prepare('DELETE FROM crm_package_items WHERE package_id = :package_id')
            ->execute([':package_id' => $packageId]);

        $insert = $this->pdo->prepare('
            INSERT INTO crm_package_items
            (package_id, code_id, description, quantity, unit_price, discount_percent, sort_order)
            VALUES (:package_id, :code_id, :description, :quantity, :unit_price, :discount_percent, :sort_order)
        ');

        foreach ($items as $item) {
            $insert->execute([
                ':package_id' => $packageId,
                ':code_id' => $item['code_id'] ?? null,
                ':description' => $item['description'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':discount_percent' => $item['discount_percent'],
                ':sort_order' => $item['sort_order'],
            ]);
        }
    }

    private function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = $this->slugify($name);
        if ($base === '') {
            $base = 'pack';
        }

        $slug = $base;
        $suffix = 1;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $suffix++;
        }

        return $slug;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $sql = 'SELECT COUNT(*) FROM crm_packages WHERE slug = :slug';
        $params = [':slug' => $slug];

        if ($ignoreId !== null) {
            $sql .= ' AND id <> :id';
            $params[':id'] = $ignoreId;
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    private function slugify(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';
        return trim($normalized, '-');
    }
}
