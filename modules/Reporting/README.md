# Reporting module

El módulo **Reporting** agrupa la reportería PDF y expone las plantillas en una
estructura coherente con el resto de la plataforma.

## Estructura de plantillas

```
modules/Reporting/Templates/
├── assets/           # CSS compartido para todos los PDF
├── layouts/          # Layouts base reutilizables (HTML + CSS embebido)
├── partials/         # Fragmentos comunes (por ejemplo, cabeceras de pacientes)
└── reports/          # Plantillas renderizables por slug (protocolo, 005, 007...)
```

* `layouts/base.php` encapsula la estructura `<html>`, inyecta el CSS definido
en `assets/pdf.css` y recibe tres variables:
  * `$header`: HTML opcional que se renderiza antes del contenido principal.
  * `$content`: contenido del reporte (se genera con `ob_start()`/`ob_get_clean()`).
  * `$title`: texto para la etiqueta `<title>`.
* `partials/patient_header.php` centraliza la cabecera repetida de los
formularios clínicos (datos del establecimiento y del paciente).
* `assets/pdf.css` combina las reglas que antes vivían en `public/css/pdf/`,
por lo que basta un único archivo para todos los reportes.

## Crear un nuevo reporte

1. **Definir los datos** que recibirá la plantilla desde el controlador o
   servicio correspondiente (por ejemplo, `$paciente`, `$consulta`, etc.).
2. **Crear el archivo** `modules/Reporting/Templates/reports/<slug>.php`. El
   nombre del archivo define el slug utilizado por `ReportService`.
3. **Incluir el layout** y construir el contenido:

   ```php
   <?php
   $layout = __DIR__ . '/../layouts/base.php';

   // Opcional: preparar datos para partials reutilizables
   $patient = [
       'afiliacion' => $paciente['afiliacion'] ?? '',
       'hc_number' => $paciente['hc_number'] ?? '',
       'lname' => $paciente['lname'] ?? '',
       // ...
   ];

   ob_start();
   include __DIR__ . '/../partials/patient_header.php';
   $header = ob_get_clean();

   ob_start();
   ?>
   <!-- HTML del reporte -->
   <?php
   $content = ob_get_clean();
   $title = 'Nombre del reporte';

   include $layout;
   ```

4. **Registrar CSS adicional** (si es necesario) agregando archivos dentro de
   `Templates/assets/` y pasándolos al layout mediante la variable `$stylesheets`.
   La mayoría de casos utilizan únicamente `pdf.css`.
5. **Probar el slug** consumiendo `ReportService::render('<slug>')` o generando
   el PDF desde el controlador para asegurarse de que la salida es la esperada.

Con esta estructura basta con duplicar un reporte existente, ajustar los datos y
mantener la cabecera/estilos centralizados.
