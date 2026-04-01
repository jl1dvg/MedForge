document.addEventListener('DOMContentLoaded', function () {
    const $ = (id) => document.getElementById(id);

    function val(id) {
        const el = $(id);
        return (el && el.value ? el.value : '').toString().trim();
    }

    function toNumber(value) {
        const normalized = (value || '').toString().trim().replace(',', '.');
        if (!normalized) return null;
        const n = Number(normalized);
        return Number.isFinite(n) ? n : null;
    }

    function formatNumber(value, withSign) {
        if (value === null || !Number.isFinite(value)) {
            return '';
        }
        const sign = withSign && value > 0 ? '+' : '';
        return sign + value.toFixed(2);
    }

    function computeEye(eye) {
        const paquimetria = toNumber(val('paquimetria' + eye));
        const pioMedida = toNumber(val('pioMedida' + eye));

        if (paquimetria === null) {
            $('compensacion' + eye).value = '';
            $('ajuste' + eye).value = '';
            $('pioCompensada' + eye).value = '';
            return {
                paquimetria: '',
                pioMedida: val('pioMedida' + eye),
                compensacion: '',
                ajuste: '',
                pioCompensada: ''
            };
        }

        const delta = -(((paquimetria - 540) / 10) * 0.7);
        let ajuste = 'Sin ajuste';
        if (delta > 0.0001) {
            ajuste = 'Aumentar ' + delta.toFixed(2) + ' mmHg';
        } else if (delta < -0.0001) {
            ajuste = 'Disminuir ' + Math.abs(delta).toFixed(2) + ' mmHg';
        }

        const compensacion = formatNumber(delta, true);
        const pioCompensada = pioMedida === null ? '' : formatNumber(pioMedida + delta, false);

        $('compensacion' + eye).value = compensacion;
        $('ajuste' + eye).value = ajuste;
        $('pioCompensada' + eye).value = pioCompensada;

        return {
            paquimetria: val('paquimetria' + eye),
            pioMedida: val('pioMedida' + eye),
            compensacion,
            ajuste,
            pioCompensada
        };
    }

    function updateAll() {
        computeEye('OD');
        computeEye('OI');
    }

    ['paquimetriaOD', 'pioMedidaOD', 'paquimetriaOI', 'pioMedidaOI'].forEach(function (id) {
        const el = $(id);
        if (el) {
            el.addEventListener('input', updateAll);
        }
    });

    const inputStart = $('paquimetriaOD');
    if (inputStart) inputStart.focus();
    updateAll();

    $('btnAceptar').addEventListener('click', function () {
        const od = computeEye('OD');
        const oi = computeEye('OI');

        if (!od.paquimetria && !oi.paquimetria && !od.pioMedida && !oi.pioMedida) {
            return;
        }

        const payload = {
            paquimetriaOD: od.paquimetria,
            pioMedidaOD: od.pioMedida,
            compensacionOD: od.compensacion,
            ajusteOD: od.ajuste,
            pioCompensadaOD: od.pioCompensada,
            paquimetriaOI: oi.paquimetria,
            pioMedidaOI: oi.pioMedida,
            compensacionOI: oi.compensacion,
            ajusteOI: oi.ajuste,
            pioCompensadaOI: oi.pioCompensada
        };

        const lines = ['SE REALIZA CALCULO DE PIO COMPENSADA.'];

        if (od.paquimetria || od.pioMedida || od.compensacion || od.pioCompensada) {
            lines.push('');
            lines.push('OD:');
            if (od.paquimetria) lines.push('Paquimetria central: ' + od.paquimetria + ' micras');
            if (od.pioMedida) lines.push('PIO medida: ' + od.pioMedida + ' mmHg');
            if (od.compensacion) lines.push('Compensacion estimada: ' + od.compensacion + ' mmHg');
            if (od.ajuste) lines.push('Ajuste sugerido: ' + od.ajuste);
            if (od.pioCompensada) lines.push('PIO compensada: ' + od.pioCompensada + ' mmHg');
        }

        if (oi.paquimetria || oi.pioMedida || oi.compensacion || oi.pioCompensada) {
            lines.push('');
            lines.push('OI:');
            if (oi.paquimetria) lines.push('Paquimetria central: ' + oi.paquimetria + ' micras');
            if (oi.pioMedida) lines.push('PIO medida: ' + oi.pioMedida + ' mmHg');
            if (oi.compensacion) lines.push('Compensacion estimada: ' + oi.compensacion + ' mmHg');
            if (oi.ajuste) lines.push('Ajuste sugerido: ' + oi.ajuste);
            if (oi.pioCompensada) lines.push('PIO compensada: ' + oi.pioCompensada + ' mmHg');
        }

        window.parent.postMessage({
            OD: lines.join('\n'),
            OI: '',
            payload
        }, '*');
    });

    $('btnClose').addEventListener('click', function () {
        window.parent.postMessage({close: true}, '*');
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Enter' && !event.shiftKey && !event.ctrlKey && !event.altKey && !event.metaKey) {
            event.preventDefault();
            $('btnAceptar').click();
        }
    });
});
