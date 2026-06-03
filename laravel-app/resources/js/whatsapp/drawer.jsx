/* MedForge · WhatsApp Chat v3 — Patient drawer (ES module)
   ficha · atribución · trazabilidad · notas · productividad · admin */

import React, { useState } from 'react';

function WaNoteForm({ onAdd }) {
  const [body, setBody] = useState('');
  const [fb, setFb] = useState({ tone: '', text: '' });
  const submit = async () => {
    if (!body.trim()) { setFb({ tone: 'danger', text: 'Escribe una nota.' }); return; }
    try {
      await onAdd(body.trim());
      setBody('');
      setFb({ tone: 'success', text: 'Nota guardada.' });
      setTimeout(() => setFb({ tone: '', text: '' }), 1800);
    } catch {
      setFb({ tone: 'danger', text: 'Error al guardar nota.' });
    }
  };
  return (
    <>
      <div className="wa3-field" style={{ marginTop: 10 }}>
        <textarea value={body} onChange={e => setBody(e.target.value)} placeholder="Agregar nota interna…"></textarea>
      </div>
      <div className="wa3-action-row">
        <span className="wa3-feedback" data-tone={fb.tone}>{fb.text}</span>
        <button className="wa3-primary-btn" onClick={submit}>Guardar nota</button>
      </div>
    </>
  );
}

function WaQuickReplyForm({ onCreate }) {
  const [title, setTitle] = useState('');
  const [body, setBody] = useState('');
  const [fb, setFb] = useState({ tone: '', text: '' });
  const submit = () => {
    if (!title.trim() || !body.trim()) { setFb({ tone: 'danger', text: 'Título y texto son obligatorios.' }); return; }
    onCreate(title.trim(), body.trim());
    setTitle(''); setBody('');
    setFb({ tone: 'success', text: 'Respuesta rápida creada.' });
    setTimeout(() => setFb({ tone: '', text: '' }), 1800);
  };
  return (
    <>
      <div className="wa3-field"><input value={title} onChange={e => setTitle(e.target.value)} placeholder="Título de respuesta rápida" /></div>
      <div className="wa3-field"><textarea value={body} onChange={e => setBody(e.target.value)} placeholder="Texto de respuesta rápida"></textarea></div>
      <div className="wa3-action-row">
        <span className="wa3-feedback" data-tone={fb.tone}>{fb.text}</span>
        <button className="wa3-secondary-btn" onClick={submit}>Crear respuesta rápida</button>
      </div>
    </>
  );
}

export function WaDrawer({ convo, notes, trail, canOperate, onAddNote, onCreateQuickReply, onFollowup }) {
  if (!convo) return null;
  const p = convo.patient || {};
  return (
    <aside className="wa3-drawer" id="wa3-drawer">
      <div className="wa3-drawer__profile">
        <div className="wa3-avatar" data-tone={convo.tone}>{convo.initials}</div>
        <h3>{p.name || convo.name}</h3>
        <p>{convo.hc ? `HC ${convo.hc}${p.age ? ` · ${p.age} años` : ''}` : 'Sin paciente vinculado'}</p>
        <div className="wa3-drawer__quickactions">
          <button className="wa3-quickaction"><i className="mdi mdi-phone-outline"></i>Llamar</button>
          <button className="wa3-quickaction"><i className="mdi mdi-calendar-plus-outline"></i>Agendar</button>
          <button className="wa3-quickaction"><i className="mdi mdi-file-eye-outline"></i>Ficha</button>
        </div>
      </div>

      <div className="wa3-drawer__section">
        <h6>Paciente</h6>
        <div className="wa3-kv">
          <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-phone-outline"></i>Teléfono</span><span className="v">{convo.wa}</span></div>
          {convo.assignedRole && <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-account-group-outline"></i>Equipo</span><span className="v">{convo.assignedRole}</span></div>}
          {convo.assignedTo && <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-tag-outline"></i>Responsable</span><span className="v">{convo.assignedTo}</span></div>}
          <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-map-marker-path"></i>Estado</span><span className="v">{convo.opStatus}</span></div>
          <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-speedometer"></i>Prioridad</span><span className="v">{convo.priority}</span></div>
          <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-timer-sand"></i>Ventana</span><span className="v">{convo.window === 'open' ? '24h abierta' : 'Sólo plantilla'}</span></div>
          {p.dx && <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-stethoscope"></i>Diagnóstico</span><span className="v">{p.dx}</span></div>}
          {p.nextAppt && <div className="wa3-kv__row"><span className="k"><i className="mdi mdi-calendar-clock"></i>Próxima cita</span><span className="v">{p.nextAppt}</span></div>}
        </div>
      </div>

      {convo.attribution && (
        <div className="wa3-drawer__section">
          <h6>Atribución</h6>
          <div className="wa3-tags">
            {convo.queue && <span className="wa3-tag">{convo.queue}</span>}
            <span className="wa3-tag">{convo.attribution}</span>
          </div>
        </div>
      )}

      <div className="wa3-drawer__section" id="wa3-trail-section">
        <h6>Trazabilidad</h6>
        {trail.length === 0 && <div style={{ font: '400 12px var(--font-body)', color: 'var(--wa3-text-mute)' }}>Sin eventos registrados.</div>}
        <div className="wa3-trail-flat">
          {trail.map((e, i) => (
            <div key={i} className="wa3-trail-flat__item">
              <strong>{e.event_label || e.event_type}</strong>
              <div className="wa3-trail-flat__meta">{e.actor_name || ''}{e.created_at_label ? ` · ${e.created_at_label}` : ''}</div>
            </div>
          ))}
        </div>
      </div>

      <div className="wa3-drawer__section">
        <h6>Notas internas</h6>
        <div id="wa3-notes-list">
          {notes.length === 0 && <div style={{ font: '400 12px var(--font-body)', color: 'var(--wa3-text-mute)' }}>Sin notas internas.</div>}
          {notes.map((n, i) => {
            const when = n.created_at
              ? new Date(n.created_at).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' })
              : (n.at || '');
            return (
              <div key={i} className="wa3-note">
                <div className="wa3-note__who">{n.author_name || n.author || 'Equipo'}{when ? ` · ${when}` : ''}</div>
                {n.body}
              </div>
            );
          })}
        </div>
        <WaNoteForm onAdd={onAddNote} />
      </div>

      <div className="wa3-drawer__section">
        <h6>Productividad</h6>
        <WaQuickReplyForm onCreate={onCreateQuickReply} />
      </div>

      {canOperate && (
        <div className="wa3-drawer__section">
          <h6>Acciones administrativas</h6>
          <button className="wa3-admin-btn" onClick={onFollowup}>
            <i className="mdi mdi-archive-arrow-down-outline"></i>Cerrar seguimiento
          </button>
        </div>
      )}
    </aside>
  );
}
