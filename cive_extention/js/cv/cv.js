document.addEventListener('DOMContentLoaded', function () {
    // Focar en el primer input directamente
    document.getElementById('inputOD').focus();

    // Asumiendo que tu JSON se llama 'checkboxes.json' y está en la misma carpeta
    fetch(chrome.runtime.getURL('js/cv/checkboxes.json'))
        .then(response => response.json())
        .then(data => {
            const checkboxContainerOD = document.getElementById('checkboxContainerOD');
            const checkboxContainerOI = document.getElementById('checkboxContainerOI');

            data.forEach(item => {
                // Crear checkbox para OD
                const checkboxOD = createCheckbox(item, 'OD');
                checkboxContainerOD.appendChild(checkboxOD);

                // Crear checkbox para OI
                const checkboxOI = createCheckbox(item, 'OI');
                checkboxContainerOI.appendChild(checkboxOI);
            });

            // Focar en el primer input directamente después de cargar los checkbox
            document.getElementById('inputOD').focus();
        });

    document.getElementById('btnAceptar').addEventListener('click', () => {
        const textOD = document.getElementById('inputOD').value;
        const textOI = document.getElementById('inputOI').value;
        const DLN_OD = document.getElementById('checkboxOD_dln').checked;
        const DLN_OI = document.getElementById('checkboxOI_dln').checked;
        const Amaurosis_OD = document.getElementById('checkboxOD_amaurosis').checked;
        const Amaurosis_OI = document.getElementById('checkboxOI_amaurosis').checked;

        let OD = '';
        let OI = '';

        if (Amaurosis_OD) {
            OD = `OJO: DERECHO\nSE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.\nESTRATEGIA: 24.2 DINÁMICO\nCONFIABILIDAD: BUENA\nSENSIBILIDAD FOVEAL: NULA\nLECTURA: ${textOD}\nCONCLUSIONES: CAMPO VISUAL AMAUROTICO`;
        } else if (DLN_OD) {
            OD = `OJO: DERECHO\nSE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.\nESTRATEGIA: 24.2 DINÁMICO\nCONFIABILIDAD: BUENA\nSENSIBILIDAD FOVEAL: ACTIVA\nCONCLUSIONES: CAMPO VISUAL DENTRO DE LIMITES NORMALES\n\nSE RECOMIENDA CORRELACIONAR CON CLÍNICA.`;
        } else if (textOD) {
            OD = `OJO: DERECHO\nSE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.\nESTRATEGIA: 24.2 DINÁMICO\nCONFIABILIDAD: BUENA\nSENSIBILIDAD FOVEAL: ACTIVA\nLECTURA: ${textOD}\nCONCLUSIONES: CAMPO VISUAL FUERA DE LIMITES NORMALES`;
        }

        if (Amaurosis_OI) {
            OI = `OJO: IZQUIERDO\nSE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.\nESTRATEGIA: 24.2 DINÁMICO\nCONFIABILIDAD: BUENA\nSENSIBILIDAD FOVEAL: NULA\nLECTURA: ${textOI}\nCONCLUSIONES: CAMPO VISUAL AMAUROTICO`;
        } else if (DLN_OI) {
            OI = `OJO: IZQUIERDO\nSE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.\nESTRATEGIA: 24.2 DINÁMICO\nCONFIABILIDAD: BUENA\nSENSIBILIDAD FOVEAL: ACTIVA\nCONCLUSIONES: CAMPO VISUAL DENTRO DE LIMITES NORMALES\n\nSE RECOMIENDA CORRELACIONAR CON CLÍNICA.`;
        } else if (textOI) {
            OI = `OJO: IZQUIERDO\nSE REALIZA CAMPO VISUAL OCTOPUS 600 IMPRESIÓN HFA.\nESTRATEGIA: 24.2 DINÁMICO\nCONFIABILIDAD: BUENA\nSENSIBILIDAD FOVEAL: ACTIVA\nLECTURA: ${textOI}\nCONCLUSIONES: CAMPO VISUAL FUERA DE LIMITES NORMALES`;
        }

        window.parent.postMessage({OD, OI}, '*');
    });

    document.getElementById('btnClose').addEventListener('click', () => {
        window.parent.postMessage({close: true}, '*'); // Mensaje para cerrar el popup
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            document.getElementById('btnAceptar').click(); // Simular clic en "Aceptar"
        }
    });
});

function createCheckbox(item, eye) {
    const checkbox = document.createElement('input');
    checkbox.type = 'checkbox';
    checkbox.id = `checkbox${eye}_${item.id}`;
    checkbox.value = item.text;
    checkbox.checked = false; // Checkbox seleccionado por defecto

    checkbox.addEventListener('change', function () {
        updateTextarea(`input${eye}`, this);
    });

    const label = document.createElement('label');
    label.htmlFor = checkbox.id;
    label.appendChild(document.createTextNode(item.label));

    const container = document.createElement('div');
    container.appendChild(checkbox);
    container.appendChild(label);

    return container;
}

function updateTextarea(textareaId, checkbox) {
    const textarea = document.getElementById(textareaId);
    const currentValues = textarea.value.split(',').map(item => item.trim()).filter(Boolean);

    if (checkbox.id.includes('dln')) {
        return; // No hacer nada si el checkbox es DLN
    }

    if (checkbox.checked) {
        if (currentValues.length === 0) {
            textarea.value = `SE APRECIAN PUNTOS DE DISMINUCION DE LA SENSIBILIDAD RETINIANA DE BAJA, MEDIA Y ALTA SIGNIFICANCIA QUE CONFORMAN ESCOTOMA CON PATRON ${checkbox.value}`;
        } else {
            currentValues.push(checkbox.value);
            textarea.value = currentValues.join(', ');
        }
    } else {
        const index = currentValues.indexOf(checkbox.value);
        if (index > -1) {
            currentValues.splice(index, 1);
            textarea.value = currentValues.join(', ');
        }
    }
}
