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

## Plantillas PDF fijas (aseguradoras)

Algunas aseguradoras entregan formularios predefinidos en PDF. Para reutilizar
esos archivos como fondo y sobreimprimir los datos:

1. **Guardar la plantilla** dentro de `storage/reporting/templates/` (el archivo
   puede versionarse o copiarse en el despliegue).
2. **Registrar la definición** en `modules/Reporting/Services/Definitions/pdf-templates.php`
   utilizando `ArrayPdfTemplateDefinition` u otra implementación del contrato
   `PdfTemplateDefinitionInterface`.

   ```php
   use Modules\Reporting\Services\Definitions\ArrayPdfTemplateDefinition;

   return [
       new ArrayPdfTemplateDefinition(
           'aseguradora_demo',               // slug del reporte
           'aseguradora_demo.pdf',           // ruta relativa al directorio storage/reporting/templates
           [
               'numero_poliza' => ['x' => 42, 'y' => 65],
               'fecha_emision' => [
                   'x' => 110,
                   'y' => 65,
                   'width' => 35,
                   'align' => 'C',
               ],
               'diagnostico' => [
                   'x' => 20,
                   'y' => 110,
                   'width' => 170,
                   'line_height' => 4.5,
                   'multiline' => true,
               ],
           ],
           [
               'font_family' => 'helvetica',
               'font_size' => 9,
           ]
       ),
   ];
   ```

   Cada entrada del mapa de campos acepta las llaves `x` y `y` (coordenadas en
   milímetros), opcionalmente `width`, `height`, `align`, `line_height`, `page`
   (para PDFs multipágina) y `multiline` cuando se espera más de una línea.

3. **Consumir el reporte** mediante `ReportService::renderDocument()` o el
   helper `PdfGenerator::generarReporte()`. El servicio detecta el slug y
   delega en `PdfTemplateRenderer` cuando existe una definición PDF; en caso
   contrario continúa con el flujo HTML + MPDF de siempre.

### Ajustes disponibles

* `font_family`, `font_size`, `line_height` y `text_color` pueden definirse por
  plantilla o sobrescribirse al llamar a `renderDocument()`/`generarReporte()`.
* La clave `overrides` permite inyectar valores puntuales sin modificar los
  datos originales.
