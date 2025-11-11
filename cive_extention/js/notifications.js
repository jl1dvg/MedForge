// Función para detectar el número de HC
function detectarHC() {
    const targetNode = document.querySelector('.media-body');

    if (!targetNode) {
        console.log("No se encontró el contenedor esperado para el número de HC.");
        return;
    }

    const paragraphs = targetNode.querySelectorAll('p.text-muted');
    for (const p of paragraphs) {
        if (p.textContent.includes("HC #:")) {
            const hcNumber = p.textContent.split('HC #:')[1].trim();
            console.log('HC detectado:', hcNumber);

            // Mostrar el popover junto al botón flotante
            mostrarPopover(hcNumber);

            // Agregar notificación al popup flotante
            agregarNotificacion(`HC detectada: ${hcNumber}`);

            clearInterval(intervalID); // Detener la búsqueda continua
            return;
        }
    }
}

// Función para agregar la notificación en el popup flotante
function agregarNotificacion(mensaje) {
    const contenedorNotificaciones = document.getElementById('contenedorNotificaciones');
    const notificacion = document.createElement('div');
    notificacion.className = 'notificacion';
    notificacion.textContent = mensaje;
    contenedorNotificaciones.appendChild(notificacion);
}

// Función para mostrar el popover con el número de HC
function mostrarPopover(hcNumber) {
    const popover = document.createElement('div');
    popover.className = 'popover-hc';
    popover.textContent = `HC Detectada: ${hcNumber}`;
    popover.style.position = 'absolute';
    popover.style.top = '50px';
    popover.style.left = '100px';
    popover.style.backgroundColor = '#ff5c00';
    popover.style.color = '#fff';
    popover.style.borderLeft = '0.25rem solid #802e00';
    popover.style.padding = '10px';
    popover.style.borderRadius = '5px';
    popover.style.boxShadow = '0px 4px 6px rgba(0,0,0,0.1)';

    document.body.appendChild(popover);

    setTimeout(() => {
        popover.remove();
    }, 5000);
}

// Intentar detectar el HC cada 1 segundo
const intervalID = setInterval(detectarHC, 1000);

// Detener la búsqueda si pasan más de 15 segundos
setTimeout(() => {
    clearInterval(intervalID);
}, 1000);