<?php
$leadViewData = [
    'leadStatuses' => $leadStatuses ?? [],
    'leadSources' => $leadSources ?? [],
    'assignableUsers' => $assignableUsers ?? [],
    'permissions' => $permissions ?? [],
];
?>
<?php include __DIR__ . '/_toolbar.php'; ?>
<?php include __DIR__ . '/_summary.php'; ?>
<?php include __DIR__ . '/_filters.php'; ?>
<?php include __DIR__ . '/_table.php'; ?>
<?php include __DIR__ . '/_kanban.php'; ?>
<?php include __DIR__ . '/_modals.php'; ?>
