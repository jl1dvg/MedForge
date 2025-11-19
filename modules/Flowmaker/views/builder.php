<?php
/** @var array<string, mixed> $flowmakerPayload */
$payloadJson = json_encode($flowmakerPayload, JSON_UNESCAPED_UNICODE);
?>

<section class="content" style="min-height: calc(100vh - 140px);">
    <div class="row">
        <div class="col-12">
            <div class="box box-primary mb-0" style="min-height: calc(100vh - 180px);">
                <div class="box-header with-border d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h3 class="box-title mb-0">Constructor de flujo</h3>
                        <small class="text-muted d-block">Edita la automatización en tiempo real sobre un lienzo completo.</small>
                    </div>
                    <a href="/flowmaker/flows" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-arrow-left"></i> Volver a la lista
                    </a>
                </div>
                <div class="box-body p-0">
                    <div id="flow"
                         data='<?= htmlspecialchars($payloadJson, ENT_QUOTES, 'UTF-8') ?>'
                         style="width: 100%; min-height: calc(100vh - 260px);"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
    window.flowmakerLocale = 'es';
    window.flowmakerTranslations = {
        save: 'Guardar',
        cancel: 'Cancelar',
        delete: 'Eliminar',
        duplicate: 'Duplicar',
        search: 'Buscar',
    };
    window.data = <?= $payloadJson ?>;
</script>
