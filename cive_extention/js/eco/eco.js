document.addEventListener('DOMContentLoaded', function () {
    // Asumiendo que tu JSON se llama 'checkboxes.json' y está en la misma carpeta
    fetch(chrome.runtime.getURL('js/eco/checkboxes.json'))
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

