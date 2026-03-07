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
            function initDiagPreviosInteractions() {
                const block = document.getElementById('diagDerivacionBlock');
                if (!block) return;
                const hidden = block.querySelector('input[name="diagnosticos_previos"]');
                const counter = block.querySelector('#diagPreviosCounter');

                function serialize() {
                    const rows = Array.from(block.querySelectorAll('.diag-row'));
                    const list = rows.map((row) => {
                        const cieInput = row.querySelector('.diag-cie');
                        const descInput = row.querySelector('.diag-desc');
                        return {
                            cie10: ((cieInput && cieInput.value) || '').trim(),
                            descripcion: ((descInput && descInput.value) || '').trim()
                        };
                    });
                    if (hidden) hidden.value = JSON.stringify(list);
                    const n = list.length;
                    if (counter) {
                        counter.textContent = `${n} / 3`;
                        counter.className = 'badge ' + (n > 3 ? 'bg-danger' : 'bg-secondary');
                    }
                }

                block.addEventListener('click', function (event) {
                    const button = event.target.closest('.btn-remove-diag');
                    if (!button) return;
                    const row = button.closest('.diag-row');
                    if (!row) return;
                    row.remove();
                    serialize();
                });

                serialize();
            }

            function escapeHtml(value) {
                return String(value || '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/\"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function renderDiagnosticosPrevios(items) {
                const placeholder = document.getElementById('diagDerivacionPlaceholder');
                if (!placeholder) return;

                placeholder.innerHTML = '';
                const block = document.createElement('div');
                block.id = 'diagDerivacionBlock';

                if (!Array.isArray(items) || items.length === 0) {
                    placeholder.appendChild(block);
                    return;
                }

                const normalized = items.map((item) => {
                    const source = (item && typeof item === 'object') ? item : {};
                    return {
                        cie10: String(source.cie10 || '').trim(),
                        descripcion: String(source.descripcion || '').trim(),
                        from_scraper: Boolean(source.from_scraper)
                    };
                }).filter((item) => item.cie10 !== '' || item.descripcion !== '');

                const group = document.createElement('div');
                group.className = 'form-group';
                group.innerHTML = '<label class="form-label">Diagnósticos de la derivación:</label>';

                const list = document.createElement('div');
                list.className = 'border rounded p-2';
                list.style.background = '#f9fbfd';

                normalized.forEach((item) => {
                    const row = document.createElement('div');
                    row.className = 'row mb-2 align-items-center diag-row' + (item.from_scraper ? ' bg-light border-start border-3 border-primary' : '');
                    row.dataset.cie = item.cie10;

                    row.innerHTML = `
                        <div class="col-md-3"><input type="text" class="form-control diag-cie" value="${escapeHtml(item.cie10)}" readonly></div>
                        <div class="col-md-7"><input type="text" class="form-control diag-desc" value="${escapeHtml(item.descripcion)}" readonly></div>
                        <div class="col-md-2 text-end">
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove-diag" title="Quitar">Quitar</button>
                        </div>
                    `;

                    list.appendChild(row);
                });

                const hidden = document.createElement('input');
                hidden.type = 'hidden';
                hidden.name = 'diagnosticos_previos';
                hidden.value = JSON.stringify(normalized.map((item) => ({
                    cie10: item.cie10,
                    descripcion: item.descripcion
                })));

                const footer = document.createElement('div');
                footer.className = 'd-flex justify-content-between align-items-center mt-2';
                footer.innerHTML = `
                    <small class="text-muted">Máximo permitido: 3 diagnósticos.</small>
                    <span id="diagPreviosCounter" class="badge bg-secondary">0 / 3</span>
                `;

                list.appendChild(hidden);
                list.appendChild(footer);
                group.appendChild(list);
                block.appendChild(group);
                placeholder.appendChild(block);

                initDiagPreviosInteractions();
            }

            const button = document.getElementById('btnScrapeDerivacion');
            const output = document.getElementById('resultadoScraper');
            if (!button || !output) return;

            const endpoint = (window.cirugiasEndpoints && window.cirugiasEndpoints.scrapeDerivacion)
                ? window.cirugiasEndpoints.scrapeDerivacion
                : '/v2/cirugias/wizard/scrape-derivacion';

            const spinner = `
                <div class="d-flex align-items-center gap-2">
                    <div class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></div>
                    <span>Extrayendo datos...</span>
                </div>
            `;

            button.addEventListener('click', async function () {
                const formId = (button.dataset.form || '').trim();
                const hcNumber = (button.dataset.hc || '').trim();
                if (formId === '' || hcNumber === '') {
                    output.innerHTML = '<div class="text-danger">Faltan form_id o hc_number para ejecutar el scraper.</div>';
                    return;
                }

                const originalLabel = button.innerHTML;
                button.disabled = true;
                button.innerHTML = 'Procesando...';
                output.innerHTML = spinner;

                try {
                    const formData = new FormData();
                    formData.append('form_id', formId);
                    formData.append('hc_number', hcNumber);
                    if (typeof window.csrfToken === 'string' && window.csrfToken) {
                        formData.append('_token', window.csrfToken);
                    }

                    const response = await fetch(endpoint, {
                        method: 'POST',
                        body: formData,
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    const payload = await response.json();
                    if (!response.ok || payload.success === false) {
                        throw new Error(payload.message || 'No se pudo procesar la derivación.');
                    }

                    const data = payload.data || {};
                    const diagnosticos = Array.isArray(data.diagnosticos_previos) ? data.diagnosticos_previos : [];
                    renderDiagnosticosPrevios(diagnosticos);

                    if (diagnosticos.length === 0) {
                        output.innerHTML = '<div class="text-warning">No se encontraron diagnósticos para cargar desde la derivación.</div>';
                    } else {
                        output.innerHTML = '<div class="text-success">Diagnósticos de derivación cargados correctamente.</div>';
                    }

                    if (payload.warning) {
                        const warning = document.createElement('div');
                        warning.className = 'text-muted small mt-1';
                        warning.textContent = String(payload.warning);
                        output.appendChild(warning);
                    }
                } catch (error) {
                    console.error('[SCRAPER] Error:', error);
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'text-danger';
                    errorDiv.textContent = (error && error.message)
                        ? String(error.message)
                        : 'Ocurrió un error al consultar el scraper.';
                    output.innerHTML = '';
                    output.appendChild(errorDiv);
                } finally {
                    button.disabled = false;
                    button.innerHTML = originalLabel;
                }
            });
        })();
    </script>
</section>
