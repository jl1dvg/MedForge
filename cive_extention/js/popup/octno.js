document.getElementById('inputOD').focus();
document.getElementById('btnAceptar').addEventListener('click', () => {
    const inputOD = document.getElementById('inputOD');
    const inputOI = document.getElementById('inputOI');

    // Si ambos inputs están vacíos, no hacer nada
    if (!inputOD.value && !inputOI.value) return;

    // Continuar si alguno de los inputs tiene valor
    const ODvalue = inputOD.value.trim();
    const OIvalue = inputOI.value.trim();

    // Crear mensajes solo si hay valores o marcadores de cuadrantes
    let ODMessage = '', OIMessage = '';
    if (ODvalue || document.querySelectorAll('#checkboxI:checked, #checkboxS:checked, #checkboxN:checked, #checkboxT:checked').length) {
        ODMessage = construirMensaje('OD', ODvalue);
    }
    if (OIvalue || document.querySelectorAll('#checkboxI_OI:checked, #checkboxS_OI:checked, #checkboxN_OI:checked, #checkboxT_OI:checked').length) {
        OIMessage = construirMensaje('OI', OIvalue);
    }

    // Enviar los mensajes si existen
    if (ODMessage || OIMessage) {
        window.parent.postMessage({OD: ODMessage, OI: OIMessage}, '*');
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
