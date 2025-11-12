<?php
/**
 * @var array $types
 * @var array $cats
 * @var array $f
 * @var int $total
 */
$totalFormatted = number_format((int) $total, 0, '', '.');
?>
<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Catálogo de códigos</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item">
                            <a href="/dashboard"><i class="mdi mdi-home-outline"></i></a>
                        </li>
                        <li class="breadcrumb-item active" aria-current="page">Códigos</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div>
            <a href="/codes/create" class="btn btn-primary btn-sm">
                <i class="mdi mdi-plus"></i> Nuevo código
            </a>
        </div>
    </div>
</div>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex flex-wrap align-items-center gap-3">
                    <div>
                        <h4 class="mb-0">Gestión de códigos</h4>
                        <p class="text-muted mb-0">Total actual: <?= htmlspecialchars($totalFormatted, ENT_QUOTES, 'UTF-8') ?> registros</p>
                    </div>
                    <div class="ms-auto">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="codes-refresh-btn">
                            <i class="mdi mdi-refresh"></i> Recargar
                        </button>
                    </div>
                </div>
                <div class="box-body">
                    <form class="card card-body mb-3" method="get" action="/codes" id="codes-filter-form">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label mb-0">Buscar</label>
                                <input type="text" name="q" class="form-control form-control-sm"
                                       value="<?= htmlspecialchars($f['q'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                       placeholder="Código o descripción">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0">Tipo</label>
                                <select name="code_type" class="form-select form-select-sm">
                                    <option value="">— Todos —</option>
                                    <?php foreach ($types as $type): ?>
                                        <?php
                                        $value = $type['key_name'] ?? '';
                                        $selected = ($f['code_type'] ?? '') === $value ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($type['label'] ?? $value, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label mb-0">Categoría (superbill)</label>
                                <select name="superbill" class="form-select form-select-sm">
                                    <option value="">— Todas —</option>
                                    <?php foreach ($cats as $cat): ?>
                                        <?php
                                        $value = $cat['slug'] ?? '';
                                        $selected = ($f['superbill'] ?? '') === $value ? 'selected' : '';
                                        ?>
                                        <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>>
                                            <?= htmlspecialchars($cat['title'] ?? $value, ENT_QUOTES, 'UTF-8') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="d-flex gap-3 flex-wrap">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_active" name="active"
                                               value="1" <?= !empty($f['active']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="f_active">Solo activos</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_reportable" name="reportable"
                                               value="1" <?= !empty($f['reportable']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="f_reportable">Reportables</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="f_finrep" name="financial_reporting"
                                               value="1" <?= !empty($f['financial_reporting']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="f_finrep">Financieros</label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 d-flex gap-2">
                                <button class="btn btn-primary btn-sm" type="submit">
                                    <i class="mdi mdi-magnify"></i> Aplicar filtros
                                </button>
                                <a href="/codes" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table id="codesTable" class="table table-striped table-sm align-middle w-100">
                            <thead class="bg-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Modifier</th>
                                    <th>Activo</th>
                                    <th>Categoría</th>
                                    <th>Dx rep.</th>
                                    <th>Fin. rep.</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th>Descripción corta</th>
                                    <th>Relacionados</th>
                                    <th class="text-end">Precio N1</th>
                                    <th class="text-end">Precio N2</th>
                                    <th class="text-end">Precio N3</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
