# Reglas De Facturacion IESS

Este documento resume las reglas activas que usa MedForge para el informe IESS, el consolidado por pestañas y las descargas de planos `IESS` y `IESS_SOAM`.

Su objetivo es servir como referencia para replicar estas mismas reglas en otras empresas de auditoria medica sin tener que releer el codigo.

## Alcance

Aplica a estos componentes:

- Consolidado y filtros IESS: [/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Support/InformesHelper.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Support/InformesHelper.php)
- Carga de datos del caso: [/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingInformeDataService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingInformeDataService.php)
- Exportador IESS plano y SOAM: [/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/IessSpreadsheetExportService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/IessSpreadsheetExportService.php)
- Adaptador SOAM: [/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingSoamAdapter.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingSoamAdapter.php)
- Reglas dinamicas de exclusiones SOAM: [/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingSoamRuleAdapter.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingSoamRuleAdapter.php)
- Descarga consolidada IESS y ZIP SOAM: [/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingConsolidadoExportService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Billing/Services/BillingConsolidadoExportService.php)

## Fuente De Verdad Por Caso

Para cada `form_id`, el exportador y el informe parten de `BillingInformeDataService::obtenerDatos(form_id)`.

Ese payload consolida:

- `paciente`
- `formulario`
- `visita`
- `protocoloExtendido`
- `procedimientos`
- `anestesia`
- `medicamentos`
- `oxigeno`
- `insumos`
- `derechos`
- `billing`

Para datos de derivacion se usa `obtenerDerivacionPorFormId(form_id)`.

### Merge de derivaciones

La derivacion se arma mezclando dos fuentes:

- `derivaciones_forms`
- `derivaciones_form_id`

Reglas activas del merge:

- Si `derivaciones_forms` ya tiene codigo, no se pierde el merge con `derivaciones_form_id`.
- Si el diagnostico de `derivaciones_forms` no tiene codigo CIE10 pero el legacy si lo tiene, se prefiere el legacy.
- El diagnostico final debe conservar formato `CODIGO - DETALLE`.

Ejemplo valido:

```text
H251 - CATARATA SENIL NUCLEAR
H2512 - CATARATA NUCLEAR RELACIONADA CON LA EDAD, OJO IZQUIERDO
```

## Reglas Del Consolidado IESS

El consolidado trabaja sobre facturas de `billing_main` y luego agrupa por:

- categoria
- mes
- paciente (`hc_number`)

### Filtros soportados

- mes
- hc o cedula
- apellido
- afiliacion
- sede
- con derivacion o sin derivacion
- categoria
- vista rapida o vista completa

### Agrupacion

En el agrupado por paciente se consolidan:

- lista de `form_id`
- fecha de ingreso minima
- fecha de egreso maxima
- total acumulado
- codigos de derivacion unicos
- diagnosticos acumulados para extraer CIE10
- facturadores unicos

## Reglas De Clasificacion De Pestana

Categorias validas:

- `procedimientos`
- `pni`
- `consulta`
- `imagenes`

La clasificacion actual sigue este orden.

### 1. PNI por texto

Si cualquier texto de referencia indica PNI, el caso cae en `pni`.

Textos evaluados:

- `formulario.procedimiento`
- `protocoloExtendido.membrete`
- `protocoloExtendido.membrete + lateralidad`
- `visita.procedimiento`
- `proc_detalle` de los procedimientos facturados

La deteccion considera:

- texto que empieza con `PNI`
- o contiene ` PNI `
- o `(/-PNI)` equivalentes

### 2. PNI por codigo

Hay una regla fuerte por codigo facturado. Hoy esta registrado:

- `281339`

Si aparece en `proc_codigo`, la categoria es `pni` aunque el texto no llegue al resumen agrupado.

### 3. Consulta por codigo

Codigos que fuerzan `consulta`:

- `92002`
- `92012`

### 4. Imagenes por codigo

Codigos que fuerzan `imagenes`:

- `76512`
- `92081`
- `92225`
- `281010`
- `281021`
- `281032`
- `281229`
- `281186`
- `281197`
- `281230`
- `281306`
- `281295`

### 5. Fallback por texto

Si no hubo match por reglas fuertes:

- si `procedimiento` empieza con `imagenes`, categoria `imagenes`
- si `formulario.tipo` contiene `imagen`, categoria `imagenes`
- en cualquier otro caso, categoria `procedimientos`

## Reglas De CIE10

La columna `CIE10` del consolidado y del SOAM no guarda el texto completo; extrae el codigo desde `diagnostico`.

Formato esperado:

```text
CODIGO - DETALLE; CODIGO - DETALLE
```

Ejemplos validos:

- `H251 - CATARATA SENIL NUCLEAR`
- `H2512 - CATARATA NUCLEAR RELACIONADA CON LA EDAD, OJO IZQUIERDO`
- `E11.3 - RETINOPATIA DIABETICA`

Regex aceptado actualmente:

- letra inicial
- dos digitos base
- hasta cuatro caracteres alfanumericos extra
- decimal opcional

### Comportamiento

- En el consolidado se extraen todos los codigos y se muestran separados por coma.
- En el SOAM se toma el primer diagnostico del string y se exporta solo su codigo.

## Reglas De Totales

La sumatoria de factura del consolidado aplica:

### Procedimientos

- Primer procedimiento: 100%
- Procedimientos secundarios: 50%
- Si `proc_detalle` contiene `separado`: 100%
- Caso especial `67036`: 62.5%

### Segundo cirujano / ayudante

Si hay `cirujano_2` o `primer_ayudante`:

- procedimiento principal: +20%
- secundarios: +10%

### Anestesia

- suma `valor2 * tiempo`
- adicionalmente se agrega el valor anestesico del procedimiento principal
- si existe tarifario especial de anestesia por codigo, ese valor prevalece

### Insumos y farmacia

- `medicamentos` y `oxigeno`: grupo `FARMACIA`
- `insumos`: grupo `INSUMOS`
- farmacia no suma IVA
- insumos suma 10% IVA

### Derechos

- suma `precio_afiliacion * cantidad`

## Reglas De Fechas

### En consolidado

- `fecha_ingreso`: minima fecha del grupo
- `fecha_egreso`: maxima fecha del grupo

### En exportacion IESS/SOAM

`fecha_facturacion`:

- cirugia: `formulario.fecha_fin` o `fecha_inicio` o `protocolo.fecha_inicio`
- no cirugia: `visita.fecha`

`fecha_ingreso_global` y `fecha_egreso_global`:

- se calculan sobre todos los `form_id` del lote
- primero desde `protocolo_data.fecha_inicio`
- si no existe, desde `procedimiento_proyectado.fecha`

## Reglas De Derivacion

Campos usados:

- codigo derivacion
- fecha registro
- fecha vigencia
- referido
- diagnostico
- sede
- parentesco
- ruta de archivo de derivacion

### Fuente Sigcenter

La sincronizacion de derivaciones usa consultas SQL a Sigcenter por `doc_solicitud_procedimientos.id`.

Se recupera:

- `cod_derivacion`
- `num_secuencial_derivacion`
- `fecha_registro`
- `fecha_vigencia`
- `referido`
- `procedencia`
- `parentesco`
- `sede`
- `afiliacion`
- diagnostico CIE10
- ruta real del PDF de derivacion

### PDF de derivacion

La ruta final puede vivir en:

- `storage/derivaciones`
- `/var/www/html/GOOGLE/frontend/web/data`

Si no existe localmente, el endpoint intenta leerlo por SFTP desde Sigcenter.

## Reglas Del Export IESS Plano

El plano IESS usa `44` columnas.

### Filas generadas

Por cada caso se generan filas para:

- procedimientos
- segundo cirujano / ayudante
- anestesia
- insumos
- farmacia
- derechos

### Tipo de fila en plano

`resolveProcedureType()`:

- si no es SOAM:
  - cirugia => `PRO/INTERV`
  - no cirugia => `IMAGEN`

### Reglas especiales

- `67036` genera dos filas con total `proc_precio * 0.625`
- si existe `67036`, el resto de procedimientos usa 50%
- para no cirugia solo se exporta el primer procedimiento facturado

### Campos importantes

- codigo derivacion: columna de derivacion
- CIE10: extraido del primer diagnostico del string
- afiliacion: se exporta abreviada

## Reglas Del Export SOAM

El SOAM usa `33` columnas.

### Tipos de prestacion

En SOAM:

- anestesia => `AMB`
- procedimientos con codigos de imagen => `IMA`
- otros procedimientos => `AMB`
- farmacia => `FAR`
- insumos => `IMM`

Codigos tratados como imagen en SOAM:

- `76512`
- `92081`
- `92225`
- `281010`
- `281021`
- `281032`
- `281229`
- `281186`
- `281197`
- `281230`
- `281306`
- `281295`

### Abreviacion de afiliacion

Mapa actual:

- `contribuyente voluntario` => `SV`
- `conyuge` => `CY`
- `conyuge pensionista` => `CJ`
- `seguro campesino` => `CA`
- `seguro campesino jubilado` => `JC`
- `seguro general` => `SG`
- `seguro general jubilado` => `JU`
- `seguro general por montepio` => `MO`
- `seguro general tiempo parcial` => `SG`

### Valores fijos relevantes

- empresa: `0000000135`
- origen derivacion: `CVA`
- estado derivacion: `D`
- clase documento: `T`
- campo fijo final: `F`

## Reglas Dinamicas SOAM

Existe una capa de reglas dinamicas basada en BD:

- tabla `reglas`
- tabla `condiciones`
- tabla `acciones`

Contexto evaluado:

- `afiliacion`
- `procedimiento`
- `edad`

Operadores soportados:

- `=`
- `LIKE`
- `IN`

Hoy el exportador las usa principalmente para:

- excluir insumos si una accion de tipo `excluir_insumo` hace match con la descripcion

## Descarga De Consolidados

### Consolidado simple IESS

- para IESS usa exportador propio
- para otros grupos usa export legacy/adapters

### Consolidado SOAM IESS

Puede descargarse:

- solo Excel
- o ZIP

Si se pide ZIP:

- incluye el Excel SOAM
- intenta agregar PDFs de consulta por cada `form_id + hc_number`

## Puntos Criticos Para Replicar En Otra Empresa

Si se quiere clonar este modelo para otra auditora, no basta con copiar el Excel. Hay que replicar:

1. Fuente canónica del caso por `form_id`
2. Merge de derivaciones entre tabla nueva y legacy
3. Clasificacion por categoria antes del agrupado
4. Extraccion de CIE10 desde `diagnostico`
5. Reglas de ponderacion economica
6. Tipologia de filas del plano y del SOAM
7. Reglas dinamicas de exclusion
8. Mapa de afiliacion abreviada
9. Resolucion de PDF de derivacion

## Recomendaciones Para Otra Empresa

- Mantener una lista explicita de codigos PNI, imagenes y consulta por empresa.
- No depender solo del texto `procedimiento_proyectado`.
- Persistir siempre diagnostico en formato `CODIGO - DETALLE`.
- Separar reglas clinicas, de clasificacion y de export en adapters distintos.
- Evitar leer directamente de UI o HTML; todo debe salir de BD consolidada.

## Pendientes Naturales

Estos puntos todavia conviene parametrizarlos si se quiere escalar:

- lista completa de codigos PNI
- reglas por empresa de seguro
- reglas SOAM especificas por auditora
- excepciones de IVA por tipo de item
- listas de tipos de prestacion por codigo
