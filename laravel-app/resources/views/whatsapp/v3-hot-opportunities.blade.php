@extends('layouts.medforge')

@section('pageTitle', 'Bandeja operacional · WhatsApp')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<link rel="stylesheet" href="/css/medforge-tokens.css">
<link rel="stylesheet" href="/js/whatsapp-hot-opps/hot-opps.css">
<style>
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
