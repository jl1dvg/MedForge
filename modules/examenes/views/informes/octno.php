<div class="informe-template" data-informe-template="octno">
    <?php include __DIR__ . '/_firmante.php'; ?>

    <style>
        .octno-eye-card {
            border: 1px solid #dee2e6;
            border-radius: .5rem;
            padding: .75rem;
            background: #fff;
        }

        .octno-wheel {
            width: 220px;
            height: 220px;
            margin: 0 auto;
            position: relative;
            border-radius: 50%;
            border: 1px solid #ced4da;
            overflow: hidden;
            background: #f8f9fa;
        }

        .octno-segment {
            position: absolute;
            inset: 0;
            border: none;
            background: #e9ecef;
            color: #495057;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            line-height: 1;
            cursor: pointer;
            transition: background-color .2s ease, color .2s ease;
            z-index: 1;
        }

        .octno-segment span {
            position: absolute;
            z-index: 2;
        }

        .octno-segment.sup {
            clip-path: polygon(50% 50%, 0 0, 100% 0);
        }

        .octno-segment.inf {
            clip-path: polygon(50% 50%, 0 100%, 100% 100%);
        }

        .octno-segment.nas {
            clip-path: polygon(50% 50%, 0 0, 0 100%);
        }

        .octno-segment.temp {
            clip-path: polygon(50% 50%, 100% 0, 100% 100%);
        }

        .octno-segment.sup span {
            top: 12px;
            left: 50%;
            transform: translateX(-50%);
        }

        .octno-segment.inf span {
            bottom: 12px;
            left: 50%;
            transform: translateX(-50%);
        }

        .octno-segment.nas span {
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
        }

        .octno-segment.temp span {
            right: 14px;
            top: 50%;
            transform: translateY(-50%);
        }

        .octno-wheel .btn-check:checked + .octno-segment {
            background: #0d6efd;
            color: #fff;
        }

        .octno-center {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 92px;
            height: 92px;
            border-radius: 50%;
            background: #fff;
            border: 2px solid #adb5bd;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #212529;
            z-index: 5;
            pointer-events: none;
        }
    </style>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="octno-eye-card h-100">
                <h6 class="mb-2 text-muted">Ojo derecho (OD)</h6>
                <label class="form-label" for="inputOD">Promedio espesor CFNR (um)</label>
                <input type="number" min="0" max="300" step="1" id="inputOD" class="form-control" inputmode="numeric">
                <div class="small text-muted mt-2 mb-2">Seleccione cuadrantes afectados</div>
                <div class="octno-wheel">
                    <input class="btn-check" type="checkbox" id="octno_od_sup">
                    <label class="octno-segment sup" for="octno_od_sup"><span>Sup</span></label>

                    <input class="btn-check" type="checkbox" id="octno_od_inf">
                    <label class="octno-segment inf" for="octno_od_inf"><span>Inf</span></label>

                    <input class="btn-check" type="checkbox" id="octno_od_nas">
                    <label class="octno-segment nas" for="octno_od_nas"><span>Nas</span></label>

                    <input class="btn-check" type="checkbox" id="octno_od_temp">
                    <label class="octno-segment temp" for="octno_od_temp"><span>Temp</span></label>

                    <div class="octno-center">OD</div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="octno-eye-card h-100">
                <h6 class="mb-2 text-muted">Ojo izquierdo (OI)</h6>
                <label class="form-label" for="inputOI">Promedio espesor CFNR (um)</label>
                <input type="number" min="0" max="300" step="1" id="inputOI" class="form-control" inputmode="numeric">
                <div class="small text-muted mt-2 mb-2">Seleccione cuadrantes afectados</div>
                <div class="octno-wheel">
                    <input class="btn-check" type="checkbox" id="octno_oi_sup">
                    <label class="octno-segment sup" for="octno_oi_sup"><span>Sup</span></label>

                    <input class="btn-check" type="checkbox" id="octno_oi_inf">
                    <label class="octno-segment inf" for="octno_oi_inf"><span>Inf</span></label>

                    <input class="btn-check" type="checkbox" id="octno_oi_nas">
                    <label class="octno-segment nas" for="octno_oi_nas"><span>Nas</span></label>

                    <input class="btn-check" type="checkbox" id="octno_oi_temp">
                    <label class="octno-segment temp" for="octno_oi_temp"><span>Temp</span></label>

                    <div class="octno-center">OI</div>
                </div>
            </div>
        </div>
    </div>
</div>
