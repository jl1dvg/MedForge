    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header bg-light d-flex justify-content-between align-items-center">
                    <span class="fw-bold">Resumen</span>
                    <small class="text-muted">Datos calculados desde el endpoint paginado</small>
                </div>
                <div class="box-body">
                    <div class="row text-center" id="resumenTotales">
                        <div class="col-md-4 mb-2">
                            <div class="p-2 border rounded h-100">
                                <div class="text-muted">Total pendientes</div>
                                <div class="fw-bold fs-5" data-resumen="total-cantidad">0</div>
                                <div class="text-success" data-resumen="total-monto">$0.00</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="p-2 border rounded h-100">
                                <div class="text-muted">Quirúrgicos</div>
                                <div class="fw-bold fs-5" data-resumen="quirurgicos-cantidad">0</div>
                                <div class="text-success" data-resumen="quirurgicos-monto">$0.00</div>
                            </div>
                        </div>
                        <div class="col-md-4 mb-2">
                            <div class="p-2 border rounded h-100">
                                <div class="text-muted">No quirúrgicos</div>
                                <div class="fw-bold fs-5" data-resumen="no-quirurgicos-cantidad">0</div>
                                <div class="text-success" data-resumen="no-quirurgicos-monto">$0.00</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header bg-primary text-white d-flex flex-wrap align-items-center justify-content-between gap-3">
                    <div>
                        <div class="fw-bold">Filtros rápidos</div>
                        <small class="text-white-50">Acota el listado y guarda combinaciones como vistas
                            reutilizables.</small>
                    </div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <div class="input-group input-group-sm"
                             title="Selecciona una vista guardada para aplicar sus filtros">
                            <label class="input-group-text" for="vistaGuardada">Vistas</label>
                            <select id="vistaGuardada" class="form-select form-select-sm" aria-label="Vistas guardadas">
                                <option value="">Vistas guardadas</option>
                            </select>
                        </div>
                        <button type="button" class="btn btn-sm btn-light" id="btnGuardarVista"
                                title="Guardar los filtros actuales como una vista rápida">
                            <i class="mdi mdi-content-save-outline"></i> Guardar vista
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-light" id="btnBorrarVista" disabled
                                title="Eliminar la vista seleccionada">
                            <i class="mdi mdi-delete-outline"></i>
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <form id="filtrosNoFacturados" class="row g-3 align-items-end">
                        <div class="col-md-2">
                            <label for="fFechaDesde" class="form-label">Fecha desde</label>
                            <input type="date" id="fFechaDesde" name="fecha_desde" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label for="fFechaHasta" class="form-label">Fecha hasta</label>
                            <input type="date" id="fFechaHasta" name="fecha_hasta" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2">
                            <label for="fAfiliacion" class="form-label">Afiliación</label>
                            <select id="fAfiliacion" name="afiliacion" class="form-select form-select-sm" multiple
                                    aria-label="Selecciona una o varias afiliaciones">
                                <option value="" disabled>Selecciona afiliaciones</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="fEstadoRevision" class="form-label">Estado revisión</label>
                            <select id="fEstadoRevision" name="estado_revision" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="1">Revisado</option>
                                <option value="0">Pendiente</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="fEstadoAgenda" class="form-label">Estado agenda</label>
                            <select id="fEstadoAgenda" name="estado_agenda" class="form-select form-select-sm" multiple
                                    aria-label="Selecciona estados de agenda">
                                <option value="ATENDIDO" selected>ATENDIDO</option>
                                <option value="AGENDADO">AGENDADO</option>
                                <option value="LLEGADO">LLEGADO</option>
                                <option value="CONFIRMADO">CONFIRMADO</option>
                                <option value="CONSULTA">CONSULTA</option>
                                <option value="DILATAR">DILATAR</option>
                                <option value="PAGADO">PAGADO</option>
                                <option value="CONSULTA_TERMINADO">CONSULTA_TERMINADO</option>
                                <option value="NULL">Sin estado (NULL)</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="fTipo" class="form-label">Tipo</label>
                            <select id="fTipo" name="tipo" class="form-select form-select-sm">
                                <option value="">Todos</option>
                                <option value="quirurgico">Quirúrgico</option>
                                <option value="no_quirurgico">No quirúrgico</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="fBusqueda" class="form-label">Paciente / HC</label>
                            <input type="text" id="fBusqueda" name="busqueda" class="form-control form-control-sm"
                                   placeholder="Nombre o HC">
                        </div>
                        <div class="col-md-3">
                            <label for="fProcedimiento" class="form-label">Procedimiento / Código</label>
                            <input type="text" id="fProcedimiento" name="procedimiento"
                                   class="form-control form-control-sm"
                                   placeholder="Texto o código">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Rango valor</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" id="fValorMin" name="valor_min" class="form-control"
                                       placeholder="Mín">
                                <span class="input-group-text">-</span>
                                <input type="number" step="0.01" id="fValorMax" name="valor_max" class="form-control"
                                       placeholder="Máx">
                            </div>
                        </div>
                        <div class="col-md-4 ms-auto text-end">
                            <button type="submit" class="btn btn-sm btn-primary me-2"><i class="mdi mdi-magnify"></i>
                                Aplicar
                            </button>
                            <button type="reset" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">Limpiar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
                    <ul class="nav nav-tabs card-header-tabs" id="noFacturadosTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="tabRevisados" data-bs-toggle="tab"
                                    data-bs-target="#paneRevisados"
                                    type="button" role="tab" aria-controls="paneRevisados" aria-selected="true">
                                Revisados
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tabPendientes" data-bs-toggle="tab"
                                    data-bs-target="#panePendientes"
                                    type="button" role="tab" aria-controls="panePendientes" aria-selected="false">No
                                revisados
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tabNoQuirurgicos" data-bs-toggle="tab"
                                    data-bs-target="#paneNoQuirurgicos"
                                    type="button" role="tab" aria-controls="paneNoQuirurgicos" aria-selected="false">No
                                quirúrgicos
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tabImagenes" data-bs-toggle="tab"
                                    data-bs-target="#paneImagenes" type="button" role="tab" aria-controls="paneImagenes"
                                    aria-selected="false">Imágenes
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="tabConsultas" data-bs-toggle="tab"
                                    data-bs-target="#paneConsultas" type="button" role="tab" aria-controls="paneConsultas"
                                    aria-selected="false">Consultas
                            </button>
                        </li>
                    </ul>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <span class="text-muted small" id="seleccionadosInfo">0 seleccionados</span>
                        <button type="button" class="btn btn-sm btn-outline-success" id="btnMarcarRevisado" disabled>
                            <i class="mdi mdi-check-circle-outline"></i> Marcar revisado
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" id="btnFacturarLote" disabled>
                            <i class="mdi mdi-cash-multiple"></i> Facturar en lote
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="paneRevisados" role="tabpanel"
                             aria-labelledby="tabRevisados">
                            <div class="table-responsive">
                                <table class="table table-lg invoice-archive" id="tablaRevisados">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllRevisados"
                                                   aria-label="Seleccionar todos los revisados">
                                        </th>
                                        <th>Form ID</th>
                                        <!--<th>HC</th>-->
                                        <th>Paciente</th>
                                        <th>Afiliación</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado revisión</th>
                                        <th>Procedimiento</th>
                                        <th>Valor</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="alert alert-danger d-none rounded-0" role="alert"
                                 data-table-error="tablaRevisados">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-alert-outline fs-4 mb-0"></i>
                                    <div>
                                        <div class="fw-bold">No pudimos cargar los revisados.</div>
                                        <div class="small text-muted" data-error-message>Revisa tu conexión o vuelve a
                                            intentar.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="panePendientes" role="tabpanel" aria-labelledby="tabPendientes">
                            <div class="table-responsive">
                                <table class="table table-lg invoice-archive" id="tablaPendientes">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllPendientes"
                                                   aria-label="Seleccionar todos los no revisados">
                                        </th>
                                        <th>Form ID</th>
                                        <!--<th>HC</th>-->
                                        <th>Paciente</th>
                                        <th>Afiliación</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado revisión</th>
                                        <th>Procedimiento</th>
                                        <th>Valor</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="alert alert-danger d-none rounded-0" role="alert"
                                 data-table-error="tablaPendientes">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-alert-outline fs-4 mb-0"></i>
                                    <div>
                                        <div class="fw-bold">No pudimos cargar los no revisados.</div>
                                        <div class="small text-muted" data-error-message>Revisa tu conexión o vuelve a
                                            intentar.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="paneNoQuirurgicos" role="tabpanel"
                             aria-labelledby="tabNoQuirurgicos">
                            <div class="table-responsive">
                                <table class="table table-lg invoice-archive" id="tablaNoQuirurgicos">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllNoQuirurgicos"
                                                   aria-label="Seleccionar todos los no quirúrgicos">
                                        </th>
                                        <th>Form ID</th>
                                        <!--<th>HC</th>-->
                                        <th>Paciente</th>
                                        <th>Afiliación</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado revisión</th>
                                        <th>Procedimiento</th>
                                        <th>Valor</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="alert alert-danger d-none rounded-0" role="alert"
                                 data-table-error="tablaNoQuirurgicos">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-alert-outline fs-4 mb-0"></i>
                                    <div>
                                        <div class="fw-bold">No pudimos cargar los no quirúrgicos.</div>
                                        <div class="small text-muted" data-error-message>Revisa tu conexión o vuelve a
                                            intentar.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="paneImagenes" role="tabpanel" aria-labelledby="tabImagenes">
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="toggleImagenesAgrupar">
                                    <label class="form-check-label" for="toggleImagenesAgrupar">
                                        Agrupar por paciente
                                    </label>
                                </div>
                                <small class="text-muted">Selecciona todas las imágenes de un paciente en la página
                                    actual.</small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-lg invoice-archive" id="tablaImagenes">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllImagenes"
                                                   aria-label="Seleccionar todas las imágenes">
                                        </th>
                                        <th>Form ID</th>
                                        <!--<th>HC</th>-->
                                        <th>Paciente</th>
                                        <th>Afiliación</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado revisión</th>
                                        <th>Procedimiento</th>
                                        <th>Valor</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="alert alert-danger d-none rounded-0" role="alert"
                                 data-table-error="tablaImagenes">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-alert-outline fs-4 mb-0"></i>
                                    <div>
                                        <div class="fw-bold">No pudimos cargar las imágenes.</div>
                                        <div class="small text-muted" data-error-message>Revisa tu conexión o vuelve a
                                            intentar.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="paneConsultas" role="tabpanel" aria-labelledby="tabConsultas">
                            <div class="table-responsive">
                                <table class="table table-lg invoice-archive" id="tablaConsultas">
                                    <thead class="table-light">
                                    <tr>
                                        <th class="text-center">
                                            <input type="checkbox" class="form-check-input" id="selectAllConsultas"
                                                   aria-label="Seleccionar todas las consultas">
                                        </th>
                                        <th>Form ID</th>
                                        <!--<th>HC</th>-->
                                        <th>Paciente</th>
                                        <th>Afiliación</th>
                                        <th>Fecha</th>
                                        <th>Tipo</th>
                                        <th>Estado revisión</th>
                                        <th>Procedimiento</th>
                                        <th>Valor</th>
                                        <th>Acciones</th>
                                    </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <div class="alert alert-danger d-none rounded-0" role="alert"
                                 data-table-error="tablaConsultas">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="mdi mdi-alert-outline fs-4 mb-0"></i>
                                    <div>
                                        <div class="fw-bold">No pudimos cargar las consultas.</div>
                                        <div class="small text-muted" data-error-message>Revisa tu conexión o vuelve a
                                            intentar.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
