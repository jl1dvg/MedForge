import React, { useState } from 'react';
import { AFILIACIONES } from './catalog';
import { fmtDate, deadlineInfo } from './helpers';
import { Badge, PrioPill, TipoChip, EmptyState } from './components';

function afilBadgeTone(cat) {
  return { publico: 'info', privado: 'primary', particular: 'soft', fundacional: 'success', otros: 'soft' }[cat] || 'soft';
}

// ---- Download PDF helper -------------------------------------------
function downloadPdf(url, payload, btnSetter) {
  btnSetter(true);
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  fetch(url, {
    method: 'POST',
    credentials: 'same-origin',
    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json, application/pdf', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify(payload),
  })
    .then((r) => {
      if (!r.ok) return r.json().then((d) => { throw new Error(d?.error || 'Error al generar PDF'); });
      const cd = r.headers.get('Content-Disposition') || '';
      const m = cd.match(/filename="?([^";]+)"?/i);
      const filename = m ? m[1] : 'informe.pdf';
      return r.blob().then((blob) => ({ blob, filename }));
    })
    .then(({ blob, filename }) => {
      const url2 = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url2; a.download = filename;
      document.body.appendChild(a); a.click(); a.remove();
      URL.revokeObjectURL(url2);
    })
    .catch((e) => alert(e.message || 'No se pudo generar el PDF'))
    .finally(() => btnSetter(false));
}

// ---- Bulk action bar -----------------------------------------------
export function BulkBar({ tab, count, selectedRows, onSendBandeja, onPrint, onClear }) {
  const [loading012B, setLoading012B] = useState(false);
  const [loading012A, setLoading012A] = useState(false);

  if (count === 0) return null;

  const informadosSelected = (selectedRows || []).filter((r) => r.informado);

  const print012B = () => {
    const items = informadosSelected.map((r) => ({
      id: r.id, form_id: r.form_id, hc_number: r.hc_number,
      fecha_examen: r.fecha_examen, tipo_examen: r.tipo_examen || r.tipo_label, estado_agenda: r.estado_agenda,
    }));
    if (!items.length) { alert('Selecciona exámenes informados para imprimir 012B.'); return; }
    downloadPdf('/v2/reports/imagenes/012b/paquete/seleccion', { items }, setLoading012B);
  };

  const print012A = () => {
    const items = informadosSelected.map((r) => ({
      id: r.id, form_id: r.form_id, hc_number: r.hc_number,
      fecha_examen: r.fecha_examen, tipo_examen: r.tipo_examen || r.tipo_label, estado_agenda: r.estado_agenda,
    }));
    if (!items.length) { alert('Selecciona exámenes informados para imprimir 012A.'); return; }
    downloadPdf('/v2/reports/imagenes/012a/pdf', { items }, setLoading012A);
  };

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
      {tab === 'informados' && informadosSelected.length > 0 && (
        <>
          <button className="imr-btn imr-btn-outline-primary imr-btn-sm" onClick={print012B} disabled={loading012B}>
            <i className={`mdi ${loading012B ? 'mdi-loading mdi-spin' : 'mdi-file-document-outline'}`}></i> 012B
          </button>
          <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={print012A} disabled={loading012A}>
            <i className={`mdi ${loading012A ? 'mdi-loading mdi-spin' : 'mdi-file-outline'}`}></i> 012A
          </button>
        </>
      )}
      <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={onPrint}>
        <i className="mdi mdi-printer"></i> Imprimir lista
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

// ---- Patient-grouped rows for Informados tab -----------------------
function PatientGroupHeader({ label, count, groupRows, selectedIds, onToggle, colSpan }) {
  const [loadingPdf, setLoadingPdf] = useState(false);
  const allSelected = groupRows.every((r) => selectedIds.has(r.id));

  const buildItems = (rows) => rows.map((r) => ({
    id: r.id, form_id: r.form_id, hc_number: r.hc_number,
    fecha_examen: r.fecha_examen, tipo_examen: r.tipo_examen || r.tipo_label, estado_agenda: r.estado_agenda,
  }));

  const downloadGroup = (fechaDoc) => {
    const payload = { items: buildItems(groupRows) };
    if (fechaDoc) payload.fecha_documento = fechaDoc;
    downloadPdf('/v2/reports/imagenes/012b/paquete/seleccion', payload, setLoadingPdf);
  };

  const promptDate = () => {
    const val = window.prompt('Fecha del documento (YYYY-MM-DD):', new Date().toISOString().slice(0, 10));
    if (val && val.trim()) downloadGroup(val.trim());
  };

  return (
    <tr className="imr-patient-group-row">
      <td className="imr-col-check">
        <input type="checkbox" className="imr-check" checked={allSelected}
          onChange={() => groupRows.forEach((r) => { if (allSelected !== selectedIds.has(r.id)) onToggle(r.id); else if (!selectedIds.has(r.id)) onToggle(r.id); })}
          onClick={(e) => { e.stopPropagation(); const sel = !allSelected; groupRows.forEach((r) => { if (sel !== selectedIds.has(r.id)) onToggle(r.id); }); }}
          readOnly
        />
      </td>
      <td colSpan={colSpan - 1}>
        <div className="imr-group-head">
          <span className="imr-group-label"><i className="mdi mdi-account-outline"></i> {label}</span>
          <span className="imr-group-count">{count} examen{count !== 1 ? 'es' : ''}</span>
          <div className="imr-group-actions">
            <button className="imr-btn imr-btn-outline-primary imr-btn-sm" disabled={loadingPdf} onClick={() => downloadGroup(null)}>
              <i className={`mdi ${loadingPdf ? 'mdi-loading mdi-spin' : 'mdi-file-document-multiple-outline'}`}></i> Descargar PDF paciente
            </button>
            <button className="imr-btn imr-btn-ghost imr-btn-sm" onClick={promptDate}>
              <i className="mdi mdi-calendar-edit"></i> Con cambio de fecha
            </button>
          </div>
        </div>
      </td>
    </tr>
  );
}

function renderGrouped(rows, selectedIds, onToggle, handlers) {
  const groups = [];
  const groupMap = {};
  rows.forEach((r) => {
    const key = r.hc_number || r.full_name || 'sin-id';
    if (!groupMap[key]) { groupMap[key] = []; groups.push(key); }
    groupMap[key].push(r);
  });
  const { today, tab, onInformar, onVerImagenes, onMarcarUrgente, onQuitarBandeja, onPrint } = handlers;
  const elements = [];
  groups.forEach((key) => {
    const groupRows = groupMap[key];
    const first = groupRows[0];
    const label = first.full_name ? `${first.full_name} · HC ${first.hc_number}` : `HC ${key}`;
    elements.push(
      <PatientGroupHeader key={`grp-${key}`} label={label} count={groupRows.length}
        groupRows={groupRows} selectedIds={selectedIds} onToggle={onToggle} colSpan={8} />
    );
    groupRows.forEach((row) => elements.push(
      <Row key={row.id} row={row} tab={tab} today={today}
        selected={selectedIds.has(row.id)} onToggle={() => onToggle(row.id)}
        onInformar={onInformar} onVerImagenes={onVerImagenes}
        onMarcarUrgente={onMarcarUrgente} onQuitarBandeja={onQuitarBandeja} onPrint={onPrint} />
    ));
  });
  return elements;
}

// ---- Table ---------------------------------------------------------
export function ExamTable({ rows, tab, today, selectedIds, onToggleAll, onToggle, onInformar, onVerImagenes, onMarcarUrgente, onQuitarBandeja, onPrint }) {
  const [sortKey, setSortKey] = useState(null);
  const [sortDir, setSortDir] = useState('asc');

  const lastCol = { 'no-informados': 'Archivos', 'bandeja': 'Prioridad', 'informados': 'Informe', 'sin-nas': 'Archivos' }[tab];
  const allChecked = rows.length > 0 && rows.every((r) => selectedIds.has(r.id));

  const toggleSort = (key) => {
    if (sortKey === key) setSortDir((d) => d === 'asc' ? 'desc' : 'asc');
    else { setSortKey(key); setSortDir('asc'); }
  };

  const sortedRows = sortKey ? [...rows].sort((a, b) => {
    let va = a[sortKey] || '', vb = b[sortKey] || '';
    if (typeof va === 'string') va = va.toLowerCase();
    if (typeof vb === 'string') vb = vb.toLowerCase();
    return sortDir === 'asc' ? (va < vb ? -1 : va > vb ? 1 : 0) : (va > vb ? -1 : va < vb ? 1 : 0);
  }) : rows;

  const SortTh = ({ label, skey, style }) => {
    const active = sortKey === skey;
    return (
      <th style={{ cursor: 'pointer', userSelect: 'none', whiteSpace: 'nowrap', ...(style || {}) }} onClick={() => toggleSort(skey)}>
        {label}
        <i className={`mdi imr-sort-ico ${active ? (sortDir === 'asc' ? 'mdi-chevron-up' : 'mdi-chevron-down') : 'mdi-chevron-up'}`}
          style={{ opacity: active ? 1 : 0.25, marginLeft: 3, fontSize: 14 }}></i>
      </th>
    );
  };

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
            <SortTh label="Fecha" skey="fecha_examen" />
            <th>Afiliación</th>
            <SortTh label="Paciente" skey="full_name" />
            <SortTh label="Examen" skey="tipo_label" />
            <SortTh label="Ojo" skey="ojo" />
            <th>{lastCol}</th>
            <th style={{ textAlign: 'right' }}>Acciones</th>
          </tr>
        </thead>
        <tbody>
          {tab === 'informados'
            ? renderGrouped(sortedRows, selectedIds, onToggle, { onInformar, onVerImagenes, onMarcarUrgente, onQuitarBandeja, onPrint, today, tab })
            : sortedRows.map((row) => (
                <Row key={row.id} row={row} tab={tab} today={today}
                  selected={selectedIds.has(row.id)}
                  onToggle={() => onToggle(row.id)}
                  onInformar={onInformar}
                  onVerImagenes={onVerImagenes}
                  onMarcarUrgente={onMarcarUrgente}
                  onQuitarBandeja={onQuitarBandeja}
                  onPrint={onPrint}
                />
              ))
          }
        </tbody>
      </table>
    </div>
  );
}
