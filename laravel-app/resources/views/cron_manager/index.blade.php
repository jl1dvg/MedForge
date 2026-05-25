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
                                        <th>Nombre</th>
                                        <th>Tipo</th>
                                        <th>Frecuencia</th>
                                        <th>Último run</th>
                                        <th class="text-center">Estado</th>
                                        <th class="text-end">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($tasks as $task)
                                        @php
                                            $statusStr  = strtolower((string) ($task->last_status ?? ''));
                                            $badgeClass = match ($statusStr) {
                                                'ok'      => 'badge bg-success',
                                                'failed'  => 'badge bg-danger',
                                                'skipped' => 'badge bg-warning text-dark',
                                                default   => 'badge bg-secondary',
                                            };
                                        @endphp
                                        <tr class="{{ $task->enabled ? '' : 'opacity-50' }}">
                                            <td>
                                                <div class="fw-600">{{ $task->name }}</div>
                                                @if (!empty($task->description))
                                                    <div class="text-muted small">{{ $task->description }}</div>
                                                @endif
                                            </td>
                                            <td>
                                                <span class="badge {{ $task->type === 'artisan' ? 'bg-primary' : 'bg-secondary' }}">
                                                    {{ $task->type }}
                                                </span>
                                            </td>
                                            <td><code class="small">{{ $task->cron_expression }}</code></td>
                                            <td class="small">
                                                @if ($task->last_run_at)
                                                    {{ \Carbon\Carbon::parse($task->last_run_at)->diffForHumans() }}
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @if ($statusStr !== '')
                                                    <span class="{{ $badgeClass }} text-uppercase">{{ $statusStr }}</span>
                                                @else
                                                    <span class="text-muted">—</span>
                                                @endif
                                            </td>
                                            <td class="text-end text-nowrap">
                                                {{-- Editar --}}
                                                <button type="button"
                                                        class="btn btn-sm btn-outline-secondary"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#editCronModal"
                                                        data-slug="{{ $task->slug }}"
                                                        data-name="{{ $task->name }}"
                                                        data-cron="{{ $task->cron_expression }}"
                                                        data-enabled="{{ $task->enabled }}"
                                                        data-bg="{{ $task->run_in_background }}"
                                                        data-overlap="{{ $task->without_overlapping }}"
                                                        title="Editar frecuencia">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>
                                                {{-- Ejecutar ahora --}}
                                                <form method="POST" action="/cron-manager/run/{{ $task->slug }}" style="display:inline">
                                                    @csrf
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Ejecutar ahora">
                                                        <i class="fa-solid fa-rotate"></i>
                                                    </button>
                                                </form>
                                                {{-- Toggle enabled --}}
                                                <form method="POST" action="/cron-manager/toggle/{{ $task->slug }}" style="display:inline">
                                                    @csrf
                                                    <button type="submit"
                                                            class="btn btn-sm {{ $task->enabled ? 'btn-outline-warning' : 'btn-outline-success' }}"
                                                            title="{{ $task->enabled ? 'Pausar' : 'Activar' }}">
                                                        <i class="fa-solid {{ $task->enabled ? 'fa-pause' : 'fa-play' }}"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-4 text-muted">
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

    {{-- Modal de edición de frecuencia --}}
    <div class="modal fade" id="editCronModal" tabindex="-1" aria-labelledby="editCronModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <form method="POST" id="editCronForm" action="">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editCronModalLabel">Editar: <span id="editCronModalTitle"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-600">Frecuencia (cron expression)</label>
                            <input type="text" name="cron_expression" id="editCronExpression"
                                   class="form-control font-monospace" required
                                   placeholder="*/15 * * * *">
                            <div class="form-text text-muted">Formato: minuto hora día-mes mes día-semana</div>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="enabled" id="editCronEnabled"
                                   class="form-check-input" value="1">
                            <label class="form-check-label" for="editCronEnabled">Activo</label>
                        </div>
                        <div class="form-check mb-2">
                            <input type="checkbox" name="run_in_background" id="editCronBg"
                                   class="form-check-input" value="1">
                            <label class="form-check-label" for="editCronBg">En background</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" name="without_overlapping" id="editCronOverlap"
                                   class="form-check-input" value="1">
                            <label class="form-check-label" for="editCronOverlap">Sin solapamiento</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Guardar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
    document.getElementById('editCronModal').addEventListener('show.bs.modal', function(event) {
        const btn = event.relatedTarget;
        const slug = btn.dataset.slug;

        document.getElementById('editCronForm').action = '/cron-manager/edit/' + slug;
        document.getElementById('editCronModalTitle').textContent = btn.dataset.name;
        document.getElementById('editCronExpression').value = btn.dataset.cron;
        document.getElementById('editCronEnabled').checked = btn.dataset.enabled === '1';
        document.getElementById('editCronBg').checked = btn.dataset.bg === '1';
        document.getElementById('editCronOverlap').checked = btn.dataset.overlap === '1';
    });
    </script>
    @endpush
@endsection
