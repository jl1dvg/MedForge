<div class="informe-template" data-informe-template="octno">
    <?php include __DIR__ . '/_firmante.php'; ?>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100 bg-white">
                <h6 class="mb-2 text-muted">Ojo derecho (OD)</h6>
                <label class="form-label" for="inputOD">Promedio espesor CFNR (um)</label>
                <input type="number" min="0" max="300" step="1" id="inputOD" class="form-control" inputmode="numeric">

                <div class="small text-muted mt-3 mb-2">Cuadrantes afectados</div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_od_sup">
                            <label class="form-check-label" for="octno_od_sup">Superior</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_od_inf">
                            <label class="form-check-label" for="octno_od_inf">Inferior</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_od_nas">
                            <label class="form-check-label" for="octno_od_nas">Nasal</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_od_temp">
                            <label class="form-check-label" for="octno_od_temp">Temporal</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="border rounded p-3 h-100 bg-white">
                <h6 class="mb-2 text-muted">Ojo izquierdo (OI)</h6>
                <label class="form-label" for="inputOI">Promedio espesor CFNR (um)</label>
                <input type="number" min="0" max="300" step="1" id="inputOI" class="form-control" inputmode="numeric">

                <div class="small text-muted mt-3 mb-2">Cuadrantes afectados</div>
                <div class="row g-2">
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_oi_sup">
                            <label class="form-check-label" for="octno_oi_sup">Superior</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_oi_inf">
                            <label class="form-check-label" for="octno_oi_inf">Inferior</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_oi_nas">
                            <label class="form-check-label" for="octno_oi_nas">Nasal</label>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="octno_oi_temp">
                            <label class="form-check-label" for="octno_oi_temp">Temporal</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
