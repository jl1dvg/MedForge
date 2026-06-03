// ============================================================
// MedForge · Solicitudes v3 — Panel CRM (workspace por pestañas)
// ============================================================
import React, { useState, useEffect } from 'react';
import type { Solicitud, Tarea, Nota } from './types';
import { DocAvatar, fmtDate, fmtDateTime, fmtSla, SLA_META } from './components';

const COL_TONE: Record<string, string> = {
  'recibida': '#3d7ac7', 'llamado': '#3d7ac7',
  'revision-codigos': '#6f67d8', 'espera-documentos': '#6f67d8',
  'apto-oftalmologo': '#1f9d7a', 'apto-anestesia': '#1f9d7a',
  'listo-para-agenda': '#d59623', 'programada': '#d59623', 'completado': '#05825f',
};

const CRM_TABS = [
  { key: 'seguimiento',  label: 'Seguimiento',  icon: 'mdi-radar'                      },
  { key: 'tareas',       label: 'Tareas',        icon: 'mdi-format-list-checks'         },
  { key: 'notas',        label: 'Notas',         icon: 'mdi-note-text-outline'          },
  { key: 'comunicacion', label: 'Comunicación',  icon: 'mdi-message-text-outline'       },
  { key: 'propuestas',   label: 'Propuestas',    icon: 'mdi-file-document-edit-outline' },
  { key: 'documentos',   label: 'Documentos',    icon: 'mdi-paperclip'                  },
];

const PRIO_TONE: Record<string, string> = { Alta: 'danger', Media: 'warning', Normal: 'ok', Baja: 'ok' };

const money = (n: number | null | undefined) =>
  '$' + Number(n || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function buildTimeline(sol: Solicitud) {
  const ev: Array<{ act: string; time: string; by: string; note?: boolean; done?: boolean }> = [];
  const base = new Date(sol.fecha).getTime();
  sol.checklist.filter((s) => s.completed).forEach((s, i) => {
    ev.push({ act: s.label, time: fmtDateTime(new Date(base + i * 5400000).toISOString()), by: i === 0 ? 'Recepción' : sol.crm.responsable, done: true });
  });
  (sol.detalle?.notas || []).forEach((n) => ev.push({ act: 'Nota: ' + n.txt, time: fmtDateTime(n.at), by: n.by, note: true }));
  return ev.sort((a, b) => new Date(b.time).getTime() - new Date(a.time).getTime()).slice(0, 8);
}

// ---- Tab: Seguimiento ----------------------------------------

function TabSeguimiento({ sol, onToggleStep }: { sol: Solicitud; onToggleStep: (id: number, slug: string) => void }) {
  const timeline = buildTimeline(sol);
  const telefono = sol.detalle.paciente.telefono !== '—' ? sol.detalle.paciente.telefono : sol.crm.telefono;
  const planAfiliacion = sol.plan_seguro !== '—' ? sol.plan_seguro : sol.afiliacion_label;
  return (
    <>
      <div className="panel-procbar">
        <span className="pp-ic"><i className="mdi mdi-eye-check-outline"></i></span>
        <div>
          <div className="pp-name">{sol.procedimiento_short} <span className="proto-eye">{sol.ojo}</span></div>
          <div className="pp-sub">{sol.procedimiento}</div>
        </div>
      </div>

      <section>
        <h3 className="psec-title"><i className="mdi mdi-tune-variant"></i>Detalles CRM</h3>
        <div className="info-grid">
          <div className="info-item"><div className="k">Etapa CRM</div><div className="v">{sol.estado_label}</div></div>
          <div className="info-item"><div className="k">Responsable</div><div className="v">{sol.crm.responsable}</div></div>
          <div className="info-item"><div className="k">Plan afiliación</div><div className="v">{planAfiliacion}</div></div>
          <div className="info-item"><div className="k">Fuente / convenio</div><div className="v">{sol.crm.fuente}</div></div>
          <div className="info-item"><div className="k">Teléfono</div><div className="v">{telefono}</div></div>
          <div className="info-item"><div className="k">Sede</div><div className="v">{sol.sede}</div></div>
        </div>
      </section>

      <section>
        <h3 className="psec-title">
          <i className="mdi mdi-format-list-checks"></i>Checklist operativo
          <span className="psec-meta">{sol.checklist_progress.completed}/{sol.checklist_progress.total} · {sol.checklist_progress.percent}%</span>
        </h3>
        <div className="chk-list">
          {sol.checklist.map((step) => (
            <div key={step.slug} className={`chk-item ${step.completed ? 'done' : ''}`} onClick={() => onToggleStep(sol.id, step.slug)}>
              <span className="chk-box">{step.completed && <i className="mdi mdi-check"></i>}</span>
              <span className="chk-label">{step.label}</span>
            </div>
          ))}
        </div>
      </section>

      <section>
        <h3 className="psec-title"><i className="mdi mdi-timeline-clock-outline"></i>Actividad reciente</h3>
        <div className="timeline">
          {timeline.map((e, i) => (
            <div className="tl-item" key={i}>
              <span className="tl-dot" style={{ background: e.note ? 'var(--info)' : 'var(--success)' }}>
                <i className={`mdi ${e.note ? 'mdi-note-text-outline' : 'mdi-check'}`}></i>
              </span>
              <div className="tl-body">
                <div className="tl-act">{e.act}</div>
                <div className="tl-time">{e.time}</div>
                <div className="tl-by">{e.by}</div>
              </div>
            </div>
          ))}
        </div>
      </section>
    </>
  );
}

// ---- Tab: Tareas --------------------------------------------

function TabTareas({ sol, onToggleTask, onAddTask }: { sol: Solicitud; onToggleTask: (id: number, idx: number) => void; onAddTask: (id: number, t: Tarea) => void }) {
  const [titulo, setTitulo] = useState('');
  const [prio, setPrio] = useState('Normal');
  const tareas = sol.detalle.tareas;
  const submit = (e: React.FormEvent) => {
    e.preventDefault();
    if (!titulo.trim()) return;
    onAddTask(sol.id, { titulo: titulo.trim(), prioridad: prio, asignado: sol.crm.responsable, fecha: new Date(Date.now() + 2 * 86400000).toISOString(), done: false });
    setTitulo(''); setPrio('Normal');
  };
  return (
    <section>
      <h3 className="psec-title">
        <i className="mdi mdi-format-list-checks"></i>Tareas y recordatorios
        <span className="psec-meta">{tareas.filter((t) => !t.done).length} pendientes</span>
      </h3>
      <div className="task-list">
        {tareas.length === 0 && <div className="mini-empty">Sin tareas registradas</div>}
        {tareas.map((tk, i) => (
          <div key={i} className={`task-row ${tk.done ? 'done' : ''}`} onClick={() => onToggleTask(sol.id, i)}>
            <span className="chk-box">{tk.done && <i className="mdi mdi-check"></i>}</span>
            <div className="task-body">
              <div className="task-title">{tk.titulo}</div>
              <div className="task-meta"><i className="mdi mdi-account-outline"></i>{tk.asignado} · <i className="mdi mdi-calendar-blank-outline"></i>{fmtDate(tk.fecha)}</div>
            </div>
            <span className={`prio-tag prio-${PRIO_TONE[tk.prioridad] || 'ok'}`}>{tk.prioridad}</span>
          </div>
        ))}
      </div>
      <form className="add-form" onSubmit={submit}>
        <input className="fld" placeholder="Nueva tarea…" value={titulo} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setTitulo(e.target.value)} />
        <select className="fld fld-sm" value={prio} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setPrio(e.target.value)}>
          <option>Normal</option><option>Media</option><option>Alta</option><option>Baja</option>
        </select>
        <button className="btn-add" type="submit"><i className="mdi mdi-plus"></i>Añadir</button>
      </form>
    </section>
  );
}

// ---- Tab: Notas --------------------------------------------

function TabNotas({ sol, onAddNote }: { sol: Solicitud; onAddNote: (id: number, txt: string) => void }) {
  const [txt, setTxt] = useState('');
  const notas = sol.detalle.notas;
  const submit = (e: React.FormEvent) => { e.preventDefault(); if (!txt.trim()) return; onAddNote(sol.id, txt.trim()); setTxt(''); };
  return (
    <section>
      <h3 className="psec-title"><i className="mdi mdi-note-text-outline"></i>Notas internas <span className="psec-meta">{notas.length}</span></h3>
      <div className="notes-list">
        {notas.length === 0 && <div className="mini-empty">Aún no hay notas</div>}
        {notas.map((n, i) => (
          <div className="note-row" key={i}>
            <span className="note-av"><DocAvatar name={n.by} cls="" /></span>
            <div className="note-body"><div className="nb-txt">{n.txt}</div><div className="nb-meta">{n.by} · {fmtDateTime(n.at)}</div></div>
          </div>
        ))}
      </div>
      <form className="add-form col" onSubmit={submit}>
        <textarea className="fld" rows={3} placeholder="Registrar avance del caso…" value={txt} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setTxt(e.target.value)}></textarea>
        <button className="btn-add self-end" type="submit"><i className="mdi mdi-comment-plus-outline"></i>Guardar nota</button>
      </form>
    </section>
  );
}

// ---- Tab: Comunicación ------------------------------------

function TabComunicacion({ sol, showToast }: { sol: Solicitud; showToast: (msg: string, icon?: string) => void }) {
  const [wa, setWa] = useState('');
  const [emailBody, setEmailBody] = useState('');
  const sendWa = (e: React.FormEvent) => { e.preventDefault(); if (!wa.trim()) return; showToast('WhatsApp enviado a ' + sol.full_name.split(' ')[0], 'mdi-whatsapp'); setWa(''); };
  const sendEmail = (e: React.FormEvent) => { e.preventDefault(); if (!emailBody.trim()) return; showToast('Correo enviado a ' + sol.crm.email, 'mdi-email-check-outline'); setEmailBody(''); };
  return (
    <>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-whatsapp" style={{ color: '#1f9d7a' }}></i>WhatsApp <span className="psec-meta">{sol.crm.telefono}</span></h3>
        <form className="add-form col" onSubmit={sendWa}>
          <div className="quick-replies">
            {['Confirmar fecha de cirugía', 'Recordatorio de ayuno', 'Solicitar documentos'].map((q) => (
              <button type="button" key={q} className="qr" onClick={() => setWa(q + ' — Estimado/a ' + sol.full_name.split(' ')[0] + ', ')}>{q}</button>
            ))}
          </div>
          <textarea className="fld" rows={3} placeholder="Escribe un mensaje para el paciente…" value={wa} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setWa(e.target.value)}></textarea>
          <button className="btn-add self-end btn-wa" type="submit"><i className="mdi mdi-send"></i>Enviar WhatsApp</button>
        </form>
      </section>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-email-outline"></i>Correo <span className="psec-meta">{sol.crm.email}</span></h3>
        <form className="add-form col" onSubmit={sendEmail}>
          <input className="fld" placeholder="Asunto: Seguimiento de solicitud" />
          <textarea className="fld" rows={4} placeholder="Escribe el correo…" value={emailBody} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setEmailBody(e.target.value)}></textarea>
          <button className="btn-add self-end" type="submit"><i className="mdi mdi-email-send-outline"></i>Enviar correo</button>
        </form>
      </section>
    </>
  );
}

// ---- Tab: Propuestas ----------------------------------------

function TabPropuestas({ sol, onAddProposal, showToast }: { sol: Solicitud; onAddProposal: (id: number) => void; showToast: (msg: string, icon?: string) => void }) {
  const props_ = sol.detalle.propuestas;
  const PROP_STATE_TONE: Record<string, string> = { Borrador: 'none', Enviada: 'warn', Aceptada: 'ok' };
  return (
    <section>
      <h3 className="psec-title"><i className="mdi mdi-file-document-edit-outline"></i>Propuestas CRM <span className="psec-meta">{props_.length}</span></h3>
      <div className="prop-list">
        {props_.length === 0 && <div className="mini-empty">Sin propuestas. Crea un borrador vinculado al lead.</div>}
        {props_.map((p, i) => (
          <div className="prop-card" key={i}>
            <div className="prop-head">
              <div className="prop-title">{p.titulo}</div>
              <span className={`conc-status conc-${PROP_STATE_TONE[p.estado] || 'none'}`}>{p.estado}</span>
            </div>
            <div className="prop-items">
              {p.items.map((it, j) => (
                <div className="prop-item" key={j}>
                  <span className="pi-cod">{it.cod}</span>
                  <span className="pi-desc">{it.desc}</span>
                  <span className="pi-qty">×{it.cant}</span>
                  <span className="pi-val">{money(it.cant * it.valor)}</span>
                </div>
              ))}
            </div>
            <div className="prop-tot">
              <span>Subtotal <b>{money(p.subtotal)}</b></span>
              <span>IVA 15% <b>{money(p.iva)}</b></span>
              <span className="pt-total">Total <b>{money(p.total)}</b></span>
            </div>
            <div className="prop-foot">
              <span className="prop-vig"><i className="mdi mdi-calendar-clock-outline"></i>Vigente hasta {fmtDate(p.vigencia)}</span>
              <div className="prop-actions">
                <button onClick={() => showToast('Propuesta enviada al paciente', 'mdi-send')}><i className="mdi mdi-send-outline"></i>Enviar</button>
                <button onClick={() => showToast('PDF de propuesta generado', 'mdi-file-pdf-box')}><i className="mdi mdi-file-pdf-box"></i>PDF</button>
              </div>
            </div>
          </div>
        ))}
      </div>
      <button className="btn-add full" onClick={() => onAddProposal(sol.id)}><i className="mdi mdi-file-document-plus-outline"></i>Nuevo borrador de propuesta</button>
    </section>
  );
}

// ---- Tab: Documentos ----------------------------------------

function TabDocumentos({ sol, showToast }: { sol: Solicitud; showToast: (msg: string, icon?: string) => void }) {
  const adj = sol.detalle.adjuntos;
  const der = sol.detalle.derivacion;
  return (
    <>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-shield-check-outline"></i>Cobertura</h3>
        {der.tiene ? (
          <div className="cover-card">
            <div className="cover-row"><span>Aseguradora</span><b>{der.aseguradora}</b></div>
            <div className="cover-row"><span>Plan</span><b>{der.plan}</b></div>
            <div className="cover-row"><span>Derivación</span><b>#{der.cod}</b></div>
            <div className="cover-row">
              <span>Vigencia</span>
              <span className={`conc-status ${der.vencida ? 'conc-warn' : 'conc-ok'}`}>
                <i className={`mdi ${der.vencida ? 'mdi-calendar-alert' : 'mdi-calendar-check'}`}></i>
                {der.vencida ? `Vencida hace ${Math.abs(der.dias_vigencia ?? 0)} d` : `${der.dias_vigencia} días`}
              </span>
            </div>
            {der.autorizacion_pendiente && <div className="cover-alert"><i className="mdi mdi-shield-clock-outline"></i>Autorización pendiente del seguro</div>}
          </div>
        ) : <div className="mini-empty">Paciente particular — sin derivación.</div>}
      </section>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-paperclip"></i>Documentos adjuntos <span className="psec-meta">{adj.length}</span></h3>
        <div className="doc-list">
          {adj.length === 0 && <div className="mini-empty">Sin documentos adjuntos</div>}
          {adj.map((a, i) => (
            <div className="doc-row" key={i} onClick={() => showToast('Abriendo ' + a.nombre, 'mdi-file-eye-outline')}>
              <span className="doc-ic"><i className={`mdi ${a.icon}`}></i></span>
              <div className="doc-info"><div className="doc-name2">{a.nombre}</div><div className="doc-meta">{a.peso} · {fmtDate(a.at)}</div></div>
              <i className="mdi mdi-download doc-dl"></i>
            </div>
          ))}
        </div>
        <button className="btn-add full" onClick={() => showToast('Selector de archivo (demo)', 'mdi-upload')}><i className="mdi mdi-upload"></i>Subir documento</button>
      </section>
    </>
  );
}

// ---- DetailPanel (main export) ------------------------------

export interface DetailPanelProps {
  sol: Solicitud | null;
  open: boolean;
  onClose: () => void;
  onToggleStep: (id: number, slug: string) => void;
  onAdvance: (id: number) => void;
  onToggleTask: (id: number, idx: number) => void;
  onAddTask: (id: number, t: Tarea) => void;
  onAddNote: (id: number, txt: string) => void;
  onAddProposal: (id: number) => void;
  onOpenPrefactura: (id: number) => void;
  showToast: (msg: string, icon?: string) => void;
}

export function DetailPanel({ sol, open, onClose, onToggleStep, onAdvance, onToggleTask, onAddTask, onAddNote, onAddProposal, onOpenPrefactura, showToast }: DetailPanelProps) {
  const [tab, setTab] = useState('seguimiento');

  useEffect(() => { if (open) setTab('seguimiento'); }, [sol?.id, open]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => { if (e.key === 'Escape') onClose(); };
    if (open) window.addEventListener('keydown', onKey);
    return () => window.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  const lastDone = sol?.estado === 'completado';
  const tone = sol ? (COL_TONE[sol.estado] ?? 'var(--accent)') : 'var(--accent)';

  return (
    <>
      <div className={`panel-backdrop ${open ? 'open' : ''}`} onClick={onClose}></div>
      <aside className={`panel panel-crm ${open ? 'open' : ''}`} aria-hidden={!open}>
        {sol && (
          <>
            <header className="panel-head">
              <span className="ph-av"><DocAvatar name={sol.full_name} cls="" /></span>
              <div className="ph-info">
                <h2>{sol.full_name}</h2>
                <div className="ph-meta">HC {sol.hc_number} · {sol.form_id} · <span style={{ color: tone, fontWeight: 600 }}>{sol.estado_label}</span></div>
              </div>
              <button className="panel-close" onClick={onClose} aria-label="Cerrar"><i className="mdi mdi-close"></i></button>
            </header>

            <nav className="panel-tabs">
              {CRM_TABS.map((tb) => (
                <button key={tb.key} className={tab === tb.key ? 'is-active' : ''} onClick={() => setTab(tb.key)}>
                  <i className={`mdi ${tb.icon}`}></i><span>{tb.label}</span>
                </button>
              ))}
            </nav>

            <div className="panel-body">
              {tab === 'seguimiento' && <TabSeguimiento sol={sol} onToggleStep={onToggleStep} />}
              {tab === 'tareas' && <TabTareas sol={sol} onToggleTask={onToggleTask} onAddTask={onAddTask} />}
              {tab === 'notas' && <TabNotas sol={sol} onAddNote={onAddNote} />}
              {tab === 'comunicacion' && <TabComunicacion sol={sol} showToast={showToast} />}
              {tab === 'propuestas' && <TabPropuestas sol={sol} onAddProposal={onAddProposal} showToast={showToast} />}
              {tab === 'documentos' && <TabDocumentos sol={sol} showToast={showToast} />}
            </div>

            <footer className="panel-foot">
              <button className="btn" onClick={() => onOpenPrefactura(sol.id)}>
                <i className="mdi mdi-file-document-multiple-outline"></i>Prefactura
              </button>
              {!lastDone ? (
                <button className="btn btn-primary" onClick={() => onAdvance(sol.id)}>
                  <i className="mdi mdi-arrow-right-bold-outline"></i>{sol.checklist_progress.next_label}
                </button>
              ) : (
                <button className="btn btn-primary" disabled style={{ opacity: .6 }}>
                  <i className="mdi mdi-check-all"></i>Completado
                </button>
              )}
            </footer>
          </>
        )}
      </aside>
    </>
  );
}
