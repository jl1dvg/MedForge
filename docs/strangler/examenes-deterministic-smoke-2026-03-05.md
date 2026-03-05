# Exámenes deterministic smoke — 2026-03-05

Production validation done on hosting (`/usr/bin/php8.3-cli`) with authenticated `PHPSESSID`.

## Deterministic fixture policy
- Source fixture from `POST /examenes/kanban-data`.
- Pick first item and reuse:
  - `id`
  - `hc_number`
  - current `estado`
- Perform same-state write to avoid business-flow side effects.

## Executed evidence
Fixture selected:
- `id=18918`
- `hc_number=0906272430`
- `estado=recibido`

Requests:
1. `GET /examenes/api/estado?hc_number=0906272430` => **HTTP 200**
2. `POST /examenes/api/estado` with body
   `{"id":18918,"estado":"recibido","observacion":"smoke-deterministic"}` => **HTTP 200**
3. `POST /examenes/actualizar-estado` with body
   `{"id":18918,"estado":"recibido","origen":"smoke"}` => **HTTP 200**

## Conclusion
Exámenes write-path can be validated deterministically by reusing current state + valid fixture from kanban feed.

## Added helper
- `tools/tests/examenes_deterministic_smoke.sh`
