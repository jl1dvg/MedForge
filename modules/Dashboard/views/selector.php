<?php
/** @var array $dashboards */
/** @var array $dashboard_context */
$dashboards = $dashboards ?? [];
$dashboardContext = $dashboard_context ?? [];
$defaultKey = $dashboardContext['default_key'] ?? null;
?>

<section class="content">
    <div class="row">
        <div class="col-12">
            <div class="box">
                <div class="box-body">
                    <h4 class="box-title">Selecciona un dashboard</h4>
                    <p class="text-muted mb-0">
                        Puedes ingresar a cualquiera de los tableros disponibles. El dashboard por defecto
                        está marcado para referencia.
                    </p>
                </div>
            </div>
        </div>
        <?php foreach ($dashboards as $key => $meta): ?>
            <?php
            $label = $meta['label'] ?? ucfirst($key);
            $path = $meta['path'] ?? '/dashboard';
            $isDefault = $defaultKey === $key;
            ?>
            <div class="col-12 col-md-6 col-xl-3">
                <a href="<?= htmlspecialchars($path, ENT_QUOTES, 'UTF-8') ?>" class="box h-100">
                    <div class="box-body text-center">
                        <div class="fw-700 fs-16"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></div>
                        <?php if ($isDefault): ?>
                            <span class="badge bg-primary-light text-primary mt-10">Por defecto</span>
                        <?php else: ?>
                            <span class="badge bg-light text-muted mt-10">Disponible</span>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</section>
