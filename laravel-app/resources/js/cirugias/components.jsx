import React from 'react';

export const TABS = [
  { key: 'por-revisar', label: 'Por revisar', icon: 'mdi-clipboard-text-clock-outline', tone: 'primary',
    desc: 'Protocolos completos en espera de tu revisión y firma. Es tu cola principal de trabajo.',
    help: 'Cirugías cuyo protocolo ya fue redactado por el equipo quirúrgico y está listo para que lo revises. Ábrelo para auditar concordancia, corregir y marcarlo como revisado.' },
  { key: 'auditoria', label: 'Con alertas', icon: 'mdi-shield-alert-outline', tone: 'danger',
    desc: 'Protocolos donde la auditoría automática detectó discrepancias con lo proyectado o la plantilla.',
    help: 'Bandeja prioritaria: la auditoría comparó el protocolo con lo proyectado y encontró alertas (insumos faltantes, equipo incompleto, diagnósticos sin registrar, tiempos ausentes). Resuélvelas antes de firmar.' },
  { key: 'revisados', label: 'Revisados', icon: 'mdi-clipboard-check-outline', tone: 'success',
    desc: 'Protocolos revisados y firmados. Puedes imprimirlos, emitir certificados y reabrir si hace falta.',
    help: 'Histórico de protocolos ya revisados y conformes. Muestra quién revisó y cuándo, y el estado de impresión. Desde aquí imprimes el protocolo, emites el certificado de descanso o reabres para corregir.' },
  { key: 'sin-protocolo', label: 'Sin protocolo', icon: 'mdi-clipboard-alert-outline', tone: 'warning',
    desc: 'Cirugías realizadas que aún no tienen protocolo redactado. Falta documentar el acto quirúrgico.',
    help: 'La cirugía consta como realizada pero su protocolo quirúrgico todavía no se ha redactado. Inicia el protocolo en el wizard para documentarla.' },
];

export const AFILIACIONES = [
  { value: 'IESS', label: 'IESS', cat: 'publico' },
  { value: 'ISSPOL', label: 'ISSPOL', cat: 'publico' },
  { value: 'ISSFA', label: 'ISSFA', cat: 'publico' },
  { value: 'MSP', label: 'Red Pública MSP', cat: 'publico' },
  { value: 'SALUD S.A.', label: 'Salud S.A.', cat: 'privado' },
  { value: 'BMI', label: 'BMI Seguros', cat: 'privado' },
  { value: 'PARTICULAR', label: 'Particular', cat: 'particular' },
  { value: 'FUNDACIÓN', label: 'Fundación Ver', cat: 'fundacional' },
];

export function afilOf(value) {
  return AFILIACIONES.find((a) => a.value === value) || null;
}

export function afilBadgeTone(cat) {
  return { publico: 'info', privado: 'primary', particular: 'soft', fundacional: 'success' }[cat] || 'soft';
}

export function initials(name) {
  return (name || '').split(' ').filter(Boolean).slice(0, 2).map((w) => w[0]).join('').toUpperCase();
}

// ---- Badges -----------------------------------------------------
export function Badge({ tone = 'soft', icon, children }) {
  return <span className={`badge badge-${tone}`}>{icon && <i className={`mdi ${icon}`} />}{children}</span>;
}

export function ProcChip({ row }) {
  return (
    <span className="proc-chip">
      <span className="ic"><i className="mdi mdi-hospital-box-outline" /></span>
      <span>
        {row.membrete || '—'}
        <br />
        <span className="code">{row.form_id}{row.cirujano_display ? ` · ${row.cirujano_display}` : ''}</span>
      </span>
    </span>
  );
}

export function AuditPill({ audit }) {
  if (!audit) return null;
  const map = {
    ok: { cls: 'audit-ok', ic: 'mdi-shield-check', txt: 'Conforme' },
    warning: { cls: 'audit-warning', ic: 'mdi-shield-alert-outline', txt: `${audit.summary?.warning ?? 0} advert.` },
    error: { cls: 'audit-error', ic: 'mdi-shield-alert', txt: `${audit.summary?.error ?? 0} alerta${(audit.summary?.error ?? 0) !== 1 ? 's' : ''}` },
  }[audit.status] || { cls: 'audit-warning', ic: 'mdi-shield-alert-outline', txt: 'Revisar' };
  return <span className={`audit-pill ${map.cls}`}><i className={`mdi ${map.ic}`} />{map.txt}</span>;
}

export function StatusPill({ row }) {
  if (row.status === 1) return <span className="status-pill status-revisado"><i className="mdi mdi-check" /> Revisado</span>;
  if (!row.protocolo_iniciado) return <span className="status-pill status-vacio">Sin protocolo</span>;
  return <span className="status-pill status-pendiente">Por revisar</span>;
}

// ---- Topbar -----------------------------------------------------
export function Topbar({ currentUser, onHelp }) {
  const u = currentUser || {};
  return (
    <header className="app-topbar">
      <div className="app-brand">
        <span className="app-brand-mark"><i className="mdi mdi-flash" /></span>
        <div className="app-title">
          <h1>Cirugías · Reporte de protocolos</h1>
          <span className="crumb">MedForge · /v2/cirugias</span>
        </div>
      </div>
      <div className="topbar-spacer" />
      <button className="help-trigger" onClick={onHelp} title="Cómo funciona esta vista">
        <i className="mdi mdi-help-circle-outline" /> Cómo funciona
      </button>
      <div className="topbar-actions">
        <button className="icon-btn" title="Exportar PDF"><i className="mdi mdi-file-pdf-box" /></button>
        <button className="icon-btn" title="Exportar Excel"><i className="mdi mdi-file-excel-box" /></button>
        <button className="icon-btn" title="Avisos del sistema"><i className="mdi mdi-bell-outline" /><span className="dot" /></button>
        {u.name && (
          <div className="user-chip">
            <span className="av">{initials(u.name)}</span>
            <span className="meta"><b>{u.name}</b><span>{u.role || ''}</span></span>
          </div>
        )}
      </div>
    </header>
  );
}

// ---- KPI --------------------------------------------------------
function Kpi({ tone, icon, value, label, active, onClick }) {
  return (
    <button className={`kpi kpi-c-${tone} ${active ? 'active' : ''}`} onClick={onClick}>
      <div className="kpi-top"><span className="kpi-ico"><i className={`mdi ${icon}`} /></span></div>
      <div className="kpi-val">{value}</div>
      <div className="kpi-lbl">{label}</div>
    </button>
  );
}

export function KpiRow({ metrics, kpiFilter, onKpi }) {
  return (
    <div className="kpi-row">
      <Kpi tone="cir" icon="mdi-hospital-box-outline" value={metrics.total}
        label="Cirugías del periodo" active={kpiFilter === 'total'} onClick={() => onKpi('total')} />
      <Kpi tone="primary" icon="mdi-clipboard-text-clock-outline" value={metrics.porRevisar}
        label="Por revisar" active={kpiFilter === 'por-revisar'} onClick={() => onKpi('por-revisar')} />
      <Kpi tone="danger" icon="mdi-shield-alert-outline" value={metrics.alertas}
        label="Con alertas" active={kpiFilter === 'alertas'} onClick={() => onKpi('alertas')} />
      <Kpi tone="success" icon="mdi-clipboard-check-outline" value={metrics.revisados}
        label="Revisados" active={kpiFilter === 'revisados'} onClick={() => onKpi('revisados')} />
      <Kpi tone="warning" icon="mdi-clipboard-alert-outline" value={metrics.sinProtocolo}
        label="Sin protocolo" active={kpiFilter === 'sin-protocolo'} onClick={() => onKpi('sin-protocolo')} />
    </div>
  );
}

// ---- Tabs -------------------------------------------------------
export function Tabs({ activeTab, counts, onChange, onTabHelp }) {
  return (
    <div className="tabs-wrap">
      <div className="tabs" role="tablist">
        {TABS.map((tb) => (
          <button key={tb.key} role="tab"
            className={`tab tab-c-${tb.tone} ${activeTab === tb.key ? 'active' : ''}`}
            onClick={() => onChange(tb.key)}>
            <i className={`mdi ${tb.icon}`} />
            {tb.label}
            <span className="tab-count">{counts[tb.key] ?? 0}</span>
            <span className="tab-help-btn" title={`¿Para qué sirve "${tb.label}"?`}
              onClick={(e) => { e.stopPropagation(); onTabHelp(tb.key); }}>
              <i className="mdi mdi-information-outline" />
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}

export function TabDescription({ tab, onMore }) {
  const tones = {
    'por-revisar': { bg: 'var(--primary-fade)', c: 'var(--accent)' },
    'auditoria': { bg: '#fdecef', c: 'var(--danger)' },
    'revisados': { bg: '#e3f5ee', c: 'var(--success)' },
    'sin-protocolo': { bg: '#fff6e3', c: '#b9760f' },
  };
  const tn = tones[tab.key] || tones['por-revisar'];
  return (
    <div className="tab-desc" style={{ '--desc-bg': tn.bg, '--desc-c': tn.c }}>
      <i className={`mdi ${tab.icon}`} />
      <span><b>{tab.label}:</b> {tab.desc}</span>
      <button className="more" onClick={() => onMore(tab.key)}>Saber más</button>
    </div>
  );
}

// ---- Filters ----------------------------------------------------
export function Filters({ filters, setFilters, onClear, afiliacionOptions, sedeOptions }) {
  const set = (k, v) => setFilters((f) => ({ ...f, [k]: v }));
  return (
    <div className="filters">
      <div className="field search-field grow">
        <label>Paciente / cédula / HC</label>
        <div style={{ position: 'relative' }}>
          <i className="mdi mdi-magnify" />
          <input type="text" placeholder="Buscar por nombre, cédula o HC…"
            value={filters.search} onChange={(e) => set('search', e.target.value)} />
        </div>
      </div>
      <div className="field"><label>Desde</label>
        <input type="date" value={filters.from} onChange={(e) => set('from', e.target.value)} /></div>
      <div className="field"><label>Hasta</label>
        <input type="date" value={filters.to} onChange={(e) => set('to', e.target.value)} /></div>
      <div className="field"><label>Afiliación</label>
        <select value={filters.afiliacion} onChange={(e) => set('afiliacion', e.target.value)}>
          <option value="">Todas</option>
          {(afiliacionOptions || []).map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
        </select></div>
      <div className="field"><label>Sede</label>
        <select value={filters.sede} onChange={(e) => set('sede', e.target.value)}>
          <option value="">Todas</option>
          {(sedeOptions || []).map((s) => {
          const val = typeof s === 'object' ? s.value : s;
          const lbl = typeof s === 'object' ? s.label : s;
          return <option key={val} value={val}>{lbl}</option>;
        })}
        </select></div>
      <div className="filter-actions">
        <button className="btn btn-ghost btn-sm" onClick={onClear}>
          <i className="mdi mdi-close-circle-outline" /> Limpiar
        </button>
      </div>
    </div>
  );
}

// ---- Panel de auditoría ----------------------------------------
export function AuditPanel({ audit, compact }) {
  if (!audit) return null;
  const head = {
    ok: { ic: 'mdi-shield-check', h: 'Protocolo conforme', p: 'La auditoría no detectó discrepancias con lo proyectado ni con la plantilla quirúrgica.' },
    warning: { ic: 'mdi-shield-alert-outline', h: 'Revisar advertencias', p: 'El protocolo es válido pero hay puntos que conviene verificar antes de firmar.' },
    error: { ic: 'mdi-shield-alert', h: 'Alertas en el protocolo', p: 'La auditoría encontró discrepancias que debes resolver antes de marcar como revisado.' },
  }[audit.status] || { ic: 'mdi-shield-alert-outline', h: 'Revisar protocolo', p: 'Revisa el protocolo antes de continuar.' };
  const ckIc = { ok: 'mdi-check-circle', warning: 'mdi-alert', error: 'mdi-close-circle' };
  return (
    <div className="audit-panel">
      <div className={`audit-summary s-${audit.status}`}>
        <span className="as-ico"><i className={`mdi ${head.ic}`} /></span>
        <div className="as-txt">
          <h4>{head.h}</h4>
          <p>{head.p}</p>
        </div>
        <div className="audit-counts">
          <span className="audit-count c-ok"><i className="mdi mdi-check-circle" />{audit.summary?.ok ?? 0}</span>
          <span className="audit-count c-warning"><i className="mdi mdi-alert" />{audit.summary?.warning ?? 0}</span>
          <span className="audit-count c-error"><i className="mdi mdi-close-circle" />{audit.summary?.error ?? 0}</span>
        </div>
      </div>
      {!compact && audit.checks && audit.checks.length > 0 && (
        <div className="audit-checks">
          {audit.checks.map((c, i) => (
            <div className={`audit-check k-${c.status}`} key={i}>
              <span className="ac-ic"><i className={`mdi ${ckIc[c.status] || 'mdi-alert'}`} /></span>
              <div className="ac-body">
                <div className="ac-head"><h5>{c.title}</h5></div>
                <p className="ac-msg">{c.message}</p>
                {c.details && <AuditDetail details={c.details} />}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

function AuditDetail({ details }) {
  if (!details) return null;
  const d = details;
  const faltantes = Array.isArray(d.faltantes) ? d.faltantes : [];
  return (
    <div className="audit-detail">
      {d.proyectado && <span className="ad-k"><b>Proyectado:</b> {d.proyectado}</span>}
      {d.registrado != null && d.registrado !== '' && <span className="ad-k"><b>Registrado:</b> {d.registrado}</span>}
      {d.esperado != null && <span className="ad-k"><b>Esperado:</b> {d.esperado}</span>}
      {faltantes.map((f, i) => <span className="ad-k" key={i}><b>Falta:</b> {f}</span>)}
    </div>
  );
}

// ---- Toast ------------------------------------------------------
export function Toast({ toast }) {
  if (!toast) return null;
  return (
    <div className="toast-wrap">
      <div className={`toast ${toast.tone || 'ok'}`}>
        <i className={`mdi ${toast.icon || 'mdi-check-circle'}`} />{toast.msg}
      </div>
    </div>
  );
}

export function EmptyState({ icon, title, text }) {
  return (
    <div className="empty">
      <i className={`mdi ${icon}`} />
      <h4>{title}</h4>
      <p>{text}</p>
    </div>
  );
}

// ---- TweakPanel -------------------------------------------------
export function TweakPanel({ tweaks, setTweak }) {
  const ACCENTS = ['#5156be', '#d34b5b', '#3596f7', '#1f9d7a', '#7C4DFF'];
  return (
    <div className="tweak-panel">
      <div className="tweak-head">
        <span>⚙ Vista</span>
      </div>
      <div className="tweak-body">
        <div className="tweak-section">Disposición</div>
        <div className="tweak-row">
          <span>Densidad</span>
          <div className="tweak-seg">
            <button className={tweaks.density === 'comodo' ? 'on' : ''} onClick={() => setTweak('density', 'comodo')}>Cómodo</button>
            <button className={tweaks.density === 'compacto' ? 'on' : ''} onClick={() => setTweak('density', 'compacto')}>Compacto</button>
          </div>
        </div>
        <div className="tweak-row">
          <span>Color por afiliación</span>
          <label className="tweak-toggle">
            <input type="checkbox" checked={tweaks.afilColor} onChange={(e) => setTweak('afilColor', e.target.checked)} />
            <span className="trk" />
          </label>
        </div>
        <div className="tweak-row">
          <span>Resaltar alertas</span>
          <label className="tweak-toggle">
            <input type="checkbox" checked={tweaks.highlightAlerts} onChange={(e) => setTweak('highlightAlerts', e.target.checked)} />
            <span className="trk" />
          </label>
        </div>
        <div className="tweak-section">Marca</div>
        <div className="tweak-row">
          <span>Acento</span>
          <div className="tweak-colors">
            {ACCENTS.map((c) => (
              <button key={c} className={`tweak-swatch${tweaks.accent === c ? ' on' : ''}`}
                style={{ background: c }} onClick={() => setTweak('accent', c)} title={c} />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
