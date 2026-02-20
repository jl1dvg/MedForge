document.getElementById('inputOD').focus();
document.getElementById('btnAceptar').addEventListener('click', () => {
    const inputOD = document.getElementById('inputOD');
    const inputOI = document.getElementById('inputOI');
    const odFlags = {
        inf: Boolean(document.getElementById('checkboxI')?.checked),
        sup: Boolean(document.getElementById('checkboxS')?.checked),
        nas: Boolean(document.getElementById('checkboxN')?.checked),
        temp: Boolean(document.getElementById('checkboxT')?.checked)
    };
    const oiFlags = {
        inf: Boolean(document.getElementById('checkboxI_OI')?.checked),
        sup: Boolean(document.getElementById('checkboxS_OI')?.checked),
        nas: Boolean(document.getElementById('checkboxN_OI')?.checked),
        temp: Boolean(document.getElementById('checkboxT_OI')?.checked)
    };

    const ODvalue = inputOD.value.trim();
    const OIvalue = inputOI.value.trim();
    const hasOdFlags = Object.values(odFlags).some(Boolean);
    const hasOiFlags = Object.values(oiFlags).some(Boolean);

    if (!ODvalue && !OIvalue && !hasOdFlags && !hasOiFlags) return;

    let ODMessage = '', OIMessage = '';
    if (ODvalue || hasOdFlags) {
        ODMessage = construirMensaje('OD', ODvalue);
    }
    if (OIvalue || hasOiFlags) {
        OIMessage = construirMensaje('OI', OIvalue);
    }

    if (ODMessage || OIMessage) {
        window.parent.postMessage({
            OD: ODMessage,
            OI: OIMessage,
            payload: {
                inputOD: ODvalue,
                inputOI: OIvalue,
                checkboxI: odFlags.inf,
                checkboxS: odFlags.sup,
                checkboxN: odFlags.nas,
                checkboxT: odFlags.temp,
                checkboxI_OI: oiFlags.inf,
                checkboxS_OI: oiFlags.sup,
                checkboxN_OI: oiFlags.nas,
                checkboxT_OI: oiFlags.temp,
                octno_od_inf: odFlags.inf,
                octno_od_sup: odFlags.sup,
                octno_od_nas: odFlags.nas,
                octno_od_temp: odFlags.temp,
                octno_oi_inf: oiFlags.inf,
                octno_oi_sup: oiFlags.sup,
                octno_oi_nas: oiFlags.nas,
                octno_oi_temp: oiFlags.temp
            }
        }, '*');
    }
});

function construirMensaje(ojo, valor) {
    const esOD = ojo === 'OD';
    const baseId = esOD ? '' : '_OI';

    const cuadrantes = [
        {id: 'I', desc: 'INFERIOR'},
        {id: 'S', desc: 'SUPERIOR'},
        {id: 'N', desc: 'NASAL'},
        {id: 'T', desc: 'TEMPORAL'}
    ].map(q => {
        const checkbox = document.getElementById(`checkbox${q.id}${baseId}`);
        return checkbox && checkbox.checked ? q.desc : '';
    }).filter(Boolean).join(', ');

    let clasificacion = 'AL BORDE DE LIMITES NORMALES';
    if (valor >= 85) clasificacion = 'DENTRO DE LIMITES NORMALES';
    else if (cuadrantes) clasificacion = 'FUERA DE LIMITES NORMALES';

    let defecto = '';
    if (cuadrantes) {
        defecto = `SE APRECIA DISMINUCIÓN DEL ESPESOR DE CAPA DE FIBRAS NERVIOSAS RETINALES EN CUADRANTES ${cuadrantes}.\n`;
    }

    return `${ojo === 'OD' ? 'OJO DERECHO' : 'OJO IZQUIERDO'}\nCONFIABILIDAD: BUENA\n${defecto}PROMEDIO ESPESOR CFNR ${ojo}: ${valor}UM\nCLASIFICACIÓN: ${clasificacion}`;
}

document.getElementById('btnClose').addEventListener('click', () => {
    window.parent.postMessage({close: true}, '*'); // Mensaje para cerrar el popup
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        document.getElementById('btnAceptar').click(); // Simular clic en "Aceptar"
    }
});
