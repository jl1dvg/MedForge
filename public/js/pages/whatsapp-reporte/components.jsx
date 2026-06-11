/* ============================================================================
   MedForge · Reporte ejecutivo WhatsApp — átomos de UI compartidos
   ========================================================================== */

const fmt = (n) => (typeof n === "number" ? n.toLocaleString("es-EC") : n);
const pctStr = (n) => `${n}%`;

const ACCENT = {
  primary: { fg: "var(--primary)", bg: "var(--primary-fade)" },
  info:    { fg: "var(--info)",    bg: "#cfe5fd" },
  success: { fg: "var(--success)", bg: "#dff5ee" },
  warning: { fg: "#8a5d0a",        bg: "#fff0d1" },
  danger:  { fg: "var(--danger)",  bg: "#fde2e7" },
};

/* delta chip — invert=true cuando "menos es mejor" (ej. tiempo de respuesta) */
function Delta({ value, invert = false, suffix = "%", absolute = false }) {
  if (value === 0 || value == null) {
    return <span className="rep-delta flat"><i className="mdi mdi-minus"></i>0{suffix}</span>;
  }
  const positive = value > 0;
  const good = invert ? !positive : positive;
  const cls = good ? "up" : "down";
  const icon = positive ? "mdi-arrow-up-thin" : "mdi-arrow-down-thin";
  const shown = absolute ? Math.abs(value) : Math.abs(value);
  return (
    <span className={`rep-delta ${cls}`}>
      <i className={`mdi ${icon}`}></i>{shown}{suffix}
    </span>
  );
}

/* mini sparkline desde un array de números */
function Spark({ values, accentLast = true }) {
  const max = Math.max(...values, 1);
  return (
    <div className="rep-spark" aria-hidden="true">
      {values.map((v, i) => (
        <span key={i} className={accentLast && i === values.length - 1 ? "hi" : ""} style={{ height: `${Math.max(6, (v / max) * 100)}%` }}></span>
      ))}
    </div>
  );
}

function KpiCard({ icon, accent = "primary", label, value, unit, sub, delta, deltaInvert = false, deltaSuffix = "%", spark, footer }) {
  const a = ACCENT[accent] || ACCENT.primary;
  return (
    <article className="rep-kpi" style={{ "--kpi-accent": a.fg }}>
      <div className="rep-kpi-top">
        <span className="rep-kpi-ic" style={{ background: a.bg, color: a.fg }}><i className={`mdi ${icon}`}></i></span>
        <span className="rep-kpi-label">{label}</span>
      </div>
      <div className="rep-kpi-valrow">
        <span className="rep-kpi-value">{value}{unit ? <small>{unit}</small> : null}</span>
        {delta !== undefined && <Delta value={delta} invert={deltaInvert} suffix={deltaSuffix} />}
      </div>
      {sub && <div className="rep-kpi-sub" dangerouslySetInnerHTML={{ __html: sub }}></div>}
      {spark && <Spark values={spark} />}
      {footer}
    </article>
  );
}

/* placeholder elegante para KPI [NUEVO] — pendiente de implementación */
function NuevoCard({ icon, label, ghost, desc, chart = true }) {
  return (
    <article className="rep-nuevo" title="KPI propuesto — pendiente de implementación en base de datos">
      <span className="rep-nuevo-tag">Nuevo</span>
      <div className="rep-nuevo-top">
        <span className="rep-nuevo-ic"><i className={`mdi ${icon}`}></i></span>
        <span className="rep-nuevo-label">{label}</span>
      </div>
      <div className="rep-nuevo-ghost">{ghost}</div>
      {chart ? (
        <div className="rep-nuevo-ghostchart" aria-hidden="true">
          {[40, 62, 48, 78, 58, 86, 70].map((h, i) => <span key={i} style={{ height: `${h}%` }}></span>)}
        </div>
      ) : null}
      <div className="rep-nuevo-state"><i className="mdi mdi-progress-wrench"></i>Datos en implementación</div>
      <div className="rep-nuevo-desc">{desc}</div>
    </article>
  );
}

function SectionHead({ num, kicker, title, lede }) {
  return (
    <header className="rep-sec-head">
      <div className="rep-sec-num">{num}</div>
      <div className="rep-sec-headmain">
        <div className="rep-sec-kicker">{kicker}</div>
        <h2>{title}</h2>
        {lede && <p className="rep-sec-lede" dangerouslySetInnerHTML={{ __html: lede }}></p>}
      </div>
    </header>
  );
}

/* lectura narrativa (callout) */
function Read({ icon = "mdi-text-box-outline", html, children }) {
  return (
    <div className="rep-read">
      <i className={`mdi ${icon} lead`}></i>
      {html ? <p dangerouslySetInnerHTML={{ __html: html }}></p> : <p>{children}</p>}
    </div>
  );
}

/* barras horizontales genéricas */
function BarList({ items, max, palette, accessor = (x) => x.total, metaFmt }) {
  const m = max || Math.max(...items.map(accessor), 1);
  return (
    <div className="rep-bars">
      {items.map((it, i) => (
        <div key={it.id || it.label || i}>
          <div className="rep-bar-top">
            <span className="rep-bar-name">{it.icon && <i className={`mdi ${it.icon}`}></i>}<span className="rep-bar-txt">{it.label || it.name}</span></span>
            <span className="rep-bar-meta">{metaFmt ? metaFmt(it) : <><strong>{fmt(accessor(it))}</strong> · {it.share}%</>}</span>
          </div>
          <div className="rep-bar-track">
            <div className="rep-bar-fill" style={{ width: `${(accessor(it) / m) * 100}%`, background: (palette && palette[i % palette.length]) || "var(--primary)" }}></div>
          </div>
        </div>
      ))}
    </div>
  );
}

function Card({ title, icon, note, className = "", children, headRight, style }) {
  return (
    <section className={`rep-card ${className}`} style={style}>
      {(title || headRight) && (
        <header className="rep-card-head">
          {title && <h3>{icon && <i className={`mdi ${icon}`}></i>}{title}</h3>}
          {headRight || (note && <span className="rep-card-note">{note}</span>)}
        </header>
      )}
      {children}
    </section>
  );
}

const PALETTE = ["#5156be", "#3596f7", "#05825f", "#ffa800", "#0863be", "#7479d4"];

Object.assign(window, { fmt, pctStr, ACCENT, PALETTE, Delta, Spark, KpiCard, NuevoCard, SectionHead, Read, BarList, Card });
