<h6>Procedimientos, Diagnósticos y Lateralidad</h6>
<section>
    <!-- Procedimientos -->
    <div class="form-group">
        <label for="procedimientos" class="form-label">Procedimientos :</label>
        <?php
        $procedimientosArray = json_decode($cirugia->procedimientos, true); // Decodificar el JSON

        // Si hay procedimientos, los mostramos en inputs separados
        if (!empty($procedimientosArray)) {
            foreach ($procedimientosArray as $index => $proc) {
                $codigo = isset($proc['procInterno']) ? $proc['procInterno'] : '';  // Código completo del procedimiento
                echo '<div class="row mb-2">';
                echo '<div class="col-md-12">';
                echo '<input type="text" class="form-control" name="procedimientos[' . $index . '][procInterno]" value="' . htmlspecialchars($codigo) . '" />';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<input type="text" class="form-control" name="procedimientos[0][procInterno]" placeholder="Agregar Procedimiento" />';
        }
        ?>
    </div>

    <!-- Diagnósticos de la derivación (placeholder, se llena por AJAX del scraper) -->
    <div id="diagDerivacionPlaceholder"></div>

    <!-- Diagnósticos -->
    <div class="form-group">
        <label for="diagnosticos" class="form-label">Diagnósticos :</label>
        <?php
        $diagnosticosArray = json_decode($cirugia->diagnosticos, true); // Decodificar el JSON

        // Si hay diagnósticos, los mostramos en inputs separados
        if (!empty($diagnosticosArray)) {
            foreach ($diagnosticosArray as $index => $diag) {
                $ojo = isset($diag['ojo']) ? $diag['ojo'] : '';
                $evidencia = isset($diag['evidencia']) ? $diag['evidencia'] : '';
                $idDiagnostico = isset($diag['idDiagnostico']) ? $diag['idDiagnostico'] : '';
                $observaciones = isset($diag['observaciones']) ? $diag['observaciones'] : '';

                echo '<div class="row mb-2">';
                echo '<div class="col-md-2">';
                echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][ojo]" value="' . htmlspecialchars($ojo) . '" placeholder="Ojo" />';
                echo '</div>';
                echo '<div class="col-md-2">';
                echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][evidencia]" value="' . htmlspecialchars($evidencia) . '" placeholder="Evidencia" />';
                echo '</div>';
                echo '<div class="col-md-6">';
                echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][idDiagnostico]" value="' . htmlspecialchars($idDiagnostico) . '" placeholder="Código CIE-10" />';
                echo '</div>';
                echo '<div class="col-md-2">';
                echo '<input type="text" class="form-control" name="diagnosticos[' . $index . '][observaciones]" value="' . htmlspecialchars($observaciones) . '" placeholder="Observaciones" />';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<input type="text" class="form-control" name="diagnosticos[0][idDiagnostico]" placeholder="Agregar Diagnóstico" />';
        }
        ?>
    </div>
    <!-- Lateralidad -->
    <div class="form-group">
        <label for="lateralidad" class="form-label">Lateralidad :</label>
        <select class="form-select" id="lateralidad" name="lateralidad">
            <option value="OD" <?= ($cirugia->lateralidad == 'OD') ? 'selected' : '' ?>>
                OD
            </option>
            <option value="OI" <?= ($cirugia->lateralidad == 'OI') ? 'selected' : '' ?>>
                OI
            </option>
            <option value="AO" <?= ($cirugia->lateralidad == 'AO') ? 'selected' : '' ?>>
                AO
            </option>
        </select>
    </div>
    <!-- Botón para Scraping de Derivación -->
    <div class="form-group mt-4">
        <button type="button" id="btnScrapeDerivacion" class="btn btn-outline-secondary"
                data-form="<?= htmlspecialchars($cirugia->form_id); ?>"
                data-hc="<?= htmlspecialchars($cirugia->hc_number); ?>">
            🔍 Extraer datos desde Log de Admisión
        </button>
        <div id="resultadoScraper" class="mt-3"></div>
    </div>
    <script>
        (function () {

            // Inicializa eventos de quitar y sincroniza el hidden JSON (se llama tras insertar el fragmento)
            function initDiagPreviosInteractions() {
                const block = document.getElementById('diagDerivacionBlock');
                if (!block) return;
                const hidden = block.querySelector('input[name=diagnosticos_previos]');
                const counter = block.querySelector('#diagPreviosCounter');

                function serialize() {
                    const rows = Array.from(block.querySelectorAll('.diag-row'));
                    const list = rows.map(r => ({
                        cie10: r.querySelector('.diag-cie').value.trim(),
                        descripcion: r.querySelector('.diag-desc').value.trim()
                    }));
                    if (hidden) hidden.value = JSON.stringify(list);
                    const n = list.length;
                    if (counter) {
                        counter.textContent = `${n} / 3`;
                        counter.className = 'badge ' + (n > 3 ? 'bg-danger' : 'bg-secondary');
                    }
                }

                // Delegación de eventos para quitar
                block.addEventListener('click', function (e) {
                    const btn = e.target.closest('.btn-remove-diag');
                    if (!btn) return;
                    const row = btn.closest('.diag-row');
                    if (row) {
                        row.remove();
                        serialize();
                    }
                });
                serialize();
            }

            const btn = document.getElementById('btnScrapeDerivacion');
            const out = document.getElementById('resultadoScraper');
            if (!btn || !out) return;

            const spinner = `
          <div class="d-flex align-items-center gap-2">
              <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
              <span>Extrayendo datos…</span>
          </div>
      `;

            btn.addEventListener('click', function () {
                const form_id = btn.dataset.form;
                const hc_number = btn.dataset.hc;
                const currentUrl = new URL(window.location.href);
                const requestUrl = currentUrl.pathname + currentUrl.search;

                // UI: disable & show spinner
                btn.disabled = true;
                const original = btn.innerHTML;
                btn.innerHTML = 'Procesando…';
                out.innerHTML = spinner;

                console.time('[SCRAPER] fetch');
                const formData = new FormData();
                formData.append('scrape_derivacion', '1');
                formData.append('form_id_scrape', form_id);
                formData.append('hc_number_scrape', hc_number);
                formData.append('form_id', form_id);
                formData.append('hc_number', hc_number);
                formData.append('ajax', '1'); // marca como ajax para futura lógica si la usas en PHP

                fetch(requestUrl, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(async (resp) => {
                        console.timeEnd('[SCRAPER] fetch');
                        const text = await resp.text();
                        const temp = document.createElement('div');
                        temp.innerHTML = text;

                        // 1) Preferimos el fragmento minimalista con diagnósticos:
                        const frag = temp.querySelector('#diagDerivacionBlock');
                        const placeholder = document.getElementById('diagDerivacionPlaceholder');
                        if (frag && placeholder) {
                            placeholder.innerHTML = '';
                            placeholder.appendChild(frag);
                            // Inicializa lógica de quitar/sincronizar ahora que el fragmento está en el DOM
                            initDiagPreviosInteractions();
                            // limpiamos el área de resultado para no mostrar info extra
                            out.innerHTML = '';
                            return;
                        }

                        // 2) Fallback: si no vino el fragmento, dejamos el .box (debug)
                        const box = temp.querySelector('.box');
                        if (box) {
                            out.innerHTML = '';
                            out.appendChild(box);
                        } else {
                            out.textContent = text.trim() ? text : 'No se recibió contenido.';
                        }
                    })
                    .catch((err) => {
                        console.error('[SCRAPER] Error de red o JS:', err);
                        out.innerHTML = '<div class="text-danger">❌ Ocurrió un error al conectar con el scraper.</div>';
                    })
                    .finally(() => {
                        btn.disabled = false;
                        btn.innerHTML = original;
                    });
            });
        })();
    </script>

    <?php
    if (isset($_POST['scrape_derivacion']) && !empty($_POST['form_id_scrape']) && !empty($_POST['hc_number_scrape'])) {
        // Utilidad: normaliza una cadena a {cie10, descripcion}
        if (!function_exists('normalizarCieDesc')) {
            function normalizarCieDesc(string $raw): array
            {
                $raw = trim($raw);
                if ($raw === '') {
                    return ['cie10' => '', 'descripcion' => ''];
                }
                // Si viene como "H544 - CEGUERA DE UN OJO"
                if (strpos($raw, '-') !== false) {
                    $parts = array_map('trim', explode('-', $raw, 2));
                    $cie = isset($parts[0]) ? trim(explode(' ', $parts[0])[0]) : '';
                    $desc = $parts[1] ?? '';
                    return ['cie10' => strtoupper($cie), 'descripcion' => $desc];
                }
                // Si viene solo el código o con texto extra sin guion
                $cie = trim(explode(' ', $raw)[0]);
                return ['cie10' => strtoupper($cie), 'descripcion' => ''];
            }
        }
        // Si es una solicitud AJAX, devolver solo el bloque necesario
        $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || (isset($_POST['ajax']) && $_POST['ajax'] === '1');

        $form_id = escapeshellarg($_POST['form_id_scrape']);
        $hc_number = escapeshellarg($_POST['hc_number_scrape']);

        $command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/medforge/scrapping/scrape_log_admision.py $form_id $hc_number";
        $output = shell_exec($command);
        if ($output === null || $output === false || trim($output) === '') {
            if ($isAjax) {
                echo '<div id="diagDerivacionBlock"><!-- sin diagnosticos (scraper sin salida) --></div>';
                return;
            } else {
                echo "<div class='box' style='font-family: monospace;'><div class='box-body' style='background: #f8f9fa; border: 1px solid #ccc; padding: 10px; border-radius: 5px;'>";
                echo "⚠️ El scraper no devolvió resultados o ocurrió un error.";
                echo "</div></div>";
                return;
            }
        }

        // Extrae solo los diagnósticos de la salida del scraper
        $diagnosticoRaw = '';
        if (preg_match('/"diagnostico":\s*([^\n]+)/', $output, $matchDiagnostico)) {
            $diagnosticoRaw = trim($matchDiagnostico[1], '", ');
        } elseif (preg_match('/📌 Diagnostico:\s*(.+)/', $output, $mAlt)) { // alternativa por si cambia el formato
            $diagnosticoRaw = trim($mAlt[1]);
        }
        $diagnosticosList = array_filter(array_map('trim', preg_split('/[;\r\n]+/', $diagnosticoRaw)));

        if ($isAjax) {
            // Unificar diagnósticos de DERIVACIÓN + HISTORIA CLÍNICA para enriquecer "diagnosticos_previos"
            // 1) Normalizar diagnósticos del scraper
            $fromScraper = [];
            foreach ($diagnosticosList as $item) {
                $n = normalizarCieDesc($item);
                if ($n['cie10'] !== '' || $n['descripcion'] !== '') {
                    $fromScraper[$n['cie10']] = $n;
                }
            }

            // 2) Traer diagnósticos únicos recientes del paciente (si existe el controller y método)
            $fromHistory = [];
            $hc_plain = isset($_POST['hc_number_scrape']) ? trim($_POST['hc_number_scrape'], "'") : '';
            try {
                if (!empty($hc_plain) && isset($pacienteService) && method_exists($pacienteService, 'getDiagnosticosPorPaciente')) {
                    $hist = $pacienteService->getDiagnosticosPorPaciente($hc_plain);
                    // $hist es un mapa idDiagnostico => ['idDiagnostico'=>..., 'fecha'=>...]
                    foreach ($hist as $item) {
                        $raw = trim($item['idDiagnostico'] ?? '');
                        if ($raw === '') {
                            continue;
                        }
                        $n = normalizarCieDesc($raw);
                        $cie = $n['cie10'];
                        $desc = $n['descripcion'];
                        if ($cie !== '') {
                            if (!isset($fromHistory[$cie])) {
                                $fromHistory[$cie] = ['cie10' => $cie, 'descripcion' => $desc];
                            } else {
                                if ($fromHistory[$cie]['descripcion'] === '' && $desc !== '') {
                                    $fromHistory[$cie]['descripcion'] = $desc;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Silencioso: si falla, seguimos sólo con lo del scraper
            }

            // 3) Ordenar: primero los del SCRAPER (en su orden), luego HISTORIA (sin duplicar)
            $seen = [];
            $ordered = [];
            // a) del scraper
            foreach ($diagnosticosList as $item) {
                $n = normalizarCieDesc($item);
                $cieKey = $n['cie10'];
                if ($cieKey === '' || isset($seen[$cieKey])) {
                    continue;
                }
                if (isset($fromScraper[$cieKey])) {
                    $ordered[] = $fromScraper[$cieKey];
                    $seen[$cieKey] = true;
                }
            }
            // b) del historial (los que no estén ya)
            foreach ($fromHistory as $cie => $obj) {
                if (!isset($seen[$cie])) {
                    $ordered[] = $obj;
                    $seen[$cie] = true;
                }
            }

            // 4) Render minimal block + hidden JSON (usando $ordered)
            echo '<div id="diagDerivacionBlock">';
            if (!empty($ordered)) {
                echo '<div class="form-group">';
                echo '<label class="form-label">Diagnósticos de la derivación:</label>';
                echo '<div class="border rounded p-2" style="background:#f9fbfd;">';
                foreach ($ordered as $obj) {
                    $cie = htmlspecialchars($obj['cie10']);
                    $desc = htmlspecialchars($obj['descripcion']);
                    $isFromScraper = isset($fromScraper[$obj['cie10']]);
                    $rowClass = $isFromScraper ? 'bg-light border-start border-3 border-primary' : '';
                    echo '<div class="row mb-2 align-items-center diag-row ' . $rowClass . '" data-cie="' . $cie . '">';
                    echo '  <div class="col-md-3"><input type="text" class="form-control diag-cie" value="' . $cie . '" readonly></div>';
                    echo '  <div class="col-md-7"><input type="text" class="form-control diag-desc" value="' . $desc . '" readonly></div>';
                    echo '  <div class="col-md-2 text-end">';
                    echo '      <button type="button" class="btn btn-sm btn-outline-danger btn-remove-diag" title="Quitar">Quitar</button>';
                    echo '  </div>';
                    echo '</div>';
                }
                $previosJson = htmlspecialchars(json_encode($ordered, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8');
                echo '<input type="hidden" name="diagnosticos_previos" value="' . $previosJson . '">';
                // Footer con contador y aviso de máximo
                echo '<div class="d-flex justify-content-between align-items-center mt-2">';
                echo '  <small class="text-muted">Máximo permitido: 3 diagnósticos.</small>';
                echo '  <span id="diagPreviosCounter" class="badge bg-secondary">0 / 3</span>';
                echo '</div>';
                echo '</div></div>';
            } else {
                echo '<!-- sin diagnosticos -->';
            }
            echo '</div>';
            return;
        }

        echo "<div class='box' style='font-family: monospace;'>";
        echo "<div class='box-header with-border'>";
        echo "<strong>📋 Resultado del Scraper:</strong><br></div>";
        echo "<div class='box-body' style='background: #f8f9fa; border: 1px solid #ccc; padding: 10px; border-radius: 5px;'>";

        $scraperResponse = [
            'codigo_derivacion' => '',
            'fecha_registro' => '',
            'fecha_vigencia' => '',
            'diagnostico' => '',
            'hc_number' => ''
        ];

        if (preg_match('/Código Derivación:\s*([^\n]+)/', $output, $matchCodigo)) {
            $scraperResponse['codigo_derivacion'] = trim($matchCodigo[1]);
        }
        if (preg_match('/"hc_number":\s*"([^"]+)"/', $output, $matchhcnumber)) {
            $scraperResponse['hc_number'] = trim($matchhcnumber[1]);
        }
        if (preg_match('/Fecha de registro:\s*(\d{4}-\d{2}-\d{2})/', $output, $matchRegistro)) {
            $scraperResponse['fecha_registro'] = $matchRegistro[1];
        }
        if (preg_match('/Fecha de Vigencia:\s*(\d{4}-\d{2}-\d{2})/', $output, $matchVigencia)) {
            $scraperResponse['fecha_vigencia'] = $matchVigencia[1];
        }
        if (preg_match('/"diagnostico":\s*([^\n]+)/', $output, $matchDiagnostico)) {
            $scraperResponse['diagnostico'] = trim($matchDiagnostico[1], '", ');
        }

        $form_id_trimmed = trim($_POST['form_id_scrape'], "'");
        $hc_number_trimmed = trim($_POST['hc_number_scrape'], "'");
        $verificacionController->verificarDerivacion($form_id_trimmed, $hc_number_trimmed, $scraperResponse);

        echo nl2br(htmlspecialchars($output));
        echo "</div></div>";
    }
    ?>
</section>