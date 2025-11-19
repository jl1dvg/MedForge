<div class="card mb-3">
    <div class="card-header bg-light d-flex justify-content-between align-items-center">
        <span class="fw-bold">Resumen</span>
        <small class="text-muted">Datos calculados desde el endpoint paginado</small>
    </div>
    <div class="card-body py-3">
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

<div class="card mb-3">
    <div class="card-header bg-primary text-white">Filtros</div>
    <div class="card-body">
        <form id="filtrosNoFacturados" class="row g-3">
            <div class="col-md-3">
                <label for="fFechaDesde" class="form-label">Fecha desde</label>
                <input type="date" id="fFechaDesde" name="fecha_desde" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label for="fFechaHasta" class="form-label">Fecha hasta</label>
                <input type="date" id="fFechaHasta" name="fecha_hasta" class="form-control form-control-sm">
            </div>
            <div class="col-md-3">
                <label for="fAfiliacion" class="form-label">Afiliación</label>
                <input type="text" id="fAfiliacion" name="afiliacion" class="form-control form-control-sm" placeholder="Ej: IESS">
            </div>
            <div class="col-md-3">
                <label for="fEstadoRevision" class="form-label">Estado revisión</label>
                <select id="fEstadoRevision" name="estado_revision" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="1">Revisado</option>
                    <option value="0">Pendiente</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="fTipo" class="form-label">Tipo</label>
                <select id="fTipo" name="tipo" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    <option value="quirurgico">Quirúrgico</option>
                    <option value="no_quirurgico">No quirúrgico</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="fBusqueda" class="form-label">Paciente / HC</label>
                <input type="text" id="fBusqueda" name="busqueda" class="form-control form-control-sm" placeholder="Nombre o HC">
            </div>
            <div class="col-md-3">
                <label for="fProcedimiento" class="form-label">Procedimiento / Código</label>
                <input type="text" id="fProcedimiento" name="procedimiento" class="form-control form-control-sm" placeholder="Texto o código">
            </div>
            <div class="col-md-3">
                <label class="form-label">Rango valor</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text">$</span>
                    <input type="number" step="0.01" id="fValorMin" name="valor_min" class="form-control" placeholder="Mín">
                    <span class="input-group-text">-</span>
                    <input type="number" step="0.01" id="fValorMax" name="valor_max" class="form-control" placeholder="Máx">
                </div>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-sm btn-primary me-2"><i class="mdi mdi-magnify"></i> Aplicar</button>
                <button type="reset" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">Limpiar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header bg-secondary text-white">Procedimientos no facturados</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover table-sm mb-0" id="noFacturadosTable" style="width:100%">
                <thead class="table-light">
                    <tr>
                        <th>Form ID</th>
                        <th>HC</th>
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
    </div>
</div>
