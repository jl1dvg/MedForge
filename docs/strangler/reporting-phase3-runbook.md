# Reporting PDF Phase 3 Runbook

Cutover date: March 6, 2026

## Scope

- Legacy report routes now redirect/bridge to `/v2/reports/*`.
- Legacy async queue for reporting PDF is disabled (cron task `reporting-async-queue` stays as skipped for traceability).
- Validation baseline: `tools/tests/http_smoke.php --module=reporting_cutover`.

## Release

From repository root:

```bash
git add \
  laravel-app/routes/api.php \
  modules/Reporting/routes.php \
  modules/examenes/routes.php \
  modules/examenes/controllers/ExamenController.php \
  modules/CronManager/Services/CronRunner.php \
  tools/tests/http_smoke.php \
  tools/tests/http_smoke.contract.php \
  docs/strangler/parity-matrix.md \
  docs/strangler/reporting-phase3-runbook.md

git commit -m "chore(reporting): finalize phase 3 cutover to v2 with legacy shims and smoke coverage"
git tag v2026.03.06-reporting-phase3
git push origin HEAD --tags
```

## Deploy

```bash
cd ~/medforge/laravel-app
php8.3-cli artisan optimize:clear
```

## Post-deploy Smoke

```bash
BASE_URL="https://cive.consulmed.me"

php8.3-cli tools/tests/http_smoke.php \
  --module=reporting_cutover \
  --legacy-base="$BASE_URL" \
  --v2-base="$BASE_URL"

php8.3-cli tools/tests/http_smoke.php \
  --module=reporting_cutover \
  --legacy-base="$BASE_URL" \
  --v2-base="$BASE_URL" \
  --cookie='PHPSESSID=...' \
  --report-form-id='249878' \
  --report-hc-number='0300263803' \
  --report-dias-descanso='5'
```

Expected:

- Guest run: redirects/queue shims pass; authenticated PDF checks skip (no fixture).
- Authenticated run: `passed=18 failed=0 skipped=0`.

## Monitoring (24-48h)

```bash
tail -n 500 -f ~/medforge/laravel-app/storage/logs/laravel.log | grep -E "reporting.read.|v2-reporting-|ERROR"
```

Watch for:

- Any `reporting.read.*.error`.
- HTTP `5xx` spikes on `/v2/reports/*`.
- Repeated retries from legacy clients against removed behavior.

## Rollback

- Preferred: deploy previous git tag (before `v2026.03.06-reporting-phase3`).
- Fast fallback: keep current code and route users directly to `/v2/reports/*` endpoints already validated in production.
