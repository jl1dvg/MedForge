<div class="informe-template" data-informe-template="biometria">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="row g-3">
        <div class="col-md-6">
            <h6 class="text-muted">Ojo derecho</h6>
            <label class="form-label mt-2" for="axialOD">Longitud axial</label>
            <input type="number" step="0.01" id="axialOD" class="form-control" inputmode="decimal">
            <label class="form-label" for="camaraOD">Cámara anterior</label>
            <input type="number" step="0.01" id="camaraOD" class="form-control" inputmode="decimal">
            <label class="form-label mt-2" for="cristalinoOD">Cristalino</label>
            <input type="number" step="0.01" id="cristalinoOD" class="form-control" inputmode="decimal">
        </div>
        <div class="col-md-6">
            <h6 class="text-muted">Ojo izquierdo</h6>
            <label class="form-label mt-2" for="axialOI">Longitud axial</label>
            <input type="number" step="0.01" id="axialOI" class="form-control" inputmode="decimal">
            <label class="form-label" for="camaraOI">Cámara anterior</label>
            <input type="number" step="0.01" id="camaraOI" class="form-control" inputmode="decimal">
            <label class="form-label mt-2" for="cristalinoOI">Cristalino</label>
            <input type="number" step="0.01" id="cristalinoOI" class="form-control" inputmode="decimal">
        </div>
    </div>
</div>
