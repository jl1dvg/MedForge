# MedForge Migration War Board (14 días)

Objetivo: operar principalmente en Laravel (`/v2/*`) al día 14 con rollback controlado por flags.

## Reglas de ejecución

1. Sin features nuevas (solo migración + bugs críticos).
2. Orden fijo por módulo: **Read parity -> Write parity -> UI cutover**.
3. Todo módulo requiere smoke + rollback flag antes de marcarse DONE.
4. Si algo rompe producción: rollback por flag en <5 min.

## Definition of Done por módulo

- [ ] Endpoints críticos con paridad validada (status + contrato JSON)
- [ ] `tools/tests/http_smoke.php` en verde (guest + auth)
- [ ] Flags de cutover/rollback documentados
- [ ] Logging con `X-Request-Id` y error trace mínimo
- [ ] Runbook de rollback (pasos + responsable)

## Plan 14 días

### Día 1-2 (bloqueante)
- Auth/sesión unificada (logout cruzado, expiración, tabs múltiples).
- Endurecer módulos ya activos: Dashboard, Billing, Pacientes, Solicitudes.
- Correr smoke matrix base auth/no-auth.

### Día 3-6
- CRM
- Agenda
- Doctores

### Día 7-9
- Cirugías
- Exámenes
- Derivaciones
- Insumos

### Día 10-11
- WhatsApp
- Autoresponder
- Flowmaker
- Mail + MailTemplates + Notifications

### Día 12
- Reporting + KPI
- Usuarios + Settings + Core/Search/CiveExtension/Farmacia pendientes

### Día 13
- Hardening final: observabilidad + reconciliación de writes + performance básica.

### Día 14
- Cutover controlado
- Verificación final
- Plan de retiro legacy por fases

## Tablero de seguimiento (actualizar 2 veces al día)

| Módulo | Owner | Estado | Paridad | Smoke | Flags | Riesgo | Nota |
|---|---|---|---:|---|---|---|---|
| Auth/Sesión | | TODO | 0% | ❌ | ❌ | Alto | Bloqueante |
| Dashboard | | IN PROGRESS | 70% | ⚠️ | ✅ | Medio | Cutover UI/Data disponible |
| Billing | | IN PROGRESS | 65% | ⚠️ | ✅ | Medio | Writes por flag |
| Pacientes | | IN PROGRESS | 60% | ⚠️ | ⚠️ | Medio | |
| Solicitudes | | IN PROGRESS | 45% | ⚠️ | ⚠️ | Medio | |
| CRM | | TODO | 0% | ❌ | ❌ | Medio | |
| Agenda | | TODO | 0% | ❌ | ❌ | Medio | |
| Doctores | | TODO | 0% | ❌ | ❌ | Medio | |
| Cirugías | | TODO | 0% | ❌ | ❌ | Medio | |
| Exámenes | | TODO | 0% | ❌ | ❌ | Medio | |
| Derivaciones | | TODO | 0% | ❌ | ❌ | Medio | |
| Insumos | | TODO | 0% | ❌ | ❌ | Medio | |
| WhatsApp | | TODO | 0% | ❌ | ❌ | Medio/Alto | Integraciones |
| Autoresponder | | TODO | 0% | ❌ | ❌ | Medio/Alto | |
| Flowmaker | | TODO | 0% | ❌ | ❌ | Medio/Alto | |
| Mail/Notifications | | TODO | 0% | ❌ | ❌ | Medio | |
| Reporting/KPI | | TODO | 0% | ❌ | ❌ | Bajo/Medio | |
| Plataforma restante | | TODO | 0% | ❌ | ❌ | Medio | |

## Comandos base (smoke)

```bash
php tools/tests/http_smoke.php --module=billing --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --module=pacientes --cookie='PHPSESSID=...' --hc-number='HC-REAL-001'
php tools/tests/http_smoke.php --module=dashboard_cutover --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --module=solicitudes_cutover --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --endpoint=auth_logout_unified --cookie='PHPSESSID=...' --allow-destructive
```

## Banderas activas relevantes

- `DASHBOARD_V2_UI_ENABLED`
- `DASHBOARD_V2_DATA_ENABLED`
- `BILLING_V2_WRITES_ENABLED`
- `SOLICITUDES_V2_UI_ENABLED`
- `SOLICITUDES_V2_READS_ENABLED`
- `SOLICITUDES_V2_WRITES_ENABLED`

## Ritual diario (10 min, mañana/noche)

1. Qué módulo cierra hoy.
2. Qué bloquea ahora mismo.
3. Qué flag queda listo para rollback.
