<div class="modal fade" id="welcomeTourModal" tabindex="-1" aria-hidden="true" aria-labelledby="welcomeTourModalLabel" data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h4 class="modal-title fw-bold" id="welcomeTourModalLabel">
                        <i class="mdi mdi-map-outline me-2 text-primary"></i>Bienvenido al nuevo menú de MedForge
                    </h4>
                    <p class="text-muted mb-0 small">El menú lateral fue reorganizado por área de trabajo. Aquí un vistazo rápido:</p>
                </div>
            </div>
            <div class="modal-body pt-3">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">📅</span>
                            <div>
                                <div class="fw-semibold small">Consulta</div>
                                <div class="text-muted" style="font-size:0.8rem">Agenda, agendamiento, pacientes, derivaciones y flujo de pacientes.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">🔬</span>
                            <div>
                                <div class="fw-semibold small">Quirúrgico</div>
                                <div class="text-muted" style="font-size:0.8rem">Solicitudes, protocolos, plantillas y dashboard quirúrgico.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">🖼️</span>
                            <div>
                                <div class="fw-semibold small">Imágenes</div>
                                <div class="text-muted" style="font-size:0.8rem">Exámenes solicitados, realizados y dashboard de imágenes.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">📈</span>
                            <div>
                                <div class="fw-semibold small">Comercial</div>
                                <div class="text-muted" style="font-size:0.8rem">CRM, leads, catálogo de códigos y constructor de paquetes.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">💬</span>
                            <div>
                                <div class="fw-semibold small">Comunicaciones</div>
                                <div class="text-muted" style="font-size:0.8rem">Chat, campañas, dashboard, bajas, flowmaker, plantillas y Mailbox.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">💊</span>
                            <div>
                                <div class="fw-semibold small">Inventario</div>
                                <div class="text-muted" style="font-size:0.8rem">Insumos, medicamentos, lentes y farmacia.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">💰</span>
                            <div>
                                <div class="fw-semibold small">Facturación</div>
                                <div class="text-muted" style="font-size:0.8rem">Afiliaciones (IESS, ISSFA, ISSPOL, MSP) y facturas.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">📊</span>
                            <div>
                                <div class="fw-semibold small">Reportes</div>
                                <div class="text-muted" style="font-size:0.8rem">Particulares, dashboard de billing y honorarios.</div>
                            </div>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="d-flex align-items-start gap-2 p-2 rounded bg-light">
                            <span class="fs-5">⚙️</span>
                            <div>
                                <div class="fw-semibold small">Administración</div>
                                <div class="text-muted" style="font-size:0.8rem">Usuarios, roles, ajustes y sugerencias/errores.</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-primary px-4" id="welcomeTourDismiss">
                    <i class="mdi mdi-check-circle-outline me-1"></i>Entendido, explorar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    var TOUR_KEY = 'medforge_menu_tour_v2';

    function showTour() {
        var el = document.getElementById('welcomeTourModal');
        if (!el || typeof bootstrap === 'undefined') return;
        var modal = bootstrap.Modal.getOrCreateInstance
            ? bootstrap.Modal.getOrCreateInstance(el)
            : new bootstrap.Modal(el);
        modal.show();
    }

    function dismissTour() {
        try { localStorage.setItem(TOUR_KEY, '1'); } catch (e) {}
        var el = document.getElementById('welcomeTourModal');
        if (!el || typeof bootstrap === 'undefined') return;
        var modal = bootstrap.Modal.getInstance(el);
        if (modal) modal.hide();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var seen = false;
        try { seen = !!localStorage.getItem(TOUR_KEY); } catch (e) {}
        if (!seen) showTour();

        var btn = document.getElementById('welcomeTourDismiss');
        if (btn) btn.addEventListener('click', dismissTour);
    });
})();
</script>
