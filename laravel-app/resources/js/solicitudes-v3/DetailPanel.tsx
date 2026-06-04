// ============================================================
// MedForge · Solicitudes v3 — Panel CRM (workspace por pestañas)
// ============================================================
import React, { useState, useEffect } from 'react';
import type { Solicitud, CrmCaseState } from './types';
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

const PRIO_TONE: Record<string, string> = { Alta: 'danger', Media: 'warning', Normal: 'ok', Baja: 'ok', alta: 'danger', media: 'warning', normal: 'ok', baja: 'ok' };

const money = (n: number | null | undefined) =>
  '$' + Number(n || 0).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

function buildTimeline(sol: Solicitud, crmCase: CrmCaseState | null) {
  if (crmCase?.activity.length) {
    return crmCase.activity.map((activity) => ({
      act: activity.description,
      time: fmtDateTime(activity.occurredAt),
      by: activity.author,
      note: activity.type === 'note_created',
      done: activity.type !== 'note_created',
    })).slice(0, 8);
  }

  const ev: Array<{ act: string; time: string; by: string; note?: boolean; done?: boolean }> = [];
  const base = new Date(sol.fecha).getTime();
  sol.checklist.filter((s) => s.completed).forEach((s, i) => {
    ev.push({ act: s.label, time: fmtDateTime(new Date(base + i * 5400000).toISOString()), by: i === 0 ? 'Recepción' : sol.crm.responsable, done: true });
  });
  (sol.detalle?.notas || []).forEach((n) => ev.push({ act: 'Nota: ' + n.txt, time: fmtDateTime(n.at), by: n.by, note: true }));
  return ev.sort((a, b) => new Date(b.time).getTime() - new Date(a.time).getTime()).slice(0, 8);
}

// ---- Tab: Seguimiento ----------------------------------------

function TabSeguimiento({ sol, crmCase }: { sol: Solicitud; crmCase: CrmCaseState | null }) {
  const timeline = buildTimeline(sol, crmCase);
  const telefono = crmCase?.contacts.primaryPhone || (sol.detalle.paciente.telefono !== '—' ? sol.detalle.paciente.telefono : sol.crm.telefono);
  const planAfiliacion = crmCase?.insurancePlan || (sol.plan_seguro !== '—' ? sol.plan_seguro : sol.afiliacion_label);
  const responsable = crmCase?.responsibleName || sol.crm.responsable;
  const fuente = crmCase?.source || sol.crm.fuente;
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
          <div className="info-item"><div className="k">Responsable</div><div className="v">{responsable}</div></div>
          <div className="info-item"><div className="k">Plan afiliación</div><div className="v">{planAfiliacion}</div></div>
          <div className="info-item"><div className="k">Fuente / convenio</div><div className="v">{fuente}</div></div>
          <div className="info-item"><div className="k">Teléfono</div><div className="v">{telefono}</div></div>
          <div className="info-item"><div className="k">Sede</div><div className="v">{sol.sede}</div></div>
        </div>
      </section>

      <section>
        <h3 className="psec-title"><i className="mdi mdi-timeline-clock-outline"></i>Actividad reciente</h3>
        <div className="timeline">
          {timeline.length === 0 && <div className="mini-empty">Sin actividad reciente</div>}
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

function TabTareas({
  sol,
  crmCase,
  onToggleTask,
  onAddTask,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onToggleTask: (taskId: number, currentStatus: string) => Promise<void>;
  onAddTask: (title: string, priority: string) => Promise<void>;
}) {
  const [titulo, setTitulo] = useState('');
  const [prio, setPrio] = useState('Normal');
  const [saving, setSaving] = useState(false);
  const [busyTaskId, setBusyTaskId] = useState<number | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const crmTasks = crmCase?.tasks ?? null;
  const tareas = crmTasks ?? sol.detalle.tareas;
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const title = titulo.trim();
    if (!title || saving) return;
    setSaving(true);
    setFormError(null);
    try {
      await onAddTask(title, prio.toLowerCase());
      setTitulo('');
      setPrio('Normal');
    } catch {
      setFormError('No se pudo guardar la tarea.');
    } finally {
      setSaving(false);
    }
  };
  const toggle = async (taskId: number, status: string) => {
    if (busyTaskId != null) return;
    setBusyTaskId(taskId);
    setFormError(null);
    try {
      await onToggleTask(taskId, status);
    } catch {
      setFormError('No se pudo actualizar la tarea.');
    } finally {
      setBusyTaskId(null);
    }
  };
  return (
    <section>
      <h3 className="psec-title">
        <i className="mdi mdi-format-list-checks"></i>Tareas y recordatorios
        <span className="psec-meta">{crmTasks ? crmTasks.filter((t) => t.status !== 'done' && t.status !== 'completed').length : sol.detalle.tareas.filter((t) => !t.done).length} pendientes</span>
      </h3>
      <div className="task-list">
        {tareas.length === 0 && <div className="mini-empty">Sin tareas registradas</div>}
        {crmTasks ? (
          crmTasks.map((tk) => (
            <div key={tk.id} className={`task-row ${tk.status === 'done' || tk.status === 'completed' ? 'done' : ''}`} onClick={() => void toggle(tk.id, tk.status)}>
              <span className="chk-box">{(tk.status === 'done' || tk.status === 'completed') && <i className="mdi mdi-check"></i>}</span>
              <div className="task-body">
                <div className="task-title">{tk.title}</div>
                <div className="task-meta"><i className="mdi mdi-account-outline"></i>{tk.assignedTo ?? '—'} · <i className="mdi mdi-calendar-blank-outline"></i>{tk.dueAt ? fmtDate(tk.dueAt) : '—'}</div>
              </div>
              <span className={`prio-tag prio-${PRIO_TONE[tk.priority] || 'ok'}`}>{tk.priority}</span>
              {busyTaskId === tk.id && <i className="mdi mdi-loading mdi-spin"></i>}
            </div>
          ))
        ) : (
          sol.detalle.tareas.map((tk, i) => (
            <div key={i} className={`task-row ${tk.done ? 'done' : ''}`}>
              <span className="chk-box">{tk.done && <i className="mdi mdi-check"></i>}</span>
              <div className="task-body">
                <div className="task-title">{tk.titulo}</div>
                <div className="task-meta"><i className="mdi mdi-account-outline"></i>{tk.asignado} · <i className="mdi mdi-calendar-blank-outline"></i>{fmtDate(tk.fecha)}</div>
              </div>
              <span className={`prio-tag prio-${PRIO_TONE[tk.prioridad] || 'ok'}`}>{tk.prioridad}</span>
            </div>
          ))
        )}
      </div>
      <form className="add-form" onSubmit={(e) => void submit(e)}>
        <input className="fld" placeholder="Nueva tarea…" value={titulo} onChange={(e: React.ChangeEvent<HTMLInputElement>) => setTitulo(e.target.value)} />
        <select className="fld fld-sm" value={prio} onChange={(e: React.ChangeEvent<HTMLSelectElement>) => setPrio(e.target.value)}>
          <option>Normal</option><option>Media</option><option>Alta</option><option>Baja</option>
        </select>
        <button className="btn-add" type="submit" disabled={saving || !titulo.trim()}><i className={`mdi ${saving ? 'mdi-loading mdi-spin' : 'mdi-plus'}`}></i>Añadir</button>
      </form>
      {formError && <div className="form-error" role="alert">{formError}</div>}
    </section>
  );
}

// ---- Tab: Notas --------------------------------------------

function TabNotas({
  sol,
  crmCase,
  onAddNote,
  onDeleteNote,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onAddNote: (txt: string) => Promise<void>;
  onDeleteNote: (noteId: number) => Promise<void>;
}) {
  const [txt, setTxt] = useState('');
  const [saving, setSaving] = useState(false);
  const [deletingId, setDeletingId] = useState<number | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const crmNotes = crmCase?.notes ?? null;
  const notas = crmNotes ?? sol.detalle.notas;
  const submit = async (e: React.FormEvent) => {
    e.preventDefault();
    const body = txt.trim();
    if (!body || saving) return;
    setSaving(true);
    setFormError(null);
    try {
      await onAddNote(body);
      setTxt('');
    } catch {
      setFormError('No se pudo guardar la nota.');
    } finally {
      setSaving(false);
    }
  };
  const remove = async (noteId: number) => {
    if (deletingId != null) return;
    setDeletingId(noteId);
    setFormError(null);
    try {
      await onDeleteNote(noteId);
    } catch {
      setFormError('No se pudo eliminar la nota.');
    } finally {
      setDeletingId(null);
    }
  };
  return (
    <section>
      <h3 className="psec-title"><i className="mdi mdi-note-text-outline"></i>Notas internas <span className="psec-meta">{notas.length}</span></h3>
      <div className="notes-list">
        {notas.length === 0 && <div className="mini-empty">Aún no hay notas</div>}
        {crmNotes ? (
          crmNotes.map((n) => (
            <div className="note-row" key={n.id}>
              <span className="note-av"><DocAvatar name={n.authorName} cls="" /></span>
              <div className="note-body"><div className="nb-txt">{n.body}</div><div className="nb-meta">{n.authorName} · {fmtDateTime(n.createdAt)}</div></div>
              {n.canDelete && (
                <button className="icon-btn" type="button" aria-label="Eliminar nota" disabled={deletingId === n.id} onClick={() => void remove(n.id)}>
                  <i className={`mdi ${deletingId === n.id ? 'mdi-loading mdi-spin' : 'mdi-delete-outline'}`}></i>
                </button>
              )}
            </div>
          ))
        ) : (
          sol.detalle.notas.map((n, i) => (
            <div className="note-row" key={i}>
              <span className="note-av"><DocAvatar name={n.by} cls="" /></span>
              <div className="note-body"><div className="nb-txt">{n.txt}</div><div className="nb-meta">{n.by} · {fmtDateTime(n.at)}</div></div>
            </div>
          ))
        )}
      </div>
      <form className="add-form col crm-note-form" onSubmit={(e) => void submit(e)}>
        <textarea className="fld" rows={3} placeholder="Registrar avance del caso…" value={txt} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setTxt(e.target.value)}></textarea>
        {formError && <div className="form-error" role="alert">{formError}</div>}
        <button className="btn-add self-end" type="submit" disabled={saving || !txt.trim()}>
          <i className={`mdi ${saving ? 'mdi-loading mdi-spin' : 'mdi-comment-plus-outline'}`}></i>Guardar nota
        </button>
      </form>
    </section>
  );
}

// ---- Tab: Comunicación ------------------------------------

function TabComunicacion({
  sol,
  crmCase,
  onSendWhatsapp,
  onSendEmail,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onSendWhatsapp: (payload: { recipients: string[]; message: string }) => Promise<void>;
  onSendEmail: (payload: { to: string[]; cc?: string[]; subject: string; body: string }) => Promise<void>;
}) {
  const [wa, setWa] = useState('');
  const [waRecipient, setWaRecipient] = useState('');
  const [waSending, setWaSending] = useState(false);
  const [emailBody, setEmailBody] = useState('');
  const [emailSubject, setEmailSubject] = useState('Seguimiento de solicitud');
  const [emailRecipient, setEmailRecipient] = useState('');
  const [emailSending, setEmailSending] = useState(false);
  const [formError, setFormError] = useState<string | null>(null);
  const phoneOptions = Array.from(new Set([crmCase?.contacts.primaryPhone, ...(crmCase?.contacts.alternatePhones ?? []), sol.crm.telefono].filter((v): v is string => Boolean(v && v !== '—'))));
  const emailOptions = Array.from(new Set([crmCase?.contacts.primaryEmail, ...(crmCase?.contacts.alternateEmails ?? []), sol.crm.email].filter((v): v is string => Boolean(v && v !== '—'))));
  useEffect(() => {
    setWaRecipient((cur) => phoneOptions.includes(cur) ? cur : phoneOptions[0] || '');
  }, [phoneOptions.join('|')]);
  useEffect(() => {
    setEmailRecipient((cur) => emailOptions.includes(cur) ? cur : emailOptions[0] || '');
  }, [emailOptions.join('|')]);
  const sendWa = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!wa.trim() || !waRecipient || waSending) return;
    setWaSending(true);
    setFormError(null);
    try {
      await onSendWhatsapp({ recipients: [waRecipient], message: wa.trim() });
      setWa('');
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'No se pudo enviar WhatsApp.');
    } finally {
      setWaSending(false);
    }
  };
  const sendEmail = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!emailBody.trim() || !emailSubject.trim() || !emailRecipient || emailSending) return;
    setEmailSending(true);
    setFormError(null);
    try {
      await onSendEmail({ to: [emailRecipient], subject: emailSubject.trim(), body: emailBody.trim() });
      setEmailBody('');
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'No se pudo enviar el correo.');
    } finally {
      setEmailSending(false);
    }
  };
  return (
    <>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-whatsapp" style={{ color: '#1f9d7a' }}></i>WhatsApp <span className="psec-meta">{waRecipient || '—'}</span></h3>
        <form className="add-form col" onSubmit={(e) => void sendWa(e)}>
          <select className="fld" value={waRecipient} onChange={(e) => setWaRecipient(e.target.value)}>
            {phoneOptions.length === 0 && <option value="">Sin teléfonos registrados</option>}
            {phoneOptions.map((phone) => <option key={phone} value={phone}>{phone}</option>)}
          </select>
          <div className="quick-replies">
            {['Confirmar fecha de cirugía', 'Recordatorio de ayuno', 'Solicitar documentos'].map((q) => (
              <button type="button" key={q} className="qr" onClick={() => setWa(q + ' — Estimado/a ' + sol.full_name.split(' ')[0] + ', ')}>{q}</button>
            ))}
          </div>
          <textarea className="fld" rows={3} placeholder="Escribe un mensaje para el paciente…" value={wa} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setWa(e.target.value)}></textarea>
          <button className="btn-add self-end btn-wa" type="submit" disabled={waSending || !wa.trim() || !waRecipient}><i className={`mdi ${waSending ? 'mdi-loading mdi-spin' : 'mdi-send'}`}></i>Enviar WhatsApp</button>
        </form>
      </section>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-email-outline"></i>Correo <span className="psec-meta">{emailRecipient || '—'}</span></h3>
        <form className="add-form col" onSubmit={(e) => void sendEmail(e)}>
          <select className="fld" value={emailRecipient} onChange={(e) => setEmailRecipient(e.target.value)}>
            {emailOptions.length === 0 && <option value="">Sin correos registrados</option>}
            {emailOptions.map((email) => <option key={email} value={email}>{email}</option>)}
          </select>
          <input className="fld" placeholder="Asunto: Seguimiento de solicitud" value={emailSubject} onChange={(e) => setEmailSubject(e.target.value)} />
          <textarea className="fld" rows={4} placeholder="Escribe el correo…" value={emailBody} onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setEmailBody(e.target.value)}></textarea>
          <button className="btn-add self-end" type="submit" disabled={emailSending || !emailBody.trim() || !emailSubject.trim() || !emailRecipient}><i className={`mdi ${emailSending ? 'mdi-loading mdi-spin' : 'mdi-email-send-outline'}`}></i>Enviar correo</button>
        </form>
      </section>
      {formError && <div className="form-error" role="alert">{formError}</div>}
    </>
  );
}

// ---- Tab: Propuestas ----------------------------------------

function TabPropuestas({ sol }: { sol: Solicitud }) {
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
                <button disabled title="Envio disponible al conectar propuesta real"><i className="mdi mdi-send-outline"></i>Enviar</button>
                <button disabled title="PDF disponible al conectar propuesta real"><i className="mdi mdi-file-pdf-box"></i>PDF</button>
              </div>
            </div>
          </div>
        ))}
      </div>
      <button className="btn-add full" disabled title="Conecta primero el buscador de catálogo">
        <i className="mdi mdi-file-document-plus-outline"></i>Nuevo borrador de propuesta
      </button>
    </section>
  );
}

// ---- Tab: Documentos ----------------------------------------

function TabDocumentos({ sol }: { sol: Solicitud }) {
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
            <div className="doc-row is-disabled" key={i} title="Apertura de documentos pendiente de conectar al repositorio real">
              <span className="doc-ic"><i className={`mdi ${a.icon}`}></i></span>
              <div className="doc-info"><div className="doc-name2">{a.nombre}</div><div className="doc-meta">{a.peso} · {fmtDate(a.at)}</div></div>
              <i className="mdi mdi-download doc-dl"></i>
            </div>
          ))}
        </div>
        <button className="btn-add full" disabled title="Subida de documentos pendiente de conectar al repositorio real"><i className="mdi mdi-upload"></i>Subir documento</button>
      </section>
    </>
  );
}

// ---- DetailPanel (main export) ------------------------------

export interface DetailPanelProps {
  sol: Solicitud | null;
  open: boolean;
  crmCase: CrmCaseState | null;
  crmLoading: boolean;
  crmError: string | null;
  onClose: () => void;
  onToggleStep: (id: number, slug: string) => void;
  onAdvance: (id: number) => void;
  onToggleTask: (taskId: number, currentStatus: string) => Promise<void>;
  onAddTask: (title: string, priority: string) => Promise<void>;
  onAddNote: (txt: string) => Promise<void>;
  onDeleteNote: (noteId: number) => Promise<void>;
  onSendWhatsapp: (payload: { recipients: string[]; message: string }) => Promise<void>;
  onSendEmail: (payload: { to: string[]; cc?: string[]; subject: string; body: string }) => Promise<void>;
  onOpenPrefactura: (id: number) => void;
}

export function DetailPanel({ sol, open, onClose, onToggleStep, onAdvance, onToggleTask, onAddTask, crmCase, crmLoading, crmError, onAddNote, onDeleteNote, onSendWhatsapp, onSendEmail, onOpenPrefactura }: DetailPanelProps) {
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
              {crmLoading && <div className="mini-empty"><i className="mdi mdi-loading mdi-spin"></i> Cargando seguimiento CRM…</div>}
              {crmError && <div className="form-error" role="alert">{crmError}</div>}
              {tab === 'seguimiento' && <TabSeguimiento sol={sol} crmCase={crmCase} />}
              {tab === 'tareas' && <TabTareas sol={sol} crmCase={crmCase} onToggleTask={onToggleTask} onAddTask={onAddTask} />}
              {tab === 'notas' && <TabNotas sol={sol} crmCase={crmCase} onAddNote={onAddNote} onDeleteNote={onDeleteNote} />}
              {tab === 'comunicacion' && <TabComunicacion sol={sol} crmCase={crmCase} onSendWhatsapp={onSendWhatsapp} onSendEmail={onSendEmail} />}
              {tab === 'propuestas' && <TabPropuestas sol={sol} />}
              {tab === 'documentos' && <TabDocumentos sol={sol} />}
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
