# Parity Matrix (Strangler Wave A/B/C)

## Rules

- `/v2/*` runs in Laravel.
- Non-`/v2/*` remains in legacy.
- Read endpoints first, writes second, auth/session last.

## Inventory (legacy -> v2)

### Dashboard

- `GET /dashboard` -> `GET /v2/dashboard/summary` (v2-only parity until structured legacy JSON exists)

### Billing

- `GET /api/billing/no-facturados` -> `GET /v2/api/billing/no-facturados`
- `GET /api/billing/afiliaciones` -> `GET /v2/api/billing/afiliaciones`

### Pacientes

- `POST /pacientes/datatable` -> `POST /v2/pacientes/datatable`
- `GET|POST /pacientes/detalles` -> `GET|POST /v2/pacientes/detalles`
- `GET /pacientes/flujo` -> `GET /v2/pacientes/flujo`

Current auth parity policy:

- `datatable` guest parity is enforced (`401` both sides).
- `detalles` differs on guest behavior by design (`302` legacy page redirect vs `401` JSON in v2 API).
- Authenticated parity is contract-aware when `--cookie=PHPSESSID=...` is present:
- `datatable`: full compare (legacy/v2 `200` + DataTable shape).
- `detalles`: status parity (`200` both sides) + v2 JSON required fields.

### Auth (deferred)

- `GET /auth/login` -> `GET /v2/auth/migration-status` (placeholder 501)

## Fixture dataset

Use stable fixture records in DB:

- `hc_number`: pass an existing patient with `--hc-number=...` (default token `HC-TEST-001`)
- At least one row in `patient_data`
- At least one row in billing source tables for `no-facturados`

## Smoke command

```bash
php tools/tests/http_smoke.php --module=billing
php tools/tests/http_smoke.php --endpoint=pacientes_datatable
php tools/tests/http_smoke.php --fail-fast
php tools/tests/http_smoke.php --module=pacientes --cookie='PHPSESSID=...' --hc-number='HC-REAL-001'
```

### Auth note for remote smoke

- In unauthenticated runs, legacy billing APIs can respond `302` to `/auth/login`.
- Current contract marks those as `v2_only` until shared-session/auth migration (Wave C).
- On IONOS, use CLI 8.1 (avoid `php` CGI legacy):

```bash
/usr/bin/php8.1-cli tools/tests/http_smoke.php --module=billing --cookie='PHPSESSID=...'
```
