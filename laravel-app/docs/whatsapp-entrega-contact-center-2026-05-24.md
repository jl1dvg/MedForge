# Entrega formal: mejoras operativas WhatsApp

Fecha: 24 de mayo de 2026  
Área: Contact Center y agentes WhatsApp  
Sistema: MedForge WhatsApp V2  
Cambios incluidos: PR #230 y PR #231

## 1. Objetivo de la entrega

Esta entrega mejora la forma en que el equipo atiende conversaciones de WhatsApp para reducir confusión operativa, ordenar mejor la cola de trabajo y separar claramente los casos resueltos de los casos cerrados para seguimiento comercial.

Los cambios buscan que cada agente pueda responder tres preguntas rápidamente:

- Qué conversación debo atender primero.
- Qué conversaciones son mías.
- Qué casos ya están cerrados, resueltos o enviados a seguimiento.

## 2. Cambios principales

### 2.1. “Dar de baja” cambia a “Cerrar seguimiento”

La acción visible “Dar de baja” fue reemplazada por “Cerrar seguimiento”.

Este cambio no elimina al paciente ni borra el historial. Su función es cerrar la conversación activa y generar un lead de seguimiento para que el caso siga existiendo dentro del proceso comercial.

Uso recomendado:

- Usar “Resolver” cuando el caso quedó solucionado.
- Usar “Cerrar seguimiento” cuando la conversación debe salir de la bandeja activa, pero debe conservarse como lead para seguimiento.
- No usar “Cerrar seguimiento” como sinónimo de “Resuelto”.

Qué ocurre al cerrar seguimiento:

- La conversación sale de las bandejas activas.
- Se genera o actualiza el lead de WhatsApp.
- Se conserva el historial de conversación.
- Se registran métricas separadas de seguimiento.

### 2.2. Separación entre Resuelto y Cerrar seguimiento

Antes ambos conceptos podían confundirse. Ahora quedan separados:

| Acción | Significado | Impacto |
| --- | --- | --- |
| Resolver | El caso fue atendido y cerrado correctamente | Cuenta como resuelto real |
| Cerrar seguimiento | El caso pasa a seguimiento comercial | Cuenta como cerrado para seguimiento y genera lead |
| No interesado | El paciente indicó que no desea continuar | Cuenta como cierre por motivo |
| Sin respuesta | El paciente no respondió | Cuenta como cierre por motivo |

## 3. Nuevas bandejas operativas

Las bandejas principales ahora están organizadas por necesidad real de atención.

### Requieren atención

Conversaciones que necesitan acción humana y todavía no tienen un agente responsable.

Prioridad operativa:

- Esta debe ser la primera bandeja que revise el equipo.
- Si una conversación aparece aquí, alguien debe tomarla o asignarla.

### Mis chats

Conversaciones asignadas al agente que está usando el sistema.

Importante:

- “Mis chats” no es un estado del paciente.
- Es una vista personal de ownership.
- Sirve para que cada agente vea su carga activa.

### En gestión

Conversaciones asignadas donde el paciente fue el último en escribir.

Prioridad operativa:

- Son conversaciones que probablemente requieren respuesta del agente.
- Deben atenderse antes que “Esperando paciente”.

### Esperando paciente

Conversaciones asignadas donde el equipo ya respondió y se espera una respuesta del paciente.

Uso recomendado:

- No deben mezclarse con casos pendientes de respuesta del agente.
- Revisar periódicamente si llevan demasiado tiempo sin respuesta.

### Agendados

Conversaciones con cita registrada.

Uso recomendado:

- Sirve para diferenciar casos que ya avanzaron a cita.
- No deben tratarse como pendientes generales.

### Cerrados

Conversaciones que ya tienen cierre estructurado.

Incluye:

- Resueltos.
- Cerrados para seguimiento.
- No interesados.
- Sin respuesta.
- Duplicados.
- Agendados por otro canal.

## 4. Filtros avanzados

Los filtros avanzados quedan disponibles para supervisión o búsqueda más específica.

Filtros disponibles:

- Captación.
- Operación.
- Información.
- Sin leer.
- 24h abierta.
- Requiere plantilla.
- Backlog >24h.
- Todos.
- Resueltos.

Uso recomendado:

- Los agentes deben trabajar principalmente con las bandejas principales.
- Supervisores pueden usar filtros avanzados para auditoría, revisión o control de casos específicos.

## 5. Prioridad calculada

El sistema ahora calcula una prioridad para ayudar a ordenar la atención.

La prioridad toma en cuenta factores como:

- Si el paciente escribió último.
- Si hay mensajes sin leer.
- Si la conversación no tiene agente asignado.
- Tiempo en cola.
- Riesgo de cierre de ventana de 24 horas.
- Backlog o demora operativa.

Niveles de prioridad:

| Prioridad | Significado |
| --- | --- |
| Crítica | Debe revisarse primero |
| Alta | Requiere atención pronta |
| Normal | Atención regular |
| Baja | No requiere acción inmediata |

Orden de conversaciones:

1. Mayor prioridad.
2. Mensajes sin leer.
3. Última actividad.
4. ID de conversación como desempate.

## 6. Último actor visible

Cada conversación muestra quién escribió el último mensaje:

- Paciente.
- Equipo/Bot.
- Bot/plantilla.
- Sin mensajes.

Esto ayuda a evitar confusión sobre quién debe responder.

Regla práctica:

- Si el último actor es “Paciente”, normalmente el equipo debe revisar.
- Si el último actor es “Equipo/Bot”, normalmente se está esperando al paciente.

## 7. Motivos de cierre

El motivo de cierre solo aplica cuando la conversación ya está cerrada.

Motivos actuales:

- Resuelto.
- Cerrado para seguimiento.
- No interesado.
- Sin respuesta.
- Duplicado.
- Agendado por otro canal.

Importante:

- El motivo de cierre no debe confundirse con la bandeja actual.
- Una conversación activa no debería depender de un motivo de cierre.

## 8. Dashboard operativo

El dashboard ahora separa mejor los resultados operativos.

Métricas agregadas o reforzadas:

- Resueltos reales.
- Cerrados para seguimiento.
- Leads WhatsApp generados.
- Requieren atención.
- En gestión.
- Esperando paciente.
- Agendados.
- Prioridad crítica.
- Casos en limbo sin agente.

Uso recomendado para supervisores:

- Revisar “Requieren atención” durante el día.
- Revisar “Prioridad crítica” para evitar acumulación.
- Revisar “Limbo sin agente” para detectar conversaciones sin responsable.
- Separar “Resueltos reales” de “Cerrados para seguimiento” en análisis de rendimiento.

## 9. Flujo operativo recomendado

### Para agentes

1. Entrar a “Mis chats”.
2. Responder primero las conversaciones con prioridad crítica o alta.
3. Revisar “En gestión” para responder pacientes que escribieron último.
4. Usar “Resolver” solo cuando el caso quedó terminado.
5. Usar “Cerrar seguimiento” solo cuando se debe generar lead y sacar el caso de la bandeja activa.

### Para supervisores

1. Revisar “Requieren atención”.
2. Asignar o pedir que se tomen conversaciones sin agente.
3. Revisar “Prioridad crítica”.
4. Auditar “Esperando paciente” para detectar casos dormidos.
5. Revisar dashboard al cierre del día.

## 10. Casos frecuentes

### ¿Cerrar seguimiento elimina al paciente?

No. El paciente y el historial se conservan.

### ¿Cerrar seguimiento sigue aportando a leads de WhatsApp?

Sí. Esa acción genera o actualiza el lead de seguimiento.

### ¿Resolver genera lead?

No necesariamente. Resolver indica que el caso fue atendido y cerrado correctamente.

### ¿Mis chats es una bandeja de estado?

No. “Mis chats” muestra conversaciones asignadas al agente actual.

### ¿Por qué una conversación aparece primero?

Porque el sistema ordena por prioridad, mensajes sin leer y última actividad.

### ¿Qué hago si una conversación está en “Requieren atención”?

Debe ser tomada o asignada a un agente.

## 11. Buenas prácticas

- No cerrar como resuelto si el paciente aún espera una respuesta.
- No usar “Cerrar seguimiento” para casos realmente solucionados.
- Revisar “En gestión” antes de “Esperando paciente”.
- Usar el motivo de cierre correcto cuando aplique.
- No dejar conversaciones en “Requieren atención” sin responsable.
- Escalar a supervisor si un caso aparece crítico y no puede resolverse.

## 12. Validación técnica realizada

Se validaron pruebas sobre:

- Listado de conversaciones.
- Nuevas bandejas operativas.
- Estados calculados.
- Prioridad calculada.
- Cierre resuelto.
- Cierre para seguimiento.
- Métricas del dashboard.

PRs incluidos:

- PR #230: Separar cierre de seguimiento, métricas operativas y orden del inbox de WhatsApp.
- PR #231: Agregar bandejas operativas y prioridad al inbox de WhatsApp.

## 13. Mensaje sugerido para comunicar al equipo

Desde ahora el dashboard de WhatsApp separa mejor las conversaciones activas, las asignadas a cada agente, las que esperan respuesta del paciente, los agendados y los cerrados.  

La acción “Dar de baja” fue reemplazada por “Cerrar seguimiento”. Esta acción no elimina pacientes ni borra historiales; sirve para cerrar la conversación activa y generar un lead de seguimiento.  

Para el trabajo diario, prioricen “Requieren atención”, “Mis chats” y “En gestión”. Los supervisores deben revisar prioridad crítica y casos en limbo sin agente.
