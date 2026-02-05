import { getTableBodySelector } from "../config.js";
import { loadExamenCore } from "./api.js";
import {
    renderEstadoContext,
    renderPatientSummaryFallback,
    resetEstadoContext,
    resetPatientSummary,
} from "./state.js";

export function relocateExamenesPrequirurgicosButton(content) {
    const button = document.getElementById("btnSolicitarExamenesPrequirurgicos");
    if (!button || !content) {
        return;
    }

    button.classList.remove("d-none");
    content.prepend(button);
}

export function parkExamenesPrequirurgicosButton(modalElement) {
    const button = document.getElementById("btnSolicitarExamenesPrequirurgicos");
    const footer = modalElement?.querySelector(".modal-footer");
    if (!button || !footer) {
        return;
    }

    button.classList.add("d-none");
    footer.appendChild(button);
}

export function abrirPrefactura({ hc, formId, examenId }) {
    if (!hc || !formId) {
        console.warn("⚠️ No se encontró hc_number o form_id en la selección actual");
        return;
    }

    const modalElement = document.getElementById("prefacturaModal");
    const modal = new bootstrap.Modal(modalElement);
    const content = document.getElementById("prefacturaContent");

    parkExamenesPrequirurgicosButton(modalElement);

    content.innerHTML = `
        <div class="d-flex align-items-center justify-content-center py-5">
            <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
            <strong>Cargando información...</strong>
        </div>
    `;

    modal.show();

    Promise.resolve()
        .then(() => loadExamenCore({ hc, formId, examenId }))
        .then(({ html, examen }) => {
            content.innerHTML = html;
            relocateExamenesPrequirurgicosButton(content);
            renderPatientSummaryFallback(examenId || examen?.id);
            renderEstadoContext(examenId || examen?.id);
        })
        .catch((error) => {
            console.error("❌ Error cargando detalle de examen:", error);
            content.innerHTML =
                '<p class="text-danger mb-0">No se pudo cargar la información del examen.</p>';
        });

    modalElement.addEventListener(
        "hidden.bs.modal",
        () => {
            document
                .querySelectorAll(".kanban-card")
                .forEach((element) => element.classList.remove("active"));
            const tableSelector = getTableBodySelector();
            document
                .querySelectorAll(`${tableSelector} tr`)
                .forEach((row) => row.classList.remove("table-active"));
            resetEstadoContext();
            resetPatientSummary();
        },
        { once: true }
    );
}
