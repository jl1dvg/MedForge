@extends('layouts.medforge')

@section('pageTitle', 'Bandeja operacional · WhatsApp')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<link rel="stylesheet" href="/js/whatsapp-hot-opps/hot-opps.css">
<style>
  :root {
    --font-body: 'Inter', system-ui, -apple-system, sans-serif;
    --font-display: 'Inter', system-ui, -apple-system, sans-serif;
    --font-mono: 'JetBrains Mono', monospace;
    --fs-14: 14px;
    --rem: 14px;
    --fg-1: #111827;
    --fg-2: #374151;
    --fg-3: #6b7280;
    --fg-fade: #9ca3af;
    --fg-mute: #d1d5db;
    --bg-soft: #f3f6f9;
    --border: #e5e7eb;
    --border-soft: #f1f3f5;
    --border-strong: #d1d5db;
    --gray-100: #f3f4f6;
    --gray-200: #e5e7eb;
    --gray-400: #9ca3af;
    --gray-500: #6b7280;
    --primary: #6366f1;
    --primary-hover: #4f46e5;
    --primary-fade: rgba(99,102,241,.12);
    --primary-light: rgba(99,102,241,.08);
    --brand-navy: #0f172a;
    --brand-navy-2: #1e293b;
    --brand-cyan: #06b6d4;
    --cat-examen: rgba(99,102,241,.1);
    --cat-examen-fg: #6366f1;
    --danger: #ef4444;
    --danger-hover: #dc2626;
    --success: #22c55e;
    --success-light: rgba(34,197,94,.12);
    --warning: #f59e0b;
    --grad-brand-mark: linear-gradient(135deg, #6366f1 0%, #06b6d4 100%);
    --shadow-xs: 0 1px 2px rgba(0,0,0,.05);
    --shadow: 0 1px 3px rgba(0,0,0,.1), 0 1px 2px rgba(0,0,0,.06);
    --shadow-md: 0 4px 6px rgba(0,0,0,.07), 0 2px 4px rgba(0,0,0,.06);
    --shadow-focus: 0 0 0 3px rgba(99,102,241,.3);
    --ease-out: cubic-bezier(.16,1,.3,1);
  }
  /* Full-height cockpit inside the MedForge shell */
  .content-wrapper { background: var(--bg-soft, #f3f6f9) !important; overflow: hidden !important; }
  .content-wrapper > .content { padding: 0 !important; height: calc(100vh - 60px); display: flex; flex-direction: column; min-height: 0; }
  #root { flex: 1; display: flex; flex-direction: column; min-height: 0; overflow: hidden; }
  /* Hide duplicate brand inside the cockpit header when in layout shell */
  .ho-brand { display: none; }
  .ho-hd-divider { display: none; }

  /* ── Bucket badges ── */
  .ho-bucket { display: inline-flex; align-items: center; gap: 4px; font-size: 11px; font-weight: 700; padding: 2px 8px; border-radius: 12px; letter-spacing: .3px; }
  .bk-hot     { background: rgba(239,68,68,.13);   color: #ef4444; border: 1px solid rgba(239,68,68,.22); }
  .bk-rescue  { background: rgba(245,158,11,.13);  color: #d97706; border: 1px solid rgba(245,158,11,.22); }
  .bk-backlog { background: rgba(107,114,128,.1);  color: #6b7280; border: 1px solid rgba(107,114,128,.2); }
  .bk-lost    { background: rgba(107,114,128,.07); color: #9ca3af; border: 1px solid rgba(107,114,128,.13); }

  /* ── Tab variants ── */
  .ho-tab-sep { width: 1px; background: var(--border-strong); margin: 0 4px; flex-shrink: 0; }
  .ho-tab-hot.active    { border-bottom-color: #ef4444; color: #ef4444; }
  .ho-tab-rescue.active { border-bottom-color: #d97706; color: #d97706; }
  .ho-tab-backlog.active,.ho-tab-lost.active { border-bottom-color: #6b7280; color: #6b7280; }

  /* ── Historical banner ── */
  .ho-hist-banner { display: flex; align-items: center; gap: 8px; margin: 12px 16px 0; padding: 9px 14px; background: rgba(107,114,128,.07); border: 1px solid rgba(107,114,128,.18); border-radius: 8px; font-size: 13px; color: var(--fg-3); }
  .ho-hist-banner .mdi { font-size: 16px; flex-shrink: 0; }

  /* ── KPI pill variants ── */
  .ho-hd-pill.exec { background: rgba(99,102,241,.15); color: var(--primary); }
  .ho-hd-pill.debt { background: rgba(107,114,128,.12); color: #6b7280; }

  /* ── Priority / risk badges ── */
  .pri-high   { background: rgba(239,68,68,.13);   color: #ef4444; border: 1px solid rgba(239,68,68,.22); }
  .pri-med    { background: rgba(245,158,11,.13);  color: #d97706; border: 1px solid rgba(245,158,11,.22); }
  .pri-normal { background: rgba(99,102,241,.1);   color: #6366f1; border: 1px solid rgba(99,102,241,.2); }
  .pri-low    { background: rgba(107,114,128,.1);  color: #6b7280; border: 1px solid rgba(107,114,128,.2); }
  .risk-high   { background: rgba(239,68,68,.13);   color: #ef4444; border: 1px solid rgba(239,68,68,.22); }
  .risk-med    { background: rgba(245,158,11,.13);  color: #d97706; border: 1px solid rgba(245,158,11,.22); }
  .risk-low    { background: rgba(34,197,94,.1);    color: #16a34a; border: 1px solid rgba(34,197,94,.2); }
  .risk-closed { background: rgba(107,114,128,.1);  color: #6b7280; border: 1px solid rgba(107,114,128,.2); }

  /* ── Table ── */
  .ho-table { width: 100%; border-collapse: collapse; font-size: 13px; }
  .ho-table th { padding: 10px 14px; text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--fg-3); border-bottom: 2px solid var(--border); white-space: nowrap; }
  .ho-table td { padding: 10px 14px; border-bottom: 1px solid var(--border-soft); vertical-align: middle; }
  .ho-table tr:hover td { background: var(--primary-light); }
  .text-green { color: #16a34a; }
  .text-muted { color: var(--fg-mute); }

  /* ── Empty state ── */
  .ho-empty { display: flex; align-items: center; justify-content: center; gap: 10px; padding: 48px 24px; color: var(--fg-3); font-size: 14px; }

  /* ── Button ── */
  .ho-btn { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 6px; border: 1px solid var(--border); background: #fff; font-size: 13px; font-weight: 600; color: var(--fg-2); cursor: pointer; transition: background .15s; }
  .ho-btn:hover { background: var(--gray-100); }
  .ho-btn:disabled { opacity: .5; cursor: not-allowed; }
</style>
@endpush

@section('content')
<div id="root"></div>
@endsection

@push('scripts')
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" crossorigin="anonymous"></script>
<script>
window.HOT_OPPS_CONFIG = {
  apiUrl:         @json($apiUrl),
  chatUrl:        @json($chatUrl),
  pollIntervalMs: 0,
};
</script>
<script src="/js/whatsapp-hot-opps/app.js?v={{ filemtime(public_path('js/whatsapp-hot-opps/app.js')) }}"></script>
@endpush
