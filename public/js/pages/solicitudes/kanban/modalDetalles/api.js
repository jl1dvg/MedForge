import {getKanbanConfig, getDataStore} from "../config.js";
import {updateKanbanCardSla} from "../renderer.js";
import {findSolicitudById} from "./store.js";

const solicitudDetalleCache = new Map();

export function buildApiCandidates(pathname) {
    const path = pathname.startsWith("/") ? pathname : `/${pathname}`;
    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const locationPath =
        typeof window !== "undefined" ? window.location.pathname || "" : "";
    const rootPrefix =
        normalizedBase && locationPath.includes(normalizedBase)
            ? locationPath.slice(0, locationPath.indexOf(normalizedBase))
            : "";
    const variants = new Set();

    variants.add(path);

    if (normalizedBase) {
        if (!path.startsWith(normalizedBase)) {
            variants.add(`${normalizedBase}${path}`);
        }

        const stripped = path.startsWith(normalizedBase)
            ? path.slice(normalizedBase.length) || "/"
            : path;
        variants.add(stripped.startsWith("/") ? stripped : `/${stripped}`);
    }

    if (rootPrefix) {
        variants.add(`${rootPrefix}${path}`);
    }

    return Array.from(variants);
}

export function resolveApiBasePath() {
    const {apiBasePath} = getKanbanConfig();
    const fallback = "/api";
    if (!apiBasePath) {
        return fallback;
    }
    const normalized = apiBasePath.replace(/\/+$/, "");
    return normalized.startsWith("/") ? normalized : `/${normalized}`;
}

export function buildEstadoApiCandidates() {
    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const apiBase = resolveApiBasePath();
    const orderedCandidates = [
        "/solicitudes/api/estado",
        `${apiBase}/solicitudes/estado`,
        "/api/solicitudes/estado",
    ];
    if (normalizedBase) {
        orderedCandidates.push(`${normalizedBase}/api/estado`);
    }

    const expanded = [];
    const seen = new Set();
    orderedCandidates.forEach((candidate) => {
        buildApiCandidates(candidate).forEach((url) => {
            if (!seen.has(url)) {
                seen.add(url);
                expanded.push(url);
            }
        });
    });

    return expanded;
}

export function buildGuardarSolicitudInternalCandidates({solicitudId} = {}) {
    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";

    const sid = solicitudId ? encodeURIComponent(String(solicitudId)) : "";

    // ✅ Endpoint interno del módulo (requiere sesión)
    // Ruta canónica: POST /solicitudes/{id}/cirugia
    // Nota: en muchos entornos `basePath` ya es "/solicitudes" (se usa para /prefactura, /derivacion, etc.)
    // Por eso evitamos construir "/solicitudes/solicitudes/...".

    const candidates = [];

    if (!sid) {
        return candidates;
    }

    // 1) Canónica absoluta
    candidates.push(`/solicitudes/${sid}/cirugia`);

    // 2) Si el basePath existe y NO es ya "/solicitudes", construir prefijo + "/solicitudes/..."
    if (normalizedBase && normalizedBase !== "/solicitudes") {
        candidates.push(`${normalizedBase}/solicitudes/${sid}/cirugia`);
    }

    // 3) Si el basePath existe y YA es "/solicitudes", construir simplemente basePath + "/{id}/cirugia"
    if (normalizedBase === "/solicitudes") {
        candidates.push(`${normalizedBase}/${sid}/cirugia`);
    }

    // Fallback opcional: endpoint CRM (solo si backend lo soporta)
    candidates.push(`/solicitudes/${sid}/crm`);
    if (normalizedBase && normalizedBase !== "/solicitudes") {
        candidates.push(`${normalizedBase}/solicitudes/${sid}/crm`);
    }
    if (normalizedBase === "/solicitudes") {
        candidates.push(`${normalizedBase}/${sid}/crm`);
    }

    // Filtrar duplicados preservando orden
    return Array.from(new Set(candidates.filter(Boolean)));
}

export function clearSolicitudDetalleCacheBySolicitudId(solicitudId) {
    if (!solicitudId) return;
    const sid = String(solicitudId);
    for (const key of Array.from(solicitudDetalleCache.keys())) {
        // keys are like: hc:solicitudId:formId (some parts may be missing)
        if (key.split(":").includes(sid)) {
            solicitudDetalleCache.delete(key);
        }
    }
}

export async function fetchWithFallback(urls, options) {
    let lastError;
    for (const url of urls) {
        try {
            const safeOptions = {
                credentials: options?.credentials ?? "same-origin",
                ...options,
            };
            const response = await fetch(url, safeOptions);
            if (response.ok) {
                return response;
            }
            lastError = new Error(`HTTP ${response.status}`);
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError || new Error("No se pudo completar la solicitud");
}

export async function fetchDetalleSolicitud({hcNumber, solicitudId, formId}) {
    const cacheKey = [hcNumber, solicitudId, formId].filter(Boolean).join(":");
    if (solicitudDetalleCache.has(cacheKey)) {
        return solicitudDetalleCache.get(cacheKey);
    }

    if (!hcNumber) {
        throw new Error("No se puede solicitar detalle sin HC");
    }

    const searchParams = new URLSearchParams({hcNumber});
    if (formId) {
        searchParams.set("form_id", formId);
    }

    const urls = buildEstadoApiCandidates().map(
        (base) => `${base}?${searchParams}`
    );
    const response = await fetchWithFallback(urls);
    if (!response.ok) {
        throw new Error("No se pudo obtener el detalle de la solicitud");
    }

    const payload = await response.json();
    const lista = Array.isArray(payload?.solicitudes) ? payload.solicitudes : [];
    const detalle = lista.find(
        (item) =>
            String(item.id) === String(solicitudId) ||
            String(item.form_id) === String(formId)
    );

    if (!detalle) {
        throw new Error("No se encontró información de la solicitud");
    }

    solicitudDetalleCache.set(cacheKey, detalle);
    return detalle;
}

export async function refreshKanbanBadgeFromDetalle({hcNumber, solicitudId, formId}) {
    if (!solicitudId && !formId && !hcNumber) {
        return;
    }

    try {
        const detalle = await fetchDetalleSolicitud({
            hcNumber,
            solicitudId,
            formId,
        });
        const store = getDataStore();
        const target = Array.isArray(store)
            ? store.find((item) => String(item.id) === String(solicitudId))
            : null;
        if (target && typeof target === "object") {
            Object.assign(target, detalle);
        }
        updateKanbanCardSla(detalle);
    } catch (error) {
        console.warn("No se pudo refrescar badge SLA", error);
    }
}

export async function hydrateSolicitudFromDetalle({solicitudId, formId, hcNumber}) {
    const base = findSolicitudById(solicitudId) || {};
    if (!hcNumber && !base.hc_number) {
        return base;
    }

    if (base.detalle_hidratado) {
        return base;
    }

    try {
        const detalle = await fetchDetalleSolicitud({
            hcNumber: hcNumber || base.hc_number,
            solicitudId,
            formId: formId || base.form_id,
        });

        const merged = {...base, ...detalle, detalle_hidratado: true};
        const store = getDataStore();
        const target = store.find((item) => String(item.id) === String(solicitudId));
        if (target && typeof target === "object") {
            Object.assign(target, merged, {detalle_hidratado: true});
        }
        return merged;
    } catch (error) {
        console.warn("No se pudo hidratar solicitud con detalle", error);
        return base;
    }
}

export async function loadSolicitudCore({hc, formId, solicitudId}) {
    const {basePath} = getKanbanConfig();
    const prefacturaUrl = `${basePath}/prefactura?hc_number=${encodeURIComponent(
        hc
    )}&form_id=${encodeURIComponent(formId)}&solicitud_id=${encodeURIComponent(
        solicitudId
    )}`;

    const [html, solicitud] = await Promise.all([
        fetch(prefacturaUrl).then((response) => {
            if (!response.ok) {
                throw new Error("No se encontró la prefactura");
            }
            return response.text();
        }),
        hydrateSolicitudFromDetalle({solicitudId, formId, hcNumber: hc}),
    ]);

    return {html, solicitud};
}
