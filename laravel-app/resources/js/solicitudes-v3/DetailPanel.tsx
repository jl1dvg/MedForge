// ============================================================
// MedForge · Solicitudes v3 — Panel CRM (workspace por pestañas)
// ============================================================
import React, { useState, useEffect, useRef } from 'react';
import type { Solicitud, CrmCaseState, CrmCaseProposal } from './types';
import { DocAvatar, fmtDate, fmtDateTime, fmtSla, SLA_META } from './components';
import { crmProposalPdfUrl, resolveCoverageMailDraft, searchCrmCatalogCodes, searchCrmCatalogPackages } from './api';
import type { CoverageMailDraft } from './api';

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

const isTaskDone = (status: string) => ['done', 'completed', 'completada', 'completado'].includes(status.toLowerCase());
const textToHtml = (text: string) => text
  .split(/\n{2,}/)
  .map((part) => `<p>${part.replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch] ?? ch)).replace(/\n/g, '<br>')}</p>`)
  .join('');

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
  (sol.detalle?.notas || []).forEach((n) => ev.push({ act: 'Nota: ' + n.txt, time: fmtDateTime(n.at), by: n.by, note: true }));
  return ev.sort((a, b) => new Date(b.time).getTime() - new Date(a.time).getTime()).slice(0, 8);
}

// ---- Tab: Seguimiento ----------------------------------------

function TabSeguimiento({
  sol,
  crmCase,
  onAssignResponsible,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onAssignResponsible: (responsibleId: number | null) => Promise<void>;
}) {
  const timeline = buildTimeline(sol, crmCase);
  const telefono = crmCase?.contacts.primaryPhone || (sol.detalle.paciente.telefono !== '—' ? sol.detalle.paciente.telefono : sol.crm.telefono);
  const planAfiliacion = crmCase?.insurancePlan || (sol.plan_seguro !== '—' ? sol.plan_seguro : sol.afiliacion_label);
  const responsable = crmCase?.responsibleName || sol.crm.responsable;
  const fuente = crmCase?.source || sol.crm.fuente;
  const whatsapp = crmCase?.contacts.whatsapp;
  const whatsappUrl = whatsapp?.conversationUrl || whatsapp?.searchUrl || null;
  const whatsappLabel = whatsapp?.matched && whatsapp?.conversationId
    ? `Conversación #${whatsapp.conversationId} · ${whatsapp.unreadCount} sin leer · Último mensaje ${fmtDateTime(whatsapp.lastMessageAt)}`
    : whatsappUrl
      ? `Buscar conversación ${whatsapp?.search || telefono}`
      : '—';
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
          <div className="info-item">
            <div className="k">Responsable</div>
            <div className="v">
              {crmCase?.options.responsables.length ? (
                <select className="fld crm-inline-select" value={crmCase.responsibleId ?? ''} onChange={(e) => void onAssignResponsible(e.target.value ? Number(e.target.value) : null)}>
                  <option value="">Sin responsable</option>
                  {crmCase.options.responsables.map((user) => <option key={user.id} value={user.id}>{user.nombre}</option>)}
                </select>
              ) : responsable}
            </div>
          </div>
          <div className="info-item"><div className="k">Plan afiliación</div><div className="v">{planAfiliacion}</div></div>
          <div className="info-item"><div className="k">Fuente / convenio</div><div className="v">{fuente}</div></div>
          <div className="info-item"><div className="k">Teléfono</div><div className="v">{telefono}</div></div>
          <div className="info-item"><div className="k">Sede</div><div className="v">{sol.sede}</div></div>
          <div className="info-item info-item-wide">
            <div className="k">WhatsApp</div>
            <div className="v">
              {whatsappUrl ? (
                <a className="crm-link" href={whatsappUrl} target="_blank" rel="noreferrer">{whatsappLabel}</a>
              ) : whatsappLabel}
            </div>
          </div>
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
        <span className="psec-meta">{crmTasks ? crmTasks.filter((t) => !isTaskDone(t.status)).length : sol.detalle.tareas.filter((t) => !t.done).length} pendientes</span>
      </h3>
      <div className="task-list">
        {tareas.length === 0 && <div className="mini-empty">Sin tareas registradas</div>}
        {crmTasks ? (
          crmTasks.map((tk) => (
            <div key={tk.id} className={`task-row ${isTaskDone(tk.status) ? 'done' : ''}`} onClick={() => void toggle(tk.id, tk.status)}>
              <span className="chk-box">{isTaskDone(tk.status) && <i className="mdi mdi-check"></i>}</span>
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
  onAddContact,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onSendWhatsapp: (payload: { recipients: string[]; message: string }) => Promise<void>;
  onSendEmail: (payload: { to: string[]; cc?: string[]; subject: string; body: string }) => Promise<void>;
  onAddContact: (type: 'phone' | 'email', value: string) => Promise<void>;
}) {
  const [wa, setWa] = useState('');
  const [waRecipient, setWaRecipient] = useState('');
  const [waSending, setWaSending] = useState(false);
  const [emailBody, setEmailBody] = useState('');
  const [emailSubject, setEmailSubject] = useState('Seguimiento de solicitud');
  const [emailRecipient, setEmailRecipient] = useState('');
  const [emailSending, setEmailSending] = useState(false);
  const [savingContact, setSavingContact] = useState<'phone' | 'email' | null>(null);
  const [formError, setFormError] = useState<string | null>(null);
  const phoneOptions = Array.from(new Set([crmCase?.contacts.primaryPhone, crmCase?.contacts.whatsapp.waNumber, ...(crmCase?.contacts.alternatePhones ?? []), sol.crm.telefono].filter((v): v is string => Boolean(v && v !== '—'))));
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
  const addContact = async (type: 'phone' | 'email') => {
    const label = type === 'phone' ? 'teléfono' : 'correo';
    const value = window.prompt(`Agregar ${label}`);
    if (value == null) return;
    const clean = value.trim();
    if (!clean || savingContact) return;
    setSavingContact(type);
    setFormError(null);
    try {
      await onAddContact(type, clean);
    } catch (err) {
      setFormError(err instanceof Error ? err.message : `No se pudo guardar el ${label}.`);
    } finally {
      setSavingContact(null);
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
          <button className="prop-outline-btn crm-contact-add" type="button" disabled={savingContact === 'phone'} onClick={() => void addContact('phone')}>
            <i className={`mdi ${savingContact === 'phone' ? 'mdi-loading mdi-spin' : 'mdi-phone-plus-outline'}`}></i>Agregar teléfono
          </button>
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
          <button className="prop-outline-btn crm-contact-add" type="button" disabled={savingContact === 'email'} onClick={() => void addContact('email')}>
            <i className={`mdi ${savingContact === 'email' ? 'mdi-loading mdi-spin' : 'mdi-email-plus-outline'}`}></i>Agregar correo
          </button>
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

type ProposalCatalogKind = 'code' | 'package';

type ProposalCatalogItem = {
  key: string;
  kind: ProposalCatalogKind;
  id: number;
  code: string;
  title: string;
  description: string;
  unitPrice: number;
};

type ProposalDraftItem = {
  key: string;
  catalogType?: ProposalCatalogKind;
  catalogId?: number;
  code: string;
  description: string;
  quantity: number;
  unitPrice: number;
  discountPercent: number;
};

function proposalCatalogItem(row: Record<string, unknown>, kind: ProposalCatalogKind): ProposalCatalogItem {
  const id = Number(row.id ?? row.catalog_id ?? 0);
  const code = String(row.codigo ?? row.code ?? row.slug ?? (kind === 'package' ? 'PAQ' : 'COD'));
  const title = String(row.name ?? row.nombre ?? row.descripcion ?? row.description ?? code);
  const description = String(row.descripcion ?? row.description ?? row.short_description ?? title);
  const unitPrice = Number(row.unit_price ?? row.price ?? row.total_amount ?? row.computed_total ?? row.default_price ?? 0);

  return {
    key: `${kind}:${id}`,
    kind,
    id,
    code,
    title,
    description,
    unitPrice: Number.isFinite(unitPrice) ? unitPrice : 0,
  };
}

function TabPropuestas({
  sol,
  crmCase,
  onCreateProposal,
  onSendProposalEmail,
  onSendProposalWhatsapp,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onCreateProposal: (payload: Record<string, unknown>) => Promise<void>;
  onSendProposalEmail: (proposalId: number, to: string) => Promise<void>;
  onSendProposalWhatsapp: (proposalId: number) => Promise<void>;
}) {
  const proposals = crmCase?.proposals ?? [];
  const PROP_STATE_TONE: Record<string, string> = { Borrador: 'none', Enviada: 'warn', Aceptada: 'ok', Rechazada: 'warn' };
  const currentAffiliation = crmCase?.insurancePlan !== '—' ? crmCase?.insurancePlan : sol.plan_seguro;
  const [mode, setMode] = useState<ProposalCatalogKind>('code');
  const [query, setQuery] = useState('');
  const [affiliation, setAffiliation] = useState(currentAffiliation && currentAffiliation !== '—' ? currentAffiliation : 'Particular');
  const [proposalTitle, setProposalTitle] = useState(`Propuesta quirúrgica — ${sol.procedimiento_short}`);
  const [validUntil, setValidUntil] = useState('');
  const [taxRate, setTaxRate] = useState(0);
  const [notes, setNotes] = useState('');
  const [results, setResults] = useState<ProposalCatalogItem[]>([]);
  const [draftItems, setDraftItems] = useState<ProposalDraftItem[]>([
    {
      key: 'manual:initial',
      code: '',
      description: sol.procedimiento,
      quantity: 1,
      unitPrice: 0,
      discountPercent: 0,
    },
  ]);
  const [loading, setLoading] = useState(false);
  const [saving, setSaving] = useState(false);
  const [sendingId, setSendingId] = useState<number | null>(null);
  const [formError, setFormError] = useState<string | null>(null);

  useEffect(() => {
    const nextAffiliation = currentAffiliation && currentAffiliation !== '—' ? currentAffiliation : 'Particular';
    setAffiliation((current) => current || nextAffiliation);
  }, [currentAffiliation]);

  useEffect(() => {
    setProposalTitle(`Propuesta quirúrgica — ${sol.procedimiento_short}`);
    setDraftItems([{
      key: `manual:${sol.id}`,
      code: '',
      description: sol.procedimiento,
      quantity: 1,
      unitPrice: 0,
      discountPercent: 0,
    }]);
    setQuery('');
    setResults([]);
    setNotes('');
    setValidUntil('');
    setTaxRate(0);
  }, [sol.id, sol.procedimiento, sol.procedimiento_short]);

  const runSearch = async (e: React.FormEvent) => {
    e.preventDefault();
    const q = query.trim();
    if (!q || loading) return;
    setLoading(true);
    setFormError(null);
    try {
      const rows = mode === 'code'
        ? await searchCrmCatalogCodes(q, affiliation)
        : await searchCrmCatalogPackages(q, affiliation);
      setResults(rows.map((row) => proposalCatalogItem(row, mode)).filter((item) => item.id > 0));
    } catch (err) {
      setResults([]);
      setFormError(err instanceof Error ? err.message : 'No se pudo buscar en catálogo.');
    } finally {
      setLoading(false);
    }
  };

  const addCatalogItem = (item: ProposalCatalogItem) => {
    setDraftItems((items) => [
      ...items,
      {
        key: `${item.key}:${Date.now()}`,
        catalogType: item.kind,
        catalogId: item.id,
        code: item.code,
        description: item.description,
        quantity: 1,
        unitPrice: item.unitPrice,
        discountPercent: 0,
      },
    ]);
    setResults([]);
    setQuery('');
  };

  const addManualLine = () => {
    setDraftItems((items) => [
      ...items,
      {
        key: `manual:${Date.now()}:${items.length}`,
        code: '',
        description: '',
        quantity: 1,
        unitPrice: 0,
        discountPercent: 0,
      },
    ]);
  };

  const updateDraftItem = (key: string, patch: Partial<ProposalDraftItem>) => {
    setDraftItems((items) => items.map((item) => item.key === key ? { ...item, ...patch } : item));
  };

  const removeDraftItem = (key: string) => {
    setDraftItems((items) => items.length <= 1 ? items : items.filter((item) => item.key !== key));
  };

  const createProposal = async () => {
    const items = draftItems
      .map((item) => ({
        catalog_type: item.catalogType,
        catalog_id: item.catalogId,
        quantity: item.quantity,
        description: item.description.trim(),
        unit_price: item.unitPrice,
        discount_percent: item.discountPercent,
      }))
      .filter((item) => item.description);
    if (items.length === 0 || saving) return;
    setSaving(true);
    setFormError(null);
    try {
      await onCreateProposal({
        title: proposalTitle.trim() || `Propuesta quirúrgica — ${sol.procedimiento_short}`,
        valid_until: validUntil || null,
        tax_rate: Number(taxRate) || 0,
        notes,
        send_by: 'none',
        pricing_affiliation: affiliation,
        items,
      });
      setDraftItems([{
        key: `manual:${Date.now()}`,
        code: '',
        description: sol.procedimiento,
        quantity: 1,
        unitPrice: 0,
        discountPercent: 0,
      }]);
      setResults([]);
      setQuery('');
      setNotes('');
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'No se pudo crear la propuesta.');
    } finally {
      setSaving(false);
    }
  };

  const openPdf = (proposal: CrmCaseProposal) => {
    const url = proposal.pdfUrl || crmProposalPdfUrl(proposal.id);
    window.open(url, '_blank', 'noopener');
  };

  const sendWhatsapp = async (proposal: CrmCaseProposal) => {
    if (sendingId != null) return;
    const ok = window.confirm('Se enviará la propuesta por WhatsApp usando el link público. ¿Continuar?');
    if (!ok) return;
    setSendingId(proposal.id);
    setFormError(null);
    try {
      await onSendProposalWhatsapp(proposal.id);
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'No se pudo enviar la propuesta por WhatsApp.');
    } finally {
      setSendingId(null);
    }
  };

  const sendEmail = async (proposal: CrmCaseProposal) => {
    if (sendingId != null) return;
    const defaultEmail = crmCase?.contacts.primaryEmail && crmCase.contacts.primaryEmail !== '—' ? crmCase.contacts.primaryEmail : '';
    const value = window.prompt('Correo de destino', defaultEmail);
    if (value == null) return;
    const email = value.trim();
    if (!email) {
      setFormError('Indica un correo para enviar la propuesta.');
      return;
    }
    setSendingId(proposal.id);
    setFormError(null);
    try {
      await onSendProposalEmail(proposal.id, email);
    } catch (err) {
      setFormError(err instanceof Error ? err.message : 'No se pudo enviar la propuesta por correo.');
    } finally {
      setSendingId(null);
    }
  };

  return (
    <section>
      <h3 className="psec-title"><i className="mdi mdi-file-document-edit-outline"></i>Propuestas CRM <span className="psec-meta">{proposals.length}</span></h3>
      <div className="prop-list">
        {proposals.length === 0 && <div className="mini-empty">Sin propuestas. Crea un borrador vinculado al lead.</div>}
        {proposals.map((p) => (
          <div className="prop-card" key={p.id}>
            <div className="prop-head">
              <div className="prop-title">{p.title} <span className="prop-number">{p.number}</span></div>
              <span className={`conc-status conc-${PROP_STATE_TONE[p.statusLabel] || 'none'}`}>{p.statusLabel}</span>
            </div>
            <div className="prop-items">
              {p.items.length === 0 && <div className="mini-empty">Sin ítems registrados</div>}
              {p.items.map((it) => (
                <div className="prop-item" key={it.id || `${it.code}-${it.description}`}>
                  <span className="pi-cod">{it.code || '—'}</span>
                  <span className="pi-desc">{it.description}</span>
                  <span className="pi-qty">×{it.quantity}</span>
                  <span className="pi-val">{money(it.total)}</span>
                </div>
              ))}
            </div>
            <div className="prop-tot">
              <span>Subtotal <b>{money(p.subtotal)}</b></span>
              <span>IVA <b>{money(p.taxTotal)}</b></span>
              <span className="pt-total">Total <b>{money(p.total)}</b></span>
            </div>
            <div className="prop-foot">
              <span className="prop-vig"><i className="mdi mdi-calendar-clock-outline"></i>{p.validUntil ? `Vigente hasta ${fmtDate(p.validUntil)}` : 'Sin vigencia'}</span>
              <div className="prop-actions">
                <button type="button" disabled={sendingId === p.id} onClick={() => void sendWhatsapp(p)}><i className={`mdi ${sendingId === p.id ? 'mdi-loading mdi-spin' : 'mdi-send-outline'}`}></i>WhatsApp</button>
                <button type="button" disabled={sendingId === p.id} onClick={() => void sendEmail(p)}><i className={`mdi ${sendingId === p.id ? 'mdi-loading mdi-spin' : 'mdi-email-outline'}`}></i>Email</button>
                <button type="button" onClick={() => openPdf(p)}><i className="mdi mdi-file-pdf-box"></i>PDF</button>
              </div>
            </div>
          </div>
        ))}
      </div>
      <div className="prop-builder">
        <div className="prop-form-grid">
          <label className="prop-field prop-field-title">
            <span>Título</span>
            <input className="fld" value={proposalTitle} onChange={(e) => setProposalTitle(e.target.value)} placeholder="Propuesta quirúrgica / paquete" />
          </label>
          <label className="prop-field">
            <span>Vigencia</span>
            <input className="fld" type="date" value={validUntil} onChange={(e) => setValidUntil(e.target.value)} />
          </label>
          <label className="prop-field">
            <span>IVA %</span>
            <input className="fld" type="number" min="0" max="100" step="0.01" value={taxRate} onChange={(e) => setTaxRate(Number(e.target.value))} />
          </label>
          <label className="prop-field prop-field-rate">
            <span>Tarifa a aplicar</span>
            <select className="fld" value={affiliation} onChange={(e) => setAffiliation(e.target.value)}>
              {currentAffiliation && currentAffiliation !== '—' && <option value={currentAffiliation}>Afiliación del paciente: {currentAffiliation}</option>}
              <option value="Particular">Particular</option>
            </select>
          </label>
        </div>

        <div className="prop-builder-head">
          <span>Ítems de la propuesta</span>
          <div className="prop-builder-actions">
            <button type="button" className="prop-outline-btn" onClick={addManualLine}><i className="mdi mdi-plus"></i>Línea manual</button>
            <button type="button" className={`prop-outline-btn ${mode === 'code' ? 'is-active' : ''}`} onClick={() => { setMode('code'); setResults([]); }}><i className="mdi mdi-barcode-scan"></i>Buscar código</button>
            <button type="button" className={`prop-outline-btn ${mode === 'package' ? 'is-active' : ''}`} onClick={() => { setMode('package'); setResults([]); }}><i className="mdi mdi-package-variant"></i>Agregar paquete</button>
          </div>
        </div>

        <form className="prop-search-form" onSubmit={(e) => void runSearch(e)}>
          <input className="fld" placeholder={mode === 'code' ? 'Buscar código o descripción…' : 'Buscar paquete…'} value={query} onChange={(e) => setQuery(e.target.value)} />
          <button className="btn-add" type="submit" disabled={loading || !query.trim()}>
            <i className={`mdi ${loading ? 'mdi-loading mdi-spin' : 'mdi-magnify'}`}></i>Buscar
          </button>
        </form>
        <div className="prop-search-list">
          {results.map((item) => (
            <button className="prop-search-row" type="button" key={item.key} onClick={() => addCatalogItem(item)}>
              <span className="pi-cod">{item.code}</span>
              <span className="pi-desc">{item.title}</span>
              <span className="pi-val">{money(item.unitPrice)}</span>
            </button>
          ))}
        </div>

        <div className="prop-draft-list">
          {draftItems.map((item) => (
            <div className="prop-draft-row" key={item.key}>
              <input className="fld" value={item.description} onChange={(e) => updateDraftItem(item.key, { description: e.target.value })} placeholder="Descripción del ítem" />
              <input className="fld" type="number" min="0.01" step="0.01" value={item.quantity} aria-label="Cantidad" onChange={(e) => updateDraftItem(item.key, { quantity: Number(e.target.value) || 1 })} />
              <input className="fld" type="number" min="0" step="0.01" value={item.unitPrice} aria-label="Precio unitario" onChange={(e) => updateDraftItem(item.key, { unitPrice: Number(e.target.value) || 0 })} />
              <input className="fld" type="number" min="0" max="100" step="0.01" value={item.discountPercent} aria-label="Descuento" onChange={(e) => updateDraftItem(item.key, { discountPercent: Number(e.target.value) || 0 })} />
              <button type="button" className="prop-remove-line" aria-label="Eliminar línea" onClick={() => removeDraftItem(item.key)}><i className="mdi mdi-close"></i></button>
            </div>
          ))}
        </div>

        <label className="prop-field">
          <span>Notas</span>
          <textarea className="fld" rows={3} value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Condiciones, observaciones o alcance" />
        </label>
        {formError && <div className="form-error" role="alert">{formError}</div>}
        <div className="prop-builder-foot">
          <span>Se creará como borrador vinculado al caso. Precio: {affiliation}.</span>
          <button className="btn-add" type="button" disabled={saving || draftItems.every((item) => !item.description.trim())} onClick={() => void createProposal()}>
            <i className={`mdi ${saving ? 'mdi-loading mdi-spin' : 'mdi-file-document-plus-outline'}`}></i>Crear propuesta
          </button>
        </div>
      </div>
    </section>
  );
}

// ---- Tab: Documentos ----------------------------------------

function TabDocumentos({
  sol,
  crmCase,
  onRescrapeDerivacion,
  onUploadDocument,
  onSendCoverageMail,
}: {
  sol: Solicitud;
  crmCase: CrmCaseState | null;
  onRescrapeDerivacion: (id: number) => Promise<void>;
  onUploadDocument: (file: File, descripcion: string) => Promise<void>;
  onSendCoverageMail: (payload: { to: string; cc: string; subject: string; body: string; attachment?: File | null; isHtml?: boolean; templateKey?: string | null; derivacionPdf?: string | null }) => Promise<void>;
}) {
  const adj = sol.detalle.adjuntos;
  const der = sol.detalle.derivacion;
  const coverageMails = sol.detalle.cobertura_mails;
  const [scraping, setScraping] = useState(false);
  const [scrapeError, setScrapeError] = useState<string | null>(null);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadDesc, setUploadDesc] = useState('');
  const [uploading, setUploading] = useState(false);
  const [uploadError, setUploadError] = useState<string | null>(null);
  const [mailModalOpen, setMailModalOpen] = useState(false);
  const [mailDraft, setMailDraft] = useState<CoverageMailDraft | null>(null);
  const [mailLoading, setMailLoading] = useState(false);
  const [mailTo, setMailTo] = useState('');
  const [mailCc, setMailCc] = useState('');
  const [mailSubject, setMailSubject] = useState(`Cobertura solicitud ${sol.form_id}`);
  const [mailBodyHtml, setMailBodyHtml] = useState(textToHtml(`Estimados,\n\nSolicitamos por favor revisar la cobertura de la solicitud ${sol.form_id} del paciente ${sol.full_name}.\n\nProcedimiento: ${sol.procedimiento}.\n\nQuedamos atentos.`));
  const [mailAttachment, setMailAttachment] = useState<File | null>(null);
  const [sendingMail, setSendingMail] = useState(false);
  const [mailError, setMailError] = useState<string | null>(null);
  const mailBodyRef = useRef<HTMLDivElement | null>(null);

  useEffect(() => {
    setMailModalOpen(false);
    setMailDraft(null);
    setMailTo('');
    setMailSubject(`Cobertura solicitud ${sol.form_id}`);
    setMailBodyHtml(textToHtml(`Estimados,\n\nSolicitamos por favor revisar la cobertura de la solicitud ${sol.form_id} del paciente ${sol.full_name}.\n\nProcedimiento: ${sol.procedimiento}.\n\nQuedamos atentos.`));
    setMailCc('');
    setMailAttachment(null);
    setUploadFile(null);
    setUploadDesc('');
    setUploadError(null);
    setMailError(null);
  }, [sol.id, sol.form_id, sol.full_name, sol.procedimiento]);

  const runScrape = async () => {
    if (scraping) return;
    setScraping(true);
    setScrapeError(null);
    try {
      await onRescrapeDerivacion(sol.id);
    } catch (err) {
      setScrapeError(err instanceof Error ? err.message : 'No se pudo re-scrapear la derivación.');
    } finally {
      setScraping(false);
    }
  };
  const submitUpload = async () => {
    if (!uploadFile || uploading) return;
    setUploading(true);
    setUploadError(null);
    try {
      await onUploadDocument(uploadFile, uploadDesc);
      setUploadFile(null);
      setUploadDesc('');
    } catch (err) {
      setUploadError(err instanceof Error ? err.message : 'No se pudo subir el documento.');
    } finally {
      setUploading(false);
    }
  };
  const openCoverageModal = async () => {
    if (mailLoading) return;
    setMailLoading(true);
    setMailError(null);
    try {
      const draft = await resolveCoverageMailDraft(sol);
      const template = draft.template;
      setMailDraft(draft);
      setMailTo(template?.to ?? '');
      setMailCc(template?.cc ?? '');
      setMailSubject(template?.subject || `Solicitud de cobertura ${sol.form_id}`);
      setMailBodyHtml(template?.bodyHtml || textToHtml(template?.bodyText || `Estimados,\n\nSolicitamos por favor revisar la cobertura de la solicitud ${sol.form_id} del paciente ${sol.full_name}.\n\nProcedimiento: ${sol.procedimiento}.\n\nQuedamos atentos.`));
      setMailAttachment(null);
      setMailModalOpen(true);
    } catch (err) {
      setMailError(err instanceof Error ? err.message : 'No se pudo preparar el correo de cobertura.');
    } finally {
      setMailLoading(false);
    }
  };
  const submitCoverageMail = async () => {
    if (sendingMail) return;
    const html = mailBodyRef.current?.innerHTML?.trim() || mailBodyHtml;
    setSendingMail(true);
    setMailError(null);
    try {
      await onSendCoverageMail({
        to: mailTo,
        cc: mailCc,
        subject: mailSubject,
        body: html,
        attachment: mailAttachment,
        isHtml: true,
        templateKey: mailDraft?.template?.key ?? null,
        derivacionPdf: sol.detalle.derivacion.archivo_href,
      });
      setMailAttachment(null);
      setMailModalOpen(false);
    } catch (err) {
      setMailError(err instanceof Error ? err.message : 'No se pudo enviar el correo de cobertura.');
    } finally {
      setSendingMail(false);
    }
  };
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
        ) : (
          <div className="cover-card cover-empty-action">
            <div className="mini-empty">Sin derivación registrada para esta solicitud.</div>
            <button className="btn-add full" type="button" disabled={scraping} onClick={() => void runScrape()}>
              <i className={`mdi ${scraping ? 'mdi-loading mdi-spin' : 'mdi-refresh'}`}></i>Re-scrapear derivación
            </button>
            {scrapeError && <div className="form-error" role="alert">{scrapeError}</div>}
          </div>
        )}
      </section>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-email-check-outline"></i>Correos de cobertura <span className="psec-meta">{coverageMails.length}</span></h3>
        <button className="btn-add full" type="button" disabled={mailLoading} onClick={() => void openCoverageModal()}>
          <i className={`mdi ${mailLoading ? 'mdi-loading mdi-spin' : 'mdi-email-fast-outline'}`}></i>Solicitar cobertura
        </button>
        {mailError && !mailModalOpen && <div className="form-error" role="alert">{mailError}</div>}
        <div className="coverage-mail-list">
          {coverageMails.length === 0 && <div className="mini-empty">Sin correos de cobertura registrados</div>}
          {coverageMails.map((mail) => (
            <div className={`coverage-mail-row ${mail.status === 'failed' ? 'is-failed' : ''}`} key={mail.id ?? `${mail.subject}-${mail.sentAt}`}>
              <span className="doc-ic"><i className={`mdi ${mail.status === 'failed' ? 'mdi-email-alert-outline' : 'mdi-email-check-outline'}`}></i></span>
              <div className="doc-info">
                <div className="doc-name2">{mail.subject}</div>
                <div className="doc-meta">{mail.to || 'Destinatario por defecto'} · {fmtDateTime(mail.sentAt || mail.createdAt || '')}</div>
                {mail.attachmentName && <div className="doc-meta">Adjunto: {mail.attachmentName}</div>}
                {mail.errorMessage && <div className="doc-meta danger">{mail.errorMessage}</div>}
              </div>
              <span className={`status-pill ${mail.status === 'failed' ? 'danger' : 'ok'}`}>{mail.status === 'failed' ? 'fallido' : 'enviado'}</span>
            </div>
          ))}
        </div>
      </section>
      <section>
        <h3 className="psec-title"><i className="mdi mdi-paperclip"></i>Documentos adjuntos <span className="psec-meta">{adj.length}</span></h3>
        <div className="doc-list">
          {adj.length === 0 && <div className="mini-empty">Sin documentos adjuntos</div>}
          {adj.map((a, i) => (
            <a className={`doc-row ${a.href ? '' : 'is-disabled'}`} key={a.id ?? i} href={a.href ?? undefined} target="_blank" rel="noreferrer" title={a.href ? 'Abrir documento' : 'Documento sin ruta registrada'}>
              <span className="doc-ic"><i className={`mdi ${a.icon}`}></i></span>
              <div className="doc-info"><div className="doc-name2">{a.nombre}</div><div className="doc-meta">{a.peso} · {fmtDate(a.at)}</div>{a.descripcion && <div className="doc-meta">{a.descripcion}</div>}</div>
              <i className={`mdi ${a.href ? 'mdi-open-in-new' : 'mdi-alert-circle-outline'} doc-dl`}></i>
            </a>
          ))}
        </div>
        <div className="doc-upload-box">
          <label className="file-picker">
            <input type="file" onChange={(e) => setUploadFile(e.target.files?.[0] ?? null)} />
            <i className="mdi mdi-upload"></i>
            <span>{uploadFile ? uploadFile.name : 'Seleccionar documento'}</span>
          </label>
          <input className="fld" value={uploadDesc} onChange={(e) => setUploadDesc(e.target.value)} placeholder="Descripción del documento" />
          {uploadError && <div className="form-error" role="alert">{uploadError}</div>}
          <button className="btn-add full" type="button" disabled={!uploadFile || uploading} onClick={() => void submitUpload()}>
            <i className={`mdi ${uploading ? 'mdi-loading mdi-spin' : 'mdi-upload'}`}></i>Subir documento
          </button>
        </div>
      </section>
      {mailModalOpen && (
        <div className="coverage-modal-backdrop" role="presentation">
          <div className="coverage-modal" role="dialog" aria-modal="true" aria-label="Solicitar cobertura por correo">
            <header className="coverage-modal-head">
              <div>
                <h3>Solicitar cobertura por correo</h3>
                <p>{sol.full_name} · HC {sol.hc_number} · {sol.form_id}</p>
              </div>
              <button type="button" className="panel-close" onClick={() => setMailModalOpen(false)} aria-label="Cerrar"><i className="mdi mdi-close"></i></button>
            </header>
            <div className="coverage-modal-body">
              <div className="coverage-sender-box">
                <span>Desde</span>
                <b>{mailDraft?.sender.fromName || 'SMTP no configurado'} {mailDraft?.sender.fromAddress ? `<${mailDraft.sender.fromAddress}>` : ''}</b>
                {mailDraft?.sender.replyToAddress && <small>Responder a: {mailDraft.sender.replyToName || mailDraft.sender.replyToAddress} &lt;{mailDraft.sender.replyToAddress}&gt;</small>}
                {mailDraft && !mailDraft.sender.configured && <small className="danger">No hay perfil SMTP válido para solicitudes.</small>}
              </div>
              {!mailDraft?.template && <div className="cover-alert"><i className="mdi mdi-email-alert-outline"></i>No hay plantilla configurada para esta afiliación; puedes completar el correo manualmente.</div>}
              {der.archivo_href && <a className="coverage-pdf-link" href={der.archivo_href} target="_blank" rel="noreferrer"><i className="mdi mdi-file-pdf-box"></i>Ver PDF de respaldo de derivación</a>}
              <div className="coverage-grid">
                <label className="prop-field"><span>Para</span><input className="fld" value={mailTo} onChange={(e) => setMailTo(e.target.value)} placeholder="Vacío usa destino interno configurado" /></label>
                <label className="prop-field"><span>CC</span><input className="fld" value={mailCc} onChange={(e) => setMailCc(e.target.value)} placeholder="CC opcional" /></label>
              </div>
              <label className="prop-field"><span>Asunto</span><input className="fld" value={mailSubject} onChange={(e) => setMailSubject(e.target.value)} placeholder="Asunto" /></label>
              <label className="prop-field">
                <span>Template del correo</span>
                <div
                  key={`${sol.id}-${mailDraft?.template?.key ?? 'manual'}-${mailBodyHtml.length}`}
                  ref={mailBodyRef}
                  className="coverage-editor"
                  contentEditable
                  suppressContentEditableWarning
                  dangerouslySetInnerHTML={{ __html: mailBodyHtml }}
                />
              </label>
              <label className="file-picker">
                <input type="file" onChange={(e) => setMailAttachment(e.target.files?.[0] ?? null)} />
                <i className="mdi mdi-paperclip"></i>
                <span>{mailAttachment ? mailAttachment.name : 'Adjuntar archivo opcional'}</span>
              </label>
              {mailError && <div className="form-error" role="alert">{mailError}</div>}
            </div>
            <footer className="coverage-modal-foot">
              <button className="btn" type="button" onClick={() => setMailModalOpen(false)}>Cancelar</button>
              <button className="btn btn-primary" type="button" disabled={sendingMail || !mailSubject.trim() || !mailDraft?.sender.configured} onClick={() => void submitCoverageMail()}>
                <i className={`mdi ${sendingMail ? 'mdi-loading mdi-spin' : 'mdi-send-outline'}`}></i>Enviar cobertura
              </button>
            </footer>
          </div>
        </div>
      )}
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
  onAssignResponsible: (responsibleId: number | null) => Promise<void>;
  onAddContact: (type: 'phone' | 'email', value: string) => Promise<void>;
  onRescrapeDerivacion: (id: number) => Promise<void>;
  onUploadDocument: (file: File, descripcion: string) => Promise<void>;
  onSendCoverageMail: (payload: { to: string; cc: string; subject: string; body: string; attachment?: File | null; isHtml?: boolean; templateKey?: string | null; derivacionPdf?: string | null }) => Promise<void>;
  onAddNote: (txt: string) => Promise<void>;
  onDeleteNote: (noteId: number) => Promise<void>;
  onSendWhatsapp: (payload: { recipients: string[]; message: string }) => Promise<void>;
  onSendEmail: (payload: { to: string[]; cc?: string[]; subject: string; body: string }) => Promise<void>;
  onCreateProposal: (payload: Record<string, unknown>) => Promise<void>;
  onSendProposalEmail: (proposalId: number, to: string) => Promise<void>;
  onSendProposalWhatsapp: (proposalId: number) => Promise<void>;
  onOpenPrefactura: (id: number) => void;
}

export function DetailPanel({ sol, open, onClose, onToggleStep, onAdvance, onToggleTask, onAddTask, onAssignResponsible, onAddContact, onRescrapeDerivacion, onUploadDocument, onSendCoverageMail, crmCase, crmLoading, crmError, onAddNote, onDeleteNote, onSendWhatsapp, onSendEmail, onCreateProposal, onSendProposalEmail, onSendProposalWhatsapp, onOpenPrefactura }: DetailPanelProps) {
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
              {tab === 'seguimiento' && <TabSeguimiento sol={sol} crmCase={crmCase} onAssignResponsible={onAssignResponsible} />}
              {tab === 'tareas' && <TabTareas sol={sol} crmCase={crmCase} onToggleTask={onToggleTask} onAddTask={onAddTask} />}
              {tab === 'notas' && <TabNotas sol={sol} crmCase={crmCase} onAddNote={onAddNote} onDeleteNote={onDeleteNote} />}
              {tab === 'comunicacion' && <TabComunicacion sol={sol} crmCase={crmCase} onSendWhatsapp={onSendWhatsapp} onSendEmail={onSendEmail} onAddContact={onAddContact} />}
              {tab === 'propuestas' && <TabPropuestas sol={sol} crmCase={crmCase} onCreateProposal={onCreateProposal} onSendProposalEmail={onSendProposalEmail} onSendProposalWhatsapp={onSendProposalWhatsapp} />}
              {tab === 'documentos' && <TabDocumentos sol={sol} crmCase={crmCase} onRescrapeDerivacion={onRescrapeDerivacion} onUploadDocument={onUploadDocument} onSendCoverageMail={onSendCoverageMail} />}
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
