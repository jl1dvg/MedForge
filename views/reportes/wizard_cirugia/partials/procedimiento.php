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
            <input type="hidden" name="form_id_scrape" value="<?= htmlspecialchars($cirugia->form_id); ?>">
            <input type="hidden" name="hc_number_scrape" value="<?= htmlspecialchars($cirugia->hc_number); ?>">
            <button type="submit" name="scrape_derivacion" class="btn btn-outline-secondary">
                🔍 Extraer datos desde Log de Admisión
            </button>
    </div>

    <?php
    if (isset($_POST['scrape_derivacion']) && !empty($_POST['form_id_scrape']) && !empty($_POST['hc_number_scrape'])) {
        $form_id = escapeshellarg($_POST['form_id_scrape']);
        $hc_number = escapeshellarg($_POST['hc_number_scrape']);

        $command = "/usr/bin/python3 /homepages/26/d793096920/htdocs/cive/public/scrapping/scrape_log_admision.py $form_id $hc_number";
        $output = shell_exec($command);

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