@extends('layouts.medforge')

@php
    $pageTitle = 'Agenda V3';
    $skipDefaultVendorScripts = false;
    $disableWelcomeTour = true;
    $agendaV3AssetVersion = '20260605-98bbd9dc';
@endphp

@push('styles')
<link rel="stylesheet" href="/agenda-v3/colors_and_type.css?v={{ $agendaV3AssetVersion }}">
<link rel="stylesheet" href="/agenda-v3/shell.css?v={{ $agendaV3AssetVersion }}">
<link rel="stylesheet" href="/agenda-v3/module.css?v={{ $agendaV3AssetVersion }}">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" crossorigin="anonymous">
<style>
  /* Integración con el wrapper de MedForge */
  .content-wrapper { background: var(--surface, #f4f6fb) !important; }
  #agenda-v3-root { min-height: calc(100vh - 120px); }
</style>
@endpush

@section('content')
<div id="agenda-v3-root"></div>
@endsection

@push('scripts')
<script>
window.__MF__ = {
  csrf:    "{{ csrf_token() }}",
  apiBase: "/v3/api/agenda",
  user: {
    id:     {{ (int) auth()->id() }},
    nombre: "{{ addslashes(auth()->user()->nombre ?? auth()->user()->name ?? '') }}",
    rol:    "{{ addslashes(auth()->user()->rol ?? '') }}"
  },
  backUrl: "/v2/dashboard"
};
</script>
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js"
        integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js"
        integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js"
        integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script src="/agenda-v3/data.js?v={{ $agendaV3AssetVersion }}"></script>
<script src="/agenda-v3/api.js?v={{ $agendaV3AssetVersion }}"></script>
<script src="/agenda-v3/clinical-data.js?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/components.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/calendar.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/modals.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/flowboard.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/clinical.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/assistant.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/consulta.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/config.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/spec.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/tweaks-panel.jsx?v={{ $agendaV3AssetVersion }}"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/app.jsx?v={{ $agendaV3AssetVersion }}"></script>
@endpush
