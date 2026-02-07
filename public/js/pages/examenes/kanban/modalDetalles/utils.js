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

export function formatIsoDate(value, fallback = "No disponible") {
    if (!value) {
        return fallback;
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    if (typeof moment === "function") {
        return moment(date).format("DD-MM-YYYY HH:mm");
    }

    return date.toLocaleString();
}

export function formatDerivacionVigencia(fechaVigencia) {
    if (!fechaVigencia) {
        return { texto: "No disponible", badge: null };
    }
    const vigenciaDate = new Date(fechaVigencia);
    if (Number.isNaN(vigenciaDate.getTime())) {
        return { texto: "No disponible", badge: null };
    }

    const hoy = new Date();
    const diffMs = vigenciaDate.getTime() - hoy.getTime();
    const diffDays = Math.trunc(diffMs / (1000 * 60 * 60 * 24));
    let badge = null;

    if (diffDays >= 60) {
        badge = { color: "success", texto: "Vigente" };
    } else if (diffDays >= 30) {
        badge = { color: "info", texto: "Vigente" };
    } else if (diffDays >= 15) {
        badge = { color: "warning", texto: "Por vencer" };
    } else if (diffDays >= 0) {
        badge = { color: "danger", texto: "Urgente" };
    } else {
        badge = { color: "dark", texto: "Vencida" };
    }

    return {
        texto: `<strong>Días para caducar:</strong> ${diffDays} días`,
        badge,
    };
}

export function resolveDerivacionStatus(fechaVigencia) {
    if (!fechaVigencia) {
        return null;
    }
    const date = new Date(fechaVigencia);
    if (Number.isNaN(date.getTime())) {
        return null;
    }
    return date.getTime() >= Date.now() ? "vigente" : "vencida";
}

export function slugifyEstado(value) {
    return (value ?? "")
        .toString()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "");
}
