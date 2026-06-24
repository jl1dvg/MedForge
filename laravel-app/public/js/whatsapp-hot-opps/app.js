"use strict";
var __defProp = Object.defineProperty;
var __defProps = Object.defineProperties;
var __getOwnPropDescs = Object.getOwnPropertyDescriptors;
var __getOwnPropSymbols = Object.getOwnPropertySymbols;
var __hasOwnProp = Object.prototype.hasOwnProperty;
var __propIsEnum = Object.prototype.propertyIsEnumerable;
var __defNormalProp = (obj, key, value) => key in obj ? __defProp(obj, key, { enumerable: true, configurable: true, writable: true, value }) : obj[key] = value;
var __spreadValues = (a, b) => {
  for (var prop in b || (b = {}))
    if (__hasOwnProp.call(b, prop))
      __defNormalProp(a, prop, b[prop]);
  if (__getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(b)) {
      if (__propIsEnum.call(b, prop))
        __defNormalProp(a, prop, b[prop]);
    }
  return a;
};
var __spreadProps = (a, b) => __defProps(a, __getOwnPropDescs(b));
var __objRest = (source, exclude) => {
  var target = {};
  for (var prop in source)
    if (__hasOwnProp.call(source, prop) && exclude.indexOf(prop) < 0)
      target[prop] = source[prop];
  if (source != null && __getOwnPropSymbols)
    for (var prop of __getOwnPropSymbols(source)) {
      if (exclude.indexOf(prop) < 0 && __propIsEnum.call(source, prop))
        target[prop] = source[prop];
    }
  return target;
};
const { useState, useEffect, useRef, useCallback } = React;
const CFG = window.HOT_OPPS_CONFIG || { apiUrl: "/v2/whatsapp/api/hot-opportunities", chatUrl: "/v2/whatsapp/chat", pollIntervalMs: 3e4 };
function mapConversation(c) {
  const src = (c.attribution_source_category || "").toLowerCase();
  const sourceLabel = src === "paid" ? "Ads" : src === "organic" ? "Org\xE1nico" : src === "return" ? "Retorno" : src === "campaign" ? "Campa\xF1a" : c.attribution_source_category || "Org\xE1nico";
  const intentRaw = (c.attribution_initial_intent || "").toLowerCase();
  const intentLabel = intentRaw.includes("agenda") || intentRaw.includes("agendar") ? "agendar" : intentRaw.includes("reagend") ? "reagendar" : intentRaw.includes("cancel") ? "cancelar" : "informaci\xF3n";
  const topic = c.handoff_topic || "captacion_agendar";
  const waitMin = typeof c.queue_age_minutes === "number" ? c.queue_age_minutes : 0;
  let metaState = "open";
  let metaMinLeft = null;
  const mw = c.messaging_window_state || "";
  if (mw === "needs_template") {
    metaState = "warn";
    metaMinLeft = 120;
  }
  if (mw === "closed") {
    metaState = "critical";
    metaMinLeft = 0;
  }
  const score = c.priority_score || 0;
  const reasons = buildReasons(c, waitMin);
  return {
    id: c.id,
    name: c.display_name || c.patient_full_name || c.wa_number || `Conv #${c.id}`,
    hc: c.patient_hc_number || null,
    source: sourceLabel,
    intent: intentLabel,
    topic,
    waitMin,
    agentId: c.assigned_user_id || null,
    metaState,
    metaMinLeft,
    score,
    requeued: 0,
    reasons,
    _raw: c
  };
}
function buildReasons(c, waitMin) {
  const reasons = [];
  const assigned = !!c.assigned_user_id;
  const mw = c.messaging_window_state || "";
  if (!assigned && waitMin > 20) reasons.push(["alarm", `Sin asignar hace ${waitMin} min`, "crit"]);
  else if (!assigned && waitMin > 0) reasons.push(["alarm", `Sin asignar hace ${waitMin} min`, "risk"]);
  if (!assigned) reasons.push(["account-off", "Sin asignar", assigned ? "info" : "crit"]);
  if (mw === "needs_template") reasons.push(["timer-sand", "Ventana Meta por cerrar", "risk"]);
  if (mw === "closed") reasons.push(["timer-alert-outline", "Ventana Meta cerrada", "crit"]);
  if (c.attribution_initial_intent && (c.attribution_initial_intent.includes("agenda") || c.attribution_initial_intent.includes("agendar"))) {
    reasons.push(["calendar-check", "Intenci\xF3n de agendar", "info"]);
  }
  if (c.patient_hc_number) reasons.push(["card-account-details", "Paciente identificado \xB7 HC", "info"]);
  if ((c.priority_reasons || []).length > 0 && reasons.length === 0) {
    c.priority_reasons.forEach((r) => reasons.push(["information-outline", r, "info"]));
  }
  return reasons.slice(0, 4);
}
function mapReminder(r) {
  var _a;
  return {
    id: String(r.id),
    conversationId: r.conversation_id,
    name: r.patient_name || `HC ${r.hc_number}`,
    hc: r.hc_number || null,
    apptDate: r.appointment_at ? fmtApptDate(r.appointment_at) : "\u2014",
    apptMinutes: (_a = r.appointment_minutes_from_now) != null ? _a : 9999,
    apptDoctor: r.doctor_name || "",
    apptSede: r.sede || "",
    failureReason: r.failure_reason || "unknown",
    failedAt: r.failed_at ? r.failed_at.slice(11, 16) : "\u2014",
    retries: r.retry_count || 0,
    windowState: r.window_state || "open"
  };
}
function fmtApptDate(isoStr) {
  try {
    const d = new Date(isoStr);
    const today = /* @__PURE__ */ new Date();
    const tomorrow = new Date(today);
    tomorrow.setDate(today.getDate() + 1);
    const sameDay = (d2) => d2.getDate() === today.getDate() && d2.getMonth() === today.getMonth();
    const sameYear = (d2) => d2.getFullYear() === today.getFullYear();
    const prefix = sameDay(d) ? "Hoy" : d.getDate() === tomorrow.getDate() && d.getMonth() === tomorrow.getMonth() ? "Ma\xF1ana" : d.toLocaleDateString("es-EC", { day: "2-digit", month: "short" });
    const time = d.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit", hour12: false });
    return `${prefix} ${time}`;
  } catch (e) {
    return isoStr;
  }
}
function mapAgent(a) {
  const STATUS_MAP = { available: "available", busy: "busy", away: "away", offline: "away" };
  return {
    id: a.id,
    name: a.name,
    initials: a.initials || makeInitials(a.name),
    color: a.color || "#5156be",
    status: STATUS_MAP[a.presence_status] || "available",
    convs: a.assigned_open_count || 0,
    unread: a.unread_open_count || 0,
    resp: "\u2014"
  };
}
function makeInitials(name) {
  const parts = (name || "").trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return (name || "??").slice(0, 2).toUpperCase();
}
function priorityOf(c) {
  if (!c.agentId && c.waitMin > 20) return "crit";
  if (c.metaState === "critical") return "crit";
  if (c.requeued >= 2) return "crit";
  if (!c.agentId && c.waitMin >= 5) return "risk";
  if (c.metaState === "warn") return "risk";
  return "norm";
}
const loadPct = (n, max = 15) => Math.min(100, Math.round(n / max * 100));
const loadClass = (p) => p >= 75 ? "load-high" : p >= 45 ? "load-mid" : "load-ok";
function scoreColor(s) {
  return s >= 170 ? "var(--danger)" : s >= 90 ? "var(--warning)" : "var(--success)";
}
function fmtMeta(c) {
  if (c.metaMinLeft == null) return null;
  const h = Math.floor(c.metaMinLeft / 60), m = c.metaMinLeft % 60;
  return h > 0 ? `${h}h ${m}min` : `${m} min`;
}
const isRemSoon = (r) => typeof r.apptMinutes === "number" && r.apptMinutes <= 120 && r.apptMinutes >= 0;
function fmtApptIn(min) {
  if (min <= 0) return ["pasada", true];
  if (min <= 60) return [`${min}min`, true];
  const h = Math.floor(min / 60), m = min % 60;
  return [`${h}h${m ? ` ${m}min` : ""}`, false];
}
const TOPIC = {
  captacion_agendar: ["captaci\xF3n", "tp-captacion"],
  agenda_sin_disponibilidad: ["agenda \xB7 sin disp.", "tp-agenda"],
  faq_escalada: ["faq \xB7 escalada", "tp-faq"],
  operacion_reagenda: ["operaci\xF3n \xB7 reagenda", "tp-operacion"]
};
const SOURCE = {
  "Ads": ["src-ads", "bullhorn-variant"],
  "Org\xE1nico": ["src-organico", "leaf"],
  "Retorno": ["src-retorno", "backup-restore"],
  "Campa\xF1a": ["src-campana", "bullhorn"]
};
const INTENT = {
  "agendar": ["int-agendar", "calendar-plus"],
  "reagendar": ["int-reagendar", "calendar-sync"],
  "cancelar": ["int-cancelar", "calendar-remove"],
  "informaci\xF3n": ["int-info", "information-outline"]
};
const FAILURE = {
  location_header_missing_coordinates: ["Coord. faltantes", "fl-location", "map-marker-off-outline", "El template espera coordenadas de ubicaci\xF3n pero no se enviaron. Requiere corregir el payload del recordatorio."],
  template_header_location_mismatch: ["Header incorrecto", "fl-template", "file-document-alert-outline", "El header del template est\xE1 configurado como LOCATION pero se envi\xF3 un tipo distinto (posiblemente UNKNOWN). PR #401 corregido."],
  whatsapp_messages_table_missing: ["Tabla ausente", "fl-infra", "database-alert-outline", "El servicio de recordatorios no encontr\xF3 la tabla whatsapp_messages al deduplicar. Error de infraestructura."]
};
const failInfo = (r) => FAILURE[r] || [r, "fl-unknown", "help-circle-outline", r];
const Icon = (_a) => {
  var _b = _a, { n } = _b, p = __objRest(_b, ["n"]);
  return /* @__PURE__ */ React.createElement("i", __spreadValues({ className: `mdi mdi-${n}` }, p));
};
function AgentDropdown({ agents, onSelect, onClose }) {
  const ref = useRef();
  useEffect(() => {
    const h = (e) => {
      if (ref.current && !ref.current.contains(e.target)) onClose();
    };
    document.addEventListener("mousedown", h);
    return () => document.removeEventListener("mousedown", h);
  }, []);
  const sorted = [...agents].sort((a, b) => a.convs - b.convs);
  return /* @__PURE__ */ React.createElement("div", { className: "ho-dd", ref }, /* @__PURE__ */ React.createElement("div", { className: "ho-dd-lbl" }, /* @__PURE__ */ React.createElement("span", null, "Asignar a agente"), /* @__PURE__ */ React.createElement("span", null, "menor carga primero")), sorted.map((a, i) => {
    const p = loadPct(a.convs);
    return /* @__PURE__ */ React.createElement("div", { key: a.id, className: "ho-dd-item", onClick: () => onSelect(a) }, /* @__PURE__ */ React.createElement("div", { className: `ho-av st-${a.status}`, style: { background: a.color, width: 30, height: 30 } }, a.initials), /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("div", { className: "ho-dd-name" }, a.name, " ", i === 0 && /* @__PURE__ */ React.createElement("span", { className: "ho-dd-best" }, "\xF3ptimo")), /* @__PURE__ */ React.createElement("div", { className: "ho-dd-meta" }, a.convs, " conv activas")), /* @__PURE__ */ React.createElement("div", { className: "ho-dd-load" }, /* @__PURE__ */ React.createElement("div", { className: "ho-dd-loadbar" }, /* @__PURE__ */ React.createElement("div", { className: `ho-dd-loadfill ${loadClass(p)}`, style: { width: `${p}%` } })), /* @__PURE__ */ React.createElement("span", { className: "ho-dd-loadnum" }, a.convs)));
  }));
}
function OppCard({ conv, agents, prio, selected, onSelect, onAssign, onOpen }) {
  const [drop, setDrop] = useState(false);
  const agent = agents.find((a) => a.id === conv.agentId);
  const waitCls = prio === "crit" ? "w-crit" : prio === "risk" ? "w-risk" : "w-ok";
  const [srcCls, srcIc] = SOURCE[conv.source] || ["int-info", "help"];
  const [intCls, intIc] = INTENT[conv.intent] || ["int-info", "help"];
  const [topLbl, topCls] = TOPIC[conv.topic] || [conv.topic, "tp-faq"];
  const metaFmt = fmtMeta(conv);
  return /* @__PURE__ */ React.createElement("div", { className: `ho-card ${prio} ${selected ? "sel" : ""}`, onClick: () => onSelect(conv.id) }, /* @__PURE__ */ React.createElement("div", { className: "ho-card-body" }, /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("div", { className: "ho-pt-name" }, conv.name), conv.hc ? /* @__PURE__ */ React.createElement("span", { className: "ho-pt-hc" }, /* @__PURE__ */ React.createElement(Icon, { n: "card-account-details-outline" }), "HC ", conv.hc) : /* @__PURE__ */ React.createElement("span", { className: "ho-pt-nohc" }, /* @__PURE__ */ React.createElement(Icon, { n: "card-account-details-outline" }), "Sin HC"), /* @__PURE__ */ React.createElement("div", { className: "ho-score", title: `Prioridad ${conv.score}/450` }, /* @__PURE__ */ React.createElement("div", { className: "ho-score-track" }, /* @__PURE__ */ React.createElement("div", { className: "ho-score-fill", style: { width: `${Math.min(100, Math.round(conv.score / 4.5))}%`, background: scoreColor(conv.score) } })), /* @__PURE__ */ React.createElement("span", { className: "ho-score-lbl", style: { color: scoreColor(conv.score) } }, conv.score))), /* @__PURE__ */ React.createElement("div", { className: "ho-badges" }, /* @__PURE__ */ React.createElement("span", { className: `ho-badge ${srcCls}` }, /* @__PURE__ */ React.createElement(Icon, { n: srcIc }), conv.source), /* @__PURE__ */ React.createElement("span", { className: `ho-badge ${intCls}` }, /* @__PURE__ */ React.createElement(Icon, { n: intIc }), conv.intent)), /* @__PURE__ */ React.createElement("div", { className: "ho-wait" }, /* @__PURE__ */ React.createElement("span", { className: `ho-wait-badge ${waitCls}` }, conv.waitMin, /* @__PURE__ */ React.createElement("small", null, "min")), /* @__PURE__ */ React.createElement("div", { className: "ho-wait-sub" }, "en cola")), /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("span", { className: `ho-topic ${topCls}` }, topLbl), conv.requeued >= 1 && /* @__PURE__ */ React.createElement("div", { className: "ho-requeue" }, /* @__PURE__ */ React.createElement(Icon, { n: "backup-restore" }), "reencolado ", conv.requeued, "\xD7")), /* @__PURE__ */ React.createElement("div", { className: "ho-meta" }, conv.metaState === "open" && /* @__PURE__ */ React.createElement("span", { className: "ho-meta-open" }, /* @__PURE__ */ React.createElement(Icon, { n: "check-circle" }), "Abierta"), conv.metaState === "warn" && /* @__PURE__ */ React.createElement("span", { className: "ho-meta-warn m-warn" }, /* @__PURE__ */ React.createElement(Icon, { n: "timer-sand" }), metaFmt), conv.metaState === "critical" && /* @__PURE__ */ React.createElement("span", { className: "ho-meta-warn m-crit" }, /* @__PURE__ */ React.createElement(Icon, { n: "timer-alert-outline" }), metaFmt || "Cerrada"), /* @__PURE__ */ React.createElement("div", { className: "ho-meta-lbl" }, "ventana Meta")), /* @__PURE__ */ React.createElement("div", { className: "ho-agent" }, agent ? /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: `ho-av st-${agent.status}`, style: { background: agent.color } }, agent.initials), /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("div", { className: "ho-agent-name" }, agent.name), /* @__PURE__ */ React.createElement("div", { className: "ho-agent-meta" }, agent.convs, " conv asignadas"))) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "ho-av-empty" }, /* @__PURE__ */ React.createElement(Icon, { n: "account-plus-outline" })), /* @__PURE__ */ React.createElement("span", { className: "ho-agent-none" }, "Sin asignar"))), /* @__PURE__ */ React.createElement("div", { className: "ho-actions", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement("div", { className: "ho-dd-wrap" }, /* @__PURE__ */ React.createElement("button", { className: agent ? "ho-btn ho-btn-sec" : "ho-btn ho-btn-pri", onClick: () => setDrop((v) => !v) }, /* @__PURE__ */ React.createElement(Icon, { n: agent ? "account-switch" : "account-arrow-right" }), agent ? "Reasignar" : "Asignar"), drop && /* @__PURE__ */ React.createElement(AgentDropdown, { agents, onSelect: (a) => {
    setDrop(false);
    onAssign(conv.id, a);
  }, onClose: () => setDrop(false) })), /* @__PURE__ */ React.createElement("button", { className: "ho-btn ho-btn-ic", title: "Abrir chat", onClick: () => onOpen(conv) }, /* @__PURE__ */ React.createElement(Icon, { n: "message-text-outline" })))), /* @__PURE__ */ React.createElement("div", { className: "ho-reasons" }, /* @__PURE__ */ React.createElement("span", { className: "ho-reasons-lbl" }, /* @__PURE__ */ React.createElement(Icon, { n: "information-outline" }), "Por qu\xE9"), conv.reasons.map(([ic, txt, sev], i) => /* @__PURE__ */ React.createElement("span", { key: i, className: `ho-reason rs-${sev}` }, /* @__PURE__ */ React.createElement(Icon, { n: ic }), txt))));
}
function RemCard({ rem, selected, onSelect, onOpen, onRecontact }) {
  const [desc, setDesc] = useState(false);
  const soon = isRemSoon(rem);
  const [failLbl, failCls, failIc, failDesc] = failInfo(rem.failureReason);
  const [inLbl, inSoon] = fmtApptIn(rem.apptMinutes);
  return /* @__PURE__ */ React.createElement("div", { className: `ho-rcard ${soon ? "soon" : ""} ${selected === rem.id ? "sel" : ""}`, onClick: () => onSelect(rem.id) }, /* @__PURE__ */ React.createElement("div", { className: "ho-rcard-body" }, /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("div", { className: "ho-pt-name" }, rem.name), rem.hc ? /* @__PURE__ */ React.createElement("span", { className: "ho-pt-hc" }, /* @__PURE__ */ React.createElement(Icon, { n: "card-account-details-outline" }), "HC ", rem.hc) : /* @__PURE__ */ React.createElement("span", { className: "ho-pt-nohc" }, /* @__PURE__ */ React.createElement(Icon, { n: "card-account-details-outline" }), "Sin HC"), soon && /* @__PURE__ */ React.createElement("div", { className: "ho-soon-flag" }, /* @__PURE__ */ React.createElement(Icon, { n: "alert-circle" }), "Cita muy pronto")), /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("span", { className: `ho-fail ${failCls}` }, /* @__PURE__ */ React.createElement(Icon, { n: failIc }), failLbl), /* @__PURE__ */ React.createElement("div", { className: "ho-fail-toggle", onClick: (e) => {
    e.stopPropagation();
    setDesc((v) => !v);
  } }, /* @__PURE__ */ React.createElement(Icon, { n: desc ? "chevron-up" : "chevron-down" }), desc ? "Ocultar detalle" : "Ver detalle"), desc && /* @__PURE__ */ React.createElement("div", { className: "ho-fail-desc" }, failDesc), rem.retries > 0 && /* @__PURE__ */ React.createElement("div", { className: "ho-fail-retry" }, /* @__PURE__ */ React.createElement(Icon, { n: "restart" }), "Reintentado ", rem.retries, "\xD7")), /* @__PURE__ */ React.createElement("div", { style: { minWidth: 0 } }, /* @__PURE__ */ React.createElement("span", { className: `ho-appt-date ${soon ? "soon" : ""}` }, /* @__PURE__ */ React.createElement(Icon, { n: "calendar-clock" }), rem.apptDate), /* @__PURE__ */ React.createElement("div", { className: "ho-appt-in" }, "en ", /* @__PURE__ */ React.createElement("b", { className: inSoon ? "soon" : "" }, inLbl)), (rem.apptDoctor || rem.apptSede) && /* @__PURE__ */ React.createElement("div", { className: "ho-appt-where" }, [rem.apptDoctor, rem.apptSede].filter(Boolean).join(" \xB7 "))), /* @__PURE__ */ React.createElement("div", null, rem.windowState === "open" && /* @__PURE__ */ React.createElement("span", { className: "ho-win w-open" }, /* @__PURE__ */ React.createElement(Icon, { n: "check-circle" }), "Abierta"), rem.windowState === "needs_template" && /* @__PURE__ */ React.createElement("span", { className: "ho-win w-tmpl" }, /* @__PURE__ */ React.createElement(Icon, { n: "file-document-outline" }), "Requiere template"), rem.windowState === "closed" && /* @__PURE__ */ React.createElement("span", { className: "ho-win w-closed" }, /* @__PURE__ */ React.createElement(Icon, { n: "close-circle" }), "Cerrada"), /* @__PURE__ */ React.createElement("div", { className: "ho-win-lbl" }, "ventana WhatsApp")), /* @__PURE__ */ React.createElement("div", { className: "ho-actions", onClick: (e) => e.stopPropagation() }, /* @__PURE__ */ React.createElement("button", { className: "ho-btn ho-btn-rem", onClick: () => onRecontact(rem) }, /* @__PURE__ */ React.createElement(Icon, { n: "phone-outline" }), "Recontactar"), rem.conversationId && /* @__PURE__ */ React.createElement("button", { className: "ho-btn ho-btn-ic", title: "Abrir chat", onClick: () => onOpen({ name: rem.name, id: rem.conversationId }) }, /* @__PURE__ */ React.createElement(Icon, { n: "message-text-outline" })))));
}
function FailureSummary({ reminders, active, onFilter }) {
  const counts = {};
  reminders.forEach((r) => {
    counts[r.failureReason] = (counts[r.failureReason] || 0) + 1;
  });
  const items = Object.entries(counts).sort((a, b) => b[1] - a[1]);
  const soon = reminders.filter(isRemSoon).length;
  return /* @__PURE__ */ React.createElement("div", { className: "ho-failsum" }, /* @__PURE__ */ React.createElement("span", { className: "ho-failsum-title" }, /* @__PURE__ */ React.createElement(Icon, { n: "chart-donut" }), "Fallos por causa"), /* @__PURE__ */ React.createElement("div", { className: "ho-failsum-items" }, items.map(([reason, count]) => {
    const [lbl, cls, ic] = failInfo(reason);
    return /* @__PURE__ */ React.createElement(
      "span",
      {
        key: reason,
        className: `ho-fail ${cls} ho-failsum-chip ${active === reason ? "active" : ""}`,
        onClick: () => onFilter(active === reason ? null : reason)
      },
      /* @__PURE__ */ React.createElement(Icon, { n: ic }),
      /* @__PURE__ */ React.createElement("b", null, count),
      " ",
      lbl
    );
  }), /* @__PURE__ */ React.createElement("span", { className: "ho-failsum-total" }, "Total ", /* @__PURE__ */ React.createElement("b", { style: { color: "var(--fg-1)" } }, reminders.length), " fallidos \xB7 ", /* @__PURE__ */ React.createElement("b", null, soon), " con cita < 2h")));
}
const SEC_META = {
  crit: ["sev-crit", "fire", "CR\xCDTICAS", "acci\xF3n inmediata"],
  risk: ["sev-risk", "alert-outline", "EN RIESGO", "responder pronto"],
  norm: ["sev-norm", "check-circle-outline", "BAJO CONTROL", "asignadas y activas"]
};
function Section(_c) {
  var _d = _c, { prio, convs } = _d, rest = __objRest(_d, ["prio", "convs"]);
  if (!convs.length) return null;
  const [cls, ic, lbl, sub] = SEC_META[prio];
  return /* @__PURE__ */ React.createElement("div", { className: cls }, /* @__PURE__ */ React.createElement("div", { className: "ho-sec-hdr" }, /* @__PURE__ */ React.createElement("span", { className: "ho-sec-icon" }, /* @__PURE__ */ React.createElement(Icon, { n: ic })), /* @__PURE__ */ React.createElement("h3", null, lbl, " ", /* @__PURE__ */ React.createElement("span", { className: "ho-sec-sub" }, "\xB7 ", sub)), /* @__PURE__ */ React.createElement("span", { className: "ho-sec-count" }, convs.length), /* @__PURE__ */ React.createElement("span", { className: "ho-sec-line" })), /* @__PURE__ */ React.createElement("div", { className: "ho-cards" }, convs.map((c) => /* @__PURE__ */ React.createElement(OppCard, __spreadValues({ key: c.id, conv: c, prio }, rest)))));
}
function SecHeader({ ic, label, sub, count }) {
  return /* @__PURE__ */ React.createElement("div", { className: "ho-sec-hdr" }, /* @__PURE__ */ React.createElement("span", { className: "ho-sec-icon" }, /* @__PURE__ */ React.createElement(Icon, { n: ic })), /* @__PURE__ */ React.createElement("h3", null, label, " ", /* @__PURE__ */ React.createElement("span", { className: "ho-sec-sub" }, "\xB7 ", sub)), /* @__PURE__ */ React.createElement("span", { className: "ho-sec-count" }, count), /* @__PURE__ */ React.createElement("span", { className: "ho-sec-line" }));
}
function AgentPanel({ agents }) {
  const totalConv = agents.reduce((s, a) => s + a.convs, 0);
  const totalUnread = agents.reduce((s, a) => s + a.unread, 0);
  const avail = agents.filter((a) => a.status === "available").length;
  const STATUS_LBL = { available: "Disponible", busy: "Ocupado", away: "Ausente" };
  return /* @__PURE__ */ React.createElement("aside", { className: "ho-ap" }, /* @__PURE__ */ React.createElement("div", { className: "ho-ap-hd" }, /* @__PURE__ */ React.createElement("span", { className: "ho-ap-hd-eye" }, /* @__PURE__ */ React.createElement(Icon, { n: "account-group-outline" }), "Equipo en turno"), /* @__PURE__ */ React.createElement("h4", null, agents.length, " agentes ", /* @__PURE__ */ React.createElement("small", null, "\xB7 ", avail, " disponibles"))), /* @__PURE__ */ React.createElement("div", { className: "ho-ap-list" }, [...agents].sort((a, b) => a.convs - b.convs).map((a) => {
    const p = loadPct(a.convs);
    return /* @__PURE__ */ React.createElement("div", { className: "ho-ac", key: a.id }, /* @__PURE__ */ React.createElement("div", { className: `ho-ac-av st-${a.status}`, style: { background: a.color } }, a.initials), /* @__PURE__ */ React.createElement("div", { className: "ho-ac-info" }, /* @__PURE__ */ React.createElement("div", { className: "ho-ac-name" }, a.name), /* @__PURE__ */ React.createElement("span", { className: `ho-ac-status s-${a.status}` }, STATUS_LBL[a.status]), /* @__PURE__ */ React.createElement("div", { className: "ho-ac-loadbar" }, /* @__PURE__ */ React.createElement("div", { className: `ho-ac-loadfill ${loadClass(p)}`, style: { width: `${p}%` } }))), /* @__PURE__ */ React.createElement("div", { className: "ho-ac-stats" }, /* @__PURE__ */ React.createElement("div", { className: "ho-ac-conv" }, a.convs, /* @__PURE__ */ React.createElement("small", null, " conv")), a.unread > 0 && /* @__PURE__ */ React.createElement("div", { className: "ho-ac-unread" }, a.unread, " sin leer")));
  })), /* @__PURE__ */ React.createElement("div", { className: "ho-ap-foot" }, /* @__PURE__ */ React.createElement("div", { className: "ho-ap-foot-row" }, /* @__PURE__ */ React.createElement("span", null, "Conversaciones asignadas"), /* @__PURE__ */ React.createElement("b", null, totalConv)), /* @__PURE__ */ React.createElement("div", { className: "ho-ap-foot-row" }, /* @__PURE__ */ React.createElement("span", null, "Sin leer en el equipo"), /* @__PURE__ */ React.createElement("b", null, totalUnread))));
}
function Toasts({ toasts }) {
  return /* @__PURE__ */ React.createElement("div", { className: "ho-toasts" }, toasts.map((t) => /* @__PURE__ */ React.createElement("div", { key: t.id, className: `ho-toast ${t.out ? "out" : ""}` }, /* @__PURE__ */ React.createElement("span", { className: `ho-toast-ic t-${t.kind}` }, /* @__PURE__ */ React.createElement(Icon, { n: t.icon })), /* @__PURE__ */ React.createElement("span", null, t.msg))));
}
function FSelect({ value, onChange, allLabel, options }) {
  return /* @__PURE__ */ React.createElement("select", { className: `ho-select ${value !== "all" ? "active" : ""}`, value, onChange: (e) => onChange(e.target.value) }, /* @__PURE__ */ React.createElement("option", { value: "all" }, allLabel), options.map(([v, l]) => /* @__PURE__ */ React.createElement("option", { key: v, value: v }, l)));
}
function LoadingState() {
  return /* @__PURE__ */ React.createElement("div", { className: "ho-empty", style: { margin: "auto" } }, /* @__PURE__ */ React.createElement("div", { className: "ho-empty-ic", style: { background: "var(--primary-fade)", color: "var(--primary)" } }, /* @__PURE__ */ React.createElement(Icon, { n: "loading", style: { animation: "ho-spin .75s linear infinite" } })), /* @__PURE__ */ React.createElement("h4", null, "Cargando bandeja operacional"), /* @__PURE__ */ React.createElement("p", null, "Conectando con el servidor\u2026"));
}
function ErrorState({ msg, onRetry }) {
  return /* @__PURE__ */ React.createElement("div", { className: "ho-empty", style: { margin: "auto" } }, /* @__PURE__ */ React.createElement("div", { className: "ho-empty-ic", style: { background: "#fde2e7", color: "var(--danger)" } }, /* @__PURE__ */ React.createElement(Icon, { n: "wifi-off" })), /* @__PURE__ */ React.createElement("h4", null, "Error al cargar los datos"), /* @__PURE__ */ React.createElement("p", null, msg), /* @__PURE__ */ React.createElement("button", { className: "ho-btn ho-btn-pri", style: { margin: "14px auto 0", display: "inline-flex" }, onClick: onRetry }, /* @__PURE__ */ React.createElement(Icon, { n: "refresh" }), "Reintentar"));
}
function App() {
  const [convs, setConvs] = useState([]);
  const [agents, setAgents] = useState([]);
  const [reminders, setReminders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [tab, setTab] = useState("oportunidades");
  const [failFilter, setFailFilter] = useState(null);
  const [selected, setSelected] = useState(null);
  const [spin, setSpin] = useState(false);
  const [toasts, setToasts] = useState([]);
  const [ts, setTs] = useState(null);
  const [now, setNow] = useState(() => Date.now());
  const [fPrio, setFPrio] = useState("all");
  const [fTopic, setFTopic] = useState("all");
  const [fSource, setFSource] = useState("all");
  const [fIntent, setFIntent] = useState("all");
  const [fAgent, setFAgent] = useState("all");
  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 1e3);
    return () => clearInterval(t);
  }, []);
  const addToast = useCallback((msg, icon = "check-circle", kind = "ok") => {
    const id = Date.now() + Math.random();
    setToasts((p) => [...p, { id, msg, icon, kind }]);
    setTimeout(() => {
      setToasts((p) => p.map((t) => t.id === id ? __spreadProps(__spreadValues({}, t), { out: true }) : t));
      setTimeout(() => setToasts((p) => p.filter((t) => t.id !== id)), 240);
    }, 2800);
  }, []);
  const fetchData = useCallback(async (showSpin = false) => {
    if (showSpin) setSpin(true);
    try {
      const resp = await fetch(CFG.apiUrl, { headers: { "Accept": "application/json", "X-Requested-With": "XMLHttpRequest" } });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const json = await resp.json();
      if (!json.ok) throw new Error(json.error || "Error del servidor");
      const data = json.data || {};
      setConvs((data.conversations || []).map(mapConversation));
      setAgents((data.agents || []).map(mapAgent));
      setReminders((data.reminders || []).map(mapReminder));
      setTs(/* @__PURE__ */ new Date());
      setError(null);
      setLoading(false);
    } catch (e) {
      setError(e.message);
      setLoading(false);
    } finally {
      setSpin(false);
    }
  }, []);
  useEffect(() => {
    const handleWsEvent = (e) => {
      const ev = e.detail || {};
      if (["handoff.requeued", "handoff.escalated", "handoff.auto_assigned"].includes(ev.event)) {
        fetchData(false);
        if (ev.event === "handoff.auto_assigned" && ev.assigned_to) {
          addToast(`Auto-asignado a ${ev.assigned_to.name}`, "account-arrow-right", "user");
        }
      }
    };
    window.addEventListener("whatsapp:handoff", handleWsEvent);
    return () => window.removeEventListener("whatsapp:handoff", handleWsEvent);
  }, [fetchData, addToast]);
  useEffect(() => {
    fetchData(false);
  }, [fetchData]);
  useEffect(() => {
    const interval = setInterval(() => fetchData(false), CFG.pollIntervalMs);
    return () => clearInterval(interval);
  }, [fetchData]);
  const refresh = useCallback(() => {
    fetchData(true);
    addToast("Actualizando bandeja\u2026", "refresh", "info");
  }, [fetchData, addToast]);
  function assign(convId, agent) {
    const conv = convs.find((c) => c.id === convId);
    const prev = conv && conv.agentId;
    setConvs((p) => p.map((c) => c.id === convId ? __spreadProps(__spreadValues({}, c), { agentId: agent.id }) : c));
    setAgents((p) => p.map((a) => {
      if (a.id === agent.id) return __spreadProps(__spreadValues({}, a), { convs: a.convs + 1 });
      if (a.id === prev) return __spreadProps(__spreadValues({}, a), { convs: Math.max(0, a.convs - 1) });
      return a;
    }));
    addToast(`${(conv == null ? void 0 : conv.name) || "Conv"} \u2192 ${agent.name}`, "account-arrow-right", "user");
  }
  const openChat = (conv) => {
    const url = conv.id ? `${CFG.chatUrl}?conversation_id=${conv.id}` : CFG.chatUrl;
    window.location.href = url;
  };
  const recontact = (rem) => {
    if (rem.conversationId) {
      window.location.href = `${CFG.chatUrl}?conversation_id=${rem.conversationId}`;
    } else {
      addToast(`Recontacto manual iniciado para ${rem.name}`, "phone-outline", "user");
    }
  };
  const filtered = convs.filter((c) => {
    if (fTopic !== "all" && c.topic !== fTopic) return false;
    if (fSource !== "all" && c.source !== fSource) return false;
    if (fIntent !== "all" && c.intent !== fIntent) return false;
    if (fPrio !== "all" && priorityOf(c) !== fPrio) return false;
    if (fAgent !== "all") {
      if (fAgent === "none" && c.agentId !== null) return false;
      if (fAgent !== "none" && String(c.agentId) !== fAgent) return false;
    }
    return true;
  });
  const byScore = (a, b) => b.score - a.score;
  const crit = filtered.filter((c) => priorityOf(c) === "crit").sort(byScore);
  const risk = filtered.filter((c) => priorityOf(c) === "risk").sort(byScore);
  const norm = filtered.filter((c) => priorityOf(c) === "norm").sort(byScore);
  const unassigned = convs.filter((c) => !c.agentId).length;
  const filteredRem = failFilter ? reminders.filter((r) => r.failureReason === failFilter) : reminders;
  const byAppt = (a, b) => a.apptMinutes - b.apptMinutes;
  const soonRem = filteredRem.filter(isRemSoon).sort(byAppt);
  const laterRem = filteredRem.filter((r) => !isRemSoon(r)).sort(byAppt);
  const urgentRemCount = reminders.filter(isRemSoon).length;
  const hasFilters = [fPrio, fTopic, fSource, fIntent, fAgent].some((f) => f !== "all");
  const clearFilters = () => {
    setFPrio("all");
    setFTopic("all");
    setFSource("all");
    setFIntent("all");
    setFAgent("all");
  };
  const secsAgo = ts ? Math.floor((now - ts.getTime()) / 1e3) : 0;
  const fmtTs = ts ? ts.toLocaleTimeString("es-EC", { hour: "2-digit", minute: "2-digit" }) : "\u2014";
  const agoLabel = !ts ? "\u2026" : secsAgo < 5 ? "reci\xE9n" : secsAgo < 60 ? `hace ${secsAgo}s` : `hace ${Math.floor(secsAgo / 60)} min`;
  const sectionProps = { agents, selected, onSelect: setSelected, onAssign: assign, onOpen: openChat };
  return /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("header", { className: "ho-hd" }, /* @__PURE__ */ React.createElement("div", { className: "ho-brand" }, /* @__PURE__ */ React.createElement("div", { className: "ho-brand-mark" }, /* @__PURE__ */ React.createElement(Icon, { n: "lightning-bolt" })), /* @__PURE__ */ React.createElement("div", { className: "ho-brand-text" }, /* @__PURE__ */ React.createElement("div", { className: "ho-brand-word" }, "MedForge"), /* @__PURE__ */ React.createElement("div", { className: "ho-brand-sub" }, "by Consulmed"))), /* @__PURE__ */ React.createElement("div", { className: "ho-hd-divider" }), /* @__PURE__ */ React.createElement("div", { className: "ho-hd-title" }, /* @__PURE__ */ React.createElement("h1", null, "Bandeja operacional"), /* @__PURE__ */ React.createElement("span", { className: "ho-hd-crumb" }, /* @__PURE__ */ React.createElement(Icon, { n: "whatsapp" }), "WhatsApp \xB7 Supervisi\xF3n CIVE")), /* @__PURE__ */ React.createElement("div", { className: "ho-live" }, /* @__PURE__ */ React.createElement("span", { className: "ho-pulse" }), "En vivo"), !loading && !error && /* @__PURE__ */ React.createElement("div", { className: "ho-hd-pills" }, /* @__PURE__ */ React.createElement("span", { className: "ho-hd-pill crit" }, /* @__PURE__ */ React.createElement(Icon, { n: "fire" }), /* @__PURE__ */ React.createElement("b", null, crit.length), " cr\xEDticas"), /* @__PURE__ */ React.createElement("span", { className: "ho-hd-pill risk" }, /* @__PURE__ */ React.createElement(Icon, { n: "alert-outline" }), /* @__PURE__ */ React.createElement("b", null, risk.length), " en riesgo"), urgentRemCount > 0 && /* @__PURE__ */ React.createElement("span", { className: "ho-hd-pill reminder", style: { background: "rgba(213,150,35,.26)", color: "#f3cd7e" } }, /* @__PURE__ */ React.createElement(Icon, { n: "calendar-alert" }), /* @__PURE__ */ React.createElement("b", null, urgentRemCount), " recordatorios urgentes"), /* @__PURE__ */ React.createElement("span", { className: "ho-hd-pill unassi" }, /* @__PURE__ */ React.createElement(Icon, { n: "account-off-outline" }), /* @__PURE__ */ React.createElement("b", null, unassigned), " sin asignar")), /* @__PURE__ */ React.createElement("div", { className: "ho-hd-meta" }, /* @__PURE__ */ React.createElement("span", { className: "ho-hd-ts" }, ts ? `Actualizado ${fmtTs} \xB7 ${agoLabel}` : "Cargando\u2026"), /* @__PURE__ */ React.createElement("button", { className: `ho-refresh ${spin ? "spin" : ""}`, onClick: refresh, title: "Actualizar" }, /* @__PURE__ */ React.createElement(Icon, { n: "refresh" })))), /* @__PURE__ */ React.createElement("div", { className: "ho-tabs" }, /* @__PURE__ */ React.createElement("button", { className: `ho-tab ${tab === "oportunidades" ? "active" : ""}`, onClick: () => setTab("oportunidades") }, /* @__PURE__ */ React.createElement(Icon, { n: "fire" }), "Oportunidades calientes", /* @__PURE__ */ React.createElement("span", { className: "ho-tab-count" }, convs.length)), /* @__PURE__ */ React.createElement("button", { className: `ho-tab ${tab === "recordatorios" ? "active" : ""}`, onClick: () => setTab("recordatorios") }, /* @__PURE__ */ React.createElement(Icon, { n: "calendar-alert" }), "Recordatorios fallidos", /* @__PURE__ */ React.createElement("span", { className: "ho-tab-count" }, reminders.length), urgentRemCount > 0 && /* @__PURE__ */ React.createElement("span", { className: "ho-tab-urgent" }, /* @__PURE__ */ React.createElement(Icon, { n: "clock-alert-outline" }), urgentRemCount, " urgentes"))), tab === "oportunidades" && !loading && !error && /* @__PURE__ */ React.createElement("div", { className: "ho-fb" }, /* @__PURE__ */ React.createElement("span", { className: "ho-fb-label" }, /* @__PURE__ */ React.createElement(Icon, { n: "filter-variant" }), "Filtrar"), /* @__PURE__ */ React.createElement(FSelect, { value: fPrio, onChange: setFPrio, allLabel: "Prioridad", options: [["crit", "Cr\xEDtico"], ["risk", "En riesgo"], ["norm", "Bajo control"]] }), /* @__PURE__ */ React.createElement(FSelect, { value: fTopic, onChange: setFTopic, allLabel: "Topic", options: [["captacion_agendar", "Captaci\xF3n \xB7 agendar"], ["agenda_sin_disponibilidad", "Agenda sin disponibilidad"], ["faq_escalada", "FAQ escalada"], ["operacion_reagenda", "Operaci\xF3n \xB7 reagenda"]] }), /* @__PURE__ */ React.createElement(FSelect, { value: fSource, onChange: setFSource, allLabel: "Origen", options: [["Ads", "Ads"], ["Org\xE1nico", "Org\xE1nico"], ["Retorno", "Retorno"], ["Campa\xF1a", "Campa\xF1a"]] }), /* @__PURE__ */ React.createElement(FSelect, { value: fIntent, onChange: setFIntent, allLabel: "Intenci\xF3n", options: [["agendar", "Agendar"], ["reagendar", "Reagendar"], ["cancelar", "Cancelar"], ["informaci\xF3n", "Informaci\xF3n"]] }), /* @__PURE__ */ React.createElement(FSelect, { value: fAgent, onChange: setFAgent, allLabel: "Agente", options: [["none", "Sin asignar"], ...agents.map((a) => [String(a.id), a.name])] }), hasFilters && /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement("div", { className: "ho-fb-sep" }), /* @__PURE__ */ React.createElement("button", { className: "ho-fb-clear", onClick: clearFilters }, /* @__PURE__ */ React.createElement(Icon, { n: "close" }), "Limpiar")), /* @__PURE__ */ React.createElement("span", { className: "ho-fb-count" }, /* @__PURE__ */ React.createElement("b", null, filtered.length), " de ", convs.length, " conversaciones")), /* @__PURE__ */ React.createElement("div", { className: "ho-main" }, /* @__PURE__ */ React.createElement("main", { className: "ho-queue" }, loading ? /* @__PURE__ */ React.createElement(LoadingState, null) : error ? /* @__PURE__ */ React.createElement(ErrorState, { msg: error, onRetry: refresh }) : tab === "oportunidades" ? filtered.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "ho-empty" }, /* @__PURE__ */ React.createElement("div", { className: "ho-empty-ic" }, /* @__PURE__ */ React.createElement(Icon, { n: "check-all" })), /* @__PURE__ */ React.createElement("h4", null, "Sin oportunidades", hasFilters ? " con estos filtros" : ""), /* @__PURE__ */ React.createElement("p", null, hasFilters ? "Ajusta los filtros o espera nuevas conversaciones entrantes." : "No hay conversaciones activas en la bandeja.")) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(Section, __spreadValues({ prio: "crit", convs: crit }, sectionProps)), /* @__PURE__ */ React.createElement(Section, __spreadValues({ prio: "risk", convs: risk }, sectionProps)), /* @__PURE__ */ React.createElement(Section, __spreadValues({ prio: "norm", convs: norm }, sectionProps))) : /* @__PURE__ */ React.createElement(React.Fragment, null, reminders.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "ho-empty" }, /* @__PURE__ */ React.createElement("div", { className: "ho-empty-ic" }, /* @__PURE__ */ React.createElement(Icon, { n: "check-all" })), /* @__PURE__ */ React.createElement("h4", null, "Sin recordatorios fallidos"), /* @__PURE__ */ React.createElement("p", null, "El servicio de recordatorios no reporta entregas fallidas.")) : /* @__PURE__ */ React.createElement(React.Fragment, null, /* @__PURE__ */ React.createElement(FailureSummary, { reminders, active: failFilter, onFilter: setFailFilter }), filteredRem.length === 0 ? /* @__PURE__ */ React.createElement("div", { className: "ho-empty" }, /* @__PURE__ */ React.createElement("div", { className: "ho-empty-ic" }, /* @__PURE__ */ React.createElement(Icon, { n: "check-all" })), /* @__PURE__ */ React.createElement("h4", null, "Sin resultados con este filtro"), /* @__PURE__ */ React.createElement("p", null, "Selecciona otro tipo de fallo para ver los recordatorios correspondientes.")) : /* @__PURE__ */ React.createElement(React.Fragment, null, soonRem.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "sev-crit" }, /* @__PURE__ */ React.createElement(SecHeader, { ic: "clock-alert-outline", label: "URGENTE", sub: "cita en menos de 2 horas", count: soonRem.length }), /* @__PURE__ */ React.createElement("div", { className: "ho-cards" }, soonRem.map((r) => /* @__PURE__ */ React.createElement(RemCard, { key: r.id, rem: r, selected, onSelect: setSelected, onOpen: openChat, onRecontact: recontact })))), laterRem.length > 0 && /* @__PURE__ */ React.createElement("div", { className: "sev-risk" }, /* @__PURE__ */ React.createElement(SecHeader, { ic: "calendar-alert", label: "FALLIDOS", sub: "cita posterior", count: laterRem.length }), /* @__PURE__ */ React.createElement("div", { className: "ho-cards" }, laterRem.map((r) => /* @__PURE__ */ React.createElement(RemCard, { key: r.id, rem: r, selected, onSelect: setSelected, onOpen: openChat, onRecontact: recontact })))))))), !loading && !error && /* @__PURE__ */ React.createElement(AgentPanel, { agents })), /* @__PURE__ */ React.createElement(Toasts, { toasts }));
}
ReactDOM.createRoot(document.getElementById("root")).render(/* @__PURE__ */ React.createElement(App, null));
