/* MedForge Control Center — shared UI components. Exposed to window. */
const { useState, useEffect, useRef } = React;

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

Object.assign(window, {
  Card, Kpi, StateBadge, PayBadge, PlanBadge, RiskBadge, ClientAva, Switch, Progress,
  BarChart, Sparkline, AreaChart, Donut, ServicePill, PageHead, Drawer,
});
