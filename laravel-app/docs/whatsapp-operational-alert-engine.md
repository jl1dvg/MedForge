# WhatsApp Operational Alert Engine

Internal reference for the Alert Engine stack (Fase 4A → 4B). Read-only at every layer.

---

## Components

### Alert Engine (`WhatsappOperationalAlertService`)
Runs read-only queries against `whatsapp_handoffs` + `whatsapp_conversations` + `whatsapp_messages` and classifies active conversations into alert types and severity levels. No DB writes.

### Decision Engine (`WhatsappOperationalDecisionService`)
Evaluates each conversation row against a set of rules to produce a decision (`alert_type`, `severity`, `bucket`, `suggested_action`, etc.). Called internally by the Alert Engine.

### Notification Preview (`WhatsappOperationalNotificationPreviewService`)
Dry-run only. Filters Alert Engine output to `hot_unassigned + critical + unassigned` candidates and builds the message text that *would* be sent in a future Fase 4C. Never sends anything. Returns `mode=dry_run, channel=none, db_writes=0`.

---

## Alert Types

| Type | Trigger |
|---|---|
| `hot_unassigned` | Active handoff in `queued` status with no agent, window still open (≤24h since last inbound) |
| `rescue_aging` | Conversation with inbound messages 1–7 days old, no agent response |
| `supervisor_sla_breach` | Agent assigned but no reply beyond SLA window |
| `no_availability_repeated` | Multiple contacts with no availability slot |
| `ambiguous_urgent_faq` | Urgent-looking FAQ with ambiguous urgency signal |

## Severity Levels

| Severity | Threshold |
|---|---|
| `critical` | ≥ 60 min waiting |
| `high` | 30–59 min |
| `medium` | 15–29 min |
| `low` | < 15 min |

## Conversation Buckets (classifyBucket)

| Bucket | Age of last inbound |
|---|---|
| `hot_open` | ≤ 24h, messaging window open |
| `hot_needs_template` | ≤ 24h, window closed |
| `rescue` | 1–7 days |
| `backlog` | 7–30 days |
| `lost` | > 30 days |

---

## Endpoints

| Method | Path | Auth | Description |
|---|---|---|---|
| `GET` | `/v2/whatsapp/operational-alerts` | `app.auth` + `whatsapp.chat.supervise` | UI page |
| `GET` | `/v2/whatsapp/alerts` | `app.auth` | Legacy alias |
| `GET` | `/v2/whatsapp/api/operational-alerts` | `app.auth` + `whatsapp.chat.supervise` | Alert Engine API |
| `GET` | `/v2/whatsapp/api/operational-alerts/notification-preview` | `app.auth` + `whatsapp.chat.supervise` | Notification Preview dry-run API |

All endpoints are GET / read-only. No POST/PUT/DELETE exists for this stack.

### API: `/api/operational-alerts`

Query params: `date`, `severity` (all/critical/high/medium/low), `category`, `type`, `agent`, `limit`, `summary`.

Returns:
```json
{
  "ok": true,
  "read_only": true,
  "db_writes": 0,
  "mode": "read_only",
  "summary": { "critical": 0, "high": 0, "medium": 0, "low": 0 },
  "by_type": {},
  "alerts": [],
  "filters_applied": {}
}
```

### API: `/api/operational-alerts/notification-preview`

Query params: `date`.  
Guardrails: returns 422 if `send` or `channel` params are present.  
Optional: `?debug=1` adds a `diagnostics` block (staging/local only).

Returns:
```json
{
  "ok": true,
  "mode": "dry_run",
  "read_only": true,
  "db_writes": 0,
  "channel": "none",
  "would_notify": 0,
  "evaluated": 0,
  "date": "YYYY-MM-DD",
  "notifications": []
}
```

Each notification item:
```json
{
  "conversation_id": 123,
  "wa_number": "5930991234567",
  "display_name": "Paciente Test",
  "hc_number": "HC-001",
  "alert_type": "hot_unassigned",
  "severity": "critical",
  "topic_label": "Agendar consulta",
  "waiting_minutes": 90,
  "chat_url": "/v2/whatsapp/chat?search=5930991234567&filter=all",
  "message_preview": "🚨 Alerta WhatsApp crítica\n..."
}
```

---

## Artisan Commands

```bash
# Preview dry-run (no sends, no writes)
php8.3-cli artisan whatsapp:operational-notifications-preview
php8.3-cli artisan whatsapp:operational-notifications-preview --date=2026-06-29
php8.3-cli artisan whatsapp:operational-notifications-preview --json
```

The command has no `--send`, `--channel`, or `--dispatch` option. Attempting to pass them causes a Laravel error.

---

## Read-Only Guarantees

- No INSERT into `whatsapp_handoff_events`
- No INSERT into `whatsapp_operational_events`
- No UPDATE to `whatsapp_conversations`
- No UPDATE to `whatsapp_handoffs`
- No messages sent via any channel
- No scheduler / cron activation
- No `.env` variables for notification channels are read
- `db_writes=0` enforced in every response
- `channel=none` hardcoded in preview service (not configurable from outside)
- Controller rejects `?send=true` and `?channel=*` with HTTP 422

---

## Exclusions from Notification Preview

Only `hot_unassigned + critical + assigned_user_id=null` qualify as candidates.  
**Explicitly excluded:**
- `rescue_aging` — intentionally excluded; different workflow
- `supervisor_sla_breach`
- `no_availability_repeated`
- `ambiguous_urgent_faq`
- Any alert with severity below `critical`
- Any alert already assigned to an agent

---

## What is blocked until Fase 4C

- Sending any real notification (Telegram, WhatsApp interno, email, Slack, etc.)
- Activating any scheduled/cron notification job
- Auto-assigning conversations
- Any DB write in the notification path

Fase 4C is blocked until coordinación confirms during Fase 4B.2 operational validation that `hot_unassigned + critical + unassigned` alerts are actionable with low false-positive rate.
