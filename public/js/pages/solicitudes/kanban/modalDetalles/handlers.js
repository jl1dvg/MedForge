import {getKanbanConfig, getDataStore, resolveWritePath} from "../config.js";
import {actualizarEstadoSolicitud} from "../estado.js";
import {showToast} from "../toast.js";
import {
    buildGuardarSolicitudInternalCandidates,
    clearSolicitudDetalleCacheBySolicitudId,
    fetchWithFallback,
    hydrateSolicitudFromDetalle,
    refreshKanbanBadgeFromDetalle,
} from "./api.js";
import {obtenerLentesCatalogo, generarPoderes} from "./lentes.js";
import {abrirPrefactura} from "./prefactura.js";
import {getPrefacturaContextFromButton} from "./prefacturaContext.js";
import {
    renderEstadoContext,
    renderPatientSummaryFallback,
} from "./state.js";
import {findSolicitudById} from "./store.js";
import {highlightSelection, resolverDataset} from "./ui.js";
import {escapeHtml} from "./utils.js";

const LIO_EDITOR_MODAL_ID = "solicitudesLioEditorModal";

function getModalApi() {
    return window.bootstrap && typeof window.bootstrap.Modal === "function"
        ? window.bootstrap.Modal
        : null;
}

function getOrCreateModalInstance(element) {
    const ModalApi = getModalApi();
    if (!ModalApi || !element) {
        return null;
    }

    if (typeof ModalApi.getOrCreateInstance === "function") {
        return ModalApi.getOrCreateInstance(element);
    }

    if (typeof ModalApi.getInstance === "function") {
        return ModalApi.getInstance(element) || new ModalApi(element);
    }

    return new ModalApi(element);
}

function ensureLioEditorModalShell() {
    let modalElement = document.getElementById(LIO_EDITOR_MODAL_ID);
    if (!modalElement) {
        modalElement = document.createElement("div");
        modalElement.id = LIO_EDITOR_MODAL_ID;
        modalElement.className = "modal fade";
        modalElement.tabIndex = -1;
        modalElement.setAttribute("aria-hidden", "true");
        modalElement.setAttribute("aria-labelledby", `${LIO_EDITOR_MODAL_ID}Label`);
        modalElement.innerHTML = `
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="${LIO_EDITOR_MODAL_ID}Label" data-lio-editor-title>Editar cirugía</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-danger d-none" role="alert" data-lio-editor-error></div>
                        <div data-lio-editor-body></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal" data-lio-editor-cancel>Cancelar</button>
                        <button type="button" class="btn btn-primary" data-lio-editor-save>Guardar cambios</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modalElement);
    }

    return {
        element: modalElement,
        title: modalElement.querySelector("[data-lio-editor-title]"),
        body: modalElement.querySelector("[data-lio-editor-body]"),
        error: modalElement.querySelector("[data-lio-editor-error]"),
        saveButton: modalElement.querySelector("[data-lio-editor-save]"),
    };
}

function setLioEditorError(parts, message = "") {
    if (!parts?.error) {
        return;
    }

    if (!message) {
        parts.error.textContent = "";
        parts.error.classList.add("d-none");
        return;
    }

    parts.error.textContent = String(message);
    parts.error.classList.remove("d-none");
}

function setLioEditorSaving(parts, saving) {
    if (!parts?.saveButton) {
        return;
    }

    const button = parts.saveButton;
    const defaultLabel = button.dataset.defaultLabel || button.textContent.trim() || "Guardar cambios";
    button.dataset.defaultLabel = defaultLabel;
    button.disabled = Boolean(saving);
    button.innerHTML = saving
        ? '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Guardando...'
        : escapeHtml(defaultLabel);
}

function toDatetimeLocal(value) {
    if (!value) {
        return "";
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return "";
    }

    const pad = (number) => String(number).padStart(2, "0");
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
        date.getDate()
    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

function buildLioEditorFormHtml({
    merged,
    solicitudId,
    baseProducto,
    baseObservacion,
    lenteSeleccionada,
    poderSeleccionado,
}) {
    return `
        <form id="lioEditorForm" class="container-fluid px-0">
            <div class="row g-3">
                <div class="col-12">
                    <h6 class="text-primary mb-0">Solicitud #${escapeHtml(solicitudId || "")}</h6>
                    <small class="text-muted">Actualiza los datos clínicos y quirúrgicos de la solicitud.</small>
                </div>

                <div class="col-12">
                    <h6 class="mb-0">Datos de solicitud</h6>
                    <hr class="mt-2 mb-0">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sol-estado">Estado</label>
                    <input id="sol-estado" name="estado" class="form-control" value="${escapeHtml(
                        merged.estado || merged.kanban_estado || ""
                    )}" placeholder="Estado" readonly />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sol-doctor">Doctor</label>
                    <input id="sol-doctor" name="doctor" class="form-control" value="${escapeHtml(
                        merged.doctor || merged.crm_responsable_nombre || ""
                    )}" placeholder="Doctor responsable" />
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sol-fecha">Fecha</label>
                    <input id="sol-fecha" name="fecha" type="datetime-local" class="form-control" value="${escapeHtml(
                        toDatetimeLocal(merged.fecha || merged.fecha_programada)
                    )}" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sol-prioridad">Prioridad</label>
                    <input id="sol-prioridad" name="prioridad" class="form-control" value="${escapeHtml(
                        merged.prioridad || merged.prioridad_automatica || "Normal"
                    )}" placeholder="URGENTE / NORMAL" readonly />
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sol-producto">Producto</label>
                    <input id="sol-producto" name="producto" class="form-control" value="${escapeHtml(
                        baseProducto
                    )}" placeholder="Producto asociado" />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sol-ojo">Ojo</label>
                    <select id="sol-ojo" name="ojo" class="form-select">
                        <option value="">Selecciona ojo</option>
                        <option value="DERECHO"${
                            (merged.ojo || "").toUpperCase() === "DERECHO" ? " selected" : ""
                        }>DERECHO</option>
                        <option value="IZQUIERDO"${
                            (merged.ojo || "").toUpperCase() === "IZQUIERDO" ? " selected" : ""
                        }>IZQUIERDO</option>
                        <option value="AMBOS OJOS"${
                            (merged.ojo || "").toUpperCase() === "AMBOS OJOS" ? " selected" : ""
                        }>AMBOS OJOS</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sol-afiliacion">Afiliación</label>
                    <input id="sol-afiliacion" name="afiliacion" class="form-control" value="${escapeHtml(
                        merged.afiliacion || ""
                    )}" placeholder="Afiliación" readonly />
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sol-duracion">Duración</label>
                    <input id="sol-duracion" name="duracion" class="form-control" value="${escapeHtml(
                        merged.duracion || ""
                    )}" placeholder="Minutos" readonly />
                </div>

                <div class="col-12">
                    <label class="form-label" for="sol-procedimiento">Procedimiento</label>
                    <textarea id="sol-procedimiento" name="procedimiento" class="form-control" rows="2" placeholder="Descripción">${escapeHtml(
                        merged.procedimiento || ""
                    )}</textarea>
                </div>
                <div class="col-12">
                    <label class="form-label" for="sol-observacion">Observación</label>
                    <textarea id="sol-observacion" name="observacion" class="form-control" rows="2" placeholder="Notas">${escapeHtml(
                        merged.observacion || ""
                    )}</textarea>
                </div>

                <div class="col-12 pt-2">
                    <h6 class="mb-0">Lente e incisión</h6>
                    <hr class="mt-2 mb-0">
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sol-lente-id">Lente</label>
                    <select id="sol-lente-id" name="lente_id" class="form-select" data-value="${escapeHtml(
                        lenteSeleccionada
                    )}">
                        <option value="">Cargando lentes...</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sol-lente-nombre">Nombre de lente</label>
                    <input id="sol-lente-nombre" name="lente_nombre" class="form-control" value="${escapeHtml(
                        merged.lente_nombre || baseProducto
                    )}" placeholder="Nombre del lente" />
                </div>

                <div class="col-md-6">
                    <label class="form-label" for="sol-lente-poder">Poder del lente</label>
                    <select id="sol-lente-poder" name="lente_poder" class="form-select" data-value="${escapeHtml(
                        poderSeleccionado
                    )}">
                        <option value="">Selecciona poder</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="sol-incision">Incisión</label>
                    <input id="sol-incision" name="incision" class="form-control" value="${escapeHtml(
                        merged.incision || ""
                    )}" placeholder="Ej: Clear cornea temporal" />
                </div>

                <div class="col-12">
                    <label class="form-label" for="sol-lente-obs">Observación de lente</label>
                    <textarea id="sol-lente-obs" name="lente_observacion" class="form-control" rows="2" placeholder="Notas de lente">${escapeHtml(
                        baseObservacion
                    )}</textarea>
                </div>
            </div>
        </form>
    `;
}

function syncLioEditorPoderes({
    lenteSelect,
    poderSelect,
    nombreInput,
    poderSeleccionado,
}) {
    const selectedOption = lenteSelect?.selectedOptions?.[0];
    const nombre = selectedOption?.dataset?.nombre || "";
    const poderBase = selectedOption?.dataset?.poder || "";
    const lenteObj = {
        rango_desde: selectedOption?.dataset?.rango_desde,
        rango_hasta: selectedOption?.dataset?.rango_hasta,
        rango_paso: selectedOption?.dataset?.rango_paso,
        rango_inicio_incremento: selectedOption?.dataset?.rango_inicio_incremento,
    };

    if (nombre && nombreInput && !nombreInput.value.trim()) {
        nombreInput.value = nombre;
    }

    poderSelect.innerHTML = '<option value="">Selecciona poder</option>';
    const powers = generarPoderes(lenteObj);
    powers.forEach((power) => {
        const option = document.createElement("option");
        option.value = power;
        option.textContent = power;
        poderSelect.appendChild(option);
    });

    const presetPoder =
        poderSelect.dataset.value ||
        poderSeleccionado ||
        poderBase ||
        "";

    if (!powers.length && poderBase) {
        const option = document.createElement("option");
        option.value = poderBase;
        option.textContent = poderBase;
        poderSelect.appendChild(option);
    }

    if (!presetPoder) {
        return;
    }

    const exists = Array.from(poderSelect.options).some((option) => option.value === presetPoder);
    if (!exists) {
        const option = document.createElement("option");
        option.value = presetPoder;
        option.textContent = presetPoder;
        poderSelect.appendChild(option);
    }
    poderSelect.value = presetPoder;
}

async function hydrateLioEditorLensFields({
    lenteSeleccionada,
    poderSeleccionado,
    baseProducto,
}) {
    const lenteSelect = document.getElementById("sol-lente-id");
    const poderSelect = document.getElementById("sol-lente-poder");
    const nombreInput = document.getElementById("sol-lente-nombre");
    if (!lenteSelect || !poderSelect) {
        return;
    }

    try {
        const lentes = await obtenerLentesCatalogo();
        lenteSelect.innerHTML = '<option value="">Selecciona lente</option>';

        lentes.forEach((lente) => {
            const option = document.createElement("option");
            option.value = lente.id;
            option.textContent = `${lente.marca ?? ""} · ${lente.modelo ?? ""} · ${
                lente.nombre ?? ""
            }`.replace(/\s+·\s+·\s+$/, "").trim();
            option.dataset.nombre = lente.nombre ?? "";
            option.dataset.poder = lente.poder ?? "";
            option.dataset.rango_desde = lente.rango_desde ?? "";
            option.dataset.rango_hasta = lente.rango_hasta ?? "";
            option.dataset.rango_paso = lente.rango_paso ?? "";
            option.dataset.rango_inicio_incremento = lente.rango_inicio_incremento ?? "";
            lenteSelect.appendChild(option);
        });

        const presetLente = lenteSelect.dataset.value || lenteSeleccionada || "";
        if (presetLente) {
            lenteSelect.value = presetLente;
        }

        const sync = () =>
            syncLioEditorPoderes({
                lenteSelect,
                poderSelect,
                nombreInput,
                poderSeleccionado,
            });

        lenteSelect.addEventListener("change", sync);
        sync();
    } catch (error) {
        console.warn("No se pudieron cargar lentes:", error);
        lenteSelect.innerHTML = `<option value="${escapeHtml(
            lenteSeleccionada
        )}">${escapeHtml(baseProducto || "Sin lentes disponibles")}</option>`;
    }
}

function buildLioEditorPayload({
    merged,
    solicitud,
    solicitudId,
    formId,
    hcNumber,
}) {
    const producto =
        document.getElementById("sol-producto")?.value.trim() || "";
    const poder =
        document.getElementById("sol-lente-poder")?.value.trim() || "";
    const lenteId =
        document.getElementById("sol-lente-id")?.value.trim() || "";
    const lenteNombre =
        document.getElementById("sol-lente-nombre")?.value.trim() || producto;
    const ojo = document.getElementById("sol-ojo")?.value.trim() || "";
    const incision =
        document.getElementById("sol-incision")?.value.trim() || "";
    const lenteObservacion =
        document.getElementById("sol-lente-obs")?.value.trim() || "";
    const observacion =
        document.getElementById("sol-observacion")?.value.trim() || "";
    const procedimiento =
        document.getElementById("sol-procedimiento")?.value.trim() || "";
    const fecha = document.getElementById("sol-fecha")?.value.trim() || "";
    const doctor = document.getElementById("sol-doctor")?.value.trim() || "";

    const targetSolicitudId =
        merged?.id ? String(merged.id) : String(solicitudId || "");

    const payload = {
        solicitud_id: targetSolicitudId,
        form_id: formId || merged.form_id || solicitud.form_id || "",
        hc_number: hcNumber || merged.hc_number || solicitud.hc_number || "",
        updates: {
            doctor,
            fecha,
            ojo,
            procedimiento,
            observacion,
            lente_id: lenteId,
            lente_nombre: lenteNombre,
            lente_poder: poder,
            lente_observacion: lenteObservacion,
            incision,
            producto: lenteNombre || producto,
        },
    };

    if (!payload.hc_number || !payload.form_id) {
        throw new Error("Faltan datos para guardar (hc_number / form_id).");
    }

    return {payload, targetSolicitudId};
}

async function persistLioEditorPayload({payload, targetSolicitudId}) {
    const postUrls = buildGuardarSolicitudInternalCandidates({solicitudId: targetSolicitudId});
    console.log("[guardarCirugia] candidates:", postUrls);
    console.log("[guardarCirugia] payload:", payload);

    const response = await fetchWithFallback(postUrls, {
        method: "POST",
        credentials: "include",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(payload),
    });
    console.log("[guardarCirugia] response ok/status:", response.ok, response.status);

    let resp = null;
    const contentType = response.headers.get("content-type") || "";
    if (contentType.includes("application/json")) {
        resp = await response.json();
    } else {
        const raw = await response.text();
        try {
            resp = JSON.parse(raw);
        } catch (error) {
            resp = {success: response.ok, message: raw};
        }
    }

    if (!resp?.success) {
        throw new Error(resp?.message || "No se guardaron los cambios");
    }

    return {payload, response: resp, targetSolicitudId};
}

function applyLioEditorResult({
    result,
    hcNumber,
    formId,
    solicitud,
}) {
    const targetSolicitudId = result?.targetSolicitudId || "";
    const payload = result?.payload || {};
    const responseData = result?.response?.data || null;
    const store = getDataStore();
    const item = Array.isArray(store)
        ? store.find((entry) => String(entry.id) === String(targetSolicitudId))
        : null;

    if (item) {
        const updated = payload?.updates || {};
        Object.assign(
            item,
            {
                doctor: updated.doctor ?? item.doctor,
                fecha: updated.fecha ?? item.fecha,
                ojo: updated.ojo ?? item.ojo,
                producto: updated.producto ?? item.producto,
                procedimiento: updated.procedimiento ?? item.procedimiento,
                observacion: updated.observacion ?? item.observacion,
                lente_id: updated.lente_id ?? item.lente_id,
                lente_nombre: updated.lente_nombre ?? item.lente_nombre,
                lente_poder: updated.lente_poder ?? item.lente_poder,
                lente_observacion:
                    updated.lente_observacion ?? item.lente_observacion,
                incision: updated.incision ?? item.incision,
            },
            responseData || {}
        );
        delete item.detalle_hidratado;
    }

    clearSolicitudDetalleCacheBySolicitudId(targetSolicitudId);
    showToast("Solicitud actualizada", true);
    renderEstadoContext(targetSolicitudId);

    if (typeof window.aplicarFiltros === "function") {
        window.aplicarFiltros();
    }

    refreshKanbanBadgeFromDetalle({
        hcNumber: hcNumber || solicitud.hc_number,
        solicitudId: targetSolicitudId,
        formId,
    });

    return targetSolicitudId;
}

export function openLioEditor({
    solicitudId,
    formId,
    hcNumber,
    solicitud,
}) {
    const ModalApi = getModalApi();
    if (!ModalApi) {
        showToast("No se puede abrir el editor sin Bootstrap modal", false);
        return;
    }

    hydrateSolicitudFromDetalle({
        solicitudId,
        formId,
        hcNumber: hcNumber || solicitud.hc_number,
    })
        .then((detalle) => {
            const merged = {...solicitud, ...detalle};
            const baseProducto =
                merged.lente_nombre || merged.producto || merged.lente_brand || "";
            const baseObservacion =
                merged.lente_observacion || merged.observacion || "";
            const lenteSeleccionada = merged.lente_id || "";
            const poderSeleccionado = merged.lente_poder || merged.poder || "";
            const shell = ensureLioEditorModalShell();
            const editorModal = getOrCreateModalInstance(shell.element);
            const prefacturaModalEl = document.getElementById("prefacturaModal");
            const prefacturaWasOpen = prefacturaModalEl?.classList?.contains("show");
            let reopenPrefacturaOnClose = Boolean(prefacturaWasOpen);
            let reopenSolicitudId = solicitudId;

            const showEditor = () => {
                if (!editorModal || !shell.title || !shell.body || !shell.saveButton) {
                    showToast("No se pudo inicializar el modal del editor", false);
                    if (reopenPrefacturaOnClose) {
                        abrirPrefactura({
                            hc: hcNumber || merged.hc_number || solicitud.hc_number,
                            formId: formId || merged.form_id || solicitud.form_id,
                            solicitudId: reopenSolicitudId,
                        });
                    }
                    return;
                }

                shell.title.textContent = `Editar cirugía #${String(solicitudId || "").trim()}`;
                shell.body.innerHTML = buildLioEditorFormHtml({
                    merged,
                    solicitudId,
                    baseProducto,
                    baseObservacion,
                    lenteSeleccionada,
                    poderSeleccionado,
                });
                setLioEditorError(shell, "");
                setLioEditorSaving(shell, false);

                const reopenPrefactura = () => {
                    if (!reopenPrefacturaOnClose) {
                        return;
                    }
                    reopenPrefacturaOnClose = false;
                    abrirPrefactura({
                        hc: hcNumber || merged.hc_number || solicitud.hc_number,
                        formId: formId || merged.form_id || solicitud.form_id,
                        solicitudId: reopenSolicitudId,
                    });
                };

                shell.element.addEventListener(
                    "hidden.bs.modal",
                    () => {
                        setLioEditorError(shell, "");
                        setLioEditorSaving(shell, false);
                        shell.body.innerHTML = "";
                        shell.saveButton.onclick = null;
                        reopenPrefactura();
                    },
                    {once: true}
                );

                const saveChanges = async () => {
                    try {
                        setLioEditorError(shell, "");
                        setLioEditorSaving(shell, true);

                        const payloadResult = buildLioEditorPayload({
                            merged,
                            solicitud,
                            solicitudId,
                            formId,
                            hcNumber,
                        });
                        const savedResult = await persistLioEditorPayload(payloadResult);
                        reopenSolicitudId = applyLioEditorResult({
                            result: savedResult,
                            hcNumber,
                            formId,
                            solicitud,
                        });
                        editorModal.hide();
                    } catch (error) {
                        console.error("No se pudo guardar la solicitud", error);
                        setLioEditorError(
                            shell,
                            error?.message || "Error al guardar la solicitud"
                        );
                        setLioEditorSaving(shell, false);
                    }
                };

                shell.saveButton.onclick = () => {
                    saveChanges();
                };

                const form = shell.body.querySelector("#lioEditorForm");
                if (form) {
                    form.addEventListener("submit", (event) => {
                        event.preventDefault();
                        saveChanges();
                    });
                }

                hydrateLioEditorLensFields({
                    lenteSeleccionada,
                    poderSeleccionado,
                    baseProducto,
                });

                editorModal.show();
            };

            if (prefacturaWasOpen && prefacturaModalEl) {
                const prefacturaModal = getOrCreateModalInstance(prefacturaModalEl);
                if (prefacturaModal) {
                    prefacturaModalEl.addEventListener("hidden.bs.modal", showEditor, {
                        once: true,
                    });
                    prefacturaModal.hide();
                    return;
                }
            }

            showEditor();
        })
        .catch((error) => {
            console.error("No se pudo cargar el editor de LIO", error);
            showToast("No pudimos obtener los datos del lente", false);
        });
}

export function handlePrefacturaClick(event) {
    const trigger = event.target.closest("[data-prefactura-trigger]");
    if (!trigger) {
        return;
    }

    const {hc, formId, solicitudId} = resolverDataset(trigger);
    highlightSelection({cardId: solicitudId, rowId: solicitudId});
    renderEstadoContext(solicitudId);
    renderPatientSummaryFallback(solicitudId);
    abrirPrefactura({hc, formId, solicitudId});
}

export function handleContextualAction(event) {
    const button = event.target.closest("[data-context-action]");
    if (!button) {
        return;
    }

    const action = button.dataset.contextAction;
    const solicitudId = button.dataset.id;
    const formId = button.dataset.formId;
    const hcNumber = button.dataset.hc;
    const basePath = button.dataset.basePath || getKanbanConfig().basePath;

    const solicitud = findSolicitudById(solicitudId) || {};

    if (action === "confirmar-oftalmo") {
        console.log("[DEBUG] confirmar-oftalmo", {solicitudId, formId, hcNumber});

        actualizarEstadoSolicitud(
            solicitudId,
            formId,
            "apto-oftalmologo",
            getDataStore(),
            window.aplicarFiltros,
            {force: true, completado: true}
        )
            .then(() => {
                renderEstadoContext(solicitudId);
                renderPatientSummaryFallback(solicitudId);
            })
            .catch(() => {
            });
        return;
    }

    if (action === "confirmar-anestesia") {
        actualizarEstadoSolicitud(
            solicitudId,
            formId,
            "apto-anestesia",
            getDataStore(),
            window.aplicarFiltros,
            {force: true, completado: true}
        )
            .then(() => {
                renderEstadoContext(solicitudId);
                renderPatientSummaryFallback(solicitudId);
            })
            .catch(() => {
            });
        return;
    }

    if (action === "generar-agenda") {
        if (!solicitudId || !formId) {
            showToast("No se puede generar la agenda sin solicitud válida", false);
            return;
        }

        const url = `${basePath}/agenda?hc_number=${encodeURIComponent(
            solicitud.hc_number || ""
        )}&form_id=${encodeURIComponent(formId)}`;
        window.open(url, "_blank", "noopener");
    }

    if (action === "editar-lio") {
        openLioEditor({
            solicitudId,
            formId,
            hcNumber,
            solicitud,
        });
        return;
    }
}

export async function handleRescrapeDerivacion(event) {
    const button = event.target.closest("#btnRescrapeDerivacion");
    if (!button) {
        return;
    }

    const {formId, hcNumber, solicitudId} = getPrefacturaContextFromButton(button);
    if (!formId || !hcNumber) {
        showToast("Faltan datos (form_id / hc_number) para re-scrapear", false);
        return;
    }

    const defaultLabel = button.dataset.defaultLabel || button.textContent.trim();
    button.dataset.defaultLabel = defaultLabel;
    button.disabled = true;
    button.textContent = "⏳ Re-scrapeando…";

    try {
        const response = await fetch(resolveWritePath("/solicitudes/re-scrape-derivacion"), {
            method: "POST",
            headers: {"Content-Type": "application/json"},
            body: JSON.stringify({
                form_id: formId,
                hc_number: hcNumber,
                solicitud_id: solicitudId,
            }),
            credentials: "include",
        });

        let data = null;
        let rawText = "";
        const contentType = response.headers.get("content-type") || "";
        if (contentType.includes("application/json")) {
            data = await response.json();
        } else {
            rawText = await response.text();
            try {
                data = JSON.parse(rawText);
            } catch (error) {
                data = null;
            }
        }

        if (!response.ok || !data?.success) {
            const errorMessage =
                data?.message ||
                data?.error ||
                "No se pudo re-scrapear la derivación";
            showToast(errorMessage, false);
            return;
        }

        showToast(
            data?.saved
                ? "Derivación actualizada correctamente"
                : "Scraping completado, sin cambios guardados",
            true
        );

        if (solicitudId) {
            clearSolicitudDetalleCacheBySolicitudId(solicitudId);
            const store = getDataStore();
            const item = Array.isArray(store)
                ? store.find((entry) => String(entry.id) === String(solicitudId))
                : null;
            if (item && typeof item === "object") {
                delete item.detalle_hidratado;
            }
        }
        if (typeof window.aplicarFiltros === "function") {
            window.aplicarFiltros();
        }
        abrirPrefactura({hc: hcNumber, formId, solicitudId});
    } catch (error) {
        console.error("Error re-scrapeando derivación", error);
        showToast(
            error?.message || "No se pudo re-scrapear la derivación",
            false
        );
    } finally {
        button.disabled = false;
        button.textContent = button.dataset.defaultLabel || "🔄 Re-scrapear derivación";
    }
}
