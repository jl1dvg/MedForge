<?php
/**
 * @var string|null $titulo
 * @var string|null $generatedAt
 * @var array<int, array{label: string, value: string}> $filters
 * @var array<int, array<string, mixed>> $cards
 * @var string|null $periodoLabel
 * @var int|null $total
 */

$layout = __DIR__ . '/../layouts/base.php';

$title = $titulo ?? 'Dashboard de KPIs quirúrgicos';
$generatedAt = $generatedAt ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$cards = is_array($cards ?? null) ? $cards : [];
$periodoLabel = $periodoLabel ?? '';
$total = $total ?? count($cards);

ob_start();
?>

<div class="report-header">
    <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="report-meta">
        Generado: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?>
        · KPIs: <?= htmlspecialchars((string)$total, ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>

<?php if ($periodoLabel !== ''): ?>
    <div class="report-meta">Periodo: <?= htmlspecialchars($periodoLabel, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if (!empty($filters)): ?>
    <ul class="filters-list">
        <?php foreach ($filters as $filter): ?>
            <li>
                <strong><?= htmlspecialchars((string)($filter['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <?= htmlspecialchars((string)($filter['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <div class="report-meta text-muted">Sin filtros específicos.</div>
<?php endif; ?>

<h3 style="margin: 14px 0 6px;">Resumen de KPIs quirúrgicos</h3>
<table class="report-table">
    <thead>
    <tr>
        <th style="width: 34%;">Indicador</th>
        <th style="width: 16%;">Valor</th>
        <th>Detalle</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($cards)): ?>
        <tr>
            <td colspan="3" class="text-muted">Sin KPIs para el rango seleccionado.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($cards as $card): ?>
            <tr>
                <td><?= htmlspecialchars((string)($card['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($card['value'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($card['hint'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
include $layout;
?>
