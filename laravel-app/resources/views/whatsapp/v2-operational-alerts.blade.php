@extends('layouts.medforge')

@section('pageTitle', 'Alertas Operacionales · WhatsApp')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<style>
  :root {
    --font-body: 'Inter', system-ui, -apple-system, sans-serif;
    --fg-1: #111827; --fg-2: #374151; --fg-3: #6b7280; --fg-fade: #9ca3af;
    --bg-soft: #f3f6f9; --border: #e5e7eb; --border-soft: #f1f3f5;
    --gray-100: #f3f4f6; --gray-200: #e5e7eb; --gray-400: #9ca3af;
    --primary: #6366f1; --primary-hover: #4f46e5; --primary-fade: rgba(99,102,241,.12);
    --danger: #ef4444; --danger-light: rgba(239,68,68,.10);
    --warning: #f59e0b; --warning-light: rgba(245,158,11,.10);
    --success: #22c55e; --success-light: rgba(34,197,94,.12);
    --orange: #f97316; --orange-light: rgba(249,115,22,.10);
    --shadow-xs: 0 1px 2px rgba(0,0,0,.05);
    --shadow: 0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
    --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.06);
  }
  .oa-page { background: var(--bg-soft); min-height: calc(100vh - 60px); padding: 24px; font-family: var(--font-body); }
  /* ── Header ── */
  .oa-header { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 20px; gap: 16px; flex-wrap: wrap; }
  .oa-title-group h1 { font-size: 22px; font-weight: 700; color: var(--fg-1); margin: 0 0 4px; }
  .oa-title-group p  { font-size: 13px; color: var(--fg-3); margin: 0; }
  .oa-badge-ro { display: inline-flex; align-items: center; gap: 5px; background: var(--danger-light); color: var(--danger); border: 1px solid rgba(239,68,68,.25); border-radius: 6px; font-size: 11px; font-weight: 700; padding: 4px 10px; letter-spacing: .4px; }
  .oa-header-right { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .oa-date-input { border: 1px solid var(--border); border-radius: 6px; padding: 6px 10px; font-size: 13px; color: var(--fg-2); background: #fff; }
  .oa-btn { display: inline-flex; align-items: center; gap: 5px; border: 1px solid var(--border); border-radius: 6px; padding: 6px 12px; font-size: 13px; font-weight: 600; cursor: pointer; background: #fff; color: var(--fg-2); transition: background .15s; }
  .oa-btn:hover { background: var(--gray-100); }
  .oa-btn-primary { background: var(--primary); color: #fff; border-color: var(--primary); }
  .oa-btn-primary:hover { background: var(--primary-hover); }
  /* ── Safety notice ── */
  .oa-safety { background: #fffbeb; border: 1px solid #fde68a; border-radius: 8px; padding: 10px 14px; font-size: 12px; color: #92400e; display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
  /* ── KPI strip ── */
  .oa-kpis { display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px; margin-bottom: 24px; }
  .oa-kpi { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; box-shadow: var(--shadow-xs); }
  .oa-kpi-label { font-size: 11px; font-weight: 600; color: var(--fg-3); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 6px; }
  .oa-kpi-value { font-size: 28px; font-weight: 800; color: var(--fg-1); line-height: 1; }
  .oa-kpi.critical .oa-kpi-value { color: var(--danger); }
  .oa-kpi.high .oa-kpi-value     { color: var(--orange); }
  .oa-kpi.medium .oa-kpi-value   { color: var(--warning); }
  /* ── Filters bar ── */
  .oa-filters { background: #fff; border: 1px solid var(--border); border-radius: 10px; padding: 14px 16px; margin-bottom: 20px; display: flex; gap: 12px; flex-wrap: wrap; align-items: center; box-shadow: var(--shadow-xs); }
  .oa-filters label { font-size: 12px; font-weight: 600; color: var(--fg-3); margin-right: 4px; }
  .oa-filters select { border: 1px solid var(--border); border-radius: 6px; padding: 5px 8px; font-size: 13px; color: var(--fg-2); background: #fff; cursor: pointer; }
  .oa-filters-sep { width: 1px; height: 24px; background: var(--border); }
  /* ── Section ── */
  .oa-section { background: #fff; border: 1px solid var(--border); border-radius: 10px; margin-bottom: 20px; box-shadow: var(--shadow-xs); overflow: hidden; }
  .oa-section-hd { display: flex; align-items: center; gap: 10px; padding: 14px 18px; border-bottom: 1px solid var(--border-soft); }
  .oa-section-hd h2 { font-size: 14px; font-weight: 700; color: var(--fg-1); margin: 0; }
  .oa-section-hd p  { font-size: 12px; color: var(--fg-3); margin: 0 0 0 auto; }
  .oa-section-dot { width: 10px; height: 10px; border-radius: 50%; flex-shrink: 0; }
  .dot-critical { background: var(--danger); }
  .dot-rescue   { background: var(--warning); }
  .dot-other    { background: var(--primary); }
  /* ── Table ── */
  .oa-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .oa-table th { padding: 8px 14px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--fg-3); text-align: left; border-bottom: 1px solid var(--border); background: var(--gray-100); }
  .oa-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-soft); vertical-align: middle; color: var(--fg-2); }
  .oa-table tr:last-child td { border-bottom: none; }
  .oa-table tr:hover td { background: var(--primary-fade); }
  /* ── Severity badge ── */
  .sev { display: inline-flex; align-items: center; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 700; letter-spacing: .3px; }
  .sev-critical { background: var(--danger-light); color: var(--danger); border: 1px solid rgba(239,68,68,.2); }
  .sev-high     { background: var(--orange-light); color: var(--orange); border: 1px solid rgba(249,115,22,.2); }
  .sev-medium   { background: var(--warning-light); color: #b45309; border: 1px solid rgba(245,158,11,.2); }
  .sev-low      { background: var(--gray-100); color: var(--fg-3); border: 1px solid var(--border); }
  /* ── Cat badge ── */
  .cat { display: inline-flex; padding: 1px 7px; border-radius: 8px; font-size: 11px; font-weight: 600; background: var(--primary-fade); color: var(--primary); }
  /* ── Empty state ── */
  .oa-empty { padding: 40px 20px; text-align: center; color: var(--fg-3); font-size: 13px; }
  .oa-empty .mdi { font-size: 36px; display: block; margin-bottom: 8px; opacity: .4; }
  /* ── Loading ── */
  .oa-loading { padding: 40px 20px; text-align: center; color: var(--fg-3); font-size: 13px; }
  .oa-spinner { display: inline-block; width: 24px; height: 24px; border: 3px solid var(--border); border-top-color: var(--primary); border-radius: 50%; animation: spin .7s linear infinite; }
  @keyframes spin { to { transform: rotate(360deg); } }
  /* ── View link ── */
  .oa-view-link { display: inline-flex; align-items: center; gap: 4px; color: var(--primary); font-weight: 600; font-size: 12px; text-decoration: none; }
  .oa-view-link:hover { color: var(--primary-hover); }
  /* ── Error ── */
  .oa-error { margin-bottom: 16px; padding: 12px 16px; background: var(--danger-light); border: 1px solid rgba(239,68,68,.2); border-radius: 8px; color: var(--danger); font-size: 13px; display: none; }
</style>
@endpush

@section('content')
<div class="oa-page">

  {{-- Header --}}
  <div class="oa-header">
    <div class="oa-title-group">
      <h1><span class="mdi mdi-alert-circle-outline" style="color:var(--primary)"></span> Alertas Operacionales WhatsApp</h1>
      <p>Riesgos detectados por el motor operacional. Panel read-only: no asigna, no cambia estados y no envía mensajes.</p>
    </div>
    <div class="oa-header-right">
      <span class="oa-badge-ro"><span class="mdi mdi-eye-outline"></span> MODO LECTURA</span>
      <input type="date" id="oa-date-input" class="oa-date-input" value="{{ date('Y-m-d') }}">
      <button class="oa-btn oa-btn-primary" id="oa-refresh-btn">
        <span class="mdi mdi-refresh"></span> Actualizar
      </button>
    </div>
  </div>

  {{-- Safety notice --}}
  <div class="oa-safety">
    <span class="mdi mdi-shield-lock-outline" style="font-size:16px"></span>
    <span>Este panel solo muestra alertas. Las acciones deben realizarse manualmente desde la bandeja de conversación. No ejecuta acciones automáticas.</span>
  </div>

  {{-- Error --}}
  <div class="oa-error" id="oa-error"></div>

  {{-- KPI strip --}}
  <div class="oa-kpis" id="oa-kpis">
    <div class="oa-kpi"><div class="oa-kpi-label">Evaluadas</div><div class="oa-kpi-value" id="kpi-evaluated">—</div></div>
    <div class="oa-kpi"><div class="oa-kpi-label">Alertas totales</div><div class="oa-kpi-value" id="kpi-total">—</div></div>
    <div class="oa-kpi critical"><div class="oa-kpi-label">Críticas</div><div class="oa-kpi-value" id="kpi-critical">—</div></div>
    <div class="oa-kpi high"><div class="oa-kpi-label">Altas</div><div class="oa-kpi-value" id="kpi-high">—</div></div>
    <div class="oa-kpi medium"><div class="oa-kpi-label">Medias</div><div class="oa-kpi-value" id="kpi-medium">—</div></div>
    <div class="oa-kpi"><div class="oa-kpi-label">Bajas</div><div class="oa-kpi-value" id="kpi-low">—</div></div>
  </div>

  {{-- Filters --}}
  <div class="oa-filters">
    <label>Severidad</label>
    <select id="f-severity">
      <option value="all">Todas</option>
      <option value="critical">Crítica</option>
      <option value="high">Alta</option>
      <option value="medium">Media</option>
      <option value="low">Baja</option>
    </select>
    <div class="oa-filters-sep"></div>
    <label>Categoría</label>
    <select id="f-category">
      <option value="all">Todas</option>
      <option value="captacion">Captación</option>
      <option value="operacion">Operación</option>
      <option value="ambiguo">FAQ / Ambiguo</option>
    </select>
    <div class="oa-filters-sep"></div>
    <label>Tipo</label>
    <select id="f-type">
      <option value="all">Todos</option>
      <option value="hot_unassigned">HOT sin asignar</option>
      <option value="rescue_aging">Rescue aging</option>
      <option value="supervisor_sla_breach">SLA supervisor</option>
      <option value="no_availability_repeated">Sin disponibilidad</option>
      <option value="ambiguous_urgent_faq">FAQ urgente</option>
    </select>
    <div class="oa-filters-sep"></div>
    <label>Agente</label>
    <select id="f-agent">
      <option value="all">Todos</option>
      <option value="unassigned">Sin asignar</option>
    </select>
    <div class="oa-filters-sep"></div>
    <label>Límite</label>
    <select id="f-limit">
      <option value="50">50</option>
      <option value="100">100</option>
      <option value="500" selected>500</option>
    </select>
  </div>

  {{-- Section: Críticas HOT sin asignar --}}
  <div class="oa-section" id="section-critical">
    <div class="oa-section-hd">
      <span class="oa-section-dot dot-critical"></span>
      <h2>Críticas — HOT sin asignar</h2>
      <p id="count-critical">Cargando…</p>
    </div>
    <div id="body-critical">
      <div class="oa-loading"><span class="oa-spinner"></span></div>
    </div>
  </div>

  {{-- Section: Rescue aging --}}
  <div class="oa-section" id="section-rescue">
    <div class="oa-section-hd">
      <span class="oa-section-dot dot-rescue"></span>
      <h2>Seguimiento pendiente — Rescue Aging</h2>
      <p id="count-rescue">Cargando…</p>
    </div>
    <div id="body-rescue">
      <div class="oa-loading"><span class="oa-spinner"></span></div>
    </div>
  </div>

  {{-- Section: Otras alertas --}}
  <div class="oa-section" id="section-other" style="display:none">
    <div class="oa-section-hd">
      <span class="oa-section-dot dot-other"></span>
      <h2>Otras alertas</h2>
      <p id="count-other"></p>
    </div>
    <div id="body-other"></div>
  </div>

</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const API_URL  = '{{ $apiUrl }}';
  const CHAT_URL = '{{ $chatUrl }}';

  // ── State ─────────────────────────────────────────────────────────────────
  let allAlerts = [];

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const dateInput  = document.getElementById('oa-date-input');
  const refreshBtn = document.getElementById('oa-refresh-btn');
  const errorBox   = document.getElementById('oa-error');
  const fSeverity  = document.getElementById('f-severity');
  const fCategory  = document.getElementById('f-category');
  const fType      = document.getElementById('f-type');
  const fAgent     = document.getElementById('f-agent');
  const fLimit     = document.getElementById('f-limit');

  // ── Helpers ───────────────────────────────────────────────────────────────
  function esc(s) {
    return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

  function fmtMinutes(m) {
    m = parseInt(m) || 0;
    if (m < 60)  return m + ' min';
    if (m < 1440) return Math.floor(m / 60) + 'h ' + (m % 60) + 'min';
    return Math.floor(m / 1440) + 'd ' + Math.floor((m % 1440) / 60) + 'h';
  }

  function fmtDatetime(s) {
    if (!s) return '—';
    const d = new Date(s);
    if (isNaN(d)) return s;
    return d.toLocaleDateString('es-EC', { day:'2-digit', month:'short' })
      + ' ' + d.toLocaleTimeString('es-EC', { hour:'2-digit', minute:'2-digit' });
  }

  function sevBadge(sev) {
    const map = { critical:'Crítica', high:'Alta', medium:'Media', low:'Baja' };
    return `<span class="sev sev-${esc(sev)}">${esc(map[sev] ?? sev)}</span>`;
  }

  function catBadge(cat) {
    return cat ? `<span class="cat">${esc(cat)}</span>` : '';
  }

  function chatLink(convId) {
    return `<a class="oa-view-link" href="${esc(CHAT_URL)}?conversation_id=${esc(convId)}" target="_blank"><span class="mdi mdi-open-in-new"></span> Ver</a>`;
  }

  function setKpi(id, val) {
    const el = document.getElementById(id);
    if (el) el.textContent = val ?? '—';
  }

  function showError(msg) {
    errorBox.textContent = msg;
    errorBox.style.display = 'block';
  }

  function clearError() { errorBox.style.display = 'none'; }

  // ── Build table ───────────────────────────────────────────────────────────
  function buildTable(alerts, cols) {
    if (!alerts.length) return null;
    const headers = cols.map(c => `<th>${esc(c.label)}</th>`).join('');
    const rows = alerts.map(a => {
      const cells = cols.map(c => `<td>${c.render(a)}</td>`).join('');
      return `<tr>${cells}</tr>`;
    }).join('');
    return `<table class="oa-table"><thead><tr>${headers}</tr></thead><tbody>${rows}</tbody></table>`;
  }

  // ── Column definitions ────────────────────────────────────────────────────
  const COLS_CRITICAL = [
    { label: 'Conv.',      render: a => `<strong>#${esc(a.conversation_id)}</strong>` },
    { label: 'Categoría',  render: a => catBadge(a.category_label || a.category) },
    { label: 'Topic',      render: a => esc(a.topic_label || a.topic) },
    { label: 'Severidad',  render: a => sevBadge(a.severity) },
    { label: 'Esperando',  render: a => `<strong>${fmtMinutes(a.waiting_minutes)}</strong>` },
    { label: 'Último inbound', render: a => fmtDatetime(a.latest_inbound_at) },
    { label: 'Agente',     render: a => a.assigned_user_name ? esc(a.assigned_user_name) : '<em style="color:var(--fg-3)">Sin asignar</em>' },
    { label: 'Acción sugerida', render: a => `<span style="color:var(--fg-3);font-size:12px">${esc(a.suggested_action)}</span>` },
    { label: '',           render: a => chatLink(a.conversation_id) },
  ];

  const COLS_RESCUE = [
    { label: 'Conv.',      render: a => `<strong>#${esc(a.conversation_id)}</strong>` },
    { label: 'Categoría',  render: a => catBadge(a.category_label || a.category) },
    { label: 'Topic',      render: a => esc(a.topic_label || a.topic) },
    { label: 'Severidad',  render: a => sevBadge(a.severity) },
    { label: 'Esperando',  render: a => fmtMinutes(a.waiting_minutes) },
    { label: 'Último inbound', render: a => fmtDatetime(a.latest_inbound_at) },
    { label: 'Agente',     render: a => a.assigned_user_name ? esc(a.assigned_user_name) : '<em style="color:var(--fg-3)">Sin asignar</em>' },
    { label: 'Acción',     render: a => `<span style="color:var(--fg-3);font-size:12px">${esc(a.suggested_action)}</span>` },
    { label: '',           render: a => chatLink(a.conversation_id) },
  ];

  const COLS_OTHER = [
    { label: 'Conv.',      render: a => `<strong>#${esc(a.conversation_id)}</strong>` },
    { label: 'Tipo',       render: a => `<code style="font-size:11px">${esc(a.alert_type)}</code>` },
    { label: 'Severidad',  render: a => sevBadge(a.severity) },
    { label: 'Categoría',  render: a => catBadge(a.category_label || a.category) },
    { label: 'Topic',      render: a => esc(a.topic_label || a.topic) },
    { label: 'Esperando',  render: a => fmtMinutes(a.waiting_minutes) },
    { label: 'Acción',     render: a => `<span style="color:var(--fg-3);font-size:12px">${esc(a.suggested_action)}</span>` },
    { label: '',           render: a => chatLink(a.conversation_id) },
  ];

  // ── Render sections ───────────────────────────────────────────────────────
  function renderSections(alerts) {
    const criticals = alerts.filter(a => a.alert_type === 'hot_unassigned' && a.severity === 'critical');
    const hotHighs  = alerts.filter(a => a.alert_type === 'hot_unassigned' && a.severity !== 'critical');
    const rescues   = alerts.filter(a => a.alert_type === 'rescue_aging');
    const others    = alerts.filter(a => a.alert_type !== 'hot_unassigned' && a.alert_type !== 'rescue_aging');

    // Combine hot_unassigned into critical section
    const hotSection = [...criticals, ...hotHighs];

    // Critical section
    const cCount = document.getElementById('count-critical');
    const cBody  = document.getElementById('body-critical');
    cCount.textContent = hotSection.length + ' conversaciones';
    if (hotSection.length) {
      cBody.innerHTML = buildTable(hotSection, COLS_CRITICAL) || '';
    } else {
      cBody.innerHTML = `<div class="oa-empty"><span class="mdi mdi-check-circle-outline"></span>Sin alertas críticas HOT sin asignar.</div>`;
    }

    // Rescue section
    const rCount = document.getElementById('count-rescue');
    const rBody  = document.getElementById('body-rescue');
    rCount.textContent = rescues.length + ' conversaciones';
    if (rescues.length) {
      rBody.innerHTML = buildTable(rescues, COLS_RESCUE) || '';
    } else {
      rBody.innerHTML = `<div class="oa-empty"><span class="mdi mdi-check-circle-outline"></span>Sin seguimientos pendientes.</div>`;
    }

    // Others section
    const oSection = document.getElementById('section-other');
    const oCount   = document.getElementById('count-other');
    const oBody    = document.getElementById('body-other');
    if (others.length) {
      oSection.style.display = '';
      oCount.textContent = others.length + ' alertas';
      oBody.innerHTML = buildTable(others, COLS_OTHER) || '';
    } else {
      oSection.style.display = 'none';
    }
  }

  // ── Agent dropdown ────────────────────────────────────────────────────────
  function populateAgents(alerts) {
    const seen = new Map();
    alerts.forEach(a => {
      if (a.assigned_user_id && a.assigned_user_name) {
        seen.set(String(a.assigned_user_id), a.assigned_user_name);
      }
    });
    const current = fAgent.value;
    // Remove dynamic options (keep first two: all, unassigned)
    while (fAgent.options.length > 2) fAgent.remove(2);
    seen.forEach((name, id) => {
      const opt = new Option(name, id);
      fAgent.add(opt);
    });
    fAgent.value = current;
  }

  // ── Fetch & render ────────────────────────────────────────────────────────
  async function load() {
    clearError();
    refreshBtn.disabled = true;
    refreshBtn.innerHTML = '<span class="oa-spinner" style="width:14px;height:14px;border-width:2px"></span> Cargando…';

    ['body-critical','body-rescue','body-other'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.innerHTML = '<div class="oa-loading"><span class="oa-spinner"></span></div>';
    });
    ['count-critical','count-rescue','count-other'].forEach(id => {
      const el = document.getElementById(id);
      if (el) el.textContent = 'Cargando…';
    });

    const params = new URLSearchParams({
      date:     dateInput.value || new Date().toISOString().slice(0,10),
      severity: fSeverity.value,
      category: fCategory.value,
      type:     fType.value,
      agent:    fAgent.value,
      limit:    fLimit.value,
    });

    try {
      const resp = await fetch(`${API_URL}?${params.toString()}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!resp.ok) {
        const err = await resp.json().catch(() => ({}));
        throw new Error(err.message || `HTTP ${resp.status}`);
      }
      const data = await resp.json();

      // KPIs
      setKpi('kpi-evaluated', data.evaluated ?? '—');
      setKpi('kpi-total',     data.alerts_total ?? '—');
      setKpi('kpi-critical',  data.summary?.critical ?? 0);
      setKpi('kpi-high',      data.summary?.high ?? 0);
      setKpi('kpi-medium',    data.summary?.medium ?? 0);
      setKpi('kpi-low',       data.summary?.low ?? 0);

      allAlerts = data.alerts || [];

      populateAgents(allAlerts);
      renderSections(allAlerts);

      if (!allAlerts.length) {
        ['body-critical','body-rescue'].forEach(id => {
          const el = document.getElementById(id);
          if (el && el.querySelector('.oa-spinner')) {
            el.innerHTML = '<div class="oa-empty"><span class="mdi mdi-check-circle-outline"></span>No se detectaron alertas operacionales para esta fecha.</div>';
          }
        });
      }

    } catch (e) {
      showError('Error al cargar alertas: ' + e.message);
    } finally {
      refreshBtn.disabled = false;
      refreshBtn.innerHTML = '<span class="mdi mdi-refresh"></span> Actualizar';
    }
  }

  // ── Events ────────────────────────────────────────────────────────────────
  refreshBtn.addEventListener('click', load);

  [fSeverity, fCategory, fType, fAgent, fLimit, dateInput].forEach(el => {
    el.addEventListener('change', load);
  });

  // Initial load
  load();
})();
</script>
@endpush
