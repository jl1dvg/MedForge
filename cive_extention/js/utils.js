// utils.js

export function cargarJSON(url) {
    return fetch(url)
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        });
}

export function ejecutarTecnicos(item) {
    if (!Array.isArray(item.tecnicos)) return Promise.resolve();

    return item.tecnicos.reduce((promise, tecnico) => {
        return promise.then(() => {
            return hacerClickEnSelect2(tecnico.selector)
                .then(() => establecerBusqueda(tecnico.nombre))
                .then(() => seleccionarOpcion())
                .then(() => hacerClickEnSelect2(tecnico.trabajador))
                .then(() => establecerBusqueda(tecnico.nombre))
                .then(() => seleccionarOpcion())
                .catch(error => console.error(`Error procesando técnico ${tecnico.nombre}:`, error));
        });
    }, Promise.resolve());
}

// Definir la función ejecutarCodigos si es necesaria y exportarla
export function ejecutarCodigos() {
    // Implementar la lógica de ejecutarCodigos aquí si es necesario
}

// (Otras funciones utilitarias aquí)
export function llenarCampoTexto(selector, valor) {
    return new Promise((resolve, reject) => {
        const textArea = document.querySelector(selector);
        if (textArea) {
            textArea.value = valor;
            setTimeout(resolve, 100);
        } else {
            reject(`El campo de texto "${selector}" no se encontró.`);
        }
    });
}

export function hacerClickEnBoton(selector, numeroDeClicks) {
    return new Promise((resolve, reject) => {
        const botonPlus = document.querySelector(selector);
        if (botonPlus) {
            let clicks = 0;

            function clickBoton() {
                if (clicks < numeroDeClicks) {
                    botonPlus.click();
                    clicks++;
                    setTimeout(clickBoton, 100);
                } else {
                    resolve();
                }
            }

            clickBoton();
        } else {
            reject(`El botón "${selector}" no se encontró.`);
        }
    });
}

export function hacerClickEnSelect2(selector) {
    return new Promise((resolve, reject) => {
        const tecnicoContainer = document.querySelector(selector);
        if (tecnicoContainer) {
            const event = new MouseEvent('mousedown', {
                view:
                window,
                bubbles: true,
                cancelable: true
            });
            tecnicoContainer.dispatchEvent(event);
            setTimeout(resolve, 100);
        } else {
            reject(`El contenedor "${selector}" no se encontró.`);
        }
    });
}

export function establecerBusqueda(nombre) {
    return new Promise((resolve, reject) => {
        const searchBox = document.querySelector('.select2-search__field');
        if (searchBox) {
            searchBox.value = nombre;
            searchBox.dispatchEvent(new Event('input', {bubbles: true}));
            setTimeout(resolve, 200);
        } else {
            reject('El campo de búsqueda no se encontró.');
        }
    });
}

export function seleccionarOpcion() {
    return new Promise((resolve, reject) => {
        const firstOption = document.querySelector('.select2-results__option--highlighted');
        if (firstOption) {
            firstOption.dispatchEvent(new MouseEvent('mouseup', {
                view: window,
                bubbles: true,
                cancelable: true
            }));
            setTimeout(resolve, 100);
        } else {
            reject('No se encontró ninguna opción destacada para seleccionar.');
        }
    });
}

export function seleccionarRadioNo() {
    return new Promise((resolve, reject) => {
        const radioInput = document.getElementById('consulta-requiereentrega-no');
        if (radioInput) {
            radioInput.checked = true;
            setTimeout(resolve, 100);
        } else {
            reject('El radio button no se encontró.');
        }
    });
}