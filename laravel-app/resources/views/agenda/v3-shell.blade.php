<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>MedForge — Agenda</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" href="/agenda-v3/assets/favicon.ico">
<link rel="stylesheet" href="/agenda-v3/colors_and_type.css">
<link rel="stylesheet" href="/agenda-v3/shell.css">
<link rel="stylesheet" href="/agenda-v3/module.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@mdi/font@7.4.47/css/materialdesignicons.min.css" crossorigin="anonymous">
<script src="https://unpkg.com/react@18.3.1/umd/react.development.js"
        integrity="sha384-hD6/rw4ppMLGNu3tX5cjIb+uRZ7UkRJ6BPkLpg4hAu/6onKUg4lLsHAs9EBPT82L" crossorigin="anonymous"></script>
<script src="https://unpkg.com/react-dom@18.3.1/umd/react-dom.development.js"
        integrity="sha384-u6aeetuaXnQ38mYT8rp6sbXaQe3NL9t+IBXmnYxwkUI2Hw4bsp2Wvmx4yRQF1uAm" crossorigin="anonymous"></script>
<script src="https://unpkg.com/@babel/standalone@7.29.0/babel.min.js"
        integrity="sha384-m08KidiNqLdpJqLq95G/LEi8Qvjl/xUYll3QILypMoQ65QorJ9Lvtp2RXYGBFj1y" crossorigin="anonymous"></script>
</head>
<body>
<div id="root"></div>

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

<script src="/agenda-v3/api.js"></script>
<script src="/agenda-v3/clinical-data.js"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/components.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/calendar.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/modals.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/flowboard.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/clinical.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/assistant.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/consulta.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/config.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/spec.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/tweaks-panel.jsx"></script>
<script type="text/babel" data-presets="react" src="/agenda-v3/app.jsx"></script>
</body>
</html>
