# Checklist Operativo WhatsApp V2

## Objetivo
Poner en operación WhatsApp V2 para:

- Dashboard
- Plantillas
- Campañas
- Flowmaker

Manteniendo el chat en legacy:

- Chat de WhatsApp: `/whatsapp/chat`

## Regla operativa

### Se usa en V2
- Dashboard WhatsApp: `/v2/whatsapp/dashboard`
- Plantillas de WhatsApp: `/v2/whatsapp/templates`
- Campañas WhatsApp: `/v2/whatsapp/campaigns`
- Flowmaker WhatsApp: `/v2/whatsapp/flowmaker`

### Se mantiene en legacy
- Chat de WhatsApp: `/whatsapp/chat`

## Bloque 1. Navegación y acceso

### Debe verse en el menú
- Chat de WhatsApp
- Dashboard WhatsApp
- Campañas WhatsApp
- Plantillas de WhatsApp
- Flowmaker WhatsApp

### No debe verse como acceso operativo principal
- WhatsApp V2
- Dashboard WhatsApp legacy
- Plantillas legacy
- Bot/autoresponder legacy como opción principal de operación

### Validación
- Entrar con un usuario real con permisos operativos.
- Confirmar que cada enlace abre la ruta correcta.
- Confirmar que `Dashboard WhatsApp` abre `/v2/whatsapp/dashboard`.
- Confirmar que `Plantillas de WhatsApp` abre `/v2/whatsapp/templates`.
- Confirmar que `Campañas WhatsApp` abre `/v2/whatsapp/campaigns`.
- Confirmar que `Flowmaker WhatsApp` abre `/v2/whatsapp/flowmaker`.
- Confirmar que `Chat de WhatsApp` abre `/whatsapp/chat`.

### Criterio de salida
- Ningún usuario operativo entra por error a una pantalla legacy distinta del chat.

## Bloque 2. Dashboard V2

### Validación mínima
- Abrir dashboard sin errores.
- Filtrar por rango de fechas.
- Probar filtro por agente.
- Probar filtro por rol.
- Probar drilldown.
- Probar exportación CSV.

### Revisar
- KPIs visibles.
- Totales coherentes con datos reales.
- Sin errores JS en consola.
- Sin errores 500 en red.

### Criterio de salida
- El dashboard permite seguimiento operativo sin volver al dashboard legacy.

## Bloque 3. Plantillas V2

### Validación mínima
- Ver listado.
- Filtrar por estado, categoría y lenguaje.
- Crear plantilla.
- Editar plantilla.
- Clonar plantilla.
- Publicar plantilla.
- Sincronizar con Meta.

### Revisar
- Variables visibles y entendibles.
- Preview coherente.
- Media header funcionando si aplica.
- Estados publicados/borrador consistentes.

### Criterio de salida
- El equipo puede administrar plantillas solo desde V2.

## Bloque 4. Campañas V2

### Validación mínima
- Crear campaña.
- Seleccionar template.
- Probar sugerencias de audiencia.
- Ejecutar dry-run.
- Confirmar estados de campaña.

### Revisar
- Mensajes de validación claros.
- No permitir campañas inválidas sin feedback.
- Sin errores 500 ni bloqueos de UI.

### Criterio de salida
- El equipo puede preparar campañas reales sin depender de legacy.

## Bloque 5. Flowmaker V2

### Validación mínima
- Abrir escenarios.
- Editar condiciones.
- Editar acciones.
- Simular escenario.
- Publicar flujo.
- Confirmar que solo escenarios `Publicado` entren al runtime.

### Revisar
- Estado de escenario claro: `Publicado`, `Borrador`, `Pausado`.
- Botones, listas y templates con editor usable.
- Navegación entre escenarios clara.
- Sin errores JS al cargar editor.

### Criterio de salida
- El equipo puede mantener el flujo principal sin volver al flowmaker legacy.

## Bloque 6. Knowledge Base mínima

### Documentos mínimos a cargar
- FAQ de agendamiento
- Sedes y horarios
- Seguros
- Consentimiento y uso de datos
- Preoperatorio
- Postoperatorio

### Revisar
- Todos en estado `published`.
- Metadatos coherentes:
  - sede
  - tipo_contenido
  - audiencia
  - especialidad, si aplica

### Criterio de salida
- El `ai_agent` deja de caer sistemáticamente en `no_grounding`.

## Bloque 7. Permisos

### Validación mínima
- Perfil operativo normal
- Perfil supervisor
- Perfil administrativo

### Revisar
- Quién puede ver dashboard
- Quién puede operar templates
- Quién puede operar campañas
- Quién puede publicar flowmaker
- Quién solo puede usar chat legacy

### Criterio de salida
- Cada perfil ve solo lo que necesita y no hay accesos rotos.

## Bloque 8. Criterios de salida a operación

Se considera listo el corte cuando se cumple todo esto:

- Navegación correcta
- Chat sigue en legacy
- Dashboard operativo en V2
- Plantillas operativas en V2
- Campañas operativas en V2
- Flowmaker operativo en V2
- Knowledge Base mínima cargada
- Permisos verificados
- Sin errores 500 visibles en operación básica
- Sin errores JS bloqueantes en navegador

## Recomendación de despliegue

### Día 1
- Corregir navegación final
- Validar rutas con usuario real
- Validar Dashboard y Plantillas

### Día 2
- Validar Campañas y Flowmaker
- Cargar Knowledge Base mínima

### Día 3
- Comunicar corte operativo
- Operar V2 para todo salvo chat

## Comunicación sugerida al equipo

Desde esta fase:

- El chat sigue usándose en `Chat de WhatsApp`.
- El dashboard de WhatsApp se usa en V2.
- Las plantillas de WhatsApp se gestionan en V2.
- Las campañas de WhatsApp se gestionan en V2.
- El Flowmaker se mantiene en V2.

Si algo falla fuera del chat, se reporta como incidencia de V2 y no se vuelve automáticamente a legacy sin revisar causa.
