/* MedForge — WhatsApp Chat v3 · mock data
   Isolated prototype. Mirrors the data shapes the Blade view consumes
   (tabs, advanced filters, manager metrics, roles, templates, notes, media)
   so every V3 feature has something real to render. */

window.WA_DATA = (function () {

  /* ---- Conversations ---- */
  const CONVOS = [
    { id: 1, name: "María Fernanda Ruiz", wa: "+593 99 845 1230", hc: "80211",
      preview: "Perfecto, doctora. ¿Puedo ir mañana a las 9 am?", time: "10:42", unread: 2,
      status: "open", tone: "violet", initials: "MR", unreadFlag: true,
      bucket: "mine", buckets: ["mine", "in_progress", "captacion", "unread", "window_open"],
      window: "open", priority: "Alta", opStatus: "En gestión", lastActor: "Paciente",
      queue: "Captación", attribution: "Meta Ads · Catarata 60+",
      assignedTo: "Dra. Carolina Rivera", assignedRole: "Oftalmología", isMine: true,
      patient: { name: "María F. Ruiz", age: 64, dx: "Catarata bilateral", lastVisit: "12 mar 2026", nextAppt: "25 mar 2026 · Pre-quirúrgico" } },
    { id: 2, name: "Carlos Andrade Vega", wa: "+593 98 221 7714", hc: null,
      preview: "📎 Receta_oftalmologia.pdf", time: "10:31", unread: 0,
      status: "urgent", tone: "rose", initials: "CA",
      bucket: "requires_attention", buckets: ["requires_attention", "captacion", "window_open"],
      window: "open", priority: "Alta", opStatus: "Sin asignar", lastActor: "Paciente",
      queue: "Captación", attribution: "Formulario web",
      assignedTo: null, assignedRole: null, isMine: false,
      patient: { name: "Carlos Andrade", age: 58, dx: "Sin vincular", lastVisit: "—", nextAppt: "—" } },
    { id: 3, name: "Ana Lucía Pérez", wa: "+593 99 110 0552", hc: "79430",
      preview: "Gracias, agendado ✓", time: "09:58", unread: 0,
      status: "open", tone: "green", initials: "AP",
      bucket: "scheduled", buckets: ["scheduled", "operacion"],
      window: "open", priority: "Media", opStatus: "Agendada", lastActor: "Agente",
      queue: "Operación", attribution: "Derivación interna",
      assignedTo: "Lcda. Paola Cordero", assignedRole: "Admisión", isMine: false,
      patient: { name: "Ana L. Pérez", age: 47, dx: "Pterigión OD", lastVisit: "02 mar 2026", nextAppt: "28 mar 2026 · Consulta" } },
    { id: 4, name: "Juan Pablo Loor", wa: "+593 96 778 4421", hc: "78992",
      preview: "Voy a confirmar con mi familia y le aviso.", time: "09:14", unread: 0,
      status: "warn", tone: "amber", initials: "JL",
      bucket: "waiting_patient", buckets: ["waiting_patient", "operacion", "window_open"],
      window: "open", priority: "Media", opStatus: "Esperando paciente", lastActor: "Agente",
      queue: "Operación", attribution: "Llamada saliente",
      assignedTo: "Dra. Carolina Rivera", assignedRole: "Oftalmología", isMine: true,
      patient: { name: "Juan P. Loor", age: 69, dx: "Glaucoma", lastVisit: "20 feb 2026", nextAppt: "—" } },
    { id: 5, name: "Esteban Quintero", wa: "+593 99 002 3398", hc: "81550",
      preview: "¿Sigue válida la cotización del lunes?", time: "Ayer", unread: 1,
      status: "warn", tone: "blue", initials: "EQ", unreadFlag: true,
      bucket: "mine", buckets: ["mine", "in_progress", "captacion", "unread", "needs_template"],
      window: "template", priority: "Alta", opStatus: "En gestión", lastActor: "Paciente",
      queue: "Captación", attribution: "Meta Ads · Cirugía refractiva",
      assignedTo: "Dra. Carolina Rivera", assignedRole: "Oftalmología", isMine: true,
      patient: { name: "Esteban Quintero", age: 33, dx: "Miopía alta", lastVisit: "—", nextAppt: "—" } },
    { id: 6, name: "Sofía Méndez García", wa: "+593 98 553 0917", hc: "80017",
      preview: "Tú: Listo, te envío el bono ahora.", time: "Ayer", unread: 0,
      status: "open", tone: "cyan", initials: "SM",
      bucket: "in_progress", buckets: ["in_progress", "operacion"],
      window: "open", priority: "Media", opStatus: "En gestión", lastActor: "Agente",
      queue: "Operación", attribution: "Derivación interna",
      assignedTo: "Lic. Juan Mejía", assignedRole: "Caja", isMine: false,
      patient: { name: "Sofía Méndez", age: 51, dx: "Catarata OD", lastVisit: "11 mar 2026", nextAppt: "30 mar 2026 · Cirugía" } },
    { id: 7, name: "Pedro Albán", wa: "+593 99 990 1280", hc: "78201",
      preview: "🎯 Vino por anuncio en Meta Ads.", time: "Ayer", unread: 0,
      status: "open", tone: "violet", initials: "PA",
      bucket: "mine", buckets: ["mine", "captacion", "window_open"],
      window: "open", priority: "Baja", opStatus: "En gestión", lastActor: "Bot",
      queue: "Captación", attribution: "Meta Ads · Catarata 60+",
      assignedTo: "Dra. Carolina Rivera", assignedRole: "Oftalmología", isMine: true,
      patient: { name: "Pedro Albán", age: 71, dx: "Sin vincular", lastVisit: "—", nextAppt: "—" } },
    { id: 8, name: "Luisa Cárdenas", wa: "+593 99 487 1102", hc: "79880",
      preview: "Buenos días, quería preguntar por mi cirugía.", time: "Lun", unread: 0,
      status: "urgent", tone: "rose", initials: "LC",
      bucket: "requires_attention", buckets: ["requires_attention", "critical_backlog", "operacion", "unread"],
      window: "open", priority: "Alta", opStatus: "Backlog >24h", lastActor: "Paciente",
      queue: "Operación", attribution: "Derivación interna",
      assignedTo: null, assignedRole: null, isMine: false,
      patient: { name: "Luisa Cárdenas", age: 62, dx: "Catarata bilateral", lastVisit: "28 feb 2026", nextAppt: "—" } },
    { id: 9, name: "Diego Solís Burbano", wa: "+593 98 002 2240", hc: null,
      preview: "🎤 Audio · 0:32", time: "Lun", unread: 0,
      status: "open", tone: "blue", initials: "DS",
      bucket: "mine", buckets: ["mine", "informacion", "window_open"],
      window: "open", priority: "Baja", opStatus: "Información", lastActor: "Paciente",
      queue: "Información", attribution: "Orgánico",
      assignedTo: "Dra. Carolina Rivera", assignedRole: "Oftalmología", isMine: true,
      patient: { name: "Diego Solís", age: 40, dx: "Sin vincular", lastVisit: "—", nextAppt: "—" } },
    { id: 10, name: "Camila Espinoza", wa: "+593 99 220 4451", hc: "80994",
      preview: "Listo, agendo la consulta para el 28.", time: "Vie", unread: 0,
      status: null, tone: "green", initials: "CE",
      bucket: "closed", buckets: ["closed", "informacion"],
      window: "template", priority: "Baja", opStatus: "Cerrada", lastActor: "Agente",
      queue: "Información", attribution: "Orgánico",
      assignedTo: "Lcda. Paola Cordero", assignedRole: "Admisión", isMine: false,
      patient: { name: "Camila Espinoza", age: 29, dx: "Consulta general", lastVisit: "20 mar 2026", nextAppt: "28 mar 2026 · Consulta" } },
  ];

  /* ---- Tabs (primary bandejas) ---- */
  const TABS = [
    { id: "requires_attention", label: "Atención",   icon: "mdi-alert-circle-outline" },
    { id: "mine",              label: "Mías",        icon: "mdi-account-check-outline" },
    { id: "in_progress",       label: "En gestión",  icon: "mdi-account-clock-outline" },
    { id: "waiting_patient",   label: "Esperando",   icon: "mdi-account-arrow-left-outline" },
    { id: "scheduled",         label: "Agendados",   icon: "mdi-calendar-check-outline" },
    { id: "closed",            label: "Cerrados",    icon: "mdi-archive-check-outline" },
  ];

  /* ---- Advanced filters (secondary bandejas) ---- */
  const ADV_FILTERS = [
    { id: "critical_backlog", label: "Backlog >24h",       icon: "mdi-alert-octagon-outline",      hint: "Casos vencidos o sin atención oportuna." },
    { id: "captacion",        label: "Captación",          icon: "mdi-bullseye-arrow",             hint: "Pacientes nuevos o intención de agendar." },
    { id: "operacion",        label: "Operación",          icon: "mdi-calendar-sync-outline",      hint: "Citas vigentes, cambios o seguimiento." },
    { id: "informacion",      label: "Información",        icon: "mdi-information-outline",         hint: "Consultas generales sin proceso activo." },
    { id: "unread",           label: "Sin leer",           icon: "mdi-bell-outline",               hint: "Mensajes entrantes pendientes de revisión." },
    { id: "window_open",      label: "24h abierta",        icon: "mdi-timer-sand",                 hint: "Chats donde se puede responder libremente." },
    { id: "needs_template",   label: "Requiere plantilla", icon: "mdi-file-document-edit-outline", hint: "Ventana vencida; requiere plantilla aprobada." },
  ];

  /* tag tone per bucket — for the list-row pill */
  const BUCKET_TAG = {
    requires_attention: { tone: "attention", label: "Atención" },
    mine:               { tone: "mine",      label: "Mía" },
    in_progress:        { tone: "progress",  label: "Gestión" },
    waiting_patient:    { tone: "waiting",   label: "Esperando" },
    scheduled:          { tone: "scheduled", label: "Agendada" },
    closed:             { tone: "closed",    label: "Cerrada" },
  };

  /* ---- Manager metrics ---- */
  const MANAGER_METRICS = [
    { id: "critical_backlog", label: "SLA / backlog", value: 3, tone: "danger" },
    { id: "requires_attention", label: "Atención",    value: 2, tone: "danger" },
    { id: "unread", label: "Sin leer",                value: 4, tone: "accent" },
    { id: "waiting_patient", label: "Esperando",      value: 1, tone: "warning" },
    { id: "needs_template", label: "Plantilla",       value: 2, tone: "muted" },
    { id: "scheduled", label: "Agendados",            value: 1, tone: "success" },
  ];

  /* ---- Agents (supervisor view) ---- */
  const AGENTS = [
    { id: 1, name: "Dra. Carolina Rivera", role: "Oftalmología", status: "online", active: 4, resolved: 12, avgResp: "1m 24s", tone: "violet", initials: "CR", isMe: true },
    { id: 2, name: "Lcda. Paola Cordero",  role: "Admisión",     status: "online", active: 3, resolved:  8, avgResp: "2m 10s", tone: "green",  initials: "PC" },
    { id: 3, name: "Lic. Juan Mejía",      role: "Caja",         status: "busy",   active: 5, resolved:  6, avgResp: "3m 41s", tone: "amber",  initials: "JM" },
    { id: 4, name: "Dra. Andrea Salinas",  role: "Oftalmología", status: "away",   active: 0, resolved:  4, avgResp: "—",      tone: "rose",   initials: "AS" },
    { id: 5, name: "Lic. Felipe Vargas",   role: "Soporte",      status: "online", active: 2, resolved:  9, avgResp: "1m 50s", tone: "blue",   initials: "FV" },
    { id: 6, name: "Bot · Flowmaker",      role: "Automático",   status: "online", active: 11, resolved: 38, avgResp: "0m 04s", tone: "cyan",  initials: "🤖" },
  ];

  /* ---- Roles / teams (Derivar por equipo) ---- */
  const ROLES = [
    { id: 1, name: "Admisión",     open: 3, icon: "mdi-account-multiple-outline" },
    { id: 2, name: "Caja",         open: 1, icon: "mdi-credit-card-outline" },
    { id: 3, name: "Oftalmología", open: 5, icon: "mdi-eye-outline" },
    { id: 4, name: "Soporte",      open: 2, icon: "mdi-lifebuoy" },
  ];

  /* ---- Approved templates ---- */
  const TEMPLATES = [
    { id: 1, name: "Confirmación de cita", category: "utility", language: "ES", status: "approved",
      body: "Hola {{1}}, le confirmo su cita para el {{2}} a las {{3}}. Por favor responda *SI* para confirmar.",
      variables: ["nombre", "fecha", "hora"], examples: ["María", "25 mar", "09:00"] },
    { id: 2, name: "Recordatorio pre-cirugía", category: "utility", language: "ES", status: "approved",
      body: "Recordatorio: su cirugía es el {{1}}. Recuerde 6 horas de ayuno y venir con un acompañante.",
      variables: ["fecha"], examples: ["27 mar"] },
    { id: 3, name: "Bono de pago", category: "utility", language: "ES", status: "approved",
      body: "Hola {{1}}, le enviamos el bono de pago por ${{2}}. Puede cancelar en línea o en caja.",
      variables: ["nombre", "monto"], examples: ["María", "480"] },
    { id: 4, name: "Indicaciones post-op", category: "utility", language: "ES", status: "approved",
      body: "Indicaciones post-operatorias: gotas cada 6 horas, evite esfuerzos por 7 días. Cualquier duda, escríbanos.",
      variables: [], examples: [] },
    { id: 5, name: "Promoción catarata 60+", category: "marketing", language: "ES", status: "approved",
      body: "{{1}}, evalúe su catarata con nuestros especialistas. Agende su valoración sin costo esta semana.",
      variables: ["nombre"], examples: ["María"] },
  ];

  /* ---- Quick replies ---- */
  const QUICK_REPLIES = [
    { id: 1, title: "Confirmar cita",          body: "Le confirmo su cita. ¿Le parece bien el horario propuesto?" },
    { id: 2, title: "Enviar bono",             body: "Le envío el bono de pago. Puede cancelarlo en línea o en caja." },
    { id: 3, title: "Indicaciones pre-cirugía",body: "Le adjunto las indicaciones para que las revise con calma." },
    { id: 4, title: "Ubicación clínica",       body: "Estamos en Av. principal y secundaria. Le comparto la ubicación 📍" },
    { id: 5, title: "Transferir a admisión",   body: "Le transfiero con admisión para coordinar la agenda. Un momento, por favor." },
  ];

  /* ---- Emoji palette ---- */
  const EMOJIS = ["👁️","👀","🙂","😊","🙏","✅","📅","🕒","📍","🏥","👨‍⚕️","👩‍⚕️","🤓","💬","📄","🔎","⚠️","😔","👍","✨","🟢","🔴","🟡","📞"];

  /* ---- Traceability (per active convo, demo) ---- */
  const TRAIL = [
    { label: "Asignada a Dra. Carolina Rivera", actor: "Sistema", at: "Hoy · 10:18" },
    { label: "Transferida desde Admisión",       actor: "Lcda. Paola Cordero", at: "Hoy · 10:16" },
    { label: "Marcada como handoff humano",       actor: "Flowmaker · falta_cita_nodo", at: "Hoy · 10:15" },
    { label: "Bot respondió con menú principal",  actor: "Flowmaker · welcome_flow", at: "Hoy · 10:14" },
    { label: "Primer mensaje del paciente",       actor: "+593 99 845 1230", at: "Hoy · 10:14" },
    { label: "Llegó desde Meta Ads",             actor: "Campaña · Catarata 60+", at: "Hoy · 10:14" },
    { label: "Paciente vinculado a HC 80211",     actor: "Auto · matcher_v2", at: "9 mar 2026" },
  ];

  /* ---- Internal notes (per active convo, demo) ---- */
  const NOTES = [
    { author: "Lcda. Paola Cordero", at: "hace 2 h", body: "Paciente prefiere mañanas. Acompañante confirmado para el día de la cirugía." },
    { author: "Dra. Carolina Rivera", at: "hace 1 h", body: "Pendiente repetir biometría antes del pre-quirúrgico." },
  ];

  /* ---- Default thread (María Fernanda Ruiz) ---- */
  const THREAD = [
    { kind: "date", text: "Hoy" },
    { kind: "msg", dir: "in",  body: "Buenos días, doctora 👋 Quisiera saber si la cirugía de catarata se puede agendar la próxima semana.", time: "10:14" },
    { kind: "msg", dir: "in",  body: "Mi hija puede acompañarme cualquier día después del 23.", time: "10:14" },
    { kind: "event", icon: "mdi-account-arrow-right-outline", text: "Asignado a ti por Carolina Rivera · 10:18" },
    { kind: "msg", dir: "out", body: "Hola María, ¡buenos días! Claro, podemos coordinar. ¿Le parece bien el lunes 25 a las 9:00 am para la valoración pre-quirúrgica?", time: "10:20", status: "read" },
    { kind: "msg", dir: "out", body: "Antes de la cirugía necesitamos repetir _biometría_ y agudeza visual.", time: "10:21", status: "read" },
    { kind: "msg", dir: "in",  body: "Perfecto, doctora. ¿Necesito traer algo en particular?", time: "10:34", quote: { who: "Tú", text: "Antes de la cirugía necesitamos repetir biometría y agudeza visual." } },
    { kind: "msg", dir: "out", media: { type: "file", icon: "mdi-file-document-outline", name: "Indicaciones_pre_cirugia.pdf", size: "184 KB" }, body: "Le adjunto las indicaciones para que las revise con calma.", time: "10:38", status: "delivered" },
    { kind: "msg", dir: "in",  media: { type: "audio", name: "Mensaje de voz · 0:18" }, time: "10:40" },
    { kind: "msg", dir: "in",  body: "Perfecto, doctora. ¿Puedo ir mañana a las 9 am?", time: "10:42" },
  ];

  return { CONVOS, TABS, ADV_FILTERS, BUCKET_TAG, MANAGER_METRICS, AGENTS, ROLES, TEMPLATES, QUICK_REPLIES, EMOJIS, TRAIL, NOTES, THREAD };
})();
