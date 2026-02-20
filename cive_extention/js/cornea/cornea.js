document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);

    function num(value) {
        const clean = (value || '').toString().trim().replace(',', '.');
        if (!clean) return null;
        const n = Number(clean);
        return Number.isFinite(n) ? n : null;
    }

    function wrapAxis(v) {
        let a = Math.round(v);
        while (a <= 0) a += 180;
        while (a > 180) a -= 180;
        return a;
    }

    function recalc(prefix) {
        const kFlatEl = $('kFlat' + prefix);
        const axisFlatEl = $('axisFlat' + prefix);
        const kSteepEl = $('kSteep' + prefix);
        const axisSteepEl = $('axisSteep' + prefix);
        const cilindroEl = $('cilindro' + prefix);
        const kPromedioEl = $('kPromedio' + prefix);
        if (!kFlatEl || !axisFlatEl || !kSteepEl || !axisSteepEl || !cilindroEl || !kPromedioEl) return;

        const kFlat = num(kFlatEl.value);
        const axisFlat = num(axisFlatEl.value);
        const kSteep = num(kSteepEl.value);

        if (axisFlat !== null) {
            axisSteepEl.value = String(wrapAxis(axisFlat + 90));
        } else {
            axisSteepEl.value = '';
        }

        if (kFlat !== null && kSteep !== null) {
            cilindroEl.value = Math.abs(kSteep - kFlat).toFixed(2);
            kPromedioEl.value = ((kSteep + kFlat) / 2).toFixed(2);
        } else {
            cilindroEl.value = '';
            kPromedioEl.value = '';
        }
    }

    ['OD', 'OI'].forEach(function (prefix) {
        ['kFlat', 'axisFlat', 'kSteep'].forEach(function (field) {
            const el = $(field + prefix);
            if (!el) return;
            el.addEventListener('input', function () {
                recalc(prefix);
            });
        });
        recalc(prefix);
    });

    if ($('kFlatOD')) $('kFlatOD').focus();

    $('btnAceptar').addEventListener('click', function () {
        const payload = {
            kFlatOD: ($('kFlatOD')?.value || '').trim(),
            axisFlatOD: ($('axisFlatOD')?.value || '').trim(),
            kSteepOD: ($('kSteepOD')?.value || '').trim(),
            axisSteepOD: ($('axisSteepOD')?.value || '').trim(),
            cilindroOD: ($('cilindroOD')?.value || '').trim(),
            kPromedioOD: ($('kPromedioOD')?.value || '').trim(),
            kFlatOI: ($('kFlatOI')?.value || '').trim(),
            axisFlatOI: ($('axisFlatOI')?.value || '').trim(),
            kSteepOI: ($('kSteepOI')?.value || '').trim(),
            axisSteepOI: ($('axisSteepOI')?.value || '').trim(),
            cilindroOI: ($('cilindroOI')?.value || '').trim(),
            kPromedioOI: ($('kPromedioOI')?.value || '').trim()
        };

        const hasAny = Object.values(payload).some(function (v) { return (v || '').toString().trim() !== ''; });
        if (!hasAny) return;

        const OD = 'OD: K Flat ' + (payload.kFlatOD || '-') +
            ', Axis ' + (payload.axisFlatOD || '-') +
            ', K Steep ' + (payload.kSteepOD || '-') +
            ', Axis steep ' + (payload.axisSteepOD || '-') +
            ', Cilindro ' + (payload.cilindroOD || '-') +
            ', K Promedio ' + (payload.kPromedioOD || '-');
        const OI = 'OI: K Flat ' + (payload.kFlatOI || '-') +
            ', Axis ' + (payload.axisFlatOI || '-') +
            ', K Steep ' + (payload.kSteepOI || '-') +
            ', Axis steep ' + (payload.axisSteepOI || '-') +
            ', Cilindro ' + (payload.cilindroOI || '-') +
            ', K Promedio ' + (payload.kPromedioOI || '-');

        window.parent.postMessage({OD, OI, payload}, '*');
    });

    $('btnClose').addEventListener('click', function () {
        window.parent.postMessage({close: true}, '*');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            $('btnAceptar').click();
        }
    });
});
