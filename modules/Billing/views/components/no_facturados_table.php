<?php
/**
 * Renderiza las tablas de procedimientos quirúrgicos y no quirúrgicos no facturados.
 */
?>

<div class="card mb-4">
    <div class="card-header bg-success">
        🟢 Procedimientos Quirúrgicos Revisados no facturados
    </div>
    <div class="card-body p-2">
        <div class="table-responsive" style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
            <table id="example" class="table table-striped table-hover table-sm invoice-archive sticky-header">
                <thead class="bg-success">
                <tr>
                    <th>Form ID</th>
                    <th>HC</th>
                    <th>Paciente</th>
                    <th>Afiliación</th>
                    <th>Fecha</th>
                    <th>Procedimiento</th>
                    <th>¿Facturar?</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($quirurgicos as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['form_id']) ?></td>
                        <td><?= htmlspecialchars($r['hc_number']) ?></td>
                        <td><?= htmlspecialchars(trim(($r['fname'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? '') . ' ' . ($r['lname2'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($r['afiliacion']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha']))) ?></td>
                        <td><?= htmlspecialchars(($r['membrete'] ?? '') . ' ' . ($r['lateralidad'] ?? '')) ?></td>
                        <td>
                            <?php
                            $status = isset($r['status']) ? (int)$r['status'] : 0;
                            $badge = $status === 1
                                ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>"
                                : "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>";

                            $formId = htmlspecialchars($r['form_id']);
                            $hcNumber = htmlspecialchars($r['hc_number']);
                            echo "<button
                                    class='btn btn-app btn-info btn-preview'
                                    data-form-id='$formId'
                                    data-hc-number='$hcNumber'
                                    data-bs-toggle='modal'
                                    data-bs-target='#previewModal'>
                                    $badge
                                    <i class='mdi mdi-file-document'></i> Protocolo
                                </button>";
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-warning">
        🟢 Procedimientos Quirúrgicos No Revisados No facturados
    </div>
    <div class="card-body p-2">
        <div class="table-responsive" style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
            <table id="exampleY" class="table table-striped table-hover table-sm invoice-archive sticky-header">
                <thead class="bg-warning">
                <tr>
                    <th>Form ID</th>
                    <th>HC</th>
                    <th>Paciente</th>
                    <th>Afiliación</th>
                    <th>Fecha</th>
                    <th>Procedimiento</th>
                    <th>¿Facturar?</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($quirurgicosNoRevisados as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['form_id']) ?></td>
                        <td><?= htmlspecialchars($r['hc_number']) ?></td>
                        <td><?= htmlspecialchars(trim(($r['fname'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? '') . ' ' . ($r['lname2'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($r['afiliacion']) ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha']))) ?></td>
                        <td><?= htmlspecialchars(($r['membrete'] ?? '') . ' ' . ($r['lateralidad'] ?? '')) ?></td>
                        <td>
                            <?php
                            $status = isset($r['status']) ? (int)$r['status'] : 0;
                            $badge = $status === 1
                                ? "<span class='badge bg-success'><i class='fa fa-check'></i></span>"
                                : "<span class='badge bg-warning'><i class='fa fa-exclamation-triangle'></i></span>";

                            $formId = htmlspecialchars($r['form_id']);
                            $hcNumber = htmlspecialchars($r['hc_number']);
                            echo "<button
                                    class='btn btn-app btn-info'
                                    onclick=\"window.location.href='/cirugias/wizard?form_id=$formId&hc_number=$hcNumber'\">" .
                                $badge .
                                "<i class='mdi mdi-file-document'></i> Protocolo
                                </button>";
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        🔵 Procedimientos NO quirúrgicos no facturados
    </div>
    <div class="card-body p-2">
        <div class="table-responsive" style="overflow-x: auto; max-width: 100%; font-size: 0.85rem;">
            <table id="exampleX" class="table table-striped table-hover table-sm invoice-archive sticky-header">
                <thead class="bg-primary">
                <tr>
                    <th>Form ID</th>
                    <th>HC</th>
                    <th>Paciente</th>
                    <th>Afiliación</th>
                    <th>Fecha</th>
                    <th>Procedimiento</th>
                    <th>¿Facturar?</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($noQuirurgicos as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['form_id']) ?></td>
                        <td><?= htmlspecialchars($r['hc_number']) ?></td>
                        <td><?= htmlspecialchars(trim(($r['fname'] ?? '') . ' ' . ($r['mname'] ?? '') . ' ' . ($r['lname'] ?? '') . ' ' . ($r['lname2'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($r['afiliacion'] ?? '') ?></td>
                        <td><?= htmlspecialchars(date('d/m/Y', strtotime($r['fecha']))) ?></td>
                        <td><?= htmlspecialchars($r['nombre_procedimiento']) ?></td>
                        <td>
                            <a href="/billing/facturar.php?form_id=<?= urlencode($r['form_id']) ?>"
                               class="btn btn-sm btn-secondary mt-1">
                                Facturar ahora
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
    .table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background-color: #0d6efd;
        color: #fff;
    }
</style>
