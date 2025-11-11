window.iniciarAnalisisOcuDx = function () {
    const campoExamen = document.querySelector('textarea#consultas-fisico-0-observacion');
    if (campoExamen) {
        const texto = campoExamen.value.trim();
        if (texto.length > 0) {
            fetch('https://asistentecive.consulmed.me/api/sugerencia/sugerir_diagnosticos.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({examen_fisico: texto})
            })
                .then(response => response.text())
                .then(data => {
                    console.log("Respuesta cruda:", data);
                    try {
                        const jsonStart = data.indexOf('{');
                        const jsonString = data.slice(jsonStart);
                        const parsed = JSON.parse(jsonString);
                        if (parsed.success && parsed.sugerencias.length > 0) {
                            const contenido = parsed.sugerencias.map(s =>
                                `<div><b>${s.dx_code}</b> – ${s.descripcion}</div>`
                            ).join('');
                            Swal.fire({
                                toast: true,
                                position: 'top-end',
                                icon: 'warning',
                                title: '⚠️ Diagnósticos sugeridos',
                                html: `<div style="font-size: 1.7em;">${contenido}</div>`,
                                background: '#ffeeba',
                                color: '#856404',
                                showConfirmButton: false,
                                timer: 8000,
                                timerProgressBar: true
                            });
                        }
                    } catch (e) {
                        console.error('❌ Error al parsear JSON:', e);
                    }
                })
                .catch(err => {
                    console.error('❌ Error al analizar:', err);
                });
        }
    }
};

// Analizar en tiempo real con debounce
let timeoutId = null;

function analizarEnTiempoReal() {
    const campo = document.querySelector('textarea#consultas-fisico-0-observacion');
    if (campo) {
        campo.addEventListener('input', () => {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => {
                window.iniciarAnalisisOcuDx();
            }, 1000); // Espera 1s después de dejar de escribir
        });
    }
}

analizarEnTiempoReal();

const botonGuardar = document.querySelector('#botonGuardar');
if (botonGuardar) {
    botonGuardar.addEventListener("click", () => {
        if (window.iniciarAnalisisOcuDx) {
            window.iniciarAnalisisOcuDx();
        }
    });
}