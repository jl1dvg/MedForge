<?php
/**
 * @var array<int, array<string, mixed>> $initialPackages
 */
$packagesJson = htmlspecialchars(json_encode($initialPackages, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
?>
<div class="content-header">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h3 class="page-title mb-0">Constructor de paquetes</h3>
            <p class="text-muted mb-0">Agrupa códigos frecuentes para reutilizarlos en propuestas y cotizaciones.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/codes" class="btn btn-outline-secondary btn-sm">
                ← Volver a códigos
            </a>
            <button class="btn btn-primary btn-sm" id="package-new-btn">
                <i class="mdi mdi-plus"></i> Nuevo paquete
            </button>
        </div>
    </div>
</div>

<section class="content" id="code-packages-root" data-initial-packages="<?= $packagesJson ?>">
    <div class="row">
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header d-flex align-items-center">
                    <div class="flex-grow-1">
                        <strong>Paquetes disponibles</strong>
                        <p class="text-muted mb-0">Selecciona un paquete para editarlo o duplicarlo.</p>
                    </div>
                    <button class="btn btn-sm btn-outline-secondary" id="package-refresh-btn">
                        <i class="mdi mdi-refresh"></i>
                    </button>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                        <input type="text" class="form-control" id="package-search-input" placeholder="Buscar paquete">
                    </div>
                    <div class="flex-grow-1 overflow-auto" id="package-list">
                        <!-- JS render -->
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-8 mt-3 mt-lg-0">
            <div class="card mb-3">
                <div class="card-header">
                    <strong>Detalles del paquete</strong>
                </div>
                <div class="card-body">
                    <form id="package-form" autocomplete="off">
                        <input type="hidden" name="id" id="package-id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nombre</label>
                                <input type="text" class="form-control form-control-sm" name="name" id="package-name" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Categoría</label>
                                <input type="text" class="form-control form-control-sm" name="category" id="package-category">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Estado</label>
                                <select class="form-select form-select-sm" name="active" id="package-active">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descripción</label>
                                <textarea class="form-control form-control-sm" rows="2" name="description" id="package-description"></textarea>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header d-flex flex-wrap align-items-center gap-2">
                    <strong>Ítems del paquete</strong>
                    <div class="ms-auto d-flex gap-2 flex-wrap">
                        <button class="btn btn-sm btn-outline-primary" id="package-add-custom">
                            <i class="mdi mdi-plus-circle-outline"></i> Línea manual
                        </button>
                        <button class="btn btn-sm btn-outline-primary" id="package-add-code">
                            <i class="mdi mdi-clipboard-plus-outline"></i> Buscar código
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm align-middle" id="package-items-table">
                            <thead>
                                <tr>
                                    <th style="width: 30px;"></th>
                                    <th>Descripción</th>
                                    <th class="text-center" style="width: 90px;">Cant.</th>
                                    <th class="text-center" style="width: 120px;">Precio</th>
                                    <th class="text-center" style="width: 90px;">Desc. %</th>
                                    <th class="text-end" style="width: 120px;">Subtotal</th>
                                    <th style="width: 40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="package-items-body">
                            <tr class="text-center text-muted" data-empty-row>
                                <td colspan="7">Agrega ítems para comenzar</td>
                            </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="5" class="text-end">Total</th>
                                    <th class="text-end" id="package-total">$0.00</th>
                                    <th></th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body d-flex flex-wrap gap-2 justify-content-between">
                    <div>
                        <button class="btn btn-primary" id="package-save-btn">
                            <i class="mdi mdi-content-save"></i> Guardar paquete
                        </button>
                        <button class="btn btn-outline-secondary" id="package-reset-btn">
                            Limpiar
                        </button>
                    </div>
                    <button class="btn btn-outline-danger" id="package-delete-btn">
                        <i class="mdi mdi-delete-outline"></i> Eliminar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="code-search-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Buscar códigos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text"><i class="mdi mdi-magnify"></i></span>
                        <input type="search" class="form-control" id="code-search-input" placeholder="Código o descripción">
                        <button class="btn btn-primary" id="code-search-btn">Buscar</button>
                    </div>
                    <div class="table-responsive" style="max-height: 400px;">
                        <table class="table table-hover table-sm">
                            <thead>
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Precio ref.</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="code-search-results">
                            <tr class="text-center text-muted" data-empty-row>
                                <td colspan="4">Realiza una búsqueda para ver resultados</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</section>
