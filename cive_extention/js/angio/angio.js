document.addEventListener('DOMContentLoaded', function () {
    // Focar en el primer input directamente
    document.getElementById('inputOD').focus();

    // Asumiendo que tu JSON se llama 'checkboxes.json' y está en la misma carpeta
    fetch(chrome.runtime.getURL('js/angio/checkboxes.json'))
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
        const OD = document.getElementById('inputOD').value;
        const OI = document.getElementById('inputOI').value;

        window.parent.postMessage({
            OD,
            OI,
            payload: {
                inputOD: OD.trim(),
                inputOI: OI.trim()
            }
        }, '*');
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

    let newText = textarea.value;

    if (checkbox.checked) {
        // Agregar el valor del checkbox al final del textarea
        if (newText.trim().length > 0) {
            newText += ', ';
        }
        newText += checkbox.value;
    } else {
        // Eliminar el valor del checkbox del textarea
        const textToRemove = checkbox.value;
        const regex = new RegExp(`,? ?${textToRemove}`, 'g');
        newText = newText.replace(regex, '').trim();

        // Eliminar cualquier coma sobrante al inicio o final del texto
        if (newText.startsWith(',')) {
            newText = newText.substring(1).trim();
        }
        if (newText.endsWith(',')) {
            newText = newText.slice(0, -1).trim();
        }
    }

    textarea.value = newText;
}

