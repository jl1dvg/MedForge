<?php
/**
 * @var array|null $code
 * @var array $types
 * @var array $cats
 * @var array $rels
 * @var array $priceLevels
 * @var array $prices
 * @var string $_csrf
 */
$isEdit = !empty($code);
$action = $isEdit ? '/codes/' . (int) $code['id'] : '/codes';
$title = $isEdit ? 'Editar código' : 'Nuevo código';
?>
<div class="content-header">
    <div class="d-flex flex-wrap align-items-center gap-2">
        <div class="me-auto">
            <h3 class="page-title"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/codes">Códigos</a></li>
                        <li class="breadcrumb-item active" aria-current="page"><?= $isEdit ? 'Editar' : 'Crear' ?></li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="/codes" class="btn btn-secondary btn-sm">← Volver</a>
            <?php if ($isEdit): ?>
                <form class="d-inline" method="post" action="/codes/<?= (int) $code['id'] ?>/delete"
                      onsubmit="return confirm('¿Eliminar este código?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-danger btn-sm" type="submit">
                        <i class="mdi mdi-delete-outline"></i> Eliminar
                    </button>
                </form>
                <form class="d-inline" method="post" action="/codes/<?= (int) $code['id'] ?>/toggle">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <button class="btn btn-outline-warning btn-sm" type="submit">
                        <?= !empty($code['active']) ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <form method="post" action="<?= htmlspecialchars($action, ENT_QUOTES, 'UTF-8') ?>" class="card card-body">
                <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
                <div class="row g-3">
                    <div class="col-md-2">
                        <label class="form-label">Código</label>
                        <input name="codigo" class="form-control form-control-sm" required
                               value="<?= htmlspecialchars($code['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Modifier</label>
                        <input name="modifier" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['modifier'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tipo</label>
                        <select name="code_type" class="form-select form-select-sm">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($types as $type): ?>
                                <?php
                                $val = $type['key_name'] ?? '';
                                $selected = ($code['code_type'] ?? '') === $val ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($type['label'] ?? $val, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Categoría</label>
                        <select name="superbill" class="form-select form-select-sm">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($cats as $cat): ?>
                                <?php
                                $val = $cat['slug'] ?? '';
                                $selected = ($code['superbill'] ?? '') === $val ? 'selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($val, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                    <?= htmlspecialchars($cat['title'] ?? $val, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Revenue Code</label>
                        <input name="revenue_code" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['revenue_code'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <input name="descripcion" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción corta</label>
                        <input name="short_description" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['short_description'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="code-active" name="active" value="1"
                                   <?= !empty($code['active']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="code-active">Activo</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="code-reportable" name="reportable" value="1"
                                   <?= !empty($code['reportable']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="code-reportable">Reportable</label>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="form-check mt-4">
                            <input class="form-check-input" type="checkbox" id="code-finrep" name="financial_reporting" value="1"
                                   <?= !empty($code['financial_reporting']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="code-finrep">Financiero</label>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Precio Nivel 1</label>
                        <input name="precio_nivel1" type="number" step="0.0001" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['valor_facturar_nivel1'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio Nivel 2</label>
                        <input name="precio_nivel2" type="number" step="0.0001" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['valor_facturar_nivel2'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Precio Nivel 3</label>
                        <input name="precio_nivel3" type="number" step="0.0001" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['valor_facturar_nivel3'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <?php if (!empty($priceLevels)): ?>
                        <div class="col-12">
                            <div class="alert alert-info py-2 mb-2">
                                Además de los precios estándar puedes registrar valores dinámicos por nivel.
                            </div>
                        </div>
                        <?php foreach ($priceLevels as $level): ?>
                            <?php
                            $key = $level['level_key'];
                            $existing = $prices[$key] ?? '';
                            ?>
                            <div class="col-md-2">
                                <label class="form-label">Precio <?= htmlspecialchars($level['title'], ENT_QUOTES, 'UTF-8') ?></label>
                                <input name="prices[<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>]" type="number" step="0.0001"
                                       class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($existing, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <div class="col-md-2">
                        <label class="form-label">Anestesia N1</label>
                        <input name="anestesia_nivel1" type="number" step="0.0001" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['anestesia_nivel1'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Anestesia N2</label>
                        <input name="anestesia_nivel2" type="number" step="0.0001" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['anestesia_nivel2'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Anestesia N3</label>
                        <input name="anestesia_nivel3" type="number" step="0.0001" class="form-control form-control-sm"
                               value="<?= htmlspecialchars($code['anestesia_nivel3'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div class="col-12">
                        <button class="btn btn-primary btn-sm" type="submit">
                            <?= $isEdit ? 'Guardar cambios' : 'Crear código' ?>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <?php if ($isEdit): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <strong>Relacionar códigos</strong>
                    </div>
                    <div class="card-body">
                        <form class="row g-2 align-items-end" method="post" action="/codes/<?= (int) $code['id'] ?>/relate">
                            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
                            <div class="col-md-3">
                                <label class="form-label mb-0">ID relacionado</label>
                                <input name="related_id" type="number" class="form-control form-control-sm" required
                                       placeholder="ID de tarifario_2014">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0">Tipo relación</label>
                                <select name="relation_type" class="form-select form-select-sm">
                                    <option value="maps_to">maps_to</option>
                                    <option value="relates_to">relates_to</option>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <button class="btn btn-outline-primary btn-sm" type="submit">Agregar</button>
                            </div>
                        </form>

                        <div class="table-responsive mt-3">
                            <table class="table table-sm table-bordered align-middle">
                                <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Código</th>
                                    <th>Descripción</th>
                                    <th>Relación</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php if (empty($rels)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">Sin relaciones</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($rels as $rel): ?>
                                        <tr>
                                            <td><?= (int) $rel['related_code_id'] ?></td>
                                            <td><?= htmlspecialchars($rel['codigo'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($rel['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($rel['relation_type'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td class="text-end">
                                                <form class="d-inline" method="post"
                                                      action="/codes/<?= (int) $code['id'] ?>/relate/del"
                                                      onsubmit="return confirm('¿Quitar relación?');">
                                                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf, ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="related_id" value="<?= (int) $rel['related_code_id'] ?>">
                                                    <button class="btn btn-outline-danger btn-sm" type="submit">Quitar</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</section>
