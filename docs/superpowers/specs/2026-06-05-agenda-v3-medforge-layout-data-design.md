# Agenda V3 MedForge Layout And Real Data Design

## Context

PR 364 introduces Agenda V3 as a React SPA backed by Laravel endpoints. The latest PR commit already changed the Blade shell to extend `layouts.medforge`, but the React app still renders its own logo, header, and sidebar. This creates duplicate navigation inside the existing MedForge chrome, consumes screen space, and can interfere with interaction.

The same PR also attempts to load real appointment data from `procedimiento_proyectado` and user data from `users`. Current data loading is fragile:

- `syncMedicosFromPP()` has a malformed `SELECT` with a trailing comma before `FROM users`; the exception is swallowed, so real doctors are not synchronized.
- SigCenter/legacy appointments can be returned without a matched `medico_id`, `sala_id`, or `tipo_id`; the calendar groups appointments by resource, so records without a resource count in totals but do not render in a doctor or room column.
- Frontend write actions derive numeric IDs with `parseInt(id.replace('C', ''))`; read-only SigCenter IDs use a `P` prefix, so those actions can produce `NaN`.

## Goal

Stabilize Agenda V3 inside the existing MedForge layout and make real appointment data visible and coherent before splitting the module into separate routes.

## Non-Goals

- Do not replace the global MedForge header or sidebar.
- Do not add separate route files for each Agenda V3 view in this phase.
- Do not make SigCenter/`procedimiento_proyectado` rows editable through the new Agenda V3 endpoints until the legacy write path is designed.
- Do not refactor unrelated MedForge layout code.

## Approved Direction

Use the existing MedForge layout as the only application shell. Agenda V3 becomes embedded content inside the MedForge content area.

The MedForge sidebar is the single navigation source for Agenda V3 views. React reads the requested view from the URL query string and renders only the selected view content.

Separate routes per view are deferred until the real-data and interaction problems are fixed.

## UX And Navigation Design

The Blade shell keeps:

- `@extends('layouts.medforge')`
- MedForge auth and permissions
- CSS and script registration for Agenda V3 assets
- `window.__MF__` boot data

The React app removes:

- `.app-logo`
- `.app-header`
- `.app-sidebar`
- the internal nav array used to render sidebar links
- the "Volver a MedForge" internal nav item

The React app keeps:

- view-specific page heading
- Agenda-specific controls such as sede switch, filters, date controls, and action buttons
- modals, toasts, FlowBoard, Mi agenda, Config, and calendar content

MedForge navigation links should target the same shell with query params:

- `/v2/agenda/v3?view=agenda`
- `/v2/agenda/v3?view=flowboard`
- `/v2/agenda/v3?view=miagenda`
- `/v2/agenda/v3?view=config`

If `view` is absent or invalid, React defaults to `agenda`.

## Component Boundaries

`App` becomes an embedded view orchestrator:

- Load config, citas, and bloqueos.
- Track the active view from `URLSearchParams`.
- Track selected sede, selected doctor, modal state, toasts, and consulta state.
- Render one content wrapper, not a full app shell.

`Calendario`, `FlowBoard`, `MiAgenda`, and `ConfigModule` remain focused view components.

Small helper functions should centralize:

- reading and validating the query-string view
- converting frontend IDs to backend IDs
- identifying read-only legacy/SigCenter records
- selecting fallback resources for incomplete legacy data

## Real Data Design

Doctor sync must be deterministic and observable:

- Fix the malformed SQL in `syncMedicosFromPP()`.
- Keep sync non-fatal for the request, but log the exception with enough context.
- Populate `agenda_medicos.user_id` when the source is `users.id`, so frontend matching can use either `usr_<id>` or explicit user relation later.

Legacy appointment normalization must ensure records can render:

- Preserve `_source: "pp"` and `_readonly: true`.
- Use a matched doctor when possible.
- If no doctor matches, assign a fallback visible resource, such as the first active doctor for the appointment sede, and include the original doctor text in `notas` or a dedicated display field.
- Assign a default sala for the appointment area and sede when the legacy row has none.
- Assign a default tipo compatible with the inferred area when the legacy row has none.
- Keep legacy rows visibly marked as SigCenter/read-only in detail views and FlowBoard.

Frontend lookups must be null-safe:

- `medico(c.medico)`, `sala(c.sala)`, `tipo(c.tipo)`, `area(c.area)`, and `sede(sedeId)` must not crash when data is missing.
- UI should display clear fallback labels like "Sin médico asignado" or "SigCenter" instead of failing silently.

## Interaction Design

Editable Agenda V3 rows continue using new endpoints.

Read-only SigCenter rows:

- can be shown in calendar, FlowBoard, and detail modal
- can be opened for inspection
- do not show create/update/cancel/advance buttons that call V3 endpoints
- do not call V3 write endpoints with `NaN` IDs

Frontend write handlers must use the normalized `_dbId` and `_readonly` fields instead of parsing display IDs blindly.

## Error Handling

Initial load:

- show loading state inside the MedForge content area, not as a full-screen replacement for the global layout
- show a retry action if config/citas/bloqueos fail

Backend:

- return JSON errors for API failures
- log non-fatal sync failures
- do not swallow SQL or schema failures that directly explain missing real data

Frontend:

- show toast messages for failed mutations
- disable or hide write actions for read-only rows
- avoid uncaught render errors from missing catalog entries

## Testing And Verification

Manual verification:

- `/v2/agenda/v3?view=agenda` renders inside MedForge with only one global header and one global sidebar.
- `/v2/agenda/v3?view=flowboard` renders FlowBoard from the MedForge sidebar without an internal sidebar.
- Real rows from `procedimiento_proyectado` appear in calendar columns and FlowBoard cards.
- SigCenter/read-only rows can be opened but do not expose broken write actions.
- New V3 rows can still be created and appear in the calendar.

Code verification:

- PHP syntax check for `AgendaV3Controller.php`.
- PHP syntax check for `v3-shell.blade.php`.
- Browser verification of layout after changes if the local app can run.

## Future Phase

After this stabilization ships, split Agenda V3 views into explicit routes:

- `/v2/agenda/v3`
- `/v2/agenda/v3/flowboard`
- `/v2/agenda/v3/mi-agenda`
- `/v2/agenda/v3/config`

Those routes should still share the same embedded React assets and MedForge layout, but the URL structure will no longer depend on query params.
