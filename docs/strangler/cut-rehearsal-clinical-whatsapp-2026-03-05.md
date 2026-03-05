# Cut Rehearsal — Clinical Block + WhatsApp

Date: 2026-03-05
Environment: production (`https://cive.consulmed.me`)
Runtime: `/usr/bin/php8.3-cli` session bootstrap + authenticated cookie

## Scope
- Doctores
- Cirugías
- Exámenes (deterministic fixture write path)
- WhatsApp/Autoresponder baseline

## Evidence

### Doctores
- `GET /doctores` => 200
- `GET /doctores/1` => 200
- Result: **2/2 PASS**

### Cirugías
Fixture extracted from `POST /cirugias/datatable`:
- `form_id=229863`, `hc_number=1200454708`

Checks:
- `GET /cirugias` => 200
- `GET /cirugias/dashboard` => 200
- `GET /cirugias/wizard?form_id=229863&hc_number=1200454708` => 200
- `GET /cirugias/protocolo?form_id=229863&hc_number=1200454708` => 200
- `POST /cirugias/wizard/autosave` (fixture payload) => 200
- Result: **5/5 PASS**

### Exámenes
Deterministic fixture extracted from `POST /examenes/kanban-data`:
- `id=18918`, `hc_number=0906272430`, `estado=recibido`

Checks:
- `GET /examenes` => 200
- `GET /examenes/turnero` => 200
- `GET /imagenes/examenes-realizados` => 200
- `GET /examenes/api/estado?hc_number=0906272430` => 200
- `POST /examenes/api/estado` (same-state deterministic write) => 200
- `POST /examenes/actualizar-estado` (same-state deterministic write) => 200
- Result: **6/6 PASS**

### WhatsApp / Autoresponder
- `/whatsapp/autoresponder` => 200
- `/whatsapp/flowmaker` => 200
- `/whatsapp/chat` => 200
- `/whatsapp/dashboard` => 200
- `/whatsapp/api/inbox` => 200
- `/whatsapp/api/kpis` => 200
- `/whatsapp/api/conversations` => 200
- `/whatsapp/api/templates` => 200
- `/whatsapp/api/flowmaker/contract` => 200
- Result: **9/9 PASS**

## Global summary
- PASS: **22**
- FAIL: **0**
- SKIP: **0**

## GO / NO-GO
**GO** for this rehearsal scope.

## Rollback note
No feature-flag toggle changed during this rehearsal; checks were read-only or same-state deterministic writes.
