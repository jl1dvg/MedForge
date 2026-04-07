<?php
/** @var array $procedimientosPorCategoria */
/** @var string|null $mensajeExito */
/** @var string|null $mensajeError */
/** @var string $csrfToken */
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
                    <?= htmlspecialchars((string)($mensajeExito ?? ''), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <?php if ($mensajeError): ?>
                <div class="alert alert-danger">
                    <?= htmlspecialchars((string)($mensajeError ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
                                                <?= htmlspecialchars((string)($categoria ?? ''), ENT_QUOTES, 'UTF-8') ?>
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
                                                <?php $procedimientoId = trim((string)($procedimiento['id'] ?? '')); ?>
                                                <div class="d-flex align-items-center mb-30 border-bottom pb-15">
                                                    <div class="me-15">
                                                        <?php
                                                        $imagen = trim((string)($procedimiento['imagen_link'] ?? ''));
                                                        $placeholder = '/images/placeholder.png';
                                                        $imagenSrc = $imagen !== '' ? $imagen : $placeholder;
                                                        ?>
                                                        <img src="<?= htmlspecialchars((string)($imagenSrc ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                             class="avatar avatar-lg rounded10 bg-primary-light"
                                                             alt="<?= htmlspecialchars((string)($procedimiento['membrete'] ?? 'Imagen protocolo'), ENT_QUOTES, 'UTF-8') ?>"
                                                             onerror="this.onerror=null;this.src='<?= htmlspecialchars((string)($placeholder ?? ''), ENT_QUOTES, 'UTF-8') ?>';" />
                                                    </div>
                                                    <div class="d-flex flex-column flex-grow-1 fw-500">
                                                        <?php if ($canManage && $procedimientoId !== ''): ?>
                                                            <a href="/protocolos/editar?id=<?= urlencode($procedimientoId) ?>"
                                                               class="text-dark hover-primary mb-1 fs-16"
                                                               data-bs-toggle="tooltip"
                                                               title="<?= htmlspecialchars((string)($procedimiento['membrete'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars((string)($procedimiento['membrete'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-dark fw-600 mb-1 fs-16" data-bs-toggle="tooltip"
                                                                  title="<?= htmlspecialchars((string)($procedimiento['membrete'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                <?= htmlspecialchars((string)($procedimiento['membrete'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                            </span>
                                                        <?php endif; ?>
                                                        <?php if ($canManage && $procedimientoId === ''): ?>
                                                            <span class="badge bg-warning text-dark d-inline-block mt-5">Sin ID</span>
                                                        <?php endif; ?>
                                                        <span class="text-fade" data-bs-toggle="tooltip"
                                                              title="<?= htmlspecialchars((string)($procedimiento['cirugia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                    <?= htmlspecialchars((string)($procedimiento['cirugia'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                                </span>
                                                    </div>
                                                    <?php if ($canManage): ?>
                                                        <div class="protocolo-actions-dropdown" data-protocolo-dropdown>
                                                            <button type="button"
                                                                    class="btn btn-sm btn-light protocolo-actions-toggle"
                                                                    aria-expanded="false"
                                                                    aria-label="Acciones del protocolo">
                                                                <i class="ti-more-alt"></i>
                                                            </button>
                                                            <div class="protocolo-actions-menu" role="menu">
                                                                <?php if ($procedimientoId !== ''): ?>
                                                                    <a class="dropdown-item"
                                                                       href="/protocolos/editar?id=<?= urlencode($procedimientoId) ?>">Editar</a>
                                                                    <a class="dropdown-item"
                                                                       href="/protocolos/editar?duplicar=<?= urlencode($procedimientoId) ?>">Duplicar</a>
                                                                    <div class="dropdown-divider"></div>
                                                                    <form method="POST"
                                                                          action="/protocolos/eliminar"
                                                                          onsubmit="return confirm('¿Estás seguro de que deseas eliminar este protocolo?');">
                                                                        <input type="hidden" name="csrf_token"
                                                                               value="<?= htmlspecialchars((string)($csrfToken ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                        <input type="hidden" name="id"
                                                                               value="<?= htmlspecialchars((string)($procedimientoId ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                                        <button type="submit" class="dropdown-item text-danger">Eliminar</button>
                                                                    </form>
                                                                <?php else: ?>
                                                                    <span class="dropdown-item text-muted">ID no disponible</span>
                                                                <?php endif; ?>
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



<style>
    .protocolo-actions-dropdown {
        position: relative;
        display: inline-block;
    }

    .protocolo-actions-toggle {
        min-width: 38px;
    }

    .protocolo-actions-menu {
        position: absolute;
        top: calc(100% + 6px);
        right: 0;
        min-width: 180px;
        background: #fff;
        border: 1px solid rgba(0, 0, 0, 0.12);
        border-radius: 0.5rem;
        box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        padding: 0.35rem 0;
        z-index: 2000;
        display: none;
    }

    .protocolo-actions-dropdown.is-open .protocolo-actions-menu {
        display: block;
    }

    .protocolo-actions-menu .dropdown-item {
        display: block;
        width: 100%;
        padding: 0.5rem 1rem;
        background: transparent;
        border: 0;
        text-align: left;
        text-decoration: none;
        color: inherit;
        cursor: pointer;
    }

    .protocolo-actions-menu .dropdown-item:hover {
        background: rgba(0, 0, 0, 0.05);
    }

    .protocolo-actions-menu form {
        margin: 0;
    }

    .protocolo-actions-menu .dropdown-divider {
        height: 1px;
        margin: 0.35rem 0;
        background: rgba(0, 0, 0, 0.1);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        if (window.bootstrap && bootstrap.Tooltip) {
            tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }

        var dropdowns = [].slice.call(document.querySelectorAll('[data-protocolo-dropdown]'));

        function closeAllDropdowns(exceptNode) {
            dropdowns.forEach(function (dropdown) {
                if (dropdown !== exceptNode) {
                    dropdown.classList.remove('is-open');
                    var button = dropdown.querySelector('.protocolo-actions-toggle');
                    if (button) {
                        button.setAttribute('aria-expanded', 'false');
                    }
                }
            });
        }

        dropdowns.forEach(function (dropdown) {
            var button = dropdown.querySelector('.protocolo-actions-toggle');
            if (!button) {
                return;
            }

            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                var willOpen = !dropdown.classList.contains('is-open');
                closeAllDropdowns(dropdown);
                dropdown.classList.toggle('is-open', willOpen);
                button.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
            });

            var menu = dropdown.querySelector('.protocolo-actions-menu');
            if (menu) {
                menu.addEventListener('click', function (event) {
                    event.stopPropagation();
                });
            }
        });

        document.addEventListener('click', function () {
            closeAllDropdowns(null);
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllDropdowns(null);
            }
        });
    });
</script>
