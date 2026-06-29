# Staging integration runbook

## Branch and deploy policy

- `staging` is the official integration branch.
- `main` is the production branch.
- Feature PRs target `staging` by default.
- Production promotions are created from tested `staging` state into `main`.
- Staging deploys only from `push` to `staging` or an explicit `workflow_dispatch` with `environment=staging`.
- PRs to `main` must not deploy to staging.

## Staging database model

Staging must use an independent database. Do not point staging at the production database for application writes or migrations.

Use a filtered production refresh so staging keeps recent operational signal without sharing the production write surface:

- Refresh cadence: every 5-15 minutes for high-value operational tables, plus a nightly full filtered refresh if needed.
- Include read/evaluation data needed for clinical workflows, dashboards, CRM, scheduling, billing previews, and WhatsApp analytics.
- Exclude or sanitize credentials, sessions, webhook tokens, API keys, access tokens, password reset tokens, and any table that can trigger real-world side effects.
- Truncate or remap queue/job/session/cache tables after refresh.
- Keep production IDs when they are needed for cross-table consistency, but do not let staging webhooks or jobs write back to production systems.

## Side-effect controls

Staging must be safe by default:

- WhatsApp outbound transport must run in dry-run mode unless an explicit sandbox number/provider is configured.
- WhatsApp webhooks must be disabled or pointed to a staging-only webhook endpoint.
- Scheduled handoff, auto-assign, reminder, abandonment, and automation jobs must be disabled until each one is intentionally validated.
- Email/SMS/payment/third-party callbacks must use sandbox credentials or be disabled.
- Queue workers must use staging-only queues and prefixes.

Recommended staging flags:

```dotenv
APP_ENV=staging
APP_DEBUG=false

DB_CONNECTION=mysql
DB_DATABASE=medforge_staging

CACHE_STORE=redis
QUEUE_CONNECTION=redis
REDIS_CLIENT=predis
REDIS_DB=2
REDIS_CACHE_DB=3
REDIS_PREFIX=medforge_staging_
CACHE_PREFIX=medforge_staging_cache_

WHATSAPP_LARAVEL_API_WRITE_ENABLED=false
WHATSAPP_LARAVEL_WEBHOOK_ENABLED=false
WHATSAPP_LARAVEL_HANDOFF_REQUEUE_SCHEDULED=false
WHATSAPP_LARAVEL_HANDOFF_AUTO_ASSIGN_SCHEDULED=false
WHATSAPP_LARAVEL_AUTOMATION_ENABLED=false
WHATSAPP_LARAVEL_AUTOMATION_DRY_RUN=true
WHATSAPP_LARAVEL_TRANSPORT_DRY_RUN=true
```

## Validation checklist

- Confirm staging `.env` points to the staging database before running `php artisan migrate`.
- Confirm Redis queue/cache DBs are `2` and `3`, with `medforge_staging_` prefixes.
- Confirm no production webhook points to staging.
- Confirm outbound WhatsApp is dry-run or sandboxed.
- Confirm refresh jobs do not copy production secrets into staging.
- Confirm a staging deploy can run migrations without touching production data.
