import React, { useState, useRef, useCallback, useEffect } from 'react';
import type { Patient } from '../types';
import { MEDICO_MAP, SEDE_MAP, AFIL_MAP } from '../data';
import { fmtDate, fmtDateShort, fmtDateLong, fmtTime, fmtDateTime, fmtMoney, hasTime, relDays, phoneHref } from '../utils';
import { Avatar, SedeBadge, AfilChip, SolBadge, EmptyMini, Section } from '../components';
import { fetchPatientSection } from '../api';

interface SectionState {
  loaded: boolean;
  loading: boolean;
  rows: any[];
  summary: any;
  error: string | null;
}

interface Props {
  p: Patient;
  onBack: () => void;
  onAgendar: (p: Patient) => void;
  onWhats: (p: Patient) => void;
  onNuevaSolicitud: (p: Patient) => void;
  onEditar: (p: Patient) => void;
  onAddNote: (id: number, txt: string) => void;
  onOpenCRM: (s: any) => void;
}

const SECTION_TO_API: Record<string, string> = {
  agenda: 'agenda', solicitudes: 'solicitudes', examenes: 'examenes',
  consultas: 'consultas', protocolos: 'protocolos', prefacturas: 'prefacturas',
  derivaciones: 'derivaciones', recetas: 'recetas', crm: 'crm',
};

const SECTIONS = [
  { id: 'personales',    icon: 'mdi-card-account-details-outline', title: 'Datos personales',       color: '#5156be', bg: '#eeeefc' },
  { id: 'agenda',        icon: 'mdi-calendar-clock-outline',       title: 'Historial de citas',      color: '#0c6fb0', bg: '#e0f2fe' },
  { id: 'solicitudes',   icon: 'mdi-clipboard-text-clock-outline', title: 'Solicitudes activas',     color: '#ea580c', bg: '#fff1ea' },
  { id: 'examenes',      icon: 'mdi-image-multiple-outline',       title: 'Resultados y exámenes',   color: '#0d9488', bg: '#e0f7f5' },
  { id: 'consultas',     icon: 'mdi-stethoscope',                  title: 'Historial clínico',       color: '#7c3aed', bg: '#f3eeff' },
  { id: 'protocolos',    icon: 'mdi-hospital-box-outline',         title: 'Protocolos quirúrgicos',  color: '#e11d48', bg: '#fff0f3' },
  { id: 'prefacturas',   icon: 'mdi-receipt-text-outline',         title: 'Facturación',             color: '#475569', bg: '#f1f5f9' },
  { id: 'derivaciones',  icon: 'mdi-transit-transfer',             title: 'Derivaciones IESS',       color: '#d97706', bg: '#fffbeb' },
  { id: 'recetas',       icon: 'mdi-pill',                         title: 'Recetas médicas',         color: '#059669', bg: '#e6faf4' },
  { id: 'comunicaciones',icon: 'mdi-chat-outline',                 title: 'Comunicaciones',          color: '#1f9d7a', bg: '#e7f8f1' },
  { id: 'actividad',     icon: 'mdi-timeline-clock-outline',       title: 'Actividad reciente',      color: '#64748b', bg: '#f1f5f9' },
];

function emptyState(): SectionState {
  return { loaded: false, loading: false, rows: [], summary: {}, error: null };
}

function statusBadgeClass(estado: string): string {
  const e = (estado || '').toLowerCase().trim();
  if (['aprobada', 'completada', 'firmado', 'activa', '1', 'true'].includes(e)) return 'badge-green';
  if (['rechazada', 'cancelada', 'anulada'].includes(e)) return 'badge-red';
  if (['pendiente', 'por aprobar'].includes(e)) return 'badge-warn';
  if (['en proceso', 'agendada', 'en_proceso'].includes(e)) return 'badge-blue';
  return 'badge-neutral';
}

function vigenciaClass(fecha: string): string {
  if (!fecha) return '';
  const diff = (new Date(fecha).getTime() - Date.now()) / 86400000;
  if (diff < 0) return 'vigencia-exp';
  if (diff <= 15) return 'vigencia-warn';
  return 'vigencia-ok';
}

function afiliacionTipoLabel(p: Patient): string {
  const tipo = String(p.tipo_afiliacion || p.afiliacion_info?.categoria || '').toLowerCase().trim();
  if (tipo === 'privado') return 'Privada';
  if (tipo === 'publico') return 'Pública';
  if (tipo === 'particular') return 'Particular';
  if (tipo === 'fundacional') return 'Fundacional';
  if (tipo && AFIL_MAP[tipo]?.label) return AFIL_MAP[tipo].label;
  if (tipo) return tipo.charAt(0).toUpperCase() + tipo.slice(1);
  return '—';
}

function aseguradoraLabel(p: Patient): string {
  return String(p.aseguradora || p.afiliacion_info?.empresa_seguro || '').trim();
}

function SectionSkeleton() {
  return (
    <div className="sec-skeleton">
      {[1, 2, 3].map(i => (
        <div key={i} className="skel-row"><span className="skel" style={{ width: '100%', height: 52, borderRadius: 10 }} /></div>
      ))}
    </div>
  );
}

/* ---- Lightbox ---- */
function Lightbox({ items, index, onClose }: { items: { url: string; nombre: string; tipo: string }[]; index: number; onClose: () => void }) {
  const [cur, setCur] = useState(index);
  const item = items[cur];

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === 'Escape') onClose();
      if (e.key === 'ArrowLeft') setCur(c => Math.max(0, c - 1));
      if (e.key === 'ArrowRight') setCur(c => Math.min(items.length - 1, c + 1));
    };
    window.addEventListener('keydown', handler);
    return () => window.removeEventListener('keydown', handler);
  }, [items.length, onClose]);

  if (!item) return null;
  const isPdf = item.tipo === 'pdf' || item.url?.toLowerCase().endsWith('.pdf');

  return (
    <div className="lightbox-backdrop" onClick={onClose}>
      <div className="lightbox-shell" onClick={e => e.stopPropagation()}>
        <div className="lb-top">
          <span className="lb-name"><i className={`mdi ${isPdf ? 'mdi-file-pdf-box' : 'mdi-image-outline'}`} />{item.nombre}</span>
          <div className="lb-actions">
            <a href={item.url} target="_blank" rel="noreferrer" className="lb-btn" title="Abrir en nueva pestaña"><i className="mdi mdi-open-in-new" /></a>
            <button className="lb-btn" onClick={onClose} title="Cerrar"><i className="mdi mdi-close" /></button>
          </div>
        </div>
        <div className="lb-body">
          {isPdf
            ? <iframe src={item.url} className="lb-pdf" title={item.nombre} />
            : <img src={item.url} className="lb-img" alt={item.nombre} />
          }
        </div>
        {items.length > 1 && (
          <div className="lb-nav">
            <button className="lb-nav-btn" disabled={cur === 0} onClick={() => setCur(c => c - 1)}><i className="mdi mdi-chevron-left" /></button>
            <span className="lb-counter">{cur + 1} / {items.length}</span>
            <button className="lb-nav-btn" disabled={cur === items.length - 1} onClick={() => setCur(c => c + 1)}><i className="mdi mdi-chevron-right" /></button>
          </div>
        )}
      </div>
    </div>
  );
}

/* ---- Agenda ---- */
function SecAgenda({ rows, onAgendar, p }: { rows: any[]; onAgendar: (p: Patient) => void; p: Patient }) {
  if (!rows.length) return <EmptyMini icon="mdi-calendar-blank-outline">Sin citas registradas en la agenda.</EmptyMini>;
  return (
    <div className="agenda-list">
      {rows.map((row, i) => {
        const statusClass = statusBadgeClass(row.estado);
        return (
          <div className="agenda-card" key={row.form_id || i}>
            <span className="ag-card-ic"><i className="mdi mdi-account-tie" /></span>
            <div className="ag-card-main">
              <div className="ag-proc">{row.procedimiento || '—'}</div>
              <div className="ag-doc">
                {row.doctor_avatar && <img src={row.doctor_avatar} className="doctor-avatar" alt="" />}
                <span>{row.doctor || '—'}</span>
                {row.sede && <span className="ag-sede">{row.sede}</span>}
              </div>
              {(row.historial_estados || []).length > 0 && (
                <div className="historial-estados">
                  {(row.historial_estados as any[]).slice(0, 3).map((h: any, hi: number) => (
                    <span key={hi} className="hs-item">{h.estado} · {fmtDateShort(h.fecha_hora_cambio)}</span>
                  ))}
                </div>
              )}
            </div>
            <span className={`badge ${statusClass}`}>{row.estado || '—'}</span>
            <div className="ag-card-when">
              <div className="ag-fecha">{fmtDateShort(row.fecha)}</div>
              {row.hora && <div className="ag-hora">{fmtTime(row.hora)}</div>}
              <div className="ag-rel">{relDays(row.fecha)}</div>
            </div>
            <div className="ag-card-acts">
              <button className="icon-btn" title="Ver detalle"><i className="mdi mdi-eye-outline" /></button>
              <button className="icon-btn" title="Reagendar" onClick={() => onAgendar(p)}><i className="mdi mdi-calendar-edit" /></button>
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ---- Solicitudes ---- */
function SecSolicitudes({ rows, onNueva, onOpenCRM }: { rows: any[]; onNueva: () => void; onOpenCRM: (s: any) => void }) {
  const tipoIcon = (tipo: string) => {
    const t = (tipo || '').toLowerCase();
    if (t.includes('quirurg')) return { icon: 'mdi-hospital-box-outline', color: '#e11d48', bg: '#fff0f3' };
    if (t.includes('lab') || t.includes('examen')) return { icon: 'mdi-flask-outline', color: '#0d9488', bg: '#e0f7f5' };
    if (t.includes('imagen')) return { icon: 'mdi-image-outline', color: '#0c6fb0', bg: '#e0f2fe' };
    return { icon: 'mdi-clipboard-text-outline', color: '#ea580c', bg: '#fff1ea' };
  };

  if (!rows.length) return (
    <>
      <EmptyMini icon="mdi-clipboard-text-outline">Sin solicitudes de procedimientos.</EmptyMini>
      <button className="wbtn primary" style={{ marginTop: 14, width: '100%', height: 44 }} onClick={onNueva}><i className="mdi mdi-plus" />Crear nueva solicitud</button>
    </>
  );
  return (
    <>
      <div className="sol-list">
        {rows.map((row, i) => {
          const t = tipoIcon(row.tipo_solicitud || row.tipo || '');
          return (
            <div className="sol-card" key={row.id || i}>
              <span className="sol-card-ic" style={{ background: t.bg, color: t.color }}><i className={`mdi ${t.icon}`} /></span>
              <div className="sol-card-body">
                <div className="sc-id-row">
                  <span className="sc-id">SOL-{row.id || '—'}</span>
                  {row.tipo_solicitud && <span className="sc-tipo">{row.tipo_solicitud}</span>}
                </div>
                <div className="sc-proc">{row.procedimiento || '—'}</div>
                <div className="sc-meta">
                  <span>{row.doctor || '—'}</span>
                  <span>·</span>
                  <span>{fmtDate(row.fecha)}</span>
                  {row.prioridad && <><span>·</span><span>{row.prioridad}</span></>}
                </div>
              </div>
              {row.ojo && <span className="sr-ojo">{row.ojo}</span>}
              <span className={`badge ${statusBadgeClass(row.estado)}`}>{row.estado || '—'}</span>
              <button className="sc-crm-btn" onClick={() => onOpenCRM(row)} title="Ver en CRM">
                Ver en CRM <i className="mdi mdi-arrow-top-right" />
              </button>
            </div>
          );
        })}
      </div>
      <button className="wbtn" style={{ marginTop: 14, width: '100%', height: 42 }} onClick={onNueva}><i className="mdi mdi-plus" />Crear nueva solicitud</button>
    </>
  );
}

/* ---- Exámenes ---- */
function SecExamenes({ rows }: { rows: any[] }) {
  const [lightbox, setLightbox] = useState<{ items: any[]; index: number } | null>(null);
  const [filesByRow, setFilesByRow] = useState<Record<string, any[]>>({});
  const [loadingFiles, setLoadingFiles] = useState<Record<string, boolean>>({});

  useEffect(() => {
    rows.forEach((row, i) => {
      const key = String(row.form_id || row.id || i);
      const listUrl = row.links?.archivos_list;
      if (!listUrl || filesByRow[key] || loadingFiles[key]) return;

      setLoadingFiles(prev => ({ ...prev, [key]: true }));
      fetch(listUrl, { credentials: 'same-origin', headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } })
        .then(res => res.ok ? res.json() : { files: [] })
        .then(data => setFilesByRow(prev => ({ ...prev, [key]: Array.isArray(data.files) ? data.files : [] })))
        .catch(() => setFilesByRow(prev => ({ ...prev, [key]: [] })))
        .finally(() => setLoadingFiles(prev => ({ ...prev, [key]: false })));
    });
  }, [rows, filesByRow, loadingFiles]);

  if (!rows.length) return <EmptyMini icon="mdi-image-off-outline">Sin exámenes solicitados.</EmptyMini>;

  const fileItems = rows.flatMap((row, i) => {
    const key = String(row.form_id || row.id || i);
    return (filesByRow[key] || []).map((file: any) => ({
      url: file.url || '',
      nombre: file.name || row.examen || row.nombre || 'Archivo',
      tipo: file.type || file.ext || (String(file.url || '').toLowerCase().endsWith('.pdf') ? 'pdf' : 'img'),
      rowKey: key,
    }));
  }).filter(item => item.url);

  return (
    <>
      <div className="exam-grid">
        {rows.map((row, i) => {
          const key = String(row.form_id || row.id || i);
          const rowFiles = filesByRow[key] || [];
          const firstFile = rowFiles.find((file: any) => file.url) || null;
          const fileUrl = firstFile?.url || row.url || row.archivo || '';
          const isPdf = String(firstFile?.type || firstFile?.ext || row.tipo_archivo || fileUrl).toLowerCase().includes('pdf');
          const hasFile = !!fileUrl;
          const lbIdx = fileItems.findIndex(item => item.rowKey === key);

          return (
            <div
              className={`exam-card ${hasFile ? 'has-file' : ''}`}
              key={row.id || i}
              onClick={() => hasFile && lbIdx >= 0 && setLightbox({ items: fileItems, index: lbIdx })}
            >
              <div className="ec-thumb">
                <span className={`ec-type-badge ${isPdf ? 'pdf' : 'img'}`}>{isPdf ? 'PDF' : 'IMG'}</span>
                {loadingFiles[key]
                  ? <i className="mdi mdi-loading mdi-spin ec-placeholder-ic" />
                  : hasFile && fileUrl && !isPdf
                    ? <img src={fileUrl} alt={row.examen} className="ec-img" onError={e => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                    : <i className={`mdi ${isPdf ? 'mdi-file-pdf-box' : 'mdi-image-outline'} ec-placeholder-ic`} />
                }
                {hasFile && <span className="ec-overlay"><i className="mdi mdi-magnify-plus-outline" /></span>}
              </div>
              <div className="ec-info">
                <div className="ec-name">{row.examen || row.nombre || '—'}</div>
                <div className="ec-meta">
                  {fmtDateShort(row.fecha)}{row.doctor ? ` · ${row.doctor}` : ''}
                  {rowFiles.length > 0 ? ` · ${rowFiles.length} archivo${rowFiles.length > 1 ? 's' : ''}` : ''}
                </div>
              </div>
              {row.estado && <span className={`badge ${statusBadgeClass(row.estado)}`} style={{ position: 'absolute', top: 6, right: 6, fontSize: 10 }}>{row.estado}</span>}
            </div>
          );
        })}
      </div>
      {lightbox && (
        <Lightbox items={lightbox.items} index={lightbox.index} onClose={() => setLightbox(null)} />
      )}
    </>
  );
}

/* ---- Consultas ---- */
function SecConsultas({ rows, onAddNote, patientId }: { rows: any[]; onAddNote: (id: number, txt: string) => void; patientId: number }) {
  const [expanded, setExpanded] = useState<string | null>(null);
  const [val, setVal] = useState('');
  return (
    <>
      {!rows.length
        ? <EmptyMini icon="mdi-stethoscope">Sin consultas clínicas registradas.</EmptyMini>
        : (
          <div>
            {rows.map((row, i) => {
              const key = row.form_id || String(i);
              const isOpen = expanded === key;
              const title = row.motivo_consulta || row.enfermedad_actual || row.examen_fisico || row.plan || 'Consulta clínica';
              const diagnosticos = Array.isArray(row.diagnosticos)
                ? row.diagnosticos.filter(Boolean)
                : (row.diagnosticos ? [String(row.diagnosticos)] : []);
              return (
                <div className="consulta-card" key={key}>
                  <div className="consulta-head" onClick={() => setExpanded(isOpen ? null : key)}>
                    <span className="consulta-ic"><i className="mdi mdi-stethoscope" /></span>
                    <div className="consulta-head-txt">
                      <div className="consulta-motivo">{title}</div>
                      <div className="consulta-fecha-doc">{row.doctor || ''}{row.doctor ? ' · ' : ''}{fmtDateShort(row.fecha)}</div>
                    </div>
                    <i className={`mdi ${isOpen ? 'mdi-chevron-up' : 'mdi-chevron-down'} consulta-chev`} />
                  </div>
                  {isOpen && (
                    <div className="consulta-body">
                      {row.motivo_consulta && <div className="consulta-field"><div className="cf-label">Motivo de consulta</div><div className="cf-val">{row.motivo_consulta}</div></div>}
                      {row.enfermedad_actual && <div className="consulta-field"><div className="cf-label">Enfermedad actual</div><div className="cf-val">{row.enfermedad_actual}</div></div>}
                      {row.examen_fisico && <div className="consulta-field"><div className="cf-label">Examen físico</div><div className="cf-val">{row.examen_fisico}</div></div>}
                      {row.plan && <div className="consulta-field"><div className="cf-label">Plan de tratamiento</div><div className="cf-val">{row.plan}</div></div>}
                      {diagnosticos.length > 0 && (
                        <div className="consulta-field">
                          <div className="cf-label">Diagnósticos</div>
                          <ul className="cf-val consulta-dx-list">
                            {diagnosticos.map((dx: string, dxIndex: number) => <li key={dxIndex}>{dx}</li>)}
                          </ul>
                        </div>
                      )}
                    </div>
                  )}
                </div>
              );
            })}
          </div>
        )
      }
      <form className="note-form" onSubmit={e => { e.preventDefault(); if (val.trim()) { onAddNote(patientId, val.trim()); setVal(''); } }}>
        <input
          className="note-input"
          placeholder="Añadir nota clínica…"
          value={val}
          onChange={e => setVal(e.target.value)}
        />
        <button className="wbtn primary" style={{ height: 44, flexShrink: 0 }} type="submit"><i className="mdi mdi-plus" />Añadir nota</button>
      </form>
    </>
  );
}

/* ---- Protocolos ---- */
function SecProtocolos({ rows }: { rows: any[] }) {
  if (!rows.length) return <EmptyMini icon="mdi-hospital-box-outline">Sin protocolos quirúrgicos registrados.</EmptyMini>;
  return (
    <div>
      {rows.map((row, i) => (
        <div className="proto-card" key={row.form_id || i}>
          <span className="proto-ic"><i className="mdi mdi-hospital-box-outline" /></span>
          <div className="proto-main">
            <div className="pm-title">{row.membrete || 'Protocolo quirúrgico'}</div>
            <div className="pm-date">{fmtDateShort(row.fecha_inicio)}</div>
          </div>
          <span className={`badge ${String(row.status) === '1' || row.status === 'firmado' ? 'badge-green' : 'badge-warn'}`}>
            {String(row.status) === '1' || row.status === 'firmado' ? 'Firmado' : 'Pendiente'}
          </span>
          <div className="ag-card-acts">
            {row.links?.pdf && (
              <button className="icon-btn" title="Ver PDF" onClick={() => window.open(row.links.pdf, '_blank', 'noopener')}>
                <i className="mdi mdi-file-pdf-box" />
              </button>
            )}
            {row.links?.cirugia && (
              <button className="icon-btn" title="Abrir en cirugías" onClick={() => { window.location.href = row.links.cirugia; }}>
                <i className="mdi mdi-open-in-new" />
              </button>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---- Prefacturas ---- */
function SecPrefacturas({ rows }: { rows: any[] }) {
  if (!rows.length) return <EmptyMini icon="mdi-receipt-text-outline">Sin prefacturas registradas.</EmptyMini>;
  return (
    <div style={{ overflowX: 'auto' }}>
      <table className="pref-table">
        <thead>
          <tr><th>Código derivación</th><th>Sede</th><th>Referido</th><th>Fecha creación</th><th>Vigencia</th></tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={row.id || i}>
              <td><span className="mono-sm">{row.cod_derivacion || '—'}</span></td>
              <td>{row.sede || '—'}</td>
              <td>{row.referido || '—'}</td>
              <td>{fmtDateShort(row.fecha_creacion)}</td>
              <td className={vigenciaClass(row.fecha_vigencia)}>
                {row.fecha_vigencia ? fmtDateShort(row.fecha_vigencia) : '—'}
                {row.fecha_vigencia && new Date(row.fecha_vigencia) < new Date() && <i className="mdi mdi-alert-circle-outline" style={{ marginLeft: 4 }} />}
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ---- Derivaciones ---- */
function SecDerivaciones({ rows }: { rows: any[] }) {
  if (!rows.length) return <EmptyMini icon="mdi-transit-transfer">Sin derivaciones IESS registradas.</EmptyMini>;
  return (
    <div style={{ overflowX: 'auto' }}>
      <table className="deriv-table">
        <thead>
          <tr><th>Código</th><th>Diagnóstico</th><th>Referido</th><th>Parentesco</th><th>Fecha</th><th>Vigencia</th></tr>
        </thead>
        <tbody>
          {rows.map((row, i) => (
            <tr key={row.id || i}>
              <td><span className="mono-sm">{row.codigo || '—'}</span></td>
              <td>{row.diagnostico || '—'}</td>
              <td>{row.referido || '—'}</td>
              <td>{row.parentesco || '—'}</td>
              <td>{fmtDateShort(row.fecha)}</td>
              <td className={vigenciaClass(row.fecha_vigencia)}>{row.fecha_vigencia ? fmtDateShort(row.fecha_vigencia) : '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}

/* ---- Recetas ---- */
function SecRecetas({ rows }: { rows: any[] }) {
  if (!rows.length) return <EmptyMini icon="mdi-pill">Sin recetas médicas registradas.</EmptyMini>;
  const groups: Record<string, any[]> = {};
  rows.forEach(row => {
    const key = `${row.procedimiento || ''}|${fmtDateShort(row.fecha)}`;
    if (!groups[key]) groups[key] = [];
    groups[key].push(row);
  });
  return (
    <div>
      {Object.entries(groups).map(([key, items]) => {
        const [proc, fecha] = key.split('|');
        const first = items[0];
        return (
          <div className="receta-group" key={key}>
            <div className="receta-group-head">
              <i className="mdi mdi-pill" />
              <strong>{proc || 'Sin procedimiento'}</strong>
              <span>·</span><span>{fecha}</span>
              {first.doctor && <><span>·</span><span>{first.doctor}</span></>}
            </div>
            <div className="receta-items">
              {items.map((item, i) => (
                <div className="receta-item" key={item.id || i}>
                  <div className="ri-producto">{item.producto || '—'}</div>
                  <div className="ri-meta">{[item.via, item.dosis || item.pauta, item.cantidad ? `× ${item.cantidad}` : ''].filter(Boolean).join(' · ')}</div>
                </div>
              ))}
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ---- Comunicaciones ---- */
function SecComunicaciones({ p }: { p: Patient }) {
  const coms = p.comunicaciones || [];
  if (!coms.length) return <EmptyMini icon="mdi-chat-outline">Sin comunicaciones registradas.</EmptyMini>;

  const canalIcon = (canal: string) => {
    if (canal === 'whatsapp') return 'mdi-whatsapp';
    if (canal === 'llamada') return 'mdi-phone';
    return 'mdi-email-outline';
  };

  return (
    <div className="coms-list">
      {coms.map((c: any, i: number) => (
        <div className={`com-row canal-${c.canal || 'correo'} dir-${c.dir || 'out'}`} key={i}>
          <span className="com-ic"><i className={`mdi ${canalIcon(c.canal)}`} /></span>
          <div className="com-body">
            <div className="com-txt">{c.txt}</div>
            <div className="com-meta">
              <span className="badge-dir">{c.dir === 'out' ? 'ENVIADO' : 'RECIBIDO'}</span>
              <span>{c.dir === 'out' ? c.by : 'Paciente'}</span>
              <span>·</span>
              <span>{fmtDateTime(c.at)}</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---- Actividad ---- */
function SecActividad({ p }: { p: Patient }) {
  if (!p.timeline.length) return <EmptyMini icon="mdi-timeline-outline">Sin actividad reciente registrada.</EmptyMini>;
  return (
    <div className="act-timeline">
      {p.timeline.slice(0, 30).map((ev, i) => (
        <div className="act-item" key={i}>
          <span className={`act-dot tipo-${ev.tipo}`}><i className={`mdi ${ev.icon}`} /></span>
          <div className="act-body">
            <div className="act-txt">{ev.txt}</div>
            <div className="act-meta">{ev.by} · {fmtDateTime(ev.at)}</div>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---- Datos personales ---- */
function SecPersonales({ p }: { p: Patient }) {
  const e = p.emergencia;
  return (
    <div className="dp-grid">
      <div className="dp-item"><div className="k">Cédula / Pasaporte</div><div className="v mono">{p.cedula || '—'}</div></div>
      <div className="dp-item"><div className="k">Fecha de nacimiento</div><div className="v">{fmtDateLong(p.fecha_nac)}</div></div>
      <div className="dp-item"><div className="k">Edad</div><div className="v">{p.edad > 0 ? `${p.edad} años` : '—'}</div></div>
      <div className="dp-item"><div className="k">Sexo</div><div className="v">{p.sexo === 'F' ? 'Femenino' : p.sexo === 'M' ? 'Masculino' : p.sexo || '—'}</div></div>
      <div className="dp-item span2"><div className="k">Dirección</div><div className="v">{p.direccion || '—'}</div></div>
      <div className="dp-item"><div className="k">Ciudad</div><div className="v">{p.ciudad || '—'}</div></div>
      <div className="dp-item"><div className="k">Teléfono principal</div><div className="v">{p.telefono || '—'}</div></div>
      <div className="dp-item"><div className="k">Teléfono alternativo</div><div className="v">{p.telefono_alt || '—'}</div></div>
      <div className="dp-item"><div className="k">Correo electrónico</div><div className="v">{p.email || '—'}</div></div>

      <div className="dp-subhead"><i className="mdi mdi-shield-account-outline" />Afiliación y seguro</div>
      <div className="dp-item"><div className="k">Tipo de afiliación</div><div className="v">{afiliacionTipoLabel(p)}</div></div>
      <div className="dp-item"><div className="k">Aseguradora</div><div className="v">{aseguradoraLabel(p) || '—'}</div></div>
      <div className="dp-item"><div className="k">N.º de póliza</div><div className="v mono">{p.poliza || '—'}</div></div>
      <div className="dp-item span2"><div className="k">Titular de la póliza</div><div className="v">{p.titular || (p.tipo_afiliacion === 'privado' ? 'No aplica (paciente privado)' : '—')}</div></div>

      <div className="dp-subhead"><i className="mdi mdi-account-heart-outline" />Contacto de emergencia</div>
      <div className="dp-item"><div className="k">Nombre</div><div className="v">{e?.nombre || '—'}</div></div>
      <div className="dp-item"><div className="k">Relación</div><div className="v">{e?.rel || '—'}</div></div>
      <div className="dp-item"><div className="k">Teléfono</div><div className="v">{e?.tel || '—'}</div></div>
    </div>
  );
}

/* ============================================================
   Main DetailView
   ============================================================ */
export default function DetailView({ p, onBack, onAgendar, onWhats, onNuevaSolicitud, onEditar, onAddNote, onOpenCRM }: Props) {
  const [open, setOpen] = useState<Set<string>>(() => new Set(['personales', 'agenda', 'solicitudes']));
  const [active, setActive] = useState('personales');
  const pageRef = useRef<HTMLDivElement>(null);

  const [sectionData, setSectionData] = useState<Record<string, SectionState>>(() =>
    Object.fromEntries(SECTIONS.map(s => [s.id, emptyState()]))
  );

  const doLoad = useCallback(async (id: string, currentData: Record<string, SectionState>) => {
    const apiKey = SECTION_TO_API[id];
    if (!apiKey || currentData[id]?.loaded || currentData[id]?.loading) return;
    setSectionData(prev => ({ ...prev, [id]: { ...prev[id], loading: true, error: null } }));
    try {
      const result = await fetchPatientSection(p.hc_number, apiKey);
      setSectionData(prev => ({ ...prev, [id]: { loaded: true, loading: false, rows: result.rows, summary: result.summary, error: null } }));
    } catch (e: any) {
      setSectionData(prev => ({ ...prev, [id]: { ...prev[id], loaded: true, loading: false, error: e.message || 'Error' } }));
    }
  }, [p.hc_number]);

  useEffect(() => {
    setSectionData(curr => {
      open.forEach(id => { if (SECTION_TO_API[id] && !curr[id]?.loaded && !curr[id]?.loading) doLoad(id, curr); });
      return curr;
    });
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, []);

  const toggle = (id: string) => {
    setOpen(s => {
      const n = new Set(s);
      if (n.has(id)) { n.delete(id); } else {
        n.add(id);
        setSectionData(curr => { doLoad(id, curr); return curr; });
      }
      return n;
    });
  };

  const goTo = (id: string) => {
    setActive(id);
    setOpen(s => {
      if (!s.has(id)) {
        setSectionData(curr => { doLoad(id, curr); return curr; });
        return new Set(s).add(id);
      }
      return s;
    });
    requestAnimationFrame(() => {
      const page = pageRef.current;
      const el = document.getElementById(`sec-${id}`);
      if (page && el) {
        const top = el.getBoundingClientRect().top - page.getBoundingClientRect().top + page.scrollTop - 12;
        page.scrollTo({ top, behavior: 'smooth' });
      }
    });
  };

  const allOpen = open.size === SECTIONS.length;
  const toggleAll = () => {
    if (allOpen) {
      setOpen(new Set());
    } else {
      setOpen(new Set(SECTIONS.map(s => s.id)));
      setSectionData(curr => {
        SECTIONS.forEach(s => doLoad(s.id, curr));
        return curr;
      });
    }
  };

  const countFor = (id: string): number | undefined => {
    if (id === 'comunicaciones') return p.comunicaciones?.length || undefined;
    if (id === 'actividad') return p.timeline?.length || undefined;
    const sd = sectionData[id];
    if (!sd?.loaded) return undefined;
    if (sd.summary?.total_rows != null) return Number(sd.summary.total_rows);
    return sd.rows.length || undefined;
  };

  const m = p.medico_tratante
    ? { full: p.medico_tratante.nombre, esp: p.medico_tratante.especialidad }
    : MEDICO_MAP[p.medico];

  function renderBody(id: string): React.ReactNode {
    if (id === 'personales')     return <SecPersonales p={p} />;
    if (id === 'actividad')      return <SecActividad p={p} />;
    if (id === 'comunicaciones') return <SecComunicaciones p={p} />;
    const sd = sectionData[id];
    if (!sd || sd.loading || !sd.loaded) return <SectionSkeleton />;
    if (sd.error) return <EmptyMini icon="mdi-alert-circle-outline">Error: {sd.error}</EmptyMini>;
    if (id === 'agenda')       return <SecAgenda rows={sd.rows} onAgendar={onAgendar} p={p} />;
    if (id === 'solicitudes')  return <SecSolicitudes rows={sd.rows} onNueva={() => onNuevaSolicitud(p)} onOpenCRM={onOpenCRM} />;
    if (id === 'examenes')     return <SecExamenes rows={sd.rows} />;
    if (id === 'consultas')    return <SecConsultas rows={sd.rows} onAddNote={onAddNote} patientId={p.id} />;
    if (id === 'protocolos')   return <SecProtocolos rows={sd.rows} />;
    if (id === 'prefacturas')  return <SecPrefacturas rows={sd.rows} />;
    if (id === 'derivaciones') return <SecDerivaciones rows={sd.rows} />;
    if (id === 'recetas')      return <SecRecetas rows={sd.rows} />;
    return null;
  }

  return (
    <div className="page" ref={pageRef}>
      <div className="page-inner">
        <button className="dt-back" onClick={onBack}><i className="mdi mdi-arrow-left" />Volver a la lista de pacientes</button>

        {/* Patient header */}
        <div className={`pt-header ${p.alerta ? 'has-alert' : ''}`}>
          <div className="pt-id">
            <Avatar initials={p.initials} sede={p.sede} size={72} />
            <div className="pt-id-txt">
              <h1>{p.display_name}</h1>
              <div className="pt-tags">
                <span className="hc-pill">HC {p.hc_number}</span>
                <span className="dot-sep">·</span>
                <span className="age">{p.edad > 0 ? `${p.edad} años` : ''}{p.edad > 0 ? ' · ' : ''}{p.sexo === 'F' ? 'Femenino' : 'Masculino'}</span>
              </div>
              <div className="pt-contact">
                <span className="pc"><i className="mdi mdi-phone-outline" /><a href={phoneHref(p.telefono)}>{p.telefono || '—'}</a></span>
                <span className="pc">
                  <i className="mdi mdi-email-outline" />
                  {p.email ? <a href={`mailto:${p.email}`}>{p.email}</a> : <span style={{ color: 'var(--fg-fade)' }}>Sin correo</span>}
                </span>
              </div>
            </div>
          </div>

          <div className="pt-actions">
            <button className="pt-act primary" onClick={() => onAgendar(p)}><i className="mdi mdi-calendar-plus" />Agendar</button>
            <button className="pt-act wa" onClick={() => onWhats(p)}><i className="mdi mdi-whatsapp" />WhatsApp</button>
            <button className="pt-act" onClick={() => onNuevaSolicitud(p)}><i className="mdi mdi-clipboard-plus-outline" />Nueva solicitud</button>
            <button className="pt-act" onClick={() => onEditar(p)}><i className="mdi mdi-pencil-outline" />Editar</button>
          </div>

          {p.alerta && (
            <div className="pt-alert-banner">
              <i className="mdi mdi-alert-circle-outline" />
              <span><b>Alerta clínica:</b> {p.alerta}</span>
            </div>
          )}
        </div>

        {/* Meta strip */}
        <div className="pt-meta-strip">
          <div className="ms tone-medico">
            <span className="ms-ic"><i className="mdi mdi-stethoscope" /></span>
            <div><div className="k">Médico tratante</div><div className="v sm">{m?.full || '—'}</div></div>
          </div>
          <div className="ms tone-sede">
            <span className="ms-ic"><i className="mdi mdi-hospital-building" /></span>
            <div><div className="k">Sede principal</div><div className="v">{SEDE_MAP[p.sede]?.label || p.sede}</div></div>
          </div>
          <div className="ms tone-afil">
            <span className="ms-ic"><i className="mdi mdi-shield-account-outline" /></span>
            <div><div className="k">Afiliación</div><div className="v">{afiliacionTipoLabel(p)}{aseguradoraLabel(p) ? ` · ${aseguradoraLabel(p)}` : ''}</div></div>
          </div>
          <div className="ms tone-cita">
            <span className="ms-ic"><i className="mdi mdi-calendar-clock" /></span>
            <div>
              <div className="k">Próxima cita</div>
              <div className="v sm">{p.proxima_cita ? `${fmtDateShort(p.proxima_cita.fecha)}${hasTime(p.proxima_cita.fecha) ? ' · ' + fmtTime(p.proxima_cita.fecha) : ''}` : 'Sin agendar'}</div>
            </div>
          </div>
          {p.deuda > 0 && (
            <div className="ms tone-deuda">
              <span className="ms-ic"><i className="mdi mdi-cash-remove" /></span>
              <div><div className="k">Deuda pendiente</div><div className="v" style={{ color: '#c0392b' }}>{fmtMoney(p.deuda)}</div></div>
            </div>
          )}
        </div>

        {/* Body: sidebar nav + sections */}
        <div className="detail-body">
          <nav className="sec-nav">
            <div className="sn-title">Secciones</div>
            {SECTIONS.map(s => {
              const cnt = countFor(s.id);
              const sd = sectionData[s.id];
              return (
                <button key={s.id} className={active === s.id ? 'is-active' : ''} onClick={() => goTo(s.id)}>
                  <span className="sn-ic" style={{ background: s.bg, color: s.color }}>
                    <i className={`mdi ${s.icon}`} />
                  </span>
                  <span className="sn-label">{s.title}</span>
                  {sd?.loading && <i className="mdi mdi-loading sec-loading" />}
                  {!sd?.loading && cnt != null && <span className="sn-n">{cnt}</span>}
                </button>
              );
            })}
            <button className="sn-expand" onClick={toggleAll}>
              <i className={`mdi ${allOpen ? 'mdi-collapse-all-outline' : 'mdi-expand-all-outline'}`} />
              {allOpen ? 'Colapsar todo' : 'Expandir todo'}
            </button>
          </nav>

          <div className="sec-stack">
            {SECTIONS.map(s => (
              <Section
                key={s.id}
                id={s.id}
                icon={s.icon}
                title={s.title}
                count={countFor(s.id)}
                open={open.has(s.id)}
                onToggle={toggle}
                sectionColor={s.color}
                sectionBg={s.bg}
                badge={
                  s.id === 'solicitudes' && p.sol_activa > 0
                    ? <span className="fsec-flag warn">{p.sol_activa} activa{p.sol_activa > 1 ? 's' : ''}</span>
                    : undefined
                }
              >
                {renderBody(s.id)}
              </Section>
            ))}
          </div>
        </div>
      </div>
    </div>
  );
}
