<?php
/** @var array<int, array<string, mixed>> $tasks */
/** @var array<int, array<string, mixed>> $logs */
/** @var array<int, array<string, mixed>>|null $results */

$results = $results ?? null;

if (!function_exists('cron_manager_status_badge')) {
    function cron_manager_status_badge(?string $status): string
    {
        $status = strtolower((string) $status);
        $class = match ($status) {
            'success' => 'badge bg-success',
            'failed' => 'badge bg-danger',
            'skipped' => 'badge bg-warning text-dark',
            'running' => 'badge bg-info text-dark',
            default => 'badge bg-secondary',
        };

        return sprintf('<span class="%s text-uppercase">%s</span>', $class, htmlspecialchars($status ?: 'desconocido', ENT_QUOTES, 'UTF-8'));
    }
}

if (!function_exists('cron_manager_format_datetime')) {
    function cron_manager_format_datetime(?string $value): string
    {
        if ($value === null || trim($value) === '') {
            return '—';
        }

        try {
            $dt = new DateTimeImmutable($value);

            return $dt->format('d/m/Y H:i');
        } catch (Throwable) {
            return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        }
    }
}

if (!function_exists('cron_manager_format_duration')) {
    function cron_manager_format_duration(?int $milliseconds): string
    {
        if ($milliseconds === null || $milliseconds <= 0) {
            return '—';
        }

        if ($milliseconds < 1000) {
            return $milliseconds . ' ms';
        }

        $seconds = $milliseconds / 1000;

        if ($seconds < 60) {
            return number_format($seconds, 2) . ' s';
        }

        $minutes = floor($seconds / 60);
        $remaining = $seconds - ($minutes * 60);

        if ($remaining < 1) {
            return $minutes . ' min';
        }

        return sprintf('%d min %0.1f s', $minutes, $remaining);
    }
}

if (!function_exists('cron_manager_render_details')) {
    function cron_manager_render_details(?array $data): string
    {
        if (empty($data)) {
            return '—';
        }

        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false) {
            return '—';
        }

        return '<details><summary>Ver detalles</summary><pre class="mb-0"><code>'
            . htmlspecialchars($json, ENT_QUOTES, 'UTF-8')
            . '</code></pre></details>';
    }
}
?>

<section class="content">
    <div class="row">
        <div class="col-12">
            <?php if (is_array($results) && !empty($results)): ?>
                <div class="alert alert-info">
                    <h5 class="fw-600 mb-10">Resultado de la última ejecución manual</h5>
                    <ul class="mb-0 ps-3">
                        <?php foreach ($results as $item): ?>
                            <?php
                            $status = $item['status'] ?? 'desconocido';
                            $name = $item['name'] ?? ($item['slug'] ?? 'Tarea');
                            $message = $item['message'] ?? '';
                            ?>
                            <li class="mb-5">
                                <?= cron_manager_status_badge($status); ?>
                                <strong><?= htmlspecialchars((string) $name, ENT_QUOTES, 'UTF-8'); ?></strong>
                                <?php if ($message !== ''): ?>
                                    — <?= htmlspecialchars((string) $message, ENT_QUOTES, 'UTF-8'); ?>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-8 col-lg-7">
            <div class="box">
                <div class="box-header with-border d-flex align-items-center">
                    <h4 class="box-title mb-0">Tareas programadas</h4>
                    <form method="post" action="/cron-manager/run" class="ms-auto">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="fa-solid fa-play me-1"></i> Ejecutar cron ahora
                        </button>
                    </form>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Tarea</th>
                                    <th class="text-center">Estado</th>
                                    <th>Última ejecución</th>
                                    <th>Próxima ejecución</th>
                                    <th>Duración</th>
                                    <th class="text-center">Fallos</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tasks as $task): ?>
                                    <?php
                                    $slug = (string) ($task['slug'] ?? '');
                                    $name = (string) ($task['name'] ?? 'Tarea sin nombre');
                                    $description = (string) ($task['description'] ?? '');
                                    $status = $task['last_status'] ?? null;
                                    $lastMessage = (string) ($task['last_message'] ?? '');
                                    $lastOutput = $task['last_output_decoded'] ?? null;
                                    $lastError = (string) ($task['last_error'] ?? '');
                                    $failureCount = (int) ($task['failure_count'] ?? 0);
                                    $intervalLabel = (string) ($task['interval_label'] ?? '—');
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-600"><?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if ($description !== ''): ?>
                                                <div class="text-muted small"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <div class="small text-muted mt-5">Frecuencia: <?= htmlspecialchars($intervalLabel, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if ($lastMessage !== ''): ?>
                                                <div class="small mt-5">Resultado: <?= htmlspecialchars($lastMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($lastOutput)): ?>
                                                <div class="mt-5 small"><?= cron_manager_render_details($lastOutput); ?></div>
                                            <?php endif; ?>
                                            <?php if ($lastError !== ''): ?>
                                                <div class="mt-5 small text-danger">
                                                    <i class="fa-solid fa-circle-exclamation me-1"></i>
                                                    <?= htmlspecialchars($lastError, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?= cron_manager_status_badge($status); ?>
                                        </td>
                                        <td>
                                            <?= cron_manager_format_datetime($task['last_run_at'] ?? null); ?>
                                        </td>
                                        <td>
                                            <?= cron_manager_format_datetime($task['next_run_at'] ?? null); ?>
                                        </td>
                                        <td>
                                            <?= cron_manager_format_duration(isset($task['last_duration_ms']) ? (int) $task['last_duration_ms'] : null); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($failureCount > 0): ?>
                                                <span class="badge bg-danger"><?= $failureCount; ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">0</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <form method="post" action="/cron-manager/run/<?= htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="btn btn-outline-primary btn-sm">
                                                    <i class="fa-solid fa-rotate me-1"></i> Ejecutar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($tasks)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">
                                            No hay tareas registradas todavía.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-lg-5">
            <div class="box">
                <div class="box-header with-border d-flex align-items-center">
                    <h4 class="box-title mb-0">Historial de ejecuciones</h4>
                </div>
                <div class="box-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th>Inicio</th>
                                    <th>Tarea</th>
                                    <th>Estado</th>
                                    <th>Duración</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <?php
                                    $logName = $log['name'] ?? ($log['slug'] ?? 'Tarea');
                                    $logMessage = $log['message'] ?? '';
                                    $logOutput = $log['output_decoded'] ?? null;
                                    $logError = $log['error'] ?? '';
                                    ?>
                                    <tr>
                                        <td><?= cron_manager_format_datetime($log['started_at'] ?? null); ?></td>
                                        <td>
                                            <div class="fw-500"><?= htmlspecialchars((string) $logName, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php if ($logMessage !== ''): ?>
                                                <div class="small text-muted"><?= htmlspecialchars((string) $logMessage, ENT_QUOTES, 'UTF-8'); ?></div>
                                            <?php endif; ?>
                                            <?php if (!empty($logOutput)): ?>
                                                <div class="small mt-3"><?= cron_manager_render_details($logOutput); ?></div>
                                            <?php endif; ?>
                                            <?php if ($logError !== null && trim((string) $logError) !== ''): ?>
                                                <div class="small text-danger mt-3">
                                                    <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                                    <?= htmlspecialchars((string) $logError, ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= cron_manager_status_badge($log['status'] ?? null); ?></td>
                                        <td><?= cron_manager_format_duration(isset($log['duration_ms']) ? (int) $log['duration_ms'] : null); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($logs)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">Aún no hay ejecuciones registradas.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
