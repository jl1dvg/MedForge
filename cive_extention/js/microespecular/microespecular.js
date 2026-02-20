document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);

    const densidadOD = $('densidadOD');
    const desviacionOD = $('desviacionOD');
    const coefVarOD = $('coefVarOD');
    const densidadOI = $('densidadOI');
    const desviacionOI = $('desviacionOI');
    const coefVarOI = $('coefVarOI');

    if (densidadOD) densidadOD.focus();

    function val(el) {
        return (el && el.value ? el.value : '').toString().trim();
    }

    function buildEyeText(label, densidad, desviacion, coefVar) {
        const lines = [];
        if (densidad !== '') lines.push('Densidad celular: ' + densidad);
        if (desviacion !== '') lines.push('Desviacion standard: ' + desviacion);
        if (coefVar !== '') lines.push('Coeficiente de variacion: ' + coefVar);
        if (!lines.length) return '';
        return label + ':\n' + lines.join('\n');
    }

    $('btnAceptar').addEventListener('click', function () {
        const payload = {
            densidadOD: val(densidadOD),
            desviacionOD: val(desviacionOD),
            coefVarOD: val(coefVarOD),
            densidadOI: val(densidadOI),
            desviacionOI: val(desviacionOI),
            coefVarOI: val(coefVarOI)
        };

        const OD = buildEyeText('OD', payload.densidadOD, payload.desviacionOD, payload.coefVarOD);
        const OI = buildEyeText('OI', payload.densidadOI, payload.desviacionOI, payload.coefVarOI);

        if (!OD && !OI) return;

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
