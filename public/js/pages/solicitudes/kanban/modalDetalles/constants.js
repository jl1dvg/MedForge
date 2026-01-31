export const STATUS_BADGE_TEXT_DARK = new Set(["warning", "light", "info"]);
export const PATIENT_ALERT_TEXT = /paciente/i;

export const SLA_META = {
    en_rango: {
        label: "En rango",
        className: "text-success fw-semibold",
        icon: "mdi-check-circle-outline",
    },
    advertencia: {
        label: "Seguimiento 72h",
        className: "text-warning fw-semibold",
        icon: "mdi-timer-sand",
    },
    critico: {
        label: "Crítico 24h",
        className: "text-danger fw-semibold",
        icon: "mdi-alert-octagon",
    },
    vencido: {
        label: "SLA vencido",
        className: "text-dark fw-semibold",
        icon: "mdi-alert",
    },
    sin_fecha: {
        label: "Sin programación",
        className: "text-muted",
        icon: "mdi-calendar-remove",
    },
    cerrado: {
        label: "Cerrado",
        className: "text-muted",
        icon: "mdi-lock-outline",
    },
};

export const ALERT_TEMPLATES = [
    {
        field: "alert_reprogramacion",
        label: "Reprogramar",
        icon: "mdi-calendar-alert",
        className: "badge bg-danger text-white",
    },
    {
        field: "alert_pendiente_consentimiento",
        label: "Consentimiento",
        icon: "mdi-shield-alert",
        className: "badge bg-warning text-dark",
    },
];
