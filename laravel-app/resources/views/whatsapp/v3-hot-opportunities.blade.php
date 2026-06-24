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
  apiUrl: @json($apiUrl),
  chatUrl: @json($chatUrl),
  pollIntervalMs: 30000,
};
</script>
<script src="/js/whatsapp-hot-opps/app.js"></script>
@endpush
