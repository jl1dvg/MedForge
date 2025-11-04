<?php
/** @var array $requiredPermissions */
?>
<section class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h1 class="page-title text-danger">Acceso denegado</h1>
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home"></i></a></li>
                <li class="breadcrumb-item active">Permisos insuficientes</li>
            </ol>
        </div>
    </div>
</section>

<section class="content">
    <div class="row">
        <div class="col-lg-8">
            <div class="box">
                <div class="box-body">
                    <p class="lead mb-4">No cuentas con permisos suficientes para acceder a esta sección.</p>
                    <?php if (!empty($requiredPermissions)): ?>
                        <p class="mb-0">Se requiere alguno de los siguientes permisos:</p>
                        <ul class="mt-2">
                            <?php foreach ($requiredPermissions as $permission): ?>
                                <li><code><?= htmlspecialchars($permission, ENT_QUOTES, 'UTF-8'); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <a href="/dashboard" class="btn btn-primary mt-4">
                        <i class="mdi mdi-arrow-left-bold-circle"></i> Volver al panel
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
