# Parity Matrix (Strangler Wave A/B/C)

## Rules

- `/v2/*` runs in Laravel.
- Non-`/v2/*` remains in legacy.
- Read endpoints first, writes second, auth/session last.

## Inventory (legacy -> v2)

### Dashboard

- `GET /dashboard` -> `GET /v2/dashboard` (UI cutover por flag)
- `GET /dashboard` -> `GET /v2/dashboard/summary` (API parity)

Current auth parity policy:

- Session bridge global en Laravel (`LegacySessionBridge`) para leer `PHPSESSID` del login legacy.
- `PHPSESSID` queda excluida de encrypt/decrypt cookies de Laravel para evitar loop de login en rutas `web`.
- Guest behavior differs by design (`302` HTML redirect in legacy browser vs `401` JSON cuando request API a v2).
- Authenticated parity validates:
- `/v2/dashboard` render (`200` HTML).
- `/v2/dashboard/summary` status (`200`) + v2 JSON shape (summary metrics).
- Con `DASHBOARD_V2_UI_ENABLED=1`, `/dashboard` legacy redirige a `/v2/dashboard`; por eso el smoke de `dashboard_summary` en auth corre en modo `v2_only`.

### Billing

- `GET /api/billing/no-facturados` -> `GET /v2/api/billing/no-facturados`
- `GET /api/billing/afiliaciones` -> `GET /v2/api/billing/afiliaciones`
- `POST /billing/no-facturados/crear` -> `POST /v2/billing/no-facturados/crear` (`/v2/api/billing/no-facturados/crear`)
- `POST /informes/api/eliminar-factura` -> `POST /v2/informes/api/eliminar-factura` (`/v2/api/billing/facturas/eliminar`)
- `POST /api/billing/verificacion_derivacion.php` -> `POST /v2/api/billing/verificacion_derivacion.php` (`/v2/api/billing/derivaciones/verificar`)
- `POST /api/billing/insertar_billing_main.php` -> `POST /v2/api/billing/insertar_billing_main.php` (`/v2/api/billing/procedimientos/registrar`)

### Pacientes

- `POST /pacientes/datatable` -> `POST /v2/pacientes/datatable`
- `GET|POST /pacientes/detalles` -> `GET|POST /v2/pacientes/detalles`
- `GET /pacientes/detalles/solicitud` -> `GET /v2/pacientes/detalles/solicitud`
- `GET /pacientes/detalles/section` -> `GET /v2/pacientes/detalles/section`
- `GET /pacientes/flujo` -> `GET /v2/pacientes/flujo`
- `POST /pacientes/detalles?actualizar_paciente=1` -> `POST /v2/pacientes/detalles?actualizar_paciente=1`

Current auth parity policy:

- `datatable` guest parity is enforced (`401` both sides).
- `detalles` differs on guest behavior by design (`302` legacy page redirect vs `401` JSON in v2 API).
- Authenticated parity is contract-aware when `--cookie=PHPSESSID=...` is present:
- `datatable`: full compare (legacy/v2 `200` + DataTable shape).
- `detalles`: status parity (`200` both sides) + v2 JSON required fields.
- `detalles/solicitud`: auth parity for validation flow (`422` when missing `form_id`) + guest `401`.
- `detalles/section`: status parity (`200` both sides) + v2 JSON required fields.
- `flujo`: status parity (`200` both sides) + v2 JSON required fields.
- `detalles_update`: status parity (`302` both sides) + v2 post-check de persistencia.

### Auth (deferred)

- `GET /auth/login` -> `GET /v2/auth/migration-status` (operational 200)
- `GET /auth/logout` <-> `GET /v2/auth/logout` (logout unificado legacy+v2)

Logout unificado:

- Si cierras en legacy (`/auth/logout`), se destruye `PHPSESSID` y se expiran cookies Laravel (`laravel-session`, `XSRF-TOKEN`).
- Si cierras en v2 (`/v2/auth/logout`), se destruye sesión legacy (`PHPSESSID`) y se invalida la sesión Laravel.

### Reporting (PDF cutover)

Cutover status:

- Phase 3 completed on March 6, 2026.
- Legacy PDF routes now work as compatibility shims (`302`/`307`) to `/v2/reports/*`.
- Legacy cobertura queue no longer generates PDFs in legacy runtime; queue endpoints return a v2 shim payload that resolves to `/v2/reports/cobertura/pdf`.

Endpoints covered:

- `GET /reports/protocolo/pdf` -> `GET /v2/reports/protocolo/pdf`
- `GET /reports/cobertura/pdf` -> `GET /v2/reports/cobertura/pdf`
- `GET /reports/cobertura/pdf-template` -> `GET /v2/reports/cobertura/pdf?variant=template`
- `GET /reports/cobertura/pdf-html` -> `GET /v2/reports/cobertura/pdf?variant=appendix`
- `GET /reports/consulta/pdf` -> `GET /v2/reports/consulta/pdf`
- `GET|POST /reports/cirugias/descanso/pdf` -> `GET|POST /v2/reports/cirugias/descanso/pdf`
- `GET /imagenes/informes/012b/pdf` -> `GET /v2/reports/imagenes/012b/pdf`
- `GET /imagenes/informes/012b/paquete` -> `GET /v2/reports/imagenes/012b/paquete`
- `GET|POST /imagenes/informes/012b/paquete/seleccion` -> `GET|POST /v2/reports/imagenes/012b/paquete/seleccion`
- `GET /examenes/cobertura-012a/pdf` -> `GET /v2/reports/imagenes/012a/pdf`

## Fixture dataset

Use stable fixture records in DB:

- `hc_number`: pass an existing patient with `--hc-number=...` (default token `HC-TEST-001`)
- `billing fixture`: dynamic candidate from `/v2/api/billing/no-facturados?start=0&length=1` or pass
  `--billing-form-id` + `--billing-hc-number`
- At least one row in `patient_data`
- At least one row in billing source tables for `no-facturados`

## Smoke command

```bash
php tools/tests/http_smoke.php --module=billing
php tools/tests/http_smoke.php --module=billing --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --module=billing --cookie='PHPSESSID=...' --allow-destructive
php tools/tests/http_smoke.php --endpoint=auth_logout_unified --cookie='PHPSESSID=...' --allow-destructive
php tools/tests/http_smoke.php --endpoint=dashboard_ui
php tools/tests/http_smoke.php --endpoint=dashboard_ui --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --endpoint=dashboard_summary
php tools/tests/http_smoke.php --endpoint=dashboard_summary --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --module=dashboard_cutover
php tools/tests/http_smoke.php --module=dashboard_cutover --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --module=solicitudes_cutover
php tools/tests/http_smoke.php --module=solicitudes_cutover --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --module=reporting_cutover
php tools/tests/http_smoke.php --module=reporting_cutover --cookie='PHPSESSID=...' --report-form-id='249878' --report-hc-number='0300263803' --report-dias-descanso='5'
php tools/tests/http_smoke.php --endpoint=pacientes_datatable
php tools/tests/http_smoke.php --endpoint=pacientes_detalles_solicitud
php tools/tests/http_smoke.php --endpoint=pacientes_detalles_section
php tools/tests/http_smoke.php --endpoint=pacientes_flujo
php tools/tests/http_smoke.php --endpoint=pacientes_detalles_update
php tools/tests/http_smoke.php --fail-fast
php tools/tests/http_smoke.php --module=pacientes --cookie='PHPSESSID=...' --hc-number='HC-REAL-001'
```

### Auth note for remote smoke

- In unauthenticated runs, legacy billing APIs can respond `302` to `/auth/login`.
- Current contract marks those as `v2_only` until shared-session/auth migration (Wave C).
- Destructive tests (for example `billing_eliminar_factura`) run only with `--allow-destructive`.
- On IONOS, use CLI 8.1 (avoid `php` CGI legacy):

```bash
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=billing --cookie='PHPSESSID=...'
/usr/bin/php8.1-cli tools/tests/http_smoke.php --endpoint=dashboard_ui --cookie='PHPSESSID=...'
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=dashboard_cutover --cookie='PHPSESSID=...'
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=solicitudes_cutover --cookie='PHPSESSID=...'
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=reporting_cutover --cookie='PHPSESSID=...' --report-form-id='249878' --report-hc-number='0300263803' --report-dias-descanso='5'
/usr/bin/php8.1-cli tools/tests/http_smoke.php --endpoint=auth_logout_unified --cookie='PHPSESSID=...' --allow-destructive
```

## Billing Write Cutover Flag

- Legacy frontend can switch only Billing writes to `/v2` with env flag:

```bash
BILLING_V2_WRITES_ENABLED=1
```

- Fast rollback (no code revert): set

```bash
BILLING_V2_WRITES_ENABLED=0
```

## Dashboard UI Cutover Flag

- Legacy runtime can switch Dashboard UI to Laravel `/v2/dashboard` with env flag:

```bash
DASHBOARD_V2_UI_ENABLED=1
```

- Validation when enabled:

```bash
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=dashboard_cutover --cookie='PHPSESSID=...'
```

- Expected:
- `dashboard_ui_cutover_redirect` returns `302`.
- `Location` header contains `/v2/dashboard`.

- Fast rollback (no code revert):

```bash
DASHBOARD_V2_UI_ENABLED=0
```

## Dashboard Data Cutover Flag

- Legacy dashboard UI can keep rendering in legacy while reading summary data from Laravel `/v2/dashboard/summary`:

```bash
DASHBOARD_V2_DATA_ENABLED=1
```

- Behavior:
- `1`: legacy `/dashboard` keeps the same view but sources main dashboard metrics/charts from `/v2/dashboard/summary`.
- fallback automático a SQL legacy si `/v2` falla (sin downtime).
- response header para trazabilidad: `X-Dashboard-Data-Source: v2|legacy`.

- Rollback instantáneo:

```bash
DASHBOARD_V2_DATA_ENABLED=0
```

## Solicitudes Cutover Flags

- Legacy runtime can switch Solicitudes UI/reads/writes to Laravel `/v2/solicitudes*` with env flags:

```bash
SOLICITUDES_V2_UI_ENABLED=1
SOLICITUDES_V2_READS_ENABLED=1
SOLICITUDES_V2_WRITES_ENABLED=1
```

- Validation when enabled:

```bash
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=solicitudes_cutover --cookie='PHPSESSID=...'
```

- Expected:
- `solicitudes_ui_cutover_redirect` returns `302` to `/v2/solicitudes`.
- `solicitudes_dashboard_ui_cutover_redirect` returns `302` to `/v2/solicitudes/dashboard`.
- `solicitudes_turnero_ui_cutover_redirect` returns `302` to `/v2/solicitudes/turnero`.

- Fast rollback (no code revert):

```bash
SOLICITUDES_V2_UI_ENABLED=0
SOLICITUDES_V2_READS_ENABLED=0
SOLICITUDES_V2_WRITES_ENABLED=0
```
