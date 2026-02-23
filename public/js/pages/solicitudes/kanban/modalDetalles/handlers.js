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
        if (typeof Swal === "undefined") {
            showToast("No se puede abrir el editor sin SweetAlert", false);
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

                const toDatetimeLocal = (value) => {
                    if (!value) return "";
                    const date = new Date(value);
                    if (Number.isNaN(date.getTime())) return "";
                    const pad = (n) => String(n).padStart(2, "0");
                    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(
                        date.getDate()
                    )}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
                };

                const html = `
          <div class="box box-solid">
            <div class="box-header with-border">
              <h4 class="box-title">Editar solicitud #${escapeHtml(
                    solicitudId || ""
                )}</h4>
            </div>
            <form class="form">
              <div class="box-body">
                <h4 class="box-title text-info mb-0"><i class="ti-user me-15"></i> Datos de solicitud</h4>
                <hr class="my-15">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Estado</label>
                    <input id="sol-estado" name="estado" class="form-control" value="${escapeHtml(
                    merged.estado || merged.kanban_estado || ""
                )}" placeholder="Estado" readonly />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Doctor</label>
                      <select id="sol-doctor" name="doctor" class="form-select">
                        <option value="${escapeHtml(
                    merged.doctor || merged.crm_responsable_nombre || ""
                )}">
                          ${escapeHtml(
                    merged.doctor || merged.crm_responsable_nombre || "No definido"
                )}
                        </option>
                      </select>
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Fecha</label>
                      <input id="sol-fecha" name="fecha" type="datetime-local" class="form-control" value="${escapeHtml(
                    toDatetimeLocal(merged.fecha || merged.fecha_programada)
                )}" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Prioridad</label>
                      <input id="sol-prioridad" name="prioridad" class="form-control" value="${escapeHtml(
                    merged.prioridad || merged.prioridad_automatica || "Normal"
                )}" placeholder="URGENTE / NORMAL" readonly />
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Producto</label>
                      <input id="sol-producto" name="producto" class="form-control" value="${escapeHtml(
                    baseProducto
                )}" placeholder="Producto asociado" />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Ojo</label>
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
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Afiliación</label>
                      <input id="sol-afiliacion" name="afiliacion" class="form-control" value="${escapeHtml(
                    merged.afiliacion || ""
                )}" placeholder="Afiliación" readonly />
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Duración</label>
                      <input id="sol-duracion" name="duracion" class="form-control" value="${escapeHtml(
                    merged.duracion || ""
                )}" placeholder="Minutos" readonly />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Procedimiento</label>
                  <textarea id="sol-procedimiento" name="procedimiento" class="form-control" rows="2" placeholder="Descripción">${escapeHtml(
                    merged.procedimiento || ""
                )}</textarea>
                </div>
                <div class="form-group">
                  <label class="form-label">Observación</label>
                  <textarea id="sol-observacion" name="observacion" class="form-control" rows="2" placeholder="Notas">${escapeHtml(
                    merged.observacion || ""
                )}</textarea>
                </div>

                <h4 class="box-title text-info mb-0 mt-20"><i class="ti-save me-15"></i> Lente e incisión</h4>
                <hr class="my-15">
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Lente</label>
                      <select id="sol-lente-id" name="lente_id" class="form-select" data-value="${escapeHtml(
                    lenteSeleccionada
                )}">
                        <option value="">Cargando lentes...</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Nombre de lente</label>
                      <input id="sol-lente-nombre" name="lente_nombre" class="form-control" value="${escapeHtml(
                    merged.lente_nombre || baseProducto
                )}" placeholder="Nombre del lente" />
                    </div>
                  </div>
                </div>
                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Poder del lente</label>
                      <select id="sol-lente-poder" name="lente_poder" class="form-select" data-value="${escapeHtml(
                    poderSeleccionado
                )}">
                        <option value="">Selecciona poder</option>
                      </select>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="form-label">Incisión</label>
                      <input id="sol-incision" name="incision" class="form-control" value="${escapeHtml(
                    merged.incision || ""
                )}" placeholder="Ej: Clear cornea temporal" />
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label class="form-label">Observación de lente</label>
                  <textarea id="sol-lente-obs" name="lente_observacion" class="form-control" rows="2" placeholder="Notas de lente">${escapeHtml(
                    baseObservacion
                )}</textarea>
                </div>
              </div>
            </form>
          </div>
        `;

                Swal.fire({
                    title: `Editar solicitud #${escapeHtml(solicitudId || "")}`,
                    html,
                    width: 800,
                    customClass: {popup: "cive-modal-wide"},
                    confirmButtonText: "Guardar cambios",
                    cancelButtonText: "Cancelar",
                    showCancelButton: true,
                    focusConfirm: false,
                    showLoaderOnConfirm: true,
                    allowOutsideClick: () => !Swal.isLoading(),
                    didOpen: async () => {
                        const lenteSelect = document.getElementById("sol-lente-id");
                        const poderSelect = document.getElementById("sol-lente-poder");
                        const nombreInput = document.getElementById("sol-lente-nombre");
                        if (!lenteSelect || !poderSelect) return;

                        try {
                            const lentes = await obtenerLentesCatalogo();

                            lenteSelect.innerHTML =
                                '<option value="">Selecciona lente</option>';
                            lentes.forEach((l) => {
                                const opt = document.createElement("option");
                                opt.value = l.id;
                                opt.textContent = `${l.marca ?? ""} · ${l.modelo ?? ""} · ${
                                    l.nombre ?? ""
                                }`.replace(/\s+·\s+·\s+$/, "").trim();
                                opt.dataset.nombre = l.nombre ?? "";
                                opt.dataset.poder = l.poder ?? "";
                                opt.dataset.rango_desde = l.rango_desde ?? "";
                                opt.dataset.rango_hasta = l.rango_hasta ?? "";
                                opt.dataset.rango_paso = l.rango_paso ?? "";
                                opt.dataset.rango_inicio_incremento =
                                    l.rango_inicio_incremento ?? "";
                                lenteSelect.appendChild(opt);
                            });

                            const presetLente =
                                lenteSelect.dataset.value || lenteSeleccionada || "";
                            if (presetLente) {
                                lenteSelect.value = presetLente;
                            }

                            const syncPoderes = () => {
                                const optSel = lenteSelect.selectedOptions?.[0];
                                const nombre = optSel?.dataset?.nombre || "";
                                const poderBase = optSel?.dataset?.poder || "";
                                const lenteObj = {
                                    rango_desde: optSel?.dataset?.rango_desde,
                                    rango_hasta: optSel?.dataset?.rango_hasta,
                                    rango_paso: optSel?.dataset?.rango_paso,
                                    rango_inicio_incremento: optSel?.dataset?.rango_inicio_incremento,
                                };
                                if (nombre && nombreInput && !nombreInput.value) {
                                    nombreInput.value = nombre;
                                }

                                poderSelect.innerHTML =
                                    '<option value="">Selecciona poder</option>';
                                const powers = generarPoderes(lenteObj);
                                powers.forEach((p) => {
                                    const optP = document.createElement("option");
                                    optP.value = p;
                                    optP.textContent = p;
                                    poderSelect.appendChild(optP);
                                });

                                const presetPoder =
                                    poderSelect.dataset.value ||
                                    poderSeleccionado ||
                                    poderBase ||
                                    "";
                                if (!powers.length && poderBase) {
                                    const opt = document.createElement("option");
                                    opt.value = poderBase;
                                    opt.textContent = poderBase;
                                    poderSelect.appendChild(opt);
                                }
                                if (presetPoder) {
                                    const exists = Array.from(poderSelect.options).some(
                                        (o) => o.value === presetPoder
                                    );
                                    if (!exists) {
                                        const opt = document.createElement("option");
                                        opt.value = presetPoder;
                                        opt.textContent = presetPoder;
                                        poderSelect.appendChild(opt);
                                    }
                                    poderSelect.value = presetPoder;
                                }
                            };

                            lenteSelect.addEventListener("change", syncPoderes);
                            syncPoderes();
                        } catch (error) {
                            console.warn("No se pudieron cargar lentes:", error);
                            lenteSelect.innerHTML = `<option value="${escapeHtml(
                                lenteSeleccionada
                            )}">${escapeHtml(
                                baseProducto || "Sin lentes disponibles"
                            )}</option>`;
                        }
                    },
                    preConfirm: async () => {
                        // Campos del formulario
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

                        // Usar el id real del registro (detalle) si existe.
                        // En algunos flujos el dataset id puede no coincidir con el id de solicitud_procedimiento.
                        const targetSolicitudId =
                            merged?.id ? String(merged.id) : String(solicitudId || "");

                        // ✅ Guardado interno (módulo Solicitudes): endpoint con sesión.
                        // Recomendado: POST /solicitudes/{id}/cirugia
                        // Payload mínimo: { form_id, hc_number, updates: {...} }

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
                                // Detalles quirúrgicos (LIO / incisión)
                                lente_id: lenteId,
                                lente_nombre: lenteNombre,
                                lente_poder: poder,
                                lente_observacion: lenteObservacion,
                                incision,
                                // Campo resumen
                                producto: lenteNombre || producto,
                            },
                        };

                        if (!payload.hc_number || !payload.form_id) {
                            Swal.showValidationMessage(
                                "Faltan datos para guardar (hc_number / form_id)."
                            );
                            return false;
                        }

                        try {
                            const postUrls = buildGuardarSolicitudInternalCandidates({solicitudId: targetSolicitudId});
                            console.log("[guardarCirugia] candidates:", postUrls);
                            console.log("[guardarCirugia] payload:", payload);
                            // Siempre con sesión para endpoints internos
                            const response = await fetchWithFallback(postUrls, {
                                method: "POST",
                                credentials: "include",
                                headers: {"Content-Type": "application/json"},
                                body: JSON.stringify(payload),
                            });
                            console.log("[guardarCirugia] response ok/status:", response.ok, response.status);

                            let resp = null;
                            const ct = response.headers.get("content-type") || "";
                            if (ct.includes("application/json")) {
                                resp = await response.json();
                            } else {
                                const raw = await response.text();
                                try {
                                    resp = JSON.parse(raw);
                                } catch (e) {
                                    resp = {success: response.ok, message: raw};
                                }
                            }

                            if (!resp?.success) {
                                throw new Error(resp?.message || "No se guardaron los cambios");
                            }

                            return {payload, response: resp, targetSolicitudId};
                        } catch (error) {
                            console.error("No se pudo guardar la solicitud", error);
                            Swal.showValidationMessage(
                                error?.message || "Error al guardar la solicitud"
                            );
                            return false;
                        }
                    },
                }).then((result) => {
                    if (!result.isConfirmed) return;

                    const targetSolicitudId = result.value?.targetSolicitudId || solicitudId;
                    const payload = result.value?.payload || {};
                    const responseData = result.value?.response?.data || null;
                    const store = getDataStore();
                    const item = store.find(
                        (entry) => String(entry.id) === String(targetSolicitudId)
                    );
                    if (item) {
                        // Refrescar campos visibles y detalles del lente
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
                                // LIO
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
                    // Evitar re-abrir el modal si ya está visible (puede dejar backdrops y congelar la UI).
                    const prefacturaModalEl = document.getElementById("prefacturaModal");
                    const modalIsOpen = prefacturaModalEl?.classList?.contains("show");

                    // Refrescar badges/detalle desde backend cuando sea posible
                    refreshKanbanBadgeFromDetalle({
                        hcNumber: hcNumber || solicitud.hc_number,
                        solicitudId: targetSolicitudId,
                        formId,
                    });

                    if (!modalIsOpen) {
                        abrirPrefactura({
                            hc: hcNumber || solicitud.hc_number,
                            formId,
                            solicitudId: targetSolicitudId,
                        });
                    }
                });
            })
            .catch((error) => {
                console.error("No se pudo cargar el editor de LIO", error);
                showToast("No pudimos obtener los datos del lente", false);
            });
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
