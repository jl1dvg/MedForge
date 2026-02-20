document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);

    const ids = ['camaraOD', 'cristalinoOD', 'axialOD', 'camaraOI', 'cristalinoOI', 'axialOI'];
    if ($('camaraOD')) $('camaraOD').focus();

    function getValue(id) {
        return ($(id)?.value || '').toString().trim();
    }

    function buildEye(label, camara, cristalino, axial) {
        const lines = [];
        if (camara !== '') lines.push('Camara anterior: ' + camara);
        if (cristalino !== '') lines.push('Cristalino: ' + cristalino);
        if (axial !== '') lines.push('Longitud axil: ' + axial);
        if (!lines.length) return '';
        return label + ':\n' + lines.join('\n');
    }

    $('btnAceptar').addEventListener('click', function () {
        const payload = {};
        ids.forEach(function (id) {
            payload[id] = getValue(id);
        });

        const OD = buildEye('OD', payload.camaraOD, payload.cristalinoOD, payload.axialOD);
        const OI = buildEye('OI', payload.camaraOI, payload.cristalinoOI, payload.axialOI);
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
