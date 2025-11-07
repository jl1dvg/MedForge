<?php

declare(strict_types=1);

namespace Modules\CronManager\Repositories;

use DateInterval;
use DateTimeImmutable;
use PDO;

class CronTaskRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAll(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM medforge_cron_tasks ORDER BY name ASC');

        return $stmt ? ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []) : [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentLogs(int $limit = 20): array
    {
        $sql = 'SELECT l.*, t.slug, t.name
                FROM medforge_cron_logs l
                INNER JOIN medforge_cron_tasks t ON t.id = l.task_id
                ORDER BY l.started_at DESC
                LIMIT :limit';

        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':limit', max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM medforge_cron_tasks WHERE slug = :slug LIMIT 1');
        $stmt->execute([':slug' => $slug]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * @param array{slug:string,name:string,description:string,interval:int} $definition
     */
    public function ensureTask(array $definition): array
    {
        $existing = $this->findBySlug($definition['slug']);

        if ($existing === null) {
            $now = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
            $stmt = $this->pdo->prepare(
                'INSERT INTO medforge_cron_tasks (slug, name, description, schedule_interval, next_run_at)
                 VALUES (:slug, :name, :description, :interval, :next_run_at)'
            );
            $stmt->execute([
                ':slug' => $definition['slug'],
                ':name' => $definition['name'],
                ':description' => $definition['description'],
                ':interval' => max(1, (int) $definition['interval']),
                ':next_run_at' => $now,
            ]);

            return $this->findById((int) $this->pdo->lastInsertId()) ?? [];
        }

        $updates = [];
        $params = [':id' => (int) $existing['id']];

        if ($existing['name'] !== $definition['name']) {
            $updates[] = 'name = :name';
            $params[':name'] = $definition['name'];
        }

        $currentDescription = (string) ($existing['description'] ?? '');
        if ($currentDescription !== $definition['description']) {
            $updates[] = 'description = :description';
            $params[':description'] = $definition['description'];
        }

        $currentInterval = (int) ($existing['schedule_interval'] ?? 0);
        $incomingInterval = max(1, (int) $definition['interval']);
        if ($currentInterval !== $incomingInterval) {
            $updates[] = 'schedule_interval = :interval';
            $params[':interval'] = $incomingInterval;
            $updates[] = 'next_run_at = :next_run_at';
            $params[':next_run_at'] = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        }

        if (!empty($updates)) {
            $sql = 'UPDATE medforge_cron_tasks SET ' . implode(', ', $updates) . ', updated_at = CURRENT_TIMESTAMP WHERE id = :id';
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $existing = $this->findById((int) $existing['id']);
        }

        return $existing ?? [];
    }

    public function startLog(int $taskId, DateTimeImmutable $startedAt): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO medforge_cron_logs (task_id, started_at, status)
             VALUES (:task_id, :started_at, "running")'
        );
        $stmt->execute([
            ':task_id' => $taskId,
            ':started_at' => $startedAt->format('Y-m-d H:i:s'),
        ]);

        return (int) $this->pdo->lastInsertId();
    }

    public function finishLog(
        int $logId,
        string $status,
        DateTimeImmutable $finishedAt,
        string $message,
        ?array $details,
        ?string $error,
        int $durationMs
    ): void {
        $stmt = $this->pdo->prepare(
            'UPDATE medforge_cron_logs
             SET status = :status,
                 finished_at = :finished_at,
                 message = :message,
                 output = :output,
                 error = :error,
                 duration_ms = :duration
             WHERE id = :id'
        );
        $stmt->execute([
            ':status' => $status,
            ':finished_at' => $finishedAt->format('Y-m-d H:i:s'),
            ':message' => $message,
            ':output' => $this->encodeJson($details),
            ':error' => $error,
            ':duration' => $durationMs >= 0 ? $durationMs : null,
            ':id' => $logId,
        ]);
    }

    public function markSuccess(
        int $taskId,
        DateTimeImmutable $runAt,
        int $interval,
        string $message,
        ?array $details,
        int $durationMs
    ): void {
        $nextRun = $this->nextRunTime($runAt, $interval);
        $stmt = $this->pdo->prepare(
            'UPDATE medforge_cron_tasks
             SET last_run_at = :last_run,
                 next_run_at = :next_run,
                 last_status = "success",
                 last_message = :message,
                 last_output = :output,
                 last_error = NULL,
                 last_duration_ms = :duration,
                 failure_count = 0,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':last_run' => $runAt->format('Y-m-d H:i:s'),
            ':next_run' => $nextRun->format('Y-m-d H:i:s'),
            ':message' => $message,
            ':output' => $this->encodeJson($details),
            ':duration' => $durationMs >= 0 ? $durationMs : null,
            ':id' => $taskId,
        ]);
    }

    public function markFailure(
        int $taskId,
        DateTimeImmutable $runAt,
        int $interval,
        string $message,
        ?array $details,
        int $durationMs
    ): void {
        $nextRun = $this->nextRunTime($runAt, $interval);
        $stmt = $this->pdo->prepare(
            'UPDATE medforge_cron_tasks
             SET last_run_at = :last_run,
                 next_run_at = :next_run,
                 last_status = "failed",
                 last_message = :message,
                 last_output = :output,
                 last_error = :error,
                 last_duration_ms = :duration,
                 failure_count = failure_count + 1,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':last_run' => $runAt->format('Y-m-d H:i:s'),
            ':next_run' => $nextRun->format('Y-m-d H:i:s'),
            ':message' => $message,
            ':output' => $this->encodeJson($details),
            ':error' => $message,
            ':duration' => $durationMs >= 0 ? $durationMs : null,
            ':id' => $taskId,
        ]);
    }

    public function markSkipped(
        int $taskId,
        DateTimeImmutable $runAt,
        int $interval,
        string $message,
        ?array $details,
        int $durationMs
    ): void {
        $nextRun = $this->nextRunTime($runAt, $interval);
        $stmt = $this->pdo->prepare(
            'UPDATE medforge_cron_tasks
             SET last_run_at = :last_run,
                 next_run_at = :next_run,
                 last_status = "skipped",
                 last_message = :message,
                 last_output = :output,
                 last_duration_ms = :duration,
                 updated_at = CURRENT_TIMESTAMP
             WHERE id = :id'
        );
        $stmt->execute([
            ':last_run' => $runAt->format('Y-m-d H:i:s'),
            ':next_run' => $nextRun->format('Y-m-d H:i:s'),
            ':message' => $message,
            ':output' => $this->encodeJson($details),
            ':duration' => $durationMs >= 0 ? $durationMs : null,
            ':id' => $taskId,
        ]);
    }

    private function nextRunTime(DateTimeImmutable $runAt, int $interval): DateTimeImmutable
    {
        $seconds = max(1, $interval);

        try {
            return $runAt->add(new DateInterval('PT' . $seconds . 'S'));
        } catch (\Exception $exception) {
            return $runAt->add(new DateInterval('PT300S'));
        }
    }

    private function encodeJson(?array $data): ?string
    {
        if ($data === null) {
            return null;
        }

        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (\Throwable $exception) {
            return null;
        }
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM medforge_cron_tasks WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }
}
