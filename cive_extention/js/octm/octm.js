document.addEventListener('DOMContentLoaded', function () {
    // Focar en el primer input directamente
    document.getElementById('inputOD').focus();

    // Asumiendo que tu JSON se llama 'checkboxes.json' y está en la misma carpeta
    fetch(chrome.runtime.getURL('js/octm/checkboxes.json'))
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
        const CTMOD = document.getElementById('inputOD').value;
        const CTMOI = document.getElementById('inputOI').value;
        const textOD = document.getElementById('textOD').value;
        const textOI = document.getElementById('textOI').value;

        let OD = '';
        let OI = '';

        if (CTMOD && textOD) {
            OD = `GROSOR FOVEAL PROMEDIO OD: ${CTMOD}um\nLAS IMÁGENES SON SUGESTIVAS DE:\nOD: ${textOD}`;
        } else if (CTMOD && !textOD) {
            OD = `GROSOR FOVEAL PROMEDIO OD: ${CTMOD}um\nLAS IMÁGENES SON SUGESTIVAS DE:\nOD: Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.`;
        } else if (!CTMOD && textOD) {
            OD = `LAS IMÁGENES SON SUGESTIVAS DE:\nOD: ${textOD}`;
        }

        if (CTMOI && textOI) {
            OI = `GROSOR FOVEAL PROMEDIO OI: ${CTMOI}um\nLAS IMÁGENES SON SUGESTIVAS DE:\nOI: ${textOI}`;
        } else if (CTMOI && !textOI) {
            OI = `GROSOR FOVEAL PROMEDIO OI: ${CTMOI}um\nLAS IMÁGENES SON SUGESTIVAS DE:\nOI: Arquitectura retiniana bien definida, fóvea con depresión central bien delineada, epitelio pigmentario continuo y uniforme, membrana limitante interna es hiporreflectiva y continua, células de Müller están bien alineadas sin signos de edema o tracción.`;
        } else if (!CTMOI && textOI) {
            OI = `LAS IMÁGENES SON SUGESTIVAS DE:\nOI: ${textOI}`;
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
    checkbox.checked = false; // Checkbox no seleccionado por defecto

    checkbox.addEventListener('change', function () {
        updateTextarea(`text${eye}`, this);
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

    if (checkbox.checked) {
        if (currentValues.length === 0) {
            textarea.value = `${checkbox.value}`;
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

