@extends('layouts.medforge')

@section('pageTitle', 'Reporte ejecutivo · WhatsApp')

@push('styles')
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css">
<link rel="stylesheet" href="/css/medforge-tokens.css">
<link rel="stylesheet" href="/css/pages/whatsapp-reporte.css">
<style>
  /* Compensar el header fijo de MedForge (≈60 px) para el sticky toolbar del reporte */
  .rep-toolbar { top: 60px; }
  /* Fondo del área de contenido igual al del documento */
  .content-wrapper { background: var(--bg-soft, #f3f6f9) !important; }
  /* Ocultar marca duplicada dentro del toolbar cuando ya está en el layout */
  .rep-tb-brand { display: none; }
</style>
@endpush

@section('content')
<div id="root"></div>
@endsection

@push('scripts')
{{-- React 18 + ReactDOM + PropTypes + Recharts + Babel (transpila JSX en cliente) --}}
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js" integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js" integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/prop-types@15.8.1/prop-types.min.js"></script>
<script src="https://unpkg.com/recharts@2.12.7/umd/Recharts.js"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js" integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
<script>
/* Silenciar warning de defaultProps de recharts — ruido de la librería */
(function () {
  const origErr = console.error;
  console.error = function (...args) {
    const msg = typeof args[0] === 'string' ? args[0] : '';
    if (msg.indexOf('Support for defaultProps will be removed') !== -1) return;
    origErr.apply(console, args);
  };
}());
</script>

{{-- Capa de datos (WAR mock — reemplazar con datos reales del controlador vía window.WAR_DATA) --}}
<script src="/js/pages/whatsapp-reporte/data.js"></script>

{{-- Componentes React (transpilados por Babel en cliente) --}}
<script type="text/babel" data-presets="react" src="/js/pages/whatsapp-reporte/components.jsx"></script>
<script type="text/babel" data-presets="react" src="/js/pages/whatsapp-reporte/charts.jsx"></script>
<script type="text/babel" data-presets="react" src="/js/pages/whatsapp-reporte/sections-a.jsx"></script>
<script type="text/babel" data-presets="react" src="/js/pages/whatsapp-reporte/sections-b.jsx"></script>
<script type="text/babel" data-presets="react" src="/js/pages/whatsapp-reporte/app.jsx"></script>
@endpush
