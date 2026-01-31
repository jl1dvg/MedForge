import {getKanbanConfig} from "../config.js";

let __lentesCache = null;

export async function obtenerLentesCatalogo() {
    if (__lentesCache && Array.isArray(__lentesCache)) {
        return __lentesCache;
    }

    const {basePath} = getKanbanConfig();
    const normalizedBase =
        basePath && basePath !== "/" ? basePath.replace(/\/+$/, "") : "";
    const origin =
        (typeof window !== "undefined" &&
            window.location &&
            window.location.origin) ||
        "";

    const candidates = new Set();
    const appendCandidate = (path) => {
        const normalized = path.startsWith("/") ? path : `/${path}`;
        candidates.add(normalized);
        if (normalizedBase && !normalized.startsWith(normalizedBase)) {
            candidates.add(`${normalizedBase}${normalized}`);
        }
        if (origin) {
            candidates.add(`${origin}${normalized}`);
        }
    };

    // Preferir los endpoints locales para evitar CORS
    appendCandidate("/insumos/lentes/list");
    appendCandidate("/api/lentes/index.php");
    appendCandidate("/api/lentes");

    // Fallback absoluto a dominio de API (evitar si hay CORS)
    candidates.add("https://asistentecive.consulmed.me/api/lentes/index.php");

    for (const url of candidates) {
        try {
            const resp = await fetch(url, {
                method: "GET",
                credentials: "include",
            });
            if (!resp.ok) continue;
            const data = await resp.json();
            const lista = Array.isArray(data?.lentes) ? data.lentes : [];
            __lentesCache = lista;
            return lista;
        } catch (e) {
            // intentar siguiente url
        }
    }
    throw new Error("No se pudieron obtener lentes");
}

export function generarPoderes(lente) {
    const powers = [];
    const min = lente?.rango_desde;
    const max = lente?.rango_hasta;
    const paso = lente?.rango_paso || 0.5;
    const inicioInc = lente?.rango_inicio_incremento || min;
    const toNum = (v) =>
        v === null || v === undefined || v === "" ? null : parseFloat(v);
    const minNum = toNum(min);
    const maxNum = toNum(max);
    const pasoNum = toNum(paso) || 0.5;
    const inicioNum = toNum(inicioInc);

    if (minNum !== null && maxNum !== null) {
        for (let v = minNum; v <= maxNum + 1e-6; v += pasoNum) {
            const rounded = Math.round(v * 100) / 100;
            if (inicioNum !== null && v < inicioNum && v > 0) continue;
            powers.push(rounded.toFixed(2));
        }
    }
    if (!powers.length && lente?.poder) {
        const p = toNum(lente.poder);
        powers.push(p !== null ? p.toFixed(2) : lente.poder);
    }
    return powers;
}
