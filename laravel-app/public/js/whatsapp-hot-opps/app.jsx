/* eslint-disable */
/* @jsxRuntime classic */
/* @jsx React.createElement */
/* @jsxFrag React.Fragment */
const { useState, useEffect, useRef, useCallback } = React;

const CFG = window.HOT_OPPS_CONFIG || {
  apiUrl:  '/v2/whatsapp/api/operational-queues',
  chatUrl: '/v2/whatsapp/chat',
};

/* ─── Icons ─────────────────────────────────────────────────────────────── */
function Icon({ n, cls = '' }) {
  return React.createElement('span', { className: `mdi mdi-${n} ${cls}`.trim() });
}

/* ─── Helpers ────────────────────────────────────────────────────────────── */
function fmt(v, fallback = '—') { return v == null ? fallback : v; }

const PRIORITY_CLS = { high: 'pri-high', medium: 'pri-med', normal: 'pri-normal', low: 'pri-low' };
const RISK_CLS     = { high: 'risk-high', medium: 'risk-med', low: 'risk-low', closed: 'risk-closed' };

const BUCKET_LABEL = {
  hot_open:          '🔴 HOT — activo',
  hot_needs_template:'🟠 HOT — sin respuesta',
  rescue:            '🟡 Seguimiento',
  backlog:           '⬜ Backlog',
  lost:              '⬛ Inactivo',
};

const BUCKET_CLS = {
  hot_open:          'bk-hot',
  hot_needs_template:'bk-hot',
  rescue:            'bk-rescue',
  backlog:           'bk-backlog',
  lost:              'bk-lost',
};

const ACTION_LABEL = {
  assign_now:                'Asignar ahora',
  supervisor_review:         'Escalar a supervisor',
  rescue_followup:           'Seguimiento personalizado',
  send_template_or_review:   'Enviar plantilla / revisar',
  hold_backlog:              'Backlog',
  no_action_lost:            'Inactivo',
  no_action_converted:       'Convertida',
  no_action_already_handled: 'Atendida',
};

const QUEUE_TABS = [
  { key: 'all',        label: 'Todas',                 icon: 'view-grid-outline' },
  { key: 'assignment', label: 'Asignar ahora',         icon: 'account-plus-outline' },
  { key: 'supervisor', label: 'Escaladas a supervisor', icon: 'shield-account-outline' },
  { key: 'rescue',     label: 'Seguimiento pendiente',  icon: 'lifebuoy' },
];

const CATEGORY_TABS = [
  { key: 'all',       label: 'Todos',          icon: 'filter-outline' },
  { key: 'captacion', label: 'Captación',       icon: 'account-plus-outline' },
  { key: 'operacion', label: 'Operación',       icon: 'calendar-clock-outline' },
  { key: 'ambiguo',   label: 'FAQ / Ambiguo',   icon: 'help-circle-outline' },
];

const KPI_TOOLTIPS = {
  total: 'Total de conversaciones activas con handoff HOT analizadas al {fecha}. Incluye captación y operación. No representa todos los mensajes de WhatsApp.',
  assignment: 'Sin agente asignado, con mensaje del cliente en las últimas 24h. Asignación inmediata recomendada. Incluye captación nueva y operación de citas.',
  supervisor: 'Asignadas hace más de 2 horas sin respuesta del agente. El SLA se mide desde que entró en cola, no desde que fue asignado.',
  rescue:     'Sin actividad del cliente hace 1-7 días. Intentar recuperar con plantilla o contacto personalizado. No significa pérdida definitiva.',
  no_action:  'Ya atendidas, en espera de respuesta del cliente, convertidas o en backlog histórico. Siguen activas en el sistema; no están cerradas.',
};

/* ─── Toast ──────────────────────────────────────────────────────────────── */
function ToastContainer({ toasts }) {
  return React.createElement('div', { style: { position:'fixed', bottom:24, right:24, zIndex:9999, display:'flex', flexDirection:'column', gap:8 } },
    toasts.map(t =>
      React.createElement('div', { key: t.id, style: {
        background: t.type === 'error' ? '#fee2e2' : '#f0fdf4',
        border: `1px solid ${t.type === 'error' ? '#fca5a5' : '#86efac'}`,
        color: t.type === 'error' ? '#991b1b' : '#166534',
        padding: '10px 16px', borderRadius: 8, fontSize: 13, maxWidth: 360,
        boxShadow: '0 4px 12px rgba(0,0,0,.1)',
      }}, t.msg)
    )
  );
}

/* ─── KPI Card ───────────────────────────────────────────────────────────── */
function KpiCard({ icon, label, value, sub, color = 'primary', urgent = false, tooltip }) {
  const colors = {
    primary: { bg: 'rgba(99,102,241,.1)', fg: '#6366f1', border: 'rgba(99,102,241,.2)' },
    amber:   { bg: 'rgba(245,158,11,.1)', fg: '#d97706', border: 'rgba(245,158,11,.2)' },
    red:     { bg: 'rgba(239,68,68,.1)',  fg: '#ef4444', border: 'rgba(239,68,68,.2)'  },
    green:   { bg: 'rgba(34,197,94,.1)',  fg: '#16a34a', border: 'rgba(34,197,94,.2)'  },
    gray:    { bg: 'rgba(107,114,128,.1)', fg:'#6b7280', border:'rgba(107,114,128,.2)' },
  };
  const c = colors[color] || colors.primary;
  return React.createElement('div', {
    title: tooltip || '',
    style: {
      background: '#fff', border: `1px solid ${urgent ? c.border : 'var(--border)'}`,
      borderRadius: 12, padding: '16px 20px', display: 'flex', flexDirection: 'column', gap: 4,
      boxShadow: urgent ? `0 0 0 2px ${c.border}` : 'var(--shadow-xs)',
      cursor: tooltip ? 'help' : 'default',
    }
  },
    React.createElement('div', { style: { display:'flex', alignItems:'center', gap:8, marginBottom:4 } },
      React.createElement('span', { style: { fontSize:20, color: c.fg } },
        React.createElement(Icon, { n: icon })
      ),
      React.createElement('span', { style: { fontSize:12, fontWeight:600, color:'var(--fg-3)', textTransform:'uppercase', letterSpacing:'.5px' } }, label),
      tooltip && React.createElement('span', { style:{ marginLeft:'auto', fontSize:11, color:'var(--fg-fade)', cursor:'help' } }, '?')
    ),
    React.createElement('div', { style: { fontSize:32, fontWeight:800, color: urgent ? c.fg : 'var(--fg-1)', lineHeight:1 } }, value),
    sub && React.createElement('div', { style: { fontSize:12, color:'var(--fg-3)', marginTop:2 } }, sub)
  );
}

/* ─── Summary strip ──────────────────────────────────────────────────────── */
function SummaryStrip({ summary, loading, date }) {
  if (!summary) return null;
  const aq  = summary.assignment_queue || {};
  const sq  = summary.supervisor_queue || {};
  const rq  = summary.rescue_queue     || {};
  const na  = summary.no_action        || {};
  const noActionTotal = (na.converted || 0) + (na.already_handled || 0) + (na.backlog || 0) + (na.lost || 0);
  const dateLabel = date || '—';

  return React.createElement('div', {
    style: {
      display: 'grid', gridTemplateColumns: 'repeat(auto-fit,minmax(170px,1fr))',
      gap: 12, padding: '16px 20px', background:'var(--bg-soft)',
      borderBottom: '1px solid var(--border)',
    }
  },
    React.createElement(KpiCard, {
      icon:'view-dashboard-outline', label:'Conversaciones evaluadas',
      value: loading ? '…' : (summary.total_decisions ?? '—'),
      color:'gray',
      tooltip: KPI_TOOLTIPS.total.replace('{fecha}', dateLabel),
    }),
    React.createElement(KpiCard, {
      icon:'account-plus-outline', label:'Asignar ahora',
      value: loading ? '…' : (aq.total ?? 0),
      sub: aq.eligible_for_autoassign != null ? `${aq.eligible_for_autoassign} elegibles autoassign` : null,
      color:'primary', urgent: (aq.total ?? 0) > 0,
      tooltip: KPI_TOOLTIPS.assignment,
    }),
    React.createElement(KpiCard, {
      icon:'shield-account-outline', label:'Escaladas a supervisor',
      value: loading ? '…' : (sq.total ?? 0),
      sub: sq.over_sla != null ? `${sq.over_sla} sobre SLA` : null,
      color:'red', urgent: (sq.total ?? 0) > 0,
      tooltip: KPI_TOOLTIPS.supervisor,
    }),
    React.createElement(KpiCard, {
      icon:'lifebuoy', label:'Seguimiento pendiente',
      value: loading ? '…' : (rq.total ?? 0),
      sub: rq.total ? `${rq.rescue_followup ?? 0} followup · ${rq.send_template_or_review ?? 0} plantilla` : null,
      color:'amber', urgent: (rq.total ?? 0) > 0,
      tooltip: KPI_TOOLTIPS.rescue,
    }),
    React.createElement(KpiCard, {
      icon:'check-circle-outline', label:'Sin acción inmediata',
      value: loading ? '…' : noActionTotal,
      sub: noActionTotal ? `${na.converted ?? 0} conv · ${na.already_handled ?? 0} atend · ${na.backlog ?? 0} backlog` : null,
      color:'green',
      tooltip: KPI_TOOLTIPS.no_action,
    }),
  );
}

/* ─── Badge helpers ──────────────────────────────────────────────────────── */
function PriorityBadge({ v }) {
  const labels = { high:'Alta', medium:'Media', normal:'Normal', low:'Baja' };
  return React.createElement('span', { className: `ho-bucket ${PRIORITY_CLS[v] || ''}` }, labels[v] || v || '—');
}
function RiskBadge({ v }) {
  const labels = { high:'Alto', medium:'Medio', low:'Bajo', closed:'Cerrado' };
  return React.createElement('span', { className: `ho-bucket ${RISK_CLS[v] || ''}` }, labels[v] || v || '—');
}
function BucketBadge({ v }) {
  return React.createElement('span', { className: `ho-bucket ${BUCKET_CLS[v] || 'bk-backlog'}` }, BUCKET_LABEL[v] || v || '—');
}
function ActionBadge({ v }) {
  return React.createElement('span', { style:{fontSize:12, color:'var(--fg-2)'}}, ACTION_LABEL[v] || v || '—');
}
function CategoryBadge({ v, label }) {
  const cls = { captacion:'cat-captacion', operacion:'cat-operacion', ambiguo:'cat-ambiguo' };
  return React.createElement('span', { className: `ho-bucket ${cls[v] || ''}` }, label || v || '—');
}

/* ─── Table ──────────────────────────────────────────────────────────────── */
function ItemsTable({ items, loading, emptyMsg, emptyIcon }) {
  if (loading) return React.createElement('div', { className:'ho-empty' },
    React.createElement(Icon, { n:'loading', cls:'mdi-spin' }), ' Cargando…'
  );
  if (!items || items.length === 0) return React.createElement('div', { className:'ho-empty' },
    React.createElement(Icon, { n: emptyIcon || 'inbox-outline' }), ' ', emptyMsg || 'Sin conversaciones.'
  );

  return React.createElement('div', { style:{ overflowX:'auto' } },
    React.createElement('table', { className:'ho-table' },
      React.createElement('thead', null,
        React.createElement('tr', null,
          ['Conv','Tipo / motivo','Categoría','Estado','Acción recomendada','Prioridad','Riesgo','Cita','Motivo'].map(h =>
            React.createElement('th', { key:h }, h)
          )
        )
      ),
      React.createElement('tbody', null,
        items.map(function(item) {
          var isAmbiguo = item.category === 'ambiguo';
          return React.createElement('tr', { key: item.conversation_id },
            React.createElement('td', null,
              React.createElement('a', {
                href: `${CFG.chatUrl}/${item.conversation_id}`,
                target:'_blank', rel:'noopener noreferrer',
                style:{ fontWeight:700, color:'var(--primary)' }
              }, `#${item.conversation_id}`)
            ),
            React.createElement('td', null,
              item.topic_label
                ? React.createElement(React.Fragment, null,
                    item.topic_label,
                    isAmbiguo ? React.createElement('span', {
                      title:'Intención no determinada — puede ser captación o soporte',
                      style:{marginLeft:4, cursor:'help', color:'var(--warning)'},
                    }, '⚠️') : null
                  )
                : React.createElement('span', { style:{color:'var(--fg-3)'} }, 'No clasificado')
            ),
            React.createElement('td', null,
              React.createElement(CategoryBadge, { v: item.category, label: item.category_label })
            ),
            React.createElement('td', null, React.createElement(BucketBadge, { v: item.bucket })),
            React.createElement('td', null, React.createElement(ActionBadge, { v: item.recommended_action })),
            React.createElement('td', null, React.createElement(PriorityBadge, { v: item.priority })),
            React.createElement('td', null, React.createElement(RiskBadge, { v: item.risk_level })),
            React.createElement('td', { style:{textAlign:'center'} },
              item.has_attributed_booking
                ? React.createElement(Icon, {n:'calendar-check', cls:'text-green'})
                : React.createElement(Icon, {n:'minus-circle-outline', cls:'text-muted'})
            ),
            React.createElement('td', { title: item.reason, style:{maxWidth:260, whiteSpace:'nowrap', overflow:'hidden', textOverflow:'ellipsis', fontSize:12, color:'var(--fg-3)'} },
              item.reason || '—'
            )
          );
        })
      )
    )
  );
}

/* ─── No-action panel ────────────────────────────────────────────────────── */
function NoActionPanel({ summary }) {
  if (!summary) return null;
  const na = summary.no_action || {};
  const rows = [
    { label:'Convertidas',    icon:'calendar-check',      val: na.converted       ?? 0, desc:'Paciente ya tiene cita atribuida.' },
    { label:'Ya atendidas',   icon:'check-decagram',      val: na.already_handled ?? 0, desc:'Agente respondió dentro del SLA.' },
    { label:'Backlog',        icon:'archive-clock',       val: na.backlog         ?? 0, desc:'Sin actividad reciente — deuda histórica.' },
    { label:'Inactivas',      icon:'account-off-outline', val: na.lost            ?? 0, desc:'Sin actividad en más de 30 días.' },
  ];
  const total = (na.converted || 0) + (na.already_handled || 0) + (na.backlog || 0) + (na.lost || 0);

  if (total === 0) return React.createElement('div', { className:'ho-empty', style:{padding:'16px 20px', justifyContent:'flex-start'} },
    React.createElement(Icon, {n:'check-all'}), ' No hay conversaciones sin acción registradas para esta fecha.'
  );

  return React.createElement('div', { style:{padding:'20px', display:'flex', flexDirection:'column', gap:8} },
    React.createElement('p', { style:{fontSize:13, color:'var(--fg-3)', marginBottom:8} },
      'Estas conversaciones no requieren acción inmediata. Siguen activas en el sistema.'
    ),
    rows.map(r =>
      React.createElement('div', {
        key: r.label,
        style:{
          display:'flex', alignItems:'center', gap:12, padding:'12px 16px',
          background:'#fff', border:'1px solid var(--border)', borderRadius:8,
        }
      },
        React.createElement('span', { style:{fontSize:22, color:'var(--fg-3)'}}, React.createElement(Icon, {n: r.icon})),
        React.createElement('div', { style:{flex:1}},
          React.createElement('div', { style:{fontWeight:600, fontSize:14}}, r.label),
          React.createElement('div', { style:{fontSize:12, color:'var(--fg-3)'}}, r.desc)
        ),
        React.createElement('div', { style:{fontSize:24, fontWeight:800, color:'var(--fg-2)'}}, r.val)
      )
    )
  );
}

/* ─── Category filter bar ────────────────────────────────────────────────── */
function CategoryFilterBar({ category, onChange }) {
  return React.createElement('div', {
    style:{
      display:'flex', alignItems:'center', gap:6,
      padding:'8px 20px', borderBottom:'1px solid var(--border)',
      background:'#fff',
    }
  },
    React.createElement('span', { style:{fontSize:11, fontWeight:600, color:'var(--fg-3)', textTransform:'uppercase', letterSpacing:'.5px', marginRight:4} }, 'Filtrar:'),
    CATEGORY_TABS.map(function(ct) {
      var active = category === ct.key;
      return React.createElement('button', {
        key: ct.key,
        onClick: function() { onChange(ct.key); },
        style:{
          display:'inline-flex', alignItems:'center', gap:4,
          padding:'4px 12px', borderRadius:16,
          border: active ? '1px solid var(--primary)' : '1px solid var(--border)',
          background: active ? 'var(--primary-fade)' : '#fff',
          color: active ? 'var(--primary)' : 'var(--fg-2)',
          fontSize:12, fontWeight: active ? 700 : 400,
          cursor:'pointer',
        }
      },
        React.createElement(Icon, {n: ct.icon}),
        ' ', ct.label
      );
    })
  );
}

/* ─── Empty state per queue tab ─────────────────────────────────────────── */
function queueEmptyMsg(tab) {
  if (tab === 'assignment') return { msg: '✓ Todas las conversaciones calientes tienen agente asignado.', icon: 'check-circle-outline' };
  if (tab === 'supervisor') return { msg: '✓ Ninguna conversación ha superado el SLA de 2 horas.', icon: 'shield-check-outline' };
  if (tab === 'rescue')     return { msg: '✓ No hay conversaciones en período de seguimiento (1-7 días).', icon: 'lifebuoy' };
  return { msg: 'Sin conversaciones activas en cola.', icon: 'inbox-outline' };
}

/* ─── App ────────────────────────────────────────────────────────────────── */
function App() {
  const today = new Date().toISOString().slice(0,10);
  const [date,     setDate]     = useState(today);
  const [tab,      setTab]      = useState('all');
  const [category, setCategory] = useState('all');
  const [loading,  setLoading]  = useState(true);
  const [error,    setError]    = useState(null);
  const [summary,  setSummary]  = useState(null);
  const [items,    setItems]    = useState([]);
  const [toasts,   setToasts]   = useState([]);
  const abortRef = useRef(null);

  function addToast(msg, type) {
    var id = Date.now();
    setToasts(function(t) { return t.concat([{ id: id, msg: msg, type: type || 'error' }]); });
    setTimeout(function() { setToasts(function(t) { return t.filter(function(x) { return x.id !== id; }); }); }, 5000);
  }

  // loadQueueData is stable (useCallback with empty deps).
  // Receives all params explicitly — never closes over mutable state.
  const loadQueueData = useCallback(function(dateVal, queueVal, categoryVal) {
    if (abortRef.current) { abortRef.current.abort(); }
    var ctrl = new AbortController();
    abortRef.current = ctrl;

    setLoading(true);
    setError(null);

    var params = new URLSearchParams({ date: dateVal, queue: queueVal, category: categoryVal });
    fetch(CFG.apiUrl + '?' + params.toString(), {
      signal: ctrl.signal,
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    })
    .then(function(resp) {
      if (ctrl.signal.aborted) return;
      var ct = resp.headers.get('content-type') || '';
      if (resp.redirected || resp.status === 302 || ct.indexOf('text/html') !== -1) {
        setError('Sesión expirada. Por favor recarga la página e inicia sesión.');
        if (!ctrl.signal.aborted) setLoading(false);
        return;
      }
      return resp.json().then(function(json) {
        if (ctrl.signal.aborted) return;
        if (!resp.ok || !json.ok) {
          setError(json.message || ('Error ' + resp.status));
          setLoading(false);
          return;
        }
        var data = json.data || {};
        setSummary(data.summary || null);
        if (queueVal === 'all') {
          var queues = data.queues || {};
          setItems((queues.assignment || []).concat(queues.supervisor || []).concat(queues.rescue || []));
        } else {
          setItems(data.items || []);
        }
        if (!ctrl.signal.aborted) setLoading(false);
      });
    })
    .catch(function(e) {
      if (e.name === 'AbortError') return;
      setError('Error de red: ' + e.message);
      addToast('Error cargando datos: ' + e.message);
      if (!ctrl.signal.aborted) setLoading(false);
    });
  }, []); // stable — no external deps

  useEffect(function() {
    loadQueueData(date, tab, category);
  }, [date, tab, category, loadQueueData]);

  useEffect(function() {
    return function() {
      if (abortRef.current) { abortRef.current.abort(); }
    };
  }, []);

  var displayItems = tab === 'all' ? items : items.filter(function(i) {
    if (tab === 'assignment') return i.recommended_action === 'assign_now';
    if (tab === 'supervisor') return i.recommended_action === 'supervisor_review';
    if (tab === 'rescue')     return i.recommended_action === 'rescue_followup' || i.recommended_action === 'send_template_or_review';
    return true;
  });

  var emptyState = queueEmptyMsg(tab);

  return React.createElement(React.Fragment, null,
    React.createElement(ToastContainer, { toasts: toasts }),

    /* ── Header ── */
    React.createElement('div', { className:'ho-hd' },
      React.createElement('div', { style:{display:'flex', flexDirection:'column', gap:2, flex:1} },
        React.createElement('div', { style:{display:'flex', alignItems:'center', gap:12} },
          React.createElement('span', { style:{fontSize:20, color:'var(--primary)'}},
            React.createElement(Icon, {n:'view-dashboard-variant-outline'})
          ),
          React.createElement('span', { className:'ho-hd-title'}, 'Bandeja HOT — Captación y Citas'),
          summary && React.createElement('span', { className:'ho-hd-pill' },
            (summary.total_decisions != null ? summary.total_decisions : '—') + ' evaluadas'
          )
        ),
        React.createElement('p', {
          style:{fontSize:12, color:'var(--fg-3)', margin:0, paddingLeft:32}
        }, 'Conversaciones activas de captación y operación de citas que requieren atención del equipo.')
      ),
      /* Date picker + note + manual refresh */
      React.createElement('div', { style:{display:'flex', flexDirection:'column', alignItems:'flex-end', gap:4}},
        React.createElement('div', { style:{display:'flex', alignItems:'center', gap:8}},
          React.createElement(Icon, {n:'calendar-outline', cls:''}),
          React.createElement('input', {
            type:'date', value:date,
            onChange: function(e) { setDate(e.target.value); },
            title: 'Referencia de urgencia — no fecha de creación',
            style:{
              border:'1px solid var(--border)', borderRadius:6, padding:'5px 10px',
              fontSize:13, color:'var(--fg-1)', background:'#fff', cursor:'pointer',
            }
          }),
          React.createElement('button', {
            className:'ho-btn',
            title: 'Actualizar',
            onClick: function() { loadQueueData(date, tab, category); },
            disabled: loading,
          }, loading ? React.createElement(Icon,{n:'loading',cls:'mdi-spin'}) : React.createElement(Icon,{n:'refresh'}))
        ),
        React.createElement('span', { style:{fontSize:11, color:'var(--fg-fade)', paddingRight:2} },
          'Fecha = referencia de urgencia, no filtro de creación'
        )
      )
    ),

    /* ── KPI Strip ── */
    React.createElement(SummaryStrip, { summary: summary, loading: loading, date: date }),

    /* ── Category filter ── */
    React.createElement(CategoryFilterBar, { category: category, onChange: setCategory }),

    /* ── Queue Tabs ── */
    React.createElement('div', { className:'ho-tabs' },
      QUEUE_TABS.map(function(t) {
        return React.createElement('button', {
          key: t.key,
          className: 'ho-tab' + (tab === t.key ? ' active' : ''),
          onClick: function() { setTab(t.key); },
        },
          React.createElement(Icon, { n: t.icon }),
          ' ', t.label,
          tab === t.key && !loading ? React.createElement('span', {
            style:{
              marginLeft:6, background:'var(--primary)', color:'#fff',
              borderRadius:10, fontSize:11, padding:'1px 6px', fontWeight:700,
            }
          }, displayItems.length) : null
        );
      })
    ),

    /* ── Content ── */
    React.createElement('div', { style:{flex:1, overflowY:'auto', padding:'0 0 24px'} },
      error
        ? React.createElement('div', { style:{margin:'24px', padding:'16px', background:'#fee2e2', border:'1px solid #fca5a5', borderRadius:8, color:'#991b1b', fontSize:13}},
            React.createElement(Icon, {n:'alert-circle-outline'}), ' ', error
          )
        : tab === 'all' && !loading
          ? React.createElement(React.Fragment, null,
              React.createElement('div', { style:{padding:'20px 20px 4px', fontSize:12, fontWeight:700, color:'var(--fg-3)', textTransform:'uppercase', letterSpacing:'.5px'}}, 'Sin acción inmediata'),
              React.createElement(NoActionPanel, { summary: summary }),
              React.createElement('div', { style:{padding:'20px 20px 4px', fontSize:12, fontWeight:700, color:'var(--fg-3)', textTransform:'uppercase', letterSpacing:'.5px'}}, 'Conversaciones activas en cola'),
              React.createElement(ItemsTable, { items: displayItems, loading: loading, emptyMsg: emptyState.msg, emptyIcon: emptyState.icon })
            )
          : React.createElement(ItemsTable, {
              items: displayItems, loading: loading,
              emptyMsg: emptyState.msg,
              emptyIcon: emptyState.icon,
            })
    )
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(React.createElement(App));
