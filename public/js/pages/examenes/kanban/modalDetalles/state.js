import { findExamenById } from "./store.js";
import { escapeHtml, formatIsoDate } from "./utils.js";

export function resetEstadoContext() {
    const container = document.getElementById("prefacturaState");
    if (!container) {
        return;
    }

    container.classList.add("d-none");
    container.innerHTML = "";
}

export function resetPatientSummary() {
    const container = document.getElementById("prefacturaPatientSummary");
    if (!container) {
        return;
    }

    container.classList.add("d-none");
    container.innerHTML = "";
}

export function renderPatientSummaryFallback(examenId) {
    const container = document.getElementById("prefacturaPatientSummary");
    if (!container || !examenId) {
        return;
    }

    const examen = findExamenById(examenId);
    if (!examen) {
        return;
    }

    const nombre = examen.full_name || "Paciente sin nombre";
    const hc = examen.hc_number || "—";
    const afiliacion = examen.afiliacion || "Sin afiliación";

    container.classList.remove("d-none");
    container.innerHTML = `
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-column gap-1">
                <strong>${escapeHtml(nombre)}</strong>
                <small class="text-muted">HC ${escapeHtml(hc)}</small>
                <small class="text-muted">${escapeHtml(afiliacion)}</small>
            </div>
        </div>
    `;
}

export function renderEstadoContext(examenId) {
    const container = document.getElementById("prefacturaState");
    if (!container || !examenId) {
        return;
    }

    const examen = findExamenById(examenId);
    if (!examen) {
        return;
    }

    container.classList.remove("d-none");
    container.innerHTML = `
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row g-2 small">
                    <div class="col-md-4"><strong>Estado:</strong> ${escapeHtml(examen.estado || "Pendiente")}</div>
                    <div class="col-md-4"><strong>Etapa CRM:</strong> ${escapeHtml(examen.crm_pipeline_stage || "Recibido")}</div>
                    <div class="col-md-4"><strong>Responsable:</strong> ${escapeHtml(examen.crm_responsable_nombre || "Sin responsable")}</div>
                    <div class="col-md-4"><strong>Prioridad:</strong> ${escapeHtml(examen.prioridad || "Normal")}</div>
                    <div class="col-md-4"><strong>Fuente:</strong> ${escapeHtml(examen.crm_fuente || "Consulta")}</div>
                    <div class="col-md-4"><strong>Fecha:</strong> ${escapeHtml(formatIsoDate(examen.consulta_fecha || examen.created_at))}</div>
                </div>
            </div>
        </div>
    `;
}
