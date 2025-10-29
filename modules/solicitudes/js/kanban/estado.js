// js/kanban/estado.js
import { showToast } from './toast.js';

export function actualizarEstadoSolicitud(id, formId, nuevoEstado, solicitudes, callbackRender) {
    const solicitud = solicitudes.find(s => s.form_id === formId);
    if (solicitud) solicitud.estado = nuevoEstado;

    fetch('actualizar_estado.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ id, estado: nuevoEstado })
    })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                showToast('✅ Estado actualizado correctamente');
                callbackRender();
            } else {
                showToast('❌ Error en la actualización');
            }
        })
        .catch(err => {
            console.error(err);
            showToast('❌ Error de red');
        });
}