<?php

namespace Modules\CRM\Models;

use PDO;

class TicketModel
{
    private PDO $pdo;

    private const STATUSES = ['abierto', 'en_progreso', 'resuelto', 'cerrado'];
    private const PRIORITIES = ['baja', 'media', 'alta', 'critica'];

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function getStatuses(): array
    {
        return self::STATUSES;
    }

    public function getPriorities(): array
    {
        return self::PRIORITIES;
    }

    public function list(array $filters = []): array
    {
        $sql = "
            SELECT
                t.id,
                t.subject,
                t.status,
                t.priority,
                t.reporter_id,
                t.assigned_to,
                t.related_lead_id,
                t.related_project_id,
                t.created_by,
                t.created_at,
                t.updated_at,
                reporter.nombre AS reporter_name,
                assignee.nombre AS assigned_name,
                l.name AS lead_name,
                project.title AS project_title
            FROM crm_tickets t
            LEFT JOIN users reporter ON t.reporter_id = reporter.id
            LEFT JOIN users assignee ON t.assigned_to = assignee.id
            LEFT JOIN crm_leads l ON t.related_lead_id = l.id
            LEFT JOIN crm_projects project ON t.related_project_id = project.id
            WHERE 1 = 1
        ";

        $params = [];

        if (!empty($filters['status']) && in_array($filters['status'], self::STATUSES, true)) {
            $sql .= ' AND t.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = :assigned';
            $params[':assigned'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['priority']) && in_array($filters['priority'], self::PRIORITIES, true)) {
            $sql .= ' AND t.priority = :priority';
            $params[':priority'] = $filters['priority'];
        }

        $sql .= ' ORDER BY t.updated_at DESC';

        $limit = isset($filters['limit']) ? max(1, (int) $filters['limit']) : 100;
        $sql .= ' LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (!$tickets) {
            return [];
        }

        $ticketIds = array_column($tickets, 'id');
        $messages = $this->getMessagesByTicket($ticketIds);

        foreach ($tickets as &$ticket) {
            $ticketId = (int) $ticket['id'];
            $ticket['messages'] = $messages[$ticketId] ?? [];
        }

        return $tickets;
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare("
            SELECT
                t.id,
                t.subject,
                t.status,
                t.priority,
                t.reporter_id,
                t.assigned_to,
                t.related_lead_id,
                t.related_project_id,
                t.created_by,
                t.created_at,
                t.updated_at,
                reporter.nombre AS reporter_name,
                assignee.nombre AS assigned_name,
                l.name AS lead_name,
                project.title AS project_title
            FROM crm_tickets t
            LEFT JOIN users reporter ON t.reporter_id = reporter.id
            LEFT JOIN users assignee ON t.assigned_to = assignee.id
            LEFT JOIN crm_leads l ON t.related_lead_id = l.id
            LEFT JOIN crm_projects project ON t.related_project_id = project.id
            WHERE t.id = :id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            return null;
        }

        $ticket['messages'] = $this->getMessagesByTicket([$ticket['id']])[$ticket['id']] ?? [];

        return $ticket;
    }

    public function create(array $data, ?int $userId): array
    {
        $status = $this->sanitizeStatus($data['status'] ?? null);
        $priority = $this->sanitizePriority($data['priority'] ?? null);
        $reporter = !empty($data['reporter_id']) ? (int) $data['reporter_id'] : null;
        $assigned = !empty($data['assigned_to']) ? (int) $data['assigned_to'] : null;
        $lead = !empty($data['related_lead_id']) ? (int) $data['related_lead_id'] : null;
        $project = !empty($data['related_project_id']) ? (int) $data['related_project_id'] : null;
        $creator = $userId !== null ? (int) $userId : null;

        $stmt = $this->pdo->prepare("
            INSERT INTO crm_tickets
                (subject, status, priority, reporter_id, assigned_to, related_lead_id, related_project_id, created_by)
            VALUES
                (:subject, :status, :priority, :reporter_id, :assigned_to, :related_lead_id, :related_project_id, :created_by)
        ");

        $stmt->bindValue(':subject', trim((string) ($data['subject'] ?? '')));
        $stmt->bindValue(':status', $status);
        $stmt->bindValue(':priority', $priority);
        $stmt->bindValue(':reporter_id', $reporter, $reporter ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':assigned_to', $assigned, $assigned ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':related_lead_id', $lead, $lead ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':related_project_id', $project, $project ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':created_by', $creator, $creator !== null ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->execute();

        $ticketId = (int) $this->pdo->lastInsertId();

        if (!empty($data['message'])) {
            $this->addMessage($ticketId, $userId, $data['message']);
        }

        return $this->find($ticketId);
    }

    public function addMessage(int $ticketId, ?int $authorId, string $message): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO crm_ticket_messages (ticket_id, author_id, message)
            VALUES (:ticket_id, :author_id, :message)
        ");

        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':author_id' => $authorId ?: null,
            ':message' => trim($message),
        ]);

        $this->touch($ticketId);
    }

    public function updateStatus(int $ticketId, string $status): ?array
    {
        $status = $this->sanitizeStatus($status);
        $stmt = $this->pdo->prepare('UPDATE crm_tickets SET status = :status WHERE id = :id');
        $stmt->execute([':status' => $status, ':id' => $ticketId]);

        return $this->find($ticketId);
    }

    private function touch(int $ticketId): void
    {
        $stmt = $this->pdo->prepare('UPDATE crm_tickets SET updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([':id' => $ticketId]);
    }

    private function getMessagesByTicket(array $ticketIds): array
    {
        if (!$ticketIds) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
        $stmt = $this->pdo->prepare("SELECT m.id, m.ticket_id, m.author_id, m.message, m.created_at, u.nombre AS author_name FROM crm_ticket_messages m LEFT JOIN users u ON m.author_id = u.id WHERE m.ticket_id IN ($placeholders) ORDER BY m.created_at ASC");

        foreach ($ticketIds as $index => $ticketId) {
            $stmt->bindValue($index + 1, (int) $ticketId, PDO::PARAM_INT);
        }

        $stmt->execute();

        $grouped = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $ticketId = (int) $row['ticket_id'];
            $grouped[$ticketId][] = [
                'id' => (int) $row['id'],
                'author_id' => $row['author_id'] !== null ? (int) $row['author_id'] : null,
                'author_name' => $row['author_name'],
                'message' => $row['message'],
                'created_at' => $row['created_at'],
            ];
        }

        return $grouped;
    }

    private function sanitizeStatus(?string $status): string
    {
        if (!$status) {
            return 'abierto';
        }

        $status = strtolower(trim($status));
        if (!in_array($status, self::STATUSES, true)) {
            return 'abierto';
        }

        return $status;
    }

    private function sanitizePriority(?string $priority): string
    {
        if (!$priority) {
            return 'media';
        }

        $priority = strtolower(trim($priority));
        if (!in_array($priority, self::PRIORITIES, true)) {
            return 'media';
        }

        return $priority;
    }
}
