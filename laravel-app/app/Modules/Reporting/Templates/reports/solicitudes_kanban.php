<?php
/**
 * @var string|null $titulo
 * @var string|null $generatedAt
 * @var array<int, array<string, string>> $filters
 * @var int|null $total
 * @var array<int, array<string, mixed>> $rows
 * @var string|null $metricLabel
 */

$layout = __DIR__ . '/../layouts/base.php';

$title = $titulo ?? 'Reporte de solicitudes';
$generatedAt = $generatedAt ?? '';
$filters = $filters ?? [];
$rows = $rows ?? [];
$total = $total ?? count($rows);
$metricLabel = $metricLabel ?? null;

$formatDate = static function ($value): string {
    if (!$value) {
        return '—';
    }

    try {
        $date = new DateTimeImmutable((string) $value);
        return $date->format('d-m-Y H:i');
    } catch (Exception $e) {
        return (string) $value;
    }
};

ob_start();
?>

<div class="report-header">
    <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="report-meta">
        Generado: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?>
        · Total: <?= htmlspecialchars((string) $total, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php if (!empty($metricLabel)): ?>
        <div class="report-badge"><?= htmlspecialchars($metricLabel, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
</div>

<?php if (!empty($filters)): ?>
    <ul class="filters-list">
        <?php foreach ($filters as $filter): ?>
            <li>
                <strong><?= htmlspecialchars($filter['label'], ENT_QUOTES, 'UTF-8') ?>:</strong>
                <?= htmlspecialchars($filter['value'], ENT_QUOTES, 'UTF-8') ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php else: ?>
    <div class="report-meta text-muted">Sin filtros específicos.</div>
<?php endif; ?>

<table class="report-table">
    <thead>
    <tr>
        <th>#</th>
        <th>Solicitud ID</th>
        <th>Paciente</th>
        <th>HC</th>
        <th>Afiliación</th>
        <th>Doctor</th>
        <th>Procedimiento</th>
        <th>Estado/Columna</th>
        <th>Prioridad</th>
        <th>Fecha creación</th>
        <th>Turno</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr>
            <td colspan="11" class="text-muted">Sin registros para los filtros seleccionados.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($rows as $index => $row): ?>
            <?php
            $estadoLabel = $row['kanban_estado_label'] ?? $row['estado'] ?? '—';
            ?>
            <tr>
                <td><?= htmlspecialchars((string) ($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['id'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['full_name'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['hc_number'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['afiliacion'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['doctor'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['procedimiento'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) $estadoLabel, ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['prioridad'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($formatDate($row['created_at'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string) ($row['turno'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
include $layout;
?>
