<!-- Modal global para preview -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Confirmar Facturación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="previewContent">
                    <p class="text-muted">Cargando datos...</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
                <div>
                    <form id="facturarForm" method="POST"
                          action="/billing/no-facturados/crear"
                          class="mb-0">
                        <input type="hidden" name="form_id" id="facturarFormId">
                        <input type="hidden" name="hc_number" id="facturarHcNumber">
                        <button type="submit" class="btn btn-success">Facturar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
