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

  {{-- Quick search --}}
  <div class="oa-filters" style="margin-bottom:6px">
    <label>Buscar</label>
    <input type="search" id="f-search" placeholder="Nombre, WhatsApp o HC…" style="padding:4px 8px;border:1px solid var(--border-1);border-radius:4px;font-size:13px;min-width:220px">
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

  {{-- Section: Daily Report (read-only) --}}
  <details class="oa-section" id="section-daily-report" style="margin-top:12px;border:2px dashed #bee3f8">
    <summary style="cursor:pointer;padding:12px 16px;display:flex;align-items:center;gap:10px;list-style:none;user-select:none">
      <span style="font-size:18px">📊</span>
      <div style="flex:1">
        <strong style="font-size:14px">Reporte diario — solo lectura</strong>
        <span style="background:#0369a1;color:#fff;border-radius:10px;padding:1px 8px;font-size:11px;margin-left:8px">READ-ONLY</span>
      </div>
      <span style="font-size:11px;color:#6c757d;font-style:italic">▾ expandir</span>
    </summary>
    <div style="padding:0 16px 16px">
      <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 14px;margin:12px 0;font-size:12px;color:#1e40af">
        <strong>📋 Solo lectura.</strong>
        Este reporte es solo lectura. No envía notificaciones, no asigna agentes y no modifica conversaciones.
      </div>
      <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:8px 14px;margin-bottom:10px;font-size:12px;color:#856404">
        <strong>Exportación manual read-only.</strong> No envía notificaciones, no asigna agentes y no modifica conversaciones.
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px;flex-wrap:wrap">
        <button id="report-refresh-btn" class="oa-btn" style="padding:4px 12px;font-size:12px">
          <span class="mdi mdi-refresh"></span> Actualizar reporte
        </button>
        <a id="report-export-csv-btn"
           href="{{ $exportApiUrl ?? '' }}?date={{ date('Y-m-d') }}&format=csv"
           style="padding:4px 12px;font-size:12px;text-decoration:none;background:#0369a1;color:#fff;border-radius:6px;display:inline-flex;align-items:center;gap:4px"
           download>
          <span class="mdi mdi-download"></span> Descargar CSV
        </a>
        <a id="report-export-xlsx-btn"
           href="{{ $exportApiUrl ?? '' }}?date={{ date('Y-m-d') }}&format=xlsx"
           style="padding:4px 12px;font-size:12px;text-decoration:none;background:#166534;color:#fff;border-radius:6px;display:inline-flex;align-items:center;gap:4px"
           download>
          <span class="mdi mdi-microsoft-excel"></span> Descargar Excel
        </a>
        <span style="font-size:11px;color:#6c757d">Última actualización: <span id="report-updated-at">—</span></span>
      </div>
      <div id="report-body"><em style="color:#6c757d;font-size:12px">Expande para cargar…</em></div>
    </div>
  </details>

  {{-- Section: Notification Preview (dry-run) --}}
  <details class="oa-section" id="section-preview" style="margin-top:12px;border:2px dashed #adb5bd">
    <summary style="cursor:pointer;padding:12px 16px;display:flex;align-items:center;gap:10px;list-style:none;user-select:none">
      <span style="font-size:18px">🔔</span>
      <div style="flex:1">
        <strong style="font-size:14px">Preview de notificaciones internas</strong>
        <span id="preview-count-badge" style="background:#6c757d;color:#fff;border-radius:10px;padding:1px 8px;font-size:11px;margin-left:8px">—</span>
      </div>
      <span style="font-size:11px;color:#6c757d;font-style:italic">▾ expandir</span>
    </summary>
    <div style="padding:0 16px 16px">
      <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:6px;padding:10px 14px;margin:12px 0;font-size:12px;color:#856404">
        <strong>🚫 DRY-RUN — no se envía ninguna notificación.</strong><br>
        Esta sección solo muestra qué se notificaría si coordinación aprueba la Fase 4C. No envía mensajes ni activa canales.<br>
        Canal: <strong>none</strong>. Estado: <strong>dry-run</strong>. DB writes: <strong>0</strong>.
      </div>
      <div style="display:flex;align-items:center;gap:12px;margin-bottom:12px">
        <div style="font-size:13px">
          Candidatas a notificar: <strong id="preview-would-notify">—</strong>
          &nbsp;·&nbsp; Canal: <strong>none</strong>
          &nbsp;·&nbsp; Estado: <strong>dry-run</strong>
        </div>
        <button id="preview-refresh-btn" class="oa-refresh-btn" style="padding:4px 12px;font-size:12px">
          <span class="mdi mdi-refresh"></span> Actualizar preview
        </button>
      </div>
      <div id="preview-body"><em style="color:#6c757d;font-size:12px">Expande para cargar…</em></div>
    </div>
  </details>

</div>
@endsection

@push('scripts')
<script>
(function () {
  'use strict';

  const API_URL         = '{{ $apiUrl }}';
  const CHAT_URL        = '{{ $chatUrl }}';
  const PREVIEW_API_URL = '{{ $previewApiUrl }}';

  // ── State ─────────────────────────────────────────────────────────────────
  let allAlerts = [];

  // ── DOM refs ──────────────────────────────────────────────────────────────
  const dateInput  = document.getElementById('oa-date-input');
  const refreshBtn = document.getElementById('oa-refresh-btn');
  const errorBox   = document.getElementById('oa-error');
  const fSearch    = document.getElementById('f-search');
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

  function fmtWa(n) {
    if (!n) return '—';
    const s = String(n).replace(/\D/g, '');
    if (s.length === 12 && s.startsWith('593'))
      return '+593 ' + s[3] + s[4] + ' ' + s[5] + s[6] + s[7] + ' ' + s.slice(8);
    return '+' + s;
  }

  function nameCell(a) {
    const name = esc(a.display_name || a.wa_number || '—');
    const sub  = a.conversation_id ? `<small style="color:var(--fg-3);font-size:10px">Conv #${esc(a.conversation_id)}</small>` : '';
    return `<div style="line-height:1.3">${name}<br>${sub}</div>`;
  }

  function agentCell(a) {
    if (a.assigned_user_name) return esc(a.assigned_user_name);
    return '<span style="background:#fff3cd;color:#856404;border-radius:4px;padding:2px 6px;font-size:11px;font-weight:600">Sin asignar</span>';
  }

  function chatLink(convId, waNumber) {
    const param = waNumber ? `search=${esc(waNumber)}&filter=all` : `conversation=${esc(convId)}`;
    const tip   = 'Abre el chat filtrado por número WhatsApp. No asigna la conversación automáticamente.';
    return `<a class="oa-view-link" href="${esc(CHAT_URL)}?${param}" target="_blank" title="${esc(tip)}"><span class="mdi mdi-open-in-new"></span> Ver en chat</a>`;
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
    { label: 'Paciente / Contacto', render: a => nameCell(a) },
    { label: 'WhatsApp',  render: a => `<code style="font-size:11px">${esc(fmtWa(a.wa_number))}</code>` },
    { label: 'HC',        render: a => a.hc_number ? `<small>HC ${esc(a.hc_number)}</small>` : '<span style="color:var(--fg-3)">—</span>' },
    { label: 'Motivo',    render: a => esc(a.topic_label || a.topic) },
    { label: 'Severidad', render: a => sevBadge(a.severity) },
    { label: 'Esperando', render: a => `<strong>${fmtMinutes(a.waiting_minutes)}</strong>` },
    { label: 'Último msg',render: a => fmtDatetime(a.latest_inbound_at) },
    { label: 'Agente',    render: a => agentCell(a) },
    { label: '',          render: a => chatLink(a.conversation_id, a.wa_number) },
  ];

  const COLS_RESCUE = [
    { label: 'Paciente / Contacto', render: a => nameCell(a) },
    { label: 'WhatsApp',  render: a => `<code style="font-size:11px">${esc(fmtWa(a.wa_number))}</code>` },
    { label: 'HC',        render: a => a.hc_number ? `<small>HC ${esc(a.hc_number)}</small>` : '<span style="color:var(--fg-3)">—</span>' },
    { label: 'Motivo',    render: a => esc(a.topic_label || a.topic) },
    { label: 'Severidad', render: a => sevBadge(a.severity) },
    { label: 'Esperando', render: a => fmtMinutes(a.waiting_minutes) },
    { label: 'Último msg',render: a => fmtDatetime(a.latest_inbound_at) },
    { label: 'Agente',    render: a => agentCell(a) },
    { label: 'Acción',    render: a => `<span style="color:var(--fg-3);font-size:11px">${esc(a.suggested_action)}</span>` },
    { label: '',          render: a => chatLink(a.conversation_id, a.wa_number) },
  ];

  const COLS_OTHER = [
    { label: 'Paciente / Contacto', render: a => nameCell(a) },
    { label: 'WhatsApp',  render: a => `<code style="font-size:11px">${esc(fmtWa(a.wa_number))}</code>` },
    { label: 'HC',        render: a => a.hc_number ? `<small>HC ${esc(a.hc_number)}</small>` : '<span style="color:var(--fg-3)">—</span>' },
    { label: 'Tipo',      render: a => `<code style="font-size:11px">${esc(a.alert_type)}</code>` },
    { label: 'Severidad', render: a => sevBadge(a.severity) },
    { label: 'Motivo',    render: a => esc(a.topic_label || a.topic) },
    { label: 'Esperando', render: a => fmtMinutes(a.waiting_minutes) },
    { label: 'Agente',    render: a => agentCell(a) },
    { label: 'Acción',    render: a => `<span style="color:var(--fg-3);font-size:11px">${esc(a.suggested_action)}</span>` },
    { label: '',          render: a => chatLink(a.conversation_id, a.wa_number) },
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
      applySearchAndRender();

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

  // ── Client-side search filter ─────────────────────────────────────────────
  function applySearchAndRender() {
    const q = (fSearch.value || '').trim().toLowerCase();
    if (!q) { renderSections(allAlerts); return; }
    const filtered = allAlerts.filter(a => {
      return (a.display_name  || '').toLowerCase().includes(q)
          || (a.wa_number     || '').toLowerCase().includes(q)
          || (a.hc_number     || '').toLowerCase().includes(q)
          || String(a.conversation_id || '').includes(q);
    });
    renderSections(filtered);
  }

  // ── Events ────────────────────────────────────────────────────────────────
  refreshBtn.addEventListener('click', load);
  fSearch.addEventListener('input', applySearchAndRender);

  [fSeverity, fCategory, fType, fAgent, fLimit, dateInput].forEach(el => {
    el.addEventListener('change', load);
  });

  // Initial load
  load();

  // ── Notification Preview (dry-run) ────────────────────────────────────────
  const previewSection    = document.getElementById('section-preview');
  const previewBody       = document.getElementById('preview-body');
  const previewRefreshBtn = document.getElementById('preview-refresh-btn');
  const previewCountBadge = document.getElementById('preview-count-badge');
  const previewWouldNotify= document.getElementById('preview-would-notify');
  let previewLoaded = false;

  async function loadPreview() {
    previewBody.innerHTML = '<em style="color:#6c757d;font-size:12px"><span class="oa-spinner" style="width:12px;height:12px;border-width:2px;vertical-align:middle"></span> Cargando preview…</em>';
    const params = new URLSearchParams({ date: dateInput.value || new Date().toISOString().slice(0,10) });
    try {
      const resp = await fetch(`${PREVIEW_API_URL}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const data = await resp.json();

      const n = data.would_notify ?? 0;
      previewCountBadge.textContent = n;
      previewCountBadge.style.background = n > 0 ? '#dc3545' : '#6c757d';
      previewWouldNotify.textContent = n;

      if (!n) {
        previewBody.innerHTML = '<em style="color:#6c757d;font-size:12px">Sin candidatas para esta fecha.</em>';
        return;
      }

      const rows = (data.notifications || []).map(n => `
        <tr>
          <td>${esc(n.display_name || '—')}<br><small style="color:var(--fg-3);font-size:10px">Conv #${esc(n.conversation_id)}</small></td>
          <td><code style="font-size:11px">${esc(n.wa_number || '—')}</code></td>
          <td>${n.hc_number ? `<small>HC ${esc(n.hc_number)}</small>` : '<span style="color:var(--fg-3)">—</span>'}</td>
          <td style="font-size:11px">${esc(n.topic_label || '—')}</td>
          <td style="font-weight:600">${fmtMinutes(n.waiting_minutes)}</td>
          <td style="font-size:10px;max-width:260px;white-space:pre-wrap;font-family:monospace;color:#495057">${esc(n.message_preview)}</td>
        </tr>`).join('');

      previewBody.innerHTML = `
        <table class="oa-table">
          <thead><tr>
            <th>Paciente / Contacto</th><th>WhatsApp</th><th>HC</th>
            <th>Motivo</th><th>Esperando</th><th>Mensaje preview</th>
          </tr></thead>
          <tbody>${rows}</tbody>
        </table>
        <p style="font-size:11px;color:#6c757d;margin-top:8px">
          ⚠ Dry-run — ninguno de estos mensajes fue enviado. Canal: none.
        </p>`;
      previewLoaded = true;
    } catch (e) {
      previewBody.innerHTML = `<em style="color:#dc3545;font-size:12px">Error al cargar preview: ${esc(e.message)}</em>`;
    }
  }

  previewSection.addEventListener('toggle', () => {
    if (previewSection.open && !previewLoaded) loadPreview();
  });
  previewRefreshBtn.addEventListener('click', (e) => { e.stopPropagation(); loadPreview(); });

  // ── Daily Report (read-only) ──────────────────────────────────────────────
  const REPORT_API_URL    = '{{ $reportApiUrl }}';
  const EXPORT_API_URL    = '{{ $exportApiUrl ?? '' }}';
  const reportSection     = document.getElementById('section-daily-report');
  const reportBody        = document.getElementById('report-body');
  const reportRefreshBtn  = document.getElementById('report-refresh-btn');
  const reportUpdatedAt   = document.getElementById('report-updated-at');
  const exportCsvBtn      = document.getElementById('report-export-csv-btn');
  const exportXlsxBtn     = document.getElementById('report-export-xlsx-btn');
  let reportLoaded = false;

  function updateExportLinks() {
    const date = dateInput.value || new Date().toISOString().slice(0, 10);
    exportCsvBtn.href  = `${EXPORT_API_URL}?date=${date}&format=csv`;
    exportXlsxBtn.href = `${EXPORT_API_URL}?date=${date}&format=xlsx`;
  }
  updateExportLinks();
  dateInput.addEventListener('change', updateExportLinks);

  async function loadReport() {
    reportBody.innerHTML = '<em style="color:#6c757d;font-size:12px"><span class="oa-spinner" style="width:12px;height:12px;border-width:2px;vertical-align:middle"></span> Cargando reporte…</em>';
    const params = new URLSearchParams({ date: dateInput.value || new Date().toISOString().slice(0,10) });
    try {
      const resp = await fetch(`${REPORT_API_URL}?${params}`, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin',
      });
      if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
      const d = await resp.json();

      reportUpdatedAt.textContent = new Date().toLocaleTimeString('es-EC', { hour:'2-digit', minute:'2-digit' });

      const s  = d.summary  || {};
      const np = d.notification_preview || {};

      const byTypeRows = Object.entries(d.by_type || {}).map(([t, c]) =>
        `<tr><td style="font-family:monospace;font-size:11px">${esc(t)}</td><td style="font-weight:600">${c}</td></tr>`).join('');

      const byCatRows = Object.entries(d.by_category || {}).map(([cat, c]) =>
        `<tr><td>${esc(cat)}</td><td style="font-weight:600">${c}</td></tr>`).join('');

      const topTopicRows = (d.top_topics || []).slice(0, 10).map(t =>
        `<tr><td>${esc(t.topic_label || t.topic)}</td><td style="font-weight:600">${t.count}</td></tr>`).join('');

      const byAgentRows = (d.by_agent || []).map(ag =>
        `<tr><td>${esc(ag.assigned_user_name)}</td><td>${ag.alerts_total}</td><td>${ag.critical || 0}</td><td>${ag.medium || 0}</td></tr>`).join('');

      const recHtml = (d.recommendations || []).map(r => `<li style="margin-bottom:4px">${esc(r)}</li>`).join('');

      reportBody.innerHTML = `
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px;margin-bottom:16px">
          ${[
            ['Evaluadas', s.evaluated ?? '—'],
            ['Total alertas', s.alerts_total ?? 0],
            ['Críticas', s.critical ?? 0, 'var(--danger)'],
            ['Altas', s.high ?? 0, 'var(--orange)'],
            ['Medias', s.medium ?? 0, 'var(--warning)'],
            ['Bajas', s.low ?? 0],
            ['Candidatas 🔔', np.would_notify ?? 0, np.would_notify > 0 ? 'var(--danger)' : undefined],
          ].map(([label, val, color]) => `
            <div style="background:#fff;border:1px solid var(--border);border-radius:8px;padding:10px 12px">
              <div style="font-size:10px;color:var(--fg-3);font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:4px">${esc(label)}</div>
              <div style="font-size:22px;font-weight:800;color:${color || 'var(--fg-1)'}">${val}</div>
            </div>`).join('')}
        </div>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px">
          ${byTypeRows ? `
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--fg-3);margin-bottom:6px;text-transform:uppercase">Por tipo</div>
            <table class="oa-table"><tbody>${byTypeRows}</tbody></table>
          </div>` : ''}
          ${byCatRows ? `
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--fg-3);margin-bottom:6px;text-transform:uppercase">Por categoría</div>
            <table class="oa-table"><tbody>${byCatRows}</tbody></table>
          </div>` : ''}
          ${topTopicRows ? `
          <div>
            <div style="font-size:11px;font-weight:700;color:var(--fg-3);margin-bottom:6px;text-transform:uppercase">Top motivos</div>
            <table class="oa-table"><tbody>${topTopicRows}</tbody></table>
          </div>` : ''}
          ${byAgentRows ? `
          <div style="grid-column:1/-1">
            <div style="font-size:11px;font-weight:700;color:var(--fg-3);margin-bottom:6px;text-transform:uppercase">Por agente</div>
            <table class="oa-table">
              <thead><tr><th>Agente</th><th>Total</th><th>Críticas</th><th>Medias</th></tr></thead>
              <tbody>${byAgentRows}</tbody>
            </table>
          </div>` : ''}
        </div>
        <div style="margin-top:12px;background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;padding:10px 14px">
          <div style="font-size:11px;font-weight:700;color:#1e40af;margin-bottom:4px">🔔 Notification Preview</div>
          <div style="font-size:12px;color:#1e40af">
            Candidatas: <strong>${np.would_notify ?? 0}</strong> &nbsp;·&nbsp;
            Canal: <strong>${esc(np.channel ?? 'none')}</strong> &nbsp;·&nbsp;
            Estado: <strong>${esc(np.mode ?? 'dry_run')}</strong> &nbsp;·&nbsp;
            Política: <em>${esc(np.policy ?? '')}</em>
          </div>
        </div>
        ${recHtml ? `<ul style="font-size:12px;color:#6c757d;margin-top:10px;padding-left:18px">${recHtml}</ul>` : ''}
        <p style="font-size:11px;color:#6c757d;margin-top:8px">
          ✔ Read-only — DB writes: 0 — No se enviaron notificaciones.
        </p>`;
      reportLoaded = true;
    } catch (e) {
      reportBody.innerHTML = `<em style="color:#dc3545;font-size:12px">Error al cargar reporte: ${esc(e.message)}</em>`;
    }
  }

  reportSection.addEventListener('toggle', () => {
    if (reportSection.open && !reportLoaded) loadReport();
  });
  reportRefreshBtn.addEventListener('click', (e) => { e.stopPropagation(); loadReport(); });
})();
</script>
@endpush
