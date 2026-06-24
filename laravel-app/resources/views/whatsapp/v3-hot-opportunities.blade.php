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
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script>
window.HOT_OPPS_CONFIG = {
  apiUrl: @json($apiUrl),
  chatUrl: @json($chatUrl),
  pollIntervalMs: 30000,
};
</script>
<script type="text/babel" data-presets="react" src="/js/whatsapp-hot-opps/app.jsx"></script>
@endpush
