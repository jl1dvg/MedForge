<div class="informe-template" data-informe-template="cornea">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <h6 class="text-muted">Ojo derecho (OD)</h6>
            <label class="form-label" for="kFlatOD">K Flat</label>
            <input type="number" step="0.01" id="kFlatOD" class="form-control" inputmode="decimal">

            <label class="form-label mt-2" for="axisFlatOD">Axis</label>
            <input type="number" step="1" min="0" max="180" id="axisFlatOD" class="form-control" inputmode="numeric">

            <label class="form-label mt-2" for="kSteepOD">K Steep</label>
            <input type="number" step="0.01" id="kSteepOD" class="form-control" inputmode="decimal">

            <label class="form-label mt-2" for="axisSteepOD">Axis (auto)</label>
            <input type="number" step="1" id="axisSteepOD" class="form-control" readonly>

            <label class="form-label mt-2" for="cilindroOD">Cilindro (auto)</label>
            <input type="number" step="0.01" id="cilindroOD" class="form-control" readonly>

            <label class="form-label mt-2" for="kPromedioOD">K Promedio (auto)</label>
            <input type="number" step="0.01" id="kPromedioOD" class="form-control" readonly>
        </div>

        <div class="col-md-6">
            <h6 class="text-muted">Ojo izquierdo (OI)</h6>
            <label class="form-label" for="kFlatOI">K Flat</label>
            <input type="number" step="0.01" id="kFlatOI" class="form-control" inputmode="decimal">

            <label class="form-label mt-2" for="axisFlatOI">Axis</label>
            <input type="number" step="1" min="0" max="180" id="axisFlatOI" class="form-control" inputmode="numeric">

            <label class="form-label mt-2" for="kSteepOI">K Steep</label>
            <input type="number" step="0.01" id="kSteepOI" class="form-control" inputmode="decimal">

            <label class="form-label mt-2" for="axisSteepOI">Axis (auto)</label>
            <input type="number" step="1" id="axisSteepOI" class="form-control" readonly>

            <label class="form-label mt-2" for="cilindroOI">Cilindro (auto)</label>
            <input type="number" step="0.01" id="cilindroOI" class="form-control" readonly>

            <label class="form-label mt-2" for="kPromedioOI">K Promedio (auto)</label>
            <input type="number" step="0.01" id="kPromedioOI" class="form-control" readonly>
        </div>
    </div>
</div>
