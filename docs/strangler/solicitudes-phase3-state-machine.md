# Solicitudes Phase 3: State Machine

Fecha: 2026-04-22
Estado: `iniciado`
Objetivo: convertir el estado operativo de `Solicitudes` en una máquina de estados nativa de Laravel, sin depender conceptualmente de `legacyState`.

## 1. Alcance

Esta fase cubre:

- estado operativo mostrado en kanban;
- checklist operativo;
- transición por conciliación;
- relación entre checklist y `completado`;
- relación entre tareas CRM y estado operativo.

Esta fase no cubre todavía:

- Mailbox;
- WhatsApp V2;
- reemplazo total de módulos legacy no relacionados con `Solicitudes`.

## 2. Problema actual

Hoy la lógica de estado sigue mezclando:

- `solicitud_procedimiento.estado` como insumo heredado;
- `solicitud_checklist` como progreso operativo;
- `crm_tasks` como capa operativa paralela;
- conciliación como escritura que además altera checklist/estado.

Eso produce una semántica incompleta:

- el kanban y el checklist suelen coincidir, pero todavía no nacen de una fuente de verdad explícita;
- `crm_tasks` no es equivalente al estado operativo;
- `completado` se resuelve por reglas heredadas y no por una máquina de estados documentada.

## 3. Fuente de verdad objetivo

La fuente de verdad debe quedar así:

- `estado_operativo`:
  - `recibida`
  - `llamado`
  - `revision-codigos`
  - `espera-documentos`
  - `apto-oftalmologo`
  - `apto-anestesia`
  - `listo-para-agenda`
  - `programada`
  - `completado`
- `checklist_operativo`:
  - representación detallada de cada etapa y su evidencia;
- `crm_tasks`:
  - apoyo operativo, no fuente de verdad del estado;
- `pipeline_crm`:
  - vista comercial/seguimiento, no reemplazo del estado operativo.

## 4. Reglas objetivo

Reglas obligatorias:

- cada etapa del checklist corresponde a una etapa operativa;
- el kanban se deriva del checklist/estado operativo nativo;
- `completado` implica checklist 100% completado;
- conciliación confirmada puede mover a `completado` bajo regla explícita;
- una tarea CRM no cambia el estado por sí sola, salvo que esté mapeada a una transición formal.

## 5. Entregables de la fase

1. Documento de transición por etapas.
2. Matriz evento -> transición.
3. Refactor en servicios:
   - [SolicitudesWriteParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesWriteParityService.php)
   - [SolicitudesReadParityService.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Modules/Solicitudes/Services/SolicitudesReadParityService.php)
4. Eliminación del uso de `legacyState` como base del cálculo operativo.
5. Validación funcional en:
   - kanban;
   - checklist CRM;
   - conciliación;
   - estado `completado`.

## 6. Primer corte técnico

El primer corte técnico de Fase 3 debe hacer esto:

- centralizar el mapa de etapas operativas en una sola clase/servicio;
- definir la función única que resuelve:
  - etapa actual;
  - siguiente etapa;
  - porcentaje completado;
  - condición de completado total;
- hacer que lectura y escritura reutilicen esa misma función.

## 7. Criterio de cierre

Fase 3 se considera cerrada cuando:

- el estado operativo ya no parte de `legacyState`;
- kanban y checklist salen de la misma resolución nativa;
- conciliación usa reglas explícitas de transición;
- `completado` queda formalizado como estado terminal del flujo operativo.
