# Strangler routing (`/v2/*`)

- `^/v2/*` -> Laravel (`laravel-app/public`)
- everything else -> legacy runtime (`MedForge/public/index.php`)

## Health checks

- Legacy liveness: `/health` (proxy alias to `/auth/login` while legacy runtime remains frozen)
- Laravel v2 health: `/v2/health`

## Trace headers

Forward these headers at the proxy layer:

- `X-Request-Id`
- `X-Forwarded-For`
- `X-Forwarded-Proto`
