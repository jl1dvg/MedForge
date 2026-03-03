# Migration Scorecard (por módulo)

Usa esta plantilla para puntuar avance real (0-100) y decidir si un módulo puede pasar a cutover.

## Fórmula

- Contrato API/paridad: **25 pts**
- Tests/Smoke: **25 pts**
- Flags + rollback: **20 pts**
- Observabilidad: **15 pts**
- Operación/Runbook: **15 pts**

**Total:** 100 pts

---

## Checklist de evaluación

### 1) Contrato API/paridad (25)
- [ ] Endpoints críticos definidos (legacy vs v2)
- [ ] Status code parity validada
- [ ] JSON shape parity (campos clave)
- [ ] Casos de error validados (422/401/500 esperados)

### 2) Tests/Smoke (25)
- [ ] `http_smoke` guest en verde
- [ ] `http_smoke` auth en verde
- [ ] Tests de rutas sensibles (write/delete) en verde
- [ ] Resultado adjunto en PR

### 3) Flags + rollback (20)
- [ ] `*_V2_READS_ENABLED` (si aplica)
- [ ] `*_V2_WRITES_ENABLED` (si aplica)
- [ ] `*_V2_UI_ENABLED` (si aplica)
- [ ] Rollback probado (off -> legacy en <5 min)

### 4) Observabilidad (15)
- [ ] `X-Request-Id` end-to-end
- [ ] Logs con contexto mínimo (ruta, usuario, request_id)
- [ ] Métrica de error rate por módulo

### 5) Operación/Runbook (15)
- [ ] Runbook de incidente/cutover
- [ ] Responsable de guardia asignado
- [ ] Criterio de stop/go definido

---

## Tabla rápida de seguimiento

| Módulo | Contrato (25) | Tests (25) | Flags (20) | Obs (15) | Ops (15) | Total | Estado |
|---|---:|---:|---:|---:|---:|---:|---|
| Auth/Sesión |  |  |  |  |  |  | |
| Dashboard |  |  |  |  |  |  | |
| Billing |  |  |  |  |  |  | |
| Pacientes |  |  |  |  |  |  | |
| Solicitudes |  |  |  |  |  |  | |
| CRM |  |  |  |  |  |  | |
| Agenda |  |  |  |  |  |  | |
| Doctores |  |  |  |  |  |  | |
| Cirugías |  |  |  |  |  |  | |
| Exámenes |  |  |  |  |  |  | |
| Derivaciones |  |  |  |  |  |  | |
| Insumos |  |  |  |  |  |  | |
| WhatsApp |  |  |  |  |  |  | |
| Autoresponder |  |  |  |  |  |  | |
| Flowmaker |  |  |  |  |  |  | |
| Mail/Notifications |  |  |  |  |  |  | |
| Reporting/KPI |  |  |  |  |  |  | |
