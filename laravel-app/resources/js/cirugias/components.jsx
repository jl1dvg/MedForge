import React, { useEffect, useRef, useState } from 'react';

// ── Afiliación color map ──────────────────────────────────────────────────────
const AFIL_COLORS = {
  iess:          { bg: '#dbeafe', color: '#1d4ed8', border: '#bfdbfe' },
  issfa:         { bg: '#ede9fe', color: '#6d28d9', border: '#ddd6fe' },
  msp:           { bg: '#fee2e2', color: '#b91c1c', border: '#fecaca' },
  red_publica:   { bg: '#fee2e2', color: '#b91c1c', border: '#fecaca' },
  salud_sa:      { bg: '#dcfce7', color: '#15803d', border: '#bbf7d0' },
  humana:        { bg: '#fed7aa', color: '#c2410c', border: '#fdba74' },
  particular:    { bg: '#f3f4f6', color: '#374151', border: '#e5e7eb' },
  privado:       { bg: '#f3f4f6', color: '#374151', border: '#e5e7eb' },
  alquiler:      { bg: '#fef9c3', color: '#92400e', border: '#fde68a' },
  default:       { bg: '#f1f5f9', color: '#475569', border: '#e2e8f0' },
};

function getAfilColor(afiliacion) {
  const a = (afiliacion || '').toLowerCase();
  if (a.includes('iess'))           return AFIL_COLORS.iess;
  if (a.includes('issfa'))          return AFIL_COLORS.issfa;
  if (a.includes('msp') || a.includes('red pública') || a.includes('red publica')) return AFIL_COLORS.red_publica;
  if (a.includes('salud s.a') || a.includes('salud sa')) return AFIL_COLORS.salud_sa;
  if (a.includes('humana'))         return AFIL_COLORS.humana;
  if (a.includes('alquiler'))       return AFIL_COLORS.alquiler;
  if (a.includes('particular'))     return AFIL_COLORS.particular;
  if (a.includes('privado'))        return AFIL_COLORS.privado;
  return AFIL_COLORS.default;
}

// ── Header ───────────────────────────────────────────────────────────────────

export function Header({ onTweaks }) {
  return (
    <div className="cir-page-head">
      <div>
        <h2>Reporte de cirugías</h2>
        <p className="cir-page-sub">Listado de cirugías realizadas · revisión y auditoría del protocolo quirúrgico</p>
      </div>
      <div className="cir-head-actions">
        <a href="/v2/cirugias/dashboard" className="cir-btn cir-btn-ghost cir-btn-sm">
          <i className="mdi mdi-chart-line" /> Dashboard
        </a>
        <button className="cir-btn cir-btn-ghost cir-btn-sm" onClick={onTweaks} title="Ajustes de visualización">
          <i className="mdi mdi-tune-variant" /> Ajustes
        </button>
        <button
          className="cir-btn cir-btn-ghost cir-btn-sm"
          onClick={() => window.print()}
        >
          <i className="mdi mdi-printer" /> Imprimir lista
        </button>
      </div>
    </div>
  );
}

// ── KPI Cards ────────────────────────────────────────────────────────────────

const KPI_DEFS = [
  { key: 'all',          label: 'Cirugías del periodo', icon: 'mdi-hospital-building', color: '#4361ee' },
  { key: 'por_revisar',  label: 'Por revisar',          icon: 'mdi-clock-outline',     color: '#d97706' },
  { key: 'alertas',      label: 'Con alertas',          icon: 'mdi-alert-circle',      color: '#dc2626' },
  { key: 'conforme',     label: 'Revisados',            icon: 'mdi-check-circle',      color: '#16a34a' },
  { key: 'sin_protocolo', label: 'Sin protocolo',       icon: 'mdi-file-remove',       color: '#6366f1' },
];

export function KpiCards({ rows, total, activeTab, onTabChange }) {
  const counts = rows.reduce((acc, r) => {
    acc[r.audit_status] = (acc[r.audit_status] || 0) + 1;
    return acc;
  }, {});

  return (
    <div className="cir-kpi-row">
      {KPI_DEFS.map((k) => {
        const val = k.key === 'all' ? total : (counts[k.key] || 0);
        const isActive = activeTab === k.key;
        return (
          <button
            key={k.key}
            className={`cir-kpi ${isActive ? 'cir-kpi-active' : ''}`}
            style={{ '--kc': k.color }}
            onClick={() => onTabChange(k.key)}
          >
            <div className="cir-kpi-top">
              <span className="cir-kpi-ico"><i className={`mdi ${k.icon}`} /></span>
            </div>
            <div className="cir-kpi-val">{val}</div>
            <div className="cir-kpi-lbl">{k.label}</div>
          </button>
        );
      })}
    </div>
  );
}

// ── Tabs ─────────────────────────────────────────────────────────────────────

const TAB_DEFS = [
  { key: 'por_revisar',  label: 'Por revisar',   icon: 'mdi-clock-outline',  color: '#d97706' },
  { key: 'alertas',      label: 'Con alertas',   icon: 'mdi-alert',          color: '#dc2626' },
  { key: 'conforme',     label: 'Revisados',     icon: 'mdi-check-circle',   color: '#16a34a' },
  { key: 'sin_protocolo', label: 'Sin protocolo', icon: 'mdi-file-remove',   color: '#6366f1' },
];

export function Tabs({ counts, active, onChange, totalFiltered }) {
  return (
    <div className="cir-tabs-wrap">
      <div className="cir-tabs">
        {TAB_DEFS.map((t) => {
          const n = counts[t.key] || 0;
          const isActive = active === t.key;
          return (
            <button
              key={t.key}
              className={`cir-tab ${isActive ? 'cir-tab-active' : ''}`}
              style={isActive ? { '--tc': t.color } : {}}
              onClick={() => onChange(active === t.key ? 'all' : t.key)}
            >
              <i className={`mdi ${t.icon}`} />
              {t.label}
              <span className="cir-tab-badge">{n}</span>
              <span className="cir-tab-info-btn" title="Ver descripción de este estado">
                <i className="mdi mdi-information-outline" />
              </span>
            </button>
          );
        })}
      </div>
      {active !== 'all' && (
        <div className="cir-tab-desc">
          <i className={`mdi ${TAB_DEFS.find((t) => t.key === active)?.icon}`} />
          {active === 'por_revisar' && 'Por revisar: Protocolos completos en espera de tu revisión y firma. Es tu cola principal de trabajo.'}
          {active === 'alertas' && 'Con alertas: Protocolos con campos faltantes o inconsistencias que requieren atención.'}
          {active === 'conforme' && 'Revisados: Protocolos firmados y auditados correctamente.'}
          {active === 'sin_protocolo' && 'Sin protocolo: Cirugías sin datos de protocolo registrados.'}
          <button className="cir-tab-desc-close" onClick={() => onChange('all')}>
            <i className="mdi mdi-close" />
          </button>
        </div>
      )}
    </div>
  );
}

// ── Filters Bar ───────────────────────────────────────────────────────────────

export function FiltersBar({
  pending, onChange, onApply, onClear, search, onSearch,
  afiliacionOptions = [], afiliacionCategoriaOptions = [], sedeOptions = [],
}) {
  const set = (key, val) => onChange((prev) => ({ ...prev, [key]: val }));
  const handleSubmit = (e) => { e.preventDefault(); onApply(); };

  return (
    <div className="cir-filters-bar">
      <form className="cir-filters-form" onSubmit={handleSubmit}>
        <div className="cir-filter-search-wrap">
          <i className="mdi mdi-magnify cir-search-icon" />
          <input
            type="text"
            className="cir-search-input"
            placeholder="Buscar por nombre, cédula, HC o procedimiento..."
            value={search}
            onChange={(e) => onSearch(e.target.value)}
          />
        </div>
        <div className="cir-filter-inline">
          <div className="cir-filter-lbl">DESDE</div>
          <input type="date" className="cir-filter-date" value={pending.fecha_inicio || ''} onChange={(e) => set('fecha_inicio', e.target.value)} />
        </div>
        <div className="cir-filter-inline">
          <div className="cir-filter-lbl">HASTA</div>
          <input type="date" className="cir-filter-date" value={pending.fecha_fin || ''} onChange={(e) => set('fecha_fin', e.target.value)} />
        </div>
        <select className="cir-filter-select" value={pending.afiliacion || ''} onChange={(e) => set('afiliacion', e.target.value)}>
          {afiliacionOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select className="cir-filter-select" value={pending.sede || ''} onChange={(e) => set('sede', e.target.value)}>
          {sedeOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <select className="cir-filter-select" value={pending.afiliacion_categoria || ''} onChange={(e) => set('afiliacion_categoria', e.target.value)}>
          {afiliacionCategoriaOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
        </select>
        <button type="submit" className="cir-btn cir-btn-primary cir-btn-sm">
          <i className="mdi mdi-filter-variant" /> Aplicar
        </button>
        <button type="button" className="cir-btn cir-btn-ghost cir-btn-sm" onClick={onClear}>
          <i className="mdi mdi-close-circle-outline" /> Limpiar
        </button>
      </form>
    </div>
  );
}

// ── Surgery Table ─────────────────────────────────────────────────────────────

const COL_MAP = [
  { idx: 0, label: 'NO.',          field: 'form_id',     sortable: true,  width: '56px' },
  { idx: 4, label: 'FECHA',        field: 'fecha_inicio', sortable: true, width: '100px' },
  { idx: 3, label: 'AFILIACIÓN',   field: 'afiliacion',  sortable: true,  width: '160px' },
  { idx: 2, label: 'PACIENTE',     field: 'full_name',   sortable: true,  width: '' },
  { idx: 5, label: 'PROCEDIMIENTO',field: 'membrete',    sortable: true,  width: '' },
  { idx: -2, label: 'LAT.',        field: null,          sortable: false, width: '54px' },
  { idx: -3, label: 'AUDITORÍA',   field: null,          sortable: false, width: '140px' },
  { idx: -1, label: 'ACCIONES',    field: null,          sortable: false, width: '140px' },
];

export function SurgeryTable({ rows, loading, error, sortCol, sortDir, onSort, onViewProtocol, onCertificado, onPrintToggle, onEdit, density, colorByAfil, highlightAlerts }) {
  if (error) return (
    <div className="cir-empty cir-empty-error">
      <i className="mdi mdi-alert-circle-outline" />
      <p>{error}</p>
    </div>
  );

  return (
    <div className="cir-table-wrap">
      <table className={`cir-table cir-table-${density}`}>
        <thead>
          <tr>
            {COL_MAP.map((c) => (
              <th key={c.idx} style={c.width ? { width: c.width } : {}}
                className={c.sortable ? 'cir-th-sort' : ''}
                onClick={() => c.sortable && onSort(c.idx)}
              >
                {c.label}
                {c.sortable && sortCol === c.idx && (
                  <i className={`mdi mdi-arrow-${sortDir === 'asc' ? 'up' : 'down'} cir-sort-ico`} />
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr><td colSpan={8} className="cir-loading-cell"><span className="cir-spinner" /> Cargando cirugías...</td></tr>
          )}
          {!loading && rows.length === 0 && (
            <tr><td colSpan={8} className="cir-empty-cell"><i className="mdi mdi-clipboard-text-off" /> Sin resultados</td></tr>
          )}
          {!loading && rows.map((row) => (
            <SurgeryRow
              key={`${row.form_id}-${row.hc_number}`}
              row={row}
              colorByAfil={colorByAfil}
              highlightAlerts={highlightAlerts}
              onViewProtocol={onViewProtocol}
              onCertificado={onCertificado}
              onPrintToggle={onPrintToggle}
              onEdit={onEdit}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

function LatBadge({ lat }) {
  if (!lat) return <span className="cir-lat cir-lat-empty">—</span>;
  const cls = { OD: 'cir-lat-od', OI: 'cir-lat-oi', AO: 'cir-lat-ao' }[lat] || '';
  return <span className={`cir-lat ${cls}`}>{lat}</span>;
}

function AuditBadge({ status, count }) {
  if (status === 'conforme') return <span className="cir-audit cir-audit-ok"><i className="mdi mdi-check-circle" /> Conforme</span>;
  if (status === 'por_revisar') return <span className="cir-audit cir-audit-warn"><i className="mdi mdi-clock-outline" /> Por revisar</span>;
  if (status === 'alertas') return <span className="cir-audit cir-audit-alert"><i className="mdi mdi-alert" /> {count} alerta{count !== 1 ? 's' : ''}</span>;
  return <span className="cir-audit cir-audit-none"><i className="mdi mdi-file-remove-outline" /> Sin protocolo</span>;
}

function AfilBadge({ label, sede, colorByAfil }) {
  const style = colorByAfil ? (() => {
    const c = getAfilColor(label);
    return { background: c.bg, color: c.color, border: `1px solid ${c.border}` };
  })() : {};
  return (
    <div className="cir-afil-cell">
      <span className="cir-afil-badge" style={style}>{label}</span>
      {sede && <span className="cir-afil-sede">{sede}</span>}
    </div>
  );
}

function SurgeryRow({ row, colorByAfil, highlightAlerts, onViewProtocol, onCertificado, onPrintToggle, onEdit }) {
  const isAlert = row.audit_status === 'alertas' || row.audit_status === 'sin_protocolo';
  return (
    <tr className={`cir-row ${highlightAlerts && isAlert ? 'cir-row-alert' : ''} ${row.audit_status === 'conforme' ? 'cir-row-ok' : ''}`}>
      <td className="cir-td-num">{row.form_id}</td>
      <td className="cir-td-fecha">{row.fecha_inicio}</td>
      <td className="cir-td-afil">
        <AfilBadge label={row.afiliacion_label} sede={row.sede} colorByAfil={colorByAfil} />
      </td>
      <td className="cir-td-paciente">
        <div className="cir-patient-name">{row.full_name}</div>
        <div className="cir-patient-meta">
          {row.cedula && <><span>CC {row.cedula}</span><span className="cir-sep">·</span></>}
          <span>HC {row.hc_number}</span>
          {row.edad != null && <><span className="cir-sep">·</span><span>{row.edad}a</span></>}
        </div>
      </td>
      <td className="cir-td-proc">{row.membrete || <span className="cir-empty-val">—</span>}</td>
      <td className="cir-td-lat"><LatBadge lat={row.lateralidad} /></td>
      <td className="cir-td-audit"><AuditBadge status={row.audit_status} count={row.alertas_count} /></td>
      <td className="cir-td-actions">
        <div className="cir-row-actions">
          <button className="cir-act-icon" title="Certificado de descanso" onClick={() => onCertificado(row)}>
            <i className="mdi mdi-file-document-outline" />
          </button>
          <button className="cir-act-revisar" onClick={() => onViewProtocol(row)}>
            Revisar
          </button>
          <button className="cir-act-icon" title="Editar protocolo" onClick={() => onEdit(row)}>
            <i className="mdi mdi-pencil" />
          </button>
        </div>
      </td>
    </tr>
  );
}

// ── Pagination ────────────────────────────────────────────────────────────────

export function Pagination({ page, totalPages, onPageChange }) {
  return (
    <div className="cir-pagination">
      <button className="cir-btn cir-btn-ghost cir-btn-sm" disabled={page === 0} onClick={() => onPageChange(page - 1)}>
        <i className="mdi mdi-chevron-left" /> Anterior
      </button>
      <span className="cir-page-info">Página {page + 1} de {totalPages}</span>
      <button className="cir-btn cir-btn-ghost cir-btn-sm" disabled={page >= totalPages - 1} onClick={() => onPageChange(page + 1)}>
        Siguiente <i className="mdi mdi-chevron-right" />
      </button>
    </div>
  );
}

// ── Tweaks Panel ──────────────────────────────────────────────────────────────

const ACCENT_PRESETS = ['#4361ee', '#dc2626', '#2563eb', '#16a34a', '#7c3aed', '#9333ea'];

export function TweaksPanel({ density, onDensity, colorByAfil, onColorByAfil, highlightAlerts, onHighlightAlerts, accentColor, onAccentColor, onClose }) {
  const ref = useRef(null);
  useEffect(() => {
    const handler = (e) => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [onClose]);

  return (
    <div className="cir-tweaks-backdrop">
      <div className="cir-tweaks-panel" ref={ref}>
        <div className="cir-tweaks-header">
          <span className="cir-tweaks-title">Tweaks</span>
          <button className="cir-modal-close" onClick={onClose}>&times;</button>
        </div>

        <div className="cir-tweaks-section">
          <div className="cir-tweaks-label">DISPOSICIÓN</div>
          <div className="cir-tweaks-densidad">
            <button className={`cir-dens-btn ${density === 'comodo' ? 'active' : ''}`} onClick={() => onDensity('comodo')}>Cómodo</button>
            <button className={`cir-dens-btn ${density === 'compacto' ? 'active' : ''}`} onClick={() => onDensity('compacto')}>Compacto</button>
          </div>
        </div>

        <div className="cir-tweaks-section">
          <div className="cir-tweaks-label">COLOR POR AFILIACIÓN</div>
          <Toggle value={colorByAfil} onChange={onColorByAfil} />
        </div>

        <div className="cir-tweaks-section">
          <div className="cir-tweaks-label">RESALTAR FILAS CON ALERTAS</div>
          <Toggle value={highlightAlerts} onChange={onHighlightAlerts} />
        </div>

        <div className="cir-tweaks-section">
          <div className="cir-tweaks-label">ACENTO</div>
          <div className="cir-accent-row">
            {ACCENT_PRESETS.map((c) => (
              <button
                key={c}
                className={`cir-accent-dot ${accentColor === c ? 'active' : ''}`}
                style={{ background: c }}
                onClick={() => onAccentColor(c)}
              />
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}

function Toggle({ value, onChange }) {
  return (
    <button
      className={`cir-toggle ${value ? 'cir-toggle-on' : ''}`}
      onClick={() => onChange(!value)}
      role="switch"
      aria-checked={value}
    >
      <span className="cir-toggle-thumb" />
    </button>
  );
}

// ── Toast ─────────────────────────────────────────────────────────────────────

export function Toast({ message, type = 'success', onClose }) {
  useEffect(() => {
    const t = setTimeout(onClose, 3500);
    return () => clearTimeout(t);
  }, [onClose]);
  return (
    <div className={`cir-toast cir-toast-${type}`}>
      <i className={`mdi ${type === 'error' ? 'mdi-alert-circle' : 'mdi-check-circle'}`} />
      <span>{message}</span>
      <button className="cir-toast-close" onClick={onClose}>&times;</button>
    </div>
  );
}
