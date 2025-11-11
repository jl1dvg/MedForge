<?php
/** @var array $procedimientosPorCategoria */
/** @var string|null $mensajeExito */
/** @var string|null $mensajeError */
/** @var string $username */
/** @var array $scripts */
$canManage = $canManage ?? false;
$scripts = array_merge($scripts ?? [], [
    'js/pages/list.js',
]);
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Editores</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item active" aria-current="page">Editor de Protocolos</li>
                    </ol>
                </nav>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <?php if ($mensajeExito): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($mensajeExito, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($mensajeError): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars($mensajeError, ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header d-flex justify-content-between align-items-center">
                    <div>
                        <h4 class="box-title">📋 <strong>Listado de plantillas de Protocolos Quirúrgicos</strong></h4>
                        <?php if ($canManage): ?>
                            <h6 class="subtitle">
                                Haz clic sobre cualquier celda para modificar su contenido y guarda los cambios con los
                                botones de acciones.
                            </h6>
                        <?php else: ?>
                            <h6 class="subtitle">
                                Consulta las plantillas disponibles. Solicita acceso de edición a un administrador si lo necesitas.
                            </h6>
                        <?php endif; ?>
                    </div>
                    <?php if ($canManage): ?>
                        <div>
                            <a href="/protocolos/crear" class="btn btn-primary">
                                <i class="mdi mdi-plus-circle-outline me-5"></i> Nuevo Protocolo
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="box-body">
                    <?php if (!empty($procedimientosPorCategoria)): ?>
                        <div class="accordion" id="accordionProtocolos">
                            <?php foreach ($procedimientosPorCategoria as $categoria => $procedimientos): ?>
                                <div class="accordion-item">
                                    <h2 class="accordion-header d-flex justify-content-between align-items-center px-3"
                                        id="heading-<?= md5($categoria) ?>">
                                        <div class="d-flex flex-grow-1 align-items-center">
                                            <button class="accordion-button collapsed flex-grow-1 text-start"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#collapse-<?= md5($categoria) ?>"
                                                    aria-expanded="false"
                                                    aria-controls="collapse-<?= md5($categoria) ?>">
                                                <?= htmlspecialchars($categoria, ENT_QUOTES, 'UTF-8') ?>
                                                (<?= count($procedimientos) ?>)
                                            </button>
                                        </div>
                                        <?php if ($canManage): ?>
                                            <div class="ms-3">
                                                <a href="/protocolos/crear?categoria=<?= urlencode($categoria) ?>"
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="mdi mdi-plus-circle-outline me-5"></i> Nuevo protocolo en
                                                    esta categoría
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </h2>
                                    <div id="collapse-<?= md5($categoria) ?>" class="accordion-collapse collapse"
                                         aria-labelledby="heading-<?= md5($categoria) ?>"
                                         data-bs-parent="#accordionProtocolos">
                                        <div class="accordion-body">
                                            <?php foreach ($procedimientos as $procedimiento): ?>
                                                <div class="d-flex align-items-center mb-30 border-bottom pb-15">
                                                    <div class="me-15">
                                                        <?php $imagen = $procedimiento['imagen_link'] ?? ''; ?>
                                                        <img src="<?= htmlspecialchars($imagen ?: '/public/images/placeholder.png', ENT_QUOTES, 'UTF-8') ?>"
                                                             class="avatar avatar-lg rounded10 bg-primary-light"
                                                             alt="<?= htmlspecialchars($procedimiento['membrete'] ?? 'Imagen protocolo', ENT_QUOTES, 'UTF-8') ?>"/>
                                                    </div>
                                                    <div class="d-flex flex-column flex-grow-1 fw-500">
                                                        <?php if ($canManage): ?>
                                                            <a href="/protocolos/editar?id=<?= urlencode($procedimiento['id']) ?>"
                                                               class="text-dark hover-primary mb-1 fs-16"
                                                               data-bs-toggle="tooltip"
                                                               title="<?= htmlspecialchars($procedimiento['membrete'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars($procedimiento['membrete'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-dark fw-600 mb-1 fs-16" data-bs-toggle="tooltip"
                                                                  title="<?= htmlspecialchars($procedimiento['membrete'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars($procedimiento['membrete'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <span class="text-fade" data-bs-toggle="tooltip"
                                                              title="<?= htmlspecialchars($procedimiento['cirugia'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars($procedimiento['cirugia'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                    </div>
                                                    <?php if ($canManage): ?>
                                                        <div class="dropdown">
                                                            <a class="px-10 pt-5" href="#" data-bs-toggle="dropdown"><i
                                                                        class="ti-more-alt"></i></a>
                                                            <div class="dropdown-menu dropdown-menu-end">
                                                                <a class="dropdown-item"
                                                                   href="/protocolos/editar?id=<?= urlencode($procedimiento['id']) ?>">Editar</a>
                                                                <a class="dropdown-item"
                                                                   href="/protocolos/editar?duplicar=<?= urlencode($procedimiento['id']) ?>">Duplicar</a>
                                                                <form method="POST" action="/protocolos/eliminar"
                                                                      style="display:inline;"
                                                                      onsubmit="return confirm('¿Estás seguro de que deseas eliminar este protocolo?');">
                                                                    <input type="hidden" name="id"
                                                                           value="<?= htmlspecialchars($procedimiento['id'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                                    <button type="submit"
                                                                            class="dropdown-item text-danger">Eliminar
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-warning">No hay protocolos disponibles.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
</section>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
