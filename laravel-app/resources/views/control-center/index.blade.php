<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>MedForge Control Center</title>
    @vite(['resources/js/control-center/main.jsx'])
</head>
<body>
    <div id="control-center-root"></div>
</body>
</html>
