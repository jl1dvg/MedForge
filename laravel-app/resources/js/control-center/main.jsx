import '@mdi/font/css/materialdesignicons.css';
import '../../css/control-center.css';
import React, { useEffect, useState, useRef } from 'react';
import { createRoot } from 'react-dom/client';

/* MedForge Control Center — approved visual mockup placeholders.
   Runtime data is hydrated from /v2/control-center endpoints below. */

/* ---- operational state metadata ---- */
let CC_STATES = {
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

let CC_PLANS = {
  Enterprise:   { tone: "acc",  color: "#7b80ff" },
  Professional: { tone: "read", color: "#4aa8ff" },
  Starter:      { tone: "mute", color: "#7d8aa3" },
  Trial:        { tone: "beta", color: "#b58bff" },
  Custom:       { tone: "maint",color: "#f5b53d" },
};

/* ---- API-first runtime collections ----
   These must stay empty until hydrated from /v2/control-center.
   Staging/production must never render demo organizations from React. */
let CC_CLIENTS = [];
let CC_FEATURES = [];
let CC_SERVICE_DEFS = [];
let SVC = { ok: "operativo", deg: "degradado", err: "error", pause: "pausado", none: "no_config" };
let CC_SERVICE_STATE = {};
let CC_SVC_META = {
  operativo:  { label: "Operativo",      cls: "prod",  color: "var(--st-prod)" },
  degradado:  { label: "Degradado",      cls: "maint", color: "var(--st-maint)" },
  error:      { label: "Error",          cls: "susp",  color: "var(--st-susp)" },
  pausado:    { label: "Pausado",        cls: "mute",  color: "var(--st-mute)" },
  no_config:  { label: "No configurado", cls: "mute",  color: "var(--st-mute)" },
};
let SVC_KEYMAP = { ok:"operativo", deg:"degradado", err:"error", pause:"pausado", none:"no_config" };

let CC_PLAN_CARDS = [];
let CC_RELEASES = [];
let CC_AUDIT = [];
let CC_STATE_HISTORY = {};

const DEV_VISUAL_DEMO_SERIES = import.meta.env.DEV ? {
  months: ["Ene", "Feb", "Mar", "Abr", "May", "Jun"],
  consumo: {
    iaTokens: [3.1, 3.6, 4.0, 4.4, 5.2, 6.9],
    iaCosto: [398, 462, 511, 560, 668, 883],
    waMsgs: [19.2, 21.0, 22.4, 24.1, 26.8, 28.0],
    conv: [3.4, 3.8, 4.1, 4.0, 4.6, 4.7],
    pdfs: [9.8, 10.4, 11.1, 12.0, 13.2, 14.6],
    reportes: [410, 468, 502, 540, 612, 647],
    storage: [402, 441, 478, 520, 566, 611],
    api: [1.4, 1.5, 1.6, 1.7, 1.9, 2.1],
  },
} : null;
const HAS_VISUAL_DEMO_SERIES = Boolean(DEV_VISUAL_DEMO_SERIES);

let CC_MONTHS = DEV_VISUAL_DEMO_SERIES?.months || [];
let CC_CONSUMO = DEV_VISUAL_DEMO_SERIES?.consumo || {
  iaTokens: [],
  iaCosto: [],
  waMsgs: [],
  conv: [],
  pdfs: [],
  reportes: [],
  storage: [],
  api: [],
};

let CC_BACKEND_STATUS = {
  overview: "idle",
  organizations: "idle",
  instances: "idle",
  details: "idle",
  services: "idle",
  plans: "idle",
  deployments: "idle",
  usage: "idle",
  audit: "idle",
};
let CC_BACKEND_ERRORS = {};
let CC_OVERVIEW_SUMMARY = {};
let CC_USAGE_TOTALS = {};
let CC_SERVICE_DETAILS = {};

const fmtNum = (n) => n.toLocaleString("es-EC");
const fmtMoney = (n) => "$" + n.toLocaleString("es-EC");

/* MedForge Control Center — shared UI components. Exposed to window. */
/* ---------- Card ---------- */
function Card({ title, icon, action, hint, children, flush, style, className = "" }) {
  return (
    <div className={`cc-card ${className}`} style={style}>
      {(title || action) && (
        <div className="cc-card-h">
          <h3>{icon && <i className={`mdi ${icon}`}></i>}{title}{hint && <span className="hint" style={{ marginLeft: 6 }}>{hint}</span>}</h3>
          {action}
        </div>
      )}
      <div className={`cc-card-b ${flush ? "flush" : ""}`}>{children}</div>
    </div>
  );
}

/* ---------- KPI ---------- */
function Kpi({ icon, tone = "acc", label, value, unit, delta, deltaDir = "up", foot }) {
  const toneBg = {
    acc:  ["var(--cc-accent-soft)", "var(--cc-accent)"],
    prod: ["var(--st-prod-bg)", "var(--st-prod)"],
    maint:["var(--st-maint-bg)", "var(--st-maint)"],
    read: ["var(--st-read-bg)", "var(--st-read)"],
    susp: ["var(--st-susp-bg)", "var(--st-susp)"],
    beta: ["var(--st-beta-bg)", "var(--st-beta)"],
  }[tone] || ["var(--cc-accent-soft)", "var(--cc-accent)"];
  return (
    <div className="cc-kpi fade-in">
      <div className="top">
        <div className="lbl">{label}</div>
        <div className="tile" style={{ background: toneBg[0], color: toneBg[1] }}><i className={`mdi ${icon}`}></i></div>
      </div>
      <div className="val">{value}{unit && <small>{unit}</small>}</div>
      <div className="foot">
        {delta != null && <span className={`cc-delta ${deltaDir}`}><i className={`mdi mdi-arrow-${deltaDir === "up" ? "up" : deltaDir === "dn" ? "down" : "right"}-thin`}></i>{delta}</span>}
        {foot}
      </div>
    </div>
  );
}

/* ---------- State badge ---------- */
function StateBadge({ estado, size }) {
  const s = CC_STATES[estado];
  if (!s) return null;
  return <span className={`cc-badge ${s.cls}`} style={size === "lg" ? { fontSize: 13, padding: "6px 14px" } : null}><span className="led"></span>{s.label}</span>;
}
function PayBadge({ pago, label }) {
  const cls = pago === "ok" ? "prod" : pago === "trial" ? "beta" : "susp";
  return <span className={`cc-badge ${cls}`}><span className="led"></span>{label}</span>;
}
function PlanBadge({ plan }) {
  const p = CC_PLANS[plan] || CC_PLANS.Starter;
  return <span className={`cc-badge ${p.tone}`}>{plan}</span>;
}
function RiskBadge({ riesgo }) {
  const map = { bajo: "prod", medio: "maint", alto: "susp", crítico: "susp" };
  return <span className={`cc-badge ${map[riesgo] || "mute"}`} style={{ textTransform: "capitalize" }}>{riesgo}</span>;
}

/* ---------- Avatar ---------- */
function ClientAva({ c, size = 32, radius = 8 }) {
  return <div style={{ width: size, height: size, borderRadius: radius, background: c.color, color: "#fff", display: "grid", placeItems: "center", font: `700 ${Math.round(size*0.36)}px var(--font-body)`, flexShrink: 0 }}>{c.inicial}</div>;
}

/* ---------- Switch ---------- */
function Switch({ on, onClick, disabled }) {
  return <button className={`cc-switch ${on ? "on" : ""}`} disabled={disabled} onClick={onClick} aria-pressed={on}></button>;
}

/* ---------- Progress ---------- */
function Progress({ value, max = 100, tone }) {
  const pct = Math.min(100, Math.round((value / max) * 100));
  const cls = tone || (pct >= 90 ? "dang" : pct >= 70 ? "warn" : "");
  return <div className={`cc-prog ${cls}`}><span style={{ width: pct + "%" }}></span></div>;
}

/* ---------- Bar chart ---------- */
function BarChart({ data, labels, unit = "", alt = false, fmt }) {
  if (!Array.isArray(data) || data.length === 0) return <ChartEmpty />;
  const max = Math.max(...data) * 1.15;
  return (
    <div>
      <div className="cc-bars">
        {data.map((v, i) => (
          <div key={i} className={`bar ${alt ? "alt" : ""}`} title={`${labels[i]}: ${(fmt ? fmt(v) : v)}${unit}`}>
            <span style={{ height: `${(v / max) * 100}%` }}></span>
            <b>{labels[i]}</b>
          </div>
        ))}
      </div>
    </div>
  );
}

/* ---------- Sparkline (SVG) ---------- */
function Sparkline({ data, color = "var(--cc-accent)", h = 44, fill = true }) {
  if (!Array.isArray(data) || data.length === 0) return <ChartEmpty compact />;
  const w = 200, max = Math.max(...data), min = Math.min(...data);
  const rng = max - min || 1;
  const pts = data.map((v, i) => [(i / (data.length - 1)) * w, h - 6 - ((v - min) / rng) * (h - 12)]);
  const line = pts.map((p, i) => `${i === 0 ? "M" : "L"}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(" ");
  const area = `${line} L${w},${h} L0,${h} Z`;
  const gid = "sg" + Math.random().toString(36).slice(2, 7);
  return (
    <svg className="cc-spark" viewBox={`0 0 ${w} ${h}`} preserveAspectRatio="none" style={{ height: h }}>
      <defs><linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stopColor={color} stopOpacity="0.28" />
        <stop offset="100%" stopColor={color} stopOpacity="0" />
      </linearGradient></defs>
      {fill && <path d={area} fill={`url(#${gid})`} />}
      <path d={line} fill="none" stroke={color} strokeWidth="2" strokeLinejoin="round" strokeLinecap="round" />
      <circle cx={pts[pts.length - 1][0]} cy={pts[pts.length - 1][1]} r="3" fill={color} />
    </svg>
  );
}

/* ---------- Area chart (larger, with grid + labels) ---------- */
function AreaChart({ data, labels, color = "var(--cc-accent)", unit = "", fmt, h = 200 }) {
  if (!Array.isArray(data) || data.length === 0) return <ChartEmpty height={h} />;
  const w = 560;
  const max = Math.max(...data) * 1.1, min = 0;
  const rng = max - min || 1;
  const pts = data.map((v, i) => [(i / (data.length - 1)) * w, h - 26 - ((v - min) / rng) * (h - 44)]);
  const line = pts.map((p, i) => `${i === 0 ? "M" : "L"}${p[0].toFixed(1)},${p[1].toFixed(1)}`).join(" ");
  const area = `${line} L${w},${h - 26} L0,${h - 26} Z`;
  const gid = "ag" + Math.random().toString(36).slice(2, 7);
  const grid = [0, 0.25, 0.5, 0.75, 1];
  return (
    <svg viewBox={`0 0 ${w} ${h}`} style={{ width: "100%", height: h, display: "block" }} preserveAspectRatio="none">
      <defs><linearGradient id={gid} x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stopColor={color} stopOpacity="0.30" />
        <stop offset="100%" stopColor={color} stopOpacity="0.02" />
      </linearGradient></defs>
      {grid.map((g, i) => <line key={i} x1="0" x2={w} y1={26 + g * (h - 52)} y2={26 + g * (h - 52)} stroke="var(--cc-grid)" strokeWidth="1" />)}
      <path d={area} fill={`url(#${gid})`} />
      <path d={line} fill="none" stroke={color} strokeWidth="2.4" strokeLinejoin="round" strokeLinecap="round" />
      {pts.map((p, i) => <circle key={i} cx={p[0]} cy={p[1]} r="3" fill="var(--cc-surface)" stroke={color} strokeWidth="2" />)}
      {labels.map((l, i) => <text key={i} x={pts[i][0]} y={h - 6} fill="var(--cc-fg-mute)" fontSize="10" fontFamily="var(--font-mono)" textAnchor="middle">{l}</text>)}
    </svg>
  );
}

function ChartEmpty({ height = 160, compact = false }) {
  return (
    <div className="cc-empty-chart" style={{ height: compact ? 44 : height, display: "grid", placeItems: "center", border: "1px dashed var(--cc-border)", borderRadius: 8, color: "var(--cc-fg-3)", fontSize: 12 }}>
      <span><i className="mdi mdi-chart-line-variant" style={{ marginRight: 6 }}></i>Pendiente de integración</span>
    </div>
  );
}

/* ---------- Donut ---------- */
function Donut({ segments, centerValue, centerLabel }) {
  let acc = 0;
  const stops = segments.map((s) => { const a = acc; acc += s.pct; return `${s.color} ${a}% ${acc}%`; }).join(", ");
  return (
    <div className="cc-donut-wrap">
      <div className="cc-donut" style={{ background: `conic-gradient(${stops})` }}>
        {centerValue != null && <div className="ctr"><b>{centerValue}</b><span>{centerLabel}</span></div>}
      </div>
      <ul className="cc-legend">
        {segments.map((s, i) => <li key={i}><i style={{ background: s.color }}></i>{s.label}<b>{s.val != null ? s.val : s.pct + "%"}</b></li>)}
      </ul>
    </div>
  );
}

/* ---------- Service status pill ---------- */
function ServicePill({ state }) {
  const m = CC_SVC_META[state] || CC_SVC_META.no_config;
  return <span className={`cc-badge ${m.cls}`}><span className="led" style={{ animation: state === "operativo" ? "ccPulse 2s infinite" : "none" }}></span>{m.label}</span>;
}

/* ---------- Page header ---------- */
function PageHead({ crumbs, title, sub, actions }) {
  return (
    <div className="cc-pagehead">
      <div>
        {crumbs && <div className="cc-crumb">{crumbs}</div>}
        <h1>{title}</h1>
        {sub && <p className="sub">{sub}</p>}
      </div>
      {actions && <div className="cc-headactions">{actions}</div>}
    </div>
  );
}

function SectionNotice({ section, empty, demo }) {
  if (CC_BACKEND_STATUS[section] === "error") {
    return (
      <div className="cc-alert warn" style={{ marginBottom: "var(--gap)" }}>
        <i className="mdi mdi-alert-circle-outline"></i>
        <div><p className="t">Datos no disponibles</p><p className="d">{CC_BACKEND_ERRORS[section] || "No se pudo cargar esta sección."}</p></div>
      </div>
    );
  }
  if (empty) {
    return (
      <div className="cc-alert info" style={{ marginBottom: "var(--gap)" }}>
        <i className="mdi mdi-database-off-outline"></i>
        <div><p className="t">Sin datos reales todavía</p><p className="d">El endpoint respondió, pero no hay registros para mostrar.</p></div>
      </div>
    );
  }
  if (demo) {
    return (
      <div className="cc-alert info" style={{ marginBottom: "var(--gap)" }}>
        <i className="mdi mdi-chart-timeline-variant"></i>
        <div><p className="t">{HAS_VISUAL_DEMO_SERIES ? "Visual Demo" : "Pendiente de integración"}</p><p className="d">{HAS_VISUAL_DEMO_SERIES ? "Esta gráfica conserva series demo solo en entorno de desarrollo." : "Las series históricas no se muestran en staging/producción hasta que el backend las entregue."}</p></div>
      </div>
    );
  }
  return null;
}

function EmptyState({ icon = "mdi-database-off-outline", title, description, actionLabel, onAction, compact = false }) {
  return (
    <div className="cc-card" style={{ padding: compact ? "22px 24px" : "34px 30px", textAlign: "center", display: "grid", justifyItems: "center", gap: 12 }}>
      <div style={{ width: compact ? 46 : 58, height: compact ? 46 : 58, borderRadius: 16, display: "grid", placeItems: "center", background: "var(--cc-accent-soft)", color: "var(--cc-accent)", fontSize: compact ? 24 : 30 }}>
        <i className={`mdi ${icon}`}></i>
      </div>
      <div>
        <h3 style={{ margin: 0, font: "700 18px var(--font-display)", color: "var(--cc-fg)" }}>{title}</h3>
        <p className="muted" style={{ margin: "8px auto 0", maxWidth: 560, fontSize: 13.5, lineHeight: 1.55 }}>{description}</p>
      </div>
      {actionLabel && (
        <button className="cc-btn primary sm" onClick={onAction} style={{ marginTop: 4 }}>
          <i className="mdi mdi-plus"></i>{actionLabel}
        </button>
      )}
    </div>
  );
}

function CreateOrganizationPlaceholder({ onClose }) {
  return (
    <Drawer
      title="Crear organización"
      subtitle="Flujo pendiente de implementación"
      onClose={onClose}
      footer={<button className="cc-btn primary" onClick={onClose}>Entendido</button>}
    >
      <div className="cc-alert info" style={{ marginBottom: 18 }}>
        <i className="mdi mdi-domain-plus"></i>
        <div>
          <p className="t">CRUD de organizaciones pendiente</p>
          <p className="d">El Control Center ya está preparado para consumir organizaciones reales desde el backend. La creación manual se implementará como flujo dedicado sin usar datos demo.</p>
        </div>
      </div>
      <dl className="cc-defs">
        <div className="row"><dt>Fuente de verdad</dt><dd>Backend /v2/control-center</dd></div>
        <div className="row"><dt>Resultado actual</dt><dd>No se insertan registros desde React.</dd></div>
        <div className="row"><dt>Próximo paso</dt><dd>Formulario con organización, instancia inicial, plan y dominio.</dd></div>
      </dl>
    </Drawer>
  );
}

/* ---------- Drawer ---------- */
function Drawer({ title, subtitle, children, footer, onClose }) {
  useEffect(() => {
    const h = (e) => { if (e.key === "Escape") onClose(); };
    window.addEventListener("keydown", h);
    return () => window.removeEventListener("keydown", h);
  }, []);
  return (
    <React.Fragment>
      <div className="cc-scrim" onClick={onClose}></div>
      <aside className="cc-drawer" role="dialog">
        <div className="cc-drawer-h">
          <div><h3>{title}</h3>{subtitle && <p>{subtitle}</p>}</div>
          <button className="cc-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="cc-drawer-b">{children}</div>
        {footer && <div className="cc-drawer-f">{footer}</div>}
      </aside>
    </React.Fragment>
  );
}

/* MedForge Control Center — Overview screen */

function ScreenOverview({ onOpenClient, onNav, env, onCreateOrganization }) {
  const activos = Number(CC_OVERVIEW_SUMMARY.production ?? CC_CLIENTS.filter(c => c.estado === "produccion").length);
  const enRiesgo = CC_CLIENTS.filter(c => c.riesgo === "alto" || c.riesgo === "crítico").length;
  const suspendidos = Number(CC_OVERVIEW_SUMMARY.suspended ?? CC_CLIENTS.filter(c => c.estado === "suspendido").length);
  const porVencer = null;
  const firstSuspended = CC_CLIENTS.find(c => c.estado === "suspendido");
  const firstReadonly = CC_CLIENTS.find(c => c.estado === "lectura");

  // global service health counts
  let counts = { operativo: 0, degradado: 0, error: 0, pausado: 0, no_config: 0 };
  Object.values(CC_SERVICE_STATE).forEach(svc => Object.values(svc).forEach(v => counts[SVC_KEYMAP[v]]++));
  const totalSvc = Object.values(counts).reduce((a, b) => a + b, 0) || 1;

  const iaMonthly = CC_CONSUMO.iaTokens;
  const waMonthly = CC_CONSUMO.waMsgs;

  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Overview"
        sub="Estado global de la plataforma MedForge — clientes, operación, consumo y eventos críticos en un solo lugar."
        actions={<React.Fragment>
          <button className="cc-btn line sm"><i className="mdi mdi-calendar-range"></i>Periodo actual</button>
          <button className="cc-btn ghost sm"><i className="mdi mdi-file-pdf-box"></i>Exportar</button>
        </React.Fragment>}
      />

      <SectionNotice section="instances" empty={CC_CLIENTS.length === 0} />
      {CC_CLIENTS.length === 0 && (
        <EmptyState
          icon="mdi-domain-plus"
          title="No existen organizaciones registradas."
          description="Este Control Center aún no tiene ninguna organización o instancia configurada desde el backend. Cuando la API devuelva registros reales, aparecerán aquí."
          actionLabel="Crear organización"
          onAction={onCreateOrganization}
        />
      )}

      {/* KPI strip */}
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-domain" tone="prod" label="Clientes activos" value={activos} delta="100% SLA" deltaDir="up"
             foot={<span className="muted">de {CC_CLIENTS.length} cuentas totales</span>} />
        <Kpi icon="mdi-alert-rhombus-outline" tone="susp" label="Clientes en riesgo" value={enRiesgo} delta={suspendidos ? `${suspendidos} suspendido(s)` : "0 suspendidos"} deltaDir="flat"
             foot={<span className="muted">pago vencido o incidencias</span>} />
        <Kpi icon="mdi-server-off" tone="maint" label="Servicios con incidencia" value={counts.error + counts.degradado} delta={counts.error + " en error"} deltaDir="flat"
             foot={<span className="muted">{counts.pausado} pausados por suspensión</span>} />
        <Kpi icon="mdi-license" tone="read" label="Licencias por vencer" value={porVencer ?? "Pendiente"} delta="Fase 2" deltaDir="flat"
             foot={<span className="muted">pendiente de integración contractual</span>} />
      </div>

      {/* critical alerts */}
      {(firstSuspended || firstReadonly) && (
        <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
          {firstSuspended && <div className="cc-alert danger">
            <i className="mdi mdi-cancel"></i>
            <div><p className="t">{firstSuspended.nombre} está suspendido</p>
              <p className="d">Estado operativo real reportado por Control Center. La causa específica depende de auditoría/contrato.</p></div>
          </div>}
          {firstReadonly && <div className="cc-alert warn">
            <i className="mdi mdi-eye-lock-outline"></i>
            <div><p className="t">{firstReadonly.nombre} en Solo lectura</p>
              <p className="d">Estado operativo real reportado por Control Center. Los usuarios solo pueden consultar información.</p></div>
          </div>}
        </div>
      )}

      {/* consumption row */}
      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Consumo mensual de IA" icon="mdi-brain"
              action={<div className="flex ac gap10"><span className="cc-tag">{HAS_VISUAL_DEMO_SERIES ? "Visual Demo" : "Pendiente de integración"}</span></div>}>
          <div className="flex jb ac" style={{ marginBottom: 10 }}>
            <div><div className="cc-kpi-inline" style={{ font: "700 28px var(--font-display)", color: "var(--cc-fg)" }}>{metricDisplay(CC_USAGE_TOTALS.aiTokens, compactNumber)}</div>
              <div className="muted" style={{ fontSize: 12 }}>tokens reales acumulados</div></div>
          </div>
          <AreaChart data={iaMonthly} labels={CC_MONTHS} color="var(--cc-accent)" h={170} />
        </Card>
        <Card title="Consumo de WhatsApp" icon="mdi-whatsapp"
              action={<div className="flex ac gap10"><span className="cc-tag">{HAS_VISUAL_DEMO_SERIES ? "Visual Demo" : "Pendiente de integración"}</span></div>}>
          <div className="flex jb ac" style={{ marginBottom: 10 }}>
            <div><div style={{ font: "700 28px var(--font-display)", color: "var(--cc-fg)" }}>{metricDisplay(CC_USAGE_TOTALS.whatsappMessages, compactNumber)}</div>
              <div className="muted" style={{ fontSize: 12 }}>mensajes reales acumulados</div></div>
          </div>
          <AreaChart data={waMonthly} labels={CC_MONTHS} color="var(--cc-accent-2)" h={170} fmt={(v)=>v+"K"} />
        </Card>
      </div>

      {/* servers health + critical events */}
      <div className="cc-grid g21">
        <Card title="Estado general de servidores" icon="mdi-server-network"
              action={<button className="cc-btn line sm" onClick={() => onNav("servicios")}>Ver servicios<i className="mdi mdi-arrow-right"></i></button>}>
          <div className="flex ac gap14" style={{ marginBottom: 18 }}>
            <Donut centerValue={Math.round(counts.operativo / totalSvc * 100) + "%"} centerLabel="operativo"
              segments={[
                { pct: Math.round(counts.operativo / totalSvc * 100), color: "var(--st-prod)", label: "Operativo", val: counts.operativo },
                { pct: Math.round(counts.degradado / totalSvc * 100), color: "var(--st-maint)", label: "Degradado", val: counts.degradado },
                { pct: Math.round(counts.error / totalSvc * 100), color: "var(--st-susp)", label: "Error", val: counts.error },
                { pct: Math.round(counts.pausado / totalSvc * 100), color: "var(--st-mute)", label: "Pausado / no config.", val: counts.pausado + counts.no_config },
              ]} />
            <div style={{ flex: 1, minWidth: 0 }}>
              {CC_CLIENTS.map(c => {
                const svc = CC_SERVICE_STATE[c.id];
                const states = Object.values(svc).map(v => SVC_KEYMAP[v]);
                const err = states.filter(s => s === "error").length;
                const deg = states.filter(s => s === "degradado").length;
                const pau = states.filter(s => s === "pausado").length;
                const dotColor = err ? "var(--st-susp)" : deg ? "var(--st-maint)" : pau ? "var(--st-mute)" : "var(--st-prod)";
                const txt = err ? `${err} en error` : deg ? `${deg} degradado` : pau ? "pausado" : "todo operativo";
                return (
                  <div key={c.id} className="flex ac jb" style={{ padding: "8px 0", borderBottom: "1px solid var(--cc-border)" }}>
                    <div className="flex ac gap10"><ClientAva c={c} size={26} /><span style={{ fontWeight: 600, fontSize: 13 }}>{c.nombre}</span></div>
                    <span className="flex ac gap6" style={{ fontSize: 12, color: "var(--cc-fg-3)" }}><span className="svc-dot" style={{ background: dotColor }}></span>{txt}</span>
                  </div>
                );
              })}
              {CC_CLIENTS.length === 0 && (
                <EmptyState compact icon="mdi-server-network-off" title="Aún no existen datos de monitoreo." description="Los servicios se renderizan únicamente desde /v2/control-center/services." />
              )}
            </div>
          </div>
        </Card>

        <Card title="Últimos eventos críticos" icon="mdi-timeline-alert-outline" flush
              action={<button className="cc-btn line sm" onClick={() => onNav("auditoria")}>Auditoría</button>}>
          <div style={{ padding: "16px 18px 4px" }}>
            <div className="cc-timeline">
              {CC_AUDIT.slice(0, 5).map((e, i) => (
                <div key={i} className="cc-tl-item">
                  <div className="cc-tl-dot" style={{ background: `var(--st-${e.cls === "acc" ? "read" : e.cls}-bg)`, color: e.cls === "acc" ? "var(--cc-accent)" : `var(--st-${e.cls})` }}>
                    <i className={`mdi ${e.icon}`}></i>
                  </div>
                  <div className="cc-tl-body">
                    <p className="t">{e.titulo}</p>
                    <div className="when"><span className="cc-tl-actor"><i className="mdi mdi-account-circle-outline" style={{ fontSize: 13 }}></i>{e.actor}</span>· {e.when}</div>
                  </div>
                </div>
              ))}
              {CC_AUDIT.length === 0 && (
                <p className="muted" style={{ padding: "20px 0" }}>Timeline vacío. No existen eventos de auditoría reales para mostrar.</p>
              )}
            </div>
          </div>
        </Card>
      </div>

      {/* clients quick strip */}
      <Card title="Clientes" icon="mdi-domain" flush style={{ marginTop: "var(--gap)" }}
            action={<button className="cc-btn line sm" onClick={() => onNav("clientes")}>Ver todos<i className="mdi mdi-arrow-right"></i></button>}>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Empresa</th><th>Plan</th><th>Estado</th><th>Usuarios</th><th>Versión</th><th>Pago</th><th></th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => (
                <tr key={c.id} className="clickable" onClick={() => onOpenClient(c.id)}>
                  <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                  <td><PlanBadge plan={c.plan} /></td>
                  <td><StateBadge estado={c.estado} /></td>
                  <td className="cc-mono">{c.usuarios}</td>
                  <td><span className="cc-tag">{c.version}</span></td>
                  <td><PayBadge pago={c.pago} label={c.pagoLabel} /></td>
                  <td style={{ textAlign: "right" }}><i className="mdi mdi-chevron-right" style={{ color: "var(--cc-fg-3)", fontSize: 20 }}></i></td>
                </tr>
              ))}
              {CC_CLIENTS.length === 0 && (
                <tr><td colSpan="7" style={{ padding: 22 }}><EmptyState compact icon="mdi-domain-off" title="No existen organizaciones registradas." description="No se muestran clientes ficticios cuando la API no devuelve datos." actionLabel="Crear organización" onAction={onCreateOrganization} /></td></tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}

/* MedForge Control Center — Clientes (listado + filtros) */

function ScreenClientes({ onOpenClient, onCreateOrganization }) {
  const [fEstado, setFEstado] = useState("todos");
  const [fPlan, setFPlan] = useState("todos");
  const [fCiudad, setFCiudad] = useState("todas");
  const [fPago, setFPago] = useState("todos");
  const [q, setQ] = useState("");

  const rows = CC_CLIENTS.filter(c =>
    (fEstado === "todos" || c.estado === fEstado) &&
    (fPlan === "todos" || c.plan === fPlan) &&
    (fCiudad === "todas" || c.ciudad === fCiudad) &&
    (fPago === "todos" || c.pago === fPago) &&
    (q === "" || c.nombre.toLowerCase().includes(q.toLowerCase()) || c.dominio.includes(q.toLowerCase()))
  );

  const ciudades = [...new Set(CC_CLIENTS.map(c => c.ciudad))];

  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Clientes"
        sub="Todas las organizaciones que operan sobre MedForge. Filtra por estado, plan, ciudad o vencimiento."
        actions={<React.Fragment>
          <button className="cc-btn ghost sm" disabled title="Pendiente Fase 2"><i className="mdi mdi-file-excel-box"></i>Exportar</button>
          <button className="cc-btn primary sm" onClick={onCreateOrganization}><i className="mdi mdi-plus"></i>Crear organización</button>
        </React.Fragment>}
      />

      <SectionNotice section="instances" empty={CC_CLIENTS.length === 0} />
      {CC_CLIENTS.length === 0 && (
        <EmptyState
          icon="mdi-domain-plus"
          title="No existen organizaciones registradas."
          description="Este Control Center aún no tiene ninguna organización configurada. No se muestran datos demo en staging ni producción."
          actionLabel="Crear organización"
          onAction={onCreateOrganization}
        />
      )}

      {CC_CLIENTS.length > 0 && <Card style={{ marginBottom: "var(--gap)" }}>
        <div className="cc-filters">
          <div className="cc-search" style={{ maxWidth: 260, flex: "0 0 260px" }}>
            <i className="mdi mdi-magnify"></i>
            <input placeholder="Buscar empresa o dominio…" value={q} onChange={e => setQ(e.target.value)} />
          </div>
          <div className="cc-field"><label>Estado operativo</label>
            <select value={fEstado} onChange={e => setFEstado(e.target.value)}>
              <option value="todos">Todos</option>
              <option value="produccion">Producción</option>
              <option value="mantenimiento">Mantenimiento</option>
              <option value="lectura">Solo lectura</option>
              <option value="suspendido">Suspendido</option>
            </select></div>
          <div className="cc-field"><label>Plan</label>
            <select value={fPlan} onChange={e => setFPlan(e.target.value)}>
              <option value="todos">Todos</option>
              <option>Enterprise</option><option>Professional</option><option>Starter</option><option>Trial</option><option>Custom</option>
            </select></div>
          <div className="cc-field"><label>Pago</label>
            <select value={fPago} onChange={e => setFPago(e.target.value)}>
              <option value="todos">Todos</option>
              <option value="ok">Al día</option><option value="vencido">Vencido</option><option value="trial">Trial</option>
            </select></div>
          <div className="cc-field"><label>Ciudad</label>
            <select value={fCiudad} onChange={e => setFCiudad(e.target.value)}>
              <option value="todas">Todas</option>
              {ciudades.map(c => <option key={c}>{c}</option>)}
            </select></div>
          <div style={{ flex: 1 }}></div>
          <button className="cc-btn line sm" onClick={() => { setFEstado("todos"); setFPlan("todos"); setFCiudad("todas"); setFPago("todos"); setQ(""); }}>
            <i className="mdi mdi-filter-remove-outline"></i>Limpiar
          </button>
        </div>
      </Card>}

      {CC_CLIENTS.length > 0 && <Card flush>
        <div className="flex jb ac" style={{ padding: "13px 18px", borderBottom: "1px solid var(--cc-border)" }}>
          <span style={{ fontSize: 12.5, color: "var(--cc-fg-3)" }}>Mostrando <b style={{ color: "var(--cc-fg)" }}>{rows.length}</b> de {CC_CLIENTS.length} clientes</span>
          <span className="cc-tag"><i className="mdi mdi-update" style={{ fontSize: 13 }}></i> Datos del backend</span>
        </div>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr>
              <th>Empresa</th><th>Plan</th><th>Estado</th><th>Usuarios</th><th>Últ. actividad</th><th>Versión</th><th>Consumo IA</th><th>Pago</th><th></th>
            </tr></thead>
            <tbody>
              {rows.map(c => (
                <tr key={c.id} className="clickable" onClick={() => onOpenClient(c.id)}>
                  <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                  <td><PlanBadge plan={c.plan} /></td>
                  <td><StateBadge estado={c.estado} /></td>
                  <td><span className="cc-mono">{c.usuarios}</span><span className="muted" style={{ fontSize: 11 }}> / {c.usuariosMax}</span></td>
                  <td className="muted" style={{ fontSize: 12.5 }}>{c.ultimaActividad}</td>
                  <td><span className="cc-tag">{c.version}</span></td>
                  <td style={{ minWidth: 130 }}>
                    <div className="flex ac gap10">
                      <div style={{ flex: 1 }}><Progress value={c.iaPct} /></div>
                      <span className="cc-mono" style={{ fontSize: 11.5 }}>{c.iaPct}%</span>
                    </div>
                  </td>
                  <td><PayBadge pago={c.pago} label={c.pagoLabel} /></td>
                  <td style={{ textAlign: "right" }}>
                    <button className="cc-btn line sm" onClick={(e) => { e.stopPropagation(); onOpenClient(c.id); }}>Ver detalle</button>
                  </td>
                </tr>
              ))}
              {rows.length === 0 && (
                <tr><td colSpan="9" style={{ textAlign: "center", padding: 40, color: "var(--cc-fg-3)" }}>No hay clientes que coincidan con los filtros aplicados.</td></tr>
              )}
            </tbody>
          </table>
        </div>
      </Card>}
    </div>
  );
}

/* MedForge Control Center — Client detail (ficha SaaS) with tabs.
   Includes Estado Operativo block + serious state-change drawer. */

function StateChangeDrawer({ client, current, onClose, onApply, busy }) {
  const [sel, setSel] = useState(current);
  const [inicio, setInicio] = useState("");
  const [fin, setFin] = useState("");
  const [motivo, setMotivo] = useState("");
  const [confirmTxt, setConfirmTxt] = useState("");
  const s = CC_STATES[sel];
  const isSerious = sel === "lectura" || sel === "suspendido";
  const changed = sel !== current;
  const confirmOk = !isSerious || confirmTxt.trim().toUpperCase() === s.label.toUpperCase();

  return (
    <Drawer
      title="Cambiar estado operativo"
      subtitle={`${client.nombre} · ${client.dominio}`}
      onClose={onClose}
      footer={<React.Fragment>
        <button className="cc-btn line" onClick={onClose}>Cancelar</button>
        <button className={`cc-btn ${isSerious ? "danger" : "primary"}`} disabled={busy || !changed || !confirmOk}
                onClick={() => onApply(sel, motivo)}>
          <i className={`mdi ${s.icon}`}></i>Aplicar «{s.label}»
        </button>
      </React.Fragment>}
    >
      <label className="cc-formlbl">Selecciona el nuevo estado</label>
      <div style={{ display: "grid", gap: 9, marginBottom: 22 }}>
        {Object.values(CC_STATES).map(o => (
          <div key={o.key} className={`cc-stopt ${sel === o.key ? "sel" : ""}`}
               style={{ color: `var(--st-${o.cls})` }} onClick={() => setSel(o.key)}>
            <div className="ic"><i className={`mdi ${o.icon}`}></i></div>
            <div><div className="nm">{o.label}</div><div className="dc">{o.impact.split(".")[0]}.</div></div>
            <div className="radio"></div>
          </div>
        ))}
      </div>

      {isSerious && (
        <div className="cc-alert warn" style={{ marginBottom: 20 }}>
          <i className="mdi mdi-shield-alert-outline"></i>
          <div><p className="t">Acción de licenciamiento sensible</p>
            <p className="d">Cambiar a «{s.label}» afecta directamente la operación de {client.usuarios} usuarios. Esta acción queda registrada en auditoría con tu identidad y marca de tiempo.</p></div>
        </div>
      )}

      <div className="cc-grid g2" style={{ gridTemplateColumns: "1fr 1fr", marginBottom: 18 }}>
        <div><label className="cc-formlbl">Fecha de inicio (opcional)</label>
          <input className="cc-input" type="datetime-local" value={inicio} onChange={e => setInicio(e.target.value)} /></div>
        <div><label className="cc-formlbl">Fecha de fin (opcional)</label>
          <input className="cc-input" type="datetime-local" value={fin} onChange={e => setFin(e.target.value)} /></div>
      </div>

      <div style={{ marginBottom: 18 }}>
        <label className="cc-formlbl">Motivo interno</label>
        <textarea className="cc-input" placeholder="Visible solo para el equipo de MedForge. Ej: «Mora superior a 30 días», «Mantenimiento programado»…"
                  value={motivo} onChange={e => setMotivo(e.target.value)}></textarea>
      </div>

      {s.msg && (
        <div style={{ marginBottom: 18 }}>
          <label className="cc-formlbl">Vista previa — mensaje que verá el cliente</label>
          <div className="cc-preview">
            <div className="winbar"><i></i><i></i><i></i></div>
            <div className="flex ac gap10" style={{ marginBottom: 10 }}>
              <div style={{ width: 34, height: 34, borderRadius: 9, background: `var(--st-${s.cls}-bg)`, color: `var(--st-${s.cls})`, display: "grid", placeItems: "center", fontSize: 19 }}><i className={`mdi ${s.icon}`}></i></div>
              <b style={{ font: "600 14px var(--font-display)", color: `var(--st-${s.cls})` }}>{s.label}</b>
            </div>
            <p style={{ margin: 0, fontSize: 13, lineHeight: 1.6, color: "var(--cc-fg-2)" }}>{s.msg}</p>
          </div>
        </div>
      )}

      {isSerious && (
        <div>
          <label className="cc-formlbl">Para confirmar, escribe «{s.label}»</label>
          <input className="cc-input" value={confirmTxt} onChange={e => setConfirmTxt(e.target.value)} placeholder={s.label} />
        </div>
      )}
    </Drawer>
  );
}

/* ---- Estado Operativo tab content ---- */
function TabEstado({ client, estado, history, onChangeClick }) {
  const s = CC_STATES[estado];
  return (
    <div className="fade-in">
      <div className={`cc-statebanner ${s.cls}`} style={{ marginBottom: "var(--gap)" }}>
        <div className="glyph"><i className={`mdi ${s.icon}`}></i></div>
        <div style={{ flex: 1 }}>
          <div className="flex ac gap10" style={{ marginBottom: 2 }}>
            <p className="big">{s.label}</p>
            <span style={{ fontSize: 11, color: "var(--cc-fg-3)", fontFamily: "var(--font-mono)" }}>estado actual</span>
          </div>
          <p className="imp">{s.impact}</p>
        </div>
        <button className="cc-btn line" onClick={onChangeClick} style={{ flexShrink: 0 }}><i className="mdi mdi-swap-horizontal"></i>Cambiar estado</button>
      </div>

      {estado === "lectura" && (
        <div className="cc-alert info" style={{ marginBottom: "var(--gap)" }}>
          <i className="mdi mdi-information-outline"></i>
          <div><p className="t">Mensaje activo para el cliente</p>
            <p className="d">«{CC_STATES.lectura.msg}»</p></div>
        </div>
      )}

      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Cambiar estado operativo" icon="mdi-tune-variant">
          <p className="muted" style={{ fontSize: 13, marginTop: 0, lineHeight: 1.55 }}>Define cómo opera este cliente. Los cambios sensibles (Solo lectura y Suspendido) requieren confirmación y quedan auditados.</p>
          <div style={{ display: "grid", gap: 9, marginTop: 14 }}>
            {Object.values(CC_STATES).map(o => (
              <div key={o.key} className={`cc-stopt ${estado === o.key ? "sel" : ""}`} style={{ color: `var(--st-${o.cls})`, cursor: "default" }}>
                <div className="ic"><i className={`mdi ${o.icon}`}></i></div>
                <div><div className="nm">{o.label}</div><div className="dc">{o.impact.split(".")[0]}.</div></div>
                {estado === o.key ? <span className="cc-badge acc" style={{ color: `var(--st-${o.cls})`, background: `var(--st-${o.cls}-bg)` }}>Activo</span>
                  : <button className="cc-btn line sm" onClick={onChangeClick}>Activar</button>}
              </div>
            ))}
          </div>
        </Card>

        <Card title="Historial de cambios de estado" icon="mdi-history" flush>
          <div style={{ padding: "16px 18px 4px" }}>
            <div className="cc-timeline">
              {(history || []).map((h, i) => {
                const hs = CC_STATES[h.estado];
                return (
                  <div key={i} className="cc-tl-item">
                    <div className="cc-tl-dot" style={{ background: `var(--st-${hs.cls}-bg)`, color: `var(--st-${hs.cls})` }}><i className={`mdi ${hs.icon}`}></i></div>
                    <div className="cc-tl-body">
                      <p className="t">{hs.label}</p>
                      <p className="d">{h.motivo}</p>
                      <div className="when"><span className="cc-tl-actor"><i className="mdi mdi-account-circle-outline" style={{ fontSize: 13 }}></i>{h.actor}</span>· {h.when}</div>
                    </div>
                  </div>
                );
              })}
            </div>
          </div>
        </Card>
      </div>
    </div>
  );
}

/* ---- Resumen tab ---- */
function TabResumen({ client }) {
  const c = client;
  const Def = ({ l, children, mono }) => (
    <div className="row"><dt>{l}</dt><dd className={mono ? "mono" : ""}>{children}</dd></div>
  );
  return (
    <div className="fade-in">
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-account-group-outline" tone="acc" label="Usuarios activos" value={c.usuarios} foot={<span className="muted">de {c.usuariosMax} permitidos</span>} />
        <Kpi icon="mdi-lifebuoy" tone={c.tickets > 5 ? "susp" : "maint"} label="Tickets abiertos" value={c.tickets} foot={<span className="muted">soporte técnico</span>} />
        <Kpi icon="mdi-brain" tone="read" label="Consumo de IA" value={c.iaPct} unit="%" foot={<span className="muted">{fmtNum(c.iaTokens)} tokens</span>} />
        <Kpi icon="mdi-database" tone="beta" label="Almacenamiento" value={c.storage} unit="GB" foot={<span className="muted">de {c.storageMax} GB</span>} />
      </div>

      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Información comercial" icon="mdi-card-account-details-outline">
          <dl className="cc-defs">
            <Def l="Nombre comercial">{c.nombre}</Def>
            <Def l="Razón social">{c.razon}</Def>
            <Def l="RUC" mono>{c.ruc}</Def>
            <Def l="Dominio principal" mono>{c.dominio}</Def>
            <Def l="Ciudad">{c.ciudad}</Def>
            <Def l="Plan contratado"><PlanBadge plan={c.plan} /></Def>
            <Def l="Estado de pago"><PayBadge pago={c.pago} label={c.pagoLabel} /></Def>
          </dl>
        </Card>
        <Card title="Contrato y contactos" icon="mdi-file-sign">
          <dl className="cc-defs">
            <Def l="Fecha de inicio" mono>{c.inicio}</Def>
            <Def l="Fecha de vencimiento" mono>{c.vence}</Def>
            <Def l="Contacto administrativo">{c.contactoAdmin.n}<br /><span className="mono muted" style={{ fontSize: 11, fontWeight: 400 }}>{c.contactoAdmin.c}</span></Def>
            <Def l="Contacto técnico">{c.contactoTec.n}<br /><span className="mono muted" style={{ fontSize: 11, fontWeight: 400 }}>{c.contactoTec.c}</span></Def>
          </dl>
        </Card>
      </div>

      <div className="cc-grid g3" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Despliegue" icon="mdi-rocket-launch-outline">
          <dl className="cc-defs">
            <Def l="Versión instalada"><span className="cc-tag">{c.version}</span></Def>
            <Def l="Canal">{c.canal}</Def>
            <Def l="Último deploy" mono>{c.ultimoDeploy}</Def>
            <Def l="Último backup" mono>{c.ultimoBackup}</Def>
          </dl>
        </Card>
        <Card title="Consumo de IA" icon="mdi-brain">
          <div className="flex jb ac"><div style={{ font: "700 26px var(--font-display)", color: "var(--cc-fg)" }}>{fmtNum(c.iaTokens)}</div><span className="cc-tag">{fmtMoney(c.iaCosto)} / mes</span></div>
          <div className="muted" style={{ fontSize: 12, margin: "2px 0 12px" }}>tokens este mes</div>
          <Progress value={c.iaPct} />
          <Sparkline data={CC_CONSUMO.iaTokens} color="var(--cc-accent)" />
        </Card>
        <Card title="WhatsApp & almacenamiento" icon="mdi-whatsapp">
          <dl className="cc-defs">
            <Def l="Mensajes enviados" mono>{fmtNum(c.waMsgs)}</Def>
            <Def l="Conversaciones" mono>{fmtNum(c.waConv)}</Def>
            <Def l="PDFs generados" mono>{fmtNum(c.pdfs)}</Def>
          </dl>
          <div style={{ marginTop: 10 }}><Progress value={c.storage} max={c.storageMax} /></div>
          <div className="muted" style={{ fontSize: 11.5, marginTop: 6 }}>{c.storage} GB de {c.storageMax} GB de almacenamiento</div>
        </Card>
      </div>
    </div>
  );
}

function ClientDetail({ clientId, onBack, onNav, onDataChanged }) {
  const base = CC_CLIENTS.find(c => c.id === clientId);
  const [estado, setEstado] = useState(base?.estado || "produccion");
  const [history, setHistory] = useState(CC_STATE_HISTORY[clientId] || []);
  const [tab, setTab] = useState("resumen");
  const [drawer, setDrawer] = useState(false);
  const [flags, setFlags] = useState(() => {
    const byKey = new Map((base?.features || []).map(feature => [feature.key, Boolean(feature.enabled)]));
    const o = {}; CC_FEATURES.forEach(f => o[f.id] = byKey.has(f.id) ? byKey.get(f.id) : f.on);
    return o;
  });
  if (!base) {
    return (
      <div className="cc-page fade-in">
        <PageHead title="Instancia no encontrada" sub="La instancia solicitada no existe en los datos reales cargados desde el backend."
          actions={<button className="cc-btn line sm" onClick={onBack}><i className="mdi mdi-arrow-left"></i>Volver</button>} />
        <EmptyState icon="mdi-domain-off" title="No existe esta instancia." description="El backend no devolvió un registro para este identificador. No se reconstruyen datos desde mocks de React." />
      </div>
    );
  }
  const c = { ...base, estado };

  const [savingState, setSavingState] = useState(false);
  const applyState = async (newState, motivo) => {
    setSavingState(true);
    try {
      await changeInstanceState(clientId, newState, motivo || "Cambio desde Control Center");
      setEstado(newState);
      setHistory(h => [{ estado: newState, actor: "Equipo MedForge", motivo: motivo || "Sin motivo especificado.", when: "ahora" }, ...h]);
      setDrawer(false);
      if (onDataChanged) await onDataChanged(clientId);
    } catch (error) {
      window.alert(error.message || "No se pudo cambiar el estado operativo.");
    } finally {
      setSavingState(false);
    }
  };

  const tabs = [
    { id: "resumen", label: "Resumen", icon: "mdi-information-outline" },
    { id: "estado", label: "Estado operativo", icon: "mdi-toggle-switch-outline" },
    { id: "features", label: "Feature flags", icon: "mdi-flag-variant-outline" },
    { id: "servicios", label: "Servicios", icon: "mdi-server-network" },
    { id: "deploys", label: "Deploys", icon: "mdi-rocket-launch-outline" },
    { id: "consumo", label: "Consumo", icon: "mdi-chart-areaspline" },
  ];

  return (
    <div className="cc-page fade-in">
      <PageHead
        crumbs={<React.Fragment><a onClick={onBack}><i className="mdi mdi-home-outline"></i></a><i className="mdi mdi-chevron-right"></i><a onClick={onBack}>Clientes</a><i className="mdi mdi-chevron-right"></i>{c.nombre}</React.Fragment>}
        title={<span className="flex ac gap14"><ClientAva c={c} size={40} radius={11} />{c.nombre}</span>}
        actions={<React.Fragment>
          <button className="cc-btn line sm" onClick={onBack}><i className="mdi mdi-arrow-left"></i>Volver</button>
          <button className="cc-btn ghost sm"><i className="mdi mdi-open-in-new"></i>Abrir instancia</button>
          <button className="cc-btn primary sm" onClick={() => { setTab("estado"); setDrawer(true); }}><i className="mdi mdi-swap-horizontal"></i>Cambiar estado</button>
        </React.Fragment>}
      />

      {/* identity strip */}
      <div className="flex ac wrap gap10" style={{ marginBottom: 20 }}>
        <span className="cc-tag"><i className="mdi mdi-web" style={{ fontSize: 13 }}></i>{c.dominio}</span>
        <PlanBadge plan={c.plan} />
        <StateBadge estado={estado} />
        <PayBadge pago={c.pago} label={c.pagoLabel} />
        <span className="cc-tag">{c.version}</span>
        <span className="muted" style={{ fontSize: 12, marginLeft: 4 }}><i className="mdi mdi-clock-outline" style={{ fontSize: 13, verticalAlign: -2 }}></i> Últ. actividad {c.ultimaActividad}</span>
      </div>

      <div className="cc-tabs">
        {tabs.map(t => (
          <button key={t.id} className={tab === t.id ? "on" : ""} onClick={() => setTab(t.id)}>
            <i className={`mdi ${t.icon}`}></i>{t.label}
            {t.id === "estado" && (estado === "lectura" || estado === "suspendido") && <span className="svc-dot" style={{ background: `var(--st-${CC_STATES[estado].cls})` }}></span>}
          </button>
        ))}
      </div>

      {tab === "resumen" && <TabResumen client={c} />}
      {tab === "estado" && <TabEstado client={c} estado={estado} history={history} onChangeClick={() => setDrawer(true)} />}
      {tab === "features" && <FeatureFlagsPanel flags={flags} setFlags={setFlags} scope={c.nombre} clientId={clientId} onDataChanged={onDataChanged} />}
      {tab === "servicios" && <ServicesPanel clientId={clientId} />}
      {tab === "deploys" && <DeploysPanel client={c} />}
      {tab === "consumo" && <ConsumoPanel client={c} />}

      {drawer && <StateChangeDrawer client={c} current={estado} onClose={() => setDrawer(false)} onApply={applyState} busy={savingState} />}
    </div>
  );
}

/* MedForge Control Center — Licencias, Deploys, Consumo, Auditoría */

/* ============ LICENCIAS Y PLANES ============ */
function ScreenLicencias() {
  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Licencias y Planes"
        sub="Catálogo de planes comerciales, límites incluidos y estado de los contratos vigentes."
        actions={<button className="cc-btn primary sm" disabled title="Pendiente Fase 2"><i className="mdi mdi-plus"></i>Nuevo plan</button>}
      />

      <SectionNotice section="plans" empty={CC_PLAN_CARDS.length === 0} />
      {CC_PLAN_CARDS.length === 0 && (
        <EmptyState icon="mdi-license-off" title="No existen planes configurados." description="La sección de planes solo renderiza registros devueltos por /v2/control-center/plans." />
      )}

      {CC_PLAN_CARDS.length > 0 && <div className="cc-grid g4" style={{ marginBottom: "var(--gap)", alignItems: "stretch" }}>
        {CC_PLAN_CARDS.map(p => (
          <div key={p.nombre} className="cc-card" style={{ padding: 0, overflow: "hidden", position: "relative", borderColor: p.destacado ? p.color : undefined, borderWidth: p.destacado ? 1.5 : 1 }}>
            <div style={{ height: 4, background: p.color }}></div>
            <div style={{ padding: "18px 20px" }}>
              {p.destacado && <span className="cc-badge acc" style={{ position: "absolute", top: 16, right: 16, color: p.color, background: `${p.color}22` }}>Más usado</span>}
              <div style={{ font: "700 17px var(--font-display)", color: "var(--cc-fg)" }}>{p.nombre}</div>
              <div style={{ display: "flex", alignItems: "baseline", gap: 4, margin: "8px 0 4px" }}>
                {p.precio != null ? <React.Fragment><span style={{ font: "700 30px var(--font-display)", color: "var(--cc-fg)" }}>{fmtMoney(p.precio)}</span><span className="muted" style={{ fontSize: 12 }}>/ mes</span></React.Fragment>
                  : <span style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)" }}>A medida</span>}
              </div>
              <div className="muted" style={{ fontSize: 12, marginBottom: 14 }}>{p.clientes} cliente{p.clientes === 1 ? "" : "s"} activo{p.clientes === 1 ? "" : "s"}</div>
              <dl className="cc-defs" style={{ fontSize: 12.5 }}>
                <LiRow l="Usuarios" v={p.usuarios} />
                <LiRow l="Módulos" v={p.modulos} />
                <LiRow l="IA / mes" v={p.ia} />
                <LiRow l="WhatsApp" v={p.wa} />
                <LiRow l="Storage" v={p.storage} />
                <LiRow l="Soporte" v={p.soporte} />
                <LiRow l="SLA" v={p.sla} mono />
              </dl>
            </div>
          </div>
        ))}
      </div>}

      <Card title="Contratos vigentes" icon="mdi-file-sign" flush>
        <SectionNotice section="organizations" empty={CC_CLIENTS.length === 0} />
        {CC_CLIENTS.length === 0 && (
          <div style={{ padding: 18 }}>
            <EmptyState compact icon="mdi-file-sign" title="No existen contratos visibles." description="Los contratos/licencias aparecen cuando existen organizaciones e instancias reales." />
          </div>
        )}
        {CC_CLIENTS.length > 0 && <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Empresa</th><th>Plan</th><th>Inicio</th><th>Vencimiento</th><th>Usuarios</th><th>Estado pago</th><th>Contrato</th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => {
                const vencido = c.pago === "vencido";
                return (
                  <tr key={c.id}>
                    <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                    <td><PlanBadge plan={c.plan} /></td>
                    <td className="cc-mono">{c.inicio}</td>
                    <td className="cc-mono" style={{ color: vencido ? "var(--st-susp)" : undefined }}>{c.vence}</td>
                    <td><span className="cc-mono">{c.usuarios}/{c.usuariosMax}</span></td>
                    <td><PayBadge pago={c.pago} label={c.pagoLabel} /></td>
                    <td><span className={`cc-badge ${vencido ? "susp" : c.pago === "trial" ? "beta" : "prod"}`}>{vencido ? "Renovación pendiente" : c.pago === "trial" ? "En evaluación" : "Vigente"}</span></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>}
      </Card>
    </div>
  );
}
function LiRow({ l, v, mono }) { return <div className="row"><dt>{l}</dt><dd className={mono ? "mono" : ""} style={{ maxWidth: "55%" }}>{v}</dd></div>; }

/* ============ DEPLOYS Y VERSIONES ============ */
function DeploysPanel({ client }) {
  const behind = client.version !== client.versionDisp && client.versionDisp === "2026.6.1";
  return (
    <div className="fade-in">
      <div className="cc-grid g3" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Versión actual" icon="mdi-package-variant-closed"><div style={{ font: "700 26px var(--font-display)", color: "var(--cc-fg)" }}>{client.version}</div><div className="muted" style={{ fontSize: 12, marginTop: 4 }}>Canal {client.canal}</div></Card>
        <Card title="Versión disponible" icon="mdi-cloud-download-outline"><div style={{ font: "700 26px var(--font-display)", color: behind ? "var(--st-maint)" : "var(--st-prod)" }}>{client.versionDisp}</div><div className="muted" style={{ fontSize: 12, marginTop: 4 }}>{behind ? "Actualización disponible" : "Al día"}</div></Card>
        <Card title="Último deploy" icon="mdi-clock-fast"><div style={{ font: "600 16px var(--font-mono)", color: "var(--cc-fg)" }}>{client.ultimoDeploy}</div><div className="muted" style={{ fontSize: 12, marginTop: 6 }}>Responsable: Plataforma</div></Card>
      </div>
      {behind && (
        <div className="cc-alert warn" style={{ marginBottom: "var(--gap)" }}>
          <i className="mdi mdi-update"></i>
          <div className="flex jb ac" style={{ width: "100%", gap: 16 }}>
            <div><p className="t">Actualización disponible: {client.versionDisp}</p><p className="d">Esta instancia está {client.version}. Programa la actualización en una ventana de mantenimiento.</p></div>
            <button className="cc-btn primary sm" style={{ flexShrink: 0 }}><i className="mdi mdi-calendar-clock"></i>Programar actualización</button>
          </div>
        </div>
      )}
      <ReleaseTimeline current={client.version} />
    </div>
  );
}

function ReleaseTimeline({ current }) {
  if (CC_RELEASES.length === 0) {
    return <EmptyState icon="mdi-source-branch-off" title="No existen releases registrados." description="El timeline solo muestra deploys/versiones devueltos por /v2/control-center/deployments." />;
  }
  return (
    <Card title="Timeline de releases" icon="mdi-source-branch" flush>
      <div style={{ padding: "18px 20px 6px" }}>
        <div className="cc-timeline">
          {CC_RELEASES.map((r, i) => (
            <div key={i} className="cc-tl-item">
              <div className="cc-tl-dot" style={{ background: `var(--st-${r.cls === "prod" ? "prod" : "beta"}-bg)`, color: `var(--st-${r.cls === "prod" ? "prod" : "beta"})` }}><i className="mdi mdi-tag-outline"></i></div>
              <div className="cc-tl-body">
                <p className="t flex ac gap10" style={{ display: "flex" }}>
                  <span className="cc-tag" style={{ fontSize: 12 }}>{r.v}</span>
                  <span className={`cc-badge ${r.canal === "Stable" ? "prod" : "beta"}`}>{r.canal}</span>
                  {r.v === current && <span className="cc-badge acc">Instalada aquí</span>}
                </p>
                <p className="d" style={{ marginTop: 4 }}>{r.titulo}</p>
                <div className="when">{r.fecha} · {r.estado}</div>
              </div>
            </div>
          ))}
        </div>
      </div>
    </Card>
  );
}

function ScreenDeploys() {
  return (
    <div className="cc-page fade-in">
      <PageHead title="Deploys y Versiones" sub="Gestión de releases por cliente. Controla canales, versiones instaladas y programa actualizaciones."
        actions={<button className="cc-btn ghost sm" disabled title="Pendiente Fase 2"><i className="mdi mdi-source-branch"></i>Canales</button>} />
      <SectionNotice section="deployments" empty={CC_RELEASES.length === 0 && CC_CLIENTS.length === 0} />
      {CC_CLIENTS.length === 0 && (
        <EmptyState icon="mdi-rocket-launch-outline" title="No existen deployments registrados." description="No hay instancias reales sobre las cuales mostrar versiones o deploys." />
      )}
      {CC_CLIENTS.length > 0 && <Card flush style={{ marginBottom: "var(--gap)" }}>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Cliente</th><th>Versión actual</th><th>Disponible</th><th>Canal</th><th>Último deploy</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => {
                const behind = c.version !== c.versionDisp;
                return (
                  <tr key={c.id}>
                    <td><div className="ent"><ClientAva c={c} /><div className="nm">{c.nombre}</div></div></td>
                    <td><span className="cc-tag">{c.version}</span></td>
                    <td>{behind ? <span className="cc-tag" style={{ color: "var(--st-maint)", borderColor: "color-mix(in srgb,var(--st-maint) 40%,transparent)" }}>{c.versionDisp}</span> : <span className="muted" style={{ fontSize: 12 }}>—</span>}</td>
                    <td><span className={`cc-badge ${c.canal === "Stable" ? "prod" : "beta"}`}>{c.canal}</span></td>
                    <td className="cc-mono" style={{ fontSize: 12 }}>{c.ultimoDeploy}</td>
                    <td>{behind ? <span className="cc-badge maint">Desactualizado</span> : <span className="cc-badge prod"><span className="led"></span>Al día</span>}</td>
                    <td style={{ textAlign: "right" }}><button className="cc-btn line sm" disabled title="Pendiente Fase 2"><i className="mdi mdi-calendar-clock"></i>Programar</button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>}
      <ReleaseTimeline current="2026.6.1" />
    </div>
  );
}

/* ============ CONSUMO ============ */
function ConsumoPanel({ client }) {
  return (
    <div className="fade-in">
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-brain" tone="acc" label="Tokens IA" value={fmtNum(client.iaTokens)} foot={<span className="muted">{fmtMoney(client.iaCosto)} estimado</span>} />
        <Kpi icon="mdi-whatsapp" tone="prod" label="Mensajes WhatsApp" value={fmtNum(client.waMsgs)} foot={<span className="muted">{fmtNum(client.waConv)} conversaciones</span>} />
        <Kpi icon="mdi-file-pdf-box" tone="read" label="PDFs generados" value={fmtNum(client.pdfs)} foot={<span className="muted">{fmtNum(client.reportes)} reportes</span>} />
        <Kpi icon="mdi-api" tone="beta" label="Llamadas API" value={fmtNum(client.apiCalls)} foot={<span className="muted">este mes</span>} />
      </div>
      <div className="cc-grid g2">
        <Card title="Consumo de IA — comparativo mensual" icon="mdi-chart-areaspline"><AreaChart data={CC_CONSUMO.iaTokens} labels={CC_MONTHS} color="var(--cc-accent)" h={190} /></Card>
        <Card title="Costo estimado de IA" icon="mdi-cash"><AreaChart data={CC_CONSUMO.iaCosto} labels={CC_MONTHS} color="var(--st-prod)" h={190} /></Card>
      </div>
    </div>
  );
}

function ScreenConsumo() {
  const [vista, setVista] = useState("global");
  const hasUsage = Object.values(CC_USAGE_TOTALS).some(value => Number(value || 0) > 0);
  return (
    <div className="cc-page fade-in">
      <PageHead title="Consumo" sub="Métricas de uso de la plataforma: IA, WhatsApp, documentos, almacenamiento y API."
        actions={<button className="cc-btn ghost sm" disabled title="Pendiente Fase 2"><i className="mdi mdi-file-excel-box"></i>Exportar</button>} />
      <SectionNotice section="usage" empty={!hasUsage} />
      {!hasUsage && (
        <EmptyState icon="mdi-chart-areaspline" title="No existen métricas de consumo." description="La pantalla no inventa tokens, mensajes ni costos. Mostrará datos cuando /v2/control-center/usage devuelva registros." />
      )}
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        <Kpi icon="mdi-brain" tone="acc" label="Tokens IA usados" value={metricDisplay(CC_USAGE_TOTALS.aiTokens, compactNumber)} delta="Real" deltaDir="flat" foot={<span className="muted">desde /usage</span>} />
        <Kpi icon="mdi-cash" tone="prod" label="Costo estimado IA" value={CC_USAGE_TOTALS.aiCost ? fmtMoney(Math.round(CC_USAGE_TOTALS.aiCost)) : "Pendiente"} delta="Real" deltaDir="flat" foot={<span className="muted">cost registrado</span>} />
        <Kpi icon="mdi-whatsapp" tone="read" label="Mensajes WhatsApp" value={metricDisplay(CC_USAGE_TOTALS.whatsappMessages, compactNumber)} delta="Real" deltaDir="flat" foot={<span className="muted">desde /usage</span>} />
        <Kpi icon="mdi-folder-outline" tone="beta" label="Storage usado" value={metricDisplay(CC_USAGE_TOTALS.storageGb, compactNumber)} unit={CC_USAGE_TOTALS.storageGb ? "GB" : ""} delta="Real" deltaDir="flat" foot={<span className="muted">desde /usage</span>} />
      </div>

      <SectionNotice section="usage" demo />
      <div className="cc-grid g2" style={{ marginBottom: "var(--gap)" }}>
        <Card title="Tokens IA — comparativo mensual" icon="mdi-chart-areaspline" action={<span className="cc-tag">{HAS_VISUAL_DEMO_SERIES ? "Visual Demo" : "Pendiente de integración"}</span>}><AreaChart data={CC_CONSUMO.iaTokens} labels={CC_MONTHS} color="var(--cc-accent)" h={200} /></Card>
        <Card title="Mensajes WhatsApp enviados" icon="mdi-message-text-outline" action={<span className="cc-tag">{HAS_VISUAL_DEMO_SERIES ? "Visual Demo" : "Pendiente de integración"}</span>}><BarChart data={CC_CONSUMO.waMsgs} labels={CC_MONTHS} alt /></Card>
      </div>

      <div className="cc-grid g3" style={{ marginBottom: "var(--gap)" }}>
        <Card title="PDFs generados" icon="mdi-file-pdf-box"><div style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)", marginBottom: 8 }}>{metricDisplay(CC_USAGE_TOTALS.pdfs, compactNumber)}</div><BarChart data={CC_CONSUMO.pdfs} labels={CC_MONTHS} /></Card>
        <Card title="Reportes exportados" icon="mdi-chart-box-outline"><div style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)", marginBottom: 8 }}>{metricDisplay(CC_USAGE_TOTALS.reports, compactNumber)}</div><BarChart data={CC_CONSUMO.reportes} labels={CC_MONTHS} /></Card>
        <Card title="Llamadas API" icon="mdi-api"><div style={{ font: "700 24px var(--font-display)", color: "var(--cc-fg)", marginBottom: 8 }}>{metricDisplay(CC_USAGE_TOTALS.apiCalls, compactNumber)}</div><BarChart data={CC_CONSUMO.api} labels={CC_MONTHS} alt /></Card>
      </div>

      <Card title="Consumo por cliente — IA y WhatsApp" icon="mdi-chart-bar-stacked" flush>
        {CC_CLIENTS.length === 0 && (
          <div style={{ padding: 18 }}>
            <EmptyState compact icon="mdi-domain-off" title="No existen instancias para consumo." description="El consumo por cliente se muestra solo para instancias reales del backend." />
          </div>
        )}
        {CC_CLIENTS.length > 0 && <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Cliente</th><th>Tokens IA</th><th>% del plan</th><th>Costo IA</th><th>Mensajes WA</th><th>Storage</th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => (
                <tr key={c.id}>
                  <td><div className="ent"><ClientAva c={c} /><div className="nm">{c.nombre}</div></div></td>
                  <td className="cc-mono">{fmtNum(c.iaTokens)}</td>
                  <td style={{ minWidth: 120 }}><div className="flex ac gap10"><div style={{ flex: 1 }}><Progress value={c.iaPct} /></div><span className="cc-mono" style={{ fontSize: 11.5 }}>{c.iaPct}%</span></div></td>
                  <td className="cc-mono">{fmtMoney(c.iaCosto)}</td>
                  <td className="cc-mono">{fmtNum(c.waMsgs)}</td>
                  <td className="cc-mono">{c.storage} GB</td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>}
      </Card>
    </div>
  );
}

/* ============ AUDITORÍA ============ */
function ScreenAuditoria() {
  const [filtro, setFiltro] = useState("todos");
  const tipos = [
    { id: "todos", label: "Todos", icon: "mdi-format-list-bulleted" },
    { id: "estado", label: "Estado operativo", icon: "mdi-toggle-switch-outline" },
    { id: "licencia", label: "Licencias", icon: "mdi-license" },
    { id: "feature", label: "Features", icon: "mdi-flag-variant-outline" },
    { id: "deploy", label: "Deploys", icon: "mdi-rocket-launch-outline" },
    { id: "backup", label: "Backups", icon: "mdi-backup-restore" },
    { id: "error", label: "Errores", icon: "mdi-alert-octagon-outline" },
    { id: "soporte", label: "Soporte", icon: "mdi-lifebuoy" },
  ];
  const rows = CC_AUDIT.filter(e => filtro === "todos" || e.tipo === filtro);
  return (
    <div className="cc-page fade-in">
      <PageHead title="Auditoría" sub="Registro cronológico global de todas las acciones sensibles sobre la plataforma y sus clientes."
        actions={<button className="cc-btn ghost sm" disabled title="Pendiente Fase 2"><i className="mdi mdi-download"></i>Exportar registro</button>} />
      <SectionNotice section="audit" empty={CC_AUDIT.length === 0} />
      <div className="flex ac wrap gap6" style={{ marginBottom: "var(--gap)" }}>
        {tipos.map(t => (
          <button key={t.id} className={`cc-btn ${filtro === t.id ? "primary" : "line"} sm`} onClick={() => setFiltro(t.id)}>
            <i className={`mdi ${t.icon}`}></i>{t.label}
          </button>
        ))}
      </div>
      <Card flush>
        <div style={{ padding: "20px 22px 4px" }}>
          <div className="cc-timeline">
            {rows.map((e, i) => (
              <div key={i} className="cc-tl-item">
                <div className="cc-tl-dot" style={{ background: e.cls === "acc" ? "var(--cc-accent-soft)" : `var(--st-${e.cls}-bg)`, color: e.cls === "acc" ? "var(--cc-accent)" : `var(--st-${e.cls})` }}><i className={`mdi ${e.icon}`}></i></div>
                <div className="cc-tl-body">
                  <p className="t flex ac gap10" style={{ display: "flex", flexWrap: "wrap" }}>{e.titulo}<span className="cc-tag" style={{ fontSize: 10.5 }}>{e.cliente}</span></p>
                  <p className="d">{e.desc}</p>
                  <div className="when"><span className="cc-tl-actor"><i className="mdi mdi-account-circle-outline" style={{ fontSize: 13 }}></i>{e.actor}</span>· {e.when}</div>
                </div>
              </div>
            ))}
            {rows.length === 0 && <p className="muted" style={{ padding: "20px 0" }}>No hay eventos de este tipo en el periodo.</p>}
          </div>
        </div>
      </Card>
    </div>
  );
}

/* MedForge Control Center — Feature Flags + Services panels & screens */

const ENV_CLS = { "Producción": "prod", "Beta": "beta", "Experimental": "maint" };

/* ---- Feature flags panel (reused in detail + standalone) ---- */
function FeatureFlagsPanel({ flags, setFlags, scope, clientId, onDataChanged }) {
  const onCount = Object.values(flags).filter(Boolean).length;
  if (CC_FEATURES.length === 0) {
    return (
      <React.Fragment>
        <SectionNotice section="details" empty />
        <EmptyState icon="mdi-flag-variant-off" title="No existen feature flags configurados." description="Los flags se renderizan desde el detalle real de instancia. No hay catálogo demo en staging ni producción." />
      </React.Fragment>
    );
  }
  return (
    <div className="fade-in">
      <div className="flex jb ac wrap gap10" style={{ marginBottom: 14 }}>
        <p className="muted" style={{ margin: 0, fontSize: 13 }}>
          <b style={{ color: "var(--cc-fg)" }}>{onCount}</b> de {CC_FEATURES.length} módulos activos{scope ? ` para ${scope}` : ""}.
          Cambiar un flag de riesgo alto requiere revisión del equipo de plataforma.
        </p>
        <div className="cc-seg">
          <button className="on">Todos</button>
          <button>Activos</button>
          <button>Riesgo alto</button>
        </div>
      </div>
      <Card flush>
        {CC_FEATURES.map(f => (
          <div key={f.id} className="cc-flag">
            <div>
              <div className="nm">
                <i className={`mdi ${f.icon}`} style={{ fontSize: 19, color: flags[f.id] ? "var(--cc-accent)" : "var(--cc-fg-3)" }}></i>
                {f.nombre}
              </div>
              <p className="desc">{f.desc}</p>
              <div className="meta">
                <span className={`cc-badge ${ENV_CLS[f.env]}`}>{f.env}</span>
                <span className="cc-tag">Riesgo: <RiskInline r={f.riesgo} /></span>
                <span className="cc-tag"><i className="mdi mdi-account-outline" style={{ fontSize: 13 }}></i>{f.resp}</span>
                <span className="muted" style={{ fontSize: 11.5 }}><i className="mdi mdi-clock-outline" style={{ fontSize: 12, verticalAlign: -2 }}></i> {f.mod}</span>
              </div>
            </div>
            <div className="ctrl">
              <Switch on={flags[f.id]} onClick={async () => { const next = !flags[f.id]; setFlags(s => ({ ...s, [f.id]: next })); if (clientId) { try { await updateInstanceFeature(clientId, f.id, next); if (onDataChanged) await onDataChanged(clientId); } catch (error) { setFlags(s => ({ ...s, [f.id]: !next })); window.alert(error.message || "No se pudo actualizar el feature flag."); } } }} />
              <span style={{ font: "600 11px var(--font-mono)", color: flags[f.id] ? "var(--st-prod)" : "var(--cc-fg-3)" }}>{flags[f.id] ? "ON" : "OFF"}</span>
            </div>
          </div>
        ))}
      </Card>
    </div>
  );
}
function RiskInline({ r }) {
  const col = { bajo: "var(--st-prod)", medio: "var(--st-maint)", alto: "var(--st-susp)", crítico: "var(--st-susp)" }[r];
  return <b style={{ color: col, textTransform: "capitalize" }}>{r}</b>;
}

/* ---- Services panel (per client) ---- */
function ServicesPanel({ clientId }) {
  const svc = CC_SERVICE_STATE[clientId] || {};
  if (CC_SERVICE_DEFS.length === 0) {
    return (
      <React.Fragment>
        <SectionNotice section="services" empty />
        <EmptyState icon="mdi-server-network-off" title="Aún no existen datos de monitoreo." description="Los servicios se renderizan desde /v2/control-center/services y sus snapshots reales." />
      </React.Fragment>
    );
  }
  return (
    <div className="fade-in cc-grid g2">
      {CC_SERVICE_DEFS.map(d => {
        const state = SVC_KEYMAP[svc[d.id] || 'none'];
        const m = CC_SVC_META[state];
        const detail = CC_SERVICE_DETAILS[`${clientId}:${d.id}`];
        const serviceText = detail?.message
          || (detail?.latency_ms != null || detail?.uptime_pct != null
            ? `${detail?.latency_ms != null ? `Latencia ${detail.latency_ms}ms` : 'Latencia pendiente'} · ${detail?.uptime_pct != null ? `uptime ${detail.uptime_pct}%` : 'uptime pendiente'}`
            : 'Pendiente de healthcheck real');
        return (
          <div key={d.id} className="cc-card" style={{ padding: "15px 18px", display: "flex", alignItems: "center", gap: 14 }}>
            <div style={{ width: 42, height: 42, borderRadius: 11, background: `${m.color}1f`, color: m.color, display: "grid", placeItems: "center", fontSize: 21, flexShrink: 0 }}>
              <i className={`mdi ${d.icon}`}></i>
            </div>
            <div style={{ flex: 1, minWidth: 0 }}>
              <div style={{ font: "600 14px var(--font-body)", color: "var(--cc-fg)" }}>{d.nombre}</div>
              <div className="muted" style={{ fontSize: 11.5, fontFamily: "var(--font-mono)" }}>
                {serviceText}
              </div>
            </div>
            <ServicePill state={state} />
          </div>
        );
      })}
    </div>
  );
}

/* ---- Standalone: Feature Flags screen (with client selector) ---- */
function ScreenFeatures({ selectedClient, onPickClient, onDataChanged }) {
  const c = CC_CLIENTS.find(x => x.id === selectedClient) || CC_CLIENTS[0];
  const [flags, setFlags] = useState(() => {
    const byKey = new Map((c?.features || []).map(feature => [feature.key, Boolean(feature.enabled)]));
    const o = {}; CC_FEATURES.forEach(f => o[f.id] = byKey.has(f.id) ? byKey.get(f.id) : f.on); return o;
  });
  useEffect(() => {
    const byKey = new Map((c?.features || []).map(feature => [feature.key, Boolean(feature.enabled)]));
    const o = {}; CC_FEATURES.forEach(f => o[f.id] = byKey.has(f.id) ? byKey.get(f.id) : f.on); setFlags(o);
  }, [c?.id]);

  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Feature Flags"
        sub="Activa o desactiva módulos por cliente. Los cambios se propagan al ambiente seleccionado y quedan auditados."
        actions={c ? <ClientPicker c={c} onPick={onPickClient} /> : null}
      />
      {!c ? <EmptyState icon="mdi-domain-off" title="No existen instancias para configurar." description="Crea o carga instancias reales desde el backend antes de administrar feature flags." />
        : <FeatureFlagsPanel flags={flags} setFlags={setFlags} scope={c?.nombre} clientId={c?.id} onDataChanged={onDataChanged} />}
    </div>
  );
}

/* ---- Standalone: Servicios screen ---- */
function ScreenServicios({ selectedClient, onPickClient }) {
  const [scope, setScope] = useState("matriz");
  const c = CC_CLIENTS.find(x => x.id === selectedClient) || CC_CLIENTS[0];
  return (
    <div className="cc-page fade-in">
      <PageHead
        title="Servicios"
        sub="Salud de la infraestructura por cliente. Monitorea aplicación, base de datos, integraciones y procesos."
        actions={<div className="cc-seg">
          <button className={scope === "matriz" ? "on" : ""} onClick={() => setScope("matriz")}><i className="mdi mdi-grid"></i>Matriz global</button>
          <button className={scope === "cliente" ? "on" : ""} onClick={() => setScope("cliente")}><i className="mdi mdi-domain"></i>Por cliente</button>
        </div>}
      />
      {scope === "cliente" && c && <div style={{ marginBottom: "var(--gap)" }}><ClientPicker c={c} onPick={onPickClient} /></div>}
      {scope === "cliente"
        ? (c ? <ServicesPanel clientId={c.id} /> : <EmptyState icon="mdi-domain-off" title="No existen instancias para monitorear." description="No hay servicios por cliente porque el backend no devolvió instancias." />)
        : <ServiceMatrix />}
    </div>
  );
}

/* ---- Global service matrix (clients × services) ---- */
function ServiceMatrix() {
  if (CC_SERVICE_DEFS.length === 0) {
    return (
      <React.Fragment>
        <SectionNotice section="services" empty />
        <EmptyState icon="mdi-server-network-off" title="Aún no existen datos de monitoreo." description="La matriz global se llena únicamente con snapshots reales del endpoint /services." />
      </React.Fragment>
    );
  }
  return (
    <Card flush>
      <div className="cc-tblwrap">
        <table className="cc-tbl">
          <thead><tr>
            <th>Servicio</th>
            {CC_CLIENTS.map(c => <th key={c.id} style={{ textAlign: "center" }}>{c.inicial}</th>)}
            <th style={{ textAlign: "center" }}>Salud</th>
          </tr></thead>
          <tbody>
            {CC_SERVICE_DEFS.map(d => {
              const states = CC_CLIENTS.map(c => SVC_KEYMAP[CC_SERVICE_STATE[c.id][d.id]]);
              const okCount = states.filter(s => s === "operativo").length;
              return (
                <tr key={d.id}>
                  <td><div className="flex ac gap10"><i className={`mdi ${d.icon}`} style={{ fontSize: 18, color: "var(--cc-fg-3)" }}></i><span style={{ fontWeight: 600, color: "var(--cc-fg)" }}>{d.nombre}</span></div></td>
                  {CC_CLIENTS.map((c, i) => {
                    const m = CC_SVC_META[states[i]];
                    return <td key={c.id} style={{ textAlign: "center" }} title={`${c.nombre}: ${m.label}`}>
                      <span className="svc-dot" style={{ background: m.color, width: 11, height: 11, animation: states[i] === "operativo" ? "ccPulse 2.4s infinite" : "none" }}></span>
                    </td>;
                  })}
                  <td style={{ textAlign: "center" }}><span className="cc-mono" style={{ fontSize: 12 }}>{okCount}/{CC_CLIENTS.length}</span></td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
      <div className="flex ac gap14 wrap" style={{ padding: "13px 18px", borderTop: "1px solid var(--cc-border)" }}>
        {Object.entries(CC_SVC_META).map(([k, m]) => (
          <span key={k} className="flex ac gap6" style={{ fontSize: 12, color: "var(--cc-fg-3)" }}><span className="svc-dot" style={{ background: m.color }}></span>{m.label}</span>
        ))}
      </div>
    </Card>
  );
}

/* ---- Client picker (compact) ---- */
function ClientPicker({ c, onPick }) {
  const [open, setOpen] = useState(false);
  return (
    <div className="cc-clientsel">
      <button onClick={() => setOpen(o => !o)}>
        <span className="ava" style={{ background: c.color }}>{c.inicial}</span>{c.nombre}<i className="mdi mdi-chevron-down chev"></i>
      </button>
      {open && (
        <div className="cc-menu" style={{ right: 0, left: "auto" }} onMouseLeave={() => setOpen(false)}>
          <div className="head">Seleccionar cliente</div>
          {CC_CLIENTS.map(x => (
            <div key={x.id} className={`item ${x.id === c.id ? "on" : ""}`} onClick={() => { onPick(x.id); setOpen(false); }}>
              <span className="ava" style={{ background: x.color }}>{x.inicial}</span>
              <div><div className="nm">{x.nombre}</div><div className="dm">{x.dominio}</div></div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}


const CC_NAV = [
  { grp: "Plataforma", items: [
    { id: "overview",  icon: "mdi-view-dashboard-outline", label: "Overview" },
    { id: "clientes",  icon: "mdi-domain",                 label: "Clientes", pill: "", pillMut: true },
    { id: "licencias", icon: "mdi-license",                label: "Licencias y Planes" },
  ]},
  { grp: "Operación", items: [
    { id: "estado",    icon: "mdi-toggle-switch-outline",  label: "Estado Operativo", pill: "" },
    { id: "features",  icon: "mdi-flag-variant-outline",   label: "Feature Flags" },
    { id: "servicios", icon: "mdi-server-network",         label: "Servicios", pill: "" },
  ]},
  { grp: "Entrega", items: [
    { id: "deploys",   icon: "mdi-rocket-launch-outline",  label: "Deploys y Versiones" },
    { id: "consumo",   icon: "mdi-chart-areaspline",       label: "Consumo" },
    { id: "auditoria", icon: "mdi-clipboard-text-clock-outline", label: "Auditoría" },
  ]},
];

const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
const stateApiToUi = { production: "produccion", maintenance: "mantenimiento", readonly: "lectura", suspended: "suspendido" };
const stateUiToApi = { produccion: "production", mantenimiento: "maintenance", lectura: "readonly", suspendido: "suspended" };
const serviceApiToUi = { operational: "operativo", healthy: "operativo", ok: "operativo", degraded: "degradado", error: "error", paused: "pausado", suspended: "pausado", no_config: "no_config", unknown: "no_config" };
const serviceUiToCompact = { operativo: "ok", degradado: "deg", error: "err", pausado: "pause", no_config: "none" };
const featureIcons = {
  crm: "mdi-account-heart-outline",
  whatsapp: "mdi-whatsapp",
  protocolos: "mdi-file-document-edit-outline",
  dashboard: "mdi-view-dashboard-variant-outline",
  farmacia: "mdi-pill",
  ia: "mdi-auto-fix",
  iess: "mdi-cash-register",
  reportes: "mdi-chart-bar",
  sigcenter: "mdi-sync",
  movil: "mdi-cellphone",
};

async function ccRequest(path, options = {}) {
  const response = await fetch(path, {
    ...options,
    headers: {
      Accept: 'application/json',
      'Content-Type': 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
      ...(options.headers || {}),
    },
  });
  const payload = await response.json().catch(() => ({}));
  if (!response.ok) throw new Error(payload.message || payload.error || 'No se pudo completar la solicitud.');
  return payload.data;
}

function normalizeCollection(payload) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

function normalizeWrappedResponse(payload, key) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.[key])) return payload[key];
  if (Array.isArray(payload?.data)) return payload.data;
  if (Array.isArray(payload?.data?.[key])) return payload.data[key];
  return [];
}

function normalizeServicesResponse(payload) {
  return normalizeWrappedResponse(payload, "services");
}

function normalizePlansResponse(payload) {
  return normalizeWrappedResponse(payload, "plans");
}

function normalizeDeploymentsResponse(payload) {
  return normalizeWrappedResponse(payload, "deployments");
}

function normalizeUsageResponse(payload) {
  return normalizeWrappedResponse(payload, "usage");
}

function normalizeAuditResponse(payload) {
  return normalizeWrappedResponse(payload, "audit");
}

async function loadSection(key, path, normalizer = (payload) => payload) {
  try {
    const data = normalizer(await ccRequest(path));
    CC_BACKEND_STATUS[key] = "ready";
    delete CC_BACKEND_ERRORS[key];
    return data;
  } catch (error) {
    CC_BACKEND_STATUS[key] = "error";
    CC_BACKEND_ERRORS[key] = error.message || "No se pudo cargar esta seccion.";
    return Array.isArray(normalizer({})) ? [] : null;
  }
}

function firstMetric(rows, instanceId, names, fallback = 0) {
  const row = rows.find(r => Number(r.instance_id) === Number(instanceId) && names.includes(r.metric));
  return row ? Number(row.value || 0) : fallback;
}

function usageTotal(rows, names) {
  return rows
    .filter(row => names.includes(row.metric))
    .reduce((total, row) => total + Number(row.value || 0), 0);
}

function usageCost(rows, names) {
  return rows
    .filter(row => names.includes(row.metric))
    .reduce((total, row) => total + Number(row.cost || 0), 0);
}

function metricDisplay(value, formatter = fmtNum, empty = "Pendiente") {
  return Number(value || 0) > 0 ? formatter(value) : empty;
}

function compactNumber(value) {
  const n = Number(value || 0);
  if (n >= 1000000) return (n / 1000000).toFixed(n >= 10000000 ? 0 : 1) + "M";
  if (n >= 1000) return (n / 1000).toFixed(n >= 10000 ? 0 : 1) + "K";
  return String(Math.round(n));
}

function dateShort(value, fallback = "—") {
  if (!value) return fallback;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleDateString('es-EC', { day: '2-digit', month: 'short', year: 'numeric' });
}

function dateTimeShort(value, fallback = "—") {
  if (!value) return fallback;
  const d = new Date(value);
  if (Number.isNaN(d.getTime())) return String(value);
  return d.toLocaleString('es-EC', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' });
}

function paymentToUi(status) {
  if (status === 'past_due' || status === 'overdue' || status === 'suspended') return ['vencido', 'Pago vencido'];
  if (status === 'trial') return ['trial', 'Trial'];
  return ['ok', 'Al día'];
}

function clientFromInstance(instance, org, usageRows, deployments) {
  const uiState = stateApiToUi[instance.status] || 'produccion';
  const [pago, pagoLabel] = paymentToUi(instance.payment_status);
  const deploy = deployments.find(d => Number(d.instance_id) === Number(instance.id));
  const tokens = firstMetric(usageRows, instance.id, ['ai_tokens', 'ia_tokens'], 0);
  const wa = firstMetric(usageRows, instance.id, ['whatsapp_messages', 'wa_messages'], 0);
  const storage = firstMetric(usageRows, instance.id, ['storage_gb', 'storage'], 0);
  const api = firstMetric(usageRows, instance.id, ['api_calls'], 0);
  const pdfs = firstMetric(usageRows, instance.id, ['pdfs', 'pdf_documents'], 0);
  const reportes = firstMetric(usageRows, instance.id, ['reports', 'reportes'], 0);
  return {
    id: String(instance.id),
    instanceId: instance.id,
    organizationId: instance.organization_id,
    slug: instance.slug,
    nombre: instance.name || org?.name || 'Instancia',
    razon: org?.legal_name || org?.commercial_name || org?.name || instance.organization_name || '—',
    color: instance.organization_color || org?.color || '#5156be',
    inicial: instance.organization_initials || org?.initials || (instance.name || 'MF').slice(0, 2).toUpperCase(),
    dominio: instance.domain || instance.admin_url || '—',
    ruc: org?.ruc || '—',
    plan: instance.plan_name || org?.plan_name || 'Starter',
    estado: uiState,
    ciudad: instance.organization_city || org?.city || '—',
    usuarios: Math.round(firstMetric(usageRows, instance.id, ['active_users', 'users'], 0)),
    usuariosMax: 300,
    ultimaActividad: dateTimeShort(instance.last_activity_at, 'sin actividad reciente'),
    version: instance.current_version || '—',
    canal: instance.release_channel || deploy?.channel || 'Stable',
    versionDisp: deploy?.available_version || instance.current_version || '—',
    pago,
    pagoLabel,
    inicio: '—',
    vence: '—',
    ultimoDeploy: dateTimeShort(deploy?.deployed_at, '—'),
    ultimoBackup: dateTimeShort(instance.last_backup_at, '—'),
    tickets: uiState === 'suspendido' ? 12 : uiState === 'lectura' ? 8 : uiState === 'mantenimiento' ? 5 : 2,
    riesgo: uiState === 'suspendido' ? 'crítico' : uiState === 'lectura' ? 'alto' : uiState === 'mantenimiento' ? 'medio' : 'bajo',
    contactoAdmin: { n: org?.name || instance.organization_name || 'Equipo cliente', c: '—', t: '—' },
    contactoTec: { n: 'Equipo MedForge', c: 'soporte@medforge.app', t: '—' },
    placeholderFields: ['inicio', 'vence', 'tickets', 'contactos'].concat(org?.ruc ? [] : ['ruc']).concat(instance.last_backup_at ? [] : ['ultimoBackup']),
    dataQuality: instance.data_quality || org?.data_quality || { source: 'pending', source_label: 'Pendiente de integracion' },
    iaTokens: tokens,
    iaCosto: Math.round(tokens / 1000000 * 127),
    iaPct: Math.min(100, Math.round(tokens ? tokens / 80000 : 0)),
    waMsgs: wa,
    waConv: Math.round(wa / 6),
    storage: Math.round(storage),
    storageMax: 500,
    pdfs,
    reportes,
    apiCalls: api,
  };
}

function hydrateControlCenterData(payload) {
  const overview = payload.overview || {};
  const orgs = payload.organizations?.length ? payload.organizations : normalizeCollection(overview.organizations);
  const instances = payload.instances?.length ? payload.instances : normalizeCollection(overview.instances);
  const deployments = payload.deployments || [];
  const usageRows = payload.usage || [];
  const auditRows = payload.audit?.length ? payload.audit : normalizeCollection(overview.audit);
  CC_OVERVIEW_SUMMARY = overview.summary || {};
  CC_USAGE_TOTALS = {
    aiTokens: usageTotal(usageRows, ['ai_tokens', 'ia_tokens']),
    aiCost: usageCost(usageRows, ['ai_tokens', 'ia_tokens']),
    whatsappMessages: usageTotal(usageRows, ['whatsapp_messages', 'wa_messages']),
    storageGb: usageTotal(usageRows, ['storage_gb', 'storage']),
    pdfs: usageTotal(usageRows, ['pdfs', 'pdf_documents']),
    reports: usageTotal(usageRows, ['reports', 'reportes']),
    apiCalls: usageTotal(usageRows, ['api_calls']),
  };
  const orgById = new Map(orgs.map(o => [Number(o.id), o]));
  const detailByInstance = new Map((payload.details || []).map(detail => [Number(detail.instance?.id), detail]));
  CC_CLIENTS = instances.map(instance => {
    const client = clientFromInstance(instance, orgById.get(Number(instance.organization_id)), usageRows, deployments);
    const detail = detailByInstance.get(Number(instance.id));
    client.features = detail?.features || [];
    client.services = detail?.services || [];
    return client;
  });

  const serviceDefs = new Map();
  CC_SERVICE_STATE = {};
  CC_SERVICE_DETAILS = {};
  for (const c of CC_CLIENTS) CC_SERVICE_STATE[c.id] = {};
  for (const service of (payload.services || [])) {
    serviceDefs.set(service.key, { id: service.key, nombre: service.name, icon: service.icon || 'mdi-server', dataQuality: service.data_quality });
    const client = CC_CLIENTS.find(c => Number(c.instanceId) === Number(service.instance_id));
    if (client) {
      const uiState = serviceApiToUi[service.state] || service.state;
      CC_SERVICE_STATE[client.id][service.key] = serviceUiToCompact[uiState] || "none";
      CC_SERVICE_DETAILS[`${client.id}:${service.key}`] = service;
    }
  }
  CC_SERVICE_DEFS = serviceDefs.size ? Array.from(serviceDefs.values()) : [];
  for (const c of CC_CLIENTS) {
    for (const svc of CC_SERVICE_DEFS) CC_SERVICE_STATE[c.id][svc.id] ||= 'none';
  }

  const firstFeatureSet = CC_CLIENTS.find(c => c.features?.length)?.features || [];
  if (firstFeatureSet.length) {
    CC_FEATURES = firstFeatureSet.map(feature => ({
      id: feature.key,
      key: feature.key,
      nombre: feature.name,
      icon: featureIcons[feature.key] || featureIcons[(feature.module || "").toLowerCase()] || "mdi-flag-variant-outline",
      env: "Producción",
      riesgo: feature.risk_level || "bajo",
      on: Boolean(feature.enabled),
      mod: dateShort(feature.updated_at, "MVP"),
      resp: feature.requires_review ? "Plataforma" : "Operaciones",
      desc: feature.description || feature.module || "Feature flag de instancia.",
    }));
  } else {
    CC_FEATURES = [];
  }

  CC_PLAN_CARDS = (payload.plans?.length ? payload.plans : []).map(plan => ({
    nombre: plan.name || plan.nombre,
    precio: plan.monthly_price ?? plan.precio ?? null,
    color: CC_PLANS[plan.name]?.color || '#7b80ff',
    usuarios: String(plan.user_limit ?? plan.usuarios ?? 'A medida'),
    modulos: Array.isArray(plan.modules) ? plan.modules.join(' + ') : (plan.modulos || 'Módulos contratados'),
    ia: plan.ai_token_limit ? `${fmtNum(Number(plan.ai_token_limit))} tokens` : (plan.ia || 'A medida'),
    wa: plan.whatsapp_message_limit ? `${fmtNum(Number(plan.whatsapp_message_limit))} msj` : (plan.wa || 'A medida'),
    storage: plan.storage_gb_limit ? `${plan.storage_gb_limit} GB` : (plan.storage || 'A medida'),
    soporte: plan.support_level || plan.soporte || 'Soporte',
    sla: plan.sla_target ? `${plan.sla_target}%` : (plan.sla || '—'),
    clientes: CC_CLIENTS.filter(c => c.plan === (plan.name || plan.nombre)).length,
    destacado: (plan.name || plan.nombre) === 'Professional',
    dataQuality: plan.data_quality,
  }));

  CC_RELEASES = deployments.slice(0, 8).map(d => ({
    v: d.version,
    canal: d.channel || 'Stable',
    fecha: dateShort(d.deployed_at || d.scheduled_at),
    resp: d.responsible || 'Plataforma',
    titulo: d.release_title || d.status || 'Deploy registrado',
    estado: d.status === 'update_available' ? 'Disponible' : 'Instalado',
    cls: (d.channel || '').toLowerCase().includes('beta') ? 'beta' : 'prod',
    dataQuality: d.data_quality,
  }));

  CC_AUDIT = auditRows.map(entry => ({
    tipo: entry.event_type || 'estado',
    icon: entry.event_type === 'feature' ? 'mdi-toggle-switch-outline' : entry.event_type === 'deploy' ? 'mdi-rocket-launch-outline' : 'mdi-clipboard-text-clock-outline',
    cls: entry.event_type === 'feature' ? 'prod' : entry.event_type === 'state' ? 'read' : 'acc',
    titulo: entry.action || 'Evento auditado',
    desc: `${entry.target_type || 'registro'} ${entry.target_id || ''}`.trim(),
    actor: entry.actor_name || 'Sistema',
    cliente: entry.instance_name || entry.organization_name || 'Global',
    when: dateTimeShort(entry.created_at),
  }));

  CC_STATE_HISTORY = {};
  for (const entry of auditRows) {
    if (entry.event_type !== 'state' || !entry.instance_id) continue;
    const key = String(entry.instance_id);
    const state = stateApiToUi[entry.after?.state] || 'produccion';
    CC_STATE_HISTORY[key] ||= [];
    CC_STATE_HISTORY[key].push({ estado: state, actor: entry.actor_name || 'Sistema', motivo: entry.after?.reason || entry.action, when: dateTimeShort(entry.created_at) });
  }
}

function resetControlCenterData() {
  CC_CLIENTS = [];
  CC_FEATURES = [];
  CC_SERVICE_DEFS = [];
  CC_SERVICE_STATE = {};
  CC_PLAN_CARDS = [];
  CC_RELEASES = [];
  CC_AUDIT = [];
  CC_STATE_HISTORY = {};
  CC_OVERVIEW_SUMMARY = {};
  CC_USAGE_TOTALS = {};
  CC_SERVICE_DETAILS = {};
}

async function loadControlCenterData() {
  CC_BACKEND_STATUS = {
    overview: "loading",
    organizations: "loading",
    instances: "loading",
    details: "loading",
    services: "loading",
    plans: "loading",
    deployments: "loading",
    usage: "loading",
    audit: "loading",
  };
  const [overview, organizations, instances, services, plans, deployments, usage, audit] = await Promise.all([
    loadSection('overview', '/v2/control-center/overview', (payload) => payload || {}),
    loadSection('organizations', '/v2/control-center/organizations?per_page=100', normalizeCollection),
    loadSection('instances', '/v2/control-center/instances?per_page=100', normalizeCollection),
    loadSection('services', '/v2/control-center/services', normalizeServicesResponse),
    loadSection('plans', '/v2/control-center/plans', normalizePlansResponse),
    loadSection('deployments', '/v2/control-center/deployments', normalizeDeploymentsResponse),
    loadSection('usage', '/v2/control-center/usage', normalizeUsageResponse),
    loadSection('audit', '/v2/control-center/audit', normalizeAuditResponse),
  ]);
  const detailResults = await Promise.allSettled((instances || []).map(instance => ccRequest(`/v2/control-center/instances/${instance.id}`)));
  const details = detailResults.filter(result => result.status === 'fulfilled').map(result => result.value);
  CC_BACKEND_STATUS.details = detailResults.some(result => result.status === 'rejected') ? 'error' : 'ready';
  if (detailResults.some(result => result.status === 'rejected')) {
    CC_BACKEND_ERRORS.details = 'Algunos detalles de instancia no se pudieron cargar.';
  } else {
    delete CC_BACKEND_ERRORS.details;
  }
  try {
    hydrateControlCenterData({ overview, organizations, instances, services, plans, deployments, usage, audit, details });
  } catch (error) {
    resetControlCenterData();
    throw error;
  }
}

async function changeInstanceState(clientId, uiState, reason) {
  const client = CC_CLIENTS.find(c => c.id === String(clientId));
  if (!client) throw new Error('Instancia no encontrada.');
  const state = stateUiToApi[uiState];
  await ccRequest(`/v2/control-center/instances/${client.instanceId}/state`, {
    method: 'POST',
    body: JSON.stringify({ state, reason, confirm: state === 'production' ? undefined : state }),
  });
}

async function updateInstanceFeature(clientId, featureId, enabled) {
  const client = CC_CLIENTS.find(c => c.id === String(clientId));
  const feature = CC_FEATURES.find(f => f.id === featureId);
  if (!client || !feature) throw new Error('Feature flag no encontrado.');
  await ccRequest(`/v2/control-center/instances/${client.instanceId}/features`, {
    method: 'POST',
    body: JSON.stringify({ features: [{ key: feature.key || feature.id, enabled, reason: 'Cambio desde Control Center' }] }),
  });
}

function EnvSelector({ env, setEnv }) {
  const opts = [{ id: "prod", label: "Producción", cls: "prod" }, { id: "beta", label: "Beta", cls: "beta" }, { id: "exp", label: "Experimental", cls: "exp" }];
  return (
    <div className="cc-env" title="Ambiente activo">
      {opts.map(o => (
        <button key={o.id} className={`${o.cls} ${env === o.id ? "on " + o.cls : ""}`} onClick={() => setEnv(o.id)}>
          <span className="led"></span>{o.label}
        </button>
      ))}
    </div>
  );
}

function GlobalClientSelector({ selected, onPick }) {
  const [open, setOpen] = useState(false);
  const c = CC_CLIENTS.find(x => x.id === selected);
  return (
    <div className="cc-clientsel">
      <button onClick={() => setOpen(o => !o)}>
        {c ? <React.Fragment><span className="ava" style={{ background: c.color }}>{c.inicial}</span>{c.nombre}</React.Fragment>
           : <React.Fragment><span className="ava" style={{ background: "linear-gradient(135deg,#1ECCDD,#7C4DFF)" }}><i className="mdi mdi-earth" style={{ fontSize: 14 }}></i></span>Todos los clientes</React.Fragment>}
        <i className="mdi mdi-chevron-down chev"></i>
      </button>
      {open && (
        <div className="cc-menu" onMouseLeave={() => setOpen(false)}>
          <div className="head">Cliente global</div>
          <div className={`item ${!selected ? "on" : ""}`} onClick={() => { onPick(null); setOpen(false); }}>
            <span className="ava" style={{ background: "linear-gradient(135deg,#1ECCDD,#7C4DFF)" }}><i className="mdi mdi-earth" style={{ fontSize: 15 }}></i></span>
            <div><div className="nm">Todos los clientes</div><div className="dm">vista consolidada</div></div>
          </div>
          {CC_CLIENTS.map(x => (
            <div key={x.id} className={`item ${selected === x.id ? "on" : ""}`} onClick={() => { onPick(x.id); setOpen(false); }}>
              <span className="ava" style={{ background: x.color }}>{x.inicial}</span>
              <div><div className="nm">{x.nombre}</div><div className="dm">{x.dominio}</div></div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function App() {
  const [route, setRoute] = useState("overview");
  const [detailId, setDetailId] = useState(null);
  const [selectedClient, setSelectedClient] = useState(null);
  const [env, setEnv] = useState("prod");
  const [collapsed, setCollapsed] = useState(false);
  const [theme, setTheme] = useState("dark");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [showCreateOrganization, setShowCreateOrganization] = useState(false);
  const [, bump] = useState(0);

  const reload = async (keepDetailId = detailId) => {
    setError("");
    await loadControlCenterData();
    if (keepDetailId && !CC_CLIENTS.some(c => c.id === String(keepDetailId))) setDetailId(null);
    bump(v => v + 1);
  };

  useEffect(() => {
    let mounted = true;
    (async () => {
      try { await loadControlCenterData(); }
      catch (err) { if (mounted) setError(err.message || 'No se pudo cargar Control Center.'); }
      finally { if (mounted) setLoading(false); }
    })();
    return () => { mounted = false; };
  }, []);

  useEffect(() => {
    document.documentElement.setAttribute("data-cc-theme", theme);
    document.documentElement.setAttribute("data-cc-density", "comfortable");
    document.documentElement.setAttribute("data-cc-collapsed", String(collapsed));
  }, [theme, collapsed]);

  const openClient = (id) => { setDetailId(String(id)); window.scrollTo(0, 0); };
  const go = (r) => { setRoute(r); setDetailId(null); };
  const openCreateOrganization = () => setShowCreateOrganization(true);
  CC_NAV[0].items[1].pill = String(CC_CLIENTS.length || '');
  CC_NAV[1].items[0].pill = String(CC_CLIENTS.filter(c => c.estado !== 'produccion').length || '');
  CC_NAV[1].items[2].pill = String(Object.values(CC_SERVICE_STATE).flatMap(x => Object.values(x)).filter(x => ['deg','err'].includes(x)).length || '');

  let content;
  if (loading) {
    content = <div className="cc-page fade-in"><PageHead title="Control Center" sub="Cargando datos operativos…" /><Card><div className="muted">Preparando consola.</div></Card></div>;
  } else if (detailId) {
    content = <ClientDetail clientId={detailId} onBack={() => setDetailId(null)} onNav={go} onDataChanged={reload} />;
  } else {
    content = {
      overview:  <ScreenOverview onOpenClient={openClient} onNav={go} env={env} onCreateOrganization={openCreateOrganization} />,
      clientes:  <ScreenClientes onOpenClient={openClient} onCreateOrganization={openCreateOrganization} />,
      licencias: <ScreenLicencias />,
      estado:    <ScreenEstadoGlobal onOpenClient={openClient} onCreateOrganization={openCreateOrganization} />,
      features:  <ScreenFeatures selectedClient={selectedClient || CC_CLIENTS[0]?.id} onPickClient={setSelectedClient} onDataChanged={reload} />,
      servicios: <ScreenServicios selectedClient={selectedClient || CC_CLIENTS[0]?.id} onPickClient={setSelectedClient} />,
      deploys:   <ScreenDeploys />,
      consumo:   <ScreenConsumo />,
      auditoria: <ScreenAuditoria />,
    }[route];
  }

  return (
    <div className="cc-app">
      <div className="cc-brand">
        <div className="mark"><i className="mdi mdi-lightning-bolt"></i></div>
        <div className="txt"><b>MedForge</b><span>Control Center</span></div>
      </div>

      <header className="cc-top">
        <button className="cc-iconbtn" onClick={() => setCollapsed(v => !v)} title="Colapsar menú"><i className="mdi mdi-menu"></i></button>
        <EnvSelector env={env} setEnv={setEnv} />
        <GlobalClientSelector selected={selectedClient} onPick={setSelectedClient} />
        <div className="cc-search">
          <i className="mdi mdi-magnify"></i>
          <input placeholder="Buscar cliente, dominio, versión…" />
          <kbd>⌘K</kbd>
        </div>
        <div style={{ flex: 1 }}></div>
        <button className="cc-iconbtn" title="Actualizar" onClick={() => reload()}><i className="mdi mdi-refresh"></i></button>
        <button className="cc-iconbtn" title="Cambiar tema" onClick={() => setTheme(theme === "dark" ? "light" : "dark")}>
          <i className={`mdi ${theme === "dark" ? "mdi-weather-night" : "mdi-white-balance-sunny"}`}></i>
        </button>
        <button className="cc-iconbtn" title="Notificaciones"><i className="mdi mdi-bell-outline"></i><span className="dot"></span></button>
        <div className="cc-userchip" title="Equipo MedForge">
          <div style={{ textAlign: "right" }}><div className="nm">Equipo MedForge</div><div className="rl">Operaciones</div></div>
          <div className="ava">MF</div>
        </div>
      </header>

      <nav className="cc-nav">
        {CC_NAV.map(sec => (
          <React.Fragment key={sec.grp}>
            <div className="grp">{sec.grp}</div>
            {sec.items.map(it => (
              <a key={it.id} className={(route === it.id && !detailId) ? "on" : ""} onClick={() => go(it.id)} title={it.label}>
                <i className={`mdi ${it.icon}`}></i>
                <span className="lbl">{it.label}</span>
                {it.pill && <span className={`pill ${it.pillMut ? "mut" : ""}`}>{it.pill}</span>}
              </a>
            ))}
          </React.Fragment>
        ))}
        <div className="spacer"></div>
        <div className="railcard">
          <b>Estado de la plataforma</b>
          <p>{CC_CLIENTS.filter(c => c.estado === 'produccion').length} de {CC_CLIENTS.length} clientes operativos.</p>
          <button className="cc-btn primary sm" style={{ width: "100%", justifyContent: "center" }} onClick={() => go("servicios")}>Ver estado</button>
        </div>
      </nav>

      <main className="cc-main">
        {error && <div className="cc-alert danger" style={{ marginBottom: 18 }}><i className="mdi mdi-alert-circle-outline"></i><div><p className="t">No se pudo cargar la consola</p><p className="d">{error}</p></div></div>}
        {content}
      </main>
      {showCreateOrganization && <CreateOrganizationPlaceholder onClose={() => setShowCreateOrganization(false)} />}
    </div>
  );
}

function ScreenEstadoGlobal({ onOpenClient, onCreateOrganization }) {
  return (
    <div className="cc-page fade-in">
      <PageHead title="Estado Operativo" sub="Modo de operación de cada cliente. Cambia a Producción, Mantenimiento, Solo lectura o Suspendido desde la ficha del cliente." />
      <SectionNotice section="instances" empty={CC_CLIENTS.length === 0} />
      {CC_CLIENTS.length === 0 && (
        <EmptyState
          icon="mdi-toggle-switch-off-outline"
          title="No existen instancias con estado operativo."
          description="El estado operativo vive por instancia y solo se muestra cuando /v2/control-center/instances devuelve registros."
          actionLabel="Crear organización"
          onAction={onCreateOrganization}
        />
      )}
      <div className="cc-grid g4" style={{ marginBottom: "var(--gap)" }}>
        {Object.values(CC_STATES).map(s => {
          const n = CC_CLIENTS.filter(c => c.estado === s.key).length;
          return (
            <div key={s.key} className="cc-kpi fade-in">
              <div className="top"><div className="lbl">{s.label}</div><div className="tile" style={{ background: `var(--st-${s.cls}-bg)`, color: `var(--st-${s.cls})` }}><i className={`mdi ${s.icon}`}></i></div></div>
              <div className="val">{n}</div>
              <div className="foot"><span className="muted">{n === 1 ? "cliente" : "clientes"}</span></div>
            </div>
          );
        })}
      </div>
      {CC_CLIENTS.length > 0 && <Card flush>
        <div className="cc-tblwrap">
          <table className="cc-tbl">
            <thead><tr><th>Empresa</th><th>Estado operativo</th><th>Impacto</th><th>Usuarios</th><th></th></tr></thead>
            <tbody>
              {CC_CLIENTS.map(c => {
                const s = CC_STATES[c.estado];
                return (
                  <tr key={c.id} className="clickable" onClick={() => onOpenClient(c.id)}>
                    <td><div className="ent"><ClientAva c={c} /><div><div className="nm">{c.nombre}</div><div className="dm">{c.dominio}</div></div></div></td>
                    <td><StateBadge estado={c.estado} /></td>
                    <td className="muted" style={{ fontSize: 12.5, maxWidth: 340 }}>{s.impact.split(".")[0]}.</td>
                    <td><span className="cc-mono">{c.usuarios}</span></td>
                    <td style={{ textAlign: "right" }}><button className="cc-btn line sm" onClick={(e) => { e.stopPropagation(); onOpenClient(c.id); }}><i className="mdi mdi-swap-horizontal"></i>Gestionar</button></td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>}
    </div>
  );
}


const root = document.getElementById('control-center-root');
if (root) createRoot(root).render(<App />);
