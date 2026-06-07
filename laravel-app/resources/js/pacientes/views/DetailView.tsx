import React, { useState, useRef } from 'react';
import type { Patient } from '../types';
import { MEDICO_MAP, SEDE_MAP, AFIL_MAP, TIPO_CITA } from '../data';
import { fmtDate, fmtDateShort, fmtDateLong, fmtTime, fmtDateTime, fmtMoney, hasTime, relDays, phoneHref } from '../utils';
import { Avatar, SedeBadge, AfilChip, SolBadge, EmptyMini, Section } from '../components';

const SECTIONS = [
  { id: 'personales', icon: 'mdi-card-account-details-outline', title: 'Datos personales' },
  { id: 'citas', icon: 'mdi-calendar-month-outline', title: 'Historial de citas' },
  { id: 'solicitudes', icon: 'mdi-clipboard-text-clock-outline', title: 'Solicitudes' },
  { id: 'examenes', icon: 'mdi-image-multiple-outline', title: 'Resultados y exámenes' },
  { id: 'notas', icon: 'mdi-note-text-outline', title: 'Historial clínico' },
  { id: 'facturacion', icon: 'mdi-receipt-text-outline', title: 'Facturación' },
  { id: 'comunicaciones', icon: 'mdi-message-text-outline', title: 'Comunicaciones' },
  { id: 'actividad', icon: 'mdi-timeline-clock-outline', title: 'Actividad reciente' },
];

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

export default function DetailView({ p, onBack, onAgendar, onWhats, onNuevaSolicitud, onEditar, onAddNote, onOpenCRM }: Props) {
  const [open, setOpen] = useState<Set<string>>(() => new Set(['personales', 'citas', 'solicitudes']));
  const [active, setActive] = useState('personales');
  const pageRef = useRef<HTMLDivElement>(null);

  const toggle = (id: string) => setOpen(s => { const n = new Set(s); n.has(id) ? n.delete(id) : n.add(id); return n; });
  const allOpen = open.size === SECTIONS.length;
  const toggleAll = () => setOpen(allOpen ? new Set() : new Set(SECTIONS.map(s => s.id)));

  const goTo = (id: string) => {
    setOpen(s => new Set(s).add(id));
    setActive(id);
    requestAnimationFrame(() => {
      const page = pageRef.current;
      const el = document.getElementById(`sec-${id}`);
      if (page && el) {
        const top = el.getBoundingClientRect().top - page.getBoundingClientRect().top + page.scrollTop - 12;
        page.scrollTo({ top, behavior: 'smooth' });
      }
    });
  };

  const m = MEDICO_MAP[p.medico];
  const counts = {
    citas: p.citas.length, solicitudes: p.solicitudes.length, examenes: p.examenes.length,
    notas: p.notas.length, facturacion: p.facturas.length, comunicaciones: p.comunicaciones.length,
    actividad: p.timeline.length,
  };

  return (
    <div className="page" ref={pageRef}>
      <div className="page-inner">
        <button className="dt-back" onClick={onBack}><i className="mdi mdi-arrow-left" />Volver a la lista de pacientes</button>

        {/* Patient header */}
        <div className={`pt-header ${p.alerta ? 'has-alert' : ''}`}>
          <div className="pt-id">
            <Avatar initials={p.initials} sede={p.sede} size={68} />
            <div className="pt-id-txt">
              <h1>{p.display_name}</h1>
              <div className="pt-tags">
                <span className="hc-pill">HC {p.hc_number}</span>
                <span className="dot-sep">·</span>
                <span className="age">{p.edad > 0 ? `${p.edad} años` : ''} · {p.sexo === 'F' ? 'Femenino' : 'Masculino'}</span>
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
            <div><div className="k">Afiliación</div><div className="v">{AFIL_MAP[p.afiliacion]?.label || p.afiliacion}{p.aseguradora ? ` · ${p.aseguradora}` : ''}</div></div>
          </div>
          <div className="ms tone-cita">
            <span className="ms-ic"><i className="mdi mdi-calendar-clock" /></span>
            <div>
              <div className="k">Próxima cita</div>
              <div className="v sm">{p.proxima_cita ? `${fmtDateShort(p.proxima_cita.fecha)}${hasTime(p.proxima_cita.fecha) ? ' · ' + fmtTime(p.proxima_cita.fecha) : ''}` : 'Sin agendar'}</div>
            </div>
          </div>
        </div>

        {/* Body: nav + sections */}
        <div className="detail-body">
          <nav className="sec-nav">
            <div className="sn-title">Secciones</div>
            {SECTIONS.map(s => (
              <button key={s.id} className={active === s.id ? 'is-active' : ''} onClick={() => goTo(s.id)}>
                <i className={`mdi ${s.icon}`} />{s.title}
                {(counts as any)[s.id] != null && <span className="sn-n">{(counts as any)[s.id]}</span>}
              </button>
            ))}
            <button className="sn-expand" onClick={toggleAll}>
              <i className={`mdi ${allOpen ? 'mdi-collapse-all-outline' : 'mdi-expand-all-outline'}`} />
              {allOpen ? 'Colapsar todo' : 'Expandir todo'}
            </button>
          </nav>

          <div className="sec-stack">
            <Section id="personales" icon="mdi-card-account-details-outline" title="Datos personales" open={open.has('personales')} onToggle={toggle}>
              <SecPersonales p={p} />
            </Section>

            <Section id="citas" icon="mdi-calendar-month-outline" title="Historial de citas" count={counts.citas} open={open.has('citas')} onToggle={toggle}>
              <SecCitas p={p} onAgendar={onAgendar} />
            </Section>

            <Section
              id="solicitudes"
              icon="mdi-clipboard-text-clock-outline"
              title="Solicitudes"
              count={counts.solicitudes}
              badge={p.sol_activa > 0 ? <span className="fsec-flag warn">{p.sol_activa} activa{p.sol_activa > 1 ? 's' : ''}</span> : undefined}
              open={open.has('solicitudes')}
              onToggle={toggle}
            >
              <SecSolicitudes p={p} onOpenCRM={onOpenCRM} onNueva={() => onNuevaSolicitud(p)} />
            </Section>

            <Section id="examenes" icon="mdi-image-multiple-outline" title="Resultados y exámenes" count={counts.examenes} open={open.has('examenes')} onToggle={toggle}>
              <SecExamenes p={p} />
            </Section>

            <Section id="notas" icon="mdi-note-text-outline" title="Historial clínico" count={counts.notas} open={open.has('notas')} onToggle={toggle}>
              <SecNotas p={p} onAddNote={onAddNote} />
            </Section>

            <Section
              id="facturacion"
              icon="mdi-receipt-text-outline"
              title="Facturación"
              count={counts.facturacion}
              badge={p.deuda > 0 ? <span className="fsec-flag danger">Deuda {fmtMoney(p.deuda)}</span> : undefined}
              open={open.has('facturacion')}
              onToggle={toggle}
            >
              <SecFacturacion p={p} />
            </Section>

            <Section id="comunicaciones" icon="mdi-message-text-outline" title="Comunicaciones" count={counts.comunicaciones} open={open.has('comunicaciones')} onToggle={toggle}>
              <SecComunicaciones p={p} onWhats={onWhats} />
            </Section>

            <Section id="actividad" icon="mdi-timeline-clock-outline" title="Actividad reciente" open={open.has('actividad')} onToggle={toggle}>
              <SecActividad p={p} />
            </Section>
          </div>
        </div>
      </div>
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
      <div className="dp-item span2"><div className="k">Dirección</div><div className="v">{p.direccion || '—'}</div></div>
      <div className="dp-item"><div className="k">Ciudad</div><div className="v">{p.ciudad || '—'}</div></div>
      <div className="dp-item"><div className="k">Teléfono principal</div><div className="v">{p.telefono || '—'}</div></div>
      <div className="dp-item"><div className="k">Teléfono alternativo</div><div className="v">{p.telefono_alt || '—'}</div></div>
      <div className="dp-item"><div className="k">Correo electrónico</div><div className="v">{p.email || '—'}</div></div>

      <div className="dp-subhead"><i className="mdi mdi-shield-account-outline" />Afiliación y seguro</div>
      <div className="dp-item"><div className="k">Tipo de afiliación</div><div className="v">{AFIL_MAP[p.afiliacion]?.label || p.afiliacion}</div></div>
      <div className="dp-item"><div className="k">Aseguradora</div><div className="v">{p.aseguradora || '—'}</div></div>
      <div className="dp-item"><div className="k">N.º de póliza</div><div className="v mono">{p.poliza || '—'}</div></div>
      <div className="dp-item span2"><div className="k">Titular de la póliza</div><div className="v">{p.titular || (p.afiliacion === 'privado' ? 'No aplica (paciente privado)' : '—')}</div></div>

      <div className="dp-subhead"><i className="mdi mdi-account-heart-outline" />Contacto de emergencia</div>
      <div className="dp-item"><div className="k">Nombre</div><div className="v">{e?.nombre || '—'}</div></div>
      <div className="dp-item"><div className="k">Relación</div><div className="v">{e?.rel || '—'}</div></div>
      <div className="dp-item"><div className="k">Teléfono</div><div className="v">{e?.tel || '—'}</div></div>
    </div>
  );
}

/* ---- Historial de citas ---- */
function SecCitas({ p, onAgendar }: { p: Patient; onAgendar: (p: Patient) => void }) {
  if (!p.citas.length) return <EmptyMini icon="mdi-calendar-blank-outline">No hay citas registradas para este paciente.</EmptyMini>;
  return (
    <div className="rec-list">
      {p.citas.map((c, i) => {
        const t = TIPO_CITA[c.tipo] || { label: c.tipo, icon: 'mdi-calendar', cat: 'consulta' };
        return (
          <div className="rec-row" key={i}>
            <span className={`rec-ic cat-${t.cat}`}><i className={`mdi ${t.icon}`} /></span>
            <div className="rec-main">
              <div className="rec-t">{c.det}</div>
              <div className="rec-s"><span>{t.label}</span><span>·</span><span>{MEDICO_MAP[c.medico]?.full || c.medico}</span></div>
            </div>
            <span className={`rec-status st-${c.estado}`}>{c.estado === 'agendada' ? 'Agendada' : c.estado === 'completada' ? 'Completada' : 'No asistió'}</span>
            <div className="rec-when">
              <div className="rw-date">{fmtDateShort(c.fecha)}{hasTime(c.fecha) ? ` · ${fmtTime(c.fecha)}` : ''}</div>
              <div className="rw-rel">{relDays(c.fecha)}</div>
            </div>
            <div className="rec-actions">
              <button title="Ver detalle"><i className="mdi mdi-eye-outline" /></button>
              <button title="Repetir cita" onClick={() => onAgendar(p)}><i className="mdi mdi-calendar-refresh-outline" /></button>
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ---- Solicitudes ---- */
function SecSolicitudes({ p, onOpenCRM, onNueva }: { p: Patient; onOpenCRM: (s: any) => void; onNueva: () => void }) {
  if (!p.solicitudes.length) {
    return (
      <>
        <EmptyMini icon="mdi-clipboard-text-outline">Este paciente no tiene solicitudes registradas.</EmptyMini>
        <button className="wbtn primary" style={{ marginTop: 14, width: '100%', height: 44 }} onClick={onNueva}><i className="mdi mdi-plus" />Crear nueva solicitud</button>
      </>
    );
  }
  return (
    <>
      <div className="sol-grid">
        {p.solicitudes.map(s => (
          <div className={`sol-card tipo-${s.tipo}`} key={s.id}>
            <div className="sc-top">
              <span className="sc-ic"><i className={`mdi ${s.tipo === 'quirurgica' ? 'mdi-hospital-box-outline' : 'mdi-flask-outline'}`} /></span>
              <div style={{ minWidth: 0 }}>
                <div className="sc-id">{s.id} · {s.tipo === 'quirurgica' ? 'Quirúrgica' : 'Examen'}</div>
                <div className="sc-title">{s.titulo}</div>
              </div>
            </div>
            <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
              <SolBadge estado={s.estado} />
            </div>
            <div className="sc-foot">
              <span className="sc-date">{fmtDate(s.fecha)}</span>
              <button className="sc-crm" onClick={() => onOpenCRM(s)}>Ver en CRM<i className="mdi mdi-arrow-top-right" /></button>
            </div>
          </div>
        ))}
      </div>
      <button className="wbtn" style={{ marginTop: 14, width: '100%', height: 42 }} onClick={onNueva}><i className="mdi mdi-plus" />Crear nueva solicitud</button>
    </>
  );
}

/* ---- Exámenes ---- */
function SecExamenes({ p }: { p: Patient }) {
  if (!p.examenes.length) return <EmptyMini icon="mdi-image-off-outline">Aún no hay exámenes ni resultados adjuntos.</EmptyMini>;
  return (
    <div className="exam-grid">
      {p.examenes.map((x, i) => (
        <div className="exam-card" key={i}>
          <div className={`exam-thumb ${x.tipo === 'pdf' ? 'is-pdf' : ''}`}>
            <i className={`mdi ${x.tipo === 'pdf' ? 'mdi-file-pdf-box' : 'mdi-image-outline'}`} />
            <span className="ex-tag">{x.tipo === 'pdf' ? 'PDF' : 'IMG'}</span>
          </div>
          <div className="exam-meta">
            <div className="ex-name">{x.nombre}</div>
            <div className="ex-sub">{fmtDateShort(x.fecha)} · {MEDICO_MAP[x.med]?.full?.split(' ').slice(-1)[0] || '—'}</div>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---- Notas clínicas ---- */
function SecNotas({ p, onAddNote }: { p: Patient; onAddNote: (id: number, txt: string) => void }) {
  const [val, setVal] = useState('');
  return (
    <>
      {p.notas.length === 0 ? (
        <EmptyMini icon="mdi-note-off-outline">No hay notas clínicas registradas.</EmptyMini>
      ) : (
        <div className="notes-list">
          {p.notas.map((n, i) => (
            <div className="note-card" key={i}>
              <div className="nc-rail">
                <span className="nc-dot"><i className="mdi mdi-stethoscope" /></span>
                <span className="nc-line" />
              </div>
              <div className="nc-body">
                <div className="nc-meta"><span className="nc-by">{n.by}</span><span className="nc-date">· {fmtDateLong(n.at)}</span></div>
                <div className="nc-txt">{n.txt}</div>
              </div>
            </div>
          ))}
        </div>
      )}
      <form
        style={{ display: 'flex', gap: 8, marginTop: 16 }}
        onSubmit={e => { e.preventDefault(); if (val.trim()) { onAddNote(p.id, val.trim()); setVal(''); } }}
      >
        <input
          className="field"
          style={{ flex: 1, height: 44, border: '1px solid var(--border)', borderRadius: 10, padding: '0 14px', fontFamily: 'inherit', fontSize: 13.5, outline: 0 }}
          placeholder="Añadir nota clínica…"
          value={val}
          onChange={e => setVal(e.target.value)}
        />
        <button className="wbtn primary" style={{ height: 44 }} type="submit"><i className="mdi mdi-plus" />Añadir nota</button>
      </form>
    </>
  );
}

/* ---- Facturación ---- */
function SecFacturacion({ p }: { p: Patient }) {
  const totalFact = p.facturas.reduce((a, f) => a + f.total, 0);
  const totalPag = p.facturas.reduce((a, f) => a + f.pagado, 0);
  return (
    <>
      <div className="bill-summary">
        <div className="bill-stat"><div className="bs-k">Total facturado</div><div className="bs-v">{fmtMoney(totalFact)}</div></div>
        <div className="bill-stat tone-paid"><div className="bs-k">Pagado</div><div className="bs-v">{fmtMoney(totalPag)}</div></div>
        <div className="bill-stat tone-debt"><div className="bs-k">Deuda pendiente</div><div className="bs-v">{fmtMoney(p.deuda)}</div></div>
      </div>
      {p.facturas.length === 0 ? (
        <EmptyMini icon="mdi-receipt-text-outline">No hay facturas registradas.</EmptyMini>
      ) : (
        <div className="bill-table">
          {p.facturas.map((f, i) => (
            <div className="bill-row" key={i}>
              <div><div className="br-concept">{f.concepto}</div><div className="br-num">{f.num}</div></div>
              <div className="br-date">{fmtDate(f.fecha)}</div>
              <div className="br-total">{fmtMoney(f.total)}</div>
              <div className={`br-state st-${f.estado}`}>{f.estado === 'pagada' ? 'Pagada' : f.estado === 'parcial' ? 'Pago parcial' : 'Pendiente'}</div>
            </div>
          ))}
        </div>
      )}
    </>
  );
}

/* ---- Comunicaciones ---- */
function SecComunicaciones({ p, onWhats }: { p: Patient; onWhats: (p: Patient) => void }) {
  if (!p.comunicaciones.length) {
    return (
      <>
        <EmptyMini icon="mdi-message-off-outline">No hay comunicaciones registradas con este paciente.</EmptyMini>
        <button className="wbtn" style={{ marginTop: 14, width: '100%', height: 42, color: '#1f9d7a', borderColor: '#b7e2d4' }} onClick={() => onWhats(p)}>
          <i className="mdi mdi-whatsapp" />Iniciar conversación por WhatsApp
        </button>
      </>
    );
  }
  const ordered = p.comunicaciones.slice().sort((a, b) => new Date(b.at).getTime() - new Date(a.at).getTime());
  return (
    <div className="coms-list">
      {ordered.map((c, i) => (
        <div className={`com-row canal-${c.canal} dir-${c.dir}`} key={i}>
          <span className="com-ic"><i className={`mdi ${c.canal === 'whatsapp' ? 'mdi-whatsapp' : c.canal === 'llamada' ? 'mdi-phone' : 'mdi-email-outline'}`} /></span>
          <div className="com-body">
            <div className="com-txt">{c.txt}</div>
            <div className="com-meta">
              <span className="badge-dir">{c.dir === 'out' ? 'ENVIADO' : 'RECIBIDO'}</span>
              <span>{c.dir === 'out' ? c.by : 'Paciente'}</span><span>·</span><span>{fmtDateTime(c.at)}</span>
            </div>
          </div>
        </div>
      ))}
    </div>
  );
}

/* ---- Actividad reciente ---- */
function SecActividad({ p }: { p: Patient }) {
  if (!p.timeline.length) return <EmptyMini icon="mdi-timeline-outline">Sin actividad registrada.</EmptyMini>;
  return (
    <div className="act-timeline">
      {p.timeline.slice(0, 20).map((ev, i) => (
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
