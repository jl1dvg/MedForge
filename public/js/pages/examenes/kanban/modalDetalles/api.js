import { getKanbanConfig, getDataStore } from "../config.js";
import { findExamenById } from "./store.js";

const examenDetalleCache = new Map();

export function buildApiCandidates(pathname) {
    const path = pathname.startsWith("/") ? pathname : `/${pathname}`;
    const { basePath } = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const locationPath =
        typeof window !== "undefined" ? window.location.pathname || "" : "";
    const rootPrefix =
        normalizedBase && locationPath.includes(normalizedBase)
            ? locationPath.slice(0, locationPath.indexOf(normalizedBase))
            : "";

    const variants = new Set([path]);

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
    const { apiBasePath } = getKanbanConfig();
    const fallback = "/api";

    if (!apiBasePath) {
        return fallback;
    }

    const normalized = apiBasePath.replace(/\/+$/, "");
    return normalized.startsWith("/") ? normalized : `/${normalized}`;
}

export function buildEstadoApiCandidates() {
    const { basePath } = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const apiBase = resolveApiBasePath();

    const orderedCandidates = [
        "/examenes/api/estado",
        `${apiBase}/examenes/estado`,
        "/api/examenes/estado",
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

export async function fetchDetalleExamen({ hcNumber, examenId, formId }) {
    const cacheKey = [hcNumber, examenId, formId].filter(Boolean).join(":");
    if (examenDetalleCache.has(cacheKey)) {
        return examenDetalleCache.get(cacheKey);
    }

    if (!hcNumber) {
        throw new Error("No se puede solicitar detalle sin HC");
    }

    const searchParams = new URLSearchParams({ hcNumber });
    if (formId) {
        searchParams.set("form_id", formId);
    }

    const urls = buildEstadoApiCandidates().map(
        (base) => `${base}?${searchParams}`
    );

    const response = await fetchWithFallback(urls);
    const payload = await response.json();
    const lista = Array.isArray(payload?.examenes) ? payload.examenes : [];
    const detalle = lista.find(
        (item) =>
            String(item.id) === String(examenId) ||
            String(item.form_id) === String(formId)
    );

    if (!detalle) {
        throw new Error("No se encontró información del examen");
    }

    examenDetalleCache.set(cacheKey, detalle);
    return detalle;
}

export async function hydrateExamenFromDetalle({ examenId, formId, hcNumber }) {
    const base = findExamenById(examenId) || {};
    if (!hcNumber && !base.hc_number) {
        return base;
    }

    if (base.detalle_hidratado) {
        return base;
    }

    try {
        const detalle = await fetchDetalleExamen({
            hcNumber: hcNumber || base.hc_number,
            examenId,
            formId: formId || base.form_id,
        });

        const merged = { ...base, ...detalle, detalle_hidratado: true };
        const store = getDataStore();
        const target = store.find((item) => String(item.id) === String(examenId));
        if (target && typeof target === "object") {
            Object.assign(target, merged, { detalle_hidratado: true });
        }

        return merged;
    } catch (error) {
        console.warn("No se pudo hidratar examen con detalle", error);
        return base;
    }
}

export async function loadExamenCore({ hc, formId, examenId }) {
    const { basePath } = getKanbanConfig();
    const prefacturaUrl = `${basePath}/prefactura?hc_number=${encodeURIComponent(
        hc
    )}&form_id=${encodeURIComponent(formId)}&examen_id=${encodeURIComponent(
        examenId
    )}`;

    const [html, examen] = await Promise.all([
        fetch(prefacturaUrl).then((response) => {
            if (!response.ok) {
                throw new Error("No se encontró el detalle del examen");
            }
            return response.text();
        }),
        hydrateExamenFromDetalle({ examenId, formId, hcNumber: hc }),
    ]);

    return { html, examen };
}
