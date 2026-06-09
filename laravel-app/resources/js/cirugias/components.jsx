import React, { useEffect, useRef } from 'react';

// ── Filters ──────────────────────────────────────────────────────────────────

export function Filters({
  pending,
  onChange,
  onApply,
  onClear,
  search,
  onSearch,
  afiliacionOptions = [],
  afiliacionCategoriaOptions = [],
  sedeOptions = [],
}) {
  const set = (key, val) => onChange((prev) => ({ ...prev, [key]: val }));

  const handleSubmit = (e) => {
    e.preventDefault();
    onApply();
  };

  return (
    <div className="cir-filters">
      <form className="cir-filters-grid" onSubmit={handleSubmit}>
        <div className="cir-filter-field">
          <label>Desde</label>
          <input
            type="date"
            className="cir-input"
            value={pending.fecha_inicio || ''}
            onChange={(e) => set('fecha_inicio', e.target.value)}
          />
        </div>
        <div className="cir-filter-field">
          <label>Hasta</label>
          <input
            type="date"
            className="cir-input"
            value={pending.fecha_fin || ''}
            onChange={(e) => set('fecha_fin', e.target.value)}
          />
        </div>
        <div className="cir-filter-field">
          <label>Afiliación</label>
          <select
            className="cir-input"
            value={pending.afiliacion || ''}
            onChange={(e) => set('afiliacion', e.target.value)}
          >
            {afiliacionOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>
        <div className="cir-filter-field">
          <label>Categoría</label>
          <select
            className="cir-input"
            value={pending.afiliacion_categoria || ''}
            onChange={(e) => set('afiliacion_categoria', e.target.value)}
          >
            {afiliacionCategoriaOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>
        <div className="cir-filter-field">
          <label>Sede</label>
          <select
            className="cir-input"
            value={pending.sede || ''}
            onChange={(e) => set('sede', e.target.value)}
          >
            {sedeOptions.map((opt) => (
              <option key={opt.value} value={opt.value}>{opt.label}</option>
            ))}
          </select>
        </div>
        <div className="cir-filter-field cir-filter-search">
          <label>Buscar</label>
          <input
            type="text"
            className="cir-input"
            placeholder="Nombre, C.I., procedimiento..."
            value={search}
            onChange={(e) => onSearch(e.target.value)}
          />
        </div>
        <div className="cir-filter-actions">
          <button type="submit" className="cir-btn cir-btn-primary cir-btn-sm">
            <i className="mdi mdi-filter-variant" /> Aplicar
          </button>
          <button type="button" className="cir-btn cir-btn-ghost cir-btn-sm" onClick={onClear}>
            <i className="mdi mdi-close-circle-outline" /> Limpiar
          </button>
        </div>
      </form>
    </div>
  );
}

// ── KPI Row ───────────────────────────────────────────────────────────────────

export function KpiRow({ rows, total }) {
  // Compute quick stats from loaded rows (detect from HTML returned by datatable)
  const revisados = rows.filter((r) => r.protocolo_html?.includes('bg-success')).length;
  const pendientes = rows.filter((r) => !r.protocolo_html?.includes('bg-success')).length;
  const impresos = rows.filter((r) => r.imprimir_html?.includes('active')).length;

  const kpis = [
    { label: 'Total', value: total, icon: 'mdi-clipboard-list', color: 'primary' },
    { label: 'En vista', value: rows.length, icon: 'mdi-eye', color: 'info' },
    { label: 'Revisados', value: revisados, icon: 'mdi-check-circle', color: 'success' },
    { label: 'Pendientes', value: pendientes, icon: 'mdi-clock-outline', color: 'warning' },
    { label: 'Impresos', value: impresos, icon: 'mdi-printer-check', color: 'muted' },
  ];

  return (
    <div className="cir-kpi-row">
      {kpis.map((k) => (
        <div key={k.label} className={`cir-kpi cir-kpi-c-${k.color}`}>
          <div className="cir-kpi-top">
            <span className="cir-kpi-ico"><i className={`mdi ${k.icon}`} /></span>
          </div>
          <div className="cir-kpi-val">{k.value.toLocaleString()}</div>
          <div className="cir-kpi-lbl">{k.label}</div>
        </div>
      ))}
    </div>
  );
}

// ── Surgery Table ─────────────────────────────────────────────────────────────

const COLS = [
  { key: 0, label: 'No.', col: 'form_id' },
  { key: 1, label: 'C.I.', col: 'hc_number' },
  { key: 2, label: 'Paciente', col: 'full_name' },
  { key: 3, label: 'Afiliación', col: 'afiliacion' },
  { key: 4, label: 'Fecha', col: 'fecha_inicio' },
  { key: 5, label: 'Procedimiento', col: 'membrete' },
  { key: -1, label: 'Acciones', col: null },
];

export function SurgeryTable({
  rows,
  loading,
  error,
  sortCol,
  sortDir,
  onSort,
  onViewProtocol,
  onCertificado,
  onPrintToggle,
}) {
  if (error) {
    return (
      <div className="cir-empty cir-empty-error">
        <i className="mdi mdi-alert-circle-outline" />
        <p>{error}</p>
      </div>
    );
  }

  return (
    <div className="cir-table-wrap">
      <table className="cir-table">
        <thead>
          <tr>
            {COLS.map((c) => (
              <th
                key={c.key}
                className={c.col ? 'sortable' : ''}
                onClick={() => c.col && onSort(c.key)}
              >
                {c.label}
                {c.col && sortCol === c.key && (
                  <i className={`mdi mdi-arrow-${sortDir === 'asc' ? 'up' : 'down'} sort-icon`} />
                )}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr>
              <td colSpan={7} className="cir-loading-cell">
                <span className="cir-spinner" /> Cargando...
              </td>
            </tr>
          )}
          {!loading && rows.length === 0 && (
            <tr>
              <td colSpan={7} className="cir-empty-cell">
                <i className="mdi mdi-clipboard-text-off" /> Sin resultados
              </td>
            </tr>
          )}
          {!loading && rows.map((row) => (
            <SurgeryRow
              key={`${row.form_id}-${row.hc_number}`}
              row={row}
              onViewProtocol={onViewProtocol}
              onCertificado={onCertificado}
              onPrintToggle={onPrintToggle}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}

function SurgeryRow({ row, onViewProtocol, onCertificado, onPrintToggle }) {
  // The datatable returns HTML strings — we parse the estado from html badges
  const printed = row.imprimir_html?.includes('active') ?? false;
  // Detect estado from badge HTML
  const estado = row.protocolo_html?.includes('bg-success')
    ? 'revisado'
    : row.protocolo_html?.includes('bg-warning')
    ? 'advertencia'
    : 'pendiente';

  const estadoBadge = {
    revisado: <span className="cir-badge cir-badge-success"><i className="mdi mdi-check" /> Revisado</span>,
    advertencia: <span className="cir-badge cir-badge-warning"><i className="mdi mdi-alert" /> Advertencia</span>,
    pendiente: <span className="cir-badge cir-badge-danger"><i className="mdi mdi-close" /> Pendiente</span>,
  }[estado];

  return (
    <tr className={`cir-row ${estado === 'revisado' ? 'cir-row-ok' : ''}`}>
      <td className="cir-td-num">{row.form_id}</td>
      <td className="cir-td-ci">{row.hc_number}</td>
      <td className="cir-td-name">
        <span className="cir-name">{row.full_name}</span>
      </td>
      <td
        className="cir-td-afil"
        dangerouslySetInnerHTML={{ __html: row.afiliacion_html || row.afiliacion || '' }}
      />
      <td className="cir-td-fecha">{row.fecha_inicio}</td>
      <td className="cir-td-proc">{row.membrete}</td>
      <td className="cir-td-actions">
        <div className="cir-actions">
          <button
            className="cir-act-btn cir-act-info"
            title="Ver protocolo quirúrgico"
            onClick={() => onViewProtocol(row)}
          >
            {estadoBadge}
            <i className="mdi mdi-file-document" /> Protocolo
          </button>
          <button
            className="cir-act-btn cir-act-warning"
            title="Certificado de descanso"
            onClick={() => onCertificado(row)}
          >
            <i className="mdi mdi-file-document-box" /> Certificado
          </button>
          <button
            className={`cir-act-btn cir-act-print ${printed ? 'active' : ''}`}
            title={printed ? 'Impreso — clic para desmarcar' : 'Imprimir protocolo'}
            onClick={() => {
              if (estado !== 'revisado' && !printed) {
                window.Swal?.fire({
                  icon: 'warning',
                  title: 'Pendiente revisión',
                  text: 'Debe revisar el protocolo antes de imprimir.',
                });
                return;
              }
              onPrintToggle({ ...row, _wasPrinted: printed }, !printed);
            }}
          >
            {printed && <i className="mdi mdi-check cir-print-check" />}
            <i className="mdi mdi-printer" /> Imprimir
          </button>
        </div>
      </td>
    </tr>
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
