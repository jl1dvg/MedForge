<?php if (!empty($scrapingOutput)):
$partes = explode("📋 Procedimientos proyectados:", $scrapingOutput);
if (isset($partes[1])):
$lineas = array_filter(array_map('trim', explode("\n", trim($partes[1]))));
$grupos = [];
for ($i = 0; $i < count($lineas); $i += 4) {
    $idLinea = $lineas[$i] ?? '';
    $fecha = $lineas[$i + 1] ?? '';
    $doctor = $lineas[$i + 2] ?? '';
    $estado = $lineas[$i + 3] ?? '';
    $color = str_contains($estado, '✅') ? 'success' : 'danger';
    $grupos[] = [
        'id' => trim($idLinea),
        'fecha' => trim($fecha),
        'doctor' => trim($doctor),
        'estado' => trim($estado),
        'color' => $color
    ];
}
?>
<div class="box">
    <div class="box-header with-border">
        <h4 class="box-title">Procedimientos proyectados</h4>
    </div>
    <div class="box-body">
        <div class="d-flex align-items-center mb-15">
            <input type="checkbox" id="select_all_checkbox" class="filled-in chk-col-info me-10">
            <label for="select_all_checkbox" class="mb-0">Seleccionar todos</label>
        </div>
        <?php foreach ($grupos as $i => $g): ?>
            <div class="d-flex align-items-center mb-25">
                <span class="bullet bullet-bar bg-<?= $g['color'] ?> align-self-stretch"></span>
                <div class="h-20 mx-20 flex-shrink-0">
                    <input type="checkbox" id="md_checkbox_proj_<?= $i ?>"
                           class="filled-in chk-col-<?= $g['color'] ?>"
                           value="<?= htmlspecialchars($g['id']) ?>">
                    <label for="md_checkbox_proj_<?= $i ?>"
                           class="h-20 p-10 mb-0"></label>
                </div>
                <div class="d-flex flex-column flex-grow-1">
                    <span class="text-dark fw-500 fs-16"><?= htmlspecialchars($g['id']) ?></span>
                    <span class="text-fade fw-500"><?= htmlspecialchars($g['fecha']) ?></span>
                    <span class="text-fade fw-500"><?= htmlspecialchars($g['doctor']) ?></span>
                </div>
                <span class="badge bg-<?= $g['color'] ?>"><?= htmlspecialchars($g['estado']) ?></span>
            </div>
        <?php endforeach; ?>
        <!-- Botón para agregar seleccionados -->
        <form id="guardarSeleccionadosForm" method="post">
            <input type="hidden" name="accion_guardar_procedimientos" value="1">
            <div class="text-end mt-3">
                <button type="button" class="btn btn-primary"
                        id="agregar-seleccionados"
                        onclick="enviarSeleccionados()">➕ Agregar seleccionados
                </button>
            </div>
        </form>
        <script>
            document.getElementById('agregar-seleccionados').addEventListener('click', function () {
                const checkboxes = document.querySelectorAll('input[id^="md_checkbox_proj_"]:checked');
                const formIds = Array.from(checkboxes).map(cb => {
                    const raw = cb.value;
                    const match = raw.match(/ID:\s*(\d+)/);
                    return match ? match[1] : null;
                }).filter(Boolean);

                if (formIds.length === 0) {
                    alert("Por favor selecciona al menos un procedimiento.");
                    return;
                }

                console.log("🛰️ Enviando form_ids:", formIds);

                fetch('/../../api/billing/verificacion_derivacion.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: 'form_ids[]=' + formIds.join('&form_ids[]=')
                })
                    .then(response => response.json())
                    .then(data => {
                        console.log("✅ Nuevos form_id:", data.nuevos);
                        console.log("❌ Ya existen form_id:", data.existentes);
                        alert(`📊 Resultados:\n➕ Nuevos: ${data.nuevos.length}\n⛔ Existentes: ${data.existentes.length}`);
                    })
                    .catch(error => {
                        console.error("Error en la verificación:", error);
                        alert("❌ Ocurrió un error en la verificación.");
                    });
            });

            function enviarSeleccionados() {
                const seleccionados = [];
                document.querySelectorAll('input[id^="md_checkbox_proj_"]:checked').forEach(cb => {
                    const row = cb.closest('.d-flex');
                    const id = row.querySelector('.fw-500.fs-16')?.textContent.trim();
                    const fecha = row.querySelectorAll('.text-fade.fw-500')[0]?.textContent.trim();
                    const doctor = row.querySelectorAll('.text-fade.fw-500')[1]?.textContent.trim();
                    const estado = row.querySelector('.badge')?.textContent.trim();
                    seleccionados.push({id, fecha, doctor, estado});
                });
                console.log("📦 Procedimientos seleccionados:", seleccionados);
                console.log("📋 Solo IDs seleccionados:", seleccionados.map(p => p.id));
                alert(`Seleccionados: ${seleccionados.length}\n` + seleccionados.map(p => p.id + ' – ' + p.doctor).join('\n'));
            }

            document.getElementById('select_all_checkbox')?.addEventListener('change', function () {
                const all = document.querySelectorAll('input[id^="md_checkbox_proj_"]');
                all.forEach(cb => cb.checked = this.checked);
            });
        </script>
    </div>
</div>
<?php
endif;
endif;
?></file>