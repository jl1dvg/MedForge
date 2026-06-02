/* ============================================================
   MedForge · Solicitudes v2 — mock data
   Coordinación quirúrgica oftalmológica (datos ficticios)
   Exposed on window.MEDF_DATA
   ============================================================ */
(function () {
  // ---- Columnas / etapas del flujo -------------------------------
  const COLUMNS = [
    { slug: "recibida",        label: "Recibida",          phase: "ingreso"   },
    { slug: "llamado",         label: "Turno llamado",     phase: "ingreso"   },
    { slug: "revision-codigos",label: "Revisión códigos",  phase: "validacion"},
    { slug: "espera-documentos",label: "Documentación",    phase: "validacion"},
    { slug: "apto-oftalmologo",label: "Apto oftalmólogo",  phase: "aptitud"   },
    { slug: "apto-anestesia",  label: "Apto anestesia",    phase: "aptitud"   },
    { slug: "listo-para-agenda",label: "Listo p/ agenda",  phase: "agenda"    },
    { slug: "programada",      label: "Programada",        phase: "agenda"    },
    { slug: "completado",      label: "Completado",        phase: "agenda"    },
  ];

  const PHASES = [
    { key: "ingreso",    label: "Ingreso",            icon: "mdi-bullhorn-outline" },
    { key: "validacion", label: "Validación & docs",  icon: "mdi-file-search-outline" },
    { key: "aptitud",    label: "Aptitud clínica",    icon: "mdi-stethoscope" },
    { key: "agenda",     label: "Agenda quirúrgica",  icon: "mdi-calendar-check-outline" },
  ];

  // checklist canonical steps (subset shown on cards)
  const CHECK_STEPS = [
    { slug: "recibida",         label: "Solicitud recibida" },
    { slug: "llamado",          label: "Turno llamado" },
    { slug: "revision-codigos", label: "Códigos validados" },
    { slug: "espera-documentos",label: "Documentos completos" },
    { slug: "apto-oftalmologo", label: "Apto oftalmólogo" },
    { slug: "apto-anestesia",   label: "Apto anestesia" },
    { slug: "listo-para-agenda",label: "Listo para agenda" },
    { slug: "programada",       label: "Cirugía programada" },
  ];

  // ---- Afiliaciones (con color de categoría) ---------------------
  const AFILIACIONES = {
    "IESS":          { label: "IESS",            tone: "visita" },
    "ISSFA":         { label: "ISSFA",           tone: "consulta" },
    "ISSPOL":        { label: "ISSPOL",          tone: "optometria" },
    "MSP":           { label: "MSP — Red Pública", tone: "examen" },
    "Particular":    { label: "Particular",      tone: "neutral" },
    "Seguro":        { label: "Seguro privado",  tone: "cirugia" },
  };

  const PROCEDURES = [
    { full: "FACOEMULSIFICACIÓN + LIO MONOFOCAL", short: "Faco + LIO" },
    { full: "FACOEMULSIFICACIÓN + LIO TÓRICA", short: "Faco + LIO tórica" },
    { full: "VITRECTOMÍA POSTERIOR (VPP) 23G", short: "Vitrectomía (VPP)" },
    { full: "INYECCIÓN INTRAVÍTREA — ANTIANGIOGÉNICO", short: "Inyección intravítrea" },
    { full: "EXÉRESIS DE PTERIGION + INJERTO CONJUNTIVAL", short: "Pterigion + injerto" },
    { full: "CAPSULOTOMÍA YAG LÁSER", short: "Capsulotomía YAG" },
    { full: "TRABECULECTOMÍA", short: "Trabeculectomía" },
    { full: "BLEFAROPLASTIA SUPERIOR FUNCIONAL", short: "Blefaroplastia" },
    { full: "EXÉRESIS DE CHALAZIÓN", short: "Chalazión" },
    { full: "CIRUGÍA DE ESTRABISMO — 2 MÚSCULOS", short: "Estrabismo (2 músc.)" },
    { full: "DACRIOCISTORRINOSTOMÍA (DCR)", short: "Dacriocistorrinostomía" },
    { full: "TRASPLANTE DE CÓRNEA (QUERATOPLASTIA)", short: "Trasplante de córnea" },
  ];

  const DOCTORS = [
    "Dra. Salazar Vinueza", "Dr. Aguirre Cordero", "Dra. Cevallos Ponce",
    "Dr. Mendoza Tapia", "Dr. Paredes Llerena", "Dra. Andrade Bustos",
  ];
  const RESPONSABLES = [
    "Coord. M. Quishpe", "Coord. L. Tobar", "Coord. V. Cañar", "Coord. D. Loor",
  ];
  const SEDES = ["MATRIZ", "NORTE", "VALLE"];
  const FUENTES = ["Consulta externa", "Derivación externa", "WhatsApp", "Call center"];

  const NAMES = [
    "María Fernanda Logroño", "José Antonio Caicedo", "Rosa Elvira Pillajo",
    "Carlos Estuardo Naranjo", "Blanca Lucía Yépez", "Segundo Manuel Chicaiza",
    "Gladys Patricia Morán", "Ángel Rodrigo Tenesaca", "Mariana de Jesús Ushiña",
    "Luis Alberto Quimbiulco", "Esther Beatriz Calderón", "Jorge Washington Lema",
    "Nancy Jacqueline Simbaña", "Pedro Vicente Cuzco", "Martha Cecilia Toapanta",
    "Edison Fabián Guamán", "Carmen Amelia Pazmiño", "Víctor Hugo Cabascango",
    "Dolores del Carmen Iza", "Wilson Patricio Maigua", "Susana Margarita Defaz",
    "Galo Enrique Chuquimarca", "Fanny Rocío Tituaña", "Marco Antonio Llumiquinga",
  ];

  // deterministic pseudo-random helper
  function mulberry32(a) {
    return function () {
      a |= 0; a = (a + 0x6D2B79F5) | 0;
      let t = Math.imul(a ^ (a >>> 15), 1 | a);
      t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
      return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
  }
  const rnd = mulberry32(20260602);
  const pick = (arr) => arr[Math.floor(rnd() * arr.length)];
  const initials = (name) => name.split(/\s+/).slice(0, 2).map((w) => w[0]).join("").toUpperCase();

  function buildChecklist(estadoSlug) {
    const idx = COLUMNS.findIndex((c) => c.slug === estadoSlug);
    let nextLabel = "Completado";
    const list = CHECK_STEPS.map((step, i) => {
      const completed = i <= idx;
      return { slug: step.slug, label: step.label, completed, can_toggle: i === idx + 1 || i === idx };
    });
    const firstPending = list.find((s) => !s.completed);
    if (firstPending) nextLabel = firstPending.label;
    const completed = list.filter((s) => s.completed).length;
    const total = list.length;
    return {
      checklist: list,
      checklist_progress: {
        completed, total,
        percent: Math.round((completed / total) * 100),
        next_label: nextLabel,
      },
    };
  }

  // distribution of cases per column
  const DISTRIBUTION = {
    "recibida": 4, "llamado": 3, "revision-codigos": 3, "espera-documentos": 3,
    "apto-oftalmologo": 2, "apto-anestesia": 2, "listo-para-agenda": 2,
    "programada": 3, "completado": 2,
  };

  function slaFor(estadoSlug) {
    // earlier stages skew more urgent; completed always ok
    if (estadoSlug === "completado") return { sla_status: "ok", sla_hours_remaining: null, sla_label: "Cerrada" };
    if (estadoSlug === "programada") return { sla_status: "ok", sla_hours_remaining: 96, sla_label: "En agenda" };
    const r = rnd();
    if (r < 0.22) return { sla_status: "vencido", sla_hours_remaining: -(2 + Math.floor(rnd() * 30)), sla_label: "SLA vencido" };
    if (r < 0.5) return { sla_status: "critico", sla_hours_remaining: 1 + Math.floor(rnd() * 6), sla_label: "Vence pronto" };
    return { sla_status: "ok", sla_hours_remaining: 12 + Math.floor(rnd() * 60), sla_label: "En tiempo" };
  }

  function alertsFor(estadoSlug, afilKey) {
    const a = [];
    if (estadoSlug === "espera-documentos" || rnd() < 0.25)
      a.push({ key: "docs", label: "Documentos faltantes", icon: "mdi-file-alert-outline", tone: "warning" });
    if (afilKey !== "Particular" && rnd() < 0.45)
      a.push({ key: "auth", label: "Autorización pendiente", icon: "mdi-shield-clock-outline", tone: "danger" });
    if (rnd() < 0.3)
      a.push({ key: "exam", label: "Exámenes por vencer", icon: "mdi-flask-empty-outline", tone: "warning" });
    return a;
  }

  const OBS = [
    "Paciente solicita fecha después del 15.",
    "Requiere transporte institucional.",
    "Acompañante confirmado.",
    "Alergia a penicilina registrada.",
    "Pendiente valoración cardiológica.",
    "", "", "",
  ];

  // protocolo posterior compatible (conciliación de cirugías)
  function protocolFor(estadoSlug, proc, ojo, baseDate) {
    const make = () => ({
      form_id: "PR-" + (70000 + Math.floor(rnd() * 9999)),
      lateralidad: ojo,
      fecha_inicio: new Date(baseDate.getTime() + (2 + Math.floor(rnd() * 6)) * 86400000).toISOString(),
      membrete: proc.full,
    });
    if (estadoSlug === "completado") {
      const p = make();
      return {
        protocolo_confirmado: { ...p, confirmado_at: new Date(baseDate.getTime() + 7 * 86400000).toISOString(), confirmado_by: pick(RESPONSABLES) },
        protocolo_posterior_compatible: p,
      };
    }
    if (estadoSlug === "programada") return { protocolo_confirmado: null, protocolo_posterior_compatible: make() };
    if (estadoSlug === "listo-para-agenda" && rnd() < 0.55) return { protocolo_confirmado: null, protocolo_posterior_compatible: make() };
    if (rnd() < 0.14) return { protocolo_confirmado: null, protocolo_posterior_compatible: make() };
    return { protocolo_confirmado: null, protocolo_posterior_compatible: null };
  }

  // ---- Expediente del caso (CRM + Prefactura) --------------------
  const DIAGS = [
    ["H25.1", "Catarata senil nuclear"],
    ["H40.11", "Glaucoma primario de ángulo abierto"],
    ["H33.0", "Desprendimiento de retina con ruptura"],
    ["H11.0", "Pterigión"],
    ["H35.31", "Degeneración macular relacionada con la edad"],
    ["H02.4", "Ptosis palpebral"],
    ["H50.0", "Estrabismo concomitante convergente"],
    ["H04.5", "Estenosis de vías lagrimales"],
    ["E11.3", "Diabetes mellitus tipo 2 con retinopatía"],
  ];
  const PREOP_STEPS = [
    "Biometría / cálculo de LIO", "Laboratorio prequirúrgico", "Electrocardiograma",
    "Valoración cardiológica", "Valoración anestésica", "Consentimiento informado", "Ayuno confirmado",
  ];
  const NOTE_TXT = [
    "Paciente contactado, confirma asistencia.",
    "Se solicita autorización a la aseguradora vía portal.",
    "Adjunta póliza vigente y cédula.",
    "Exámenes de laboratorio dentro de rango.",
    "Familiar acompañante confirmado para el día de cirugía.",
    "Se reagenda por disponibilidad de quirófano.",
  ];
  const TASK_TXT = [
    "Llamar al paciente para confirmar fecha",
    "Solicitar autorización al seguro",
    "Adjuntar póliza vigente",
    "Verificar exámenes de laboratorio",
    "Confirmar disponibilidad de quirófano",
    "Enviar instrucciones preoperatorias",
  ];
  const ADJ_TXT = [
    ["Cédula de identidad", "mdi-card-account-details-outline", "PDF · 240 KB"],
    ["Póliza de seguro", "mdi-shield-check-outline", "PDF · 1.1 MB"],
    ["Derivación / autorización", "mdi-file-document-outline", "PDF · 380 KB"],
    ["Exámenes de laboratorio", "mdi-flask-outline", "PDF · 620 KB"],
    ["Consentimiento informado", "mdi-file-sign", "PDF · 210 KB"],
  ];
  const PROPOSAL_ITEMS = [
    ["DQX-001", "Derecho de quirófano", 1, 320],
    ["HON-014", "Honorarios cirujano oftalmólogo", 1, 480],
    ["ANE-007", "Anestesia y honorarios anestesiólogo", 1, 180],
    ["LIO-220", "Lente intraocular monofocal", 1, 240],
    ["INS-330", "Insumos y material quirúrgico", 1, 150],
  ];
  const PROPOSAL_STATES = ["Borrador", "Enviada", "Aceptada"];

  function take(arr, n, baseDate) {
    const out = [];
    for (let i = 0; i < n; i++) out.push(arr[i % arr.length]);
    return out;
  }

  function buildDetalle(sol, baseDate, afilKey) {
    // paciente
    const sexo = rnd() < 0.5 ? "F" : "M";
    const edad = 28 + Math.floor(rnd() * 55);
    const paciente = {
      edad, sexo, cedula: "17" + (10000000 + Math.floor(rnd() * 89999999)),
      direccion: pick(["Quito · La Mariscal", "Quito · Cumbayá", "Sangolquí · centro", "Quito · El Inca", "Machachi"]),
    };
    // diagnósticos
    const nd = 1 + Math.floor(rnd() * 2);
    const diagnosticos = [];
    for (let i = 0; i < nd; i++) { const d = pick(DIAGS); if (!diagnosticos.find(x => x.cie === d[0])) diagnosticos.push({ cie: d[0], desc: d[1] }); }
    // derivación / cobertura
    const particular = afilKey === "Particular";
    const dias = particular ? null : (rnd() < 0.25 ? -(2 + Math.floor(rnd() * 20)) : 10 + Math.floor(rnd() * 80));
    const derivacion = {
      tiene: !particular,
      cod: particular ? null : "DRV-" + (50000 + Math.floor(rnd() * 9999)),
      aseguradora: sol.afiliacion_label,
      plan: pick(["Plan integral", "Cobertura ambulatoria", "Plan quirúrgico", "Convenio institucional"]),
      dias_vigencia: dias,
      vencida: dias != null && dias < 0,
      archivo: !particular,
      autorizacion_pendiente: sol.alerts.some(a => a.key === "auth"),
    };
    // preop checklist
    const colIdx = COLUMNS.findIndex(c => c.slug === sol.estado);
    const preopDone = Math.min(PREOP_STEPS.length, Math.round((colIdx / (COLUMNS.length - 1)) * PREOP_STEPS.length));
    const preop = PREOP_STEPS.map((label, i) => ({ label, done: i < preopDone }));
    // notas
    const notas = take(NOTE_TXT, Math.min(sol.crm.notas, 4), baseDate).map((txt, i) => ({
      txt, by: i === 0 ? "Recepción" : sol.crm.responsable,
      at: new Date(baseDate.getTime() + (i + 1) * 7200000).toISOString(),
    }));
    // tareas
    const tareas = [];
    for (let i = 0; i < sol.crm.tareas_total; i++) {
      tareas.push({
        titulo: TASK_TXT[(i + sol.id) % TASK_TXT.length],
        asignado: sol.crm.responsable,
        fecha: new Date(baseDate.getTime() + (i + 2) * 86400000).toISOString(),
        prioridad: pick(["Alta", "Media", "Normal"]),
        done: i >= sol.crm.tareas_pendientes,
      });
    }
    // propuestas
    const npr = sol.estado === "completado" ? 1 : (rnd() < 0.4 ? 1 : 0);
    const propuestas = [];
    for (let i = 0; i < npr; i++) {
      const nItems = 3 + Math.floor(rnd() * 3);
      const items = PROPOSAL_ITEMS.slice(0, nItems).map(([cod, desc, cant, valor]) => ({ cod, desc, cant, valor }));
      const subtotal = items.reduce((s, it) => s + it.cant * it.valor, 0);
      const iva = Math.round(subtotal * 0.15 * 100) / 100;
      propuestas.push({
        titulo: "Paquete quirúrgico — " + sol.procedimiento_short,
        estado: sol.estado === "completado" ? "Aceptada" : pick(PROPOSAL_STATES),
        vigencia: new Date(baseDate.getTime() + 20 * 86400000).toISOString(),
        items, subtotal, iva, total: subtotal + iva,
      });
    }
    // adjuntos
    const adjuntos = take(ADJ_TXT, Math.min(sol.crm.adjuntos, 5), baseDate).map(([nombre, icon, peso], i) => ({
      nombre, icon, peso, at: new Date(baseDate.getTime() - i * 86400000).toISOString(),
    }));
    // examen / plan
    const examen = {
      av_od: pick(["20/20", "20/40", "20/60", "20/200", "CD 2m"]),
      av_oi: pick(["20/20", "20/40", "20/60", "20/200", "CD 2m"]),
      pio_od: 10 + Math.floor(rnd() * 12), pio_oi: 10 + Math.floor(rnd() * 12),
      plan: `${sol.procedimiento} en ${sol.ojo}. Continuar tratamiento tópico y control postoperatorio a las 24h.`,
    };
    // agenda / sigcenter
    const agenda = {
      sala: "Quirófano " + (1 + Math.floor(rnd() * 3)),
      fecha: ["programada", "completado"].includes(sol.estado) ? new Date(baseDate.getTime() + 5 * 86400000).toISOString() : null,
      duracion: 30 + 15 * Math.floor(rnd() * 4),
      anestesia: pick(["Tópica", "Local + sedación", "General"]),
    };
    return { paciente, diagnosticos, derivacion, preop, notas, tareas, propuestas, adjuntos, examen, agenda };
  }

  let id = 4820;
  let turno = 0;
  const solicitudes = [];
  let nameIdx = 0;

  COLUMNS.forEach((col) => {
    const n = DISTRIBUTION[col.slug] || 0;
    for (let i = 0; i < n; i++) {
      const afilKey = pick(Object.keys(AFILIACIONES));
      const proc = pick(PROCEDURES);
      const prioridad = rnd() < 0.28 ? "urgente" : "normal";
      const sla = slaFor(col.slug);
      const chk = buildChecklist(col.slug);
      const name = NAMES[nameIdx % NAMES.length]; nameIdx++;
      const showTurno = (col.slug === "recibida" || col.slug === "llamado");
      const daysAgo = Math.floor(rnd() * 9);
      const d = new Date(2026, 5, 2 - daysAgo, 8 + Math.floor(rnd() * 9), Math.floor(rnd() * 59));
      const ojo = pick(["OD", "OI", "AO"]);
      const sol = {
        id: id++,
        form_id: "PD-" + (90000 + Math.floor(rnd() * 9999)),
        hc_number: String(120000 + Math.floor(rnd() * 79999)),
        full_name: name,
        avatar_initials: initials(name),
        doctor: pick(DOCTORS),
        afiliacion: afilKey,
        afiliacion_label: AFILIACIONES[afilKey].label,
        afiliacion_tone: AFILIACIONES[afilKey].tone,
        procedimiento: proc.full,
        procedimiento_short: proc.short,
        ojo,
        prioridad,
        estado: col.slug,
        estado_label: col.label,
        fecha: d.toISOString(),
        sede: pick(SEDES),
        observacion: pick(OBS),
        ...sla,
        ...chk,
        ...protocolFor(col.slug, proc, ojo, d),
        crm: {
          responsable: pick(RESPONSABLES),
          telefono: "09" + (60000000 + Math.floor(rnd() * 39999999)),
          email: name.split(" ")[0].toLowerCase() + "@correo.ec",
          fuente: pick(FUENTES),
          notas: Math.floor(rnd() * 6),
          adjuntos: Math.floor(rnd() * 5),
          tareas_pendientes: Math.floor(rnd() * 3),
          tareas_total: Math.floor(rnd() * 4) + 1,
          proximo_vencimiento: rnd() < 0.6 ? new Date(2026, 5, 4 + Math.floor(rnd() * 10)).toISOString() : null,
          pipeline: col.label,
        },
        alerts: alertsFor(col.slug, afilKey),
        turno: showTurno ? "A" + String(++turno).padStart(2, "0") : null,
      };
      sol.detalle = buildDetalle(sol, d, afilKey);
      solicitudes.push(sol);
    }
  });

  window.MEDF_DATA = { COLUMNS, PHASES, PHASES_MAP: Object.fromEntries(PHASES.map(p=>[p.key,p])), AFILIACIONES, DOCTORS, SEDES, solicitudes };
})();
