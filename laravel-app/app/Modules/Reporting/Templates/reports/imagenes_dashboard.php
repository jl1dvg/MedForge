<?php
/**
 * @var string|null $titulo
 * @var string|null $generatedAt
 * @var array<int, array{label: string, value: string}> $filters
 * @var array<int, array<string, mixed>> $cards
 * @var array<string, mixed> $meta
 * @var array<int, array<string, mixed>> $rows
 * @var int|null $total
 */

$layout = __DIR__ . '/../layouts/base.php';

$title = $titulo ?? 'Dashboard de KPIs de imágenes';
$generatedAt = $generatedAt ?? '';
$filters = is_array($filters ?? null) ? $filters : [];
$cards = is_array($cards ?? null) ? $cards : [];
$meta = is_array($meta ?? null) ? $meta : [];
$rows = is_array($rows ?? null) ? $rows : [];
$total = $total ?? count($rows);

$formatDate = static function ($value): string {
    if (!$value) {
        return '—';
    }

    try {
        $date = new \DateTimeImmutable((string)$value);
        return $date->format('d-m-Y H:i');
    } catch (\Throwable) {
        return (string)$value;
    }
};

$tatPromedio = ($meta['tat_promedio_horas'] ?? null) !== null ? number_format((float)$meta['tat_promedio_horas'], 2) . ' h' : '—';
$tatMediana = ($meta['tat_mediana_horas'] ?? null) !== null ? number_format((float)$meta['tat_mediana_horas'], 2) . ' h' : '—';
$tatP90 = ($meta['tat_p90_horas'] ?? null) !== null ? number_format((float)$meta['tat_p90_horas'], 2) . ' h' : '—';

ob_start();
?>

<div class="report-header">
    <h2><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
    <div class="report-meta">
        Generado: <?= htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') ?>
        · Registros: <?= htmlspecialchars((string)$total, ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>

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

<h3 style="margin: 14px 0 6px;">Resumen de KPIs</h3>
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
    <tr>
        <td>TAT (promedio / mediana / P90)</td>
        <td><?= htmlspecialchars($tatPromedio, ENT_QUOTES, 'UTF-8') ?></td>
        <td><?= htmlspecialchars('Mediana: ' . $tatMediana . ' · P90: ' . $tatP90, ENT_QUOTES, 'UTF-8') ?></td>
    </tr>
    </tbody>
</table>

<h3 style="margin: 14px 0 6px;">Detalle de estudios</h3>
<table class="report-table">
    <thead>
    <tr>
        <th>#</th>
        <th>Fecha examen</th>
        <th>Form ID</th>
        <th>HC</th>
        <th>Paciente</th>
        <th>Afiliación</th>
        <th>Estado</th>
        <th>Cita</th>
        <th>Realizado</th>
        <th>Informado</th>
        <th>Facturado</th>
        <th>Examen</th>
    </tr>
    </thead>
    <tbody>
    <?php if (empty($rows)): ?>
        <tr>
            <td colspan="12" class="text-muted">Sin registros para exportar.</td>
        </tr>
    <?php else: ?>
        <?php foreach ($rows as $index => $row): ?>
            <tr>
                <td><?= htmlspecialchars((string)($index + 1), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($formatDate($row['fecha_examen'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($row['form_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($row['hc_number'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($row['paciente'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($row['afiliacion'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars((string)($row['estado_agenda'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= !empty($row['cita_generada']) ? 'SI' : 'NO' ?></td>
                <td><?= !empty($row['examen_realizado']) ? 'SI' : 'NO' ?></td>
                <td><?= !empty($row['informado']) ? 'SI' : 'NO' ?></td>
                <td><?= !empty($row['facturado']) ? 'SI' : 'NO' ?></td>
                <td><?= htmlspecialchars((string)($row['examen'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
    <?php endif; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
include $layout;
?>
