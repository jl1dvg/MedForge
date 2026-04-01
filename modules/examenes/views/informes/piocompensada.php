<div class="informe-template" data-informe-template="piocompensada">
    <?php include __DIR__ . '/_firmante.php'; ?>
    <div class="alert alert-light border small">
        Formula de compensacion: PIO corregida = PIO medida - (((CCT - 540) / 10) * 0.7)
    </div>
    <div class="row g-3">
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <h6 class="text-muted">Ojo derecho</h6>
                <label class="form-label" for="paquimetriaOD">Paquimetria central (micras)</label>
                <input type="number" step="0.01" id="paquimetriaOD" class="form-control" inputmode="decimal">

                <label class="form-label mt-2" for="pioMedidaOD">PIO medida (mmHg)</label>
                <input type="number" step="0.01" id="pioMedidaOD" class="form-control" inputmode="decimal">

                <label class="form-label mt-2" for="compensacionOD">Compensacion estimada (mmHg)</label>
                <input type="text" id="compensacionOD" class="form-control" readonly>

                <label class="form-label mt-2" for="ajusteOD">Ajuste sugerido</label>
                <input type="text" id="ajusteOD" class="form-control" readonly>

                <label class="form-label mt-2" for="pioCompensadaOD">PIO compensada (mmHg)</label>
                <input type="text" id="pioCompensadaOD" class="form-control" readonly>
            </div>
        </div>
        <div class="col-md-6">
            <div class="border rounded p-3 h-100">
                <h6 class="text-muted">Ojo izquierdo</h6>
                <label class="form-label" for="paquimetriaOI">Paquimetria central (micras)</label>
                <input type="number" step="0.01" id="paquimetriaOI" class="form-control" inputmode="decimal">

                <label class="form-label mt-2" for="pioMedidaOI">PIO medida (mmHg)</label>
                <input type="number" step="0.01" id="pioMedidaOI" class="form-control" inputmode="decimal">

                <label class="form-label mt-2" for="compensacionOI">Compensacion estimada (mmHg)</label>
                <input type="text" id="compensacionOI" class="form-control" readonly>

                <label class="form-label mt-2" for="ajusteOI">Ajuste sugerido</label>
                <input type="text" id="ajusteOI" class="form-control" readonly>

                <label class="form-label mt-2" for="pioCompensadaOI">PIO compensada (mmHg)</label>
                <input type="text" id="pioCompensadaOI" class="form-control" readonly>
            </div>
        </div>
    </div>
</div>
