# Clinical Block Baseline — 2026-03-05

Scope: **Doctores / Cirugías / Exámenes**
Environment: production (`https://cive.consulmed.me`) via `/usr/bin/php8.3-cli` session bootstrap.

## Fixtures used
- Cirugías fixture from datatable:
  - `form_id=229863`
  - `hc_number=1200454708`
- Exámenes fixture from kanban-data:
  - `id=18918`
  - `hc_number=0906272430`
  - `form_id=253901`

## Results

### Doctores
- `GET /doctores` => **200**
- `GET /doctores/1` => **200**

Summary: **2/2 PASS**

### Cirugías
- `GET /cirugias` => **200**
- `GET /cirugias/dashboard` => **200**
- `POST /cirugias/datatable` => **200**
- `GET /cirugias/wizard?form_id=229863&hc_number=1200454708` => **200**
- `GET /cirugias/protocolo?form_id=229863&hc_number=1200454708` => **200**
- `POST /cirugias/wizard/autosave` with fixture payload => **200** (`{"success":true}`)

Summary: **6/6 PASS**

### Exámenes
- `GET /examenes` => **200**
- `GET /examenes/turnero` => **200**
- `GET /imagenes/examenes-realizados` => **200**
- `GET /examenes/turnero-data` => **200**
- `POST /examenes/kanban-data` => **200**
- `GET /examenes/api/estado?hc_number=0906272430` => **200**
- `POST /examenes/api/estado` with `id=18918, estado=llamado` => **200/422 depending business validation state`
- `POST /examenes/actualizar-estado` with valid `id` + `estado` => **200**

Summary: baseline auth stable, some writes are state-dependent by business rules.

## Cutover interpretation
- **Doctores:** ✅ baseline ready
- **Cirugías:** ✅ baseline ready with fixture flow
- **Exámenes:** 🟡 baseline ready; write-path needs deterministic fixture policy for strict PASS-only automation

## Next action
Move to deterministic fixture seeding for Exámenes write transitions to keep smoke fully green across repeated runs.
