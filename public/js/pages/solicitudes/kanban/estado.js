import { showToast } from './toast.js';
import { getKanbanConfig } from './config.js';

export function actualizarEstadoSolicitud(id, formId, nuevoEstado, solicitudes = [], callbackRender = () => {}) {
    const payload = {
        id: Number.parseInt(id, 10),
        estado: nuevoEstado,
    };

    const { basePath } = getKanbanConfig();

    return fetch(`${basePath}/actualizar-estado`, {
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

            const estadoFinal = (data.estado ?? nuevoEstado ?? '').toString();
            const turnoFinal = data.turno ?? null;

            if (Array.isArray(solicitudes)) {
                const encontrada = solicitudes.find(s => String(s.form_id) === String(formId));
                if (encontrada) {
                    encontrada.estado = estadoFinal;
                    if (turnoFinal !== undefined) {
                        encontrada.turno = turnoFinal;
                    }
                }
            }

            showToast('✅ Estado actualizado correctamente');

            if (typeof callbackRender === 'function') {
                try {
                    const resultado = callbackRender();
                    if (resultado && typeof resultado.catch === 'function') {
                        resultado.catch(err => {
                            console.error('⚠️ Error al refrescar el tablero de solicitudes:', err);
                        });
                    }
                } catch (callbackError) {
                    console.error('⚠️ Error al refrescar el tablero de solicitudes:', callbackError);
                }
            }

            return data;
        })
        .catch(error => {
            console.error('❌ Error al actualizar estado:', error);
            const mensaje = error?.message || 'No se pudo actualizar el estado';
            showToast(`❌ ${mensaje.replace(/^❌\s*/, '')}`, false);

            if (error && typeof error === 'object') {
                error.__estadoNotificado = true;
                throw error;
            }

            const wrapped = new Error(mensaje);
            wrapped.__estadoNotificado = true;
            throw wrapped;
        });
}
