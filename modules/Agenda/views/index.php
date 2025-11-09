<?php
/** @var array{fecha_inicio: string, fecha_fin: string, doctor: ?string, estado: ?string, sede: ?string, solo_con_visita: bool} $filters */
/** @var array<int, array<string, mixed>> $agenda */
/** @var array<int, string> $estadosDisponibles */
/** @var array<int, string> $doctoresDisponibles */
/** @var array<int, array{id_sede: ?string, sede_departamento: ?string}> $sedesDisponibles */

function agenda_badge_class(?string $estado): string
{
    $estado = strtoupper(trim((string) $estado));
    return match ($estado) {
        'AGENDADO', 'PROGRAMADO' => 'badge bg-primary-light text-primary',
        'LLEGADO', 'EN CURSO' => 'badge bg-success-light text-success',
        'ATENDIDO', 'COMPLETADO' => 'badge bg-success text-white',
        'CANCELADO' => 'badge bg-danger-light text-danger',
        'NO LLEGO', 'NO LLEGÓ', 'NO_ASISTIO', 'NO ASISTIO' => 'badge bg-warning-light text-warning',
        default => 'badge bg-secondary',
    };
}
?>

<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Agenda de procedimientos</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Agenda</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="ms-auto text-muted fw-600">
            <?= count($agenda) ?> resultados
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Filtros</h4>
                </div>
                <div class="box-body">
                    <form method="get" class="row g-3">
                        <div class="col-sm-6 col-md-3">
                            <label for="fecha_inicio" class="form-label fw-600">Fecha desde</label>
                            <input type="date"
                                   id="fecha_inicio"
                                   name="fecha_inicio"
                                   value="<?= htmlspecialchars($filters['fecha_inicio']) ?>"
                                   class="form-control">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="fecha_fin" class="form-label fw-600">Fecha hasta</label>
                            <input type="date"
                                   id="fecha_fin"
                                   name="fecha_fin"
                                   value="<?= htmlspecialchars($filters['fecha_fin']) ?>"
                                   class="form-control">
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="doctor" class="form-label fw-600">Doctor</label>
                            <select id="doctor" name="doctor" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($doctoresDisponibles as $doctor): ?>
                                    <option value="<?= htmlspecialchars($doctor) ?>"
                                        <?= $filters['doctor'] === $doctor ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($doctor) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="estado" class="form-label fw-600">Estado</label>
                            <select id="estado" name="estado" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($estadosDisponibles as $estado): ?>
                                    <option value="<?= htmlspecialchars($estado) ?>"
                                        <?= $filters['estado'] === $estado ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($estado) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3">
                            <label for="sede" class="form-label fw-600">Sede</label>
                            <select id="sede" name="sede" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($sedesDisponibles as $sede): ?>
                                    <?php
                                    $value = $sede['id_sede'] ?: $sede['sede_departamento'];
                                    $labelParts = array_filter([
                                        $sede['sede_departamento'] ?? null,
                                        $sede['id_sede'] ? ('#' . $sede['id_sede']) : null,
                                    ]);
                                    $label = $labelParts ? implode(' ', $labelParts) : 'Sin nombre';
                                    ?>
                                    <option value="<?= htmlspecialchars((string) $value) ?>"
                                        <?= $filters['sede'] === $value ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-sm-6 col-md-3 d-flex align-items-end">
                            <div class="form-check">
                                <input class="form-check-input"
                                       type="checkbox"
                                       value="1"
                                       id="solo_con_visita"
                                       name="solo_con_visita"
                                       <?= $filters['solo_con_visita'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="solo_con_visita">
                                    Solo con encuentro asignado
                                </label>
                            </div>
                        </div>
                        <div class="col-12 d-flex gap-2 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="mdi mdi-magnify"></i> Filtrar
                            </button>
                            <a href="/agenda" class="btn btn-light">Limpiar</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border">
                    <h4 class="box-title">Procedimientos proyectados</h4>
                </div>
                <div class="box-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle">
                            <thead class="bg-primary-light">
                            <tr>
                                <th>Fecha</th>
                                <th>Hora</th>
                                <th>Form ID</th>
                                <th>Paciente</th>
                                <th>Procedimiento</th>
                                <th>Doctor</th>
                                <th>Estado</th>
                                <th>Sede</th>
                                <th>Encuentro</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if ($agenda): ?>
                                <?php foreach ($agenda as $registro): ?>
                                    <?php
                                    $fechaAgenda = $registro['fecha_agenda'] ? date('d/m/Y', strtotime((string) $registro['fecha_agenda'])) : '—';
                                    $horaAgenda = $registro['hora_agenda'] ?? '—';
                                    $paciente = $registro['paciente'] ?: 'Sin registro';
                                    $sedeNombre = $registro['sede_departamento'] ?: ($registro['id_sede'] ?: '—');
                                    $estado = $registro['estado_agenda'] ?? 'Sin estado';
                                    $visitaId = $registro['visita_id'] ?? null;
                                    ?>
                                    <tr>
                                        <td><?= htmlspecialchars($fechaAgenda) ?></td>
                                        <td><?= htmlspecialchars($horaAgenda) ?></td>
                                        <td>
                                            <span class="badge bg-info-light text-primary fw-600">
                                                <?= htmlspecialchars((string) $registro['form_id']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="fw-600 text-dark"><?= htmlspecialchars($paciente) ?></div>
                                            <div class="text-muted small">HC <?= htmlspecialchars((string) $registro['hc_number']) ?></div>
                                            <div>
                                                <a class="small" href="/pacientes/detalles?hc_number=<?= urlencode((string) $registro['hc_number']) ?>">
                                                    Ver ficha
                                                </a>
                                            </div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($registro['procedimiento'] ?? '—')) ?></td>
                                        <td><?= htmlspecialchars((string) ($registro['doctor'] ?? '—')) ?></td>
                                        <td>
                                            <span class="<?= agenda_badge_class($estado) ?>">
                                                <?= htmlspecialchars($estado) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars((string) $sedeNombre) ?></td>
                                        <td>
                                            <?php if ($visitaId): ?>
                                                <a class="btn btn-sm btn-outline-primary" href="/agenda/visitas/<?= urlencode((string) $visitaId) ?>">
                                                    <i class="mdi mdi-link-variant"></i> Ver encuentro
                                                </a>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Sin encuentro</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4 text-muted">
                                        No se encontraron procedimientos proyectados para los filtros seleccionados.
                                    </td>
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
