document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);
    const textOD = $('textOD');
    const textOI = $('textOI');

    if (textOD) {
        textOD.focus();
    }

    function valueOf(el) {
        return (el && typeof el.value === 'string' ? el.value : '').trim();
    }

    $('btnAceptar').addEventListener('click', function () {
        const payload = {
            textOD: valueOf(textOD),
            textOI: valueOf(textOI)
        };

        const OD = payload.textOD;
        const OI = payload.textOI;

        if (!OD && !OI) {
            return;
        }

        window.parent.postMessage({OD, OI, payload}, '*');
    });

    $('btnClose').addEventListener('click', function () {
        window.parent.postMessage({close: true}, '*');
    });

    document.addEventListener('keydown', function (event) {
        if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
            event.preventDefault();
            $('btnAceptar').click();
        }
    });
});
