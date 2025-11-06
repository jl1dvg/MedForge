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

## Plantillas HTML por aseguradora

Para las solicitudes que reutilizan las vistas HTML (`007.php`, `010.php`,
`referencia.php`) se añadió un flujo declarativo que selecciona el conjunto de
plantillas según la aseguradora.

1. **Normalización de datos.** `SolicitudDataFormatter::enrich()` agrega campos
   comunes (nombre completo, edad formateada, slug de aseguradora, listas de
   exámenes, etc.) para que todas las vistas tengan el mismo contrato.
2. **Registro de plantillas.** Define las variaciones en
   `modules/Reporting/Services/Definitions/solicitud-templates.php` utilizando
   `ArraySolicitudTemplateDefinition`. Cada entrada especifica identificador,
   páginas a renderizar, orientación, CSS opcional y coincidencias por slug o
   regex.
3. **Selección automática.** `ProtocolReportService::generateCoberturaDocument()`
   consulta el registro y genera el PDF con las páginas y opciones asociadas.

Ejemplo de entrada:

```php
use Modules\Reporting\Services\Definitions\ArraySolicitudTemplateDefinition;

return [
    new ArraySolicitudTemplateDefinition(
        'cobertura-seguroxyz',
        ['007', '010'],
        [
            'orientation' => 'P',
            'css' => dirname(__DIR__, 2) . '/Templates/assets/pdf.css',
        ],
        ['seguroxyz', 'regex:/^seguro-xyz/']
    ),
    // ... fallback "cobertura" definido por defecto
];
```

El primer matcher que coincida con `aseguradoraSlug`, `aseguradoraNombre` o la
afiliación del paciente determina la plantilla. Si ninguna coincide, se utiliza
la entrada `cobertura` como fallback.

### Ejemplo: cobertura con Ecuasanitas

1. **Registrar la plantilla.** Añade una entrada en
   `modules/Reporting/Services/Definitions/solicitud-templates.php` con el slug
   `cobertura-ecuasanitas`. El matcher `['ecuasanitas']` hará que cualquier
   solicitud cuya aseguradora se llame "Ecuasanitas" utilice esta vista
   específica. El `filename_pattern` puede personalizar el nombre del PDF,
   p. ej. `cobertura_ecuasanitas_%2$s_%3$s.pdf`.
2. **Crear la vista.** Genera `Templates/reports/cobertura_ecuasanitas.php` (ver
   ejemplo incluido) y compón el HTML usando las variables normalizadas por
   `SolicitudDataFormatter::enrich()`. Los campos claves ya vienen listos:

   | Campo | Variable disponible |
   | --- | --- |
   | Historia clínica | `$hc_number` (alias `$pacienteHistoriaClinica`) |
   | Nombre del paciente | `$paciente['full_name']` (o `$pacienteNombreCompleto`) |
   | Fecha de nacimiento | `$paciente['fecha_nacimiento']` (alias `$pacienteFechaNacimiento`) |
   | Fecha de nacimiento formateada | `$pacienteFechaNacimientoFormateada` |
   | Diagnósticos | `$diagnosticoLista` (formato "CIE - descripción") |
   | Diagnósticos en bloque | `$diagnosticoListaTexto` (texto multilínea) |

   Puedes iterar sobre `$diagnosticoLista` para renderizar los diagnósticos en un
   `<ol>` o tabla, tal como se muestra en la plantilla de ejemplo.
3. **Renderizar el documento.** Desde el controlador existe el método
   `PdfController::generateCobertura($formId, $hcNumber)` que delega en
   `ProtocolReportService::generateCoberturaDocument()`. Si quieres dispararlo
   manualmente en una vista o job puedes hacer:

   ```php
   $service = app(Modules\Reporting\Services\ProtocolReportService::class);
   $documento = $service->generateCoberturaDocument($formId, $hcNumber);

   if (($documento['mode'] ?? null) === 'report') {
       PdfGenerator::generarReporte(
           $documento['slug'],
           $documento['data'],
           array_merge(
               $documento['options'] ?? [],
               ['finalName' => $documento['filename'], 'modoSalida' => 'I']
           )
       );
   } else {
       PdfGenerator::generarDesdeHtml(
           $documento['html'],
           $documento['filename'],
           $documento['css'],
           'I',
           $documento['orientation'],
           $documento['mpdf'] ?? []
       );
   }
   ```

   El servicio ya inyecta los datos del paciente, consulta y diagnósticos a la
   vista según corresponda. No necesitas preparar los datos manualmente, solo
   pasar `formId` y `hcNumber`.

Con este flujo puedes tomar el PDF base de la aseguradora como referencia para
el diseño y únicamente ajustar la vista para ubicar historia clínica, nombre,
fecha de nacimiento y diagnósticos en las posiciones deseadas.

## Plantillas PDF fijas para aseguradoras

Cuando la aseguradora exige entregar un formulario prediseñado en PDF, puedes
reutilizar el motor FPDI integrado. El registro
`modules/Reporting/Services/Definitions/pdf-templates.php` describe cada
plantilla como un mapa de coordenadas sobre el PDF original.

```php
use Modules\Reporting\Services\Definitions\ArrayPdfTemplateDefinition;

return [
    new ArrayPdfTemplateDefinition(
        'cobertura-ecuasanitas-form',
        'aseguradoras/cobertura_ecuasanitas.pdf',
        [
            'hc_number' => ['x' => 135, 'y' => 126],
            'pacienteNombreCompleto' => ['x' => 135, 'y' => 138, 'width' => 320, 'multiline' => true],
            'pacienteFechaNacimientoFormateada' => ['x' => 135, 'y' => 150],
            'diagnosticoLista' => ['x' => 40, 'y' => 186, 'width' => 460, 'line_height' => 5, 'multiline' => true],
        ]
    ),
];
```

Cada entrada define:

* `identifier`: slug con el que invocarás el reporte (`generarReporte('cobertura-ecuasanitas-form', ...)`).
* `templatePath`: ruta del PDF base dentro de `storage/reporting/templates/` (asegúrate de colocar ahí el archivo oficial de la aseguradora).
* `fieldMap`: coordenadas absolutas en milímetros para cada campo.
* Opciones adicionales (`font_family`, `font_size`, `line_height`, `defaults`).

`SolicitudDataFormatter::enrich()` suministra de forma homogénea las llaves
`hc_number`, `pacienteNombreCompleto`, `pacienteFechaNacimientoFormateada`,
`diagnosticoLista` y `diagnosticoListaTexto`, por lo que no necesitas armar los
valores manualmente.

Para amarrar una aseguradora a un PDF fijo basta con declarar el slug de la
plantilla en el registro de solicitudes:

```php
new ArraySolicitudTemplateDefinition(
    'cobertura-ecuasanitas',
    ['cobertura_ecuasanitas'],
    [
        'report' => ['slug' => 'cobertura-ecuasanitas-form'],
        // ... resto de opciones (orientación, CSS, nombre de archivo)
    ],
    ['ecuasanitas']
);
```

Cuando `ProtocolReportService::generateCoberturaDocument()` detecta la clave
`report`, retorna el slug del PDF fijo y los datos ya enriquecidos. El
`PdfController` delega en `PdfGenerator::generarReporte()` para sobreimprimir
los campos sobre el PDF de la aseguradora.

## Plantillas HTML por aseguradora

Para las solicitudes que reutilizan las vistas HTML (`007.php`, `010.php`,
`referencia.php`) se añadió un flujo declarativo que selecciona el conjunto de
plantillas según la aseguradora.

1. **Normalización de datos.** `SolicitudDataFormatter::enrich()` agrega campos
   comunes (nombre completo, edad formateada, slug de aseguradora, listas de
   exámenes, etc.) para que todas las vistas tengan el mismo contrato.
2. **Registro de plantillas.** Define las variaciones en
   `modules/Reporting/Services/Definitions/solicitud-templates.php` utilizando
   `ArraySolicitudTemplateDefinition`. Cada entrada especifica identificador,
   páginas a renderizar, orientación, CSS opcional y coincidencias por slug o
   regex.
3. **Selección automática.** `ProtocolReportService::generateCoberturaDocument()`
   consulta el registro y genera el PDF con las páginas y opciones asociadas.

Ejemplo de entrada:

```php
use Modules\Reporting\Services\Definitions\ArraySolicitudTemplateDefinition;

return [
    new ArraySolicitudTemplateDefinition(
        'cobertura-seguroxyz',
        ['007', '010'],
        [
            'orientation' => 'P',
            'css' => dirname(__DIR__, 2) . '/Templates/assets/pdf.css',
        ],
        ['seguroxyz', 'regex:/^seguro-xyz/']
    ),
    // ... fallback "cobertura" definido por defecto
];
```

El primer matcher que coincida con `aseguradoraSlug`, `aseguradoraNombre` o la
afiliación del paciente determina la plantilla. Si ninguna coincide, se utiliza
la entrada `cobertura` como fallback.

### Ejemplo: cobertura con Ecuasanitas

1. **Registrar la plantilla.** Añade una entrada en
   `modules/Reporting/Services/Definitions/solicitud-templates.php` con el slug
   `cobertura-ecuasanitas`. El matcher `['ecuasanitas']` hará que cualquier
   solicitud cuya aseguradora se llame "Ecuasanitas" utilice esta vista
   específica. El `filename_pattern` puede personalizar el nombre del PDF,
   p. ej. `cobertura_ecuasanitas_%2$s_%3$s.pdf`.
2. **Crear la vista.** Genera `Templates/reports/cobertura_ecuasanitas.php` (ver
   ejemplo incluido) y compón el HTML usando las variables normalizadas por
   `SolicitudDataFormatter::enrich()`. Los campos claves ya vienen listos:

   | Campo | Variable disponible |
   | --- | --- |
   | Historia clínica | `$hc_number` |
   | Nombre del paciente | `$paciente['full_name']` (o `$pacienteNombreCompleto`) |
   | Fecha de nacimiento | `$paciente['fecha_nacimiento']` |
   | Diagnósticos | `$diagnosticoLista` (formato "CIE - descripción") |

   Puedes iterar sobre `$diagnosticoLista` para renderizar los diagnósticos en un
   `<ol>` o tabla, tal como se muestra en la plantilla de ejemplo.
3. **Renderizar el documento.** Desde el controlador existe el método
   `PdfController::generateCobertura($formId, $hcNumber)` que delega en
   `ProtocolReportService::generateCoberturaDocument()`. Si quieres dispararlo
   manualmente en una vista o job puedes hacer:

   ```php
   $service = app(Modules\Reporting\Services\ProtocolReportService::class);
   $documento = $service->generateCoberturaDocument($formId, $hcNumber);

   PdfGenerator::generarDesdeHtml(
       $documento['html'],
       $documento['filename'],
       $documento['css'],
       'I',
       $documento['orientation'],
       $documento['mpdf'] ?? []
   );
   ```

   El servicio ya inyecta los datos del paciente, consulta y diagnósticos a la
   vista según corresponda. No necesitas preparar los datos manualmente, solo
   pasar `formId` y `hcNumber`.

Con este flujo puedes tomar el PDF base de la aseguradora como referencia para
el diseño y únicamente ajustar la vista para ubicar historia clínica, nombre,
fecha de nacimiento y diagnósticos en las posiciones deseadas.

## Plantillas HTML por aseguradora

Para las solicitudes que reutilizan las vistas HTML (`007.php`, `010.php`,
`referencia.php`) se añadió un flujo declarativo que selecciona el conjunto de
plantillas según la aseguradora.

1. **Normalización de datos.** `SolicitudDataFormatter::enrich()` agrega campos
   comunes (nombre completo, edad formateada, slug de aseguradora, listas de
   exámenes, etc.) para que todas las vistas tengan el mismo contrato.
2. **Registro de plantillas.** Define las variaciones en
   `modules/Reporting/Services/Definitions/solicitud-templates.php` utilizando
   `ArraySolicitudTemplateDefinition`. Cada entrada especifica identificador,
   páginas a renderizar, orientación, CSS opcional y coincidencias por slug o
   regex.
3. **Selección automática.** `ProtocolReportService::generateCoberturaDocument()`
   consulta el registro y genera el PDF con las páginas y opciones asociadas.

Ejemplo de entrada:

```php
use Modules\Reporting\Services\Definitions\ArraySolicitudTemplateDefinition;

return [
    new ArraySolicitudTemplateDefinition(
        'cobertura-seguroxyz',
        ['007', '010'],
        [
            'orientation' => 'P',
            'css' => dirname(__DIR__, 2) . '/Templates/assets/pdf.css',
        ],
        ['seguroxyz', 'regex:/^seguro-xyz/']
    ),
    // ... fallback "cobertura" definido por defecto
];
```

El primer matcher que coincida con `aseguradoraSlug`, `aseguradoraNombre` o la
afiliación del paciente determina la plantilla. Si ninguna coincide, se utiliza
la entrada `cobertura` como fallback.
