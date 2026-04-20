# Manual de usuario: Flowmaker WhatsApp V2

## Objetivo

Flowmaker sirve para diseñar y mantener el flujo automático de WhatsApp sin editar código.

Con Flowmaker puedes:

- crear escenarios
- definir cuándo aplica cada escenario
- configurar mensajes, botones, listas y templates
- cambiar estados del paciente o de la conversación
- derivar a un agente humano
- probar el flujo antes de publicarlo

## Idea general

Un flujo se organiza así:

1. **Escenario**
   - representa una situación del paciente
   - ejemplo: primer contacto, captura de cédula, acceso al menú, ayuda

2. **Condiciones**
   - definen cuándo ese escenario hace match
   - ejemplo: si el paciente escribe `hola`, si está en estado `menu_principal`, si es primera vez

3. **Acciones**
   - lo que el sistema hace cuando el escenario aplica
   - ejemplo: enviar mensaje, enviar botones, cambiar estado, derivar a agente

4. **Transiciones**
   - documentan la siguiente salida esperada del escenario
   - ayudan a entender la ruta del flujo

## Pantalla principal

La pantalla de Flowmaker tiene cuatro zonas importantes:

### 1. Encabezado superior

- **Ver contrato JSON**
  - abre el contrato actual del flujo
  - útil para soporte o validación técnica

- **Publicar JSON**
  - convierte el draft actual en la nueva versión activa
  - los cambios no quedan activos hasta usar este botón

### 2. KPIs superiores

Muestran el estado operativo:

- versión activa
- cantidad de escenarios
- sesiones activas
- filtros, schedules y transiciones

### 3. Columna izquierda: Escenarios

Aquí ves la lista de escenarios del flujo.

Desde esta columna puedes:

- buscar un escenario
- seleccionar uno para editarlo
- crear uno nuevo con el botón **Nuevo**

### 4. Panel derecho: Editor del escenario

Muestra:

- datos base del escenario
- condiciones
- acciones
- transiciones

## Cómo editar un escenario

Cuando seleccionas un escenario, el editor muestra el bloque **Nodo base del escenario**.

Ahí puedes modificar:

- **ID**
  - identificador interno
  - conviene no cambiarlo si el escenario ya está en uso

- **Nombre**
  - nombre visible del escenario

- **Stage**
  - categoría funcional del escenario
  - ejemplos: `arrival`, `validation`, `menu`, `custom`

- **Estado**
  - controla si el escenario entra o no al runtime

- **Intercepta menú**
  - indica si el escenario puede abrir o interceptar el menú principal

- **Descripción**
  - explicación funcional del escenario

## Estados del escenario

Cada escenario puede estar en uno de estos estados:

### Publicado

- entra al runtime
- participa en la versión publicada
- puede responder en producción

### Borrador

- no entra al runtime
- sirve para seguir construyendo el escenario sin activarlo

### Pausado

- no entra al runtime
- sirve para desactivar temporalmente un escenario sin borrarlo

## Importante sobre activar o desactivar

Cambiar el campo **Estado** no activa el cambio por sí solo.

Después de mover un escenario a `Borrador` o `Pausado`, debes hacer:

1. cambiar el estado
2. revisar el flujo
3. pulsar **Publicar JSON**

Solo después de publicar, ese escenario deja de estar activo en producción.

## Cómo crear un escenario nuevo

1. pulsa **Nuevo**
2. Flowmaker crea el escenario en estado `Borrador`
3. ponle nombre y descripción
4. define sus condiciones
5. agrega las acciones
6. si ya está listo, cambia el estado a `Publicado`
7. pulsa **Publicar JSON**

## Cómo duplicar un escenario

Dentro del escenario existe el botón **Duplicar**.

Úsalo cuando:

- quieres reutilizar una lógica parecida
- quieres hacer una variante sin tocar el escenario original

La copia se crea en `Borrador`, para evitar que se active por accidente.

## Cómo eliminar un escenario

Usa el botón **Eliminar** dentro del escenario.

Recomendación:

- si no estás seguro, usa `Pausado` antes de eliminar
- eliminar es más riesgoso que desactivar

## Cómo funcionan las condiciones

El bloque **Nodo de condiciones** define cuándo entra el escenario.

Ejemplos de uso:

- el paciente escribe `hola`
- el estado actual es `menu_principal`
- el paciente ya dio consentimiento
- la conversación está esperando cédula

Buenas prácticas:

- usa pocas condiciones y que sean claras
- evita reglas demasiado parecidas entre escenarios
- si dos escenarios compiten por la misma entrada, puede ser difícil entender cuál ganó

## Cómo funcionan las acciones

El bloque **Secuencia de acciones** define qué hace el sistema.

Las acciones se ejecutan en orden, de arriba hacia abajo.

Puedes reordenarlas con:

- **Subir**
- **Bajar**

## Tipos de acciones disponibles

### 1. Enviar mensaje

Sirve para enviar texto simple.

Uso recomendado:

- saludos
- confirmaciones
- indicaciones breves

### 2. Enviar botones

Sirve para mostrar opciones rápidas.

La acción incluye:

- texto del mensaje
- lista de botones
- cada botón tiene:
  - título
  - id

Ejemplo:

- mensaje: `¿Qué deseas hacer?`
- botones:
  - `Agendar`
  - `Resultados`
  - `Hablar con asesor`

Recomendaciones:

- usa textos cortos
- mantén botones claros y distintos
- el `id` debe ser estable y entendible

### 3. Enviar lista

Sirve para mostrar un menú más largo.

La acción incluye:

- texto del mensaje
- texto del botón que abre la lista
- título de sección
- filas u opciones

Cada fila tiene:

- título
- id
- descripción

Úsala cuando tienes más opciones que las que conviene mostrar en botones.

### 4. Enviar template

Sirve para elegir una plantilla de WhatsApp ya existente.

La acción muestra:

- selector de template
- nombre visible

Úsala cuando el mensaje depende de plantillas aprobadas.

### 5. Cambiar estado

Guarda un nuevo estado de conversación.

Ejemplo:

- `consentimiento_pendiente`
- `esperando_cedula`
- `menu_principal`

Esto es importante porque muchos escenarios dependen del estado previo.

### 6. Guardar contexto

Sirve para conservar datos útiles del paciente o de la conversación.

Ejemplo:

- campo que se está esperando
- selección temporal
- resultado de una validación

### 7. Guardar consentimiento

Se usa cuando el paciente autoriza tratamiento de datos o uso de información protegida.

### 8. Derivar a agente

Manda el caso a atención humana.

Se usa cuando:

- el paciente pide ayuda
- el flujo no tiene suficiente contexto
- se necesita intervención operativa

### 9. AI Agent

Usa la base de conocimiento y reglas del agente para sugerir respuesta o handoff.

Incluye:

- instructions
- fallback message
- thresholds
- filtros de Knowledge Base

## Cómo publicar correctamente

Antes de pulsar **Publicar JSON**, revisa:

1. que los escenarios en prueba estén en `Borrador` o `Pausado`
2. que solo los escenarios listos estén en `Publicado`
3. que las condiciones no se pisen entre sí
4. que los botones y listas tengan ids correctos
5. que los cambios de estado estén bien definidos

Después:

1. pulsa **Publicar JSON**
2. revisa el mensaje de confirmación
3. prueba el flujo desde la simulación

## Cómo probar un flujo

Usa el bloque **Simulación operativa**.

Ahí puedes ingresar:

- número
- mensaje
- contexto JSON opcional

Esto sirve para verificar:

- qué escenario hace match
- qué acciones ejecuta
- si pide handoff
- cuál queda como estado final

## Recomendaciones operativas

### Para cambios pequeños

- duplica el escenario
- trabaja la copia en `Borrador`
- prueba
- publica solo cuando esté listo

### Para apagar algo temporalmente

- usa `Pausado`
- publica

### Para trabajo en construcción

- usa `Borrador`

### Para mensajes con opciones

- usa **botones** si son pocas opciones
- usa **lista** si son varias opciones

### Para escenarios críticos

- documenta bien la descripción
- usa nombres claros
- evita ids ambiguos

## Errores comunes

### Cambié algo pero no pasó nada

Probablemente faltó pulsar **Publicar JSON**.

### El escenario aparece, pero no responde

Revisa:

- condiciones
- estado del escenario
- orden de prioridad frente a otros escenarios

### El escenario está en Borrador pero sigue activo

Seguramente todavía no has publicado la nueva versión.

### No aparece el template esperado

Revisa si la plantilla existe en el catálogo y si ya está disponible para Flowmaker.

### La lista o los botones no se comportan bien

Revisa:

- ids repetidos
- títulos vacíos
- orden de acciones

## Qué conviene mejorar a futuro

Si el objetivo es acercarse a una experiencia tipo Flowbuilder de WhatsBox, las mejoras más valiosas serían:

1. editor visual con flechas entre escenarios
2. activación/desactivación con switch visible
3. condiciones en lenguaje más natural
4. selector visual de transiciones
5. menos campos técnicos y menos JSON visible
6. vista de mapa completa del flujo

## Resumen corto

- **Publicado**: activo
- **Borrador**: no activo
- **Pausado**: no activo
- cambiar el estado no basta: hay que **Publicar JSON**
- usa simulación antes de publicar
- usa botones y listas para evitar respuestas ambiguas

