import {getKanbanConfig} from "../config.js";
import {escapeHtml, formatDerivacionVigencia} from "./utils.js";

export function buildDerivacionMissingHtml(
    message = "Seguro particular: requiere autorización."
) {
    return `
        <div class="card border-0 shadow-sm">
            <div class="card-body d-flex flex-column gap-2">
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="badge bg-secondary">Sin derivación</span>
                    <span class="text-muted">${escapeHtml(message)}</span>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="btnSolicitarAutorizacion">
                    Solicitar autorización
                </button>
            </div>
        </div>
    `;
}

function buildDerivacionDiagnosticoHtml(derivacion) {
    const diagnosticos = Array.isArray(derivacion?.diagnosticos)
        ? derivacion.diagnosticos
        : [];
    if (diagnosticos.length) {
        const items = diagnosticos
            .map((dx) => {
                const code = escapeHtml(dx?.dx_code || "");
                const descripcion = escapeHtml(
                    dx?.descripcion || dx?.diagnostico || ""
                );
                const lateralidad = dx?.lateralidad
                    ? ` (${escapeHtml(dx.lateralidad)})`
                    : "";
                return `<li><span class="text-primary">${code}</span> — ${descripcion}${lateralidad}</li>`;
            })
            .join("");
        return `<ul class="mb-0 mt-2">${items}</ul>`;
    }

    if (derivacion?.diagnostico) {
        const items = String(derivacion.diagnostico)
            .split(";")
            .map((item) => item.trim())
            .filter(Boolean)
            .map((item) => `<li>${escapeHtml(item)}</li>`)
            .join("");
        if (items) {
            return `<ul class="mb-0 mt-2">${items}</ul>`;
        }
    }

    return '<span class="text-muted">No disponible</span>';
}

function buildDerivacionHtml(derivacion) {
    if (!derivacion) {
        return buildDerivacionMissingHtml();
    }

    const derivacionId = derivacion.derivacion_id || derivacion.id || null;
    const archivoHref = derivacionId
        ? `/derivaciones/archivo/${encodeURIComponent(String(derivacionId))}`
        : derivacion.archivo_derivacion_path
            ? `/${String(derivacion.archivo_derivacion_path).replace(/^\/+/, "")}`
            : null;
    const vigenciaInfo = formatDerivacionVigencia(derivacion.fecha_vigencia);
    const badgeHtml = vigenciaInfo.badge
        ? `<span class="badge bg-${escapeHtml(vigenciaInfo.badge.color)} ms-2">${escapeHtml(
            vigenciaInfo.badge.texto
        )}</span>`
        : "";
    const archivoHtml = archivoHref
        ? `
        <div class="alert alert-info d-flex align-items-center justify-content-between flex-wrap">
            <div>
                <strong>📎 Derivación:</strong>
                <span class="text-muted ms-1">Documento adjunto disponible.</span>
            </div>
            <a class="btn btn-sm btn-outline-primary mt-2 mt-md-0" href="${escapeHtml(
            archivoHref
        )}" target="_blank" rel="noopener">
                <i class="bi bi-file-earmark-pdf"></i> Abrir PDF
            </a>
        </div>
        `
        : "";

    return `
        ${archivoHtml}
        <div class="box box-outline-primary">
            <div class="box-header">
                <h5 class="box-title"><strong>📌 Información de la Derivación</strong></h5>
            </div>
            <ul class="list-group list-group-flush">
                <li class="list-group-item"><i class="bi bi-upc-scan"></i> <strong>Código Derivación:</strong>
                    ${escapeHtml(derivacion.cod_derivacion || "No disponible")}
                </li>
                <li class="list-group-item"><i class="bi bi-calendar-check"></i> <strong>Fecha Registro:</strong>
                    ${escapeHtml(derivacion.fecha_registro || "No disponible")}
                </li>
                <li class="list-group-item"><i class="bi bi-calendar-event"></i> <strong>Fecha Vigencia:</strong>
                    ${escapeHtml(derivacion.fecha_vigencia || "No disponible")}
                </li>
                <li class="list-group-item">
                    <i class="bi bi-hourglass-split"></i> ${vigenciaInfo.texto}
                    ${badgeHtml}
                </li>
                <li class="list-group-item">
                    <i class="bi bi-clipboard2-pulse"></i>
                    <strong>Diagnóstico:</strong>
                    ${buildDerivacionDiagnosticoHtml(derivacion)}
                </li>
            </ul>
            <div class="box-body"></div>
        </div>
    `;
}

export function renderDerivacionContent(container, payload) {
    if (!container) {
        return;
    }
    const hasDerivacion =
        payload?.success &&
        payload?.has_derivacion &&
        payload?.derivacion &&
        payload?.derivacion_status !== "missing";

    if (hasDerivacion) {
        container.innerHTML = buildDerivacionHtml(payload.derivacion);
        return;
    }

    const status = payload?.derivacion_status || "missing";
    const fallbackMessage =
        status === "error"
            ? "Derivación no disponible por ahora."
            : payload?.message || "Seguro particular: requiere autorización.";

    container.innerHTML = buildDerivacionMissingHtml(fallbackMessage);
}

export async function loadDerivacion({hc, formId}) {
    const {basePath} = getKanbanConfig();
    const derivacionUrl = `${basePath}/derivacion?hc_number=${encodeURIComponent(
        hc
    )}&form_id=${encodeURIComponent(formId)}&solicitud_id=${encodeURIComponent(
        window.__prefacturaSolicitudId || ""
    )}`;

    try {
        const response = await fetch(derivacionUrl);
        if (!response.ok) {
            return {
                success: true,
                has_derivacion: false,
                derivacion_status: "error",
                derivacion: null,
            };
        }
        return response.json();
    } catch (error) {
        return {
            success: true,
            has_derivacion: false,
            derivacion_status: "error",
            derivacion: null,
        };
    }
}

async function guardarPreseleccionDerivacion(payload) {
    const response = await fetch("/solicitudes/derivacion-preseleccion/guardar", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify(payload),
    });
    if (!response.ok) {
        throw new Error("No se pudo guardar la derivación seleccionada.");
    }
    const data = await response.json();
    if (!data?.success) {
        throw new Error("No se pudo guardar la derivación seleccionada.");
    }
    return data;
}

function buildDerivacionOptionsHtml(options, selectedValue) {
    return `
        <input type="hidden" id="derivacionSeleccionValue" value="${escapeHtml(
        selectedValue || ""
    )}">
        <div class="d-flex flex-column gap-2 text-start" id="derivacionSeleccionList">
            ${options
        .map((option) => {
            const value = String(option.pedido_id_mas_antiguo || "");
            const isSelected = value === selectedValue;
            const label = `${escapeHtml(option.codigo_derivacion || "Sin código")} · Pedido ${
                option.pedido_id_mas_antiguo || "-"
            }`;
            const meta = [
                option.lateralidad ? `Lateralidad: ${option.lateralidad}` : null,
                option.fecha_vigencia ? `Vigencia: ${option.fecha_vigencia}` : null,
                option.prefactura ? `Prefactura: ${option.prefactura}` : null,
            ]
                .filter(Boolean)
                .join(" · ");
            return `
                        <button type="button"
                                class="btn btn-light text-start border ${isSelected ? "border-primary" : ""}"
                                data-derivacion-option="true"
                                data-derivacion-value="${escapeHtml(value)}">
                            <strong>${label}</strong>
                            ${meta ? `<div class="text-muted small">${escapeHtml(meta)}</div>` : ""}
                        </button>
                    `;
        })
        .join("")}
        </div>
    `;
}

export async function asegurarPreseleccionDerivacion({hc, formId, solicitudId}) {
    if (!hc || !formId) {
        return null;
    }

    const response = await fetch("/solicitudes/derivacion-preseleccion", {
        method: "POST",
        headers: {"Content-Type": "application/json"},
        body: JSON.stringify({
            hc_number: hc,
            form_id: formId,
            solicitud_id: solicitudId,
        }),
    });

    if (!response.ok) {
        throw new Error("No se pudo obtener las derivaciones disponibles.");
    }

    const data = await response.json();
    if (data?.selected) {
        return data.selected;
    }

    const options = Array.isArray(data?.options) ? data.options : [];
    if (!data?.needs_selection || options.length === 0) {
        return null;
    }

    if (options.length === 1) {
        const option = options[0];
        await guardarPreseleccionDerivacion({
            solicitud_id: solicitudId,
            codigo_derivacion: option.codigo_derivacion,
            pedido_id_mas_antiguo: option.pedido_id_mas_antiguo,
            lateralidad: option.lateralidad,
            fecha_vigencia: option.fecha_vigencia,
            prefactura: option.prefactura,
        });
        return option;
    }

    if (typeof Swal === "undefined") {
        throw new Error("No se puede seleccionar derivación sin SweetAlert.");
    }

    const selectedValue = String(options[0]?.pedido_id_mas_antiguo || "");
    const {isConfirmed} = await Swal.fire({
        title: "Selecciona la derivación",
        html: buildDerivacionOptionsHtml(options, selectedValue),
        confirmButtonText: "Guardar selección",
        showCancelButton: true,
        cancelButtonText: "Cancelar",
        focusConfirm: false,
        didOpen: () => {
            const list = document.getElementById("derivacionSeleccionList");
            const hidden = document.getElementById("derivacionSeleccionValue");
            if (!list || !hidden) {
                return;
            }
            list.querySelectorAll("[data-derivacion-option]").forEach((btn) => {
                btn.addEventListener("click", () => {
                    const value = btn.dataset.derivacionValue || "";
                    hidden.value = value;
                    list.querySelectorAll("[data-derivacion-option]").forEach((item) => {
                        item.classList.toggle(
                            "border-primary",
                            item === btn
                        );
                    });
                });
            });
        },
        preConfirm: () => {
            const chosenValue = document.getElementById(
                "derivacionSeleccionValue"
            )?.value;
            if (!chosenValue) {
                Swal.showValidationMessage("Selecciona una derivación.");
                return null;
            }
            return chosenValue;
        },
    });

    if (!isConfirmed) {
        return null;
    }

    const chosenValue =
        document.getElementById("derivacionSeleccionValue")?.value || "";
    const chosen = options.find(
        (option) => String(option.pedido_id_mas_antiguo || "") === String(chosenValue)
    );
    if (!chosen) {
        return null;
    }

    await guardarPreseleccionDerivacion({
        solicitud_id: solicitudId,
        codigo_derivacion: chosen.codigo_derivacion,
        pedido_id_mas_antiguo: chosen.pedido_id_mas_antiguo,
        lateralidad: chosen.lateralidad,
        fecha_vigencia: chosen.fecha_vigencia,
        prefactura: chosen.prefactura,
    });

    return chosen;
}
