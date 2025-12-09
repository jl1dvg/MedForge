import { showToast } from './toast.js';
import { getKanbanConfig } from './config.js';

function buildApiVariants(pathname) {
    const path = pathname.startsWith('/') ? pathname : `/${pathname}`;
    const { basePath } = getKanbanConfig();
    const normalizedBase = basePath && basePath !== '/' ? basePath.replace(/\/+$/, '') : '';
    const locationPath = typeof window !== 'undefined' ? window.location.pathname || '' : '';
    const rootPrefix =
        normalizedBase && locationPath.includes(normalizedBase)
            ? locationPath.slice(0, locationPath.indexOf(normalizedBase))
            : '';

    const origin = (typeof window !== 'undefined' && window.location?.origin) ? window.location.origin : '';
    const pathHasBase =
        normalizedBase && (path === normalizedBase || path.startsWith(`${normalizedBase}/`));

    const variants = new Set();

    // Path absoluto tal cual
    variants.add(path);

    // Path con basePath cuando no está incluido
    if (normalizedBase && !pathHasBase) {
        variants.add(`${normalizedBase}${path}`);
    }
    if (rootPrefix) {
        variants.add(`${rootPrefix}${path}`);
        if (normalizedBase && !pathHasBase) {
            variants.add(`${rootPrefix}${normalizedBase}${path}`);
        }
    }

    // Variantes absolutas con origin
    if (origin) {
        Array.from(variants).forEach((p) => {
            variants.add(`${origin}${p.startsWith('/') ? p : `/${p}`}`);
        });
    }

    return Array.from(variants);
}

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
        const slug = normalizarSlug(nuevoEstado);
const payload = {
        id: Number.parseInt(id, 10),
        // muchos backends viejos esperan 'estado'
        estado: slug,
        // el nuevo service de checklist seguramente espera 'etapa' o 'etapa_slug'
        etapa: slug,
        etapa_slug: slug,
        completado: options.completado !== undefined ? Boolean(options.completado) : true,
        force: options.force ? Boolean(options.force) : false,
    };

    // IMPORTANTÍSIMO: mandar también el form_id si lo tienes
    if (formId !== undefined && formId !== null && formId !== "") {
        payload.form_id = formId;
    }

    if (options.nota) {
        payload.nota = options.nota;
    }

    const urls = buildApiVariants('/solicitudes/actualizar-estado');

    const attempt = (index = 0) => {
        if (index >= urls.length) {
            const err = new Error('No se pudo actualizar el estado');
            err.__estadoNotificado = true;
            return Promise.reject(err);
        }

        return fetch(urls[index], {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        }).then(async response => {
            const data = await response.json().catch(() => ({}));
            const successFlag = typeof data.success === 'boolean' ? data.success : true;
            if (!response.ok || !successFlag) {
                const message = data?.error || data?.message || 'No se pudo actualizar el estado';
                const error = new Error(message);
                error.__estadoNotificado = false;
                throw error;
            }

            const estadoKanban = (data.kanban_estado ?? data.estado ?? nuevoEstado ?? '').toString();
            const estadoLabel = (data.kanban_estado_label ?? data.estado_label ?? estadoKanban)?.toString();
            const turnoFinal = data.turno ?? null;

            if (Array.isArray(solicitudes)) {
                const encontrada =
                    solicitudes.find(s => String(s.form_id) === String(formId)) ||
                    solicitudes.find(s => String(s.id) === String(id));

                if (encontrada) {
                    // Estado lógico y de tablero
                    encontrada.estado = estadoKanban;
                    encontrada.estado_label = estadoLabel || encontrada.estado_label || estadoKanban;
                    encontrada.kanban_estado = data.kanban_estado ?? encontrada.kanban_estado ?? estadoKanban;
                    encontrada.kanban_estado_label =
                        data.kanban_estado_label ?? encontrada.kanban_estado_label ?? estadoLabel ?? estadoKanban;

                    if (data.kanban_next) {
                        encontrada.kanban_next = {
                            ...(encontrada.kanban_next || {}),
                            ...data.kanban_next,
                        };
                    }

                    // Checklist y progreso
                    if (data.checklist !== undefined) {
                        encontrada.checklist = data.checklist;
                    }
                    if (data.checklist_progress !== undefined) {
                        encontrada.checklist_progress = data.checklist_progress;
                    }

                    // Turno, si aplica
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
        }).catch(error => {
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

            // Intentar siguiente variante de URL si es error de red/404
            if (!error?.__estadoNotificado && index + 1 < urls.length) {
                return attempt(index + 1);
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
    };

    return attempt(0);
}
