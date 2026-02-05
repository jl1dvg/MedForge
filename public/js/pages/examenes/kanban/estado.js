import { showToast } from './toast.js';

const slugifyEstado = value => {
    const raw = (value ?? '')
        .toString()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');

    if (
        raw === 'revision-de-cobertura'
        || raw === 'revision-cobertura'
        || raw === 'revision-de-codigos'
        || raw === 'revision-codigos'
    ) {
        return 'revision-cobertura';
    }

    return raw;
};

export function actualizarEstadoExamen(
    id,
    formId,
    nuevoEstado,
    examenes = [],
    callbackRender = () => {},
    options = {}
) {
    const estadoLabel = (nuevoEstado ?? '').toString();
    const estadoSlug = (options && options.estado_slug) || slugifyEstado(estadoLabel);

    if (Array.isArray(examenes)) {
        const encontrada =
            examenes.find(s => String(s.form_id) === String(formId)) ||
            examenes.find(s => String(s.id) === String(id));

        if (encontrada) {
            encontrada.estado = estadoLabel || encontrada.estado;
            encontrada.estado_label = estadoLabel || encontrada.estado_label || estadoLabel;
            if (estadoSlug) {
                encontrada.kanban_estado = estadoSlug;
                encontrada.kanban_estado_label =
                    encontrada.kanban_estado_label || estadoLabel || encontrada.estado;
            }
        }
    }

    const payload = {
        id: Number.parseInt(id, 10),
        estado: estadoLabel || estadoSlug,
        estado_slug: estadoSlug,
        etapa: estadoLabel || estadoSlug,
        etapa_slug: estadoSlug,
        completado: options.completado !== undefined ? Boolean(options.completado) : true,
        force: options.force ? Boolean(options.force) : false,
    };

    if (formId !== undefined && formId !== null && formId !== '') {
        payload.form_id = formId;
    }

    if (options.nota) {
        payload.nota = options.nota;
    }

    return fetch('/examenes/actualizar-estado', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
    })
        .then(async response => {
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const message = data?.error || data?.message || 'Respuesta no válida del servidor';
                throw new Error(message);
            }
            if (!data.success) {
                throw new Error(data.error || 'No se pudo actualizar el estado');
            }
            return data;
        })
        .then(data => {
            const estadoRespuesta = (data.estado ?? estadoLabel ?? '').toString();
            const estadoRespuestaLabel = (data.estado_label ?? data.kanban_estado_label ?? estadoRespuesta).toString();
            const estadoRespuestaSlug = slugifyEstado(data.estado_slug ?? data.kanban_estado ?? estadoRespuesta);

            if (Array.isArray(examenes)) {
                const encontrada =
                    examenes.find(s => String(s.form_id) === String(formId)) ||
                    examenes.find(s => String(s.id) === String(id));

                if (encontrada) {
                    encontrada.estado = estadoRespuestaSlug || estadoRespuesta;
                    encontrada.estado_label = estadoRespuestaLabel || estadoRespuesta;
                    encontrada.kanban_estado = estadoRespuestaSlug || estadoRespuesta;
                    encontrada.kanban_estado_label =
                        data.estado_label ?? data.kanban_estado_label ?? estadoRespuestaLabel ?? estadoRespuesta;
                    if (data.checklist !== undefined) {
                        encontrada.checklist = data.checklist;
                    }
                    if (data.checklist_progress !== undefined) {
                        encontrada.checklist_progress = data.checklist_progress;
                    }
                    if (data.kanban_next !== undefined) {
                        encontrada.kanban_next = data.kanban_next;
                    }

                    if (data.turno !== undefined) {
                        encontrada.turno = data.turno;
                    }
                }
            }

            showToast('✅ Estado actualizado correctamente');
            if (typeof callbackRender === 'function') {
                try {
                    const resultado = callbackRender();
                    if (resultado && typeof resultado.catch === 'function') {
                        resultado.catch(err => console.error('⚠️ Error al refrescar el tablero de exámenes:', err));
                    }
                } catch (callbackError) {
                    console.error('⚠️ Error al refrescar el tablero de exámenes:', callbackError);
                }
            }
            return data;
        })
        .catch(error => {
            console.error('❌ Error al actualizar estado:', error);
            const mensaje = error?.message || 'No se pudo actualizar el estado';
            showToast(`❌ ${mensaje.replace(/^❌\s*/, '')}`, false);
            throw error;
        });
}
