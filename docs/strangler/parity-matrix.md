# Parity Matrix (Strangler Wave A/B/C)

## Rules

- `/v2/*` runs in Laravel.
- Non-`/v2/*` remains in legacy.
- Read endpoints first, writes second, auth/session last.

## Inventory (legacy -> v2)

### Dashboard

- `GET /dashboard` -> `GET /v2/dashboard/summary`

Current auth parity policy:

- Guest behavior differs by design (`302` HTML redirect in legacy vs `401` JSON in v2 API).
- Authenticated parity validates status (`200` both sides) + v2 JSON shape (summary metrics).

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
- `GET /pacientes/flujo` -> `GET /v2/pacientes/flujo`
- `POST /pacientes/detalles?actualizar_paciente=1` -> `POST /v2/pacientes/detalles?actualizar_paciente=1`

Current auth parity policy:

- `datatable` guest parity is enforced (`401` both sides).
- `detalles` differs on guest behavior by design (`302` legacy page redirect vs `401` JSON in v2 API).
- Authenticated parity is contract-aware when `--cookie=PHPSESSID=...` is present:
- `datatable`: full compare (legacy/v2 `200` + DataTable shape).
- `detalles`: status parity (`200` both sides) + v2 JSON required fields.
- `flujo`: status parity (`200` both sides) + v2 JSON required fields.
- `detalles_update`: status parity (`302` both sides) + v2 post-check de persistencia.

### Auth (deferred)

- `GET /auth/login` -> `GET /v2/auth/migration-status` (placeholder 501)

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
php tools/tests/http_smoke.php --endpoint=dashboard_summary
php tools/tests/http_smoke.php --endpoint=dashboard_summary --cookie='PHPSESSID=...'
php tools/tests/http_smoke.php --endpoint=pacientes_datatable
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

- Fast rollback (no code revert):

```bash
DASHBOARD_V2_UI_ENABLED=0
```
