/* MedForge · WhatsApp Chat v3 — Modals (ES module)
   nueva conversación (plantilla) · cerrar seguimiento · tour */

import React, { useState, useMemo, useEffect, useRef } from 'react';
import { searchContacts, startWithTemplate, uploadMedia } from './api.js';

const HEADER_ICON = { location: '📍', image: '🖼️', video: '🎥', document: '📄' };
const SEDE_OPTIONS = [
  { value: 'matriz',     label: 'Matriz' },
  { value: 'ceibos',    label: 'Ceibos' },
  { value: 'villa_club', label: 'Villa Club' },
];

// ── New conversation modal ────────────────────────────────────────────────────

export function WaNewConvoModal({ onClose, toast, templates = [], convos = [], prefill = null }) {
  const approvedTpls = useMemo(
    () => templates.filter(t => t.status === 'approved' || t.status === 'active' || !t.status),
    [templates]
  );

  const [q, setQ] = useState('');
  const [results, setResults] = useState([]);
  const [searching, setSearching] = useState(false);
  const [picked, setPicked] = useState(prefill?.number || null);
  const [number, setNumber] = useState(prefill?.number || '');
  const [contact, setContact] = useState(prefill?.contact || '');
  const [patient, setPatient] = useState(prefill?.patient || '');
  const [hc, setHc] = useState(prefill?.hc || '');
  const [tplId, setTplId] = useState(prefill?.tplId ? String(prefill.tplId) : '');
  const [vars, setVars] = useState(() => {
    if (prefill?.tplId) {
      const t = approvedTpls.find(x => String(x.id) === String(prefill.tplId));
      return (t?.preview?.variables || []).map(() => '');
    }
    return [];
  });
  const [locationSede, setLocationSede] = useState('');
  const [headerMediaUrl, setHeaderMediaUrl] = useState('');
  const [headerUploading, setHeaderUploading] = useState(false);
  const [submitting, setSubmitting] = useState(false);
  const [fb, setFb] = useState({ tone: '', text: prefill ? '' : 'Escribe para buscar o ingresa el número manualmente.' });
  const debounceRef = useRef(null);

  const tpl = approvedTpls.find(t => String(t.id) === String(tplId));
  const tplVars = tpl?.preview?.variables || [];
  const tplBody = tpl?.preview?.body_text || '';
  const tplHeaderType = tpl?.preview?.header_type || 'none';

  // Real-time search with debounce
  useEffect(() => {
    if (prefill) return; // skip search when patient is pre-selected
    if (!q.trim()) { setResults([]); return; }
    clearTimeout(debounceRef.current);
    debounceRef.current = setTimeout(async () => {
      setSearching(true);
      try {
        const result = await searchContacts(q);
        const found = result.data || [];
        setResults(found);
        if (found.length === 0) setFb({ tone: '', text: 'Sin resultados. Ingresa el número manualmente.' });
      } catch {
        setFb({ tone: 'danger', text: 'Error al buscar contactos.' });
      } finally { setSearching(false); }
    }, 350);
    return () => clearTimeout(debounceRef.current);
  }, [q, prefill]);

  const pick = (c) => {
    const wa = c.wa_number || c.wa || '';
    setPicked(wa);
    setNumber(wa);
    setContact(c.name || c.display_name || '');
    setPatient(c.patient_full_name || c.name || '');
    setHc(c.patient_hc_number || c.hc || '');
  };

  const onTpl = (id) => {
    setTplId(id);
    const t = approvedTpls.find(x => String(x.id) === String(id));
    setVars(t ? (t.preview?.variables || []).map(() => '') : []);
    setLocationSede('');
    setHeaderMediaUrl('');
  };

  const onHeaderFile = async (file) => {
    if (!file) return;
    setHeaderUploading(true);
    try {
      const res = await uploadMedia(file);
      const url = res?.data?.url || res?.data?.media_url || '';
      setHeaderMediaUrl(url);
    } catch {
      setFb({ tone: 'danger', text: 'Error al subir el archivo de cabecera.' });
    } finally { setHeaderUploading(false); }
  };

  const preview = tpl
    ? tplBody.replace(/\{\{(\d+)\}\}/g, (mm, i) => vars[Number(i) - 1] || mm)
    : 'Selecciona una plantilla para revisar el mensaje final.';

  const submit = async () => {
    if (!number.trim() || !tplId) { setFb({ tone: 'danger', text: 'Número y plantilla son obligatorios.' }); return; }
    if (tplHeaderType === 'location' && !locationSede) { setFb({ tone: 'danger', text: 'Selecciona la sede para la ubicación.' }); return; }
    if ((tplHeaderType === 'image' || tplHeaderType === 'video' || tplHeaderType === 'document') && !headerMediaUrl) {
      setFb({ tone: 'danger', text: 'Sube el archivo requerido por la cabecera.' }); return;
    }
    setSubmitting(true);
    setFb({ tone: '', text: 'Iniciando conversación…' });
    try {
      const result = await startWithTemplate({
        waNumber: number,
        templateId: tplId,
        variables: vars,
        contactName: contact || undefined,
        patientHcNumber: hc || undefined,
        patientFullName: patient || undefined,
        locationSede: locationSede || undefined,
        headerMediaUrl: headerMediaUrl || undefined,
      });
      toast(`Conversación iniciada con ${contact || number}`, 'mdi-message-plus');
      const convId = result?.data?.conversation?.id || null;
      onClose(convId);
    } catch (err) {
      setFb({ tone: 'danger', text: err?.response?.data?.error || err.message || 'Error al iniciar la conversación.' });
    } finally { setSubmitting(false); }
  };

  return (
    <div className="wa3-modal" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="wa3-modal__card">
        <div className="wa3-modal__head">
          <div>
            <h3>{prefill ? 'Enviar plantilla' : 'Nueva conversación con plantilla'}</h3>
            <div className="wa3-modal__sub">{prefill ? 'Completa las variables y envía la plantilla al paciente.' : 'Usa una plantilla aprobada para iniciar o continuar fuera de ventana.'}</div>
          </div>
          <button className="wa3-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="wa3-modal__body">
          <div className="wa3-modal__grid">
            {/* Left column: search (or locked patient card) + preview */}
            <div>
              {prefill ? (
                <div className="wa3-picker-card is-active" style={{ marginBottom: 12 }}>
                  <div>
                    <strong>{contact || number}</strong>
                    <small>{number}{hc ? ` · HC ${hc}` : ''}</small>
                  </div>
                  <i className="mdi mdi-account-check" style={{ color: 'var(--wa3-accent)', fontSize: 18 }}></i>
                </div>
              ) : (
                <>
                  <div className="wa3-field">
                    <label>Buscar paciente o número</label>
                    <div style={{ position: 'relative' }}>
                      <input value={q} onChange={e => setQ(e.target.value)} placeholder="Celular, HC, nombres o apellidos" style={{ paddingRight: 32 }} />
                      {searching && <i className="mdi mdi-loading mdi-spin" style={{ position: 'absolute', right: 10, top: '50%', transform: 'translateY(-50%)', color: 'var(--wa3-text-mute)' }}></i>}
                    </div>
                  </div>
                  <div className="wa3-picker-results">
                    {results.map((c, i) => {
                      const wa = c.wa_number || c.wa || '';
                      const existing = convos.find(cv => cv.wa === wa);
                      return (
                        <div key={i} className={`wa3-picker-card${picked === wa ? ' is-active' : ''}`} style={{ cursor: 'pointer' }} onClick={() => pick(c)}>
                          <div>
                            <strong>{c.name || c.display_name}</strong>
                            <small>{wa}{c.patient_hc_number || c.hc ? ` · HC ${c.patient_hc_number || c.hc}` : ''}</small>
                            {existing && <small style={{ color: 'var(--wa3-accent)', marginTop: 2, display: 'block' }}>💬 Conversación activa</small>}
                          </div>
                          <div style={{ display: 'flex', gap: 6, alignItems: 'center' }}>
                            {existing && (
                              <a href={`/v3/whatsapp/chat?conversation=${existing.id}`} className="wa3-secondary-btn" style={{ fontSize: 11, padding: '3px 8px', whiteSpace: 'nowrap' }} onClick={e => e.stopPropagation()}>
                                Abrir chat
                              </a>
                            )}
                            <i className="mdi mdi-chevron-right" style={{ color: 'var(--wa3-text-mute)', fontSize: 18 }}></i>
                          </div>
                        </div>
                      );
                    })}
                  </div>
                </>
              )}
              <div className="wa3-field" style={{ marginTop: 12 }}>
                <label>Preview del mensaje</label>
                <div className="wa3-template-preview">{preview}</div>
              </div>
            </div>
            {/* Right column: patient fields + template selector + variables */}
            <div>
              {!prefill && <>
                <div className="wa3-field"><label>Número WhatsApp</label><input value={number} onChange={e => setNumber(e.target.value)} placeholder="593999111222" /></div>
                <div className="wa3-field"><label>Nombre visible</label><input value={contact} onChange={e => setContact(e.target.value)} placeholder="Nombre del contacto" /></div>
                <div className="wa3-field"><label>Paciente</label><input value={patient} onChange={e => setPatient(e.target.value)} placeholder="Nombres y apellidos" /></div>
                <div className="wa3-field"><label>HC</label><input value={hc} onChange={e => setHc(e.target.value)} placeholder="Historia clínica" /></div>
              </>}
              <div className="wa3-field">
                <label>Plantilla aprobada</label>
                <select value={tplId} onChange={e => onTpl(e.target.value)}>
                  <option value="">Selecciona una plantilla</option>
                  {approvedTpls.map(t => {
                    const hIcon = HEADER_ICON[t.preview?.header_type] || '';
                    return (
                      <option key={t.id} value={t.id}>
                        {hIcon ? `${hIcon} ` : ''}{t.name || t.display_name} · {t.language || 'es'}
                      </option>
                    );
                  })}
                </select>
              </div>
              {tpl && tplHeaderType === 'location' && (
                <div className="wa3-field">
                  <label>📍 Sede (ubicación)</label>
                  <select value={locationSede} onChange={e => setLocationSede(e.target.value)}>
                    <option value="">Selecciona la sede</option>
                    {SEDE_OPTIONS.map(s => <option key={s.value} value={s.value}>{s.label}</option>)}
                  </select>
                </div>
              )}
              {tpl && (tplHeaderType === 'image' || tplHeaderType === 'video' || tplHeaderType === 'document') && (
                <div className="wa3-field">
                  <label>{HEADER_ICON[tplHeaderType]} Archivo de cabecera ({tplHeaderType})</label>
                  {headerMediaUrl ? (
                    <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                      <i className="mdi mdi-check-circle" style={{ color: 'var(--wa3-accent)' }}></i>
                      <span style={{ fontSize: 12, color: 'var(--wa3-text-mute)', flex: 1, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' }}>{headerMediaUrl.split('/').pop()}</span>
                      <button className="wa3-secondary-btn" style={{ fontSize: 11, padding: '2px 8px' }} onClick={() => setHeaderMediaUrl('')}>Cambiar</button>
                    </div>
                  ) : (
                    <label className="wa3-upload-label" style={{ display: 'flex', alignItems: 'center', gap: 8, cursor: 'pointer' }}>
                      <input type="file" style={{ display: 'none' }}
                        accept={tplHeaderType === 'image' ? 'image/*' : tplHeaderType === 'video' ? 'video/*' : '*/*'}
                        onChange={e => onHeaderFile(e.target.files?.[0])} />
                      {headerUploading
                        ? <><i className="mdi mdi-loading mdi-spin"></i> Subiendo…</>
                        : <><i className="mdi mdi-upload"></i> Subir archivo</>}
                    </label>
                  )}
                </div>
              )}
              {tpl && tplVars.map((v, i) => (
                <div key={i} className="wa3-field">
                  <label>Variable {i + 1}</label>
                  <input value={vars[i] || ''} placeholder={`{{${i + 1}}}`}
                         onChange={e => setVars(arr => { const n = [...arr]; n[i] = e.target.value; return n; })} />
                </div>
              ))}
            </div>
          </div>
          <div className="wa3-feedback" data-tone={fb.tone} style={{ marginTop: 12 }}>{fb.text}</div>
        </div>
        <div className="wa3-modal__foot">
          <div className="wa3-modal__sub">Esto crea o reutiliza la conversación y la deja abierta en tu inbox.</div>
          <button className="wa3-primary-btn" onClick={submit} disabled={submitting}>
            {submitting ? <><i className="mdi mdi-loading mdi-spin" style={{ marginRight: 6 }}></i>Iniciando…</> : 'Iniciar con plantilla'}
          </button>
        </div>
      </div>
    </div>
  );
}

// ── Close followup modal ──────────────────────────────────────────────────────

export function WaFollowupModal({ onClose, onConfirm }) {
  const [reason, setReason] = useState('');
  const [fb, setFb] = useState({ tone: '', text: '' });
  const submit = () => {
    if (!reason.trim()) { setFb({ tone: 'danger', text: 'El motivo del cierre es obligatorio.' }); return; }
    onConfirm(reason.trim());
  };
  return (
    <div className="wa3-modal" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="wa3-modal__card" style={{ width: 'min(520px, 100%)' }}>
        <div className="wa3-modal__head">
          <div>
            <h3>Cerrar seguimiento</h3>
            <div className="wa3-modal__sub">No elimina al paciente ni el historial. Cierra la conversación y genera un lead de seguimiento.</div>
          </div>
          <button className="wa3-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="wa3-modal__body">
          <div className="wa3-field">
            <label>Motivo del cierre</label>
            <textarea value={reason} onChange={e => setReason(e.target.value)} placeholder="Ej.: paciente no responde, retomar seguimiento comercial, pidió contacto posterior…"></textarea>
          </div>
          <div className="wa3-feedback" data-tone={fb.tone}>{fb.text}</div>
        </div>
        <div className="wa3-modal__foot">
          <button className="wa3-secondary-btn" onClick={onClose}>Cancelar</button>
          <button className="wa3-primary-btn" onClick={submit}>Cerrar seguimiento</button>
        </div>
      </div>
    </div>
  );
}

// ── Welcome tour modal ────────────────────────────────────────────────────────

const WA_TOUR_STEPS = [
  {
    selector: null,
    icon: 'mdi-whatsapp',
    title: 'Bienvenido al chat WhatsApp',
    copy: 'Este es el centro de operaciones renovado. Tienes tres zonas: la bandeja de conversaciones a la izquierda, el hilo de mensajes en el centro y la ficha del paciente a la derecha. En móvil cambias entre zonas con un toque.',
  },
  {
    selector: '.wa3-inbox',
    icon: 'mdi-inbox-multiple-outline',
    title: 'Bandeja de conversaciones',
    copy: 'Aquí ves todos los chats activos. Usa las pestañas para filtrar: Mis chats, Todos, Pendientes de handoff o Sin plantilla. El buscador y el filtro de fechas te permiten encontrar cualquier conversación al instante.',
  },
  {
    selector: '.wa3-thread__actions',
    icon: 'mdi-tools',
    title: 'Herramientas del chat',
    copy: 'Desde esta barra puedes tomar la conversación, transferirla a otro agente o equipo, enviar una plantilla oficial, buscar en el historial o cerrar el caso. El botón ⋮ agrupa las acciones menos frecuentes.',
  },
  {
    selector: '.wa3-messages',
    icon: 'mdi-message-text-outline',
    title: 'Historial de mensajes',
    copy: 'Aquí ves todo el intercambio con el paciente en orden cronológico. Los mensajes nuevos aparecen automáticamente sin recargar la página. Imágenes, audios y documentos se pueden abrir directamente.',
  },
  {
    selector: '.wa3-composer',
    icon: 'mdi-pencil-outline',
    title: 'Escribe tu respuesta',
    copy: 'Escribe en el cuadro de texto y pulsa Enter o el botón enviar. También puedes adjuntar imágenes y documentos, usar respuestas rápidas guardadas, insertar emojis o grabar un audio de voz.',
  },
  {
    selector: '.wa3-drawer',
    icon: 'mdi-card-account-details-outline',
    title: 'Panel del paciente',
    copy: 'Aquí están los datos del contacto, las notas internas del equipo (solo visibles para los agentes), el historial de cambios de la conversación y las respuestas rápidas. Puedes crear notas y respuestas rápidas nuevas en el momento.',
  },
];

export function WaTourModal({ onClose }) {
  const [idx, setIdx] = useState(0);
  const steps = useMemo(
    () => WA_TOUR_STEPS.filter(s => !s.selector || document.querySelector(s.selector)),
    [],
  );
  const step = steps[idx];

  useEffect(() => {
    if (!step?.selector) return;
    const el = document.querySelector(step.selector);
    if (el) {
      el.classList.add('wa3-tour-focus');
      el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
    }
    return () => { if (el) el.classList.remove('wa3-tour-focus'); };
  }, [step]);

  if (!step) return null;
  const last = idx === steps.length - 1;

  return (
    <div className="wa3-modal wa3-tour-modal" onMouseDown={e => { if (e.target === e.currentTarget) onClose(); }}>
      <div className="wa3-modal__card">
        <div className="wa3-modal__head">
          <div>
            <h3>Recorrido del chat</h3>
            <div className="wa3-modal__sub">Conoce las zonas principales en {steps.length} pasos.</div>
          </div>
          <button className="wa3-iconbtn" onClick={onClose}><i className="mdi mdi-close"></i></button>
        </div>
        <div className="wa3-modal__body">
          {step.icon && <div className="wa3-tour-icon"><i className={`mdi ${step.icon}`}></i></div>}
          <h4 className="wa3-tour-step__title">{step.title}</h4>
          <p>{step.copy}</p>
          <div className="wa3-tour-step">
            <span>{idx + 1} / {steps.length}</span>
            <span className="wa3-tour-step__bar"><span style={{ width: `${((idx + 1) / steps.length) * 100}%` }}></span></span>
          </div>
        </div>
        <div className="wa3-modal__foot">
          <button className="wa3-secondary-btn" disabled={idx === 0} onClick={() => setIdx(i => Math.max(0, i - 1))}>← Anterior</button>
          <div style={{ display: 'flex', gap: 8 }}>
            <button className="wa3-secondary-btn" onClick={onClose}>Saltar</button>
            {!last && <button className="wa3-primary-btn" onClick={() => setIdx(i => Math.min(steps.length - 1, i + 1))}>Siguiente →</button>}
            {last  && <button className="wa3-primary-btn" onClick={onClose}>¡Listo, explorar!</button>}
          </div>
        </div>
      </div>
    </div>
  );
}
