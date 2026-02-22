import {getKanbanConfig, resolveReadPath} from "../config.js";
import {fetchDetalleSolicitud, fetchWithFallback, resolveApiBasePath} from "./api.js";
import {findSolicitudById} from "./store.js";
import {extractProcedimientoCodigo, lateralidadToId, resolveLateralidad} from "./utils.js";

export function initSigcenterPanel(container) {
    const root = container || document;
    const SIGCENTER_DEBUG =
        typeof window !== "undefined" && typeof window.__SIGCENTER_DEBUG !== "undefined"
            ? Boolean(window.__SIGCENTER_DEBUG)
            : true;

    const absUrl = (path) => {
        try {
            return new URL(path, window.location.origin).href;
        } catch (e) {
            return String(path);
        }
    };

    const sanitizePayload = (payload) => {
        if (!payload || typeof payload !== "object") return payload;
        const sanitized = Array.isArray(payload) ? [...payload] : {...payload};
        ["sigcenter_pass", "password", "sigcenter_password"].forEach((key) => {
            if (Object.prototype.hasOwnProperty.call(sanitized, key)) {
                sanitized[key] = "******";
            }
        });
        return sanitized;
    };

    const logReq = (label, url, payload) => {
        if (!SIGCENTER_DEBUG) return;
        console.groupCollapsed(`%c[Sigcenter][REQ] ${label}`, "color:#0d6efd");
        console.log("url:", absUrl(url));
        console.log("payload:", sanitizePayload(payload));
        console.groupEnd();
    };

    const logRes = (label, res, raw) => {
        if (!SIGCENTER_DEBUG) return;
        console.groupCollapsed(`%c[Sigcenter][RES] ${label}`, "color:#198754");
        console.log("url:", res?.url);
        console.log("status:", res?.status, res?.statusText);
        console.log("raw:", raw);
        console.groupEnd();
    };

    // --- Begin Sigcenter API candidate helpers ---
    const buildSigcenterApiCandidates = (filename) => {
        const apiBase = resolveApiBasePath();
        const {basePath} = getKanbanConfig();
        const normalizedBase =
            basePath && basePath !== "/" ? String(basePath).replace(/\/+$/g, "") : "";

        const path = filename.startsWith("/") ? filename : `/sigcenter/${filename}`;

        const candidates = new Set();

        // Canonical
        candidates.add(`/api${path}`);

        // Using configured apiBasePath
        candidates.add(`${apiBase}${path}`);

        // Some deployments mount api under the module basePath
        if (normalizedBase) {
            candidates.add(`${normalizedBase}/api${path}`);
            candidates.add(`${normalizedBase}${apiBase}${path}`);
        }

        // Absolute (helps when running inside nested routes)
        try {
            const origin = window.location.origin;
            Array.from(candidates).forEach((u) => {
                if (u.startsWith("/")) candidates.add(`${origin}${u}`);
            });
        } catch (e) {
            // ignore
        }

        return Array.from(candidates);
    };

    const fetchSigcenter = async (label, filename, options) => {
        const candidates = buildSigcenterApiCandidates(filename);
        if (SIGCENTER_DEBUG) {
            console.groupCollapsed(`%c[Sigcenter][CANDIDATES] ${label}`, "color:#6f42c1");
            console.log("candidates:", candidates);
            console.groupEnd();
        }
        return fetchWithFallback(candidates, options);
    };
    // --- End Sigcenter API candidate helpers ---
    const card = root.querySelector("#prefacturaSigcenterCard");
    if (!card) return;

    const controls = card.querySelector("[data-sigcenter-controls]");
    const unavailable = card.querySelector("[data-sigcenter-unavailable]");
    const noWorker = card.querySelector("[data-sigcenter-no-worker]");
    const currentAlert = card.querySelector("[data-sigcenter-current]");
    const currentFecha = card.querySelector("[data-sigcenter-current-fecha]");
    const currentAgenda = card.querySelector("[data-sigcenter-current-agenda]");
    const sedeSelect = card.querySelector("[data-sigcenter-sede]");
    const loadDaysBtn = card.querySelector("[data-sigcenter-load-days]");
    const daysContainer = card.querySelector("[data-sigcenter-days]");
    const timesContainer = card.querySelector("[data-sigcenter-times]");
    const scheduleBtn = card.querySelector("[data-sigcenter-agendar]");
    const arrivalInput = card.querySelector("[data-sigcenter-arrival]");
    const selectedLabel = card.querySelector("[data-sigcenter-selected]");
    const status = card.querySelector("[data-sigcenter-status]");

    const solicitudId = Number(card.dataset.solicitudId || 0);
    const hcNumber = (card.dataset.hcNumber || "").trim();
    const trabajadorId = (card.dataset.trabajadorId || "").trim();
    const existingAgendaId = (card.dataset.sigcenterAgendaId || "").trim();
    const existingFechaInicio = (card.dataset.sigcenterFechaInicio || "").trim();
    const existingProcedimientoId = (card.dataset.sigcenterProcedimientoId || "").trim();
    const coberturaData = root.querySelector("#prefacturaCoberturaData");
    const existingDocSolicitud = (card.dataset.sigcenterDocSolicitud || "").trim();
    const sigcenterUsername = (card.dataset.sigcenterUsername || "").trim();
    const sigcenterPassword = (card.dataset.sigcenterPassword || "").trim();

    const state = {
        ready: false,
        selectedDate: "",
        selectedTime: "",
        arrivalTime: "",
        sedeId: "1",
        sedesLoaded: false,
        action: existingAgendaId ? "UPDATE" : "CREATE",
        agendaId: existingAgendaId,
    };

    const setStatus = (message, tone = "") => {
        if (!status) return;
        status.textContent = message;
        status.classList.remove("text-danger", "text-success", "text-muted");
        if (tone) {
            status.classList.add(tone);
        } else {
            status.classList.add("text-muted");
        }
    };

    const setSelectedLabel = () => {
        if (!selectedLabel) return;
        if (!state.selectedDate || !state.selectedTime) {
            selectedLabel.textContent = "";
            return;
        }
        selectedLabel.textContent = `Fecha seleccionada: ${state.selectedDate} ${state.selectedTime}`;
    };

    const normalizeList = (items) => Array.from(new Set(items.filter(Boolean)));

    const collectStrings = (value, output = []) => {
        if (typeof value === "string") {
            output.push(value);
            return output;
        }
        if (Array.isArray(value)) {
            value.forEach((item) => collectStrings(item, output));
            return output;
        }
        if (value && typeof value === "object") {
            Object.values(value).forEach((item) => collectStrings(item, output));
        }
        return output;
    };

    const extractDates = (data) => {
        const strings = collectStrings(data);
        const dates = strings
            .map((value) => value.match(/\d{4}-\d{2}-\d{2}/))
            .filter(Boolean)
            .map((match) => match[0]);
        return normalizeList(dates);
    };

    const extractTimes = (data) => {
        const strings = collectStrings(data);
        const times = [];
        strings.forEach((value) => {
            const matches = value.match(/([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?/g);
            if (matches) {
                matches.forEach((match) => times.push(match));
            }
        });
        return normalizeList(times);
    };

    const extractProcedimientos = (data) => {
        const list = Array.isArray(data)
            ? data
            : Array.isArray(data?.data?.tipoProcedimientos)
                ? data.data.tipoProcedimientos
                : Array.isArray(data?.data)
                    ? data.data
                    : [];
        const result = [];
        if (!Array.isArray(list)) return result;
        list.forEach((item) => {
            if (!item || typeof item !== "object") return;
            const id = item.procedimiento_id ?? item.id ?? item.procedimientoId ?? null;
            const label = item.nombre ?? item.descripcion ?? item.procedimiento ?? item.name ?? null;
            if (id !== null) {
                result.push({id: String(id), label: label ? String(label) : `Procedimiento ${id}`});
            }
        });
        return result;
    };

    const extractSedes = (data) => {
        const list = Array.isArray(data)
            ? data
            : Array.isArray(data?.data?.sede)
                ? data.data.sede
                : Array.isArray(data?.data)
                    ? data.data
                    : [];
        const result = [];
        if (!Array.isArray(list)) return result;
        list.forEach((item) => {
            if (!item || typeof item !== "object") return;
            const id = item.ID_SEDE ?? item.id ?? item.sede_id ?? null;
            const label = item.NOMBRE ?? item.nombre ?? item.sede ?? item.name ?? null;
            if (id !== null) {
                result.push({id: String(id), label: label ? String(label) : `Sede ${id}`});
            }
        });
        return result;
    };

    const renderCurrentAgenda = (agendaId, fechaInicio) => {
        if (!currentAlert) return;
        if (!agendaId && !fechaInicio) {
            currentAlert.classList.add("d-none");
            return;
        }
        currentAlert.classList.remove("d-none");
        if (currentFecha) {
            currentFecha.textContent = fechaInicio ? `Inicio: ${fechaInicio}` : "";
        }
        if (currentAgenda) {
            currentAgenda.textContent = agendaId ? `Agenda ID: ${agendaId}` : "";
        }
    };

    const setControlsEnabled = (enabled) => {
        if (loadDaysBtn) loadDaysBtn.disabled = !enabled;
        if (!enabled && scheduleBtn) scheduleBtn.disabled = true;
    };

    const setScheduleLabel = () => {
        if (!scheduleBtn) return;
        const icon = scheduleBtn.querySelector("i");
        const label = state.action === "UPDATE" ? "Reagendar" : "Agendar";
        if (icon) {
            scheduleBtn.innerHTML = `${icon.outerHTML} ${label}`;
        } else {
            scheduleBtn.textContent = label;
        }
    };

    function loadSedes() {
        if (!sedeSelect || !trabajadorId) return;
        state.sedesLoaded = true;
        setStatus("Cargando sedes...", "text-muted");
        return fetchSigcenter("sedes", "sedes.php", {
            method: "POST",
            headers: {"Content-Type": "application/json;charset=UTF-8"},
            body: JSON.stringify({trabajador_id: trabajadorId, company_id: 113}),
            credentials: "include",
        })
            .then(async (res) => {
                const raw = await res.text();
                console.log("[Sigcenter] sedes HTTP:", res.status, raw);
                let data = {};
                try {
                    data = raw ? JSON.parse(raw) : {};
                } catch (error) {
                    console.error("[Sigcenter] JSON inválido en sedes:", error);
                }
                if (!res.ok || !data.success) {
                    throw new Error(
                        data?.error
                        || data?.message
                        || `No se pudieron cargar sedes (HTTP ${res.status})`
                    );
                }
                const sedes = extractSedes(data);
                sedeSelect.innerHTML = '<option value="">Selecciona una sede</option>';
                sedes.forEach((sede) => {
                    const option = document.createElement("option");
                    option.value = sede.id;
                    option.textContent = sede.label;
                    if (sede.id === state.sedeId) {
                        option.selected = true;
                    }
                    sedeSelect.appendChild(option);
                });
                if (!state.sedeId && sedes.length > 0) {
                    state.sedeId = sedes[0].id;
                    sedeSelect.value = state.sedeId;
                }
                setStatus("Sedes cargadas.", "text-muted");
            })
            .catch((error) => {
                console.error(error);
                setStatus(error?.message || "No se pudieron cargar sedes.", "text-danger");
            });
    }

    // Procedimientos eliminados del frontend. No se requiere cargar ni renderizar.

    const updateAvailability = (checklist = []) => {
        const isReady = checklist.some((item) => {
            const slug = (item?.slug || item?.etapa_slug || "").toString().trim().toLowerCase();
            const isCompleted = Boolean(
                item?.completed
                || item?.completado
                || item?.checked
                || item?.completado_at
            );
            return slug === "apto-oftalmologo" && isCompleted;
        });
        console.log("[Sigcenter] Checklist recibido:", checklist);
        console.log("[Sigcenter] Apto oftalmologo listo:", isReady);
        state.ready = isReady;
        if (unavailable) {
            unavailable.classList.toggle("d-none", isReady);
        }
        if (controls) {
            controls.classList.toggle("d-none", !isReady);
        }

        const hasWorker = trabajadorId !== "";
        if (noWorker) {
            noWorker.classList.toggle("d-none", !isReady || hasWorker);
        }
        setControlsEnabled(isReady && hasWorker);
        if (isReady && hasWorker && !state.sedesLoaded) {
            loadSedes();
        }
        // Procedimientos eliminados: no se cargan.
    };

    const refreshChecklistState = async () => {
        if (!solicitudId) return;
        const {basePath} = getKanbanConfig();
        const safeBasePath = String(basePath || "").replace(/\/+$/g, "");
        const url = resolveReadPath(`${safeBasePath}/${encodeURIComponent(String(solicitudId))}/crm/checklist-state`);
        console.log("[Sigcenter] basePath:", basePath);
        console.log("[Sigcenter] checklist-state url:", url);
        setStatus("Validando checklist...", "text-muted");
        try {
            const res = await fetch(url, {credentials: "include"});
            console.log("[Sigcenter] checklist-state HTTP:", res.status, res.statusText);
            const raw = await res.text();
            console.log("[Sigcenter] checklist-state raw:", raw);
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (error) {
                console.warn("[Sigcenter] checklist-state no es JSON válido");
            }
            if (!res.ok || !data.success) {
                throw new Error(data?.error || data?.message || "No se pudo cargar checklist");
            }
            console.log("[Sigcenter] checklist-state parsed:", data);
            updateAvailability(data.checklist || []);
            if (!state.ready) {
                setStatus("Checklist apto oftalmólogo no marcado.", "text-muted");
            }
        } catch (error) {
            console.error("Sigcenter: no se pudo cargar checklist.", error);
            setStatus("No se pudo validar checklist. Revisa sesión/permiso.", "text-danger");
        }
    };

    const renderButtons = (containerEl, items, onSelect, selectedValue) => {
        if (!containerEl) return;
        containerEl.innerHTML = "";
        if (items.length === 0) return;
        items.forEach((item) => {
            const btn = document.createElement("button");
            btn.type = "button";
            btn.className = `btn btn-sm ${item === selectedValue ? "btn-primary" : "btn-outline-secondary"}`;
            btn.textContent = item;
            btn.addEventListener("click", () => onSelect(item));
            containerEl.appendChild(btn);
        });
    };

    const updateScheduleButton = () => {
        if (!scheduleBtn) return;
        const ready = state.ready
            && state.selectedDate
            && state.selectedTime
            && state.sedeId;
        scheduleBtn.disabled = !ready;
    };

    const loadDays = async () => {
        if (!trabajadorId) return;
        setStatus("Cargando días disponibles...", "text-muted");
        loadDaysBtn.disabled = true;
        try {
            const res = await fetchSigcenter("horarios-dias", "horarios-dias.php", {
                method: "POST",
                headers: {"Content-Type": "application/json;charset=UTF-8"},
                body: JSON.stringify({trabajador_id: trabajadorId, company_id: 113, ID_SEDE: state.sedeId}),
                credentials: "include",
            });
            const raw = await res.text();
            console.log("[Sigcenter] horarios-dias HTTP:", res.status, raw);
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (error) {
                console.error("[Sigcenter] JSON inválido en horarios-dias:", error);
            }
            if (!res.ok || !data.success) {
                throw new Error(
                    data?.error
                    || data?.message
                    || `No se pudieron cargar días (HTTP ${res.status})`
                );
            }
            const dates = Array.isArray(data?.data?.fechas)
                ? data.data.fechas
                : extractDates(data.data || data);
            console.log("[Sigcenter] fechas parseadas:", dates);
            const onDateSelect = (value) => {
                state.selectedDate = value;
                state.selectedTime = "";
                renderButtons(daysContainer, dates, onDateSelect, value);
                timesContainer.innerHTML = "";
                setSelectedLabel();
                updateScheduleButton();
                loadTimes(value);
            };
            renderButtons(daysContainer, dates, onDateSelect, state.selectedDate);
            setStatus(dates.length ? "Selecciona un día." : "No hay días disponibles.", "text-muted");
        } catch (error) {
            console.error(error);
            setStatus(error?.message || "No se pudieron cargar días disponibles.", "text-danger");
        } finally {
            loadDaysBtn.disabled = false;
        }
    };

    const loadTimes = async (dateValue) => {
        if (!trabajadorId || !dateValue) return;
        setStatus(`Cargando horarios para ${dateValue}...`, "text-muted");
        try {
            const res = await fetchSigcenter("horarios-especifico", "horarios-especifico.php", {
                method: "POST",
                headers: {"Content-Type": "application/json;charset=UTF-8"},
                body: JSON.stringify({
                    trabajador_id: trabajadorId,
                    FECHA: dateValue,
                    company_id: 113,
                    ID_SEDE: state.sedeId
                }),
                credentials: "include",
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || !data.success) {
                throw new Error(
                    data?.error
                    || data?.message
                    || `No se pudieron cargar horarios (HTTP ${res.status})`
                );
            }
            const times = Array.isArray(data?.data?.horarios)
                ? data.data.horarios
                : Array.isArray(data?.data?.horas)
                    ? data.data.horas
                    : extractTimes(data.data || data);
            console.log("[Sigcenter] horarios parseados:", times);
            const onTimeSelect = (value) => {
                state.selectedTime = value;
                renderButtons(timesContainer, times, onTimeSelect, value);
                setSelectedLabel();
                updateScheduleButton();
            };
            renderButtons(timesContainer, times, onTimeSelect, state.selectedTime);
            setStatus(times.length ? "Selecciona un horario." : "No hay horarios disponibles.", "text-muted");
        } catch (error) {
            console.error(error);
            setStatus(error?.message || "No se pudieron cargar horarios disponibles.", "text-danger");
        }
    };

    const resolveDocSolicitud = async () => {
        const cachedDoc = (card.dataset.sigcenterDocSolicitud || "").trim();
        if (cachedDoc) {
            return {
                docSolicitud: cachedDoc,
                lateralidad: resolveLateralidad(card.dataset.sigcenterLateralidad || ""),
            };
        }

        const formId = coberturaData?.dataset?.formId || "";
        const detalle = (() => {
            const base = findSolicitudById(solicitudId) || {};
            return base;
        })();

        let procedimientoText = detalle.procedimiento || coberturaData?.dataset?.procedimiento || "";
        let origen = card.dataset.sigcenterOrigenId
            || detalle.derivacion_pedido_id
            || "";
        // Nota: en el HTML puede venir como data-sigcenter-prefactura o data-sigcenter-prefactura-id
        let prefactura = (
            card.dataset.sigcenterPrefactura
            || card.dataset.sigcenterPrefacturaId
            || detalle.prefactura
            || coberturaData?.dataset?.prefactura
            || ""
        )
            .toString()
            .trim();
        let lateralidadRaw = card.dataset.sigcenterLateralidad
            || detalle.lateralidad
            || detalle.ojo
            || procedimientoText;
        let procedimientoCodigo = extractProcedimientoCodigo(procedimientoText);
        let lateralidad = resolveLateralidad(lateralidadRaw);

        if (!procedimientoCodigo || (!origen && !prefactura) || !lateralidad) {
            try {
                const detalleFetch = await fetchDetalleSolicitud({
                    hcNumber,
                    solicitudId,
                    formId: detalle.form_id,
                });
                procedimientoText = procedimientoText || detalleFetch.procedimiento || "";
                origen = origen
                    || detalleFetch.derivacion_pedido_id
                    || "";
                prefactura = prefactura
                    || (detalleFetch.prefactura || "").toString().trim();
                if (prefactura) {
                    if (!card.dataset.sigcenterPrefactura) {
                        card.dataset.sigcenterPrefactura = prefactura;
                    }
                    if (!card.dataset.sigcenterPrefacturaId) {
                        card.dataset.sigcenterPrefacturaId = prefactura;
                    }
                }
                lateralidadRaw = lateralidadRaw
                    || detalleFetch.lateralidad
                    || detalleFetch.ojo
                    || detalleFetch.procedimiento
                    || "";
                procedimientoCodigo = procedimientoCodigo || extractProcedimientoCodigo(procedimientoText);
                lateralidad = lateralidad || resolveLateralidad(lateralidadRaw);
                if (!card.dataset.sigcenterLateralidad && lateralidad) {
                    card.dataset.sigcenterLateralidad = lateralidad;
                }
            } catch (error) {
                console.warn("[Sigcenter] No se pudo hidratar detalle para docSolicitud:", error);
            }
        }

        if (!origen && hcNumber && formId) {
            try {
                const response = await fetch("/solicitudes/derivacion-preseleccion", {
                    method: "POST",
                    headers: {"Content-Type": "application/json;charset=UTF-8"},
                    body: JSON.stringify({
                        hc_number: hcNumber,
                        form_id: formId,
                        solicitud_id: solicitudId || undefined,
                    }),
                    credentials: "include",
                });
                const data = await response.json().catch(() => ({}));
                if (response.ok && data?.selected?.pedido_id_mas_antiguo) {
                    const pedidoId = String(data.selected.pedido_id_mas_antiguo || "");
                    const lateralidadSel = resolveLateralidad(data.selected.lateralidad || lateralidadRaw);
                    if (pedidoId) {
                        card.dataset.sigcenterDocSolicitud = pedidoId;
                        if (!card.dataset.sigcenterLateralidad && lateralidadSel) {
                            card.dataset.sigcenterLateralidad = lateralidadSel;
                        }
                        return {docSolicitud: pedidoId, lateralidad: lateralidadSel};
                    }
                }
            } catch (error) {
                console.warn("[Sigcenter] No se pudo resolver docSolicitud desde derivación:", error);
            }
        }

        if (!hcNumber || !procedimientoCodigo || !lateralidad || (!origen && !prefactura)) {
            const missing = [];
            if (!hcNumber) missing.push("hc_number");
            if (!procedimientoCodigo) missing.push("procedimiento");
            if (!lateralidad) missing.push("lateralidad");
            if (!origen && !prefactura) missing.push("origen|prefactura");
            throw new Error(`Faltan datos para resolver docSolicitud (${missing.join(", ")})`);
        }

        const payload = {
            hc_number: hcNumber,
            origen: origen || undefined,
            prefactura: prefactura || undefined,
            procedimiento: procedimientoCodigo,
            lateralidad,
            solicitud_id: solicitudId || undefined,
        };
        logReq("doc_solicitud", buildSigcenterApiCandidates("doc_solicitud.php")[0] || "/api/sigcenter/doc_solicitud.php", payload);
        const res = await fetchSigcenter("doc_solicitud", "doc_solicitud.php", {
            method: "POST",
            headers: {"Content-Type": "application/json;charset=UTF-8"},
            body: JSON.stringify(payload),
            credentials: "include",
        });
        const raw = await res.text();
        logRes("doc_solicitud", res, raw);
        let data = {};
        try {
            data = raw ? JSON.parse(raw) : {};
        } catch (error) {
            console.error("[Sigcenter] JSON inválido en doc_solicitud:", error);
        }
        if (!res.ok || !data.success) {
            throw new Error(
                data?.error
                || data?.message
                || `No se pudo resolver docSolicitud (HTTP ${res.status})`
            );
        }
        const pedidoId = data.pedido_id ? String(data.pedido_id) : "";
        if (!pedidoId) {
            throw new Error("No se obtuvo pedido_id válido");
        }
        card.dataset.sigcenterDocSolicitud = pedidoId;
        if (prefactura) {
            if (!card.dataset.sigcenterPrefactura) {
                card.dataset.sigcenterPrefactura = prefactura;
            }
            if (!card.dataset.sigcenterPrefacturaId) {
                card.dataset.sigcenterPrefacturaId = prefactura;
            }
        }
        return {docSolicitud: pedidoId, lateralidad};
    };

    const schedule = async () => {
        if (!scheduleBtn) return;
        const fecha = state.selectedDate;
        let hora = state.selectedTime;
        if (!fecha || !hora) return;
        if (hora.includes(" - ")) {
            hora = hora.split(" - ")[0].trim();
        }
        if (hora.length === 5) {
            hora = `${hora}:00`;
        }
        const fechaInicio = `${fecha} ${hora}`;
        // Para compatibilidad, obtener el input si existe:
        const arrivalInput = card.querySelector("[data-sigcenter-arrival]");
        scheduleBtn.disabled = true;
        setStatus("Resolviendo docSolicitud...", "text-muted");
        try {
            const {docSolicitud, lateralidad} = await resolveDocSolicitud();
            const idOjo = lateralidadToId(lateralidad);
            setStatus("Agendando en Sigcenter...", "text-muted");
            const payload = {
                docSolicitud,
                idtrabajador: trabajadorId,
                sigcenter_user: sigcenterUsername,
                sigcenter_pass: sigcenterPassword,
                fechaInicio,
                fechaFin: (() => {
                    const [h, m, s] = hora.split(":");
                    const end = new Date(`${fecha}T${h}:${m}:${s || "00"}`);
                    end.setMinutes(end.getMinutes() + 15);
                    return end.toISOString().slice(0, 19).replace("T", " ");
                })(),
                horaIni: arrivalInput?.dataset?.horaIni || "",
                horaFin: arrivalInput?.dataset?.horaFin || "",
                sede_departamento: state.sedeId,
                AgendaDoctor_ID_SEDE_DEPARTAMENTO: state.sedeId,
                ID_OJO: idOjo,
                ID_ANESTESIA: 4,
            };
            logReq("agendar_real", buildSigcenterApiCandidates("agendar_real.php")[0] || "/api/sigcenter/agendar_real.php", payload);
            const res = await fetchSigcenter("agendar_real", "agendar_real.php", {
                method: "POST",
                headers: {"Content-Type": "application/json;charset=UTF-8"},
                body: JSON.stringify(payload),
                credentials: "include",
            });
            const raw = await res.text();
            logRes("agendar_real", res, raw);
            let data = {};
            try {
                data = raw ? JSON.parse(raw) : {};
            } catch (error) {
                console.error("[Sigcenter] JSON inválido en agendar:", error);
            }
            if (!res.ok || !data.success) {
                throw new Error(
                    data?.error
                    || data?.message
                    || `No se pudo agendar (HTTP ${res.status})`
                );
            }
            const agendaId = data.agenda_id || "";
            if (agendaId) {
                state.agendaId = String(agendaId);
                state.action = "UPDATE";
                setScheduleLabel();
            }
            renderCurrentAgenda(agendaId, fechaInicio);
            setStatus(`Agendado correctamente${agendaId ? ` (ID ${agendaId})` : ""}.`, "text-success");
        } catch (error) {
            console.error(error);
            setStatus(error?.message || "No se pudo agendar en Sigcenter.", "text-danger");
        } finally {
            scheduleBtn.disabled = false;
        }
    };

    if (sedeSelect) {
        sedeSelect.addEventListener("change", (event) => {
            state.sedeId = event.target.value;
            updateScheduleButton();
        });
    }

    if (loadDaysBtn) {
        loadDaysBtn.addEventListener("click", () => loadDays());
    }

    if (scheduleBtn) {
        scheduleBtn.addEventListener("click", () => schedule());
    }

    if (arrivalInput) {
        arrivalInput.addEventListener("change", (event) => {
            state.arrivalTime = event.target.value;
        });
    }

    renderCurrentAgenda(existingAgendaId, existingFechaInicio);
    setScheduleLabel();
    setSelectedLabel();
    setStatus("Selecciona una sede y carga días disponibles.", "text-muted");
    refreshChecklistState();
}
