/* eslint-disable */
/* @jsxRuntime classic */
/* @jsx React.createElement */
/* @jsxFrag React.Fragment */
const { useState, useEffect, useRef, useCallback } = React;

const CFG = window.HOT_OPPS_CONFIG || { apiUrl: '/v2/whatsapp/api/hot-opportunities', chatUrl: '/v2/whatsapp/chat', pollIntervalMs: 30000 };

/* ─────────────────────────── Field mapping (API → UI) ─────────────────────────── */

function mapConversation(c) {
  /* source / intent */
  const src = (c.attribution_source_category || '').toLowerCase();
  const sourceLabel =
    src === 'paid'    ? 'Ads'      :
    src === 'organic' ? 'Orgánico' :
    src === 'return'  ? 'Retorno'  :
    src === 'campaign'? 'Campaña'  :
    (c.attribution_source_category || 'Orgánico');

  const intentRaw = (c.attribution_initial_intent || '').toLowerCase();
  const intentLabel =
    intentRaw.includes('agenda') || intentRaw.includes('agendar') ? 'agendar'    :
    intentRaw.includes('reagend')                                  ? 'reagendar'  :
    intentRaw.includes('cancel')                                   ? 'cancelar'   : 'información';

  const topic = c.handoff_topic || 'captacion_agendar';

  /* wait time */
  const waitMin = typeof c.queue_age_minutes === 'number' ? c.queue_age_minutes : 0;

  /* meta window */
  let metaState = 'open';
  let metaMinLeft = null;
  const mw = c.messaging_window_state || '';
  if (mw === 'needs_template') { metaState = 'warn'; metaMinLeft = 120; }
  if (mw === 'closed') { metaState = 'critical'; metaMinLeft = 0; }

  /* priority */
  const score = c.priority_score || 0;

  /* reasons (from priority_reasons array) */
  const reasons = buildReasons(c, waitMin);

  return {
    id: c.id,
    name: c.display_name || c.patient_full_name || c.wa_number || `Conv #${c.id}`,
    hc: c.patient_hc_number || null,
    source: sourceLabel,
    intent: intentLabel,
    topic,
    waitMin,
    agentId: c.assigned_user_id || null,
    metaState,
    metaMinLeft,
    score,
    requeued: 0,
    reasons,
    _raw: c,
  };
}

function buildReasons(c, waitMin) {
  const reasons = [];
  const assigned = !!c.assigned_user_id;
  const mw = c.messaging_window_state || '';

  if (!assigned && waitMin > 20) reasons.push(['alarm', `Sin asignar hace ${waitMin} min`, 'crit']);
  else if (!assigned && waitMin > 0) reasons.push(['alarm', `Sin asignar hace ${waitMin} min`, 'risk']);

  if (!assigned) reasons.push(['account-off', 'Sin asignar', assigned ? 'info' : 'crit']);

  if (mw === 'needs_template') reasons.push(['timer-sand', 'Ventana Meta por cerrar', 'risk']);
  if (mw === 'closed') reasons.push(['timer-alert-outline', 'Ventana Meta cerrada', 'crit']);

  if (c.attribution_initial_intent && (c.attribution_initial_intent.includes('agenda') || c.attribution_initial_intent.includes('agendar'))) {
    reasons.push(['calendar-check', 'Intención de agendar', 'info']);
  }
  if (c.patient_hc_number) reasons.push(['card-account-details', 'Paciente identificado · HC', 'info']);

  if ((c.priority_reasons || []).length > 0 && reasons.length === 0) {
    c.priority_reasons.forEach(r => reasons.push(['information-outline', r, 'info']));
  }

  return reasons.slice(0, 4);
}

function mapReminder(r) {
  return {
    id: String(r.id),
    conversationId: r.conversation_id,
    name: r.patient_name || `HC ${r.hc_number}`,
    hc: r.hc_number || null,
    apptDate: r.appointment_at ? fmtApptDate(r.appointment_at) : '—',
    apptMinutes: r.appointment_minutes_from_now ?? 9999,
    apptDoctor: r.doctor_name || '',
    apptSede: r.sede || '',
    failureReason: r.failure_reason || 'unknown',
    failedAt: r.failed_at ? r.failed_at.slice(11, 16) : '—',
    retries: r.retry_count || 0,
    windowState: r.window_state || 'open',
  };
}

function fmtApptDate(isoStr) {
  try {
    const d = new Date(isoStr);
    const today = new Date();
    const tomorrow = new Date(today); tomorrow.setDate(today.getDate() + 1);
    const sameDay = d => d.getDate() === today.getDate() && d.getMonth() === today.getMonth();
    const sameYear = d => d.getFullYear() === today.getFullYear();
    const prefix = sameDay(d) ? 'Hoy' : (d.getDate() === tomorrow.getDate() && d.getMonth() === tomorrow.getMonth()) ? 'Mañana' : d.toLocaleDateString('es-EC', { day: '2-digit', month: 'short' });
    const time = d.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit', hour12: false });
    return `${prefix} ${time}`;
  } catch { return isoStr; }
}

function mapAgent(a) {
  const STATUS_MAP = { available: 'available', busy: 'busy', away: 'away', offline: 'away' };
  return {
    id: a.id,
    name: a.name,
    initials: a.initials || makeInitials(a.name),
    color: a.color || '#5156be',
    status: STATUS_MAP[a.presence_status] || 'available',
    convs: a.assigned_open_count || 0,
    unread: a.unread_open_count || 0,
    resp: '—',
  };
}

function makeInitials(name) {
  const parts = (name || '').trim().split(/\s+/);
  if (parts.length >= 2) return (parts[0][0] + parts[1][0]).toUpperCase();
  return (name || '??').slice(0, 2).toUpperCase();
}

/* ─────────────────────────── Helpers ─────────────────────────── */
function priorityOf(c) {
  if (!c.agentId && c.waitMin > 20) return 'crit';
  if (c.metaState === 'critical') return 'crit';
  if (c.requeued >= 2) return 'crit';
  if (!c.agentId && c.waitMin >= 5) return 'risk';
  if (c.metaState === 'warn') return 'risk';
  return 'norm';
}
const loadPct = (n, max = 15) => Math.min(100, Math.round(n / max * 100));
const loadClass = p => p >= 75 ? 'load-high' : p >= 45 ? 'load-mid' : 'load-ok';
function scoreColor(s) { return s >= 170 ? 'var(--danger)' : s >= 90 ? 'var(--warning)' : 'var(--success)'; }
function fmtMeta(c) {
  if (c.metaMinLeft == null) return null;
  const h = Math.floor(c.metaMinLeft / 60), m = c.metaMinLeft % 60;
  return h > 0 ? `${h}h ${m}min` : `${m} min`;
}
const isRemSoon = r => typeof r.apptMinutes === 'number' && r.apptMinutes <= 120 && r.apptMinutes >= 0;
function fmtApptIn(min) {
  if (min <= 0) return ['pasada', true];
  if (min <= 60) return [`${min}min`, true];
  const h = Math.floor(min / 60), m = min % 60;
  return [`${h}h${m ? ` ${m}min` : ''}`, false];
}

const TOPIC = {
  captacion_agendar:          ['captación', 'tp-captacion'],
  agenda_sin_disponibilidad:  ['agenda · sin disp.', 'tp-agenda'],
  faq_escalada:               ['faq · escalada', 'tp-faq'],
  operacion_reagenda:         ['operación · reagenda', 'tp-operacion'],
};
const SOURCE = {
  'Ads':      ['src-ads', 'bullhorn-variant'],
  'Orgánico': ['src-organico', 'leaf'],
  'Retorno':  ['src-retorno', 'backup-restore'],
  'Campaña':  ['src-campana', 'bullhorn'],
};
const INTENT = {
  'agendar':     ['int-agendar', 'calendar-plus'],
  'reagendar':   ['int-reagendar', 'calendar-sync'],
  'cancelar':    ['int-cancelar', 'calendar-remove'],
  'información': ['int-info', 'information-outline'],
};
const FAILURE = {
  location_header_missing_coordinates: ['Coord. faltantes',  'fl-location', 'map-marker-off-outline', 'El template espera coordenadas de ubicación pero no se enviaron. Requiere corregir el payload del recordatorio.'],
  template_header_location_mismatch:   ['Header incorrecto', 'fl-template', 'file-document-alert-outline', 'El header del template está configurado como LOCATION pero se envió un tipo distinto (posiblemente UNKNOWN). PR #401 corregido.'],
  whatsapp_messages_table_missing:     ['Tabla ausente',     'fl-infra',    'database-alert-outline', 'El servicio de recordatorios no encontró la tabla whatsapp_messages al deduplicar. Error de infraestructura.'],
};
const failInfo = r => FAILURE[r] || [r, 'fl-unknown', 'help-circle-outline', r];
const Icon = ({ n, ...p }) => <i className={`mdi mdi-${n}`} {...p} />;

/* ─────────────────────────── Assign dropdown ─────────────────────────── */
function AgentDropdown({ agents, onSelect, onClose }) {
  const ref = useRef();
  useEffect(() => {
    const h = e => { if (ref.current && !ref.current.contains(e.target)) onClose(); };
    document.addEventListener('mousedown', h);
    return () => document.removeEventListener('mousedown', h);
  }, []);
  const sorted = [...agents].sort((a, b) => a.convs - b.convs);
  return (
    <div className="ho-dd" ref={ref}>
      <div className="ho-dd-lbl"><span>Asignar a agente</span><span>menor carga primero</span></div>
      {sorted.map((a, i) => {
        const p = loadPct(a.convs);
        return (
          <div key={a.id} className="ho-dd-item" onClick={() => onSelect(a)}>
            <div className={`ho-av st-${a.status}`} style={{ background: a.color, width: 30, height: 30 }}>{a.initials}</div>
            <div style={{ minWidth: 0 }}>
              <div className="ho-dd-name">{a.name} {i === 0 && <span className="ho-dd-best">óptimo</span>}</div>
              <div className="ho-dd-meta">{a.convs} conv activas</div>
            </div>
            <div className="ho-dd-load">
              <div className="ho-dd-loadbar"><div className={`ho-dd-loadfill ${loadClass(p)}`} style={{ width: `${p}%` }} /></div>
              <span className="ho-dd-loadnum">{a.convs}</span>
            </div>
          </div>
        );
      })}
    </div>
  );
}

/* ─────────────────────────── Opportunity card ─────────────────────────── */
function OppCard({ conv, agents, prio, selected, onSelect, onAssign, onOpen }) {
  const [drop, setDrop] = useState(false);
  const agent = agents.find(a => a.id === conv.agentId);
  const waitCls = prio === 'crit' ? 'w-crit' : prio === 'risk' ? 'w-risk' : 'w-ok';
  const [srcCls, srcIc] = SOURCE[conv.source] || ['int-info', 'help'];
  const [intCls, intIc] = INTENT[conv.intent] || ['int-info', 'help'];
  const [topLbl, topCls] = TOPIC[conv.topic] || [conv.topic, 'tp-faq'];
  const metaFmt = fmtMeta(conv);

  return (
    <div className={`ho-card ${prio} ${selected ? 'sel' : ''}`} onClick={() => onSelect(conv.id)}>
      <div className="ho-card-body">
        {/* patient */}
        <div style={{ minWidth: 0 }}>
          <div className="ho-pt-name">{conv.name}</div>
          {conv.hc
            ? <span className="ho-pt-hc"><Icon n="card-account-details-outline" />HC {conv.hc}</span>
            : <span className="ho-pt-nohc"><Icon n="card-account-details-outline" />Sin HC</span>}
          <div className="ho-score" title={`Prioridad ${conv.score}/450`}>
            <div className="ho-score-track"><div className="ho-score-fill" style={{ width: `${Math.min(100, Math.round(conv.score / 4.5))}%`, background: scoreColor(conv.score) }} /></div>
            <span className="ho-score-lbl" style={{ color: scoreColor(conv.score) }}>{conv.score}</span>
          </div>
        </div>
        {/* source + intent */}
        <div className="ho-badges">
          <span className={`ho-badge ${srcCls}`}><Icon n={srcIc} />{conv.source}</span>
          <span className={`ho-badge ${intCls}`}><Icon n={intIc} />{conv.intent}</span>
        </div>
        {/* wait */}
        <div className="ho-wait">
          <span className={`ho-wait-badge ${waitCls}`}>{conv.waitMin}<small>min</small></span>
          <div className="ho-wait-sub">en cola</div>
        </div>
        {/* topic */}
        <div style={{ minWidth: 0 }}>
          <span className={`ho-topic ${topCls}`}>{topLbl}</span>
          {conv.requeued >= 1 && <div className="ho-requeue"><Icon n="backup-restore" />reencolado {conv.requeued}×</div>}
        </div>
        {/* meta window */}
        <div className="ho-meta">
          {conv.metaState === 'open' && <span className="ho-meta-open"><Icon n="check-circle" />Abierta</span>}
          {conv.metaState === 'warn' && <span className="ho-meta-warn m-warn"><Icon n="timer-sand" />{metaFmt}</span>}
          {conv.metaState === 'critical' && <span className="ho-meta-warn m-crit"><Icon n="timer-alert-outline" />{metaFmt || 'Cerrada'}</span>}
          <div className="ho-meta-lbl">ventana Meta</div>
        </div>
        {/* agent */}
        <div className="ho-agent">
          {agent ? (
            <>
              <div className={`ho-av st-${agent.status}`} style={{ background: agent.color }}>{agent.initials}</div>
              <div style={{ minWidth: 0 }}>
                <div className="ho-agent-name">{agent.name}</div>
                <div className="ho-agent-meta">{agent.convs} conv asignadas</div>
              </div>
            </>
          ) : (
            <>
              <div className="ho-av-empty"><Icon n="account-plus-outline" /></div>
              <span className="ho-agent-none">Sin asignar</span>
            </>
          )}
        </div>
        {/* actions */}
        <div className="ho-actions" onClick={e => e.stopPropagation()}>
          <div className="ho-dd-wrap">
            <button className={agent ? 'ho-btn ho-btn-sec' : 'ho-btn ho-btn-pri'} onClick={() => setDrop(v => !v)}>
              <Icon n={agent ? 'account-switch' : 'account-arrow-right'} />{agent ? 'Reasignar' : 'Asignar'}
            </button>
            {drop && <AgentDropdown agents={agents} onSelect={a => { setDrop(false); onAssign(conv.id, a); }} onClose={() => setDrop(false)} />}
          </div>
          <button className="ho-btn ho-btn-ic" title="Abrir chat" onClick={() => onOpen(conv)}><Icon n="message-text-outline" /></button>
        </div>
      </div>
      <div className="ho-reasons">
        <span className="ho-reasons-lbl"><Icon n="information-outline" />Por qué</span>
        {conv.reasons.map(([ic, txt, sev], i) => (
          <span key={i} className={`ho-reason rs-${sev}`}><Icon n={ic} />{txt}</span>
        ))}
      </div>
    </div>
  );
}

/* ─────────────────────────── Reminder card ─────────────────────────── */
function RemCard({ rem, selected, onSelect, onOpen, onRecontact }) {
  const [desc, setDesc] = useState(false);
  const soon = isRemSoon(rem);
  const [failLbl, failCls, failIc, failDesc] = failInfo(rem.failureReason);
  const [inLbl, inSoon] = fmtApptIn(rem.apptMinutes);
  return (
    <div className={`ho-rcard ${soon ? 'soon' : ''} ${selected === rem.id ? 'sel' : ''}`} onClick={() => onSelect(rem.id)}>
      <div className="ho-rcard-body">
        {/* patient */}
        <div style={{ minWidth: 0 }}>
          <div className="ho-pt-name">{rem.name}</div>
          {rem.hc
            ? <span className="ho-pt-hc"><Icon n="card-account-details-outline" />HC {rem.hc}</span>
            : <span className="ho-pt-nohc"><Icon n="card-account-details-outline" />Sin HC</span>}
          {soon && <div className="ho-soon-flag"><Icon n="alert-circle" />Cita muy pronto</div>}
        </div>
        {/* failure */}
        <div style={{ minWidth: 0 }}>
          <span className={`ho-fail ${failCls}`}><Icon n={failIc} />{failLbl}</span>
          <div className="ho-fail-toggle" onClick={e => { e.stopPropagation(); setDesc(v => !v); }}>
            <Icon n={desc ? 'chevron-up' : 'chevron-down'} />{desc ? 'Ocultar detalle' : 'Ver detalle'}
          </div>
          {desc && <div className="ho-fail-desc">{failDesc}</div>}
          {rem.retries > 0 && <div className="ho-fail-retry"><Icon n="restart" />Reintentado {rem.retries}×</div>}
        </div>
        {/* appointment */}
        <div style={{ minWidth: 0 }}>
          <span className={`ho-appt-date ${soon ? 'soon' : ''}`}><Icon n="calendar-clock" />{rem.apptDate}</span>
          <div className="ho-appt-in">en <b className={inSoon ? 'soon' : ''}>{inLbl}</b></div>
          {(rem.apptDoctor || rem.apptSede) && <div className="ho-appt-where">{[rem.apptDoctor, rem.apptSede].filter(Boolean).join(' · ')}</div>}
        </div>
        {/* window */}
        <div>
          {rem.windowState === 'open' && <span className="ho-win w-open"><Icon n="check-circle" />Abierta</span>}
          {rem.windowState === 'needs_template' && <span className="ho-win w-tmpl"><Icon n="file-document-outline" />Requiere template</span>}
          {rem.windowState === 'closed' && <span className="ho-win w-closed"><Icon n="close-circle" />Cerrada</span>}
          <div className="ho-win-lbl">ventana WhatsApp</div>
        </div>
        {/* actions */}
        <div className="ho-actions" onClick={e => e.stopPropagation()}>
          <button className="ho-btn ho-btn-rem" onClick={() => onRecontact(rem)}><Icon n="phone-outline" />Recontactar</button>
          {rem.conversationId && <button className="ho-btn ho-btn-ic" title="Abrir chat" onClick={() => onOpen({ name: rem.name, id: rem.conversationId })}><Icon n="message-text-outline" /></button>}
        </div>
      </div>
    </div>
  );
}

/* ─────────────────────────── Failure summary ─────────────────────────── */
function FailureSummary({ reminders, active, onFilter }) {
  const counts = {};
  reminders.forEach(r => { counts[r.failureReason] = (counts[r.failureReason] || 0) + 1; });
  const items = Object.entries(counts).sort((a, b) => b[1] - a[1]);
  const soon = reminders.filter(isRemSoon).length;
  return (
    <div className="ho-failsum">
      <span className="ho-failsum-title"><Icon n="chart-donut" />Fallos por causa</span>
      <div className="ho-failsum-items">
        {items.map(([reason, count]) => {
          const [lbl, cls, ic] = failInfo(reason);
          return (
            <span key={reason} className={`ho-fail ${cls} ho-failsum-chip ${active === reason ? 'active' : ''}`}
              onClick={() => onFilter(active === reason ? null : reason)}>
              <Icon n={ic} /><b>{count}</b> {lbl}
            </span>
          );
        })}
        <span className="ho-failsum-total">Total <b style={{ color: 'var(--fg-1)' }}>{reminders.length}</b> fallidos · <b>{soon}</b> con cita &lt; 2h</span>
      </div>
    </div>
  );
}

/* ─────────────────────────── Section ─────────────────────────── */
const SEC_META = {
  crit: ['sev-crit', 'fire', 'CRÍTICAS', 'acción inmediata'],
  risk: ['sev-risk', 'alert-outline', 'EN RIESGO', 'responder pronto'],
  norm: ['sev-norm', 'check-circle-outline', 'BAJO CONTROL', 'asignadas y activas'],
};
function Section({ prio, convs, ...rest }) {
  if (!convs.length) return null;
  const [cls, ic, lbl, sub] = SEC_META[prio];
  return (
    <div className={cls}>
      <div className="ho-sec-hdr">
        <span className="ho-sec-icon"><Icon n={ic} /></span>
        <h3>{lbl} <span className="ho-sec-sub">· {sub}</span></h3>
        <span className="ho-sec-count">{convs.length}</span>
        <span className="ho-sec-line" />
      </div>
      <div className="ho-cards">
        {convs.map(c => <OppCard key={c.id} conv={c} prio={prio} {...rest} />)}
      </div>
    </div>
  );
}

function SecHeader({ ic, label, sub, count }) {
  return (
    <div className="ho-sec-hdr">
      <span className="ho-sec-icon"><Icon n={ic} /></span>
      <h3>{label} <span className="ho-sec-sub">· {sub}</span></h3>
      <span className="ho-sec-count">{count}</span>
      <span className="ho-sec-line" />
    </div>
  );
}

/* ─────────────────────────── Agent panel ─────────────────────────── */
function AgentPanel({ agents }) {
  const totalConv = agents.reduce((s, a) => s + a.convs, 0);
  const totalUnread = agents.reduce((s, a) => s + a.unread, 0);
  const avail = agents.filter(a => a.status === 'available').length;
  const STATUS_LBL = { available: 'Disponible', busy: 'Ocupado', away: 'Ausente' };
  return (
    <aside className="ho-ap">
      <div className="ho-ap-hd">
        <span className="ho-ap-hd-eye"><Icon n="account-group-outline" />Equipo en turno</span>
        <h4>{agents.length} agentes <small>· {avail} disponibles</small></h4>
      </div>
      <div className="ho-ap-list">
        {[...agents].sort((a, b) => a.convs - b.convs).map(a => {
          const p = loadPct(a.convs);
          return (
            <div className="ho-ac" key={a.id}>
              <div className={`ho-ac-av st-${a.status}`} style={{ background: a.color }}>{a.initials}</div>
              <div className="ho-ac-info">
                <div className="ho-ac-name">{a.name}</div>
                <span className={`ho-ac-status s-${a.status}`}>{STATUS_LBL[a.status]}</span>
                <div className="ho-ac-loadbar"><div className={`ho-ac-loadfill ${loadClass(p)}`} style={{ width: `${p}%` }} /></div>
              </div>
              <div className="ho-ac-stats">
                <div className="ho-ac-conv">{a.convs}<small> conv</small></div>
                {a.unread > 0 && <div className="ho-ac-unread">{a.unread} sin leer</div>}
              </div>
            </div>
          );
        })}
      </div>
      <div className="ho-ap-foot">
        <div className="ho-ap-foot-row"><span>Conversaciones asignadas</span><b>{totalConv}</b></div>
        <div className="ho-ap-foot-row"><span>Sin leer en el equipo</span><b>{totalUnread}</b></div>
      </div>
    </aside>
  );
}

/* ─────────────────────────── Toasts ─────────────────────────── */
function Toasts({ toasts }) {
  return (
    <div className="ho-toasts">
      {toasts.map(t => (
        <div key={t.id} className={`ho-toast ${t.out ? 'out' : ''}`}>
          <span className={`ho-toast-ic t-${t.kind}`}><Icon n={t.icon} /></span>
          <span>{t.msg}</span>
        </div>
      ))}
    </div>
  );
}

function FSelect({ value, onChange, allLabel, options }) {
  return (
    <select className={`ho-select ${value !== 'all' ? 'active' : ''}`} value={value} onChange={e => onChange(e.target.value)}>
      <option value="all">{allLabel}</option>
      {options.map(([v, l]) => <option key={v} value={v}>{l}</option>)}
    </select>
  );
}

/* ─────────────────────────── Loading / Error states ─────────────────────────── */
function LoadingState() {
  return (
    <div className="ho-empty" style={{ margin: 'auto' }}>
      <div className="ho-empty-ic" style={{ background: 'var(--primary-fade)', color: 'var(--primary)' }}>
        <Icon n="loading" style={{ animation: 'ho-spin .75s linear infinite' }} />
      </div>
      <h4>Cargando bandeja operacional</h4>
      <p>Conectando con el servidor…</p>
    </div>
  );
}

function ErrorState({ msg, onRetry }) {
  return (
    <div className="ho-empty" style={{ margin: 'auto' }}>
      <div className="ho-empty-ic" style={{ background: '#fde2e7', color: 'var(--danger)' }}>
        <Icon n="wifi-off" />
      </div>
      <h4>Error al cargar los datos</h4>
      <p>{msg}</p>
      <button className="ho-btn ho-btn-pri" style={{ margin: '14px auto 0', display: 'inline-flex' }} onClick={onRetry}>
        <Icon n="refresh" />Reintentar
      </button>
    </div>
  );
}

/* ─────────────────────────── App ─────────────────────────── */
function App() {
  const [convs, setConvs] = useState([]);
  const [agents, setAgents] = useState([]);
  const [reminders, setReminders] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);
  const [tab, setTab] = useState('oportunidades');
  const [failFilter, setFailFilter] = useState(null);
  const [selected, setSelected] = useState(null);
  const [spin, setSpin] = useState(false);
  const [toasts, setToasts] = useState([]);
  const [ts, setTs] = useState(null);
  const [now, setNow] = useState(() => Date.now());

  const [fPrio, setFPrio] = useState('all');
  const [fTopic, setFTopic] = useState('all');
  const [fSource, setFSource] = useState('all');
  const [fIntent, setFIntent] = useState('all');
  const [fAgent, setFAgent] = useState('all');

  useEffect(() => { const t = setInterval(() => setNow(Date.now()), 1000); return () => clearInterval(t); }, []);

  const addToast = useCallback((msg, icon = 'check-circle', kind = 'ok') => {
    const id = Date.now() + Math.random();
    setToasts(p => [...p, { id, msg, icon, kind }]);
    setTimeout(() => {
      setToasts(p => p.map(t => t.id === id ? { ...t, out: true } : t));
      setTimeout(() => setToasts(p => p.filter(t => t.id !== id)), 240);
    }, 2800);
  }, []);

  const fetchData = useCallback(async (showSpin = false) => {
    if (showSpin) setSpin(true);
    try {
      const resp = await fetch(CFG.apiUrl, { headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const json = await resp.json();
      if (!json.ok) throw new Error(json.error || 'Error del servidor');

      const data = json.data || {};
      setConvs((data.conversations || []).map(mapConversation));
      setAgents((data.agents || []).map(mapAgent));
      setReminders((data.reminders || []).map(mapReminder));
      setTs(new Date());
      setError(null);
      setLoading(false);
    } catch (e) {
      setError(e.message);
      setLoading(false);
    } finally {
      setSpin(false);
    }
  }, []);

  /* Realtime hooks for Codex PR #402 events */
  useEffect(() => {
    const handleWsEvent = (e) => {
      const ev = e.detail || {};
      if (['handoff.requeued', 'handoff.escalated', 'handoff.auto_assigned'].includes(ev.event)) {
        fetchData(false);
        if (ev.event === 'handoff.auto_assigned' && ev.assigned_to) {
          addToast(`Auto-asignado a ${ev.assigned_to.name}`, 'account-arrow-right', 'user');
        }
      }
    };
    window.addEventListener('whatsapp:handoff', handleWsEvent);
    return () => window.removeEventListener('whatsapp:handoff', handleWsEvent);
  }, [fetchData, addToast]);

  useEffect(() => { fetchData(false); }, [fetchData]);

  useEffect(() => {
    const interval = setInterval(() => fetchData(false), CFG.pollIntervalMs);
    return () => clearInterval(interval);
  }, [fetchData]);

  const refresh = useCallback(() => {
    fetchData(true);
    addToast('Actualizando bandeja…', 'refresh', 'info');
  }, [fetchData, addToast]);

  function assign(convId, agent) {
    const conv = convs.find(c => c.id === convId);
    const prev = conv && conv.agentId;
    /* Optimistic update */
    setConvs(p => p.map(c => c.id === convId ? { ...c, agentId: agent.id } : c));
    setAgents(p => p.map(a => {
      if (a.id === agent.id) return { ...a, convs: a.convs + 1 };
      if (a.id === prev) return { ...a, convs: Math.max(0, a.convs - 1) };
      return a;
    }));
    addToast(`${conv?.name || 'Conv'} → ${agent.name}`, 'account-arrow-right', 'user');
  }

  const openChat = conv => {
    const url = conv.id ? `${CFG.chatUrl}?conversation_id=${conv.id}` : CFG.chatUrl;
    window.location.href = url;
  };
  const recontact = rem => {
    if (rem.conversationId) {
      window.location.href = `${CFG.chatUrl}?conversation_id=${rem.conversationId}`;
    } else {
      addToast(`Recontacto manual iniciado para ${rem.name}`, 'phone-outline', 'user');
    }
  };

  const filtered = convs.filter(c => {
    if (fTopic !== 'all' && c.topic !== fTopic) return false;
    if (fSource !== 'all' && c.source !== fSource) return false;
    if (fIntent !== 'all' && c.intent !== fIntent) return false;
    if (fPrio !== 'all' && priorityOf(c) !== fPrio) return false;
    if (fAgent !== 'all') {
      if (fAgent === 'none' && c.agentId !== null) return false;
      if (fAgent !== 'none' && String(c.agentId) !== fAgent) return false;
    }
    return true;
  });

  const byScore = (a, b) => b.score - a.score;
  const crit = filtered.filter(c => priorityOf(c) === 'crit').sort(byScore);
  const risk = filtered.filter(c => priorityOf(c) === 'risk').sort(byScore);
  const norm = filtered.filter(c => priorityOf(c) === 'norm').sort(byScore);
  const unassigned = convs.filter(c => !c.agentId).length;

  const filteredRem = failFilter ? reminders.filter(r => r.failureReason === failFilter) : reminders;
  const byAppt = (a, b) => a.apptMinutes - b.apptMinutes;
  const soonRem = filteredRem.filter(isRemSoon).sort(byAppt);
  const laterRem = filteredRem.filter(r => !isRemSoon(r)).sort(byAppt);
  const urgentRemCount = reminders.filter(isRemSoon).length;

  const hasFilters = [fPrio, fTopic, fSource, fIntent, fAgent].some(f => f !== 'all');
  const clearFilters = () => { setFPrio('all'); setFTopic('all'); setFSource('all'); setFIntent('all'); setFAgent('all'); };

  const secsAgo = ts ? Math.floor((now - ts.getTime()) / 1000) : 0;
  const fmtTs = ts ? ts.toLocaleTimeString('es-EC', { hour: '2-digit', minute: '2-digit' }) : '—';
  const agoLabel = !ts ? '…' : secsAgo < 5 ? 'recién' : secsAgo < 60 ? `hace ${secsAgo}s` : `hace ${Math.floor(secsAgo / 60)} min`;

  const sectionProps = { agents, selected, onSelect: setSelected, onAssign: assign, onOpen: openChat };

  return (
    <>
      {/* ── Header ── */}
      <header className="ho-hd">
        <div className="ho-brand">
          <div className="ho-brand-mark"><Icon n="lightning-bolt" /></div>
          <div className="ho-brand-text">
            <div className="ho-brand-word">MedForge</div>
            <div className="ho-brand-sub">by Consulmed</div>
          </div>
        </div>
        <div className="ho-hd-divider" />
        <div className="ho-hd-title">
          <h1>Bandeja operacional</h1>
          <span className="ho-hd-crumb"><Icon n="whatsapp" />WhatsApp · Supervisión CIVE</span>
        </div>
        <div className="ho-live"><span className="ho-pulse" />En vivo</div>

        {!loading && !error && (
          <div className="ho-hd-pills">
            <span className="ho-hd-pill crit"><Icon n="fire" /><b>{crit.length}</b> críticas</span>
            <span className="ho-hd-pill risk"><Icon n="alert-outline" /><b>{risk.length}</b> en riesgo</span>
            {urgentRemCount > 0 && <span className="ho-hd-pill reminder" style={{ background: 'rgba(213,150,35,.26)', color: '#f3cd7e' }}><Icon n="calendar-alert" /><b>{urgentRemCount}</b> recordatorios urgentes</span>}
            <span className="ho-hd-pill unassi"><Icon n="account-off-outline" /><b>{unassigned}</b> sin asignar</span>
          </div>
        )}

        <div className="ho-hd-meta">
          <span className="ho-hd-ts">{ts ? `Actualizado ${fmtTs} · ${agoLabel}` : 'Cargando…'}</span>
          <button className={`ho-refresh ${spin ? 'spin' : ''}`} onClick={refresh} title="Actualizar"><Icon n="refresh" /></button>
        </div>
      </header>

      {/* ── Tabs ── */}
      <div className="ho-tabs">
        <button className={`ho-tab ${tab === 'oportunidades' ? 'active' : ''}`} onClick={() => setTab('oportunidades')}>
          <Icon n="fire" />Oportunidades calientes
          <span className="ho-tab-count">{convs.length}</span>
        </button>
        <button className={`ho-tab ${tab === 'recordatorios' ? 'active' : ''}`} onClick={() => setTab('recordatorios')}>
          <Icon n="calendar-alert" />Recordatorios fallidos
          <span className="ho-tab-count">{reminders.length}</span>
          {urgentRemCount > 0 && <span className="ho-tab-urgent"><Icon n="clock-alert-outline" />{urgentRemCount} urgentes</span>}
        </button>
      </div>

      {/* ── Filter bar ── */}
      {tab === 'oportunidades' && !loading && !error && (
        <div className="ho-fb">
          <span className="ho-fb-label"><Icon n="filter-variant" />Filtrar</span>
          <FSelect value={fPrio} onChange={setFPrio} allLabel="Prioridad" options={[['crit', 'Crítico'], ['risk', 'En riesgo'], ['norm', 'Bajo control']]} />
          <FSelect value={fTopic} onChange={setFTopic} allLabel="Topic" options={[['captacion_agendar', 'Captación · agendar'], ['agenda_sin_disponibilidad', 'Agenda sin disponibilidad'], ['faq_escalada', 'FAQ escalada'], ['operacion_reagenda', 'Operación · reagenda']]} />
          <FSelect value={fSource} onChange={setFSource} allLabel="Origen" options={[['Ads', 'Ads'], ['Orgánico', 'Orgánico'], ['Retorno', 'Retorno'], ['Campaña', 'Campaña']]} />
          <FSelect value={fIntent} onChange={setFIntent} allLabel="Intención" options={[['agendar', 'Agendar'], ['reagendar', 'Reagendar'], ['cancelar', 'Cancelar'], ['información', 'Información']]} />
          <FSelect value={fAgent} onChange={setFAgent} allLabel="Agente" options={[['none', 'Sin asignar'], ...agents.map(a => [String(a.id), a.name])]} />
          {hasFilters && <><div className="ho-fb-sep" /><button className="ho-fb-clear" onClick={clearFilters}><Icon n="close" />Limpiar</button></>}
          <span className="ho-fb-count"><b>{filtered.length}</b> de {convs.length} conversaciones</span>
        </div>
      )}

      {/* ── Main ── */}
      <div className="ho-main">
        <main className="ho-queue">
          {loading ? <LoadingState /> :
           error ? <ErrorState msg={error} onRetry={refresh} /> :
           tab === 'oportunidades' ? (
            filtered.length === 0 ? (
              <div className="ho-empty">
                <div className="ho-empty-ic"><Icon n="check-all" /></div>
                <h4>Sin oportunidades{hasFilters ? ' con estos filtros' : ''}</h4>
                <p>{hasFilters ? 'Ajusta los filtros o espera nuevas conversaciones entrantes.' : 'No hay conversaciones activas en la bandeja.'}</p>
              </div>
            ) : (
              <>
                <Section prio="crit" convs={crit} {...sectionProps} />
                <Section prio="risk" convs={risk} {...sectionProps} />
                <Section prio="norm" convs={norm} {...sectionProps} />
              </>
            )
          ) : (
            <>
              {reminders.length === 0 ? (
                <div className="ho-empty">
                  <div className="ho-empty-ic"><Icon n="check-all" /></div>
                  <h4>Sin recordatorios fallidos</h4>
                  <p>El servicio de recordatorios no reporta entregas fallidas.</p>
                </div>
              ) : (
                <>
                  <FailureSummary reminders={reminders} active={failFilter} onFilter={setFailFilter} />
                  {filteredRem.length === 0 ? (
                    <div className="ho-empty">
                      <div className="ho-empty-ic"><Icon n="check-all" /></div>
                      <h4>Sin resultados con este filtro</h4>
                      <p>Selecciona otro tipo de fallo para ver los recordatorios correspondientes.</p>
                    </div>
                  ) : (
                    <>
                      {soonRem.length > 0 && (
                        <div className="sev-crit">
                          <SecHeader ic="clock-alert-outline" label="URGENTE" sub="cita en menos de 2 horas" count={soonRem.length} />
                          <div className="ho-cards">{soonRem.map(r => <RemCard key={r.id} rem={r} selected={selected} onSelect={setSelected} onOpen={openChat} onRecontact={recontact} />)}</div>
                        </div>
                      )}
                      {laterRem.length > 0 && (
                        <div className="sev-risk">
                          <SecHeader ic="calendar-alert" label="FALLIDOS" sub="cita posterior" count={laterRem.length} />
                          <div className="ho-cards">{laterRem.map(r => <RemCard key={r.id} rem={r} selected={selected} onSelect={setSelected} onOpen={openChat} onRecontact={recontact} />)}</div>
                        </div>
                      )}
                    </>
                  )}
                </>
              )}
            </>
          )}
        </main>
        {!loading && !error && <AgentPanel agents={agents} />}
      </div>

      <Toasts toasts={toasts} />
    </>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(<App />);
