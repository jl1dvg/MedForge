<div class="informe-template" data-informe-template="microespecular">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <h6 class="text-muted">Ojo derecho</h6>
            <label class="form-label" for="densidadOD">Densidad celular</label>
            <input type="number" step="0.01" id="densidadOD" class="form-control" inputmode="decimal">
            <label class="form-label mt-2" for="desviacionOD">Desviación standard</label>
            <input type="number" step="0.01" id="desviacionOD" class="form-control" inputmode="decimal">
            <label class="form-label mt-2" for="coefVarOD">Coeficiente de variación</label>
            <input type="number" step="0.01" id="coefVarOD" class="form-control" inputmode="decimal">
        </div>
        <div class="col-md-6">
            <h6 class="text-muted">Ojo izquierdo</h6>
            <label class="form-label" for="densidadOI">Densidad celular</label>
            <input type="number" step="0.01" id="densidadOI" class="form-control" inputmode="decimal">
            <label class="form-label mt-2" for="desviacionOI">Desviación standard</label>
            <input type="number" step="0.01" id="desviacionOI" class="form-control" inputmode="decimal">
            <label class="form-label mt-2" for="coefVarOI">Coeficiente de variación</label>
            <input type="number" step="0.01" id="coefVarOI" class="form-control" inputmode="decimal">
        </div>
    </div>
</div>
