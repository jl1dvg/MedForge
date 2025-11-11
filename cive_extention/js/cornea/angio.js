document.addEventListener('DOMContentLoaded', function () {
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
        });

    document.getElementById('btnAceptar').addEventListener('click', () => {
        const OD = document.getElementById('inputOD').value;
        const OI = document.getElementById('inputOI').value;
        const DLN_OD = document.getElementById('checkboxOD_dln').checked;
        const DLN_OI = document.getElementById('checkboxOI_dln').checked;

        window.parent.postMessage({OD, OI, DLN_OD, DLN_OI}, '*');
    });

    document.getElementById('btnClose').addEventListener('click', () => {
        window.parent.postMessage({OD: '', OI: '', DLN_OD: false, DLN_OI: false}, '*');
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
        currentValues.push(checkbox.value);
    } else {
        const index = currentValues.indexOf(checkbox.value);
        if (index > -1) {
            currentValues.splice(index, 1);
    }
}

    textarea.value = currentValues.join(', ');
}
