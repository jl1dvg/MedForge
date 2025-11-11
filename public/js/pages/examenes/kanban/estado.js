import { showToast } from './toast.js';

export function actualizarEstadoExamen(id, formId, nuevoEstado, examenes = [], callbackRender = () => {}) {
    if (Array.isArray(examenes)) {
        const encontrada = examenes.find(s => String(s.form_id) === String(formId));
        if (encontrada) {
            encontrada.estado = nuevoEstado;
        }
    }

    const payload = {
        id: Number.parseInt(id, 10),
        estado: nuevoEstado,
    };

    return fetch('/examenes/actualizar-estado', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
        .then(response => {
            if (!response.ok) {
                throw new Error('Respuesta no válida del servidor');
            }
            return response.json();
        })
        .then(data => {
            if (!data.success) {
                throw new Error(data.error || 'No se pudo actualizar el estado');
            }

            showToast('✅ Estado actualizado correctamente');
            if (typeof callbackRender === 'function') {
                callbackRender();
            }
            return data;
        })
        .catch(error => {
            console.error('❌ Error al actualizar estado:', error);
            showToast('❌ No se pudo actualizar el estado', false);
            throw error;
        });
}
