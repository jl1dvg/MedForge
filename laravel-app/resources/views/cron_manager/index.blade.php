@extends('layouts.medforge')

@section('content')
    <div class="content-header">
        <div class="d-flex align-items-center">
            <div class="me-auto">
                <h3 class="page-title">{{ $pageTitle ?? 'Cron Manager' }}</h3>
                <div class="d-inline-block align-items-center">
                    <nav>
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="/v2/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                            <li class="breadcrumb-item active" aria-current="page">Cron Manager</li>
                        </ol>
                    </nav>
                </div>
            </div>
        </div>
    </div>

    <section class="content">
        <div class="row">
            <div class="col-12">
                @if (!empty($results))
                    <div class="alert alert-info">
                        <h5 class="fw-600 mb-10">Resultado de la última ejecución manual</h5>
                        <ul class="mb-0 ps-3">
                            @foreach ($results as $item)
                                @php
                                    $status   = $item['status'] ?? 'desconocido';
                                    $name     = $item['name'] ?? ($item['slug'] ?? 'Tarea');
                                    $message  = $item['message'] ?? '';
                                    $badgeClass = match (strtolower((string) $status)) {
                                        'success' => 'badge bg-success',
                                        'failed'  => 'badge bg-danger',
                                        'skipped' => 'badge bg-warning text-dark',
                                        'running' => 'badge bg-info text-dark',
                                        default   => 'badge bg-secondary',
                                    };
                                @endphp
                                <li class="mb-5">
                                    <span class="{{ $badgeClass }} text-uppercase">{{ strtolower((string) $status) ?: 'desconocido' }}</span>
                                    <strong>{{ $name }}</strong>
                                    @if ($message !== '')
                                        — {{ $message }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <div class="row">
            {{-- Tasks table --}}
            <div class="col-xl-8 col-lg-7">
                <div class="box">
                    <div class="box-header with-border d-flex align-items-center">
                        <h4 class="box-title mb-0">Tareas programadas</h4>
                        <form method="post" action="/cron-manager/run" class="ms-auto">
                            @csrf
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
                                    @forelse ($tasks as $task)
                                        @php
                                            $slug          = (string) ($task['slug'] ?? '');
                                            $name          = (string) ($task['name'] ?? 'Tarea sin nombre');
                                            $description   = (string) ($task['description'] ?? '');
                                            $status        = $task['last_status'] ?? null;
                                            $lastMessage   = (string) ($task['last_message'] ?? '');
                                            $lastOutput    = $task['last_output_decoded'] ?? null;
                                            $lastError     = (string) ($task['last_error'] ?? '');
                                            $failureCount  = (int) ($task['failure_count'] ?? 0);
                                            $intervalLabel = (string) ($task['interval_label'] ?? '—');
                                            $settings      = $task['settings_decoded'] ?? null;

                                            $statusStr   = strtolower((string) $status);
                                            $badgeClass  = match ($statusStr) {
                                                'success' => 'badge bg-success',
                                                'failed'  => 'badge bg-danger',
                                                'skipped' => 'badge bg-warning text-dark',
                                                'running' => 'badge bg-info text-dark',
                                                default   => 'badge bg-secondary',
                                            };

                                            // Format datetimes
                                            $formatDt = function (?string $v): string {
                                                if ($v === null || trim($v) === '') return '—';
                                                try { return (new DateTimeImmutable($v))->format('d/m/Y H:i'); }
                                                catch (Throwable) { return e($v); }
                                            };

                                            // Format duration
                                            $formatDuration = function (?int $ms): string {
                                                if ($ms === null || $ms <= 0) return '—';
                                                if ($ms < 1000) return $ms . ' ms';
                                                $s = $ms / 1000;
                                                if ($s < 60) return number_format($s, 2) . ' s';
                                                $m = floor($s / 60);
                                                $r = $s - ($m * 60);
                                                return $r < 1 ? $m . ' min' : sprintf('%d min %0.1f s', $m, $r);
                                            };
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="fw-600">{{ $name }}</div>
                                                @if ($description !== '')
                                                    <div class="text-muted small">{{ $description }}</div>
                                                @endif
                                                <div class="small text-muted mt-5">Frecuencia: {{ $intervalLabel }}</div>
                                                @if ($lastMessage !== '')
                                                    <div class="small mt-5">Resultado: {{ $lastMessage }}</div>
                                                @endif
                                                @if (!empty($lastOutput))
                                                    @php
                                                        $json = json_encode($lastOutput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                                                    @endphp
                                                    <div class="mt-5 small">
                                                        <details>
                                                            <summary>Ver detalles</summary>
                                                            <pre class="mb-0"><code>{{ $json ?: '—' }}</code></pre>
                                                        </details>
                                                    </div>
                                                @endif
                                                @if ($lastError !== '')
                                                    <div class="mt-5 small text-danger">
                                                        <i class="fa-solid fa-circle-exclamation me-1"></i>
                                                        {{ $lastError }}
                                                    </div>
                                                @endif
                                                @if ($slug === 'cive-index-admisiones-sync')
                                                    @php
                                                        $defaultStart  = (new DateTimeImmutable('today'))->sub(new DateInterval('P1D'))->format('Y-m-d');
                                                        $defaultEnd    = (new DateTimeImmutable('today'))->add(new DateInterval('P1D'))->format('Y-m-d');
                                                        $currentStart  = $settings['date_start'] ?? '';
                                                        $currentEnd    = $settings['date_end'] ?? '';
                                                    @endphp
                                                    <div class="mt-10 border-top pt-10 small">
                                                        <div class="fw-600 mb-5">Rango para index-admisiones</div>
                                                        <div class="text-muted mb-5">
                                                            Automático: {{ $defaultStart }} &rarr; {{ $defaultEnd }}
                                                        </div>
                                                        <form method="post" action="/cron-manager/settings/{{ $slug }}" class="row g-2 align-items-end">
                                                            @csrf
                                                            <div class="col-12 col-md-5">
                                                                <label class="form-label mb-1">Inicio</label>
                                                                <input type="date" name="date_start" class="form-control form-control-sm" value="{{ $currentStart }}">
                                                            </div>
                                                            <div class="col-12 col-md-5">
                                                                <label class="form-label mb-1">Fin</label>
                                                                <input type="date" name="date_end" class="form-control form-control-sm" value="{{ $currentEnd }}">
                                                            </div>
                                                            <div class="col-12 col-md-2">
                                                                <button type="submit" class="btn btn-outline-secondary btn-sm w-100">
                                                                    Guardar
                                                                </button>
                                                            </div>
                                                            <div class="col-12">
                                                                <small class="text-muted">Deja en blanco para usar automático (máx. 31 días en modo manual).</small>
                                                            </div>
                                                        </form>
                                                    </div>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                <span class="{{ $badgeClass }} text-uppercase">{{ $statusStr ?: 'desconocido' }}</span>
                                            </td>
                                            <td>{{ $formatDt($task['last_run_at'] ?? null) }}</td>
                                            <td>{{ $formatDt($task['next_run_at'] ?? null) }}</td>
                                            <td>{{ $formatDuration(isset($task['last_duration_ms']) ? (int) $task['last_duration_ms'] : null) }}</td>
                                            <td class="text-center">
                                                @if ($failureCount > 0)
                                                    <span class="badge bg-danger">{{ $failureCount }}</span>
                                                @else
                                                    <span class="text-muted">0</span>
                                                @endif
                                            </td>
                                            <td class="text-end">
                                                <form method="post" action="/cron-manager/run/{{ $slug }}">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-primary btn-sm">
                                                        <i class="fa-solid fa-rotate me-1"></i> Ejecutar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                No hay tareas registradas todavía.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Logs sidebar --}}
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
                                    @forelse ($logs as $log)
                                        @php
                                            $logName    = $log['name'] ?? ($log['slug'] ?? 'Tarea');
                                            $logMessage = $log['message'] ?? '';
                                            $logOutput  = $log['output_decoded'] ?? null;
                                            $logError   = $log['error'] ?? '';
                                            $logStatus  = strtolower((string) ($log['status'] ?? ''));
                                            $logBadge   = match ($logStatus) {
                                                'success' => 'badge bg-success',
                                                'failed'  => 'badge bg-danger',
                                                'skipped' => 'badge bg-warning text-dark',
                                                'running' => 'badge bg-info text-dark',
                                                default   => 'badge bg-secondary',
                                            };
                                            $logDt = function (?string $v): string {
                                                if ($v === null || trim($v) === '') return '—';
                                                try { return (new DateTimeImmutable($v))->format('d/m/Y H:i'); }
                                                catch (Throwable) { return e($v); }
                                            };
                                            $logDur = function (?int $ms): string {
                                                if ($ms === null || $ms <= 0) return '—';
                                                if ($ms < 1000) return $ms . ' ms';
                                                $s = $ms / 1000;
                                                if ($s < 60) return number_format($s, 2) . ' s';
                                                $m = floor($s / 60);
                                                $r = $s - ($m * 60);
                                                return $r < 1 ? $m . ' min' : sprintf('%d min %0.1f s', $m, $r);
                                            };
                                        @endphp
                                        <tr>
                                            <td>{{ $logDt($log['started_at'] ?? null) }}</td>
                                            <td>
                                                <div class="fw-500">{{ $logName }}</div>
                                                @if ($logMessage !== '')
                                                    <div class="small text-muted">{{ $logMessage }}</div>
                                                @endif
                                                @if (!empty($logOutput))
                                                    @php
                                                        $logJson = json_encode($logOutput, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                                                    @endphp
                                                    <div class="small mt-3">
                                                        <details>
                                                            <summary>Ver detalles</summary>
                                                            <pre class="mb-0"><code>{{ $logJson ?: '—' }}</code></pre>
                                                        </details>
                                                    </div>
                                                @endif
                                                @if (trim((string) $logError) !== '')
                                                    <div class="small text-danger mt-3">
                                                        <i class="fa-solid fa-triangle-exclamation me-1"></i>
                                                        {{ $logError }}
                                                    </div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="{{ $logBadge }} text-uppercase">{{ $logStatus ?: 'desconocido' }}</span>
                                            </td>
                                            <td>{{ $logDur(isset($log['duration_ms']) ? (int) $log['duration_ms'] : null) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                Aún no hay ejecuciones registradas.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
@endsection
