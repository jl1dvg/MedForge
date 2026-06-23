/* MedForge · WhatsApp Chat v3 — Thread pane (ES module)
   header menus · context bar · chat search · messages · composer */

import React, { useState, useRef, useEffect, useMemo, useCallback } from 'react';
import { useWaMenu } from './components.jsx';
import { uploadMedia } from './api.js';

// ── WhatsApp markdown → HTML ──────────────────────────────────────────────────

export function waFormat(text) {
  if (!text) return '';
  let s = text.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  s = s.replace(/\*([^*\n]+)\*/g, '<strong>$1</strong>')
       .replace(/_([^_\n]+)_/g, '<em>$1</em>')
       .replace(/~([^~\n]+)~/g, '<del>$1</del>')
       .replace(/`([^`\n]+)`/g, '<code>$1</code>');
  return s;
}

// ── Bubble ────────────────────────────────────────────────────────────────────

function WaBubble({ m, query }) {
  const highlight = (html) => {
    if (!query) return html;
    try {
      const re = new RegExp(`(${query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')})`, 'gi');
      return html.replace(re, '<mark class="wa3-search-hl">$1</mark>');
    } catch { return html; }
  };
  const matches = query && m.body && m.body.toLowerCase().includes(query.toLowerCase());
  return (
    <div className={`wa3-msg is-${m.dir}${matches ? ' is-search-match' : ''}${m._current ? ' is-search-current' : ''}`}>
      <div className="wa3-bubble">
        {m.quote && <div className="wa3-quote"><span className="who">{m.quote.who}</span>{m.quote.text}</div>}
        {m.media && m.media.type === 'image' && (
          <div className="wa3-media-img">
            <img
              src={m.media.imageUrl || m.media.downloadUrl}
              alt={m.media.name || 'Imagen'}
              style={{ maxWidth: '100%', borderRadius: 8, display: 'block', cursor: 'pointer' }}
              onClick={() => window.open(m.media.imageUrl || m.media.downloadUrl, '_blank')}
              onError={e => { e.currentTarget.style.display = 'none'; }}
            />
          </div>
        )}
        {m.media && m.media.type === 'file' && (
          <div className="wa3-media">
            <i className={`mdi ${m.media.icon || 'mdi-file-document-outline'}`}></i>
            <div className="wa3-media__body">
              <strong>{m.media.name}</strong>
              <small>{m.media.size}</small>
              {m.media.downloadUrl && (
                <a href={m.media.downloadUrl} className="wa3-media__dl" download target="_blank" rel="noreferrer">
                  <i className="mdi mdi-download"></i>
                </a>
              )}
            </div>
          </div>
        )}
        {m.media && m.media.type === 'audio' && (
          <div className="wa3-media wa3-media--audio">
            <audio
              controls
              preload="none"
              style={{ width: '100%', minWidth: 220, maxWidth: 320 }}
              src={m.media.downloadUrl}
            >
              Tu navegador no soporta reproducción de audio.
            </audio>
            {m.media.voice && (
              <small style={{ display: 'block', marginTop: 2, opacity: 0.6, fontSize: 11 }}>
                <i className="mdi mdi-microphone" style={{ fontSize: 11 }}></i> Nota de voz
              </small>
            )}
          </div>
        )}
        {m.body && <div dangerouslySetInnerHTML={{ __html: highlight(waFormat(m.body)) }} />}
        <div className="wa3-bubble__meta">
          <span>{m.time}</span>
          {m.dir === 'out' && m.status === 'read'      && <i className="mdi mdi-check-all read"></i>}
          {m.dir === 'out' && m.status === 'delivered' && <i className="mdi mdi-check-all"></i>}
          {m.dir === 'out' && m.status === 'sent'      && <i className="mdi mdi-check"></i>}
          {m.dir === 'out' && m.status === 'pending'   && <i className="mdi mdi-clock-outline"></i>}
          {m.dir === 'out' && m.status === 'failed'    && <i className="mdi mdi-alert-circle-outline" style={{ color: 'var(--wa3-danger)' }}></i>}
        </div>
      </div>
    </div>
  );
}

function WaThread({ items, typing, query, currentIdx }) {
  const ref = useRef(null);
  useEffect(() => {
    if (ref.current && !query) ref.current.scrollTop = ref.current.scrollHeight;
  }, [items.length, typing, query]);
  let matchCounter = -1;
  return (
    <div className="wa3-messages" ref={ref}>
      {items.map((it, i) => {
        if (it.kind === 'date')  return <div key={i} className="wa3-date">{it.text}</div>;
        if (it.kind === 'event') return <div key={i} className="wa3-event"><i className={`mdi ${it.icon}`}></i>{it.text}</div>;
        let isCurrent = false;
        if (query && it.body && it.body.toLowerCase().includes(query.toLowerCase())) {
          matchCounter += 1;
          isCurrent = matchCounter === currentIdx;
        }
        return <WaBubble key={i} m={{ ...it, _current: isCurrent }} query={query} />;
      })}
      {typing && <div className="wa3-typing"><span></span><span></span><span></span></div>}
    </div>
  );
}

// ── Header dropdown shell ─────────────────────────────────────────────────────

function WaHeaderMenu({ icon, label, children, success }) {
  const [open, setOpen, ref] = useWaMenu();
  return (
    <div className="wa3-hbtn-wrap" ref={ref}>
      <button className={`wa3-hbtn${open ? ' is-open' : ''}${success ? ' is-success' : ''}`} type="button" onClick={() => setOpen(v => !v)}>
        <i className={`mdi ${icon}`}></i><span>{label}</span>
      </button>
      {open && <div className="wa3-hbtn__menu">{typeof children === 'function' ? children(() => setOpen(false)) : children}</div>}
    </div>
  );
}

// ── Transfer + Queue by role ──────────────────────────────────────────────────

function WaTransferMenu({ convo, agents, roles, onTransfer, onQueueRole, toast }) {
  const [note, setNote] = useState('');
  return (
    <WaHeaderMenu icon="mdi-account-arrow-right-outline" label="Transferir">
      {(close) => (
        <>
          <div style={{ maxHeight: '55vh', overflowY: 'auto' }}>
            <h6>Transferir a un agente</h6>
            {agents.filter(a => a.role !== 'Automático').map(a => (
              <button key={a.id} className="wa3-menu-item" onClick={() => { onTransfer(a, note); close(); }}>
                <i className="mdi mdi-account-outline lead"></i>
                <span>{a.name}<span className="meta">{a.role} · {a.active} chats abiertos</span></span>
                <span className="dot" data-state={a.status === 'online' ? 'online' : a.status === 'busy' ? 'busy' : 'away'}></span>
              </button>
            ))}
            <h6>Derivar por equipo</h6>
            {roles.map(r => (
              <button key={r.id} className="wa3-menu-item" onClick={() => { onQueueRole(r, note); close(); }}>
                <i className={`mdi ${r.icon} lead`}></i>
                <span>{r.name}<span className="meta">{r.open} en cola</span></span>
              </button>
            ))}
          </div>
          <div className="wa3-menu-footer">
            <input placeholder="Nota de transferencia (opcional)" value={note} onChange={e => setNote(e.target.value)} />
          </div>
        </>
      )}
    </WaHeaderMenu>
  );
}

// ── Templates ─────────────────────────────────────────────────────────────────

const HEADER_ICON = { location: '📍', image: '🖼️', video: '🎥', document: '📄' };

function WaTemplatesMenu({ templates, onSelectTemplate }) {
  const approved = templates.filter(t => t.status === 'approved' || t.status === 'active' || !t.status);
  return (
    <WaHeaderMenu icon="mdi-file-document-outline" label="Plantillas">
      {(close) => (
        <>
          <h6>Plantillas aprobadas</h6>
          {approved.length === 0 && (
            <div style={{ padding: '8px 12px', color: 'var(--wa3-text-mute)', fontSize: 13 }}>Sin plantillas disponibles.</div>
          )}
          <div style={{ maxHeight: '55vh', overflowY: 'auto' }}>
            {approved.map(t => {
              const hIcon = HEADER_ICON[t.preview?.header_type] || '';
              return (
                <button key={t.id} className="wa3-menu-item" onClick={() => { onSelectTemplate(t); close(); }}>
                  <i className="mdi mdi-clipboard-text-outline lead"></i>
                  <span>
                    {hIcon && <span style={{ marginRight: 4 }}>{hIcon}</span>}
                    {t.name || t.display_name}
                    <span className="meta">{(t.category || 'utility').toUpperCase()} · {t.language || 'es'}</span>
                  </span>
                </button>
              );
            })}
          </div>
        </>
      )}
    </WaHeaderMenu>
  );
}

// ── More options ──────────────────────────────────────────────────────────────

function WaMoreMenu({ convo, onCopy, onOpenTrail, onFollowup, canOperate }) {
  const [open, setOpen, ref] = useWaMenu();
  return (
    <div className="wa3-hbtn-wrap" ref={ref}>
      <button className="wa3-iconbtn" title="Más opciones" onClick={() => setOpen(v => !v)}><i className="mdi mdi-dots-vertical"></i></button>
      {open && (
        <div className="wa3-hbtn__menu">
          <h6>Más opciones</h6>
          <button className="wa3-menu-item" onClick={() => { onCopy(convo.wa, 'WhatsApp'); setOpen(false); }}>
            <i className="mdi mdi-content-copy lead"></i><span>Copiar WhatsApp<span className="meta">{convo.wa}</span></span>
          </button>
          {convo.hc && (
            <button className="wa3-menu-item" onClick={() => { onCopy(convo.hc, 'HC'); setOpen(false); }}>
              <i className="mdi mdi-card-account-details-outline lead"></i><span>Copiar HC<span className="meta">{convo.hc}</span></span>
            </button>
          )}
          <button className="wa3-menu-item" onClick={() => { onOpenTrail(); setOpen(false); }}>
            <i className="mdi mdi-timeline-text-outline lead"></i><span>Ver trazabilidad<span className="meta">Abrir panel lateral</span></span>
          </button>
          {canOperate && (
            <button className="wa3-menu-item" onClick={() => { onFollowup(); setOpen(false); }}>
              <i className="mdi mdi-archive-arrow-down-outline lead"></i><span>Cerrar seguimiento<span className="meta">Genera lead WhatsApp</span></span>
            </button>
          )}
          <button className="wa3-menu-item" onClick={() => { onCopy(`/v3/whatsapp/chat?conversation=${convo.id}`, 'Link'); setOpen(false); }}>
            <i className="mdi mdi-link-variant lead"></i><span>Copiar link<span className="meta">Abrir esta conversación</span></span>
          </button>
        </div>
      )}
    </div>
  );
}

// ── Context bar ───────────────────────────────────────────────────────────────

function WaContextBar({ convo }) {
  return (
    <div className="wa3-context">
      <span className="wa3-context__item"><i className="mdi mdi-map-marker-path"></i>{convo.opStatus}</span>
      <span className="sep">·</span>
      <span className="wa3-context__item"><i className="mdi mdi-speedometer"></i>Prioridad <strong>{convo.priority}</strong></span>
      <span className="sep">·</span>
      <span className="wa3-context__item"><i className="mdi mdi-account-voice"></i>Último: {convo.lastActor}</span>
      {convo.isMine ? (
        <><span className="sep">·</span><span className="wa3-context__item wa3-context__item--mine"><i className="mdi mdi-account-check-outline"></i><strong>Asignada a ti</strong></span></>
      ) : convo.assignedTo ? (
        <><span className="sep">·</span><span className="wa3-context__item"><i className="mdi mdi-account-outline"></i>{convo.assignedTo}</span></>
      ) : null}
      <span className="sep">·</span>
      {convo.window === 'open'
        ? <span className="wa3-context__item wa3-context__item--open"><i className="mdi mdi-timer-sand"></i>Ventana 24h <strong>abierta</strong></span>
        : <span className="wa3-context__item"><i className="mdi mdi-file-document-edit-outline"></i>Sólo plantilla</span>}
      {convo.queue && <><span className="sep">·</span><span className="wa3-context__item"><i className="mdi mdi-tag-outline"></i>{convo.queue}</span></>}
      {convo.attribution && <><span className="sep">·</span><span className="wa3-context__item"><i className="mdi mdi-bullseye-arrow"></i>{convo.attribution}</span></>}
    </div>
  );
}

// ── Chat search ───────────────────────────────────────────────────────────────

function WaChatSearch({ open, query, onQuery, count, idx, onPrev, onNext, onClose }) {
  const inputRef = useRef(null);
  useEffect(() => { if (open) inputRef.current?.focus(); }, [open]);
  if (!open) return null;
  return (
    <div className="wa3-chat-search is-open">
      <i className="mdi mdi-magnify" style={{ color: 'var(--wa3-text-mute)', fontSize: 18 }}></i>
      <input ref={inputRef} type="search" value={query} placeholder="Buscar dentro de esta conversación…"
             onChange={e => onQuery(e.target.value)}
             onKeyDown={e => { if (e.key === 'Enter') { e.preventDefault(); e.shiftKey ? onPrev() : onNext(); } if (e.key === 'Escape') onClose(); }} />
      <span className="wa3-chat-search__count">{count > 0 ? `${idx + 1}/${count}` : '0/0'}</span>
      <button className="wa3-iconbtn" onClick={onPrev} title="Anterior"><i className="mdi mdi-chevron-up"></i></button>
      <button className="wa3-iconbtn" onClick={onNext} title="Siguiente"><i className="mdi mdi-chevron-down"></i></button>
      <button className="wa3-iconbtn" onClick={onClose} title="Cerrar"><i className="mdi mdi-close"></i></button>
    </div>
  );
}

// ── Composer ──────────────────────────────────────────────────────────────────

export const EMOJIS = ['👁️','👀','🙂','😊','🙏','✅','📅','🕒','📍','🏥','👨‍⚕️','👩‍⚕️','🤓','💬','📄','🔎','⚠️','😔','👍','✨','🟢','🔴','🟡','📞'];

function WaComposer({ value, onChange, onSend, onSendMedia, convo, toast }) {
  const ta = useRef(null);
  const [emojiOpen, setEmojiOpen, emojiRef] = useWaMenu();
  const [recording, setRecording] = useState(false);
  const [recordingTime, setRecordingTime] = useState(0);
  const [pendingMedia, setPendingMedia] = useState(null); // null | { name, type, uploading, uploaded }
  const fileRef = useRef(null);
  const mrRef = useRef(null);
  const chunksRef = useRef([]);
  const timerRef = useRef(null);
  const recTimeRef = useRef(0);

  useEffect(() => {
    const el = ta.current; if (!el) return;
    el.style.height = 'auto';
    el.style.height = Math.min(el.scrollHeight, 140) + 'px';
  }, [value]);

  // Cleanup on unmount
  useEffect(() => () => {
    clearInterval(timerRef.current);
    mrRef.current?.stream?.getTracks().forEach(t => t.stop());
  }, []);

  const insertEmoji = (emoji) => {
    const el = ta.current;
    if (!el) { onChange(value + emoji); return; }
    const s = el.selectionStart ?? value.length, e2 = el.selectionEnd ?? value.length;
    onChange(value.slice(0, s) + emoji + value.slice(e2));
    requestAnimationFrame(() => { el.focus(); const p = s + emoji.length; el.setSelectionRange(p, p); });
  };

  const fmtTime = (s) => `${Math.floor(s / 60).toString().padStart(2, '0')}:${(s % 60).toString().padStart(2, '0')}`;

  const doUpload = useCallback(async (file, type, name) => {
    setPendingMedia({ name, type, uploading: true, uploaded: null });
    try {
      const result = await uploadMedia(file);
      if (result.ok) {
        setPendingMedia(prev => ({ ...prev, uploading: false, uploaded: result.data }));
      } else {
        throw new Error(result.error || 'Upload failed');
      }
    } catch {
      toast('Error al subir el archivo', 'mdi-alert');
      setPendingMedia(null);
    }
  }, [toast]);

  const toggleVoice = async () => {
    if (recording) {
      mrRef.current?.stop();
      setRecording(false);
      clearInterval(timerRef.current);
    } else {
      try {
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        const mimeType = MediaRecorder.isTypeSupported('audio/webm;codecs=opus')
          ? 'audio/webm;codecs=opus' : 'audio/webm';
        const mr = new MediaRecorder(stream, { mimeType });
        chunksRef.current = [];
        mr.ondataavailable = (e) => { if (e.data.size > 0) chunksRef.current.push(e.data); };
        mr.onstop = () => {
          stream.getTracks().forEach(t => t.stop());
          const blob = new Blob(chunksRef.current, { type: mimeType });
          const ext = mimeType.includes('ogg') ? 'ogg' : 'webm';
          const file = new File([blob], `nota-de-voz.${ext}`, { type: mimeType });
          doUpload(file, 'audio', `Nota de voz · ${fmtTime(recTimeRef.current)}`);
          recTimeRef.current = 0;
        };
        mr.start();
        mrRef.current = mr;
        setRecording(true);
        setRecordingTime(0);
        recTimeRef.current = 0;
        timerRef.current = setInterval(() => {
          recTimeRef.current += 1;
          setRecordingTime(t => t + 1);
        }, 1000);
        toast('Grabando… pulsa otra vez para detener', 'mdi-record-circle');
      } catch {
        toast('No se pudo acceder al micrófono', 'mdi-alert');
      }
    }
  };

  const onPickFile = (e) => {
    const f = e.target.files?.[0];
    if (!f) return;
    e.target.value = '';
    const type = f.type.startsWith('image/') ? 'image'
               : f.type.startsWith('audio/') ? 'audio'
               : f.type.startsWith('video/') ? 'video'
               : 'document';
    doUpload(f, type, f.name);
  };

  const handleSend = () => {
    if (pendingMedia) {
      if (pendingMedia.uploading) {
        toast('Espera, aún se está subiendo el archivo…', 'mdi-upload');
        return;
      }
      if (pendingMedia.uploaded && onSendMedia) {
        onSendMedia(pendingMedia.type, pendingMedia.uploaded, value.trim());
        setPendingMedia(null);
        onChange('');
      }
      return;
    }
    onSend();
  };

  const canReply = convo.canSend ?? (convo.window === 'open');
  const canSend = canReply && (!!value.trim() || !!pendingMedia);

  return (
    <div className="wa3-composer">
      <div className="wa3-composer__row">
        <button className="wa3-iconbtn" title="Adjuntar archivo" onClick={() => fileRef.current?.click()} disabled={recording}>
          <i className="mdi mdi-paperclip"></i>
        </button>
        <input ref={fileRef} type="file" style={{ display: 'none' }} onChange={onPickFile}
               accept="image/*,audio/*,video/*,application/pdf,.doc,.docx,.xls,.xlsx" />
        <textarea ref={ta} rows={1} value={value} onChange={e => onChange(e.target.value)}
                  onKeyDown={e => { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); handleSend(); } }}
                  placeholder={recording ? `Grabando ${fmtTime(recordingTime)}…` : canReply ? 'Escribe un mensaje…' : 'Ventana cerrada — usa una plantilla aprobada'}
                  disabled={!canReply || recording} />
        <div className="wa3-composer__tools">
          <button className="wa3-iconbtn" title="Notas de voz no disponibles en este formato" disabled style={{ opacity: 0.35, cursor: 'not-allowed' }}>
            <i className="mdi mdi-microphone-outline"></i>
          </button>
          <div className="wa3-emoji-wrap" ref={emojiRef}>
            <button className="wa3-iconbtn" title="Emoji" onClick={() => setEmojiOpen(v => !v)} disabled={recording}>
              <i className="mdi mdi-emoticon-outline"></i>
            </button>
            {emojiOpen && (
              <div className="wa3-emoji-popover">
                <h6>Emojis rápidos</h6>
                <div className="wa3-emoji-grid">
                  {EMOJIS.map((em, i) => (
                    <button key={i} className="wa3-emoji" onClick={() => { insertEmoji(em); setEmojiOpen(false); }}>{em}</button>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>
        <button className="wa3-send" disabled={!canSend} onClick={handleSend} title="Enviar">
          <i className="mdi mdi-send"></i>
        </button>
      </div>
      {recording && (
        <div className="wa3-upload is-visible">
          <span><i className="mdi mdi-record-circle" style={{ color: 'var(--wa3-danger)', marginRight: 6 }}></i>Grabando… {fmtTime(recordingTime)}</span>
          <button onClick={toggleVoice} title="Detener y enviar"><i className="mdi mdi-stop-circle"></i></button>
        </div>
      )}
      {pendingMedia && !recording && (
        <div className="wa3-upload is-visible">
          <span>
            {pendingMedia.uploading
              ? <><i className="mdi mdi-loading mdi-spin" style={{ marginRight: 6 }}></i>Subiendo {pendingMedia.name}…</>
              : <><i className={`mdi ${pendingMedia.type === 'audio' ? 'mdi-microphone-outline' : 'mdi-paperclip'}`} style={{ marginRight: 6 }}></i>{pendingMedia.name}</>
            }
          </span>
          <button onClick={() => setPendingMedia(null)} title="Quitar adjunto" disabled={pendingMedia.uploading}>
            <i className="mdi mdi-close"></i>
          </button>
        </div>
      )}
      <div className="wa3-composer__hint">
        <span>{canReply ? 'Ventana de 24h abierta — puedes responder libremente.' : 'Ventana cerrada — inicia con una plantilla aprobada.'}</span>
        <span><kbd>Enter</kbd> enviar · <kbd>Shift+Enter</kbd> nueva línea</span>
      </div>
    </div>
  );
}

// ── Thread pane ───────────────────────────────────────────────────────────────

export function WaThreadPane({
  convo, thread, typing, draft, setDraft, showDrawer, canSupervise, canOperate,
  realtime, handlers, toast, templates, agents, roles,
}) {
  const [searchOpen, setSearchOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [matchIdx, setMatchIdx] = useState(0);

  const matchCount = useMemo(() => {
    if (!query) return 0;
    return thread.filter(m => m.body && m.body.toLowerCase().includes(query.toLowerCase())).length;
  }, [thread, query]);

  useEffect(() => { setMatchIdx(0); }, [query]);
  const step = (d) => { if (matchCount > 0) setMatchIdx(i => (i + d + matchCount) % matchCount); };

  if (!convo) {
    return (
      <section className="wa3-thread">
        <div className="wa3-empty">
          <div className="wa3-empty__card">
            <div className="wa3-empty__icon"><i className="mdi mdi-message-text-outline"></i></div>
            <h3>Selecciona una conversación</h3>
            <p>Elige un chat del panel izquierdo para comenzar a atender al paciente.</p>
          </div>
        </div>
      </section>
    );
  }

  return (
    <section className="wa3-thread">
      <header className="wa3-thread__head">
        <button className="wa3-iconbtn wa3-back-btn" title="Volver a conversaciones"
                onClick={handlers.onBackMobile}>
          <i className="mdi mdi-arrow-left"></i>
        </button>
        <div className="wa3-thread__main">
          <div className="wa3-avatar" data-tone={convo.tone}>
            {convo.initials}
            {convo.status && <span className="wa3-avatar__status" data-state={convo.status}></span>}
          </div>
          <div className="wa3-thread__id">
            <h3 className="wa3-thread__name">{convo.name}</h3>
            <div className="wa3-thread__meta"><span>{convo.wa}</span></div>
          </div>
        </div>
        <div className="wa3-thread__actions">
          <button className="wa3-iconbtn" title="Buscar en chat" onClick={() => setSearchOpen(v => !v)}><i className="mdi mdi-magnify"></i></button>
          <span className="wa3-iconbtn--sep"></span>
          {canOperate && !convo.isMine && (
            <button className="wa3-hbtn" onClick={handlers.onAssignSelf}>
              <i className="mdi mdi-hand-back-right-outline"></i><span>Tomar</span>
            </button>
          )}
          <WaTransferMenu convo={convo} agents={agents} roles={roles} onTransfer={handlers.onTransfer} onQueueRole={handlers.onQueueRole} toast={toast} />
          <WaTemplatesMenu templates={templates} onSelectTemplate={handlers.onOpenSendTemplate} />
          <span className="wa3-iconbtn--sep"></span>
          {canOperate && (
            <button className="wa3-hbtn is-success" onClick={handlers.onResolve}>
              <i className="mdi mdi-check-circle-outline"></i><span>Resolver</span>
            </button>
          )}
          <button className={`wa3-iconbtn${showDrawer ? ' is-primary' : ''}`} onClick={handlers.onToggleDrawer}
                  title={showDrawer ? 'Ocultar ficha' : 'Ver ficha del paciente'}><i className="mdi mdi-account-details-outline"></i></button>
          <WaMoreMenu convo={convo} onCopy={handlers.onCopy} onOpenTrail={handlers.onOpenTrail} onFollowup={handlers.onFollowup} canOperate={canOperate} />
        </div>
      </header>

      <WaContextBar convo={convo} />

      <WaChatSearch open={searchOpen} query={query} onQuery={setQuery} count={matchCount} idx={matchIdx}
                    onPrev={() => step(-1)} onNext={() => step(1)} onClose={() => { setSearchOpen(false); setQuery(''); }} />

      {realtime && (
        <div className="wa3-realtime is-visible">
          <span>Hay mensajes nuevos en esta conversación.</span>
          <button onClick={handlers.onRealtimeReload}>Actualizar</button>
        </div>
      )}

      <WaThread items={thread} typing={typing} query={searchOpen ? query : ''} currentIdx={matchIdx} />

      {canOperate && (
        <WaComposer value={draft} onChange={setDraft}
                    onSend={() => handlers.onSend(draft)}
                    onSendMedia={handlers.onSendMedia}
                    convo={convo} toast={toast} />
      )}
    </section>
  );
}
