<?php
/** @var array $config */
/** @var array $flow */
/** @var string $brand */
/** @var string $flowmakerUrl */
/** @var string $flowmakerOrigin */
/** @var array|null $status */

$escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
$statusType = $status['type'] ?? '';
$statusMessage = is_string($status['message'] ?? null) ? trim($status['message']) : '';
$statusClass = 'alert-info';
if ($statusType === 'success') {
    $statusClass = 'alert-success';
} elseif ($statusType === 'danger' || $statusType === 'error') {
    $statusClass = 'alert-danger';
} elseif ($statusType === 'warning') {
    $statusClass = 'alert-warning';
}

$bootstrap = [
    'brand' => $brand,
    'flow' => $flow,
    'api' => [
        'publish' => '/whatsapp/api/flowmaker/publish',
    ],
    'embed' => [
        'url' => $flowmakerUrl,
        'origin' => $flowmakerOrigin,
    ],
];
$bootstrapJson = htmlspecialchars(json_encode($bootstrap, JSON_UNESCAPED_UNICODE) ?: '{}', ENT_QUOTES, 'UTF-8');
?>

<div class="content-header">
    <div class="d-flex align-items-center">
        <div class="me-auto">
            <h3 class="page-title">Flowmaker de WhatsApp</h3>
            <div class="d-inline-block align-items-center">
                <nav>
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/dashboard"><i class="mdi mdi-home-outline"></i></a></li>
                        <li class="breadcrumb-item">WhatsApp</li>
                        <li class="breadcrumb-item active" aria-current="page">Flowmaker</li>
                    </ol>
                </nav>
            </div>
        </div>
        <div class="text-end">
            <div class="fw-600 text-muted small">Canal activo</div>
            <div class="fw-600"><?= $escape($brand); ?></div>
            <div class="mt-2 d-flex gap-2 justify-content-end">
                <a href="/whatsapp/autoresponder" class="btn btn-sm btn-outline-secondary">
                    <i class="mdi mdi-tune-vertical me-1"></i>Editor clásico
                </a>
                <a href="/settings?section=whatsapp" class="btn btn-sm btn-primary">
                    <i class="mdi mdi-cog-outline me-1"></i>Ajustes
                </a>
            </div>
        </div>
    </div>
</div>

<section class="content">
    <div class="row g-4">
        <div class="col-12 col-xl-4">
            <div class="box h-100">
                <div class="box-header with-border">
                    <h4 class="box-title mb-1">Tu diseño en Flowmaker</h4>
                    <p class="text-muted mb-0">Sincroniza el flujo creado en WhatsBox con el autorespondedor oficial.</p>
                </div>
                <div class="box-body">
                    <ol class="text-muted small ps-3">
                        <li class="mb-2">Construye o ajusta tu recorrido directamente en Flowmaker.</li>
                        <li class="mb-2">Haz clic en <strong>Publicar</strong> dentro de Flowmaker y confirma la acción.</li>
                        <li>MedForge validará el flujo y lo activará para todos los webhooks.</li>
                    </ol>
                    <div class="alert alert-info d-flex gap-2 align-items-center">
                        <i class="mdi mdi-shield-check-outline fs-3 text-info"></i>
                        <div>
                            <div class="fw-600">Los datos se guardan en tu infraestructura.</div>
                            <div>Flowmaker solo diseña el flujo, la publicación se realiza desde MedForge.</div>
                        </div>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <a href="<?= $escape($flowmakerUrl); ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                            <i class="mdi mdi-open-in-new me-1"></i>Abrir Flowmaker en una nueva pestaña
                        </a>
                        <a href="/docs/whatsapp-flowmaker" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                            <i class="mdi mdi-book-open-outline me-1"></i>Ver guía de integración
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="box h-100">
                <div class="box-header with-border d-flex align-items-center justify-content-between flex-wrap gap-2">
                    <div>
                        <h4 class="box-title mb-1">Publicación asistida</h4>
                        <p class="text-muted mb-0">Esta vista escucha los eventos de Flowmaker y publica el flujo por ti.</p>
                    </div>
                    <span class="badge bg-primary-light text-primary fw-600">Beta</span>
                </div>
                <div class="box-body">
                    <?php if ($statusMessage !== ''): ?>
                        <div class="alert <?= $statusClass; ?>" role="alert">
                            <?= $escape($statusMessage); ?>
                        </div>
                    <?php endif; ?>

                    <div class="flowmaker-bridge" data-flowmaker-root>
                        <div class="alert d-none" data-flowmaker-status></div>
                        <div class="flowmaker-iframe-wrapper">
                            <iframe
                                src="<?= $escape($flowmakerUrl); ?>"
                                title="Flowmaker de WhatsApp"
                                allow="clipboard-write; clipboard-read"
                                loading="lazy"
                                data-flowmaker-iframe
                            ></iframe>
                        </div>
                    </div>
                    <script type="application/json" data-flowmaker-config><?= $bootstrapJson; ?></script>
                    <p class="text-muted small mt-3 mb-0">
                        ¿Problemas para sincronizar? Revisa la consola del navegador para ver los detalles del evento enviado por Flowmaker.
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
