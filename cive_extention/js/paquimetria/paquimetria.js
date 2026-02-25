document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);
    const inputOD = $('inputOD');
    const inputOI = $('inputOI');

    if (inputOD) inputOD.focus();

    function val(el) {
        return (el && el.value ? el.value : '').toString().trim();
    }

    $('btnAceptar').addEventListener('click', function () {
        const payload = {
            inputOD: val(inputOD),
            inputOI: val(inputOI)
        };

        const OD = payload.inputOD !== ''
            ? 'Espesor corneal central: ' + payload.inputOD + ' micras'
            : '';
        const OI = payload.inputOI !== ''
            ? 'Espesor corneal central: ' + payload.inputOI + ' micras'
            : '';

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
