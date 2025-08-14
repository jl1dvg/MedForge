<?php
/**
 * @var array|null $code
 * @var array $types , $cats, $rels, $priceLevels, $prices
 * @var string $_csrf
 */
$isEdit = !empty($code);
$action = $isEdit ? "/public/index.php/codes/{$code['id']}" : "/public/index.php/codes";
$title = $isEdit ? "Editar código" : "Nuevo código";
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?></title>
    <!-- Usa tus estilos del proyecto -->
    <link rel="stylesheet" href="/public/css/vendors_css.css">
    <link rel="stylesheet" href="/public/css/horizontal-menu.css">
    <link rel="stylesheet" href="/public/css/style.css">
    <link rel="stylesheet" href="/public/css/skin_color.css">
</head>
<body class="bg-light">
<div class="container py-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><?= htmlspecialchars($title) ?></h3>
        <div>
            <a href="/views/codes" class="btn btn-secondary btn-sm">← Volver</a>
            <?php if ($isEdit): ?>
                <form class="d-inline" method="post" action="/public/index.php/codes/<?= (int)$code['id'] ?>/delete"
                      onsubmit="return confirm('¿Eliminar este código?');">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
                    <button class="btn btn-outline-danger btn-sm">Eliminar</button>
                </form>
            <?php endif; ?>
            <?php if ($isEdit): ?>
                <form class="d-inline" method="post" action="/public/index.php/codes/<?= (int)$code['id'] ?>/toggle">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
                    <button class="btn btn-outline-warning btn-sm" type="submit">
                        <?= !empty($code['active']) ? 'Desactivar' : 'Activar' ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <form method="post" action="<?= htmlspecialchars($action) ?>" class="card card-body">
        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
        <div class="row g-3">
            <div class="col-md-2">
                <label class="form-label">Código</label>
                <input name="codigo" class="form-control form-control-sm" required
                       value="<?= htmlspecialchars($code['codigo'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Modifier</label>
                <input name="modifier" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['modifier'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">Tipo</label>
                <select name="code_type" class="form-select form-select-sm">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($types as $t):
                        $val = $t['key_name'] ?? '';
                        $sel = ($code['code_type'] ?? '') === $val ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>><?= htmlspecialchars($t['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label">Categoría</label>
                <select name="superbill" class="form-select form-select-sm">
                    <option value="">— Seleccionar —</option>
                    <?php foreach ($cats as $c):
                        $val = $c['slug'] ?? '';
                        $sel = ($code['superbill'] ?? '') === $val ? 'selected' : '';
                        ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $sel ?>><?= htmlspecialchars($c['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Revenue Code (opcional)</label>
                <input name="revenue_code" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['revenue_code'] ?? '') ?>">
            </div>

            <div class="col-12">
                <label class="form-label">Descripción</label>
                <input name="descripcion" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['descripcion'] ?? '') ?>">
            </div>
            <div class="col-12">
                <label class="form-label">Descripción corta</label>
                <input name="short_description" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['short_description'] ?? '') ?>">
            </div>

            <div class="col-md-2">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="f_active" name="active"
                           value="1" <?= !empty($code['active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="f_active">Activo</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="f_reportable" name="reportable"
                           value="1" <?= !empty($code['reportable']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="f_reportable">Dx Reporting</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="f_finrep" name="financial_reporting"
                           value="1" <?= !empty($code['financial_reporting']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="f_finrep">Service Reporting</label>
                </div>
            </div>

            <!-- Precios por nivel (Columnas existentes) -->
            <div class="col-md-2">
                <label class="form-label">Precio Nivel 1</label>
                <input name="precio_nivel1" type="number" step="0.0001" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['valor_facturar_nivel1'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Precio Nivel 2</label>
                <input name="precio_nivel2" type="number" step="0.0001" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['valor_facturar_nivel2'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Precio Nivel 3</label>
                <input name="precio_nivel3" type="number" step="0.0001" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['valor_facturar_nivel3'] ?? '') ?>">
            </div>

            <!-- (Opción B) Precios dinámicos -->
            <?php if (!empty($priceLevels)): ?>
                <div class="col-12">
                    <div class="alert alert-info py-2 mb-2">
                        Además de los precios por columnas, puedes registrar precios dinámicos por nivel.
                    </div>
                </div>
                <?php foreach ($priceLevels as $pl):
                    $key = $pl['level_key'];
                    $val = $prices[$key] ?? '';
                    ?>
                    <div class="col-md-2">
                        <label class="form-label">Precio <?= htmlspecialchars($pl['title']) ?></label>
                        <input name="prices[<?= htmlspecialchars($key) ?>]" type="number" step="0.0001"
                               class="form-control form-control-sm" value="<?= htmlspecialchars($val) ?>">
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Anestesia (si aplicas) -->
            <div class="col-md-2">
                <label class="form-label">Anestesia N1</label>
                <input name="anestesia_nivel1" type="number" step="0.0001" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['anestesia_nivel1'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Anestesia N2</label>
                <input name="anestesia_nivel2" type="number" step="0.0001" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['anestesia_nivel2'] ?? '') ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Anestesia N3</label>
                <input name="anestesia_nivel3" type="number" step="0.0001" class="form-control form-control-sm"
                       value="<?= htmlspecialchars($code['anestesia_nivel3'] ?? '') ?>">
            </div>

            <div class="col-12">
                <button class="btn btn-primary btn-sm"><?= $isEdit ? 'Guardar cambios' : 'Crear' ?></button>
            </div>
        </div>
    </form>

    <?php if ($isEdit): ?>
        <div class="card mt-3">
            <div class="card-header py-2"><strong>Relacionar códigos</strong></div>
            <div class="card-body">
                <form class="row g-2 align-items-end" method="post"
                      action="/public/index.php/codes/<?= (int)$code['id'] ?>/relate">
                    <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
                    <div class="col-md-3">
                        <label class="form-label mb-0">ID relacionado</label>
                        <input name="related_id" type="number" class="form-control form-control-sm"
                               placeholder="ID de tarifario_2014" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label mb-0">Tipo relación</label>
                        <select name="relation_type" class="form-select form-select-sm">
                            <option value="maps_to">maps_to</option>
                            <option value="relates_to">relates_to</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button class="btn btn-outline-primary btn-sm">Agregar</button>
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
                        <?php else: foreach ($rels as $r): ?>
                            <tr>
                                <td><?= (int)$r['related_code_id'] ?></td>
                                <td><?= htmlspecialchars($r['codigo'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['descripcion'] ?? '') ?></td>
                                <td><?= htmlspecialchars($r['relation_type'] ?? '') ?></td>
                                <td class="text-end">
                                    <form class="d-inline" method="post"
                                          action="/public/index.php/codes/<?= (int)$code['id'] ?>/relate/del"
                                          onsubmit="return confirm('¿Quitar relación?');">
                                        <input type="hidden" name="_csrf" value="<?= htmlspecialchars($_csrf) ?>">
                                        <input type="hidden" name="related_id"
                                               value="<?= (int)$r['related_code_id'] ?>">
                                        <button class="btn btn-outline-danger btn-sm">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>