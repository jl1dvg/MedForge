<?php

declare(strict_types=1);

namespace Modules\CronManager\Controllers;

use Core\BaseController;
use Modules\CronManager\Repositories\CronTaskRepository;
use Modules\CronManager\Services\CronRunner;
use PDO;

class CronManagerController extends BaseController
{
    private CronTaskRepository $repository;

    public function __construct(PDO $pdo)
    {
        parent::__construct($pdo);
        $this->repository = new CronTaskRepository($pdo);
    }

    public function index(): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $results = $_SESSION['cron_manager_results'] ?? null;
        unset($_SESSION['cron_manager_results']);

        $tasks = $this->prepareTasks($this->repository->getAll());
        $logs = $this->prepareLogs($this->repository->getRecentLogs(20));

        $this->render('modules/CronManager/views/index.php', [
            'pageTitle' => 'Cron Manager',
            'tasks' => $tasks,
            'logs' => $logs,
            'results' => $results,
        ]);
    }

    public function runAll(): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $runner = new CronRunner($this->pdo);
        $results = $runner->runAll(true);

        $_SESSION['cron_manager_results'] = $results;

        header('Location: /cron-manager');
        exit;
    }

    public function runTask(string $slug): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $runner = new CronRunner($this->pdo);
        $result = $runner->runBySlug($slug, true);

        if ($result === null) {
            $result = [
                'slug' => $slug,
                'status' => 'failed',
                'message' => 'La tarea solicitada no existe.',
                'ran' => false,
            ];
        }

        $_SESSION['cron_manager_results'] = [$result];

        header('Location: /cron-manager');
        exit;
    }

    public function updateSettings(string $slug): void
    {
        $this->requireAuth();
        $this->requirePermission(['settings.manage', 'administrativo']);

        $task = $this->repository->findBySlug($slug);
        if ($task === null) {
            $_SESSION['cron_manager_results'] = [[
                'slug' => $slug,
                'status' => 'failed',
                'message' => 'La tarea solicitada no existe.',
                'ran' => false,
            ]];
            header('Location: /cron-manager');
            exit;
        }

        $start = trim((string) ($_POST['date_start'] ?? ''));
        $end = trim((string) ($_POST['date_end'] ?? ''));
        $settings = [];

        if ($start !== '' || $end !== '') {
            $startDate = $this->parseDate($start);
            $endDate = $this->parseDate($end);

            if ($startDate === null || $endDate === null) {
                $_SESSION['cron_manager_results'] = [[
                    'slug' => $slug,
                    'status' => 'failed',
                    'message' => 'Formato de fecha inválido. Usa YYYY-MM-DD.',
                    'ran' => false,
                ]];
                header('Location: /cron-manager');
                exit;
            }

            if ($startDate > $endDate) {
                $_SESSION['cron_manager_results'] = [[
                    'slug' => $slug,
                    'status' => 'failed',
                    'message' => 'El rango de fechas es inválido: inicio mayor que fin.',
                    'ran' => false,
                ]];
                header('Location: /cron-manager');
                exit;
            }

            $days = $startDate->diff($endDate)->days ?? 0;
            if ($days > 31) {
                $_SESSION['cron_manager_results'] = [[
                    'slug' => $slug,
                    'status' => 'failed',
                    'message' => 'El rango máximo permitido es de 31 días.',
                    'ran' => false,
                ]];
                header('Location: /cron-manager');
                exit;
            }

            $settings = [
                'date_start' => $startDate->format('Y-m-d'),
                'date_end' => $endDate->format('Y-m-d'),
            ];
        }

        $this->repository->updateSettings((int) $task['id'], $settings ?: null);

        $_SESSION['cron_manager_results'] = [[
            'slug' => $slug,
            'name' => $task['name'] ?? $slug,
            'status' => 'success',
            'message' => $settings ? 'Configuración guardada.' : 'Configuración restablecida a automático.',
            'details' => $settings ?: ['modo' => 'automatico'],
            'ran' => false,
        ]];

        header('Location: /cron-manager');
        exit;
    }

    /**
     * @param array<int, array<string, mixed>> $tasks
     * @return array<int, array<string, mixed>>
     */
    private function prepareTasks(array $tasks): array
    {
        return array_map(function (array $task): array {
            $task['last_output_decoded'] = $this->decodeJson($task['last_output'] ?? null);
            $task['settings_decoded'] = $this->decodeJson($task['settings'] ?? null);
            $task['interval_label'] = $this->formatInterval((int) ($task['schedule_interval'] ?? 0));

            return $task;
        }, $tasks);
    }

    /**
     * @param array<int, array<string, mixed>> $logs
     * @return array<int, array<string, mixed>>
     */
    private function prepareLogs(array $logs): array
    {
        return array_map(function (array $log): array {
            $log['output_decoded'] = $this->decodeJson($log['output'] ?? null);

            return $log;
        }, $logs);
    }

    private function decodeJson(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function parseDate(string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $value);
        if ($date === false) {
            return null;
        }

        return $date->format('Y-m-d') === $value ? $date : null;
    }

    private function formatInterval(int $seconds): string
    {
        if ($seconds <= 0) {
            return '—';
        }

        $minutes = intdiv($seconds, 60);
        $remaining = $seconds % 60;

        if ($minutes === 0) {
            return sprintf('%d segundos', $seconds);
        }

        if ($remaining === 0) {
            return sprintf('%d minutos', $minutes);
        }

        return sprintf('%d min %d s', $minutes, $remaining);
    }
}
