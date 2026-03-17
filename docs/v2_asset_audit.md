# Auditoria de assets v2

Fecha: 2026-03-16

## Alcance

- Se auditaron 33 vistas Blade que extienden `layouts.medforge`.
- `v2` y `legacy` comparten el mismo shell base de CSS/JS global.
- `v2` no usa Vite para este layout; carga archivos estaticos desde `public/`.

## Resumen ejecutivo

No conviene "actualizar todo" en bloque.

Hoy `v2` depende de un subconjunto pequeno de librerias, pero hereda:

- 43 imports globales en `public/css/vendors_css.css`
- 17 imports en `public/css/style.css`
- 12 icon packs cargados globalmente
- 74 carpetas en `public/assets/vendor_components`

El problema principal no es solo cantidad. Es mezcla:

- `legacy` y `v2` comparten bundles
- hay librerias locales viejas + CDN modernos en paralelo
- hay mismatches de version entre CSS y JS
- hay paquetes duplicados o superpuestos

La estrategia segura es:

1. estabilizar inconsistencias de bajo riesgo
2. aislar `v2` del bundle compartido
3. migrar las dependencias activas de `v2` por modulos
4. recien ahi empezar a podar el bundle heredado

## Modelo actual de carga

### Layout compartido

Tanto `v2` como `legacy` cargan este core:

- `public/css/vendors_css.css`
- Font Awesome 6.5.2 desde CDN
- `public/css/horizontal-menu.css`
- `public/css/style.css`
- `public/css/skin_color.css`
- `public/js/vendors.min.js`
- `public/js/pages/chat-popup.js`
- `public/assets/icons/feather-icons/feather.min.js`
- `public/js/jquery.smartmenus.js`
- `public/js/menus.js`
- `public/js/pages/global-search.js`
- `public/js/template.js`

### Bundles globales

`public/css/vendors_css.css` importa 43 hojas de vendor.

Incluye, entre otros:

- Bootstrap
- perfect-scrollbar
- OwlCarousel
- horizontal-timeline
- DataTables
- sweetalert v1
- daterangepicker
- fullcalendar
- select2
- dropzone
- chartist
- c3
- weather-icons

`public/css/style.css` importa 12 packs de iconos:

- Font Awesome local
- Ionicons
- Themify
- Linea
- Glyphicons
- Flag icons
- Material Design Icons
- Simple Line Icons
- CryptoCoins
- Weather Icons
- Iconsmind
- Icomoon

## Dependencias activas detectadas en v2

La siguiente tabla refleja dependencias con evidencia directa en vistas `v2` o scripts usados por esas vistas.

| Dependencia | Fuente actual | Version detectada | Uso en v2 | Notas |
| --- | --- | --- | --- | --- |
| jQuery | `public/js/vendors.min.js` | 3.3.1 | shell + multiples pantallas | dependencia fuerte de `template.js`, `menus.js` y varios scripts de pagina |
| Bootstrap JS | `public/js/vendors.min.js` | 5.0.0 | shell | tooltips y comportamiento general |
| Bootstrap CSS | `public/assets/vendor_components/bootstrap/dist/css/bootstrap.css` | 5.2.2 | shell | mismatch con Bootstrap JS 5.0.0 |
| SmartMenus | `public/js/jquery.smartmenus.js` | no auditada | shell | usado por `menus.js` |
| slimScroll | `public/js/vendors.min.js` | no auditada | shell | `template.js` depende de esto para sidebar/paneles |
| Perfect Scrollbar | `public/js/vendors.min.js` + CSS global | 1.4.0 | sin uso directo encontrado en `v2` | solapado con slimScroll |
| Material Design Icons | pack local | 2.0.46 | muy usado | demasiado viejo para varias clases nuevas |
| Font Awesome local | `style.css` | 4.7.0 | cargado globalmente | duplicado con FA 6.5.2 por CDN |
| Font Awesome CDN | layout | 6.5.2 | usado en busqueda global y UI nueva | duplica al pack local |
| DataTables local | `public/assets/vendor_components/datatable/datatables.min.js` | 1.10.16 + extensiones | 6 pantallas | bundle armado para Bootstrap 4 |
| DataTables CDN | CDN | 1.13.8 | 2 pantallas | otra familia distinta y mas nueva |
| ApexCharts | local | 3.19.0 | 7 pantallas | activo en dashboards |
| SweetAlert2 | CDN | 11 | 6 pantallas | JS moderno, pero CSS global carga `sweetalert` v1 |
| daterangepicker | CDN | no auditada | 4 pantallas | convive con CSS local de otra copia |
| moment | CDN | 2.x | 4 pantallas | dependencia transitiva de rango de fechas |
| Pusher | CDN | 8.4.0 | 3 pantallas | tiempo real |
| OwlCarousel | local | 2.3.4 | 1 pantalla | dashboard |
| jQuery Steps | local | 1.1.0 | 1 pantalla | wizard quirurgico |
| jQuery Validation | local | 1.17.0 | 1 pantalla | wizard quirurgico |
| Tiny Editable | local | no auditada | 1 pantalla | wizard quirurgico |
| CKEditor | local | 4.9.1 | 1 pantalla | version vieja, revisar por soporte y seguridad |
| Peity | local | 3.2.1 | 1 pantalla | no facturados |
| horizontal-timeline | local | no auditada | 1 pantalla | detalle de pacientes |
| Pickadate | CDN | 3.6.2 | 1 pantalla | flujo de pacientes |
| Chart.js | CDN | 4.4.1 | 1 pantalla | dashboard de imagenes |
| SortableJS | CDN | 1.15.0 | 1 pantalla | constructor de paquetes |
| JSZip utils | CDN | 0.1.0 | 1 pantalla | examenes |
| JSZip | CDN | 3.7.1 | 1 pantalla | examenes |

## Hallazgos clave

### 1. `v2` y `legacy` estan acoplados al mismo bundle

Una actualizacion global de CSS/JS puede romper ambos lados al mismo tiempo.

Esto aplica especialmente a:

- iconos
- Bootstrap
- template shell
- DataTables
- jQuery plugins viejos

### 2. Hay inconsistencias de version reales

- Bootstrap CSS esta en 5.2.2, pero Bootstrap JS en 5.0.0.
- Font Awesome local esta en 4.7.0, pero el layout tambien carga FA 6.5.2 por CDN.
- MDI local esta en 2.0.46.
- DataTables local esta en 1.10.16 y fue generado con integracion Bootstrap 4.
- Algunas pantallas nuevas ya usan DataTables CDN 1.13.8 con estilos Bootstrap 5.

Esto significa que hoy no hay una sola linea base; hay varias.

### 3. El shell global arrastra bastante peso que `v2` no necesita de forma evidente

En `v2` no se encontro referencia directa a muchas dependencias importadas globalmente, por ejemplo:

- morris.js
- flexslider
- prism
- Magnific Popup
- gallery
- lightbox
- jvectormap
- x-editable
- bootstrap-markdown
- dropzone
- select2
- bootstrap-datepicker
- bootstrap-colorpicker
- bootstrap-select
- bootstrap-tagsinput
- bootstrap-touchspin
- raty
- ion-rangeSlider
- gridstack
- jquery-toast
- nestable
- bootstrap-switch
- c3
- chartist
- bootstrap-slider
- iCheck
- bootstrap-wysihtml5
- timepicker
- pace
- fullcalendar

Esto no prueba que sean 100% eliminables en todo el repo. Solo indica que no tienen evidencia directa en las pantallas `v2` auditadas y, por tanto, son candidatos fuertes a quedar fuera del futuro bundle de `v2`.

### 4. Hay duplicacion y solapamiento tecnico

- `sweetalert.css` global vs `SweetAlert2` por CDN
- `perfect-scrollbar` cargado, pero `template.js` usa `slimScroll`
- Font Awesome 4.7 local + Font Awesome 6.5.2 CDN
- DataTables local 1.10.16 + DataTables CDN 1.13.8
- daterangepicker local en CSS global + daterangepicker CDN en pantallas especificas

### 5. `v2` no esta usando el pipeline moderno que ya existe

`laravel-app` ya tiene Vite, pero `layouts.medforge` no usa `@vite(...)`.

Eso deja a `v2` atado a:

- assets copiados a mano en `public/`
- mezcla de CDN y archivos locales
- actualizaciones manuales y propensas a drift

## Dependencias activas por frecuencia

Conteo de apariciones en las vistas `v2` auditadas:

- ApexCharts local: 7 pantallas
- DataTables local: 6 pantallas
- SweetAlert2 CDN: 6 pantallas
- daterangepicker CDN: 4 pantallas
- moment CDN: 4 pantallas
- Pusher CDN: 3 pantallas
- DataTables CDN: 2 pantallas
- OwlCarousel local: 1 pantalla
- Chart.js CDN: 1 pantalla
- Pickadate CDN: 1 pantalla
- CKEditor 4 local: 1 pantalla
- jQuery Steps local: 1 pantalla
- jQuery Validation local: 1 pantalla
- Tiny Editable local: 1 pantalla
- horizontal-timeline local: 1 pantalla
- Peity local: 1 pantalla
- SortableJS CDN: 1 pantalla

## Recomendacion de migracion

### Fase 0. Congelar deuda nueva

Objetivo: dejar de empeorar el bundle compartido.

- No agregar mas librerias nuevas a `public/assets/vendor_components` para `v2`.
- No introducir mas CDN en vistas `v2` salvo bloqueos reales.
- Documentar las excepciones mientras se hace la migracion.

### Fase 1. Consistencia de bajo riesgo

Objetivo: arreglar incoherencias que hoy ya generan bugs o drift.

- Actualizar MDI.
- Elegir una sola fuente de Font Awesome.
- Alinear Bootstrap CSS y JS en la misma minor.
- Definir una sola estrategia de DataTables para `v2`.

Orden sugerido:

1. MDI
2. Font Awesome
3. Bootstrap
4. DataTables

### Fase 2. Aislar `v2` del bundle compartido

Objetivo: que `v2` deje de depender del mismo CSS/JS global que `legacy`.

Acciones:

- Crear entrypoints propios para `v2`, por ejemplo:
  - `laravel-app/resources/css/medforge.css`
  - `laravel-app/resources/js/medforge.js`
- Cambiar `layouts.medforge` para usar `@vite(...)`.
- Mantener `legacy` en sus bundles actuales mientras se migra.

Dependencias base que si vale la pena llevar a Vite en esta fase:

- `jquery`
- `bootstrap`
- `@mdi/font`
- `@fortawesome/fontawesome-free` o eliminar FA si no hace falta
- `sweetalert2`
- `pusher-js`
- `sortablejs`
- `apexcharts`

### Fase 3. Migrar por familias de pantallas

Objetivo: mover las dependencias realmente usadas por `v2`, una familia por vez.

Orden sugerido:

1. Shell compartido de `v2`
2. Dashboards
3. Tablas operativas
4. Tiempo real
5. Wizzards/editores

Detalle:

- Dashboards:
  - ApexCharts
  - Chart.js
  - OwlCarousel
- Tablas operativas:
  - DataTables
  - botones/exportes
  - RowGroup
- Tiempo real:
  - Pusher
- Wizzards y formularios:
  - jQuery Steps
  - jQuery Validation
  - Pickadate / daterangepicker
  - CKEditor

### Fase 4. Reemplazar lo que no conviene seguir arrastrando

No todas las librerias merecen "update". Algunas conviene reemplazarlas.

Candidatos claros:

- Pickadate -> `flatpickr`
- jQuery Steps -> stepper propio o libreria moderna
- OwlCarousel -> `swiper` o `embla`
- CKEditor 4 -> CKEditor 5 / Tiptap / editor definido por producto
- slimScroll + template shell heredado -> reescritura incremental del layout

### Fase 5. Podar el bundle heredado

Solo despues de aislar `v2`.

- eliminar imports innecesarios de `vendors_css.css` para el shell nuevo
- remover icon packs no usados
- dejar `legacy` con su bundle congelado o dividirlo si sigue vivo

## PRs sugeridos

### PR 1. Estabilizacion visual

- actualizar MDI
- resolver Font Awesome duplicado
- alinear Bootstrap CSS/JS

Riesgo: bajo a medio

### PR 2. Shell `v2` con Vite

- crear `medforge.css` y `medforge.js`
- mover dependencias base del shell
- dejar `legacy` intacto

Riesgo: medio

### PR 3. Estandarizar tablas y charts

- decidir una sola estrategia para DataTables
- migrar ApexCharts
- sacar CDNs faciles

Riesgo: medio

### PR 4. Migrar tiempo real y formularios complejos

- Pusher
- daterangepicker / Pickadate
- wizard quirurgico
- CKEditor

Riesgo: medio a alto

### PR 5. Limpieza del bundle heredado

- retirar imports no usados por `v2`
- bajar peso del shell nuevo

Riesgo: bajo, si `v2` ya esta aislado

## Decision recomendada

No actualizar las 74 dependencias vendorizadas "en sitio".

La recomendacion es:

1. corregir inconsistencias visibles
2. separar `v2` del bundle compartido
3. migrar las dependencias activas de `v2`
4. dejar `legacy` congelado mientras exista

Ese camino baja riesgo, ordena el frontend y evita una regresion masiva por tocar al mismo tiempo todo lo heredado.
