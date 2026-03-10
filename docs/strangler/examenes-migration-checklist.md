# Checklist de migración completa: Exámenes (Legacy → Laravel)

Objetivo: declarar el módulo **100% migrado** solo cuando backend y frontend estén resueltos en Laravel sin dependencia operativa del runtime legacy.

## Estado actual (referencia)

- Estrategia activa: strangler/parity (Laravel + bridge legacy).
- UI v2 disponible, pero con render de vistas legacy embebidas.
- Endpoints críticos de lectura/escritura ya tienen rutas en Laravel con fallback a legacy.

---

## 1) Backend (Laravel nativo)

### 1.1 Endpoints y contratos

- [ ] `kanban-data` servido por servicio Laravel sin bridge.
- [ ] `turnero-data` servido por servicio Laravel sin bridge.
- [ ] `actualizar-estado` (kanban/turnero) servido por servicio Laravel.
- [ ] `api/estado` GET/POST servido por servicio Laravel.
- [ ] CRM de examen (`resumen`, `guardar`, checklist, notas, tareas, bloqueo, adjuntos) servido por servicios Laravel.
- [ ] Exportes/reporte (`pdf`, `excel`) sin invocar controlador legacy.
- [ ] Derivación (`detalle`, `preselección`, `guardar`) sin bridge.

### 1.2 Datos y reglas de negocio

- [ ] Queries principales viven en repos/services Laravel (sin `Modules\\Examenes\\Models` legacy).
- [ ] Reglas de estado/cobertura centralizadas en clases Laravel.
- [ ] Realtime (Pusher) disparado desde capa Laravel.
- [ ] Validaciones y códigos HTTP equivalentes al comportamiento legacy esperado.

### 1.3 Seguridad y permisos

- [ ] Middlewares `legacy.auth` / `legacy.permission` cubren toda superficie v2.
- [ ] No hay endpoints de escritura sin validación de sesión/permiso.

---

## 2) Frontend (Laravel nativo)

### 2.1 Vistas

- [ ] `resources/views/examenes/v2-index.blade.php` sin `include` a `modules/examenes/views/examenes.php`.
- [ ] `resources/views/examenes/v2-turnero.blade.php` sin `include` a `modules/examenes/views/turnero.php`.
- [ ] Componentes/modales de Kanban implementados en Blade/partials Laravel.

### 2.2 JS/CSS

- [ ] JS de Exámenes desacoplado de globals legacy críticos.
- [ ] Entrypoints v2 documentados y versionados.
- [ ] Flujos de UI (drag/drop, filtros, CRM panel, turnero, reportes) probados en v2.

### 2.3 UX/paridad funcional

- [ ] Paridad visual suficiente con legacy (aceptada por negocio).
- [ ] Paridad funcional validada por QA/usuarios clave.

---

## 3) Eliminación progresiva del bridge

- [ ] `LegacyExamenesBridge` queda solo para fallback temporal controlado por flag.
- [ ] Flags de reads/writes/frontend definidos por entorno:
  - `EXAMENES_V2_UI_ENABLED`
  - `EXAMENES_V2_READS_ENABLED`
  - `EXAMENES_V2_WRITES_ENABLED`
  - `EXAMENES_V2_FRONTEND_MODE` (`legacy|native`)
- [ ] Runbook de rollback documentado (volver temporalmente a legacy).
- [ ] Cuando estabilidad >= 2 semanas, desactivar fallback y remover bridge.

---

## 4) Pruebas mínimas de salida (Go/No-Go)

### Smoke funcional

- [ ] Abrir `/v2/examenes` carga kanban sin errores JS.
- [ ] Cambiar estado desde kanban persiste y se refleja en tiempo real.
- [ ] Turnero lista y llama turno correctamente.
- [ ] CRM guarda detalles, notas y tareas.
- [ ] Exportes PDF/Excel responden correctamente.
- [ ] Flujos de derivación operativos.

### Calidad técnica

- [ ] Sin errores `local.ERROR` en flujos críticos durante pruebas.
- [ ] Tiempo de respuesta p95 aceptable en endpoints principales.
- [ ] No hay referencias a constantes legacy no definidas (`BASE_URL`, etc.) en frontend/reporting v2.

---

## 5) Criterio de “Migración Completa”

Se considera **completa** cuando:

1. Backend crítico de Exámenes corre en Laravel sin bridge para operaciones diarias.
2. Frontend principal (kanban + turnero + CRM) es Blade/JS v2 nativo Laravel.
3. Bridge legacy queda apagado por defecto y solo para contingencia temporal.
4. Existe validación de negocio + QA con sign-off.

---

## 6) Registro de avance

- Fecha:
- Responsable:
- % estimado backend:
- % estimado frontend:
- Riesgos abiertos:
- Próxima revisión:
