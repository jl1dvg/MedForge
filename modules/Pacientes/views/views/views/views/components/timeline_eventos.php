<?php

use Modules\Pacientes\Support\ViewHelper as PacientesHelper;

if (!empty($eventos)): ?>
    <section class="cd-horizontal-timeline">
        <div class="timeline">
            <div class="events-wrapper">
                <div class="events">
                    <ol style="white-space: nowrap; overflow-x: auto; display: flex; gap: 1rem;">
                        <?php foreach ($eventos as $index => $row): ?>
                            <li style="min-width: 80px; text-align: center;">
                                <?php
                                $fecha_raw = $row['fecha'] ?? '';
                                $timestamp = $fecha_raw && strtotime($fecha_raw) ? strtotime($fecha_raw) : null;
                                $fecha_valida = $timestamp ? date('d/m/Y', $timestamp) : '01/01/2000';
                                $texto_fecha = $timestamp ? date('d M', $timestamp) : '01 Jan';
                                ?>
                                <a href="#0" style="display: inline-block; padding: 6px 10px;"
                                   data-date="<?php echo $fecha_valida; ?>"
                                   class="<?php echo $index === 0 ? 'selected' : ''; ?>">
                                    <?php echo $texto_fecha; ?>
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <span class="filling-line" aria-hidden="true"></span>
                </div>
                <!-- .events -->
            </div>
            <!-- .events-wrapper -->
            <ul class="cd-timeline-navigation">
                <li><a href="#0" class="prev inactive">Prev</a></li>
                <li><a href="#0" class="next">Next</a></li>
            </ul>
            <!-- .cd-timeline-navigation -->
        </div>
        <!-- .timeline -->
        <div class="events-content">
            <ol>
                <?php foreach ($eventos as $index => $row):
                    $procedimiento = $row['procedimiento_proyectado'] ?? '';
                    $procedimiento_parts = explode(' - ', $procedimiento);
                    $nombre_procedimiento = implode(' - ', array_slice($procedimiento_parts, 2));
                    ?>
                    <li data-date="<?php echo PacientesHelper::formatDateSafe($row['fecha'] ?? null); ?>"
                        class="<?php echo $index === 0 ? 'selected' : ''; ?>">
                        <h2><?php echo PacientesHelper::safe($nombre_procedimiento); ?></h2>
                        <small><?php echo PacientesHelper::formatDateSafe($row['fecha'] ?? null, 'F jS, Y'); ?></small>
                        <hr class="my-30">
                        <p class="pb-30"><?php echo nl2br(PacientesHelper::safe($row['contenido'] ?? '')); ?></p>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
        <!-- .events-content -->
    </section>
<?php else: ?>
    <p>No hay datos disponibles para mostrar en el timeline.</p>
<?php endif; ?>
