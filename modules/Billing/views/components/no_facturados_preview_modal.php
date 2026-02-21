<?php
$billingV2WritesEnabled = filter_var(
    $_ENV['BILLING_V2_WRITES_ENABLED'] ?? getenv('BILLING_V2_WRITES_ENABLED') ?? '0',
    FILTER_VALIDATE_BOOLEAN
);
$billingWritePrefix = $billingV2WritesEnabled ? '/v2' : '';
$billingNoFacturadosCrearAction = $billingWritePrefix . '/billing/no-facturados/crear';
?>
<!-- Modal global para preview -->
<div class="modal fade bs-example-modal-lg" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel"
     aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="previewModalLabel">Confirmar Facturación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body preview-modal-body">
                <div class="box box-inverse box-secondary"
                     id="previewMeta">
                    <div class="box-header with-border">
                        <h4 class="box-title">
                            <strong id="previewPaciente">-</strong>
                            <small class="sidetitle" id="previewHc">—</small>
                        </h4>
                    </div>
                    <div class="box-body" id="previewProcedimiento">
                    </div>
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
                    <form id="facturarForm" method="POST"
                          action="<?= htmlspecialchars($billingNoFacturadosCrearAction, ENT_QUOTES, 'UTF-8') ?>"
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

<style>
    .preview-modal-body {
        max-height: 70vh;
        overflow-y: auto;
        position: relative;
        padding-bottom: 96px;
    }

    .preview-totals-bar {
        position: sticky;
        bottom: -7rem;
        background: #fff4c5;
        z-index: 2;
    }

    .preview-rules small {
        color: #6c757d;
    }

    .preview-tabs .nav-link {
        border-radius: 999px;
    }

    .preview-accordion .accordion-button:not(.collapsed) {
        background: #eef2ff;
        color: #1f2a44;
    }
</style>
