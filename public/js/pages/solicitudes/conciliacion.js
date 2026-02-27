import { getKanbanConfig, resolveReadPath, resolveWritePath } from './kanban/config.js';

const ESCAPE_MAP = {
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#39;',
    '`': '&#96;',
};

const escapeHtml = (value) => {
    if (value === null || value === undefined) {
        return '';
    }

    return String(value).replace(/[&<>"'`]/g, character => ESCAPE_MAP[character]);
};

const formatDateTime = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(parsed.getTime())) {
        return String(value);
    }

    return parsed.toLocaleString();
};

const pad = (value) => String(value).padStart(2, '0');

const formatYmd = (date) => {
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
};

const normalizeDatePart = (value) => {
    if (!value) {
        return '';
    }

    const trimmed = String(value).trim();
    const match = trimmed.match(/^(\d{2})-(\d{2})-(\d{4})$/);
    if (match) {
        return `${match[3]}-${match[2]}-${match[1]}`;
    }

    return trimmed;
};

const parseDateRange = (rangeText) => {
    if (!rangeText || !rangeText.includes(' - ')) {
        const single = normalizeDatePart(rangeText || '');
        return { from: single || '', to: single || '' };
    }

    const [from, to] = rangeText.split(' - ');
    return {
        from: normalizeDatePart(from),
        to: normalizeDatePart(to),
    };
};

const buildDefaultDateRange = () => {
    const endDate = new Date();
    const startDate = new Date(endDate);
    startDate.setDate(endDate.getDate() - 29);
    return {
        from: formatYmd(startDate),
        to: formatYmd(endDate),
    };
};

const resolveDateRange = (getFilters) => {
    const filters = typeof getFilters === 'function' ? (getFilters() || {}) : {};
    const fallbackText = document.getElementById('kanbanDateFilter')?.value || '';
    const rangeText = String(filters?.fechaTexto || fallbackText).trim();
    const parsed = parseDateRange(rangeText);

    const from = parsed.from || parsed.to;
    const to = parsed.to || parsed.from;
    if (from && to) {
        return { from, to };
    }

    return buildDefaultDateRange();
};

const resolveDebugParams = () => {
    const params = new URLSearchParams(window.location.search || '');
    const debugRaw = (params.get('debug_conciliacion') || '').trim().toLowerCase();
    const enabled = ['1', 'true', 'yes', 'on'].includes(debugRaw);
    if (!enabled) {
        return { enabled: false };
    }

    return {
        enabled: true,
        hc: (params.get('debug_hc') || params.get('hc_number') || '').trim(),
        formId: (params.get('debug_form_id') || params.get('form_id') || '').trim(),
        id: (params.get('debug_id') || params.get('id') || '').trim(),
        limit: (params.get('debug_limit') || '').trim(),
    };
};

const buildProtocolHtml = (protocol, { confirmed = false } = {}) => {
    if (!protocol || !protocol.form_id) {
        return '<span class="text-muted">Sin coincidencia</span>';
    }

    const summary = [
        `#${escapeHtml(protocol.form_id)}`,
        protocol.lateralidad ? `Ojo ${escapeHtml(protocol.lateralidad)}` : null,
        protocol.fecha_inicio ? formatDateTime(protocol.fecha_inicio) : null,
    ].filter(Boolean).join(' · ');

    const confirmationMeta = confirmed
        ? `<small>Confirmado: ${escapeHtml(formatDateTime(protocol.confirmado_at))}${protocol.confirmado_by ? ` · ${escapeHtml(protocol.confirmado_by)}` : ''}</small>`
        : '';

    const membrete = protocol.membrete
        ? `<small>${escapeHtml(protocol.membrete)}</small>`
        : '';

    return `
        <div class="d-flex flex-column">
            <span class="fw-semibold">${summary}</span>
            ${membrete}
            ${confirmationMeta}
        </div>
    `;
};

const renderRow = (row) => {
    const confirmed = row?.protocolo_confirmado || null;
    const candidate = row?.protocolo_posterior_compatible || null;
    const protocol = confirmed || candidate;
    const estado = String(row?.estado || '').trim().toLowerCase();
    const alreadyCompleted = estado === 'completado';

    let statusHtml = '<span class="badge text-bg-secondary">Sin match</span>';
    if (confirmed || alreadyCompleted) {
        statusHtml = '<span class="badge text-bg-success">Confirmada</span>';
    } else if (candidate) {
        statusHtml = '<span class="badge text-bg-warning text-dark">Pendiente confirmación</span>';
    }

    let actionHtml = '<span class="text-muted">—</span>';
    if (!confirmed && !alreadyCompleted && candidate?.form_id) {
        actionHtml = `
            <button
                type="button"
                class="btn btn-sm btn-outline-success"
                data-conciliacion-action="confirmar"
                data-solicitud-id="${escapeHtml(row?.id ?? '')}"
                data-protocolo-form-id="${escapeHtml(candidate.form_id)}"
            >
                Confirmar y completar
            </button>
        `;
    }

    const rowClass = confirmed ? 'table-success' : '';

    return `
        <tr class="${rowClass}">
            <td>${escapeHtml(formatDateTime(row?.fecha_solicitud))}</td>
            <td>
                <div class="fw-semibold">${escapeHtml(row?.full_name || 'Paciente sin nombre')}</div>
                <small>HC ${escapeHtml(row?.hc_number || '—')}</small>
            </td>
            <td>${escapeHtml(row?.procedimiento || '—')}</td>
            <td>${escapeHtml(row?.ojo_resuelto || '—')}</td>
            <td>${buildProtocolHtml(protocol, { confirmed: Boolean(confirmed) })}</td>
            <td>${statusHtml}</td>
            <td>${actionHtml}</td>
        </tr>
    `;
};

const buildSummary = (totales = {}, periodo = {}) => {
    const total = Number(totales.total || 0);
    const matches = Number(totales.con_match || 0);
    const confirmadas = Number(totales.confirmadas || 0);
    const from = String(periodo?.from || '').trim();
    const to = String(periodo?.to || '').trim();
    const periodLabel = from && to
        ? `${from} a ${to}`
        : `Mes ${periodo?.mes || formatYmd(new Date())}`;

    return `${periodLabel}: ${total} solicitudes · ${matches} con protocolo compatible · ${confirmadas} confirmadas.`;
};

export const initSolicitudesConciliacion = ({ showToast, onConfirmed, getFilters } = {}) => {
    const config = getKanbanConfig();

    const section = document.getElementById('solicitudesConciliacionSection');
    const summary = document.getElementById('solicitudesConciliacionSummary');
    const tableBody = document.getElementById('solicitudesConciliacionBody');
    const emptyState = document.getElementById('solicitudesConciliacionEmpty');
    const refreshButton = document.getElementById('solicitudesConciliacionRefresh');

    if (!section || !summary || !tableBody || !emptyState) {
        return {
            reload: async () => {},
        };
    }

    const uniq = (values = []) => Array.from(new Set(values.filter(Boolean)));

    const buildReadCandidates = (path) => uniq([
        resolveReadPath(path),
        path,
    ]);

    const buildWriteCandidates = (path) => uniq([
        resolveWritePath(path),
        path,
    ]);

    const setLoading = (loading) => {
        if (refreshButton) {
            refreshButton.disabled = loading;
        }
        summary.textContent = loading ? 'Cargando conciliación...' : summary.textContent;
    };

    const render = ({ data = [], totales = {}, periodo = {} } = {}) => {
        const rows = Array.isArray(data) ? data : [];
        summary.textContent = buildSummary(totales, periodo);

        if (!rows.length) {
            tableBody.innerHTML = '';
            emptyState.classList.remove('d-none');
            return;
        }

        emptyState.classList.add('d-none');
        tableBody.innerHTML = rows.map(renderRow).join('');
    };

    const load = async () => {
        setLoading(true);
        try {
            const range = resolveDateRange(getFilters);
            const debug = resolveDebugParams();
            const params = new URLSearchParams();
            if (range.from) {
                params.set('date_from', range.from);
            }
            if (range.to) {
                params.set('date_to', range.to);
            }
            if (debug.enabled) {
                params.set('debug', '1');
                if (debug.hc) {
                    params.set('debug_hc', debug.hc);
                }
                if (debug.formId) {
                    params.set('debug_form_id', debug.formId);
                }
                if (debug.id) {
                    params.set('debug_id', debug.id);
                }
                if (debug.limit) {
                    params.set('debug_limit', debug.limit);
                }
            }

            const query = params.toString();
            const path = `${config.basePath}/conciliacion-cirugias${query ? `?${query}` : ''}`;
            const candidates = buildReadCandidates(path);
            let payload = null;
            let lastError = null;

            for (const url of candidates) {
                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                    });

                    if (response.status === 404) {
                        continue;
                    }

                    const data = await response.json();
                    if (!response.ok || data?.success !== true) {
                        throw new Error(data?.error || 'No se pudo cargar la conciliación de cirugías.');
                    }

                    payload = data;
                    break;
                } catch (error) {
                    lastError = error;
                }
            }

            if (!payload) {
                throw lastError || new Error('No se pudo cargar la conciliación de cirugías.');
            }

            if (debug.enabled && payload?.debug) {
                console.group('Conciliacion Debug');
                console.log(payload.debug);
                console.groupEnd();
            }

            render(payload);
        } catch (error) {
            tableBody.innerHTML = '';
            emptyState.classList.remove('d-none');
            summary.textContent = 'No se pudo cargar la conciliación.';
            if (typeof showToast === 'function') {
                showToast(error?.message || 'No se pudo cargar la conciliación', false);
            }
        } finally {
            setLoading(false);
        }
    };

    const confirmar = async ({ solicitudId, protocoloFormId }) => {
        if (!solicitudId || !protocoloFormId) {
            return;
        }

        const message = `¿Confirmar protocolo ${protocoloFormId} y marcar la solicitud como completada?`;

        if (typeof window.Swal !== 'undefined') {
            const result = await window.Swal.fire({
                title: 'Confirmar cirugía',
                text: message,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Confirmar',
                cancelButtonText: 'Cancelar',
            });

            if (!result.isConfirmed) {
                return;
            }
        } else if (!window.confirm(message)) {
            return;
        }

        try {
            const path = `${config.basePath}/${solicitudId}/conciliacion-cirugia/confirmar`;
            const candidates = buildWriteCandidates(path);
            let payload = null;
            let lastError = null;

            for (const url of candidates) {
                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        credentials: 'same-origin',
                        body: JSON.stringify({
                            protocolo_form_id: protocoloFormId,
                        }),
                    });

                    if (response.status === 404) {
                        continue;
                    }

                    const data = await response.json();
                    if (!response.ok || data?.success !== true) {
                        throw new Error(data?.error || 'No se pudo confirmar la cirugía.');
                    }

                    payload = data;
                    break;
                } catch (error) {
                    lastError = error;
                }
            }

            if (!payload) {
                throw lastError || new Error('No se pudo confirmar la cirugía.');
            }

            if (typeof showToast === 'function') {
                showToast(payload?.message || 'Cirugía confirmada correctamente.', true);
            }

            if (typeof onConfirmed === 'function') {
                await onConfirmed(payload);
            }

            await load();
        } catch (error) {
            if (typeof showToast === 'function') {
                showToast(error?.message || 'No se pudo confirmar la cirugía', false);
            }
        }
    };

    tableBody.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-conciliacion-action="confirmar"]');
        if (!button) {
            return;
        }

        const solicitudId = button.getAttribute('data-solicitud-id') || '';
        const protocoloFormId = button.getAttribute('data-protocolo-form-id') || '';
        await confirmar({ solicitudId, protocoloFormId });
    });

    if (refreshButton) {
        refreshButton.addEventListener('click', () => {
            load();
        });
    }

    load();

    return {
        reload: load,
    };
};
