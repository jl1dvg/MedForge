# CRM Opportunity Model Redesign
**Date:** 2026-06-08
**Branch:** test/magical-ptolemy (future: new branch per phase)
**Status:** Approved — ready for implementation planning

---

## Problem Statement

The current `crm_opportunities` table enforces `UNIQUE(contact_id)`, meaning one opportunity per patient. This is architecturally incorrect for MedForge because a single patient may have multiple independent clinical/commercial decisions (Faco OD, Faco OI, a retina treatment series, etc.). As a result:

- A WhatsApp lead that converts to a surgical patient contaminates the same record with clinical data
- Multiple solicitudes for the same patient collapse into a single card in the Kanban
- Legacy leads with associated solicitudes cannot be separated
- Exam records and surgical records are mixed as if they were the same commercial intent

---

## Approved Conceptual Model

### Three distinct entities

```
crm_leads                crm_opportunities              clinical_items
─────────────            ──────────────────             ─────────────────
Captación pipeline       THE Kanban card                Feed the opportunity
WhatsApp, Ads,           One clinical/commercial        solicitud_procedimiento
referidos                decision per card              consulta_examenes
                                                        crm_activities
Lead ≠ opportunity       Examples:                      (future: crm_clinical_items)
Lead converts → links    - Faco OD
to a clinical opp        - Pterigion OI
                         - Serie retina OD
```

**Key rule:** A lead does not transform into an opportunity. It converts and *links* to a clinical opportunity. The lead remains as a historical captación record.

**Key rule:** A surgical solicitud and the resulting surgery are **the same commercial opportunity**, not separate entities. The solicitud represents the clinical/commercial intention to perform a procedure; the surgery is the expected outcome of that same opportunity. The commercial stage advances as the operational modules report progress.

**Key rule:** `consulta_examenes` does not automatically generate a surgical opportunity. It feeds an existing one. Exception: if the exam is the primary commercial service (private diagnostic exam), it can generate its own `diagnostica`-type opportunity when `crm_procedure_rules.genera_oportunidad = TRUE`.

**Key rule:** The commercial CRM does not duplicate the operational CRMs (Solicitudes, Imágenes). It consumes their events and signals as a higher-level executive/commercial dashboard. The operational modules remain the source of truth for clinical and administrative execution.

---

## Opportunity Identity Rule

A `crm_opportunity` is identified by:

```
contact_id + procedure_group + lateralidad + [active episode check]
```

- **`procedure_group`** — exact code or the group defined in `crm_procedure_rules.grupo_codigo`. Multiple codes sharing a group (e.g., Avastin + Eylea → `inyeccion_intravitrea`) belong to the same episode.
- **`lateralidad`** — `VARCHAR(10)`, normalized in the Service. Values in data include `D`, `I`, `OD`, `OI`, `AO`, `D,I`, `null`. The Service normalizes on write. If `agrupar_por_ojo = FALSE` in the rule, lateralidad is ignored in the lookup (OD and OI treated as same opportunity).
- **Active episode** — not a column, a lookup condition: does an open (not `ganado`/`perdido`) opportunity exist for this combo? Multiple closed historical opps for the same combo are valid and expected.

The `UNIQUE(contact_id)` constraint is removed. Uniqueness is enforced by the Service via this lookup rule, not by the database.

---

## New Table: `crm_procedure_rules`

### Design principle

Classification of procedure codes **must not be derived from code format or prefix**. MedForge uses multiple coding systems with no consistent structure:

- `CYP-CCA-001`, `CYP-RVI-009` — internal CIVE codes
- `66984`, `67028` — numeric CPT codes
- Internal legacy codes with arbitrary formats

The `crm_procedure_rules` table is the single authoritative source for how each code behaves commercially. No hardcoded prefix or pattern matching.

```sql
crm_procedure_rules
  id
  codigo              VARCHAR(50)     -- exact code (CYP-CCA-001, 66984, etc.)
  grupo_codigo        VARCHAR(100)    -- groups multiple codes into one episode (nullable)
  nombre              VARCHAR(200)    -- human-readable label for admin UI
  tipo                VARCHAR(20)     -- 'unica' | 'recurrente' | 'diagnostico'
  ventana_dias        INT             -- recurrente only: days after closure → new episode
  agrupar_por_ojo     TINYINT(1)      -- DEFAULT 1: OD and OI are separate opps
  genera_oportunidad  TINYINT(1)      -- DEFAULT 1: auto-creates opp on event
  activo              TINYINT(1)      -- DEFAULT 1

  -- Future extension columns (add in a later phase, not Phase 0)
  -- categoria         VARCHAR(50)    -- e.g. 'cirugia_refractiva', 'retina', 'oculoplastia'
  -- subcategoria      VARCHAR(50)
  -- especialidad      VARCHAR(50)
  -- tipo_servicio     VARCHAR(50)    -- 'quirurgico' | 'diagnostico' | 'tratamiento' | 'consulta'

  created_at, updated_at
```

### What each rule answers

For a given procedure code, `crm_procedure_rules` defines:

| Question | Column |
|---|---|
| Does this procedure generate a commercial opportunity? | `genera_oportunidad` |
| Is it a one-time procedure, a recurrent treatment, or a diagnostic exam? | `tipo` |
| How many days before a new episode is considered independent? | `ventana_dias` |
| Are the left eye and right eye separate commercial opportunities? | `agrupar_por_ojo` |
| Do multiple codes collapse into one opportunity (e.g., Avastin + Eylea)? | `grupo_codigo` |
| What human-readable name appears in the Kanban card? | `nombre` |

### Tipo behavior

| tipo | Active opp found | Closed opp found | No opp |
|---|---|---|---|
| `unica` | Add as clinical item (reprogramación/admin duplicate) | Create new opp (true recurrence) | Create new opp |
| `recurrente` | Add as clinical item (next session) | < ventana_dias → create new opp + `continuity_flag` | Create new opp |
| `recurrente` | — | ≥ ventana_dias → create new opp (new episode) | — |
| `diagnostico` | Add as clinical item | Create new opp | Create new opp |

**No rule found → fallback:** `tipo='unica'`, `genera_oportunidad=TRUE`, `agrupar_por_ojo=TRUE`. Behavior is conservative.

---

## New Columns in `crm_opportunities`

```sql
ALTER TABLE crm_opportunities
  ADD COLUMN procedure_group        VARCHAR(100) NULL,
  ADD COLUMN lateralidad            VARCHAR(10)  NULL,
  ADD COLUMN episode_started_at     TIMESTAMP    NULL,
  ADD COLUMN previous_opportunity_id BIGINT      NULL,   -- FK self, ON DELETE SET NULL
  ADD COLUMN opportunity_type       VARCHAR(20)  NULL,   -- 'quirurgica'|'tratamiento'|'diagnostica'|'manual'
  ADD COLUMN continuity_flag        TINYINT(1)   NOT NULL DEFAULT 0;
```

`previous_opportunity_id` (formerly `related_opp_id`) links to the most recent closed opportunity for the same `procedure_group + lateralidad` combo when `continuity_flag = 1`. It signals "possible continuation of prior episode" for agent review.

---

## New Table: `crm_leads`

```sql
crm_leads
  id
  canal               VARCHAR(30)     -- 'whatsapp' | 'ads' | 'referido' | 'web' | 'manual'
  source_id           BIGINT NULL     -- FK to origin record (whatsapp_leads.id, etc.)
  source_type         VARCHAR(50) NULL
  contact_id          BIGINT          -- FK crm_contacts (may be provisional resolution)

  estado              VARCHAR(20)     -- 'nuevo' | 'contactado' | 'posible_conversion'
                                      --   | 'convertido' | 'descartado'
  motivo_descarte     VARCHAR(500) NULL

  converted_at               TIMESTAMP NULL
  converted_by_user_id       BIGINT NULL
  converted_opportunity_id   BIGINT NULL     -- FK crm_opportunities (primary/first clinical opp)
                                             -- Future: crm_lead_conversions table for 1:N

  assigned_to         BIGINT NULL
  last_activity_at    TIMESTAMP NULL
  created_at, updated_at
```

### Conversion flow

```
lead.estado: nuevo → contactado → posible_conversion → convertido
                                                      → descartado
```

**Trigger C (system-detected):** When `patient_data` is created with a phone or cédula matching a lead contact, the system sets `estado = 'posible_conversion'` and notifies the assigned agent. **No auto-conversion.** Shared phones and family members make full automation unsafe.

**Trigger D (manual):** Agent explicitly marks the lead as converted, selects or creates the linked clinical opportunity, and fills `converted_opportunity_id`.

**After conversion:** The lead exits the captación Kanban. It remains as an immutable historical record. Metrics queries (conversion rate, channel ROI) use this table.

**Future:** If a lead generates more than one clinical opportunity (e.g., WhatsApp → Faco OD + Faco OI), a `crm_lead_conversions` table (`lead_id`, `opportunity_id`, `conversion_type`, `created_at`) will replace `converted_opportunity_id`. The single FK is sufficient for Phase 1.

---

## `upsertFromEvent()` Algorithm (Phase 2 behavior)

Only active when feature flag `crm.intent_model_enabled = true`.

```
INPUT: contact_id, procedure_code, lateralidad_raw, source_id, source_type, event_type

1. NORMALIZE lateralidad:
   'D' → 'OD', 'I' → 'OI', 'D,I' → 'AO', etc.

2. FIND RULE:
   rule = crm_procedure_rules WHERE codigo = procedure_code AND activo = 1
   fallback if not found: tipo='unica', genera_oportunidad=1, agrupar_ojo=1

3. SKIP OPP CREATION?
   if rule.genera_oportunidad = 0:
     save as pending clinical_item (solicitud_procedimiento.crm_opportunity_id = NULL)
     emit event: ClinicalItemPendingLinkage
     RETURN

4. DEFINE LOOKUP KEY:
   group_key = rule.grupo_codigo ?? procedure_code
   ojo_key   = rule.agrupar_por_ojo ? normalized_lateralidad : null

5. FIND ACTIVE OPP:
   opp = crm_opportunities WHERE contact_id=? AND procedure_group=group_key
                                 AND lateralidad=ojo_key
                                 AND stage NOT IN ('ganado','perdido')

6. APPLY RULE → RESOLVE ACTION:
   (see tipo behavior table above)

7. IF CREATE NEW OPP:
   Fill: contact_id, procedure_group, lateralidad, opportunity_type (from rule/event_type),
         episode_started_at=now(), source_id, source_type, title (derived),
         previous_opportunity_id (if closed opp found within window),
         continuity_flag (1 if previous_opportunity_id set)

8. LINK clinical item:
   solicitud_procedimiento.crm_opportunity_id = opp.id (or new opp.id)
```

---

## Feature Flag

```php
// config/crm.php
'intent_model_enabled' => env('CRM_OPPORTUNITY_MODEL', 'legacy') === 'intent',
```

- `CRM_OPPORTUNITY_MODEL=legacy` (default) → current behavior, no change
- `CRM_OPPORTUNITY_MODEL=intent` → new algorithm active

This allows deploying Phase 2 code without activating it, testing in staging with the flag enabled, and rolling back by resetting the env var without reverting code.

---

## Migration Phases (Enfoque C)

### Phase 0 — Rule Governance (prerequisite for all phases)

**Goal:** Populate `crm_procedure_rules` with accurate commercial behavior before any algorithmic change. The entire new model depends on these rules. Without them, the fallback (`tipo='unica'`, `genera_oportunidad=TRUE`) applies to everything, producing incorrect results.

**Deliverables:**
- Admin UI (or seed script) for creating and editing procedure rules
- Initial population of the most frequent procedure codes from `solicitud_procedimiento`
- Validation query: check that all `codigo` values in the last 90 days have a matching rule

**Minimum viable ruleset before Phase 2 activation:**

```
-- All procedure codes appearing in solicitud_procedimiento in the last 90 days
-- must have an explicit rule. This query must return 0 rows before Phase 2 go-live.

SELECT DISTINCT sp.procedimiento_codigo
FROM solicitud_procedimiento sp
LEFT JOIN crm_procedure_rules cpr ON cpr.codigo = sp.procedimiento_codigo AND cpr.activo = 1
WHERE sp.created_at >= NOW() - INTERVAL 90 DAY
  AND cpr.id IS NULL;
```

**What does NOT block Phase 0:**
- New columns in `crm_opportunities` (those are Phase 1)
- The feature flag (Phase 2)
- Any behavior change in `upsertFromEvent()`

Phase 0 can run in parallel with Phase 1 infrastructure. Both must be complete before Phase 2 activation.

---

### Phase 1 — Infrastructure (zero behavior change)

**Migrations:**
- Create `crm_procedure_rules`
- Create `crm_leads`
- Add nullable columns to `crm_opportunities` (procedure_group, lateralidad, episode_started_at, previous_opportunity_id, opportunity_type, continuity_flag)
- Drop `UNIQUE(contact_id)`, add regular index `idx_crm_opp_contact`

**Rollback:** Fully reversible — drop new tables, drop new columns, recreate UNIQUE constraint. Safe because no multiple-contact-opps exist yet.

**⚠ Rollback window closes after Phase 2 activation:** Once `CRM_OPPORTUNITY_MODEL=intent` is active in production and multiple opportunities per contact have been created, restoring `UNIQUE(contact_id)` requires deduplication first. Document this clearly in the Phase 2 runbook.

---

### Phase 2 — New events follow correct model

**Prerequisites:** Phase 1 complete. `crm_procedure_rules` populated with at least the most frequent procedure codes before activation.

**Code changes:**
- `upsertFromEvent()` implements the new algorithm, gated by `crm.intent_model_enabled`
- WhatsApp events create `crm_leads` records (not `crm_opportunities`) when flag is active
- Conversion detection: `patient_data` creation triggers lead status → `posible_conversion`, agent notification
- New opportunities fill all new columns

**Activation:** Set `CRM_OPPORTUNITY_MODEL=intent` in staging → validate → set in production.

**Rollback:** Reset env var to `legacy`. Historical records created under the new model remain but do not break the old path (new columns are ignored by legacy code).

---

### Phase 3 — Backfill of historical data

**Principle:** Conservative. Automatic migration only for unambiguous cases. Everything else goes to a manual review queue visible to an authorized operator in the UI.

**Scripts (run in order, staging first):**

```
Script A — Classify existing opps by opportunity_type
  AUTO: source_type='solicitud_procedimiento' → 'quirurgica' (or per rule if exists)
  AUTO: source_type='consulta_examenes'       → 'diagnostica'
  AUTO: source_type='whatsapp_lead' with 0 SP linked → candidate for crm_leads migration
  AUTO: source_type='legacy_crm_lead' with 0 SP linked → 'manual', no migration
  QUEUE: everything else → manual review

Script B — Migrate whatsapp-source opps to crm_leads
  CRITERIA FOR AUTO-MIGRATION (all must be true):
    - source_type = 'whatsapp_lead'
    - exactly 1 solicitud_procedimiento linked
    - contact has unique phone match (no family/shared phone ambiguity)
    - stage is not ganado/perdido (already closed, safer to leave)
  → create crm_lead + crm_opportunity with correct type
  → set lead.converted_opportunity_id
  ALL OTHER cases → manual review queue

Script C — Populate procedure_group, lateralidad on existing opps
  AUTO: if 1 solicitud linked → use its codigo + ojo as group/lateralidad
  QUEUE if: 0 solicitudes, or multiple solicitudes with different codes/eyes,
            or no ojo defined, or contradictory data

Script D — Legacy opps with associated solicitudes
  All cases go to MANUAL REVIEW queue. No automatic action.
  Reason: legacy records may have longitudinal history that requires
          clinical judgment to split correctly.
```

**Rollback:** Scripts only write to new columns and new tables. Original `crm_opportunities` columns are untouched. If backfill produces errors, new column values can be nulled and `crm_leads` truncated without affecting production.

---

### Phase 4 — Cleanup and full activation

- Remove all code that assumes `UNIQUE(contact_id)` (tests, validators, comments)
- Activate `continuity_flag` alert in Kanban UI ("possible continuation of prior episode")
- Activate manual review UI for Phase 3 queue
- Add composite index: `(contact_id, procedure_group, lateralidad)` for Service lookup performance
- Archive legacy opps with no clinical source after configurable inactivity window
- Prepare `crm_lead_conversions` schema as a future-ready migration (not yet activated)

---

## Relationship Between Commercial CRM and Operational CRMs

### Architecture

```
CRM Comercial  (crm_opportunities — executive/commercial dashboard)
      ↑
      │  consumes events and signals
      │
  ┌───┴───────────────────────────────────┐
  │                                       │
CRM Solicitudes                    CRM Imágenes / Exámenes
(solicitud_procedimiento)          (consulta_examenes)
Source of truth for:               Source of truth for:
- surgical workflow                - exam workflow
- authorization checklist          - results, lateralidad
- scheduling & confirmation        - exam-to-procedure linkage
- surgery execution                - pre-op exam completeness
```

The commercial CRM does not own or replicate any of this operational data. It reads signals from operational state changes and advances the commercial stage accordingly.

---

### Official event-emitting modules (Phase 1–4 scope)

The following operational modules are the only authorized sources of events into the commercial CRM in this design:

| Module | Event channel |
|---|---|
| **CRM Solicitudes** (`solicitud_procedimiento`) | Stage transitions, scheduling, surgery completion |
| **CRM Imágenes / Exámenes** (`consulta_examenes`) | Clinical item linkage, pre-op workup signals |
| **WhatsApp** (`whatsapp_leads`) | Captación, lead lifecycle (nuevo → convertido) |

**Billing integration is explicitly deferred.** When billing events are eventually integrated, they will follow the same event-driven pattern via `crm_stage_mappings`. No billing columns or billing-derived transitions are included in this design.

---

### What flows from CRM Solicitudes into the commercial opportunity

| Operational event / state | Commercial effect |
|---|---|
| `SolicitudCreada` (nueva solicitud) | Creates or updates commercial opp; `stage → nuevo` or adds as clinical item |
| Solicitud passes to `pendiente_autorizacion` | Signal: authorization required — can trigger alert or escalation_at |
| Solicitud passes to `programada` | `stage → comprometido`; `escalation_at` cleared |
| Solicitud passes to `confirmada` | No stage change; checklist signal: confirmed date available |
| Solicitud passes to `realizada` (surgery done) | `stage → ganado`; `last_activity_at` updated |
| Solicitud passes to `cancelada` | Alert: review required. Stage moves to `perdido` only if cancellation is definitive (motivo_baja defines this) |
| `SolicitudKanbanEstadoCambiado` event | Maps kanban state → commercial stage via `crm_stage_mappings` table |

**What the commercial CRM does NOT store from Solicitudes:**
- Authorization documents or codes
- Surgical scheduling details (date, OR, anesthesiologist)
- Equipment or implant selections (lente, producto)
- Detailed clinical notes
- Billing or prefactura data

---

### What flows from CRM Imágenes / Exámenes into the commercial opportunity

| Operational event / state | Commercial effect |
|---|---|
| `ExamenSolicitado` (exam ordered) | Links exam as clinical item to matching opp; does not change commercial stage |
| Exam results available | Signal: pre-op workup progressing — can update a `checklist_score` or signal readiness |
| All required exams completed for a surgical opp | Signal: pre-op complete — can auto-advance or alert coordinator |
| `ExamenEstadoCambiado` | Maps exam state → commercial stage via `crm_stage_mappings` if applicable |

**What the commercial CRM does NOT store from Exámenes:**
- Exam results, measurements, images
- Lateralidad or biometry values (these stay in `consulta_examenes`)
- Exam-level billing

---

### Commercial stages and their operational triggers

```
Lead nuevo          ← WhatsApp / Ads event
     │
Contactado          ← Agent action (manual)
     │
En evaluación       ← First solicitud or exam linked
     │
En coordinación     ← Solicitud reaches 'pendiente_autorizacion' or 'pendiente_documentos'
     │
Programado          ← Solicitud reaches 'programada' or 'confirmada'
     │
Ganado              ← Solicitud reaches 'realizada' (surgery done)
     │
Perdido             ← Solicitud cancelled with definitive motivo_baja
                      OR agent explicitly closes as lost
```

Stage advancement follows the existing `crm_stage_mappings` table (already in production). Stages never regress automatically — only advance. Regression requires an explicit agent action.

---

### What the commercial CRM must NOT duplicate

| Already owned by operational module | Commercial CRM behavior |
|---|---|
| Surgical scheduling (date, OR, anesthesiologist) | Read-only signal from solicitud |
| Authorization codes and documents | Checklist signal only (complete/incomplete) |
| Exam results and measurements | Not stored; linked as clinical items |
| Billing, prefactura, IVA | Not stored; `valor_estimado` is a display-only estimate |
| Clinical notes and observations | Operational modules own these |
| Patient medical history | `patient_data` owns this |

---

### Stage Mapping: Operational Events → Commercial Stages

The `crm_stage_mappings` table makes the commercial CRM configurable without code changes. Every automatic stage transition is driven by a row in this table.

```sql
crm_stage_mappings
  id
  source_module         VARCHAR(50)     -- 'solicitudes' | 'examenes' | 'whatsapp'
  source_event          VARCHAR(100)    -- event class name (e.g. 'SolicitudCreada')
  source_state          VARCHAR(100)    -- operational state value (e.g. 'programada')
  target_stage          VARCHAR(30)     -- target crm_opportunities.stage value (nullable = no stage change)
  auto_transition       TINYINT(1)      -- 1 = apply automatically; 0 = suggestion only
  only_forward          TINYINT(1)      -- DEFAULT 1: never regress stage via this mapping
  requires_agent_review TINYINT(1)      -- DEFAULT 0: if 1, flag opp for coordinator attention
  active                TINYINT(1)      -- DEFAULT 1
  created_at, updated_at
```

#### Solicitudes mappings

| `source_event` / `source_state` | `target_stage` | `auto_transition` | `requires_agent_review` | Notes |
|---|---|---|---|---|
| `SolicitudCreada` | `nuevo` | 1 | 0 | Creates opp or links as clinical item |
| `pendiente_autorizacion` | `en_coordinacion` | 1 | 0 | Authorization required |
| `pendiente_documentos` | `en_coordinacion` | 1 | 0 | Documents incomplete |
| `programada` | `comprometido` | 1 | 0 | Surgery scheduled |
| `confirmada` | `comprometido` | 1 | 0 | `only_forward=1`: no regress if already `comprometido` |
| `realizada` | `ganado` | 1 | 0 | Surgery completed |
| `cancelada` (definitive `motivo_baja`) | `perdido` | 1 | 0 | Mapped per `motivo_baja` in a separate config |
| `cancelada` (recoverable or unknown motivo) | `null` | 0 | 1 | No stage change; flag for agent |

#### Imágenes / Exámenes mappings

| `source_event` / `source_state` | `target_stage` | `auto_transition` | Notes |
|---|---|---|---|
| `ExamenSolicitado` | `null` | 0 | Links as clinical item only; no stage change |
| `ExamenRealizado` | `null` | 0 | Signals pre-op progress; updates score (future) |
| All required pre-op exams completed | `null` | 0 | Checklist signal; may trigger alert or `escalation_at` |

#### WhatsApp mappings

| `source_event` / `source_state` | `target_stage` | `auto_transition` | Notes |
|---|---|---|---|
| Lead created | `nuevo` | 1 | Creates `crm_lead` row (not opp) |
| Lead contacted (agent action) | `contactado` | 1 | Advances lead estado |
| `posible_conversion` detected | `null` | 0 | Agent notification only; no auto-advance |
| Lead converted (manual) | — | — | Lead exits captación pipeline; links to clinical opp |

#### Invariants enforced by `crm_stage_mappings`

- `only_forward = 1` on all rows by default: a stage never regresses automatically
- Stage regression requires an explicit agent action outside this table
- `requires_agent_review = 1` queues the opportunity for coordinator attention without changing stage
- Rows with `target_stage = null` produce no stage change but still fire the signal (score update, alert, clinical item link)

---

### Traceability: operational event → commercial update

Every state change driven by an operational module is recorded in `crm_activities` with:
- `type`: the operational event type (`cambio_etapa`, `solicitud`, `examen`, etc.)
- `source_type` + `source_id`: FK back to the originating operational record
- `description`: human-readable summary

This creates a full audit trail of what moved the commercial opportunity and why, without copying operational data into the CRM.

---

### Funnel metrics enabled by this architecture

Because every transition is event-driven and timestamped, the following conversion metrics become computable without manual data entry:

| Metric | From → To | Source |
|---|---|---|
| Lead → solicitud conversion rate | `crm_leads.converted_at` vs `crm_leads.created_at` | crm_leads |
| Lead → solicitud time (days) | `episode_started_at` − `crm_leads.created_at` | join |
| Solicitud → programación time | `comprometido` activity timestamp − `nuevo` activity | crm_activities |
| Programación → surgery time | `ganado` activity − `comprometido` activity | crm_activities |
| Surgery → billing time | Deferred — requires billing event integration | future |
| Cancellation rate by motivo | `perdido` opps grouped by `lost_reason` | crm_opportunities |
| Bottleneck stage | Avg time spent in each stage | crm_activities timestamps |
| Channel ROI | Opps ganadas grouped by `crm_leads.canal` | join crm_leads + opps |

These metrics require no new data — they are derived from timestamps already recorded by the event-driven architecture.

## Future Capability: Opportunity Health Score

**Not implemented in any phase of this spec. Documented here to guide future schema decisions.**

### Concept

`crm_opportunities.opportunity_score` — a dynamic 0–100 integer reflecting the current health and likelihood of conversion of a commercial opportunity. Calculated from events, not entered manually.

```sql
-- Future columns (do NOT add yet — Phase 4+ decision)
opportunity_score    TINYINT UNSIGNED NULL   -- 0–100 composite health score
score_updated_at     TIMESTAMP NULL          -- last recalculation
score_reason         JSON NULL               -- breakdown of contributing signals
```

### Signal model (illustrative, not final)

| Signal | Points |
|---|---|
| Lead contacted (at least one outbound touchpoint) | +10 |
| Initial consultation completed | +15 |
| Surgical solicitud created | +20 |
| Pre-op exams all completed | +15 |
| Authorization documents complete | +10 |
| Insurance/authorization approved | +15 |
| Procedure scheduled (`programada`) | +25 |
| Patient confirmed for surgery date | +15 |
| No activity for > 7 days (open opp) | −10 |
| No activity for > 14 days | −20 |
| Solicitud cancelled with recoverable motivo | −15 |
| Solicitud cancelled with definitive motivo | → opp closed as perdido |

### Data sources for score computation

**In scope (Phases 1–4):**
- `crm_leads` — captación and conversion signals (WhatsApp module)
- `solicitud_procedimiento` — authorization and scheduling states (Solicitudes module)
- `consulta_examenes` — pre-op workup completeness (Imágenes/Exámenes module)
- `crm_activities` — activity recency and frequency (all modules)

**Deferred:**
- Billing events — explicitly out of scope for this design; will follow same event-driven pattern when integrated
- Operational checklists — future integration

### Use cases enabled

- **Kanban prioritization:** sort cards by score to surface high-risk / high-value opportunities
- **Stagnation alerts:** score below threshold triggers automated escalation_at
- **Conversion prediction:** historical score-at-conversion data trains a probability model
- **Coordinator dashboards:** aggregate score distribution by doctor, sede, or procedure group
- **AI commercial assistant:** score feeds a recommendation engine for next best action

### Design notes for future implementation

- Score is **computed on write** (event-driven recalculation) not on read — avoids query cost
- `score_reason` JSON stores the breakdown so the UI can explain why a score changed
- Score never drives automatic stage changes — it informs agents, not the system
- The scoring function should be configurable per `opportunity_type` (a lead-only opp has different weights than a surgical opp at `en_coordinacion`)

---

## What Stays the Same (out of scope for this design)

- `crm_contacts` structure — no changes
- `crm_activities` — no structural changes in Phase 1-2; polymorphic association to leads is a Phase 4+ concern
- `/crm/opportunities` API endpoint — continues to serve current model until Phase 2 is validated
- The Kanban UI — no changes until Phase 3 data is clean enough to rely on the new model

---

## Open Questions (deferred)

1. `crm_activities` polymorphic: should activities associate to `crm_leads` as well? Deferred to Phase 4.
2. `crm_lead_conversions` (1:N): when a lead generates multiple opportunities (Faco OD + Faco OI), this table replaces `converted_opportunity_id`. Deferred — single FK is sufficient for Phase 1.
3. Exam grouping by visit/order: `consulta_examenes` records that arrive without a linked opportunity and need grouping by date or medical order. Deferred to Phase 3 Script C refinement.
4. The `ClinicalItemPendingLinkage` event: what UI surfaces the pending-linkage queue and who resolves it? Deferred to Phase 2 UI design.
