<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte Ejecutivo · Imágenes · {{ $report['period']['fromLabel'] ?? '' }} → {{ $report['period']['toLabel'] ?? '' }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.materialdesignicons.com/7.2.96/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="{{ asset('css/v2/reportes-v2.css') }}">
  @vite(['resources/js/v2/reportes-v2/imagenes/app.tsx'])
</head>
<body>
  <script>
    window.MF_IMG_REPORT = @json($report);
    window.MF_IMG_SEDE_OPTIONS = @json($sedeOptions);
    window.MF_IMG_FILTERS = {
      startDate: @json($startDate),
      endDate: @json($endDate),
      sede: @json($sedeFilter),
    };
  </script>

  <div style="position:fixed;top:12px;left:16px;z-index:9999">
    <a href="/v2/dashboard"
       style="display:inline-flex;align-items:center;gap:6px;font:500 13px 'IBM Plex Sans',sans-serif;color:#5e6278;text-decoration:none;background:#fff;border:1px solid #e4e6ef;border-radius:8px;padding:6px 14px;box-shadow:0 1px 3px rgba(16,24,40,.08)">
      <i class="mdi mdi-arrow-left" style="font-size:16px"></i>
      Volver a MedForge
    </a>
  </div>

  <div id="app"></div>
</body>
</html>
