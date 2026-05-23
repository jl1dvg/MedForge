<?php

declare(strict_types=1);

namespace App\Modules\CronManager\Http\Controllers;

use App\Modules\CronManager\Repositories\CronTaskRepository;
use App\Modules\CronManager\Services\CronRunner;
use App\Modules\Shared\Support\LegacyCurrentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CronManagerController
{
    private CronTaskRepository $repository;

    public function __construct()
    {
        $pdo = DB::connection()->getPdo();
        $this->repository = new CronTaskRepository($pdo);
    }

    public function index(Request $request): View
    {
        $results = $request->session()->pull('cron_manager_results');

        $pdo = DB::connection()->getPdo();
        $tasks = $this->prepareTasks($this->repository->getAll());
        $logs = $this->prepareLogs($this->repository->getRecentLogs(20));

        return view('cron_manager.index', [
            'pageTitle' => 'Cron Manager',
            'currentUser' => LegacyCurrentUser::resolve($request),
            'tasks' => $tasks,
            'logs' => $logs,
            'results' => $results,
        ]);
    }

    public function runAll(Request $request): RedirectResponse
    {
        $pdo = DB::connection()->getPdo();
        $runner = new CronRunner($pdo);
        $results = $runner->runAll(true);

        $request->session()->put('cron_manager_results', $results);

        return redirect('/cron-manager');
    }

    public function runTask(Request $request, string $slug): RedirectResponse
    {
        $pdo = DB::connection()->getPdo();
        $runner = new CronRunner($pdo);
        $result = $runner->runBySlug($slug, true);

        if ($result === null) {
            $result = [
                'slug' => $slug,
                'status' => 'failed',
                'message' => 'La tarea solicitada no existe.',
                'ran' => false,
            ];
        }

        $request->session()->put('cron_manager_results', [$result]);

        return redirect('/cron-manager');
    }

    public function updateSettings(Request $request, string $slug): RedirectResponse
    {
        $task = $this->repository->findBySlug($slug);
        if ($task === null) {
            $request->session()->put('cron_manager_results', [[
                'slug' => $slug,
                'status' => 'failed',
                'message' => 'La tarea solicitada no existe.',
                'ran' => false,
            ]]);
            return redirect('/cron-manager');
        }

        $start = trim((string) $request->input('date_start', ''));
        $end = trim((string) $request->input('date_end', ''));
        $settings = [];

        if ($start !== '' || $end !== '') {
            $startDate = $this->parseDate($start);
            $endDate = $this->parseDate($end);

            if ($startDate === null || $endDate === null) {
                $request->session()->put('cron_manager_results', [[
                    'slug' => $slug,
                    'status' => 'failed',
                    'message' => 'Formato de fecha inválido. Usa YYYY-MM-DD.',
                    'ran' => false,
                ]]);
                return redirect('/cron-manager');
            }

            if ($startDate > $endDate) {
                $request->session()->put('cron_manager_results', [[
                    'slug' => $slug,
                    'status' => 'failed',
                    'message' => 'El rango de fechas es inválido: inicio mayor que fin.',
                    'ran' => false,
                ]]);
                return redirect('/cron-manager');
            }

            $days = $startDate->diff($endDate)->days ?? 0;
            if ($days > 31) {
                $request->session()->put('cron_manager_results', [[
                    'slug' => $slug,
                    'status' => 'failed',
                    'message' => 'El rango máximo permitido es de 31 días.',
                    'ran' => false,
                ]]);
                return redirect('/cron-manager');
            }

            $settings = [
                'date_start' => $startDate->format('Y-m-d'),
                'date_end' => $endDate->format('Y-m-d'),
            ];
        }

        $this->repository->updateSettings((int) $task['id'], $settings ?: null);

        $request->session()->put('cron_manager_results', [[
            'slug' => $slug,
            'name' => $task['name'] ?? $slug,
            'status' => 'success',
            'message' => $settings ? 'Configuración guardada.' : 'Configuración restablecida a automático.',
            'details' => $settings ?: ['modo' => 'automatico'],
            'ran' => false,
        ]]);

        return redirect('/cron-manager');
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
