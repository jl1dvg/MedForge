<!-- Legacy billing report view relocated under modules/Billing/views/informes. -->
<div class="row mt-4">
    <div class="col-12 text-end">
        <a href="/public/index.php/billing/excel?form_id=<?= $formId ?>&grupo=IESS"
           class="btn btn-success btn-lg me-2">
            <i class="fa fa-file-excel-o"></i> Descargar Excel
        </a>
        <a href="/informes/iess?modo=consolidado<?= $filtros['mes'] ? '&mes=' . urlencode($filtros['mes']) : '' ?>"
           class="btn btn-outline-secondary btn-lg">
            <i class="fa fa-arrow-left"></i> Regresar al consolidado
        </a>
    </div>
</div>