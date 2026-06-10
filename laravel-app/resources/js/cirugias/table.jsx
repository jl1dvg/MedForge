import React from 'react';
import { afilOf, afilBadgeTone, AuditPill, StatusPill, ProcChip, EmptyState } from './components';

function fmtDate(dateStr) {
  if (!dateStr) return '—';
  // backend returns dd/mm/yyyy already; accept both formats
  if (/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) {
    const [y, m, d] = dateStr.split('-');
    return `${d}-${m}-${y}`;
  }
  return dateStr;
}

// ---- Barra de acciones masivas ---------------------------------
export function BulkBar({ tab, count, onPrint, onClear }) {
  if (count === 0) return null;
  return (
    <div className="bulk-bar">
      <i className="mdi mdi-checkbox-multiple-marked-outline" style={{ fontSize: 18, color: 'var(--accent)' }} />
      <span><b>{count}</b> seleccionada{count !== 1 ? 's' : ''}</span>
      <div className="spacer" />
      <button className="btn btn-ghost btn-sm" onClick={onPrint}>
        <i className="mdi mdi-printer" /> Imprimir / exportar protocolos
      </button>
      <button className="btn btn-ghost btn-sm" onClick={onClear}>
        <i className="mdi mdi-close" /> Quitar selección
      </button>
    </div>
  );
}

// ---- Fila -------------------------------------------------------
function Row({ row, tab, num, selected, onToggle, onVerProtocolo, onRevisar, onPrint, onCertificado }) {
  const afil = afilOf(row.afiliacion_label || row.afiliacion);
  const afilLabel = row.afiliacion_label || (afil ? afil.label : row.afiliacion) || 'Sin convenio';
  const afilCat = afil ? afil.cat : '';
  const isAlert = (tab === 'auditoria') || (row.audit && row.audit.status === 'error' && row.status !== 1 && row.protocolo_iniciado);
  const rowClass = isAlert ? 'row-alert' : '';

  return (
    <tr className={rowClass}>
      <td className="col-check"><input type="checkbox" className="check" checked={selected} onChange={onToggle} /></td>
      <td className="cell-num">{String(num).padStart(2, '0')}</td>
      <td className="cell-fecha">
        {fmtDate(row.fecha_inicio)}
        <small>{row.sede || ''}</small>
      </td>
      <td>
        <span className={`badge badge-${afilBadgeTone(afilCat)} afil-badge`}>{afilLabel}</span>
        {row.afiliacion_categoria && (
          <div style={{ fontSize: 11, color: 'var(--fg-mute)', marginTop: 2 }}>
            {row.afiliacion_categoria}
          </div>
        )}
      </td>
      <td className="cell-paciente">
        {row.full_name}
        <div className="ced">
          HC {row.hc_number}{row.edad != null ? ` · ${row.edad}a` : ''}
        </div>
      </td>
      <td><ProcChip row={row} /></td>
      <td><span className="lat-tag">{row.lateralidad || '—'}</span></td>

      {tab === 'revisados' ? (
        <td>
          <StatusPill row={row} />
          <div className={`print-dot ${row.printed ? 'printed' : ''}`} style={{ marginTop: 4 }}>
            <i className={`mdi ${row.printed ? 'mdi-printer-check' : 'mdi-printer-outline'}`} />
            {row.printed ? 'Impreso' : 'Sin imprimir'}
          </div>
        </td>
      ) : tab === 'sin-protocolo' ? (
        <td><StatusPill row={row} /></td>
      ) : (
        <td>
          <AuditPill audit={row.audit} />
          {row.audit && row.audit.status !== 'ok' && (
            <div style={{ fontSize: 11.5, color: 'var(--fg-mute)', marginTop: 4 }}>
              {(row.audit.summary?.error || 0) > 0 ? `${row.audit.summary.error} alerta(s) · ` : ''}{row.audit.summary?.warning || 0} advertencia(s)
            </div>
          )}
        </td>
      )}

      <td>
        <div className="row-actions">
          {tab === 'revisados' ? (
            <>
              {row.protocolo_iniciado && <button className="btn btn-ghost btn-xs" title="Ver protocolo" onClick={() => onVerProtocolo(row)}><i className="mdi mdi-file-document-outline" /></button>}
              <button className="btn btn-outline-primary btn-xs" title="Reabrir y editar en wizard" onClick={() => onRevisar(row)}><i className="mdi mdi-pencil-outline" /></button>
              <button className="btn btn-ghost btn-xs" title="Certificado de descanso" onClick={() => onCertificado(row)}><i className="mdi mdi-file-certificate-outline" /></button>
              <button className="btn btn-ghost btn-xs" title="Imprimir protocolo" onClick={() => onPrint(row)}><i className="mdi mdi-printer" /></button>
            </>
          ) : tab === 'sin-protocolo' ? (
            <button className="btn btn-primary btn-sm" onClick={() => onRevisar(row)}>
              <i className="mdi mdi-clipboard-edit-outline" /> Redactar
            </button>
          ) : (
            <>
              <button className="btn btn-primary btn-sm" onClick={() => onVerProtocolo(row)}>
                <i className="mdi mdi-shield-search" /> Revisar
              </button>
              <button className="btn btn-ghost btn-xs" title="Editar en wizard" onClick={() => onRevisar(row)}><i className="mdi mdi-pencil-outline" /></button>
            </>
          )}
        </div>
      </td>
    </tr>
  );
}

// ---- Tabla ------------------------------------------------------
export function CirTable({ rows, tab, selectedIds, onToggleAll, onToggle, loading, error, ...handlers }) {
  const lastCol = { 'por-revisar': 'Auditoría', 'auditoria': 'Auditoría', 'revisados': 'Estado', 'sin-protocolo': 'Estado' }[tab] || 'Estado';
  const allChecked = rows.length > 0 && rows.every((r) => selectedIds.has(r.id));

  if (loading) {
    return (
      <div className="tbl-loading">
        <div className="spin" />
        <div>Cargando cirugías…</div>
      </div>
    );
  }

  if (error) {
    return <div className="tbl-error"><i className="mdi mdi-alert-circle-outline" /> {error}</div>;
  }

  if (rows.length === 0) {
    const empties = {
      'por-revisar': ['mdi-check-all', 'Nada por revisar', 'No hay protocolos pendientes de revisión con los filtros actuales.'],
      'auditoria': ['mdi-shield-check', 'Sin alertas', 'La auditoría no encontró discrepancias en los protocolos filtrados.'],
      'revisados': ['mdi-clipboard-outline', 'Sin revisados', 'Aún no hay protocolos revisados con los filtros actuales.'],
      'sin-protocolo': ['mdi-clipboard-check-outline', 'Todo documentado', 'No hay cirugías sin protocolo redactado.'],
    }[tab] || ['mdi-magnify', 'Sin resultados', 'No hay cirugías con los filtros actuales.'];
    return <EmptyState icon={empties[0]} title={empties[1]} text={empties[2]} />;
  }

  return (
    <div className="table-scroll">
      <table className="cirtab">
        <thead>
          <tr>
            <th className="col-check"><input type="checkbox" className="check" checked={allChecked} onChange={onToggleAll} /></th>
            <th>No.</th>
            <th>Fecha</th>
            <th>Afiliación</th>
            <th>Paciente</th>
            <th>Procedimiento</th>
            <th>Lat.</th>
            <th>{lastCol}</th>
            <th style={{ textAlign: 'right' }}>Acciones</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <Row key={row.id} row={row} tab={tab} num={i + 1}
              selected={selectedIds.has(row.id)}
              onToggle={() => onToggle(row.id)}
              {...handlers} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
