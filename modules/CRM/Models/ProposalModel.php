<?php

namespace Modules\CRM\Models;

use PDO;
use RuntimeException;
use Throwable;

class ProposalModel
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @return string[]
     */
    public function getStatuses(): array
    {
        return [
            self::STATUS_DRAFT,
            self::STATUS_SENT,
            self::STATUS_ACCEPTED,
            self::STATUS_DECLINED,
            self::STATUS_EXPIRED,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(array $filters = []): array
    {
        $where = ['1 = 1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['lead_id'])) {
            $where[] = 'p.lead_id = :lead_id';
            $params[':lead_id'] = (int) $filters['lead_id'];
        }

        if (!empty($filters['search'])) {
            $where[] = '(p.title LIKE :search OR p.proposal_number LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $sql = "
            SELECT
                p.*,
                l.name AS lead_name,
                c.name AS customer_name
            FROM crm_proposals p
            LEFT JOIN crm_leads l ON l.id = p.lead_id
            LEFT JOIN crm_customers c ON c.id = p.customer_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.created_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $limit = isset($filters['limit']) ? max(1, min(100, (int) $filters['limit'])) : 50;
        $offset = isset($filters['offset']) ? max(0, (int) $filters['offset']) : 0;

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                p.*,
                l.name AS lead_name,
                c.name AS customer_name
            FROM crm_proposals p
            LEFT JOIN crm_leads l ON l.id = p.lead_id
            LEFT JOIN crm_customers c ON c.id = p.customer_id
            WHERE p.id = :id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id]);
        $proposal = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$proposal) {
            return null;
        }

        $proposal['items'] = $this->itemsFor($id);

        return $proposal;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function create(array $payload, ?int $userId = null): array
    {
        $items = $this->sanitizeItems($payload['items'] ?? []);
        if (!$items) {
            throw new RuntimeException('La propuesta debe incluir ítems');
        }

        $taxRate = max(0, min(100, (float) ($payload['tax_rate'] ?? 0)));
        $totals = $this->calculateTotals($items, $taxRate);
        $number = $this->generateNumber();
        $status = self::STATUS_DRAFT;

        $this->pdo->beginTransaction();

        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO crm_proposals
                (proposal_number, proposal_year, sequence, lead_id, customer_id, title, status, currency,
                 subtotal, discount_total, tax_rate, tax_total, total, valid_until, notes, terms,
                 packages_snapshot, created_by, updated_by)
                VALUES
                (:proposal_number, :proposal_year, :sequence, :lead_id, :customer_id, :title, :status, :currency,
                 :subtotal, :discount_total, :tax_rate, :tax_total, :total, :valid_until, :notes, :terms,
                 :packages_snapshot, :created_by, :updated_by)
            ");

            $snapshot = $this->buildPackagesSnapshot($items);

            $stmt->execute([
                ':proposal_number' => $number['number'],
                ':proposal_year' => $number['year'],
                ':sequence' => $number['sequence'],
                ':lead_id' => $payload['lead_id'] ?? null,
                ':customer_id' => $payload['customer_id'] ?? null,
                ':title' => $payload['title'] ?? 'Propuesta sin título',
                ':status' => $status,
                ':currency' => $payload['currency'] ?? 'USD',
                ':subtotal' => $totals['subtotal'],
                ':discount_total' => $totals['discount'],
                ':tax_rate' => $taxRate,
                ':tax_total' => $totals['tax'],
                ':total' => $totals['total'],
                ':valid_until' => $payload['valid_until'] ?? null,
                ':notes' => $payload['notes'] ?? null,
                ':terms' => $payload['terms'] ?? null,
                ':packages_snapshot' => $snapshot ? json_encode($snapshot, JSON_UNESCAPED_UNICODE) : null,
                ':created_by' => $userId,
                ':updated_by' => $userId,
            ]);

            $proposalId = (int) $this->pdo->lastInsertId();
            $this->storeItems($proposalId, $items);
            $this->pdo->commit();

            return $this->find($proposalId) ?? [];
        } catch (Throwable $exception) {
            $this->pdo->rollBack();
            throw $exception;
        }
    }

    public function updateStatus(int $proposalId, string $status, ?int $userId = null): ?array
    {
        $status = $this->sanitizeStatus($status);
        if ($status === '') {
            throw new RuntimeException('Estado inválido');
        }

        $stmt = $this->pdo->prepare('UPDATE crm_proposals SET status = :status, updated_by = :updated_by WHERE id = :id');
        $stmt->execute([
            ':status' => $status,
            ':updated_by' => $userId,
            ':id' => $proposalId,
        ]);

        return $this->find($proposalId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function itemsFor(int $proposalId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT *
            FROM crm_proposal_items
            WHERE proposal_id = :proposal_id
            ORDER BY sort_order ASC, id ASC
        ');
        $stmt->execute([':proposal_id' => $proposalId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     */
    private function storeItems(int $proposalId, array $items): void
    {
        $this->pdo->prepare('DELETE FROM crm_proposal_items WHERE proposal_id = :proposal_id')
            ->execute([':proposal_id' => $proposalId]);

        $stmt = $this->pdo->prepare('
            INSERT INTO crm_proposal_items
            (proposal_id, code_id, package_id, description, quantity, unit_price, discount_percent, sort_order, metadata)
            VALUES (:proposal_id, :code_id, :package_id, :description, :quantity, :unit_price, :discount_percent, :sort_order, :metadata)
        ');

        foreach ($items as $index => $item) {
            $stmt->execute([
                ':proposal_id' => $proposalId,
                ':code_id' => $item['code_id'] ?? null,
                ':package_id' => $item['package_id'] ?? null,
                ':description' => $item['description'],
                ':quantity' => $item['quantity'],
                ':unit_price' => $item['unit_price'],
                ':discount_percent' => $item['discount_percent'],
                ':sort_order' => $index,
                ':metadata' => isset($item['metadata']) ? json_encode($item['metadata'], JSON_UNESCAPED_UNICODE) : null,
            ]);
        }
    }

    private function sanitizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return in_array($status, $this->getStatuses(), true) ? $status : '';
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{number: string, sequence: int, year: int}
     */
    private function generateNumber(): array
    {
        $year = (int) date('Y');
        $stmt = $this->pdo->prepare('SELECT MAX(sequence) FROM crm_proposals WHERE proposal_year = :year');
        $stmt->execute([':year' => $year]);
        $sequence = (int) $stmt->fetchColumn() + 1;

        $number = sprintf('PROP-%d-%04d', $year, $sequence);

        return [
            'number' => $number,
            'sequence' => $sequence,
            'year' => $year,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<int, array<string, mixed>>
     */
    private function sanitizeItems(array $items): array
    {
        $clean = [];

        foreach ($items as $item) {
            $description = trim((string) ($item['description'] ?? ''));
            if ($description === '') {
                continue;
            }

            $clean[] = [
                'description' => $description,
                'quantity' => max(0.01, (float) ($item['quantity'] ?? 1)),
                'unit_price' => (float) ($item['unit_price'] ?? 0),
                'discount_percent' => max(0, min(100, (float) ($item['discount_percent'] ?? 0))),
                'code_id' => isset($item['code_id']) ? (int) $item['code_id'] : null,
                'package_id' => isset($item['package_id']) ? (int) $item['package_id'] : null,
                'metadata' => $item['metadata'] ?? null,
            ];
        }

        return $clean;
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array{subtotal: float, discount: float, tax: float, total: float}
     */
    private function calculateTotals(array $items, float $taxRate): array
    {
        $subtotal = 0.0;
        $discountTotal = 0.0;

        foreach ($items as $item) {
            $lineSubtotal = $item['quantity'] * $item['unit_price'];
            $lineDiscount = $lineSubtotal * ($item['discount_percent'] / 100);
            $subtotal += $lineSubtotal;
            $discountTotal += $lineDiscount;
        }

        $taxable = max(0, $subtotal - $discountTotal);
        $tax = $taxable * ($taxRate / 100);
        $total = $taxable + $tax;

        return [
            'subtotal' => round($subtotal, 2),
            'discount' => round($discountTotal, 2),
            'tax' => round($tax, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $items
     * @return array<string, mixed>|null
     */
    private function buildPackagesSnapshot(array $items): ?array
    {
        $packageIds = [];
        foreach ($items as $item) {
            if (!empty($item['package_id'])) {
                $packageIds[] = (int) $item['package_id'];
            }
        }

        $packageIds = array_values(array_unique(array_filter($packageIds)));
        if (!$packageIds) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($packageIds), '?'));
        $stmt = $this->pdo->prepare("SELECT id, name, total_amount FROM crm_packages WHERE id IN ($placeholders)");
        $stmt->execute($packageIds);
        $packages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'packages' => $packages,
        ];
    }
}
