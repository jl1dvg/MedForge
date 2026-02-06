// Consulta anterior vía API (MedForge)
(function () {
  const EXAM_SELECTOR = "#consultas-fisico-0-observacion";
  const PLAN_SELECTOR = "#docsolicitudprocedimientos-observacion_consulta";
  const PREV_EXAM_BTN_ID = "cive-prev-exam-btn";
  const OPHTH_EXAM_BTN_ID = "cive-oftalmo-editor-btn";

  const OPHTH_DEFAULTS = {
    antseg: {
      fields: {
        conj: "Bulbar y tarsal clara",
        sclera: "Blanca",
        cornea: "Transparente, sin opacidades.",
        ac: "Profunda, transparente",
        iris: "Color y patrón normales, sin sinequias.",
        pupil: "Isocórica, redonda, reactiva a la luz",
        lens: "Transparente.",
      },
      comments: "",
    },
    retina: {
      fields: {
        disc: "Bordes nítidos, coloración normal, sin edema, excavación 0.3.",
        macula: "Reflejo foveal conservado, sin hemorragias ni exudados.",
        vessels: "Calibre y trayecto normal, relación A/V 2:3.",
        vitreous: "Transparente.",
        periphery: "Sin desgarros.",
      },
      comments: "",
    },
  };

  const OPHTH_EXAM_SECTIONS = [
    {
      id: "antseg",
      title: "Segmento anterior",
      commentLabel: "Comentarios de segmento anterior",
      fields: [
        { id: "conj", label: "Conjuntiva" },
        { id: "sclera", label: "Esclera" },
        { id: "cornea", label: "Córnea" },
        { id: "ac", label: "Cámara anterior" },
        { id: "iris", label: "Iris" },
        { id: "pupil", label: "Pupila" },
        { id: "lens", label: "Cristalino" },
      ],
    },
    {
      id: "retina",
      title: "Retina",
      commentLabel: "Comentarios de retina",
      fields: [
        { id: "disc", label: "Nervio" },
        { id: "macula", label: "Mácula" },
        { id: "vessels", label: "Vasos" },
        { id: "vitreous", label: "Vítreo" },
        { id: "periphery", label: "Periferia" },
      ],
    },
  ];

  function ensureExamButtonContainer(textarea) {
    const row =
      textarea?.closest("tr") ||
      textarea?.closest(".multiple-input-list__item");
    if (!row) {
      console.warn("CIVE Extension: fila de examen físico no encontrada.");
      return {};
    }

    const plusCell = row.querySelector(".list-cell__button");
    if (!plusCell) {
      console.warn(
        "CIVE Extension: celda de botones no encontrada para examen físico.",
      );
      return {};
    }

    const plusButton = plusCell.querySelector(".multiple-input-list__btn");
    if (plusButton) {
      plusButton.style.display = "";
      plusButton.style.flexDirection = "";
      plusButton.style.gap = "";
      plusButton.style.alignItems = "";
    }

    let container =
      plusCell.querySelector(".cive-exam-actions") ||
      plusCell.querySelector(".cive-prev-exam-container");
    if (!container) {
      container = document.createElement("div");
      container.className = "cive-exam-actions cive-prev-exam-container";
      plusCell.appendChild(container);
    } else if (!container.classList.contains("cive-exam-actions")) {
      container.classList.add("cive-exam-actions");
    }

    plusCell.style.display = "flex";
    plusCell.style.flexDirection = "row";
    plusCell.style.gap = "6px";
    plusCell.style.alignItems = "center";

    return { row, plusCell, container };
  }

  function getIdentifiers() {
    const params = new URLSearchParams(window.location.search);
    const formId =
      params.get("idSolicitud") ||
      params.get("id") ||
      params.get("form_id") ||
      null;

    let hcNumber = null;
    let procedimiento = null;
    const hcInput = document.querySelector("#numero-historia-clinica");
    if (hcInput && hcInput.value) {
      hcNumber = hcInput.value.trim();
    } else {
      const hcFromMedia = document.querySelector(
        ".media-body p:nth-of-type(2)",
      );
      if (hcFromMedia) {
        const text = hcFromMedia.textContent || "";
        const parts = text.split("HC #:");
        if (parts[1]) {
          hcNumber = parts[1].trim();
        }
      }
    }

    if (!hcNumber) {
      try {
        const stored = JSON.parse(
          localStorage.getItem("datosPacienteSeleccionado") || "{}",
        );
        hcNumber = stored.identificacion || stored.hcNumber || null;
        procedimiento = stored.procedimiento_proyectado || null;
      } catch (error) {
        // ignore parse errors
      }
    }

    return { formId, hcNumber, procedimiento };
  }

  function setIfEmpty(selector, value) {
    if (!value) return false;
    const field = document.querySelector(selector);
    if (!field) return false;
    const current = String(field.value || "").trim();
    if (current !== "") return false;
    field.value = value.trim();
    return true;
  }

  function formatDateLabel(value) {
    if (!value) return "consulta previa";
    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) return "consulta previa";
    const day = `${parsed.getDate()}`.padStart(2, "0");
    const month = `${parsed.getMonth() + 1}`.padStart(2, "0");
    const year = parsed.getFullYear();
    return `${day}/${month}/${year}`;
  }

  function injectOphthStyles() {
    if (document.getElementById("cive-oftalmo-styles")) return;

    const style = document.createElement("style");
    style.id = "cive-oftalmo-styles";
    style.textContent = `
            .cive-oftalmo-backdrop {
                position: fixed;
                inset: 0;
                background: rgba(0,0,0,0.45);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
            }
            .cive-oftalmo-modal {
                background: #fff;
                border-radius: 10px;
                max-width: 960px;
                width: 95%;
                max-height: 90vh;
                overflow: hidden;
                display: flex;
                flex-direction: column;
                box-shadow: 0 10px 40px rgba(0,0,0,0.25);
                font-family: 'Segoe UI', sans-serif;
            }
            .cive-oftalmo-header {
                padding: 14px 18px;
                background: #0b6fa4;
                color: #fff;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 10px;
            }
            .cive-oftalmo-body {
                padding: 16px 18px;
                overflow-y: auto;
                gap: 12px;
                display: flex;
                flex-direction: column;
            }
            .cive-oftalmo-section {
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                padding: 12px;
            }
            .cive-oftalmo-section h4 {
                margin: 0 0 8px 0;
                color: #0b6fa4;
                font-size: 15px;
            }
            .cive-oftalmo-grid {
                display: grid;
                grid-template-columns: 1fr 1fr 1fr;
                gap: 8px;
                align-items: center;
            }
            .cive-oftalmo-grid label {
                font-weight: 600;
                color: #374151;
            }
            .cive-oftalmo-grid textarea {
              width: 100%;
              min-height: 60px;
              resize: vertical;
              border: 1px solid #d1d5db;
              border-radius: 6px;
              padding: 6px 8px;
              font-size: 13px;
            }

  /* NUEVO: fila por campo (label + OD + acciones + OI) */
            .cive-oftalmo-field-row {
                grid-template-columns: 1fr 1fr auto 1fr;
                align-items: start;
            }
            .cive-oftalmo-field-actions {
                display: flex;
                flex-direction: column;
                gap: 4px;
                align-items: stretch;
            }
            .cive-oftalmo-mini-btn {
                padding: 4px 6px;
                font-size: 11px;
                border: 1px solid #d1d5db;
                border-radius: 4px;
                background: #f3f4f6;
                cursor: pointer;
            }
            .cive-oftalmo-mini-btn:hover {
                background: #e5e7eb;
            }

  /* NUEVO: acciones por sección */
            .cive-oftalmo-section-actions {
                display: flex;
                gap: 6px;
                margin: 6px 0 10px 0;
                flex-wrap: wrap;
            }
            .cive-oftalmo-section-actions .cive-oftalmo-mini-btn {
                padding: 5px 8px;
            }
            .cive-oftalmo-footer {
                padding: 12px 18px;
                border-top: 1px solid #e5e7eb;
                display: flex;
                justify-content: flex-end;
                gap: 10px;
            }
            .cive-oftalmo-footer button {
                border: none;
                border-radius: 6px;
                padding: 8px 12px;
                font-weight: 600;
                cursor: pointer;
            }
            .cive-oftalmo-btn-primary {
                background: #0b6fa4;
                color: white;
            }
            .cive-oftalmo-btn-secondary {
                background: #e5e7eb;
                color: #111827;
            }
            .cive-oftalmo-section .cive-oftalmo-comments textarea {
                min-height: 50px;
            }
        `;

    document.head.appendChild(style);
  }

  let ophthModal = null;
  let ophthModalBody = null;

  function applyOphthDefaults(container) {
    if (!container) return;
    OPHTH_EXAM_SECTIONS.forEach((section) => {
      const defaults = OPHTH_DEFAULTS[section.id] || {};
      const fieldDefaults = defaults.fields || {};

      section.fields.forEach((field) => {
        const defaultText = fieldDefaults[field.id];
        if (!defaultText) return;
        const od = container.querySelector(
          `[name="${section.id}-${field.id}-od"]`,
        );
        const os = container.querySelector(
          `[name="${section.id}-${field.id}-os"]`,
        );
        if (od && !od.value.trim()) od.value = defaultText;
        if (os && !os.value.trim()) os.value = defaultText;
      });

      const commentsDefault = defaults.comments;
      const commentsEl = container.querySelector(
        `[name="${section.id}-comments"]`,
      );
      if (commentsDefault && commentsEl && !commentsEl.value.trim()) {
        commentsEl.value = commentsDefault;
      }
    });
  }

  function getSectionDef(sectionId) {
    return OPHTH_EXAM_SECTIONS.find((s) => s.id === sectionId);
  }

  function clearSectionEye(container, sectionId, eye) {
    const sectionDef = getSectionDef(sectionId);
    if (!sectionDef) return;
    sectionDef.fields.forEach((field) => {
      const el = container.querySelector(
        `[name="${sectionId}-${field.id}-${eye}"]`,
      );
      if (el) el.value = "";
    });
  }

  function copySection(container, sectionId, fromEye, toEye) {
    const sectionDef = getSectionDef(sectionId);
    if (!sectionDef) return;
    sectionDef.fields.forEach((field) => {
      const fromEl = container.querySelector(
        `[name="${sectionId}-${field.id}-${fromEye}"]`,
      );
      const toEl = container.querySelector(
        `[name="${sectionId}-${field.id}-${toEye}"]`,
      );
      if (toEl && fromEl) {
        toEl.value = fromEl.value;
      }
    });
  }

  function copyField(container, sectionId, fieldId, fromEye, toEye) {
    const fromEl = container.querySelector(
      `[name="${sectionId}-${fieldId}-${fromEye}"]`,
    );
    const toEl = container.querySelector(
      `[name="${sectionId}-${fieldId}-${toEye}"]`,
    );
    if (toEl && fromEl) {
      toEl.value = fromEl.value;
    }
  }

  function buildOphthModal() {
    if (ophthModal) return ophthModal;

    injectOphthStyles();

    ophthModal = document.createElement("div");
    ophthModal.className = "cive-oftalmo-backdrop";
    ophthModal.style.display = "none";

    const modal = document.createElement("div");
    modal.className = "cive-oftalmo-modal";
    ophthModal.appendChild(modal);

    const header = document.createElement("div");
    header.className = "cive-oftalmo-header";
    header.innerHTML = `<h3 style="margin:0; font-size:16px;">Editor de examen físico oftalmológico</h3>`;
    const closeBtn = document.createElement("button");
    closeBtn.type = "button";
    closeBtn.className = "btn btn-link btn-sm";
    closeBtn.textContent = "Cerrar";
    closeBtn.style.color = "#fff";
    closeBtn.style.textDecoration = "underline";
    closeBtn.addEventListener("click", () => toggleOphthModal(false));
    header.appendChild(closeBtn);
    modal.appendChild(header);

    const body = document.createElement("div");
    body.className = "cive-oftalmo-body";

    OPHTH_EXAM_SECTIONS.forEach((section) => {
      const wrapper = document.createElement("section");
      wrapper.className = "cive-oftalmo-section";

      const title = document.createElement("h4");
      title.textContent = section.title;
      wrapper.appendChild(title);

      const actionsBar = document.createElement("div");
      actionsBar.className = "cive-oftalmo-section-actions";
      const clearOdBtn = document.createElement("button");
      clearOdBtn.type = "button";
      clearOdBtn.className = "cive-oftalmo-mini-btn";
      clearOdBtn.textContent = "×";
      clearOdBtn.title = "Limpiar OD";
      clearOdBtn.addEventListener("click", () =>
        clearSectionEye(body, section.id, "od"),
      );

      const clearOsBtn = document.createElement("button");
      clearOsBtn.type = "button";
      clearOsBtn.className = "cive-oftalmo-mini-btn";
      clearOsBtn.textContent = "×";
      clearOsBtn.title = "Limpiar OI";
      clearOsBtn.addEventListener("click", () =>
        clearSectionEye(body, section.id, "os"),
      );

      const copyOdOsBtn = document.createElement("button");
      copyOdOsBtn.type = "button";
      copyOdOsBtn.className = "cive-oftalmo-mini-btn";
      copyOdOsBtn.textContent = "→";
      copyOdOsBtn.title = "Copiar OD → OI";
      copyOdOsBtn.addEventListener("click", () =>
        copySection(body, section.id, "od", "os"),
      );

      const copyOsOdBtn = document.createElement("button");
      copyOsOdBtn.type = "button";
      copyOsOdBtn.className = "cive-oftalmo-mini-btn";
      copyOsOdBtn.textContent = "←";
      copyOsOdBtn.title = "Copiar OI → OD";
      copyOsOdBtn.addEventListener("click", () =>
        copySection(body, section.id, "os", "od"),
      );

      actionsBar.append(clearOdBtn, clearOsBtn, copyOdOsBtn, copyOsOdBtn);
      wrapper.appendChild(actionsBar);

      section.fields.forEach((field) => {
        const grid = document.createElement("div");
        grid.className = "cive-oftalmo-grid cive-oftalmo-field-row";

        const label = document.createElement("label");
        label.setAttribute("for", `${section.id}-${field.id}-od`);
        label.textContent = field.label;

        const od = document.createElement("textarea");
        od.name = `${section.id}-${field.id}-od`;
        od.placeholder = "OD";

        const actions = document.createElement("div");
        actions.className = "cive-oftalmo-field-actions";
        const copyToOs = document.createElement("button");
        copyToOs.type = "button";
        copyToOs.className = "cive-oftalmo-mini-btn";
        copyToOs.textContent = "→";
        copyToOs.title = "Copiar OD → OI";
        copyToOs.addEventListener("click", () =>
          copyField(body, section.id, field.id, "od", "os"),
        );

        const copyToOd = document.createElement("button");
        copyToOd.type = "button";
        copyToOd.className = "cive-oftalmo-mini-btn";
        copyToOd.textContent = "←";
        copyToOd.title = "Copiar OI → OD";
        copyToOd.addEventListener("click", () =>
          copyField(body, section.id, field.id, "os", "od"),
        );

        actions.append(copyToOs, copyToOd);

        const os = document.createElement("textarea");
        os.name = `${section.id}-${field.id}-os`;
        os.placeholder = "OS";

        grid.append(label, od, actions, os);
        wrapper.appendChild(grid);
      });

      const commentsGrid = document.createElement("div");
      commentsGrid.className = "cive-oftalmo-grid cive-oftalmo-comments";

      const commentsLabel = document.createElement("label");
      commentsLabel.textContent = section.commentLabel || "Comentarios";

      const commentsText = document.createElement("textarea");
      commentsText.name = `${section.id}-comments`;
      commentsText.placeholder = "Notas adicionales";

      commentsGrid.append(commentsLabel, commentsText);
      wrapper.appendChild(commentsGrid);

      body.appendChild(wrapper);
    });

    modal.appendChild(body);

    const footer = document.createElement("div");
    footer.className = "cive-oftalmo-footer";
    const cancelBtn = document.createElement("button");
    cancelBtn.type = "button";
    cancelBtn.className = "cive-oftalmo-btn-secondary";
    cancelBtn.textContent = "Cancelar";
    cancelBtn.addEventListener("click", () => toggleOphthModal(false));

    const applyBtn = document.createElement("button");
    applyBtn.type = "button";
    applyBtn.className = "cive-oftalmo-btn-primary";
    applyBtn.textContent = "Insertar en examen";
    applyBtn.addEventListener("click", () => {
      const textarea = document.querySelector(EXAM_SELECTOR);
      if (!textarea) {
        console.warn(
          "CIVE Extension: textarea de examen físico no encontrado al insertar desde el editor.",
        );
        toggleOphthModal(false);
        return;
      }

      const narrative = buildOphthNarrative(body);
      if (narrative.trim()) {
        textarea.value = narrative;
      }
      toggleOphthModal(false);
    });

    footer.append(cancelBtn, applyBtn);
    modal.appendChild(footer);

    ophthModal.addEventListener("click", (e) => {
      if (e.target === ophthModal) {
        toggleOphthModal(false);
      }
    });

    document.body.appendChild(ophthModal);
    ophthModalBody = body;
    return ophthModal;
  }

  function toggleOphthModal(show) {
    const modal = buildOphthModal();
    if (show) {
      applyOphthDefaults(ophthModalBody);
    }
    modal.style.display = show ? "flex" : "none";
  }

  function buildOphthNarrative(container) {
    const lines = [];

    OPHTH_EXAM_SECTIONS.forEach((section) => {
      const odParts = [];
      const osParts = [];

      section.fields.forEach((field) => {
        const od = container
          .querySelector(`[name="${section.id}-${field.id}-od"]`)
          ?.value.trim();
        const os = container
          .querySelector(`[name="${section.id}-${field.id}-os"]`)
          ?.value.trim();
        if (od) odParts.push(`${field.label}: ${od}`);
        if (os) osParts.push(`${field.label}: ${os}`);
      });

      const comments = container
        .querySelector(`[name="${section.id}-comments"]`)
        ?.value.trim();
      const hasData = odParts.length > 0 || osParts.length > 0 || !!comments;

      if (!hasData) return;

      lines.push(`${section.title}:`);
      if (odParts.length > 0) {
        lines.push(`Ojo derecho: ${odParts.join("; ")}`);
      }
      if (osParts.length > 0) {
        lines.push(`Ojo izquierdo: ${osParts.join("; ")}`);
      }
      if (comments) {
        lines.push(`Comentarios: ${comments}`);
      }
    });

    return lines.join("\n");
  }

  function ensureOphthExamEditorButton() {
    const textarea = document.querySelector(EXAM_SELECTOR);
    if (!textarea) return;

    const { container } = ensureExamButtonContainer(textarea);
    if (!container) return;

    let button = container.querySelector(`#${OPHTH_EXAM_BTN_ID}`);
    if (button) return;

    button = document.createElement("button");
    button.type = "button";
    button.id = OPHTH_EXAM_BTN_ID;
    button.className = "btn btn-success btn-sm";
    button.textContent = "Editor oftalmológico";
    button.addEventListener("click", () => toggleOphthModal(true));

    container.appendChild(button);
  }

  function placePrevExamButton(examText, planText, fecha) {
    const textarea = document.querySelector(EXAM_SELECTOR);
    const planField = document.querySelector(PLAN_SELECTOR);
    if (!textarea) {
      console.warn("CIVE Extension: textarea de examen físico no encontrado.");
      return;
    }
    const { plusCell, container: btnContainer } =
      ensureExamButtonContainer(textarea);
    if (!btnContainer || !plusCell) return;

    // Si el botón ya existe (aunque esté dentro del plus), lo movemos al contenedor
    let btn = plusCell.querySelector(`#${PREV_EXAM_BTN_ID}`);
    if (btn) {
      btnContainer.appendChild(btn);
    } else {
      // Crear el botón si todavía no existe
      btn = document.createElement("button");
      btn.type = "button";
      btn.id = PREV_EXAM_BTN_ID;
      btn.className = "btn btn-info btn-sm";
      btn.textContent = `Examen físico de ${formatDateLabel(fecha)}`;
      btn.addEventListener("click", () => {
        if (textarea) {
          textarea.value = examText || "";
        }
        if (planField) {
          planField.value = planText || "";
        }
      });
      btnContainer.appendChild(btn);
    }
  }

  async function fetchConsultaAnterior() {
    if (
      !window.CiveApiClient ||
      typeof window.CiveApiClient.get !== "function"
    ) {
      console.warn(
        "CIVE Extension: CiveApiClient no está disponible para consulta anterior.",
      );
      return null;
    }

    await (window.configCIVE ? window.configCIVE.ready : Promise.resolve());
    const { formId, hcNumber, procedimiento } = getIdentifiers();
    if (!hcNumber) {
      console.warn(
        "CIVE Extension: no se pudo obtener HC para consulta anterior.",
      );
      return null;
    }

    const query = { hcNumber };
    if (formId) {
      query.form_id = formId;
    }
    if (procedimiento) {
      query.procedimiento = procedimiento;
    }

    try {
      const resp = await window.CiveApiClient.get("/consultas/anterior.php", {
        query,
        retries: 1,
        retryDelayMs: 500,
      });
      //console.log(
      //"CIVE Extension: respuesta cruda de /consultas/anterior.php",
      //resp
      //);

      if (resp && resp.success && resp.data) {
        //console.log(
        //"CIVE Extension: datos de consulta anterior obtenidos del API",
        //resp.data,
        //);
        return resp.data;
      }
      //console.info(
      //"CIVE Extension: sin consulta anterior disponible.",
      //resp?.message || "",
      //);
    } catch (error) {
      console.error(
        "CIVE Extension: error al obtener consulta anterior.",
        error,
      );
    }
    return null;
  }

  window.consultaAnterior = async function consultaAnterior() {
    const data = await fetchConsultaAnterior();
    if (!data) return;

    const examen = data.examen_fisico || data.examenFisico || "";
    const plan = data.plan || "";

    //console.log(
    //"CIVE Extension: usando datos de consulta anterior para examen previo",
    //{
    //dataCompleta: data,
    //examenPrevio: examen,
    //planPrevio: plan,
    //},
    //);

    // Asegurarnos de que el textarea exista (el formulario puede llegar vía PJAX)
    try {
      if (typeof esperarElemento === "function") {
        // Usamos el helper global si está disponible
        await esperarElemento(EXAM_SELECTOR);
      }
    } catch (e) {
      console.warn(
        "CIVE Extension: no se pudo esperar al campo de examen físico.",
        e,
      );
    }

    // Intentar colocar el botón aunque el campo ya tenga texto
    placePrevExamButton(examen, plan, data.fecha);
  };

  // Intento automático de insertar el botón de examen físico previo
  try {
    const autoInit = () => {
      if (window.consultaAnterior) {
        window.consultaAnterior().catch &&
          window.consultaAnterior().catch((err) => {
            console.warn(
              "CIVE Extension: error al ejecutar consultaAnterior automáticamente.",
              err,
            );
          });
      }

      // Insertar el botón del editor oftalmológico en cuanto el formulario esté disponible
      try {
        ensureOphthExamEditorButton();
      } catch (err) {
        console.warn(
          "CIVE Extension: no se pudo inicializar el editor oftalmológico.",
          err,
        );
      }
    };

    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", autoInit);
    } else {
      autoInit();
    }
  } catch (e) {
    console.warn(
      "CIVE Extension: no se pudo inicializar automáticamente consultaAnterior.",
      e,
    );
  }
})();

// Botón para marcar apto anestesia desde la consulta
(function () {
  const BUTTON_ID = "cive-apto-anestesia-btn";
  const TARGET_PROC_CODE = "SRV-ANE-002";
  const TARGET_PROC_NAME = "ANESTESIOLOGIA CONSULTA";

  const normalize = (value) => (value || "").toString().trim().toUpperCase();

  function getPacienteSeleccionado() {
    if (
      window.datosPacienteSeleccionado &&
      typeof window.datosPacienteSeleccionado === "object"
    ) {
      return window.datosPacienteSeleccionado;
    }
    try {
      const stored = localStorage.getItem("datosPacienteSeleccionado");
      if (!stored) return {};
      return JSON.parse(stored);
    } catch (e) {
      console.warn(
        "CIVE Extension: no se pudo leer datosPacienteSeleccionado.",
        e,
      );
      return {};
    }
  }

  function getSolicitudContext() {
    const params = new URLSearchParams(window.location.search);
    const paciente = getPacienteSeleccionado();
    const formId =
      params.get("idSolicitud") ||
      params.get("form_id") ||
      params.get("id") ||
      paciente.form_id ||
      paciente.idSolicitud ||
      null;
    const idSolicitud =
      params.get("idSolicitud") ||
      params.get("id") ||
      paciente.form_id ||
      paciente.idSolicitud ||
      null;
    const procedimiento =
      paciente.procedimiento_proyectado || paciente.procedimiento || "";
    return { idSolicitud, formId: formId || idSolicitud, procedimiento };
  }

  function shouldShowButton(procedimiento) {
    const norm = normalize(procedimiento);
    return norm.includes(TARGET_PROC_CODE) && norm.includes(TARGET_PROC_NAME);
  }

  function waitForTerminarButton(maxAttempts = 12, delay = 500) {
    return new Promise((resolve, reject) => {
      const search = (attempt) => {
        const btn = document.getElementById("button-terminar");
        if (btn) return resolve(btn);
        if (attempt <= 0)
          return reject(
            new Error('Botón "Terminar la Consulta" no encontrado.'),
          );
        setTimeout(() => search(attempt - 1), delay);
      };
      search(maxAttempts);
    });
  }

  async function postAptoAnestesia({ id, formId }) {
    const parsedId = Number.parseInt(id, 10);
    if (!Number.isFinite(parsedId)) {
      throw new Error("Id de solicitud no válido para apto anestesia.");
    }
    const basePath =
      (window.__KANBAN_MODULE__ && window.__KANBAN_MODULE__.basePath) ||
      "/solicitudes";
    const url = `${basePath.replace(/\/+$/, "")}/actualizar-estado`;
    const payload = {
      id: parsedId,
      form_id: formId || parsedId,
      estado: "apto-anestesia",
      completado: true,
      force: true,
    };
    const res = await fetch(url, {
      method: "POST",
      headers: { "Content-Type": "application/json;charset=UTF-8" },
      body: JSON.stringify(payload),
      credentials: "include",
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok || !data.success) {
      throw new Error(data?.error || "No se pudo marcar apto anestesia.");
    }
    return data;
  }

  function placeButton(nextTo) {
    const existing = document.getElementById(BUTTON_ID);
    if (existing) return existing;
    const btn = document.createElement("button");
    btn.type = "button";
    btn.id = BUTTON_ID;
    btn.className = "btn btn-info";
    btn.style.marginLeft = "8px";
    btn.textContent = "Apto anestesia";
    nextTo.insertAdjacentElement("afterend", btn);
    return btn;
  }

  async function initAptoButton() {
    const { idSolicitud, formId, procedimiento } = getSolicitudContext();
    if (!shouldShowButton(procedimiento)) {
      return;
    }
    if (!idSolicitud && !formId) {
      console.warn(
        "CIVE Extension: sin identificadores para marcar apto anestesia.",
      );
      return;
    }
    if (document.getElementById(BUTTON_ID)) return;

    try {
      const terminarBtn = await waitForTerminarButton();
      const aptoBtn = placeButton(terminarBtn);
      const solicitudId = idSolicitud || formId;
      aptoBtn.addEventListener("click", async () => {
        const originalText = aptoBtn.textContent;
        aptoBtn.disabled = true;
        aptoBtn.textContent = "Marcando apto...";
        try {
          const resp = await postAptoAnestesia({ id: solicitudId, formId });
          aptoBtn.classList.remove("btn-info");
          aptoBtn.classList.add("btn-success");
          aptoBtn.textContent = "Apto por anestesia";

          const store = window.__solicitudesKanban;
          if (Array.isArray(store)) {
            const item = store.find(
              (s) =>
                String(s.id) === String(solicitudId) ||
                String(s.form_id) === String(formId),
            );
            if (item) {
              item.estado = resp.estado || "apto-anestesia";
              item.estado_label =
                resp.estado_label || resp.estado || "Apto por anestesia";
              if (resp.checklist) item.checklist = resp.checklist;
              if (resp.checklist_progress)
                item.checklist_progress = resp.checklist_progress;
            }
          }
        } catch (error) {
          console.error(
            "CIVE Extension: error al marcar apto anestesia.",
            error,
          );
          alert(error?.message || "No se pudo marcar apto por anestesia.");
          aptoBtn.disabled = false;
          aptoBtn.textContent = originalText;
          return;
        }
      });
    } catch (error) {
      console.warn(
        "CIVE Extension: no se pudo colocar el botón de apto anestesia.",
        error,
      );
    }
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initAptoButton);
  } else {
    initAptoButton();
  }
})();

// Función que se ejecutará en la página actual para protocolos de cirugía
function ejecutarPopEnPagina() {
  // Función para obtener el contenido después del elemento <th> con el texto específico
  function getContentAfterTh(parent, thText) {
    const thElement = Array.from(parent.querySelectorAll("th")).find((th) =>
      th.textContent.includes(thText),
    );
    console.log(`Buscando <th> con el texto: ${thText}`); // Depuración
    console.log("Elemento <th> encontrado:", thElement); // Depuración
    return thElement
      ? thElement.parentElement.nextElementSibling.textContent.trim()
      : null;
  }

  // Encuentra el primer <li> que contiene "PROTOCOLO CIRUGIA"
  const liElement = Array.from(document.querySelectorAll("li")).find((li) =>
    li.textContent.includes("PROTOCOLO CIRUGIA"),
  );

  console.log("Encontrado el elemento li:", liElement); // Añadido para depurar

  if (liElement) {
    // Extrae los diagnósticos postoperatorios
    const diagnosticosPost = [];
    const postOperatorioHeader = Array.from(
      liElement.querySelectorAll("th"),
    ).find((th) => th.textContent.includes("Post Operatorio"));
    console.log(
      "Encontrado el encabezado Post Operatorio:",
      postOperatorioHeader,
    ); // Añadido para depurar

    if (postOperatorioHeader) {
      let row = postOperatorioHeader.parentElement.nextElementSibling;
      while (
        row &&
        row.querySelector("th") &&
        !row.querySelector("th").textContent.includes("C. PROCEDIMIENTO")
      ) {
        console.log("Procesando fila:", row); // Añadido para depurar
        const diagnosticoCell = row.querySelector(
          "th.descripcion:nth-child(2)",
        );
        if (diagnosticoCell) {
          const diagnostico = diagnosticoCell.textContent.trim();
          diagnosticosPost.push(diagnostico);
          console.log("Encontrado diagnóstico:", diagnostico); // Añadido para depurar
        }
        row = row.nextElementSibling;
      }
    }

    // Extrae el primer código de procedimiento realizado y el ojo afectado
    const procedimientoHeader = Array.from(
      liElement.querySelectorAll("th"),
    ).find((th) => th.textContent.includes("Realizado:"));
    console.log("Encontrado el encabezado Realizado:", procedimientoHeader); // Añadido para depurar
    let procedimiento = "";
    let ojoRealizado = "";
    if (procedimientoHeader) {
      const procedimientoElement = procedimientoHeader.nextElementSibling;
      console.log(
        "Elemento siguiente del encabezado Realizado:",
        procedimientoElement,
      ); // Añadido para depurar
      if (procedimientoElement) {
        let procedimientoText = procedimientoElement.textContent.trim();
        const primeraLinea = procedimientoText.split("\n")[0]; // Obtener solo la primera línea
        procedimiento = primeraLinea.trim();

        // Elimina el código de 5 dígitos seguido de un guion al inicio
        procedimiento = procedimiento.replace(/^\d{5}-\s*/, "");

        // Reemplaza (OD) y (OI) con ojo derecho y ojo izquierdo respectivamente
        procedimiento = procedimiento
          .replace(/\(OD\)/g, "ojo derecho")
          .replace(/\(OI\)/g, "ojo izquierdo");
        const ojoMatch = primeraLinea.match(/\((OD|OI)\)/); // Buscar el texto (OD) o (OI)
        if (ojoMatch) {
          if (ojoMatch[1] === "OD") {
            ojoRealizado = "ojo derecho";
          } else if (ojoMatch[1] === "OI") {
            ojoRealizado = "ojo izquierdo";
          }
        }
      }
    }
    console.log("Procedimiento Realizado:", procedimiento); // Añadido para depurar
    console.log("Ojo Realizado:", ojoRealizado); // Añadido para depurar

    // Extrae la fecha de realización
    const fechaInicioOperacionHeader = Array.from(
      liElement.querySelectorAll("th"),
    ).find((th) => th.textContent.includes("FECHA DE INICIO DE OPERACIÓN"));
    let fechaInicioOperacion = "";
    if (fechaInicioOperacionHeader) {
      const fechaRow =
        fechaInicioOperacionHeader.parentElement.nextElementSibling;
      if (fechaRow) {
        const dia = fechaRow.children[0]
          ? fechaRow.children[0].textContent.trim()
          : "";
        const mes = fechaRow.children[1]
          ? fechaRow.children[1].textContent.trim()
          : "";
        const año = fechaRow.children[2]
          ? fechaRow.children[2].textContent.trim()
          : "";
        const hora = fechaRow.children[3]
          ? fechaRow.children[3].textContent.trim()
          : "";
        fechaInicioOperacion = `${año}-${mes}-${dia}T${hora}`; // Formato completo para Date
      }
    }
    console.log("Fecha de Inicio de Operación:", fechaInicioOperacion); // Añadido para depurar

    // Calcula el tiempo transcurrido desde la fecha de realización hasta hoy
    const fechaOperacion = new Date(fechaInicioOperacion);
    const fechaActual = new Date();
    const diffTime = Math.abs(fechaActual - fechaOperacion);
    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)); // Diferencia en días completos
    const diffHours = Math.floor(diffTime / (1000 * 60 * 60)); // Diferencia en horas completas

    let tiempoTranscurrido;
    if (diffDays < 1) {
      // Menos de 1 día, muestra las horas
      tiempoTranscurrido = `${diffHours} horas`;
    } else {
      // Un día o más, muestra los días
      tiempoTranscurrido = `${diffDays} días`;
    }

    // Construye la nota de evolución médica
    const notaEvolucion = `Paciente acude a control post quirúrgico de ${tiempoTranscurrido} tras haber sido sometido a ${procedimiento}. Sin complicaciones.`;
    const examenFisico = `Biomicroscopia\n${ojoRealizado}: córnea transparente sin edema, cámara anterior formada con burbuja de aire presente, pupila miótica, negra y central, reactiva a la luz, pseudofaquia correctamente posicionada y centrada, sin signos de inflamación intraocular evidente.`;

    // Asigna la nota de evolución médica al textarea con id "consultas-motivoconsulta"
    const consultaTextarea = document.getElementById(
      "consultas-motivoconsulta",
    );
    const observacionTextarea = document.getElementById(
      "consultas-fisico-0-observacion",
    );

    if (consultaTextarea) {
      consultaTextarea.value = notaEvolucion;
      if (observacionTextarea) {
        observacionTextarea.value = examenFisico;
      } else {
        console.log(
          'Textarea con id "consultas-fisico-0-observacion" no encontrado.',
        );
      }
    } else {
      console.log('Textarea con id "consultas-motivoconsulta" no encontrado.');
    }

    // Aquí comienza la parte de la receta
    const item = {
      recetas: [
        {
          id: 0,
          nombre: "TRAZIDEX OFTENO SUSP",
          via: "GOTERO",
          unidad: "GOTAS",
          pauta: "Cada 4 horas",
          cantidad: 21,
          totalFarmacia: 1,
          observaciones: `TRAZIDEX OFTENO SUSP. OFT. X 5 ML 1 GOTAS GOTERO CADA 4 HORAS x 21 DÍAS EN ${ojoRealizado}`,
        },
      ],
      recetaCount: 1,
    };

    realizarSecuenciaDeAcciones(item)
      .then(() => {
        console.log("Recetas generadas correctamente.");
      })
      .catch((error) => {
        console.error("Error al generar las recetas:", error);
      });
  } else {
    console.log('No se encontró un <li> con "PROTOCOLO CIRUGIA".');
  }
}

// Las funciones adicionales que mencionaste se integran aquí:
function realizarSecuenciaDeAcciones(item) {
  return hacerClickEnPresuntivo(
    ".form-group.field-consultas-tipo_externa .cbx-container .cbx",
    1,
  )
    .then(() =>
      hacerClickEnSelect2(
        "#select2-consultas-fisico-0-tipoexamen_id-container",
      ),
    )
    .then(() =>
      establecerBusqueda(
        "#select2-consultas-fisico-0-tipoexamen_id-container",
        "OJOS",
      ),
    )
    .then(() => seleccionarOpcion())
    .then(() => ejecutarRecetas(item))
    .then(() => {
      console.log("Recetas generadas correctamente.");
    })
    .catch((error) => {
      console.error("Error al ejecutar la secuencia:", error);
    });
}

function llenarCampoTexto(selector, valor) {
  return new Promise((resolve, reject) => {
    const textArea = document.querySelector(selector);
    if (textArea) {
      console.log(`Llenando el campo de texto "${selector}" con "${valor}"`);
      textArea.value = valor;
      setTimeout(resolve, 100); // Añadir un retraso para asegurar que el valor se establezca
    } else {
      console.error(`El campo de texto "${selector}" no se encontró.`);
      reject(`El campo de texto "${selector}" no se encontró.`);
    }
  });
}

function hacerClickEnBotonDentroDeMedicina(
  selector,
  contenedorId,
  numeroDeClicks,
) {
  return new Promise((resolve, reject) => {
    const contenedor = document.getElementById(contenedorId);
    if (!contenedor) {
      console.error(`El contenedor con ID "${contenedorId}" no se encontró.`);
      reject(`El contenedor con ID "${contenedorId}" no se encontró.`);
      return;
    }

    const botonPlus = contenedor.querySelector(selector);
    if (botonPlus) {
      console.log(
        `Haciendo clic en el botón "${selector}" dentro de "${contenedorId}" ${numeroDeClicks} veces`,
      );
      let clicks = 0;

      function clickBoton() {
        if (clicks < numeroDeClicks) {
          botonPlus.click();
          clicks++;
          setTimeout(clickBoton, 100); // 100ms delay between clicks
        } else {
          resolve();
        }
      }

      clickBoton();
    } else {
      console.error(
        `El botón "${selector}" no se encontró dentro de "${contenedorId}".`,
      );
      reject(
        `El botón "${selector}" no se encontró dentro de "${contenedorId}".`,
      );
    }
  });
}

function hacerClickEnSelect2(selector) {
  return new Promise((resolve, reject) => {
    const tecnicoContainer = document.querySelector(selector);
    if (tecnicoContainer) {
      console.log(`Haciendo clic en el contenedor: ${selector}`);
      const event = new MouseEvent("mousedown", {
        view: window,
        bubbles: true,
        cancelable: true,
      });
      tecnicoContainer.dispatchEvent(event);
      setTimeout(resolve, 100); // Añadir un retraso para asegurar que el menú se despliegue
    } else {
      console.error(`El contenedor "${selector}" no se encontró.`);
      reject(`El contenedor "${selector}" no se encontró.`);
    }
  });
}

function establecerBusqueda(selector, valor) {
  return new Promise((resolve, reject) => {
    let attempts = 0;
    const maxAttempts = 20;

    const searchForField = () => {
      const searchField = document.querySelector("input.select2-search__field");
      if (!searchField) {
        console.log(
          `Intento ${
            attempts + 1
          }: no se encontró el campo de búsqueda. Retentando...`,
        );
        attempts++;
        if (attempts < maxAttempts) {
          hacerClickEnSelect2(selector)
            .then(() => setTimeout(searchForField, 500))
            .catch((error) => reject(error));
        } else {
          console.error("El campo de búsqueda del Select2 no se encontró.");
          reject("El campo de búsqueda del Select2 no se encontró.");
        }
      } else {
        console.log("Estableciendo búsqueda:", valor);
        searchField.value = valor;
        const inputEvent = new Event("input", {
          bubbles: true,
          cancelable: true,
        });
        searchField.dispatchEvent(inputEvent);
        setTimeout(() => resolve(searchField), 500);
      }
    };

    searchForField();
  });
}

function seleccionarOpcion() {
  return new Promise((resolve, reject) => {
    const searchField = document.querySelector("input.select2-search__field");
    if (searchField) {
      console.log("Seleccionando opción");
      const enterEvent = new KeyboardEvent("keydown", {
        key: "Enter",
        keyCode: 13,
        bubbles: true,
        cancelable: true,
      });
      searchField.dispatchEvent(enterEvent);
      setTimeout(resolve, 200); // Añadir un retraso para asegurar que la opción se seleccione
    } else {
      console.error(
        "El campo de búsqueda del Select2 no se encontró para seleccionar la opción.",
      );
      reject(
        "El campo de búsqueda del Select2 no se encontró para seleccionar la opción.",
      );
    }
  });
}

function esperarElemento(selector) {
  return new Promise((resolve, reject) => {
    const elemento = document.querySelector(selector);
    if (elemento) {
      resolve(elemento);
      return;
    }

    const observer = new MutationObserver((mutations, observerInstance) => {
      mutations.forEach((mutation) => {
        const nodes = Array.from(mutation.addedNodes);
        for (const node of nodes) {
          if (node.matches && node.matches(selector)) {
            observerInstance.disconnect();
            resolve(node);
            return;
          }
        }
      });
    });

    observer.observe(document.body, {
      childList: true,
      subtree: true,
    });

    setTimeout(() => {
      observer.disconnect();
      reject(
        `El elemento "${selector}" no se encontró dentro del tiempo esperado.`,
      );
    }, 10000); // Timeout de 10 segundos, ajusta según sea necesario
  });
}

function llenarCampoCantidad(selector, cantidad, tabCount = 0) {
  return new Promise((resolve, reject) => {
    const campoCantidad = document.querySelector(selector);
    if (campoCantidad) {
      console.log(`Llenando el campo cantidad con el valor: ${cantidad}`);
      campoCantidad.focus();
      campoCantidad.value = cantidad;
      campoCantidad.dispatchEvent(new Event("input", { bubbles: true }));
      campoCantidad.dispatchEvent(new Event("change", { bubbles: true }));

      // Simular la tecla TAB la cantidad de veces especificada
      let tabsPressed = 0;
      const pressTab = () => {
        if (tabsPressed < tabCount) {
          const tabEvent = new KeyboardEvent("keydown", {
            key: "Tab",
            keyCode: 9,
            code: "Tab",
            which: 9,
            bubbles: true,
            cancelable: true,
          });
          document.activeElement.dispatchEvent(tabEvent);

          const tabEventPress = new KeyboardEvent("keypress", {
            key: "Tab",
            keyCode: 9,
            code: "Tab",
            which: 9,
            bubbles: true,
            cancelable: true,
          });
          document.activeElement.dispatchEvent(tabEventPress);

          const tabEventUp = new KeyboardEvent("keyup", {
            key: "Tab",
            keyCode: 9,
            code: "Tab",
            which: 9,
            bubbles: true,
            cancelable: true,
          });
          document.activeElement.dispatchEvent(tabEventUp);

          tabsPressed++;
          setTimeout(pressTab, 100); // Asegurar que el evento se despacha correctamente
        } else {
          campoCantidad.blur();
          resolve();
        }
      };
      pressTab();
    } else {
      console.error("El campo cantidad no se encontró.");
      reject("El campo cantidad no se encontró.");
    }
  });
}

function ejecutarRecetas(item) {
  if (!Array.isArray(item.recetas)) return Promise.resolve();

  return hacerClickEnBotonDentroDeMedicina(".js-input-plus", "medicamento", 0)
    .then(() =>
      hacerClickEnBotonDentroDeMedicina(
        ".js-input-plus",
        "medicamento",
        item.recetaCount,
      ),
    )
    .then(() =>
      esperarElemento(`#select2-recetas-recetasadd-0-producto_id-container`),
    ) // Solo se ejecuta una vez
    .then(() => {
      // Iterar sobre cada receta
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          // Manejar el producto
          return hacerClickEnSelect2(
            `#select2-recetas-recetasadd-${receta.id}-producto_id-container`,
          )
            .then(() =>
              establecerBusqueda(
                `#select2-recetas-recetasadd-${receta.id}-producto_id-container`,
                receta.nombre,
              ),
            )
            .then(() => seleccionarOpcion());
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    })
    .then(() => {
      // Ahora manejar las vías
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          return hacerClickEnSelect2(
            `#select2-recetas-recetasadd-${receta.id}-vias-container`,
          )
            .then(() =>
              establecerBusqueda(
                `#select2-recetas-recetasadd-${receta.id}-vias-container`,
                receta.via,
              ),
            )
            .then(() => seleccionarOpcion());
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    })
    .then(() => {
      // Ahora manejar las unidades
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          return hacerClickEnSelect2(
            `#select2-recetas-recetasadd-${receta.id}-unidad_id-container`,
          )
            .then(() =>
              establecerBusqueda(
                `#select2-recetas-recetasadd-${receta.id}-unidad_id-container`,
                receta.unidad,
              ),
            )
            .then(() => seleccionarOpcion());
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    })
    .then(() => {
      // Ahora manejar las pautas
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          return hacerClickEnSelect2(
            `#select2-recetas-recetasadd-${receta.id}-pauta-container`,
          )
            .then(() =>
              establecerBusqueda(
                `#select2-recetas-recetasadd-${receta.id}-pauta-container`,
                receta.pauta,
              ),
            )
            .then(() => seleccionarOpcion());
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    })
    .then(() => {
      // Ahora manejar las cantidades
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          return llenarCampoCantidad(
            `#recetas-recetasadd-${receta.id}-cantidad`,
            receta.cantidad,
            2,
          );
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    })
    .then(() => {
      // Ahora manejar las total_farmacia
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          return llenarCampoTexto(
            `#recetas-recetasadd-${receta.id}-total_farmacia`,
            receta.totalFarmacia,
          );
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    })
    .then(() => {
      // Ahora manejar las observaciones
      return item.recetas.reduce((promise, receta) => {
        return promise.then(() => {
          return llenarCampoTexto(
            `#recetas-recetasadd-${receta.id}-observaciones`,
            receta.observaciones,
          );
        });
      }, Promise.resolve()); // Inicializa con una promesa resuelta
    });
}

function hacerClickEnPresuntivo(selector, numeroDeClicks = 1) {
  return new Promise((resolve, reject) => {
    const botonPresuntivo = document.querySelector(selector);

    if (botonPresuntivo) {
      console.log(
        `Haciendo clic en el checkbox "PRESUNTIVO" ${numeroDeClicks} veces`,
      );
      let contador = 0;
      const intervalo = setInterval(() => {
        botonPresuntivo.click();
        contador++;
        if (contador >= numeroDeClicks) {
          clearInterval(intervalo);
          resolve();
        }
      }, 100); // Intervalo entre clics, ajustable según necesidad
    } else {
      console.error('El checkbox "PRESUNTIVO" no se encontró.');
      reject('El checkbox "PRESUNTIVO" no se encontró.');
    }
  });
}
