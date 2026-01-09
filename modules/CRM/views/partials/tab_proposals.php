<div class="tab-pane fade" id="crm-tab-proposals" role="tabpanel" aria-labelledby="crm-tab-proposals-link">
    <div class="card mb-3">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <div>
                <h5 class="mb-0">Propuestas</h5>
            </div>
            <div class="ms-auto d-flex flex-wrap align-items-center gap-2">
                <button class="btn btn-primary btn-sm" id="proposal-new-btn">
                    <i class="fa-regular fa-plus me-1"></i> Nueva propuesta
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="proposal-pipeline-btn" title="Ver pipeline">
                    <i class="fa-solid fa-grip-vertical"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="proposal-export-btn" title="Exportar PDF">
                    <i class="fa-regular fa-file-pdf"></i>
                </button>
                <div class="input-group input-group-sm" style="max-width: 240px;">
                    <span class="input-group-text"><i class="fa fa-search"></i></span>
                    <input type="search" class="form-control" id="proposal-search" placeholder="Buscar #, asunto o destinatario">
                </div>
                <select class="form-select form-select-sm" id="proposal-status-filter" style="min-width: 140px;">
                    <option value="">Todos</option>
                    <?php foreach (($proposalStatuses ?? []) as $statusOption): ?>
                        <option value="<?= htmlspecialchars($statusOption, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(ucfirst($statusOption), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-outline-secondary btn-sm" id="proposal-refresh-btn" title="Recargar">
                    <i class="mdi mdi-refresh"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-xl-7">
                    <div class="table-responsive rounded card-table shadow-sm">
                        <table class="table table-sm align-middle mb-0" id="crm-proposals-table">
                            <thead class="bg-info text-white">
                                <tr>
                                    <th>#</th>
                                    <th>Asunto</th>
                                    <th>Para</th>
                                    <th class="text-end">Total</th>
                                    <th>Estado</th>
                                    <th class="text-end"></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="col-xl-5">
                    <div class="card mb-3">
                        <div class="card-header d-flex align-items-center justify-content-between">
                            <div>
                                <small class="text-muted text-uppercase d-block">Detalle</small>
                                <h5 class="mb-0" id="proposal-preview-title">Selecciona una propuesta</h5>
                            </div>
                            <span class="badge bg-secondary" id="proposal-preview-status">—</span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex flex-wrap gap-3 mb-3">
                                <div>
                                    <div class="text-muted text-uppercase small">Número</div>
                                    <div id="proposal-preview-number" class="fw-semibold">—</div>
                                </div>
                                <div>
                                    <div class="text-muted text-uppercase small">Para</div>
                                    <div id="proposal-preview-to" class="fw-semibold">—</div>
                                </div>
                                <div>
                                    <div class="text-muted text-uppercase small">Válida hasta</div>
                                    <div id="proposal-preview-valid" class="fw-semibold">—</div>
                                </div>
                                <div class="ms-auto">
                                    <div class="text-muted text-uppercase small">Total</div>
                                    <div id="proposal-preview-total" class="fw-bold fs-5">—</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <button class="btn btn-primary btn-sm" id="proposal-preview-open" disabled>
                                    <i class="mdi mdi-eye"></i> Ver detalle
                                </button>
                                <button class="btn btn-outline-secondary btn-sm" id="proposal-preview-refresh" disabled>
                                    <i class="mdi mdi-refresh"></i> Actualizar
                                </button>
                                <button class="btn btn-outline-primary btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#proposal-create-collapse" aria-expanded="false" aria-controls="proposal-create-collapse">
                                    <i class="mdi mdi-pencil-outline"></i> Crear/editar
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="collapse" id="proposal-create-collapse">
                        <div class="card mb-3">
                            <div class="card-header">
                                <h5 class="mb-0">Nueva propuesta rápida</h5>
                            </div>
                            <div class="card-body">
                                <form id="proposal-form" class="row g-3">
                                <div class="col-12">
                                    <label class="form-label">Lead</label>
                                    <select class="form-select form-select-sm" id="proposal-lead"></select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Título</label>
                                    <input type="text" class="form-control form-control-sm" id="proposal-title" placeholder="Ej: Procedimiento ambulatorio">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Válida hasta</label>
                                    <input type="date" class="form-control form-control-sm" id="proposal-valid-until">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Impuesto (%)</label>
                                    <input type="number" class="form-control form-control-sm" step="0.01" id="proposal-tax-rate" value="0">
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Notas internas</label>
                                    <textarea class="form-control form-control-sm" rows="2" id="proposal-notes"></textarea>
                                </div>
                            </form>
                            </div>
                        </div>

                        <div class="card mb-3">
                            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                                <strong>Detalle económico</strong>
                                <div class="ms-auto d-flex gap-2">
                                <button class="btn btn-outline-primary btn-sm" id="proposal-add-package-btn">
                                    <i class="mdi mdi-package-variant-closed"></i> Agregar paquete
                                </button>
                                <button class="btn btn-outline-primary btn-sm" id="proposal-add-code-btn">
                                    <i class="mdi mdi-magnify"></i> Buscar código
                                </button>
                                <button class="btn btn-outline-primary btn-sm" id="proposal-add-custom-btn">
                                    <i class="mdi mdi-plus"></i> Línea manual
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-sm align-middle" id="proposal-items-table">
                                    <thead>
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-center" style="width: 75px;">Cant.</th>
                                            <th class="text-center" style="width: 100px;">Precio</th>
                                            <th class="text-center" style="width: 80px;">Desc %</th>
                                            <th class="text-end" style="width: 110px;">Total</th>
                                            <th style="width: 40px;"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="proposal-items-body">
                                        <tr class="text-center text-muted" data-empty-row>
                                            <td colspan="6">Agrega un paquete o código para iniciar</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
                            <div class="totals">
                                <div>Subtotal: <strong id="proposal-subtotal">$0.00</strong></div>
                                <div>Impuesto: <strong id="proposal-tax">$0.00</strong></div>
                                <div>Total: <strong id="proposal-total">$0.00</strong></div>
                            </div>
                            <button class="btn btn-success" id="proposal-save-btn">
                                <i class="mdi mdi-send"></i> Crear propuesta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
