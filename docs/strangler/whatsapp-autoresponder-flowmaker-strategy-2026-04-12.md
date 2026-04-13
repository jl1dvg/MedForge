# Estrategia de Integración de Autorespondedor y Flowmaker

Fecha: 2026-04-12

## Decisión

El autorespondedor actual no se reemplaza de golpe.

Se conserva como runtime productivo y se convierte en la base del Flowmaker en `laravel-app`.

La migración correcta es:

- mantener el motor actual de ejecución
- conservar las tablas y versiones ya usadas en producción
- mover edición, publicación y observabilidad a Laravel
- migrar por compatibilidad, no por reinvención

## Hallazgo clave

El stack actual ya está más cerca de un Flowmaker que de un autorespondedor simple.

Hoy ya existen:

- repositorio de flujos con persistencia en tablas, settings y fallback JSON
- versionado de flujos
- estructura de pasos, acciones, transiciones, filtros y schedules
- sesiones activas de autorespuesta
- bridge desde WhatsApp hacia el motor de escenarios
- editor/publicador Flowmaker en legacy

Eso cambia la estrategia: no hay que diseñar un motor nuevo primero. Hay que encapsular y migrar el que ya funciona.

## Evidencia en el código

### Runtime actual

- [WebhookController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/WhatsApp/Controllers/WebhookController.php)
- [ScenarioEngine.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Autoresponder/Services/ScenarioEngine.php)
- [AutoresponderFlow.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/WhatsApp/Support/AutoresponderFlow.php)

El webhook actual ya usa `ScenarioEngine` y sesiones para decidir si responde, mantiene contexto o escala a humano.

### Persistencia actual

- [AutoresponderFlowRepository.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Autoresponder/Repositories/AutoresponderFlowRepository.php)

Ese repositorio ya soporta:

- `whatsapp_autoresponder_flows`
- `whatsapp_autoresponder_flow_versions`
- `whatsapp_autoresponder_steps`
- `whatsapp_autoresponder_step_actions`
- `whatsapp_autoresponder_step_transitions`
- `whatsapp_autoresponder_version_filters`
- `whatsapp_autoresponder_schedules`

Y además conserva fallback en settings y JSON por seguridad operativa.

### Editor Flowmaker actual

- [FlowmakerController.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/modules/Autoresponder/Controllers/FlowmakerController.php)

Legacy ya tiene:

- `GET /whatsapp/flowmaker`
- `GET /whatsapp/api/flowmaker/contract`
- `POST /whatsapp/api/flowmaker/publish`

### Base ya presente en Laravel

- [WhatsappAutoresponderFlow.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderFlow.php)
- [WhatsappAutoresponderFlowVersion.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderFlowVersion.php)
- [WhatsappAutoresponderStep.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderStep.php)
- [WhatsappAutoresponderStepAction.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderStepAction.php)
- [WhatsappAutoresponderStepTransition.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderStepTransition.php)
- [WhatsappAutoresponderVersionFilter.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderVersionFilter.php)
- [WhatsappAutoresponderSchedule.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderSchedule.php)
- [WhatsappAutoresponderSession.php](/Users/jorgeluisdevera/PhpstormProjects/MedForge/laravel-app/app/Models/WhatsappAutoresponderSession.php)

Laravel ya tiene el modelo de datos correcto. Lo que falta es mover runtime, publicación y UI.

## Qué se conserva

Estas piezas no deben romperse ni rehacerse sin compatibilidad:

- semántica de `ScenarioEngine`
- sesiones activas de conversación
- publicación por versiones
- fallback de persistencia
- escalado a humano desde flujo
- filtros y ventanas horarias ya interpretadas por el motor
- contrato base definido por `AutoresponderFlow`

## Qué sí se reemplaza

Estas piezas sí deben migrarse a una capa nueva en Laravel:

- UI de edición Flowmaker
- endpoints de contrato/publicación
- observabilidad del flujo
- validaciones de negocio y de publicación
- historial operativo y diagnósticos

## Arquitectura objetivo

La arquitectura correcta en `laravel-app` es:

- `Whatsapp/Automation/Runtime`
  - adapter del runtime actual
  - ejecución de sesiones
  - bridge con webhook
- `Whatsapp/Automation/Repository`
  - lectura y publicación sobre tablas actuales
  - compatibilidad con fallback legacy mientras dure la transición
- `Whatsapp/Flowmaker`
  - contrato del editor
  - publish/preview/validate
  - versiones activas y borradores
- `Whatsapp/Automation/Monitoring`
  - sesiones activas
  - motivos de fallback
  - nodos visitados
  - escalados a humano

## Estrategia de migración

### Etapa 1. Congelar el modelo canónico

Definir como modelo canónico las tablas actuales del autorespondedor y flowmaker.

No crear un segundo esquema incompatible en Laravel.

### Etapa 2. Extraer compatibilidad de runtime

Crear en Laravel un adapter que replique la entrada y salida del runtime actual:

- recibe `sender`, `text`, `message`
- resuelve sesión
- ejecuta escenario o paso
- responde mensaje o escala a humano

Mientras ese adapter no tenga paridad, legacy sigue siendo el runtime activo.

### Etapa 3. Mover publicación a Laravel

Laravel debe poder:

- leer flujo activo
- validar payload de Flowmaker
- publicar una nueva versión
- dejar la versión activa lista para ejecución

Esto debe escribir en las mismas tablas ya usadas por legacy.

### Etapa 4. Doble lectura y comparación

Antes de cortar el runtime:

- publicar desde Laravel
- leer desde legacy
- comparar estructura resuelta
- verificar que sesiones nuevas y existentes sigan comportándose igual

### Etapa 5. Cortar runtime por flag

Solo cuando haya paridad:

- `WHATSAPP_LARAVEL_AUTOMATION_ENABLED=true`
- `WHATSAPP_LARAVEL_AUTOMATION_COMPARE_WITH_LEGACY=true`
- `WHATSAPP_LARAVEL_AUTOMATION_FALLBACK_TO_LEGACY=true`

Primero en shadow mode, luego en tráfico real controlado.

## Principios de compatibilidad

- un solo flujo activo por versión publicada
- una sola fuente de verdad de sesiones activas
- publicación idempotente
- fallback explícito cuando Laravel no pueda ejecutar una rama
- logs comparables entre legacy y Laravel

## Riesgos reales

### Riesgo 1. Rehacer el motor

Sería el error más caro. Ya existe un motor funcional; rehacerlo introduce regresión innecesaria.

### Riesgo 2. Duplicar esquema

Si Laravel publica en tablas nuevas y legacy ejecuta en otras, el corte se vuelve inconsistente.

### Riesgo 3. Romper sesiones activas

Una migración sin compatibilidad de contexto puede dejar conversaciones a mitad de flujo.

### Riesgo 4. Perder fallback operativo

El fallback actual a settings/JSON y tablas es feo, pero útil. No hay que quitarlo antes de estabilizar Laravel.

## Criterio de salida para Fase 6

La fase de Flowmaker solo puede considerarse cerrada cuando:

- Laravel puede leer y publicar el flujo activo
- Laravel puede ejecutar el runtime con paridad funcional
- las sesiones activas sobreviven al cambio de runtime
- el escalado a humano se comporta igual que hoy
- existe fallback a legacy por flag

## Recomendación operativa

Orden correcto:

1. terminar `Templates` y `KPI`
2. documentar contrato exacto del runtime actual
3. construir adapter de ejecución en Laravel
4. mover publish/contract del Flowmaker
5. correr comparación con legacy
6. recién después cortar runtime

No conviene empezar por una UI bonita de nodos si todavía no está definido el runtime compatible.
