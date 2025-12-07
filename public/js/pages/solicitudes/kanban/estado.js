import { showToast } from './toast.js';
import { getKanbanConfig } from './config.js';

function normalizarSlug(valor) {
    return (valor ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()
        .replace(/\s+/g, '-')
        .replace(/[^a-z0-9-]/g, '');
}

export function actualizarEstadoSolicitud(
    id,
    formId,
    nuevoEstado,
    solicitudes = [],
    callbackRender = () => {},
    options = {}
) {
    const payload = {
        id: Number.parseInt(id, 10),
        estado: normalizarSlug(nuevoEstado),
        completado: options.completado !== undefined ? Boolean(options.completado) : true,
        force: options.force ? Boolean(options.force) : false,
    };

    if (options.nota) {
        payload.nota = options.nota;
    }

    const { basePath } = getKanbanConfig();

    return fetch(`${basePath}/actualizar-estado`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
        .then(async response => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok || !data.success) {
                const message = data?.error || 'No se pudo actualizar el estado';
                const error = new Error(message);
                error.__estadoNotificado = false;
                throw error;
            }

            const estadoFinal = (data.estado ?? nuevoEstado ?? '').toString();
            const turnoFinal = data.turno ?? null;

            if (Array.isArray(solicitudes)) {
                const encontrada = solicitudes.find(s => String(s.form_id) === String(formId));
                if (encontrada) {
                    encontrada.estado = estadoFinal;
                    encontrada.estado_label = data.estado_label ?? encontrada.estado_label ?? estadoFinal;
                    encontrada.checklist = data.checklist ?? encontrada.checklist;
                    encontrada.checklist_progress = data.checklist_progress ?? encontrada.checklist_progress;
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
            const message = (error?.message || '').toString();
            const shouldRetryForce =
                !payload.force &&
                !options.__forceRetried &&
                /etapas previas/i.test(message);

            if (shouldRetryForce) {
                return actualizarEstadoSolicitud(
                    id,
                    formId,
                    nuevoEstado,
                    solicitudes,
                    callbackRender,
                    { ...options, force: true, __forceRetried: true }
                );
            }

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
