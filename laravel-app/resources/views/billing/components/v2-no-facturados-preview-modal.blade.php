@php
    $billingV2WritesEnabled = filter_var(env('BILLING_V2_WRITES_ENABLED', '1'), FILTER_VALIDATE_BOOLEAN);
    $billingWritePrefix = $billingV2WritesEnabled ? '/v2' : '';
    $billingNoFacturadosCrearAction = $billingWritePrefix . '/billing/no-facturados/crear';
@endphp

<div class="modal fade bs-example-modal-lg" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Confirmar Facturacion</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body preview-modal-body">
                <div class="box box-inverse box-secondary" id="previewMeta">
                    <div class="box-header with-border">
                        <h4 class="box-title">
                            <strong id="previewPaciente">-</strong>
                            <small class="sidetitle" id="previewHc">-</small>
                        </h4>
                    </div>
                    <div class="box-body" id="previewProcedimiento"></div>
                </div>
                <div id="previewContent">
                    <p class="text-muted">Cargando datos...</p>
                </div>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                </div>
                <div>
                    <form id="facturarForm" method="POST" action="{{ $billingNoFacturadosCrearAction }}" class="mb-0">
                        <input type="hidden" name="form_id" id="facturarFormId">
                        <input type="hidden" name="hc_number" id="facturarHcNumber">
                        <button type="submit" class="btn btn-success">Facturar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
