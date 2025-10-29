// js/kanban/index.js
import {renderKanban} from './renderer.js';
import {actualizarEstadoSolicitud} from './estado.js';
import {inicializarModalDetalles} from "./modalDetalles";
import {inicializarBotonesModal} from "./botonesModal";

// --- Debug helpers for kanban grouping and date parsing ---
const NORMALIZE = {
    estado: s => (s || '').toString().trim().toLowerCase().replace(/\s+/g, '-'),
};

function parseFecha(fecha) {
    // Admite formatos comunes: YYYY-MM-DD, YYYY-MM-DD HH:mm:ss, DD-MM-YYYY
    if (!fecha) return {raw: fecha, ts: NaN, fmt: null};
    let ts = NaN;
    let fmt = null;
    // ISO / SQL first
    const iso = /^\d{4}-\d{2}-\d{2}/;
    const dmy = /^\d{2}-\d{2}-\d{4}/;
    if (iso.test(fecha)) {
        ts = Date.parse(fecha.replace(' ', 'T'));
        fmt = 'Y-m-d';
    } else if (dmy.test(fecha)) {
        const [d, m, y] = fecha.split(/[\sT:-]/).filter(Boolean);
        ts = Date.parse(`${y}-${m}-${d}`);
        fmt = 'd-m-Y';
    } else {
        // fallback
        ts = Date.parse(fecha);
        fmt = 'auto';
    }
    return {raw: fecha, ts, fmt};
}

function agruparPorEstado(solicitudes) {
    const resultado = {};
    console.groupCollapsed('%cKANBAN ▶ Agrupación por estado', 'color:#0aa');
    console.log('Total registros recibidos:', solicitudes?.length || 0);

    solicitudes.forEach((solicitud, idx) => {
        const estadoOriginal = solicitud.estado;
        const estado = NORMALIZE.estado(estadoOriginal);
        const f = parseFecha(solicitud.fecha);

        if (!resultado[estado]) resultado[estado] = [];
        resultado[estado].push(solicitud);

        // Log detallado por fila (solo primeras 50 para no saturar)
        if (idx < 50) {
            console.log({
                id: solicitud.id,
                form_id: solicitud.form_id,
                estado_original: estadoOriginal,
                estado_normalizado: estado,
                fecha: solicitud.fecha,
                fecha_parse: {formatoDetectado: f.fmt, timestamp: f.ts, valido: !Number.isNaN(f.ts)}
            });
        }
        if (!estado) {
            console.warn('⚠️ Estado vacío o inválido en registro:', solicitud);
        }
    });

    // Resumen por estado
    Object.keys(resultado).forEach(k => {
        const arr = resultado[k];
        // quick peek: top 3 por fecha desc
        const top3 = [...arr]
            .map(s => ({...s, _ts: parseFecha(s.fecha).ts}))
            .sort((a, b) => (b._ts || 0) - (a._ts || 0))
            .slice(0, 3)
            .map(s => ({id: s.id, fecha: s.fecha, ts: s._ts}));
        console.log(`Estado: ${k} → ${arr.length} items`, {preview_top3_por_fecha_desc: top3});
    });
    console.groupEnd();
    return resultado;
}

function actualizarContadoresKanban(agrupadas) {
    const total = Object.values(agrupadas).reduce((sum, arr) => sum + arr.length, 0);

    // Buscar todos los contadores por id
    document.querySelectorAll('[id^="count-"]').forEach(contador => {
        const estado = contador.id.replace('count-', '');
        const count = agrupadas[estado]?.length || 0;

        contador.textContent = count;

        const porcentaje = document.getElementById(`percent-${estado}`);
        if (porcentaje) {
            porcentaje.textContent = total > 0 ? `(${Math.round(count / total * 100)}%)` : '';
        }
    });
}

export function initKanban(data) {
    console.groupCollapsed('%cKANBAN ▶ initKanban', 'color:#28a745');
    console.log('Registros entrantes (post-filtrado servidor):', data?.length || 0);

    renderKanban(data, (id, formId, estado) => {
        actualizarEstadoSolicitud(id, formId, estado, data, window.aplicarFiltros);
    });

    const agrupadasPorEstado = agruparPorEstado(data);
    actualizarContadoresKanban(agrupadasPorEstado);

    const totales = Object.fromEntries(Object.entries(agrupadasPorEstado).map(([k, v]) => [k, v.length]));
    console.log('Totales por estado:', totales);
    console.groupEnd();

    inicializarModalDetalles();
    inicializarBotonesModal();
}