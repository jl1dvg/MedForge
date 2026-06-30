/* MedForge Control Center — mock data (realistic, Spanish). Exposed on window. */

/* ---- operational state metadata ---- */
const CC_STATES = {
  produccion: {
    key: "produccion", label: "Producción", cls: "prod", icon: "mdi-check-decagram",
    short: "Producción",
    impact: "El sistema opera con total normalidad. Todos los usuarios autorizados pueden consultar, crear, editar, aprobar, facturar y enviar registros sin restricciones.",
    msg: null,
  },
  mantenimiento: {
    key: "mantenimiento", label: "Mantenimiento", cls: "maint", icon: "mdi-wrench-clock",
    short: "Mantenimiento",
    impact: "Solo el personal interno y los usuarios autorizados pueden operar. El resto del equipo verá un aviso de mantenimiento y no podrá ingresar hasta la reactivación.",
    msg: "El sistema se encuentra temporalmente en mantenimiento programado. Estamos aplicando mejoras y estará disponible nuevamente en breve. Agradecemos tu comprensión.",
  },
  lectura: {
    key: "lectura", label: "Solo lectura", cls: "read", icon: "mdi-eye-lock-outline",
    short: "Solo lectura",
    impact: "Los usuarios pueden consultar la información existente, pero no podrán crear, editar, eliminar, aprobar, facturar ni enviar nuevos registros.",
    msg: "Este cliente se encuentra en modo Solo Lectura. Los usuarios pueden consultar información existente, pero no podrán crear, editar, eliminar, aprobar, facturar ni enviar nuevos registros hasta que el servicio sea reactivado.",
  },
  suspendido: {
    key: "suspendido", label: "Suspendido", cls: "susp", icon: "mdi-cancel",
    short: "Suspendido",
    impact: "El cliente no puede ingresar al sistema. Todos los accesos quedan bloqueados hasta regularizar la situación contractual o de pago.",
    msg: "El acceso a esta plataforma se encuentra temporalmente suspendido. Por favor comunícate con el área administrativa de tu organización para regularizar el servicio.",
  },
};

const CC_PLANS = {
  Enterprise:   { tone: "acc",  color: "#7b80ff" },
  Professional: { tone: "read", color: "#4aa8ff" },
  Starter:      { tone: "mute", color: "#7d8aa3" },
  Trial:        { tone: "beta", color: "#b58bff" },
  Custom:       { tone: "maint",color: "#f5b53d" },
};

/* ---- clients ---- */
const CC_CLIENTS = [
  {
    id: "cive", nombre: "CIVE", razon: "Centro Integral de la Visión y el Ojo S.A.",
    color: "#5156be", inicial: "CI", dominio: "cive.medforge.app", ruc: "1791345678001",
    plan: "Enterprise", estado: "produccion", ciudad: "Quito",
    usuarios: 248, usuariosMax: 300, ultimaActividad: "hace 2 min",
    version: "2026.6.1", canal: "Stable", versionDisp: "2026.6.1",
    pago: "ok", pagoLabel: "Al día", inicio: "12 mar 2023", vence: "12 mar 2027",
    ultimoDeploy: "28 jun 2026, 02:14", ultimoBackup: "30 jun 2026, 03:00",
    tickets: 2, riesgo: "bajo",
    contactoAdmin: { n: "Mónica Vélez", c: "monica.velez@cive.ec", t: "+593 99 845 1120" },
    contactoTec:   { n: "Ing. Daniel Pérez", c: "sistemas@cive.ec", t: "+593 98 110 4422" },
    iaTokens: 4_820_000, iaCosto: 612, iaPct: 64, waMsgs: 18420, waConv: 3110,
    storage: 182, storageMax: 500, pdfs: 9240, reportes: 412, apiCalls: 1_240_000,
  },
  {
    id: "altavision", nombre: "Alta Visión", razon: "Alta Visión Centro Oftalmológico Cía. Ltda.",
    color: "#0f9d8c", inicial: "AV", dominio: "altavision.medforge.app", ruc: "0992876541001",
    plan: "Professional", estado: "mantenimiento", ciudad: "Guayaquil",
    usuarios: 64, usuariosMax: 80, ultimaActividad: "hace 1 h",
    version: "2026.5.3", canal: "Stable", versionDisp: "2026.6.1",
    pago: "ok", pagoLabel: "Al día", inicio: "04 ago 2024", vence: "04 ago 2026",
    ultimoDeploy: "11 jun 2026, 23:40", ultimoBackup: "30 jun 2026, 03:00",
    tickets: 5, riesgo: "medio",
    contactoAdmin: { n: "Rosa Calderón", c: "administracion@altavision.ec", t: "+593 99 220 7781" },
    contactoTec:   { n: "Soporte TI", c: "ti@altavision.ec", t: "+593 4 260 1180" },
    iaTokens: 1_310_000, iaCosto: 168, iaPct: 41, waMsgs: 6240, waConv: 980,
    storage: 64, storageMax: 200, pdfs: 3120, reportes: 145, apiCalls: 412_000,
  },
  {
    id: "saludvisual", nombre: "Salud Visual", razon: "Salud Visual Integral S.A.",
    color: "#d59623", inicial: "SV", dominio: "saludvisual.medforge.app", ruc: "0103456789001",
    plan: "Professional", estado: "lectura", ciudad: "Cuenca",
    usuarios: 39, usuariosMax: 80, ultimaActividad: "hace 3 h",
    version: "2026.4.0", canal: "Stable", versionDisp: "2026.6.1",
    pago: "vencido", pagoLabel: "Pago vencido", inicio: "19 ene 2025", vence: "19 ene 2026",
    ultimoDeploy: "02 abr 2026, 21:10", ultimoBackup: "29 jun 2026, 03:00",
    tickets: 8, riesgo: "alto",
    contactoAdmin: { n: "Carlos Andrade", c: "gerencia@saludvisual.ec", t: "+593 99 501 3340" },
    contactoTec:   { n: "Externo — DevOps", c: "soporte@saludvisual.ec", t: "+593 7 405 2290" },
    iaTokens: 690_000, iaCosto: 88, iaPct: 28, waMsgs: 2980, waConv: 510,
    storage: 41, storageMax: 200, pdfs: 1840, reportes: 78, apiCalls: 196_000,
  },
  {
    id: "demo", nombre: "Clínica Demo", razon: "Clínica Demo (Ambiente de evaluación)",
    color: "#7C4DFF", inicial: "CD", dominio: "demo.medforge.app", ruc: "9999999999001",
    plan: "Trial", estado: "produccion", ciudad: "Quito",
    usuarios: 8, usuariosMax: 10, ultimaActividad: "hace 12 min",
    version: "2026.7.0-beta", canal: "Beta", versionDisp: "2026.7.0-beta",
    pago: "trial", pagoLabel: "Trial — 9 días", inicio: "21 jun 2026", vence: "09 jul 2026",
    ultimoDeploy: "29 jun 2026, 18:02", ultimoBackup: "30 jun 2026, 03:00",
    tickets: 1, riesgo: "bajo",
    contactoAdmin: { n: "Equipo MedForge", c: "demo@medforge.app", t: "—" },
    contactoTec:   { n: "Equipo MedForge", c: "demo@medforge.app", t: "—" },
    iaTokens: 120_000, iaCosto: 15, iaPct: 12, waMsgs: 410, waConv: 88,
    storage: 6, storageMax: 25, pdfs: 210, reportes: 12, apiCalls: 28_000,
  },
  {
    id: "hospitalquito", nombre: "Hospital Quito", razon: "Hospital General Quito S.A.",
    color: "#ee3158", inicial: "HQ", dominio: "hospitalquito.medforge.app", ruc: "1790012345001",
    plan: "Enterprise", estado: "suspendido", ciudad: "Quito",
    usuarios: 0, usuariosMax: 500, ultimaActividad: "hace 14 días",
    version: "2026.3.2", canal: "Stable", versionDisp: "2026.6.1",
    pago: "vencido", pagoLabel: "Pago vencido", inicio: "08 feb 2024", vence: "08 feb 2026",
    ultimoDeploy: "15 mar 2026, 01:30", ultimoBackup: "16 jun 2026, 03:00",
    tickets: 12, riesgo: "crítico",
    contactoAdmin: { n: "Dirección Administrativa", c: "admin@hospitalquito.ec", t: "+593 2 380 9900" },
    contactoTec:   { n: "Departamento de Sistemas", c: "sistemas@hospitalquito.ec", t: "+593 2 380 9912" },
    iaTokens: 0, iaCosto: 0, iaPct: 0, waMsgs: 0, waConv: 0,
    storage: 318, storageMax: 600, pdfs: 0, reportes: 0, apiCalls: 0,
  },
];

/* ---- feature flags (per client overrides simplified to global catalog) ---- */
const CC_FEATURES = [
  { id: "crm2", nombre: "CRM V2", icon: "mdi-account-heart-outline", env: "Producción", riesgo: "bajo", on: true, mod: "18 jun 2026", resp: "A. Torres", desc: "Nueva gestión de relaciones y embudo comercial con backlog y seguimiento." },
  { id: "wa3", nombre: "WhatsApp V3", icon: "mdi-whatsapp", env: "Beta", riesgo: "medio", on: true, mod: "24 jun 2026", resp: "M. Gómez", desc: "Motor de autorespuesta y agente IA con flujos visuales (Flowmaker)." },
  { id: "protoreact", nombre: "Protocolos React", icon: "mdi-file-document-edit-outline", env: "Beta", riesgo: "medio", on: false, mod: "12 jun 2026", resp: "D. Pérez", desc: "Editor de protocolos clínicos reescrito en React, reemplaza el legado Blade." },
  { id: "dashej", nombre: "Dashboard Ejecutivo", icon: "mdi-view-dashboard-variant-outline", env: "Producción", riesgo: "bajo", on: true, mod: "09 jun 2026", resp: "A. Torres", desc: "Tablero gerencial con KPIs operativos y financieros en tiempo real." },
  { id: "farmacia", nombre: "Farmacia", icon: "mdi-pill", env: "Producción", riesgo: "bajo", on: false, mod: "01 may 2026", resp: "L. Mora", desc: "Módulo de inventario y dispensación de farmacia con control de lotes." },
  { id: "ia", nombre: "Asistente IA", icon: "mdi-auto-fix", env: "Producción", riesgo: "alto", on: true, mod: "27 jun 2026", resp: "M. Gómez", desc: "Documentación asistida, resúmenes clínicos y sugerencias de protocolo." },
  { id: "iess", nombre: "Facturación IESS", icon: "mdi-cash-register", env: "Producción", riesgo: "alto", on: true, mod: "20 jun 2026", resp: "R. Calderón", desc: "Integración de prefacturación y planillaje con el IESS y convenios." },
  { id: "reportes", nombre: "Reportes Gerenciales", icon: "mdi-chart-bar", env: "Producción", riesgo: "bajo", on: true, mod: "15 jun 2026", resp: "A. Torres", desc: "Reportería avanzada exportable a PDF y Excel por sede y afiliación." },
  { id: "sigcenter", nombre: "Integración SigCenter", icon: "mdi-sync", env: "Experimental", riesgo: "alto", on: false, mod: "06 jun 2026", resp: "D. Pérez", desc: "Sincronización bidireccional con el EHR externo SigCenter / Sistema CIVE." },
  { id: "movil", nombre: "App Móvil", icon: "mdi-cellphone", env: "Experimental", riesgo: "medio", on: false, mod: "28 may 2026", resp: "Equipo Móvil", desc: "Aplicación móvil para profesionales con agenda y notificaciones push." },
];

/* ---- services health per client (id -> services) ---- */
const CC_SERVICE_DEFS = [
  { id: "web", nombre: "Aplicación Web", icon: "mdi-web" },
  { id: "db", nombre: "Base de datos", icon: "mdi-database" },
  { id: "wa", nombre: "WhatsApp API", icon: "mdi-whatsapp" },
  { id: "sig", nombre: "SigCenter Sync", icon: "mdi-sync" },
  { id: "cron", nombre: "Scheduler / Cron", icon: "mdi-clock-outline" },
  { id: "queue", nombre: "Queue Workers", icon: "mdi-tray-full" },
  { id: "backup", nombre: "Backups", icon: "mdi-backup-restore" },
  { id: "smtp", nombre: "Email SMTP", icon: "mdi-email-outline" },
  { id: "ia", nombre: "IA / OpenAI", icon: "mdi-brain" },
  { id: "storage", nombre: "Storage", icon: "mdi-folder-outline" },
];
const SVC = { ok: "operativo", deg: "degradado", err: "error", pause: "pausado", none: "no_config" };
const CC_SERVICE_STATE = {
  cive:        { web:"ok", db:"ok", wa:"ok", sig:"ok", cron:"ok", queue:"ok", backup:"ok", smtp:"ok", ia:"ok", storage:"ok" },
  altavision:  { web:"pause", db:"ok", wa:"deg", sig:"none", cron:"ok", queue:"ok", backup:"ok", smtp:"ok", ia:"ok", storage:"ok" },
  saludvisual: { web:"ok", db:"ok", wa:"ok", sig:"none", cron:"ok", queue:"deg", backup:"err", smtp:"ok", ia:"ok", storage:"ok" },
  demo:        { web:"ok", db:"ok", wa:"ok", sig:"none", cron:"ok", queue:"ok", backup:"ok", smtp:"deg", ia:"ok", storage:"ok" },
  hospitalquito:{ web:"pause", db:"pause", wa:"pause", sig:"none", cron:"pause", queue:"pause", backup:"ok", smtp:"pause", ia:"pause", storage:"ok" },
};
const CC_SVC_META = {
  operativo:  { label: "Operativo",      cls: "prod",  color: "var(--st-prod)" },
  degradado:  { label: "Degradado",      cls: "maint", color: "var(--st-maint)" },
  error:      { label: "Error",          cls: "susp",  color: "var(--st-susp)" },
  pausado:    { label: "Pausado",        cls: "mute",  color: "var(--st-mute)" },
  no_config:  { label: "No configurado", cls: "mute",  color: "var(--st-mute)" },
};
const SVC_KEYMAP = { ok:"operativo", deg:"degradado", err:"error", pause:"pausado", none:"no_config" };

/* ---- plans ---- */
const CC_PLAN_CARDS = [
  { nombre: "Starter", precio: 149, color: "#7d8aa3", usuarios: "10", modulos: "Núcleo clínico", ia: "200K tokens", wa: "1.000 msj", storage: "25 GB", soporte: "Email", sla: "99.0%", clientes: 0, destacado: false },
  { nombre: "Professional", precio: 489, color: "#4aa8ff", usuarios: "80", modulos: "Núcleo + CRM + WhatsApp", ia: "2M tokens", wa: "10.000 msj", storage: "200 GB", soporte: "Email + Chat", sla: "99.5%", clientes: 2, destacado: true },
  { nombre: "Enterprise", precio: 1290, color: "#7b80ff", usuarios: "300+", modulos: "Todos los módulos", ia: "8M tokens", wa: "Ilimitado", storage: "500 GB", soporte: "Dedicado 24/7", sla: "99.9%", clientes: 2, destacado: false },
  { nombre: "Custom", precio: null, color: "#f5b53d", usuarios: "A medida", modulos: "A medida + on-premise", ia: "A medida", wa: "A medida", storage: "A medida", soporte: "Account Manager", sla: "99.95%", clientes: 0, destacado: false },
];

/* ---- deploys / releases ---- */
const CC_RELEASES = [
  { v: "2026.7.0-beta", canal: "Beta", fecha: "29 jun 2026", resp: "Plataforma", titulo: "Protocolos React + nuevo motor de reportes", estado: "En pruebas", cls: "beta" },
  { v: "2026.6.1", canal: "Stable", fecha: "28 jun 2026", resp: "Plataforma", titulo: "Hotfix facturación IESS y mejoras de rendimiento", estado: "Disponible", cls: "prod" },
  { v: "2026.6.0", canal: "Stable", fecha: "14 jun 2026", resp: "Plataforma", titulo: "Dashboard Ejecutivo y consumo de IA por sede", estado: "Disponible", cls: "prod" },
  { v: "2026.5.3", canal: "Stable", fecha: "11 jun 2026", resp: "Plataforma", titulo: "Estabilidad WhatsApp V3 y colas de mensajería", estado: "Disponible", cls: "prod" },
  { v: "2026.4.0", canal: "Stable", fecha: "02 abr 2026", resp: "Plataforma", titulo: "Kanban de flujo de pacientes y turnero", estado: "Disponible", cls: "prod" },
];

/* ---- consumption monthly series ---- */
const CC_MONTHS = ["Ene","Feb","Mar","Abr","May","Jun"];
const CC_CONSUMO = {
  iaTokens:  [3.1, 3.6, 4.0, 4.4, 5.2, 6.9],     // millones
  iaCosto:   [398, 462, 511, 560, 668, 883],     // USD
  waMsgs:    [19.2, 21.0, 22.4, 24.1, 26.8, 28.0], // miles
  conv:      [3.4, 3.8, 4.1, 4.0, 4.6, 4.7],     // miles
  pdfs:      [9.8, 10.4, 11.1, 12.0, 13.2, 14.6],// miles
  reportes:  [410, 468, 502, 540, 612, 647],
  storage:   [402, 441, 478, 520, 566, 611],     // GB total
  api:       [1.4, 1.5, 1.6, 1.7, 1.9, 2.1],     // millones
};

/* ---- audit / events ---- */
const CC_AUDIT = [
  { tipo: "estado", icon: "mdi-eye-lock-outline", cls: "read", titulo: "Salud Visual cambió a Solo lectura", desc: "Suspensión parcial automática por factura vencida (#FAC-2025-0418). Motivo interno: «Mora superior a 30 días».", actor: "Sistema · Facturación", cliente: "Salud Visual", when: "hace 3 h" },
  { tipo: "deploy", icon: "mdi-rocket-launch-outline", cls: "acc", titulo: "Deploy 2026.7.0-beta en Clínica Demo", desc: "Canal Beta. Incluye Protocolos React y nuevo motor de reportes.", actor: "A. Torres", cliente: "Clínica Demo", when: "hace 6 h" },
  { tipo: "feature", icon: "mdi-toggle-switch-outline", cls: "prod", titulo: "Asistente IA activado en CIVE", desc: "Feature flag «ia» → ON. Ambiente Producción. Riesgo alto revisado por seguridad.", actor: "M. Gómez", cliente: "CIVE", when: "hace 9 h" },
  { tipo: "error", icon: "mdi-alert-octagon-outline", cls: "susp", titulo: "Fallo de backup en Salud Visual", desc: "El backup nocturno (03:00) terminó con error: timeout de almacenamiento externo. Reintento programado.", actor: "Scheduler", cliente: "Salud Visual", when: "hace 11 h" },
  { tipo: "licencia", icon: "mdi-license", cls: "maint", titulo: "Licencia de Hospital Quito vencida", desc: "El contrato Enterprise venció el 08 feb 2026. Acceso suspendido tras periodo de gracia de 30 días.", actor: "Sistema · Licencias", cliente: "Hospital Quito", when: "hace 1 día" },
  { tipo: "estado", icon: "mdi-wrench-clock", cls: "maint", titulo: "Alta Visión entró en Mantenimiento", desc: "Ventana de mantenimiento programado para migración de base de datos. Fin estimado: 30 jun 08:00.", actor: "D. Pérez", cliente: "Alta Visión", when: "hace 1 día" },
  { tipo: "backup", icon: "mdi-backup-restore", cls: "prod", titulo: "Backup global completado", desc: "5 de 5 instancias respaldadas correctamente. Snapshot retenido por 30 días.", actor: "Scheduler", cliente: "Global", when: "hace 1 día" },
  { tipo: "soporte", icon: "mdi-lifebuoy", cls: "read", titulo: "Ticket #4821 escalado — CIVE", desc: "Soporte de nivel 2 asignó el ticket de integración SigCenter al equipo de plataforma.", actor: "Soporte", cliente: "CIVE", when: "hace 2 días" },
  { tipo: "feature", icon: "mdi-toggle-switch-off-outline", cls: "mute", titulo: "Integración SigCenter desactivada (Experimental)", desc: "Feature flag «sigcenter» → OFF en todos los clientes por inestabilidad del proveedor.", actor: "D. Pérez", cliente: "Global", when: "hace 3 días" },
];

/* ---- per-client operational state history ---- */
const CC_STATE_HISTORY = {
  saludvisual: [
    { estado: "lectura", actor: "Sistema · Facturación", motivo: "Factura vencida #FAC-2025-0418 — mora > 30 días.", when: "30 jun 2026, 09:12" },
    { estado: "produccion", actor: "M. Vélez", motivo: "Reactivación tras pago parcial.", when: "12 may 2026, 14:30" },
    { estado: "lectura", actor: "Sistema · Facturación", motivo: "Primer aviso de mora.", when: "02 may 2026, 00:05" },
  ],
  cive: [
    { estado: "produccion", actor: "A. Torres", motivo: "Operación normal desde la implementación.", when: "12 mar 2023, 10:00" },
  ],
  altavision: [
    { estado: "mantenimiento", actor: "D. Pérez", motivo: "Migración de base de datos programada.", when: "29 jun 2026, 22:00" },
    { estado: "produccion", actor: "R. Calderón", motivo: "Operación normal.", when: "04 ago 2024, 09:00" },
  ],
  hospitalquito: [
    { estado: "suspendido", actor: "Sistema · Licencias", motivo: "Contrato vencido + mora. Periodo de gracia agotado.", when: "10 mar 2026, 00:00" },
    { estado: "lectura", actor: "Sistema · Facturación", motivo: "Aviso de vencimiento de licencia.", when: "08 feb 2026, 00:00" },
    { estado: "produccion", actor: "A. Torres", motivo: "Operación normal.", when: "08 feb 2024, 09:00" },
  ],
  demo: [
    { estado: "produccion", actor: "Equipo MedForge", motivo: "Ambiente de evaluación activo.", when: "21 jun 2026, 12:00" },
  ],
};

const fmtNum = (n) => n.toLocaleString("es-EC");
const fmtMoney = (n) => "$" + n.toLocaleString("es-EC");

Object.assign(window, {
  CC_STATES, CC_PLANS, CC_CLIENTS, CC_FEATURES, CC_SERVICE_DEFS, CC_SERVICE_STATE,
  CC_SVC_META, SVC_KEYMAP, CC_PLAN_CARDS, CC_RELEASES, CC_MONTHS, CC_CONSUMO,
  CC_AUDIT, CC_STATE_HISTORY, fmtNum, fmtMoney,
});
