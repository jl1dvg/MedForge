<?php
/** @var array<int, array<string, mixed>> $flows */
/** @var array<string, string>|null $status */
$flashClass = $status['type'] ?? null;
$flashMessage = $status['message'] ?? null;
?>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-header with-border d-flex justify-content-between align-items-center">
                    <h3 class="box-title mb-0">Flowmaker</h3>
                    <a href="/flowmaker/flows" class="btn btn-sm btn-outline-secondary">
                        <i class="fa fa-rotate"></i> Actualizar
                    </a>
                </div>
                <div class="box-body">
                    <?php if ($flashClass && $flashMessage): ?>
                        <div class="alert alert-<?= htmlspecialchars($flashClass, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($flashMessage, ENT_QUOTES, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>

                    <form class="mb-4" action="/flowmaker/flows" method="POST">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Nombre del flujo</label>
                                <input type="text" name="name" class="form-control" placeholder="Ej. Autorespondedor principal" required>
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Descripción (opcional)</label>
                                <input type="text" name="description" class="form-control" placeholder="Notas internas">
                            </div>
                            <div class="col-md-3">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fa fa-plus"></i> Crear flujo
                                </button>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                            <tr>
                                <th>ID</th>
                                <th>Nombre</th>
                                <th>Descripción</th>
                                <th>Actualizado</th>
                                <th class="text-end">Acciones</th>
                            </tr>
                            </thead>
                            <tbody>
                            <?php if (empty($flows)): ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No hay flujos registrados.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($flows as $flow): ?>
                                <tr>
                                    <td>#<?= (int) $flow['id'] ?></td>
                                    <td>
                                        <form action="/flowmaker/flows/<?= (int) $flow['id'] ?>/update" method="POST" class="d-flex gap-2">
                                            <input type="text"
                                                   name="name"
                                                   value="<?= htmlspecialchars($flow['name'], ENT_QUOTES, 'UTF-8') ?>"
                                                   class="form-control form-control-sm"
                                                   required>
                                            <input type="text"
                                                   name="description"
                                                   value="<?= htmlspecialchars((string) ($flow['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                                   class="form-control form-control-sm"
                                                   placeholder="Descripción">
                                            <button class="btn btn-sm btn-outline-primary" type="submit">
                                                <i class="fa fa-save"></i>
                                            </button>
                                        </form>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($flow['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($flow['updated_at'] ?? $flow['created_at']), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-end">
                                        <a href="/flowmaker/builder/<?= (int) $flow['id'] ?>" class="btn btn-sm btn-success">
                                            <i class="fa fa-diagram-project"></i> Abrir constructor
                                        </a>
                                        <a href="/flowmaker/flows/<?= (int) $flow['id'] ?>/delete"
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('¿Eliminar este flujo?');">
                                            <i class="fa fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
