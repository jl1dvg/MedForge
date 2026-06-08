import React from 'react';
import { fmtDate, deadlineInfo, initials } from './helpers';
import { AFILIACIONES, TIPOS, TABS } from './catalog';

// ---- Badges --------------------------------------------------------
export function Badge({ tone = 'soft', icon, children }) {
  return (
    <span className={`imr-badge imr-badge-${tone}`}>
      {icon && <i className={`mdi ${icon}`}></i>}
      {children}
    </span>
  );
}

export function PrioPill({ prioridad, overdue }) {
  if (overdue) return <span className="imr-prio-pill imr-prio-vencido"><i className="mdi mdi-clock-alert"></i> Vencido</span>;
  if (prioridad === 'urgente') return <span className="imr-prio-pill imr-prio-urgente"><i className="mdi mdi-fire"></i> Urgente</span>;
  if (prioridad === 'pronto') return <span className="imr-prio-pill imr-prio-pronto"><i className="mdi mdi-clock-fast"></i> Pronto</span>;
  return null;
}

export function TipoChip({ tipoKey, label }) {
  const tipo = TIPOS.find((t) => t.key === tipoKey);
  return (
    <span className="imr-tipo-chip">
      <span className="imr-tipo-ic"><i className={`mdi ${tipo ? tipo.icon : 'mdi-file'}`}></i></span>
      {label || (tipo ? tipo.short : tipoKey || '—')}
    </span>
  );
}

// ---- Topbar --------------------------------------------------------
export function Topbar({ onHelp, currentUser }) {
  return (
    <header className="imr-topbar">
      <div className="imr-brand">
        <span className="imr-brand-mark"><i className="mdi mdi-flash"></i></span>
        <div className="imr-title">
          <h1>Imágenes · Exámenes realizados</h1>
          <span className="imr-crumb">MedForge · /v2/imagenes/examenes-realizados</span>
        </div>
      </div>
      <div className="imr-topbar-spacer"></div>
      <button className="imr-help-trigger" onClick={onHelp}>
        <i className="mdi mdi-help-circle-outline"></i> Cómo funciona
      </button>
      <div className="imr-topbar-actions">
        <button className="imr-icon-btn" title="Exportar PDF"><i className="mdi mdi-file-pdf-box"></i></button>
        <button className="imr-icon-btn" title="Exportar Excel"><i className="mdi mdi-file-excel-box"></i></button>
        <button className="imr-icon-btn" title="Avisos del sistema"><i className="mdi mdi-bell-outline"></i></button>
        <div className="imr-user-chip">
          <span className="imr-user-av">{initials(currentUser.name)}</span>
          <span className="imr-user-meta">
            <b>{currentUser.name}</b>
            <span>{currentUser.role}</span>
          </span>
        </div>
      </div>
    </header>
  );
}

// ---- KPI row -------------------------------------------------------
function Kpi({ tone, icon, value, label, active, onClick }) {
  return (
    <button className={`imr-kpi imr-kpi-c-${tone} ${active ? 'active' : ''}`} onClick={onClick}>
      <div className="imr-kpi-top">
        <span className="imr-kpi-ico"><i className={`mdi ${icon}`}></i></span>
      </div>
      <div className="imr-kpi-val">{value}</div>
      <div className="imr-kpi-lbl">{label}</div>
    </button>
  );
}

export function KpiRow({ metrics, kpiFilter, onKpi }) {
  return (
    <div className="imr-kpi-row">
      <Kpi tone="primary" icon="mdi-file-document-edit-outline" value={metrics.porInformar}
        label="Por informar" active={kpiFilter === 'por-informar'} onClick={() => onKpi('por-informar')} />
      <Kpi tone="danger" icon="mdi-bell-alert-outline" value={metrics.bandeja}
        label="Bandeja prioritaria" active={kpiFilter === 'bandeja'} onClick={() => onKpi('bandeja')} />
      <Kpi tone="danger" icon="mdi-clock-alert-outline" value={metrics.vencidos}
        label="Plazo vencido" active={kpiFilter === 'vencidos'} onClick={() => onKpi('vencidos')} />
      <Kpi tone="warning" icon="mdi-folder-alert-outline" value={metrics.sinNas}
        label="Sin archivos" active={kpiFilter === 'sin-nas'} onClick={() => onKpi('sin-nas')} />
      <Kpi tone="success" icon="mdi-file-check-outline" value={metrics.informados}
        label="Informados" active={kpiFilter === 'informados'} onClick={() => onKpi('informados')} />
    </div>
  );
}

// ---- Tabs ----------------------------------------------------------
export function Tabs({ activeTab, counts, onChange, onTabHelp }) {
  return (
    <div className="imr-tabs-wrap">
      <div className="imr-tabs" role="tablist">
        {TABS.map((tb) => (
          <button
            key={tb.key}
            role="tab"
            className={`imr-tab imr-tab-c-${tb.tone} ${activeTab === tb.key ? 'active' : ''}`}
            onClick={() => onChange(tb.key)}
          >
            <i className={`mdi ${tb.icon}`}></i>
            {tb.label}
            <span className="imr-tab-count">{counts[tb.key] ?? 0}</span>
            <span
              className="imr-tab-help-btn"
              title={`¿Para qué sirve "${tb.label}"?`}
              onClick={(e) => { e.stopPropagation(); onTabHelp(tb.key); }}
            >
              <i className="mdi mdi-information-outline"></i>
            </span>
          </button>
        ))}
      </div>
    </div>
  );
}

export function TabDescription({ tab, onMore }) {
  const tones = {
    'no-informados': { bg: 'var(--primary-fade)', c: 'var(--accent)' },
    'bandeja':       { bg: '#fdecef', c: 'var(--danger)' },
    'informados':    { bg: '#e3f5ee', c: 'var(--success)' },
    'sin-nas':       { bg: '#fff6e3', c: '#b9760f' },
  };
  const tn = tones[tab.key] || tones['no-informados'];
  return (
    <div className="imr-tab-desc" style={{ '--desc-bg': tn.bg, '--desc-c': tn.c }}>
      <i className={`mdi ${tab.icon}`}></i>
      <span><b>{tab.label}:</b> {tab.desc}</span>
      <button className="imr-tab-desc-more" onClick={() => onMore(tab.key)}>Saber más</button>
    </div>
  );
}

// ---- Filters -------------------------------------------------------
export function Filters({ filters, setFilters, onClear }) {
  const set = (k, v) => setFilters((f) => ({ ...f, [k]: v }));
  return (
    <div className="imr-filters">
      <div className="imr-field imr-field-grow imr-search-field">
        <label>Paciente / cédula / HC</label>
        <div style={{ position: 'relative' }}>
          <i className="mdi mdi-magnify"></i>
          <input type="text" placeholder="Buscar por nombre, cédula o HC…"
            value={filters.search} onChange={(e) => set('search', e.target.value)} />
        </div>
      </div>
      <div className="imr-field">
        <label>Desde</label>
        <input type="date" value={filters.from} onChange={(e) => set('from', e.target.value)} />
      </div>
      <div className="imr-field">
        <label>Hasta</label>
        <input type="date" value={filters.to} onChange={(e) => set('to', e.target.value)} />
      </div>
      <div className="imr-field">
        <label>Afiliación</label>
        <select value={filters.afiliacion} onChange={(e) => set('afiliacion', e.target.value)}>
          <option value="">Todas</option>
          {AFILIACIONES.map((a) => <option key={a.value} value={a.value}>{a.label}</option>)}
        </select>
      </div>
      <div className="imr-field">
        <label>Sede</label>
        <select value={filters.sede} onChange={(e) => set('sede', e.target.value)}>
          <option value="">Todas</option>
          <option value="MATRIZ">MATRIZ</option>
          <option value="CEIBOS">CEIBOS</option>
        </select>
      </div>
      <div className="imr-field">
        <label>Tipo de examen</label>
        <select value={filters.tipo} onChange={(e) => set('tipo', e.target.value)}>
          <option value="">Todos</option>
          {TIPOS.map((t) => <option key={t.key} value={t.key}>{t.short}</option>)}
        </select>
      </div>
      <div className="imr-filter-actions">
        <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={onClear}>
          <i className="mdi mdi-close-circle-outline"></i> Limpiar
        </button>
      </div>
    </div>
  );
}

// ---- Toast ---------------------------------------------------------
export function Toast({ toast }) {
  if (!toast) return null;
  return (
    <div className="imr-toast-wrap">
      <div className={`imr-toast ${toast.tone || 'ok'}`}>
        <i className={`mdi ${toast.icon || 'mdi-check-circle'}`}></i>
        {toast.msg}
      </div>
    </div>
  );
}

// ---- Empty state ---------------------------------------------------
export function EmptyState({ icon, title, text }) {
  return (
    <div className="imr-empty">
      <i className={`mdi ${icon}`}></i>
      <h4>{title}</h4>
      <p>{text}</p>
    </div>
  );
}
