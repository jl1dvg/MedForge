import { getDataStore, getTableBodySelector } from "../config.js";
import { showToast } from "../toast.js";
import { loadExamenCore } from "./api.js";
import {
    asegurarPreseleccionDerivacion,
    buildDerivacionMissingHtml,
    loadDerivacion,
    renderDerivacionContent,
} from "./derivacion.js";
import {
    renderEstadoContext,
    renderPatientSummaryFallback,
    resetEstadoContext,
    resetPatientSummary,
} from "./state.js";
import { resolveDerivacionStatus } from "./utils.js";

export function abrirPrefactura({ hc, formId, examenId }) {
    if (!hc || !formId) {
        console.warn("⚠️ No se encontró hc_number o form_id en la selección actual");
        return;
    }

    const modalElement = document.getElementById("prefacturaModal");
    const modal = new bootstrap.Modal(modalElement);
    const content = document.getElementById("prefacturaContent");

    content.innerHTML = `
        <div class="d-flex align-items-center justify-content-center py-5">
            <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
            <strong>Cargando información...</strong>
        </div>
    `;

    modal.show();

    const corePromise = loadExamenCore({ hc, formId, examenId });
    const derivacionPromise = asegurarPreseleccionDerivacion({
        hc,
        formId,
        examenId,
    })
        .then(() => loadDerivacion({ hc, formId, examenId }))
        .catch((error) => {
            console.error("❌ Error preseleccionando derivación:", error);
            showToast(error?.message || "No se pudo seleccionar la derivación.", false);
            return {
                success: true,
                has_derivacion: false,
                derivacion_status: "error",
                derivacion: null,
            };
        });

    Promise.allSettled([corePromise, derivacionPromise]).then(
        ([coreResult, derivacionResult]) => {
            if (coreResult.status !== "fulfilled") {
                console.error("❌ Error cargando detalle de examen:", coreResult.reason);
                content.innerHTML =
                    '<p class="text-danger mb-0">No se pudo cargar la información del examen.</p>';
                return;
            }

            const { html, examen } = coreResult.value;
            content.innerHTML = html;
            renderPatientSummaryFallback(examenId || examen?.id);
            renderEstadoContext(examenId || examen?.id);

            const derivacionContainer = content.querySelector(
                "#prefacturaDerivacionContent"
            );
            if (derivacionResult.status === "fulfilled") {
                renderDerivacionContent(derivacionContainer, derivacionResult.value);
                const derivacion = derivacionResult.value?.derivacion || null;
                const vigencia = derivacion?.fecha_vigencia || null;
                const vigenciaStatus = derivacionResult.value?.vigencia_status || null;
                const estadoSugerido = derivacionResult.value?.estado_sugerido || null;
                const store = getDataStore();
                const target = Array.isArray(store)
                    ? store.find(
                        (item) =>
                            String(item.id) === String(examenId) ||
                            String(item.form_id) === String(formId)
                    )
                    : null;
                if (target && typeof target === "object") {
                    if (vigencia) {
                        target.derivacion_fecha_vigencia = vigencia;
                        target.derivacion_status = resolveDerivacionStatus(vigencia);
                    } else if (vigenciaStatus) {
                        target.derivacion_status = vigenciaStatus;
                    }
                    if (estadoSugerido) {
                        target.estado = estadoSugerido;
                        target.kanban_estado = estadoSugerido;
                    }
                }
                if (estadoSugerido && typeof window.aplicarFiltros === "function") {
                    window.aplicarFiltros();
                }
                renderEstadoContext(examenId || examen?.id);
            } else {
                console.warn("⚠️ Derivación no disponible:", derivacionResult.reason);
                if (derivacionContainer) {
                    derivacionContainer.innerHTML = buildDerivacionMissingHtml();
                }
            }
        }
    );

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
