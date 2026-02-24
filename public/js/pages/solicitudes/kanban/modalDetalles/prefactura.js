import {getKanbanConfig, getTableBodySelector} from "../config.js";
import {attachPrefacturaCoberturaMail} from "../botonesModal.js";
import {updateKanbanCardSla} from "../renderer.js";
import {showToast} from "../toast.js";
import {loadSolicitudCore, refreshKanbanBadgeFromDetalle} from "./api.js";
import {
    asegurarPreseleccionDerivacion,
    buildDerivacionMissingHtml,
    loadDerivacion,
    renderDerivacionContent,
} from "./derivacion.js";
import {syncPrefacturaContext} from "./prefacturaContext.js";
import {
    buildContextualActionsHtml,
    relocatePatientAlert,
    renderEstadoContext,
    resetEstadoContext,
    resetPatientSummary,
} from "./state.js";
import {initSigcenterPanel} from "./sigcenter.js";
import {findSolicitudById} from "./store.js";
import {getDataStore} from "../config.js";

export function relocateExamenesPrequirurgicosButton(content) {
    const button = document.getElementById("btnSolicitarExamenesPrequirurgicos");
    if (!button || !content) {
        return;
    }

    const targetFooter = content.querySelector(
        "#prefactura-tab-oftalmo .card:first-of-type .card-footer"
    );

    if (targetFooter) {
        button.classList.add("ms-2");
        button.classList.remove("d-none");
        targetFooter.appendChild(button);
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

export function actualizarBotonesModal(solicitudId, solicitudFallback = null) {
    const solicitud = findSolicitudById(solicitudId) || solicitudFallback;
    const normalize = (v) =>
        (v ?? "")
            .toString()
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, "-");

    const estadoRaw = solicitud
        ? (solicitud.kanban_estado || solicitud.estado || solicitud.estado_label)
        : "";
    const estado = estadoRaw ? normalize(estadoRaw) : "";

    const btnGenerarTurno = document.getElementById("btnGenerarTurnoModal");
    const btnEnAtencion = document.getElementById("btnMarcarAtencionModal");
    const btnRevisar = document.getElementById("btnRevisarCodigos");
    const btnCobertura = document.getElementById("btnSolicitarCobertura");
    const btnCoberturaExitosa = document.getElementById("btnCoberturaExitosa");

    const show = (el, visible) => {
        if (!el) return;
        el.classList.toggle("d-none", !visible);
    };

    const canShow = Boolean(estado);

    show(btnGenerarTurno, canShow && estado === "recibida");
    show(btnEnAtencion, canShow && estado === "llamado");
    show(btnRevisar, canShow && estado === "revision-codigos");
    const coberturaStates = new Set([
        "recibida",
        "en-atencion",
        "revision-codigos",
        "revision-codigo",
        "cobertura",
        "espera-documentos",
    ]);
    show(btnCobertura, canShow && coberturaStates.has(estado));
    show(
        btnCoberturaExitosa,
        canShow && (estado === "en-atencion" || estado === "revision-codigos")
    );
    console.log("[botones] estado raw:", estadoRaw);
    console.log("[botones] estado normalized:", estado);
}

export function abrirPrefactura({hc, formId, solicitudId}) {
    if (!hc || !formId) {
        console.warn(
            "⚠️ No se encontró hc_number o form_id en la selección actual"
        );
        return;
    }

    const modalElement = document.getElementById("prefacturaModal");
    const modal = new bootstrap.Modal(modalElement);
    const content = document.getElementById("prefacturaContent");

    window.__prefacturaSolicitudId = solicitudId || null;
    syncPrefacturaContext({formId, hcNumber: hc, solicitudId});
    parkExamenesPrequirurgicosButton(modalElement);

    content.innerHTML = `
        <div class="d-flex align-items-center justify-content-center py-5">
            <div class="spinner-border text-primary me-2" role="status" aria-hidden="true"></div>
            <strong>Cargando información...</strong>
        </div>
    `;

    modal.show();
    actualizarBotonesModal(solicitudId);

    const corePromise = loadSolicitudCore({hc, formId, solicitudId});
    const derivacionPromise = asegurarPreseleccionDerivacion({
        hc,
        formId,
        solicitudId,
    })
        .then(async (selected) => {
            const payload = await loadDerivacion({hc, formId});
            const hasDerivacion =
                payload?.success &&
                payload?.has_derivacion &&
                payload?.derivacion &&
                payload?.derivacion_status !== "missing";

            if (hasDerivacion) {
                return payload;
            }

            if (selected && typeof selected === "object") {
                const codigo = String(selected.codigo_derivacion || "").trim();
                const pedido = String(selected.pedido_id_mas_antiguo || "").trim();
                if (codigo && pedido) {
                    return {
                        success: true,
                        has_derivacion: true,
                        derivacion_status: "ok",
                        message: null,
                        derivacion: {
                            derivacion_id: null,
                            id: null,
                            cod_derivacion: codigo,
                            codigo_derivacion: codigo,
                            form_id: pedido,
                            hc_number: hc,
                            fecha_creacion: null,
                            fecha_registro: null,
                            fecha_vigencia: selected.fecha_vigencia || null,
                            referido: null,
                            diagnostico: null,
                            sede: null,
                            parentesco: null,
                            archivo_derivacion_path: null,
                            lateralidad: selected.lateralidad || null,
                            prefactura: selected.prefactura || null,
                            source: "ui_selected_fallback",
                        },
                    };
                }
            }

            return payload;
        })
        .catch((error) => {
            console.error("❌ Error preseleccionando derivación:", error);
            showToast(
                error?.message || "No se pudo seleccionar la derivación.",
                false
            );
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
                console.error("❌ Error cargando prefactura:", coreResult.reason);
                content.innerHTML =
                    '<p class="text-danger mb-0">No se pudo cargar la información de la solicitud.</p>';
                return;
            }

            const {html, solicitud} = coreResult.value;
            const contextual = buildContextualActionsHtml(solicitud || {});
            content.innerHTML = `${contextual}${html}`;
            updateKanbanCardSla(solicitud);
            relocatePatientAlert(solicitudId);
            renderEstadoContext(solicitudId);
            actualizarBotonesModal(solicitudId, solicitud);
            attachPrefacturaCoberturaMail();

            const actionsContainer = document.getElementById(
                "prefacturaContextualActions"
            );
            if (actionsContainer) {
                const panels = content.querySelectorAll(
                    "#prefacturaAnestesiaPanel, #prefacturaAgendaPanel"
                );
                panels.forEach((panel) => actionsContainer.appendChild(panel));
            }

            relocateExamenesPrequirurgicosButton(content);

            const header = content.querySelector(".prefactura-detail-header");
            const tabs = content.querySelector("#prefacturaTabs");
            if (header && tabs) {
                const headerHeight = Math.ceil(
                    header.getBoundingClientRect().height
                );
                tabs.style.setProperty(
                    "--prefactura-header-height",
                    `${headerHeight}px`
                );
            }

            const derivacionContainer = content.querySelector(
                "#prefacturaDerivacionContent"
            );
            if (derivacionResult.status === "fulfilled") {
                renderDerivacionContent(derivacionContainer, derivacionResult.value);
                const derivacion = derivacionResult.value?.derivacion || null;
                const vigencia = derivacion?.fecha_vigencia || null;
                if (vigencia) {
                    const store = getDataStore();
                    const target = Array.isArray(store)
                        ? store.find(
                            (item) =>
                                String(item.id) === String(solicitudId) ||
                                String(item.form_id) === String(formId)
                        )
                        : null;
                    if (target && typeof target === "object") {
                        target.derivacion_fecha_vigencia = vigencia;

                        const vigenciaDate = new Date(vigencia);
                        if (!Number.isNaN(vigenciaDate.getTime())) {
                            const isVigente = vigenciaDate.getTime() >= Date.now();
                            if (
                                String(target.sla_status || "").toLowerCase() === "vencido" &&
                                isVigente
                            ) {
                                target.sla_status = "en_rango";
                                target.sla_deadline = vigenciaDate.toISOString();
                                target.sla_hours_remaining =
                                    (vigenciaDate.getTime() - Date.now()) / 3600000;
                            }
                        }

                        updateKanbanCardSla(target);
                        renderEstadoContext(solicitudId);
                    }
                }
            } else {
                console.warn(
                    "⚠️ Derivación no disponible:",
                    derivacionResult.reason
                );
                if (derivacionContainer) {
                    derivacionContainer.innerHTML = buildDerivacionMissingHtml();
                }
            }

            initSigcenterPanel(content);

            refreshKanbanBadgeFromDetalle({
                hcNumber: hc,
                solicitudId,
                formId,
            });
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
        {once: true}
    );
}
