document.getElementById('btnAceptar').addEventListener('click', () => {
    window.addEventListener('load', () => {
        const inputOD = document.getElementById('inputOD');
        console.log(inputOD); // Verifica si el elemento se encuentra
        if (inputOD) {
            setTimeout(() => {
                inputOD.focus();
            }, 100);
        }
    });

    const inputOD = document.getElementById('inputOD');
    const inputOI = document.getElementById('inputOI');

    if (!inputOD || !inputOI) {
        console.error('Los elementos inputOD o inputOI no se encontraron.');
        return;
    }

    const OD = parseFloat(inputOD.value);
    const OI = parseFloat(inputOI.value);

    let clasificacionOD = '';
    let clasificacionOI = '';

    // Clasificación para OD
    if (OD >= 35) {
        clasificacionOD = 'IV';
    } else if (OD >= 25 && OD < 35) {
        clasificacionOD = 'III';
    } else if (OD >= 10 && OD < 25) {
        clasificacionOD = 'II';
    } else if (OD > 0 && OD < 10) {
        clasificacionOD = 'I';
    } else if (OD === 0) {
        clasificacionOD = '0';
    }

    // Clasificación para OI
    if (OI >= 35) {
        clasificacionOI = 'IV';
    } else if (OI >= 25 && OI < 35) {
        clasificacionOI = 'III';
    } else if (OI >= 10 && OI < 25) {
        clasificacionOI = 'II';
    } else if (OI > 0 && OI < 10) {
        clasificacionOI = 'I';
    } else if (OI === 0) {
        clasificacionOI = '0';
    }

    // Evaluación basada en la clasificación
    const evaluacion = (clasificacion) => {
        switch (clasificacion) {
            case 'IV':
                return 'muy amplio';
            case 'III':
                return 'amplio';
            case 'II':
                return 'estrecho';
            case 'I':
                return 'muy estrecho';
            case '0':
                return 'cerrado';
            default:
                return '';
        }
    };

    const evaluacionOD = evaluacion(clasificacionOD);
    const evaluacionOI = evaluacion(clasificacionOI);

    // Crear un mensaje para OD
    const ODMessage = `OD: SE APRECIAN ÁNGULOS IRIDOCORNEALES CON ${OD} GRADOS DE APERTURA ANGULAR COMPATIBLES CON CLASIFICACIÓN GRADO ${clasificacionOD} DE LA ESCALA DE SHAFFER.\nCONCLUSIÓN: ÁNGULOS IRIDOCORNEALES ${evaluacionOD}S. SE RECOMIENDA CORRELACIONAR IMÁGENES CON ESTUDIO GONIOSCÓPICO.`;

    // Crear un mensaje para OI
    const OIMessage = `OI: SE APRECIAN ÁNGULOS IRIDOCORNEALES CON ${OI} GRADOS DE APERTURA ANGULAR COMPATIBLES CON CLASIFICACIÓN GRADO ${clasificacionOI} DE LA ESCALA DE SHAFFER.\nCONCLUSIÓN: ÁNGULOS IRIDOCORNEALES ${evaluacionOI}S. SE RECOMIENDA CORRELACIONAR IMÁGENES CON ESTUDIO GONIOSCÓPICO.`;

    // Enviar los valores al padre
    window.parent.postMessage({OD: ODMessage, OI: OIMessage}, '*');
});

document.getElementById('btnClose').addEventListener('click', () => {
    window.parent.postMessage({close: true}, '*'); // Mensaje para cerrar el popup
});

document.addEventListener('keydown', (event) => {
    if (event.key === 'Enter') {
        document.getElementById('btnAceptar').click(); // Simular clic en "Aceptar"
    }
});
