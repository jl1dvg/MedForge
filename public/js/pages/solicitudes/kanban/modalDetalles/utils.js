import {getEstadosMeta} from "../config.js";
import {SLA_META, STATUS_BADGE_TEXT_DARK} from "./constants.js";

export function escapeHtml(value) {
    if (value === null || value === undefined) {
        return "";
    }
    return String(value)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#39;");
}

export function slugifyEstado(value) {
    if (!value) {
        return "";
    }

    const normalized = value
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");

    return normalized;
}

export function getEstadoBadge(estado) {
    const metaMap = getEstadosMeta();
    const slug = slugifyEstado(estado);
    const meta = metaMap[slug] || null;
    const color = meta?.color || "secondary";
    const label = meta?.label || estado || "Sin estado";
    const textClass = STATUS_BADGE_TEXT_DARK.has(color)
        ? "text-dark"
        : "text-white";

    return {
        label,
        badgeClass: `badge bg-${color} ${textClass}`,
    };
}

export function formatIsoDate(
    iso,
    fallback = null,
    formatter = "DD-MM-YYYY HH:mm"
) {
    if (!iso) {
        return fallback;
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return fallback;
    }
    if (typeof moment === "function") {
        return moment(date).format(formatter);
    }
    return date.toLocaleString();
}

export function formatDerivacionVigencia(fechaVigencia) {
    if (!fechaVigencia) {
        return {texto: "No disponible", badge: null};
    }
    const vigenciaDate = new Date(fechaVigencia);
    if (Number.isNaN(vigenciaDate.getTime())) {
        return {texto: "No disponible", badge: null};
    }

    const hoy = new Date();
    const diffMs = vigenciaDate.getTime() - hoy.getTime();
    const diffDays = Math.trunc(diffMs / (1000 * 60 * 60 * 24));
    let badge = null;

    if (diffDays >= 60) {
        badge = {color: "success", texto: "Vigente"};
    } else if (diffDays >= 30) {
        badge = {color: "info", texto: "Vigente"};
    } else if (diffDays >= 15) {
        badge = {color: "warning", texto: "Por vencer"};
    } else if (diffDays >= 0) {
        badge = {color: "danger", texto: "Urgente"};
    } else {
        badge = {color: "dark", texto: "Vencida"};
    }

    return {
        texto: `<strong>Días para caducar:</strong> ${diffDays} días`,
        badge,
    };
}

export function normalizeTextValue(value) {
    return (value ?? "")
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toUpperCase()
        .trim();
}

export function extractProcedimientoCodigo(value) {
    if (!value) return "";
    const match = value.toString().match(/(\d{3,})/);
    return match ? match[1] : "";
}

export function resolveLateralidad(value) {
    const normalized = normalizeTextValue(value);
    if (!normalized) return "";
    if (normalized.includes("AMB")) return "AMBOS";
    if (normalized.includes("AO")) return "AMBOS";
    if (normalized.includes("DER") || normalized.includes("OD") || normalized.includes("DERECHO")) {
        return "DERECHO";
    }
    if (normalized.includes("IZQ") || normalized.includes("OI") || normalized.includes("IZQUIERDO")) {
        return "IZQUIERDO";
    }
    return "";
}

export function lateralidadToId(lateralidad) {
    switch (lateralidad) {
        case "DERECHO":
            return 1;
        case "IZQUIERDO":
            return 2;
        case "AMBOS":
            return 3;
        default:
            return 1;
    }
}

export function formatHoursRemaining(value) {
    if (typeof value !== "number" || Number.isNaN(value)) {
        return null;
    }
    const rounded = Math.round(value);
    const abs = Math.abs(rounded);
    const label = abs >= 48 ? `${(abs / 24).toFixed(1)} día(s)` : `${abs} h`;
    return rounded >= 0 ? `Quedan ${label}` : `Retraso ${label}`;
}

function parseDateValue(value) {
    if (!value) {
        return null;
    }

    const parsed = value instanceof Date ? value : new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return null;
    }

    return parsed;
}

function resolveSlaStatusWithDerivacion(solicitud = {}) {
    const status = (solicitud.sla_status || "").toString().trim().toLowerCase();
    const derivacionDate = parseDateValue(solicitud.derivacion_fecha_vigencia);

    if (status === "vencido" && derivacionDate && derivacionDate.getTime() >= Date.now()) {
        return "en_rango";
    }

    return status;
}

export function buildSlaInfo(solicitud = {}) {
    const estado = resolveSlaStatusWithDerivacion(solicitud);
    const meta = SLA_META[estado] || SLA_META.sin_fecha;
    const derivacionDate = parseDateValue(solicitud.derivacion_fecha_vigencia);
    const shouldUseDerivacionDeadline = Boolean(
        derivacionDate && estado === "en_rango" && (solicitud.sla_status || "").toString().trim().toLowerCase() === "vencido"
    );
    const deadlineSource = shouldUseDerivacionDeadline
        ? derivacionDate.toISOString()
        : solicitud.sla_deadline;
    const deadline = formatIsoDate(deadlineSource, null);
    let hoursSource =
        typeof solicitud.sla_hours_remaining === "number"
            ? solicitud.sla_hours_remaining
            : Number.parseFloat(solicitud.sla_hours_remaining);
    if (shouldUseDerivacionDeadline && derivacionDate) {
        hoursSource = (derivacionDate.getTime() - Date.now()) / 3600000;
    }
    const hours = formatHoursRemaining(hoursSource);
    const detailParts = [];
    if (deadline) {
        detailParts.push(`Vence ${deadline}`);
    }
    if (hours) {
        detailParts.push(hours);
    }
    const detail = detailParts.length
        ? detailParts.join(" · ")
        : "Sin referencia SLA";

    return {
        label: meta.label,
        className: meta.className,
        detail,
        icon: meta.icon,
    };
}
