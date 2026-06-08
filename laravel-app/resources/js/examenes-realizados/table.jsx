import React from 'react';
import { AFILIACIONES } from './catalog';
import { fmtDate, deadlineInfo } from './helpers';
import { Badge, PrioPill, TipoChip, EmptyState } from './components';

function afilBadgeTone(cat) {
  return { publico: 'info', privado: 'primary', particular: 'soft', fundacional: 'success', otros: 'soft' }[cat] || 'soft';
}

// ---- Bulk action bar -----------------------------------------------
export function BulkBar({ tab, count, onSendBandeja, onPrint, onClear }) {
  if (count === 0) return null;
  return (
    <div className="imr-bulk-bar">
      <i className="mdi mdi-checkbox-multiple-marked-outline" style={{ fontSize: 18, color: 'var(--accent)' }}></i>
      <span><b>{count}</b> seleccionado{count !== 1 ? 's' : ''}</span>
      <div className="imr-bulk-spacer"></div>
      {(tab === 'no-informados' || tab === 'bandeja') && (
        <button className="imr-btn imr-btn-outline-danger imr-btn-sm" onClick={onSendBandeja}>
          <i className="mdi mdi-bell-plus-outline"></i>
          {tab === 'bandeja' ? 'Cambiar prioridad' : 'Enviar a bandeja prioritaria'}
        </button>
      )}
      <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={onPrint}>
        <i className="mdi mdi-printer"></i> Imprimir / exportar
      </button>
      <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={onClear}>
        <i className="mdi mdi-close"></i> Quitar selección
      </button>
    </div>
  );
}

// ---- Table row -----------------------------------------------------
function Row({ row, tab, today, selected, onToggle, onInformar, onVerImagenes, onMarcarUrgente, onQuitarBandeja, onPrint }) {
  const afil = AFILIACIONES.find((a) => a.value === row.afiliacion);
  const dl = row.fecha_limite ? deadlineInfo(row.fecha_limite, today) : null;
  const overdue = dl && dl.state === 'over';
  const rowClass = tab === 'bandeja' ? (overdue ? 'imr-row-overdue' : 'imr-row-urgent') : '';

  return (
    <tr className={rowClass}>
      <td className="imr-col-check">
        <input type="checkbox" className="imr-check" checked={selected} onChange={onToggle} />
      </td>
      <td className="imr-cell-fecha">
        {fmtDate(row.fecha_examen)}
        <small>{row.estado_agenda}</small>
      </td>
      <td>
        <span className={`imr-badge imr-badge-${afilBadgeTone(afil ? afil.cat : row.afiliacion_cat || 'otros')} imr-afil-badge`}>
          {afil ? afil.label : row.afiliacion}
        </span>
        <div style={{ fontSize: 11, color: 'var(--fg-mute)', marginTop: 3 }}>{row.sede}</div>
      </td>
      <td className="imr-cell-paciente">
        {row.full_name}
        <div className="imr-ced">CC {row.cedula} · HC {row.hc_number}</div>
      </td>
      <td><TipoChip tipoKey={row.tipo_key} /></td>
      <td>{row.ojo}</td>

      {tab === 'bandeja' ? (
        <td>
          <PrioPill prioridad={row.prioridad} overdue={overdue} />
          {row.fecha_limite && (
            <div className={`imr-deadline ${dl.state === 'over' ? 'over' : dl.state === 'soon' ? 'soon' : ''}`}>
              <i className="mdi mdi-calendar-clock" style={{ fontSize: 13 }}></i> {dl.label} · {fmtDate(row.fecha_limite)}
            </div>
          )}
          {row.responsable && <div style={{ fontSize: 11.5, color: 'var(--fg-mute)', marginTop: 2 }}>{row.responsable}</div>}
        </td>
      ) : tab === 'informados' ? (
        <td>
          <span className="imr-badge imr-badge-success"><i className="mdi mdi-check"></i> {row.informe_id}</span>
          <div style={{ fontSize: 11.5, color: 'var(--fg-mute)', marginTop: 3 }}>
            {row.informado_por} · {fmtDate(row.informado_fecha)}
          </div>
        </td>
      ) : (
        <td>
          {row.nas_status === 'con-archivos'
            ? <span className="imr-badge imr-badge-success"><i className="mdi mdi-folder-image"></i> {row.nas_files_count} archivo{row.nas_files_count !== 1 ? 's' : ''}</span>
            : <span className="imr-badge imr-badge-warning"><i className="mdi mdi-folder-remove-outline"></i> Sin archivos</span>}
          {tab === 'no-informados' && row.prioridad && (
            <div style={{ marginTop: 4 }}><PrioPill prioridad={row.prioridad} overdue={overdue} /></div>
          )}
        </td>
      )}

      <td>
        <div className="imr-row-actions">
          {row.nas_status === 'con-archivos' && (
            <button className="imr-btn imr-btn-ghost imr-btn-sm" title="Ver imágenes del NAS" onClick={() => onVerImagenes(row)}>
              <i className="mdi mdi-folder-image"></i>
            </button>
          )}
          {tab === 'informados' ? (
            <>
              <button className="imr-btn imr-btn-outline-primary imr-btn-sm" onClick={() => onInformar(row)} title="Ver informe">
                <i className="mdi mdi-file-eye-outline"></i> Ver
              </button>
              <button className="imr-btn imr-btn-ghost imr-btn-sm" title="Imprimir informe" onClick={() => onPrint(row)}>
                <i className="mdi mdi-printer"></i>
              </button>
            </>
          ) : tab === 'sin-nas' ? (
            <button className="imr-btn imr-btn-ghost imr-btn-sm" title="Reclamar archivos al área técnica">
              <i className="mdi mdi-folder-search-outline"></i> Reclamar
            </button>
          ) : tab === 'bandeja' ? (
            <>
              <button className="imr-btn imr-btn-primary imr-btn-sm" onClick={() => onInformar(row)}>
                <i className="mdi mdi-file-document-edit-outline"></i> Informar
              </button>
              <button className="imr-btn imr-btn-ghost imr-btn-sm" title="Editar prioridad" onClick={() => onMarcarUrgente(row)}>
                <i className="mdi mdi-pencil-outline"></i>
              </button>
              <button className="imr-btn imr-btn-ghost imr-btn-sm" title="Quitar de la bandeja" onClick={() => onQuitarBandeja(row)}>
                <i className="mdi mdi-bell-off-outline"></i>
              </button>
            </>
          ) : (
            <>
              <button className="imr-btn imr-btn-primary imr-btn-sm" onClick={() => onInformar(row)}>
                <i className="mdi mdi-file-document-edit-outline"></i> Informar
              </button>
              <button
                className={`imr-btn imr-btn-sm ${row.prioridad ? 'imr-btn-ghost' : 'imr-btn-outline-danger'}`}
                title={row.prioridad ? 'En bandeja prioritaria — editar' : 'Marcar urgente / pronto'}
                onClick={() => onMarcarUrgente(row)}>
                <i className={`mdi ${row.prioridad ? 'mdi-bell-check-outline' : 'mdi-bell-plus-outline'}`}></i>
              </button>
            </>
          )}
        </div>
      </td>
    </tr>
  );
}

// ---- Table ---------------------------------------------------------
export function ExamTable({ rows, tab, today, selectedIds, onToggleAll, onToggle, onInformar, onVerImagenes, onMarcarUrgente, onQuitarBandeja, onPrint }) {
  const lastCol = { 'no-informados': 'Archivos', 'bandeja': 'Prioridad', 'informados': 'Informe', 'sin-nas': 'Archivos' }[tab];
  const allChecked = rows.length > 0 && rows.every((r) => selectedIds.has(r.id));

  if (rows.length === 0) {
    const empties = {
      'no-informados': ['mdi-check-all', 'Todo al día', 'No hay exámenes pendientes de informe con los filtros actuales.'],
      'bandeja': ['mdi-bell-outline', 'Bandeja vacía', 'Marca un examen como Urgente o Pronto desde «Por informar» y aparecerá aquí.'],
      'informados': ['mdi-file-outline', 'Sin informes', 'Aún no hay informes firmados con los filtros actuales.'],
      'sin-nas': ['mdi-folder-check-outline', 'Todo escaneado', 'No hay procedimientos sin archivos en el NAS.'],
    }[tab];
    return <EmptyState icon={empties[0]} title={empties[1]} text={empties[2]} />;
  }

  return (
    <div className="imr-table-scroll">
      <table className="imr-table">
        <thead>
          <tr>
            <th className="imr-col-check">
              <input type="checkbox" className="imr-check" checked={allChecked} onChange={onToggleAll} />
            </th>
            <th>Fecha</th>
            <th>Afiliación</th>
            <th>Paciente</th>
            <th>Examen</th>
            <th>Ojo</th>
            <th>{lastCol}</th>
            <th style={{ textAlign: 'right' }}>Acciones</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <Row key={row.id} row={row} tab={tab} today={today}
              selected={selectedIds.has(row.id)}
              onToggle={() => onToggle(row.id)}
              onInformar={onInformar}
              onVerImagenes={onVerImagenes}
              onMarcarUrgente={onMarcarUrgente}
              onQuitarBandeja={onQuitarBandeja}
              onPrint={onPrint}
            />
          ))}
        </tbody>
      </table>
    </div>
  );
}
