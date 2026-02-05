<?php
/** @var array $viewData */

$examen = $viewData['examen'] ?? [];
$consulta = $viewData['consulta'] ?? [];
$paciente = $viewData['paciente'] ?? [];
$diagnosticos = $viewData['diagnostico'] ?? [];
$imagenesSolicitadas = $viewData['imagenes_solicitadas'] ?? [];
$trazabilidad = $viewData['trazabilidad'] ?? [];
$crm = $viewData['crm'] ?? [];
$crmDetalle = $crm['detalle'] ?? [];
$crmAdjuntos = $crm['adjuntos'] ?? [];

if (empty($examen)) {
    echo '<p class="text-muted mb-0">No se encontraron detalles para este examen.</p>';
    return;
}

$nombrePaciente = trim(implode(' ', array_filter([
    $paciente['fname'] ?? '',
    $paciente['mname'] ?? '',
    $paciente['lname'] ?? '',
    $paciente['lname2'] ?? '',
])));
if ($nombrePaciente === '') {
    $nombrePaciente = trim((string) ($examen['full_name'] ?? 'Paciente sin nombre'));
}

$afiliacion = trim((string) ($paciente['afiliacion'] ?? ($examen['afiliacion'] ?? '')));
$telefono = trim((string) ($crmDetalle['crm_contacto_telefono'] ?? ($paciente['celular'] ?? ($examen['paciente_celular'] ?? ''))));
$correo = trim((string) ($crmDetalle['crm_contacto_email'] ?? ($examen['crm_contacto_email'] ?? '')));
$pipeline = trim((string) ($crmDetalle['crm_pipeline_stage'] ?? ($examen['crm_pipeline_stage'] ?? 'Recibido')));
$responsable = trim((string) ($crmDetalle['crm_responsable_nombre'] ?? ($examen['crm_responsable_nombre'] ?? 'Sin responsable')));
$fuente = trim((string) ($crmDetalle['crm_fuente'] ?? ($examen['crm_fuente'] ?? 'Consulta')));

$fechaExamen = $examen['consulta_fecha'] ?? $examen['created_at'] ?? null;
$fechaTexto = 'No disponible';
if ($fechaExamen) {
    try {
        $fechaTexto = (new DateTime((string) $fechaExamen))->format('d-m-Y H:i');
    } catch (Exception $e) {
        $fechaTexto = (string) $fechaExamen;
    }
}

$estadoRaw = trim((string) ($examen['estado'] ?? 'Pendiente'));
$estadoKey = strtolower($estadoRaw);
$estadoMap = [
    'recibido' => 'secondary',
    'llamado' => 'warning',
    'revision de cobertura' => 'info',
    'revisión de cobertura' => 'info',
    'listo para agenda' => 'dark',
    'completado' => 'success',
    'atendido' => 'success',
];
$estadoColor = $estadoMap[$estadoKey] ?? 'secondary';

$prioridad = trim((string) ($examen['prioridad'] ?? 'Normal'));
$lateralidad = trim((string) ($examen['lateralidad'] ?? 'No definida'));
$solicitante = trim((string) ($examen['solicitante'] ?? 'No definido'));

$totalNotas = (int) (($crmDetalle['crm_total_notas'] ?? $examen['crm_total_notas'] ?? 0));
$totalAdjuntos = (int) (($crmDetalle['crm_total_adjuntos'] ?? $examen['crm_total_adjuntos'] ?? 0));
$tareasPendientes = (int) (($crmDetalle['crm_tareas_pendientes'] ?? $examen['crm_tareas_pendientes'] ?? 0));
$tareasTotal = (int) (($crmDetalle['crm_tareas_total'] ?? $examen['crm_tareas_total'] ?? 0));
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">

<div class="d-flex flex-column gap-3">
    <div id="prefacturaPatientSummary" class="card border-0 shadow-sm">
        <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3">
            <div>
                <h5 class="mb-1"><?= htmlspecialchars($nombrePaciente !== '' ? $nombrePaciente : 'Paciente sin nombre', ENT_QUOTES, 'UTF-8') ?></h5>
                <div class="text-muted small">
                    HC <?= htmlspecialchars((string) ($examen['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?> ·
                    Formulario <?= htmlspecialchars((string) ($examen['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
                <div class="text-muted small mt-1">
                    <?= htmlspecialchars($afiliacion !== '' ? $afiliacion : 'Sin afiliación', ENT_QUOTES, 'UTF-8') ?>
                </div>
            </div>
            <div class="d-flex flex-wrap align-items-start gap-2">
                <span class="badge bg-<?= htmlspecialchars($estadoColor, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($estadoRaw !== '' ? $estadoRaw : 'Pendiente', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-info text-dark"><?= htmlspecialchars($pipeline !== '' ? $pipeline : 'Recibido', ENT_QUOTES, 'UTF-8') ?></span>
                <span class="badge bg-light text-dark border">Prioridad <?= htmlspecialchars($prioridad, ENT_QUOTES, 'UTF-8') ?></span>
                <?php if (!empty($examen['turno'])): ?>
                    <span class="badge bg-primary">Turno #<?= htmlspecialchars((string) $examen['turno'], ENT_QUOTES, 'UTF-8') ?></span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div id="prefacturaState" class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="row g-2 small">
                <div class="col-md-3"><strong>Responsable:</strong> <?= htmlspecialchars($responsable, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Fuente:</strong> <?= htmlspecialchars($fuente !== '' ? $fuente : 'Consulta', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Teléfono:</strong> <?= htmlspecialchars($telefono !== '' ? $telefono : 'Sin teléfono', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Correo:</strong> <?= htmlspecialchars($correo !== '' ? $correo : 'Sin correo', ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Notas:</strong> <?= htmlspecialchars((string) $totalNotas, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Adjuntos:</strong> <?= htmlspecialchars((string) $totalAdjuntos, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Tareas:</strong> <?= htmlspecialchars((string) $tareasPendientes, ENT_QUOTES, 'UTF-8') ?>/<?= htmlspecialchars((string) $tareasTotal, ENT_QUOTES, 'UTF-8') ?></div>
                <div class="col-md-3"><strong>Fecha:</strong> <?= htmlspecialchars($fechaTexto, ENT_QUOTES, 'UTF-8') ?></div>
            </div>
        </div>
    </div>

    <ul class="nav nav-tabs" id="prefacturaTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="prefactura-tab-resumen-tab" data-bs-toggle="tab" data-bs-target="#prefactura-tab-resumen" type="button" role="tab" aria-controls="prefactura-tab-resumen" aria-selected="true">Resumen clínico/operativo</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prefactura-tab-imagenes-tab" data-bs-toggle="tab" data-bs-target="#prefactura-tab-imagenes" type="button" role="tab" aria-controls="prefactura-tab-imagenes" aria-selected="false">Imágenes solicitadas</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="prefactura-tab-trazabilidad-tab" data-bs-toggle="tab" data-bs-target="#prefactura-tab-trazabilidad" type="button" role="tab" aria-controls="prefactura-tab-trazabilidad" aria-selected="false">Trazabilidad</button>
        </li>
    </ul>

    <div class="tab-content border border-top-0 rounded-bottom p-3 bg-white" id="prefacturaTabsContent">
        <div class="tab-pane fade show active" id="prefactura-tab-resumen" role="tabpanel" aria-labelledby="prefactura-tab-resumen-tab">
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border h-100">
                        <div class="card-body">
                            <h6 class="card-title">Detalle del examen</h6>
                            <ul class="list-group list-group-flush small">
                                <li class="list-group-item px-0"><strong>Examen:</strong> <?= htmlspecialchars((string) ($examen['examen_nombre'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item px-0"><strong>Código:</strong> <?= htmlspecialchars((string) ($examen['examen_codigo'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item px-0"><strong>Doctor:</strong> <?= htmlspecialchars((string) ($examen['doctor'] ?? 'No definido'), ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item px-0"><strong>Solicitante:</strong> <?= htmlspecialchars($solicitante, ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item px-0"><strong>Lateralidad:</strong> <?= htmlspecialchars($lateralidad, ENT_QUOTES, 'UTF-8') ?></li>
                                <li class="list-group-item px-0"><strong>Observaciones:</strong> <?= htmlspecialchars((string) ($examen['observaciones'] ?? 'Sin observaciones'), ENT_QUOTES, 'UTF-8') ?></li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border h-100">
                        <div class="card-body">
                            <h6 class="card-title">Contexto de consulta</h6>
                            <p class="small mb-2"><strong>Motivo:</strong> <?= htmlspecialchars((string) ($consulta['motivo_consulta'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="small mb-2"><strong>Enfermedad actual:</strong> <?= htmlspecialchars((string) ($consulta['enfermedad_actual'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="small mb-2"><strong>Examen físico:</strong> <?= htmlspecialchars((string) ($consulta['examen_fisico'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="small mb-0"><strong>Plan:</strong> <?= htmlspecialchars((string) ($consulta['plan'] ?? 'No disponible'), ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border">
                        <div class="card-body">
                            <h6 class="card-title">Diagnósticos asociados</h6>
                            <?php if (!empty($diagnosticos) && is_array($diagnosticos)): ?>
                                <ul class="mb-0 small">
                                    <?php foreach ($diagnosticos as $dx): ?>
                                        <li>
                                            <?= htmlspecialchars((string) ($dx['dx_code'] ?? $dx['codigo'] ?? 'DX'), ENT_QUOTES, 'UTF-8') ?> -
                                            <?= htmlspecialchars((string) ($dx['descripcion'] ?? $dx['label'] ?? 'Sin descripción'), ENT_QUOTES, 'UTF-8') ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p class="text-muted small mb-0">No hay diagnósticos cargados en la consulta.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <div class="card border" id="prefacturaChecklistCard" data-examen-id="<?= htmlspecialchars((string) ($examen['id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card-body">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-2">
                                <h6 class="card-title mb-0">Checklist operativo (CRM)</h6>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="prefacturaChecklistBootstrapBtn">
                                    <i class="bi bi-arrow-repeat me-1"></i>Sincronizar
                                </button>
                            </div>
                            <div class="progress mb-2" style="height: 8px;">
                                <div class="progress-bar" id="prefacturaChecklistProgressBar" role="progressbar" style="width: 0%;" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                            <div class="small text-muted mb-2" id="prefacturaChecklistProgressText">Cargando checklist...</div>
                            <div id="prefacturaChecklistList" class="d-flex flex-column gap-2"></div>
                            <div class="text-muted small d-none" id="prefacturaChecklistEmpty">Sin checklist disponible.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="tab-pane fade" id="prefactura-tab-imagenes" role="tabpanel" aria-labelledby="prefactura-tab-imagenes-tab">
            <h6 class="mb-3">Estudios de imagen solicitados</h6>
            <?php if (!empty($imagenesSolicitadas)): ?>
                <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                        <tr>
                            <th>Estudio</th>
                            <th>Estado</th>
                            <th>Fuente</th>
                            <th>Fecha</th>
                            <th>Evidencia</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($imagenesSolicitadas as $item): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars((string) ($item['nombre'] ?? 'Sin nombre'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($item['codigo'])): ?>
                                        <small class="text-muted">Código: <?= htmlspecialchars((string) $item['codigo'], ENT_QUOTES, 'UTF-8') ?></small>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars((string) ($item['estado'] ?? 'Solicitado'), ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars((string) ($item['fuente'] ?? 'Consulta'), ENT_QUOTES, 'UTF-8') ?></td>
                                <td>
                                    <?php
                                    $fechaItem = $item['fecha'] ?? null;
                                    if ($fechaItem) {
                                        try {
                                            echo htmlspecialchars((new DateTime((string) $fechaItem))->format('d-m-Y H:i'), ENT_QUOTES, 'UTF-8');
                                        } catch (Exception $e) {
                                            echo htmlspecialchars((string) $fechaItem, ENT_QUOTES, 'UTF-8');
                                        }
                                    } else {
                                        echo '<span class="text-muted">No disponible</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if (!empty($item['evidencias_count'])): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars((string) $item['evidencias_count'], ENT_QUOTES, 'UTF-8') ?> archivo(s)</span>
                                    <?php else: ?>
                                        <span class="text-muted small">Sin evidencia directa</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-muted mb-3">
                    No se identificaron estudios de imagen en la consulta. Se muestran únicamente los datos operativos disponibles.
                </div>
            <?php endif; ?>

            <h6 class="mb-2 mt-4">Evidencia local (adjuntos CRM)</h6>
            <?php if (!empty($crmAdjuntos)): ?>
                <div class="list-group">
                    <?php foreach ($crmAdjuntos as $adjunto): ?>
                        <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start"
                           href="<?= htmlspecialchars((string) ($adjunto['url'] ?? '#'), ENT_QUOTES, 'UTF-8') ?>"
                           target="_blank"
                           rel="noopener">
                            <div>
                                <div class="fw-semibold"><?= htmlspecialchars((string) ($adjunto['descripcion'] ?? $adjunto['nombre_original'] ?? 'Documento'), ENT_QUOTES, 'UTF-8') ?></div>
                                <small class="text-muted"><?= htmlspecialchars((string) ($adjunto['subido_por_nombre'] ?? 'Usuario interno'), ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                            <span class="badge bg-light text-dark border"><i class="bi bi-paperclip"></i></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-muted small mb-0">Aún no se han cargado evidencias locales para este examen.</p>
            <?php endif; ?>
        </div>

        <div class="tab-pane fade" id="prefactura-tab-trazabilidad" role="tabpanel" aria-labelledby="prefactura-tab-trazabilidad-tab">
            <h6 class="mb-3">Historial operativo del examen</h6>
            <?php if (!empty($trazabilidad)): ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($trazabilidad as $evento): ?>
                        <?php
                        $tipo = strtolower((string) ($evento['tipo'] ?? 'evento'));
                        $iconMap = [
                            'estado' => 'bi-arrow-repeat',
                            'nota' => 'bi-chat-left-text',
                            'tarea' => 'bi-list-task',
                            'adjunto' => 'bi-paperclip',
                            'correo' => 'bi-envelope',
                        ];
                        $icon = $iconMap[$tipo] ?? 'bi-dot';
                        $fechaEvento = $evento['fecha'] ?? null;
                        $fechaLabel = 'Fecha no disponible';
                        if ($fechaEvento) {
                            try {
                                $fechaLabel = (new DateTime((string) $fechaEvento))->format('d-m-Y H:i');
                            } catch (Exception $e) {
                                $fechaLabel = (string) $fechaEvento;
                            }
                        }
                        ?>
                        <div class="list-group-item px-0">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div>
                                    <div class="fw-semibold"><i class="bi <?= htmlspecialchars($icon, ENT_QUOTES, 'UTF-8') ?> me-1"></i><?= htmlspecialchars((string) ($evento['titulo'] ?? 'Evento'), ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="small text-muted"><?= htmlspecialchars((string) ($evento['detalle'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php if (!empty($evento['autor'])): ?>
                                        <div class="small text-muted">Responsable: <?= htmlspecialchars((string) $evento['autor'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted text-nowrap"><?= htmlspecialchars($fechaLabel, ENT_QUOTES, 'UTF-8') ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-light border text-muted mb-0">
                    No hay eventos de trazabilidad registrados todavía.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<script>
    (function () {
        const card = document.getElementById('prefacturaChecklistCard');
        if (!card) {
            return;
        }

        const examenId = card.dataset.examenId || '';
        if (!examenId) {
            return;
        }

        const list = document.getElementById('prefacturaChecklistList');
        const empty = document.getElementById('prefacturaChecklistEmpty');
        const progressBar = document.getElementById('prefacturaChecklistProgressBar');
        const progressText = document.getElementById('prefacturaChecklistProgressText');
        const bootstrapBtn = document.getElementById('prefacturaChecklistBootstrapBtn');

        const buildUrl = (suffix) => `/examenes/${encodeURIComponent(String(examenId))}${suffix}`;

        const setProgress = (data = {}) => {
            const total = Number(data.total ?? 0);
            const completed = Number(data.completed ?? 0);
            const percent = Number(data.percent ?? (total > 0 ? (completed / total) * 100 : 0));

            if (progressBar) {
                progressBar.style.width = `${Math.max(0, Math.min(100, percent))}%`;
                progressBar.setAttribute('aria-valuenow', String(percent));
            }

            if (progressText) {
                progressText.textContent = `${completed}/${total} completadas · ${percent.toFixed(1)}%`;
            }
        };

        const renderChecklist = (checklist = [], progress = {}) => {
            if (!list || !empty) {
                return;
            }

            list.innerHTML = '';
            setProgress(progress);

            if (!Array.isArray(checklist) || checklist.length === 0) {
                empty.classList.remove('d-none');
                return;
            }

            empty.classList.add('d-none');
            checklist.forEach((item) => {
                const wrapper = document.createElement('label');
                wrapper.className = 'd-flex align-items-center gap-2 border rounded p-2';

                const input = document.createElement('input');
                input.type = 'checkbox';
                input.className = 'form-check-input m-0';
                input.checked = Boolean(item.completed || item.completado || item.checked || item.completado_at);
                input.disabled = item.can_toggle === false;

                const text = document.createElement('span');
                text.className = 'small flex-grow-1';
                text.textContent = item.label || item.slug || 'Etapa';

                const meta = document.createElement('span');
                meta.className = 'badge text-bg-light text-dark border';
                meta.textContent = input.checked ? 'Completada' : 'Pendiente';

                input.addEventListener('change', async () => {
                    input.disabled = true;
                    try {
                        const response = await fetch(buildUrl('/crm/checklist'), {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                etapa_slug: item.slug,
                                completado: input.checked,
                            }),
                        });
                        const data = await response.json().catch(() => ({}));
                        if (!response.ok || !data.success) {
                            throw new Error(data.error || 'No se pudo sincronizar el checklist');
                        }
                        renderChecklist(data.checklist || checklist, data.checklist_progress || progress);
                    } catch (error) {
                        input.checked = !input.checked;
                        if (progressText) {
                            progressText.textContent = error?.message || 'No se pudo sincronizar el checklist.';
                        }
                    } finally {
                        input.disabled = false;
                    }
                });

                wrapper.appendChild(input);
                wrapper.appendChild(text);
                wrapper.appendChild(meta);
                list.appendChild(wrapper);
            });
        };

        const loadChecklist = async () => {
            if (progressText) {
                progressText.textContent = 'Cargando checklist...';
            }
            try {
                const response = await fetch(buildUrl('/crm/checklist-state'), {
                    method: 'GET',
                    credentials: 'same-origin',
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    throw new Error(data.error || 'No se pudo cargar el checklist');
                }
                renderChecklist(data.checklist || [], data.checklist_progress || {});
            } catch (error) {
                if (empty) {
                    empty.classList.remove('d-none');
                    empty.textContent = error?.message || 'No se pudo cargar el checklist.';
                }
            }
        };

        if (bootstrapBtn) {
            bootstrapBtn.addEventListener('click', async () => {
                bootstrapBtn.disabled = true;
                try {
                    const response = await fetch(buildUrl('/crm/bootstrap'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({}),
                    });
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success) {
                        throw new Error(data.error || 'No se pudo sincronizar el checklist con CRM');
                    }
                    renderChecklist(data.checklist || [], data.checklist_progress || {});
                } catch (error) {
                    if (progressText) {
                        progressText.textContent = error?.message || 'No se pudo sincronizar el checklist con CRM.';
                    }
                } finally {
                    bootstrapBtn.disabled = false;
                }
            });
        }

        loadChecklist();
    })();
</script>
