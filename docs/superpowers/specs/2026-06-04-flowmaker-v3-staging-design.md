# Flowmaker V3 Staging Design

Date: 2026-06-04

## Objective

Build `/v3/whatsapp/flowmaker` as the staging-only candidate for the future production Flowmaker experience. V3 will use the new React visual builder from the Flowmaker V3 mockup, but it must keep the functional behavior of the current V2 Flowmaker by using the existing Laravel Flowmaker APIs and services as the source of truth.

`/v2/whatsapp/flowmaker` remains unchanged and available as the operational fallback during staging validation.

## Current Context

The current V2 Flowmaker already provides the production-critical behavior:

- Contract loading through `/v2/whatsapp/api/flowmaker/contract`.
- Publishing through `/v2/whatsapp/api/flowmaker/publish`.
- Simulation through `/v2/whatsapp/api/flowmaker/simulate`.
- Sandbox draft testing through `/v2/whatsapp/api/flowmaker/sandbox/*`.
- Readiness, shadow runs, and legacy comparison endpoints.
- Runtime persistence through the Laravel `whatsapp_autoresponder_*` tables.
- Backend logic in `FlowmakerService`, `FlowRuntimePreviewService`, sandbox services, and shadow observer services.

The PR 357 V3 experiment added useful React visual-builder pieces, but its backend integration created a parallel CRUD path around a `flows` table. That path is not the target architecture because it does not publish to the existing runtime contract.

## Design Decision

V3 will imitate V2's functional contract and improve the user experience with React.

V3 will not clone V2's Blade UI or internal DOM structure. It will use the V3 canvas, palette, inspector, phone preview, and visual structure ideas as the basis for the new interface.

V3 will not create a second source of truth for Flowmaker data. Any V3-specific graph state must compile back to the existing Flowmaker contract before publishing.

## Route Scope

The staging candidate route is:

```text
/v3/whatsapp/flowmaker
```

The route should be protected with the same authentication, permission, and WhatsApp feature constraints used by the current Flowmaker surface.

During this phase:

- `/v2/whatsapp/flowmaker` keeps serving the existing V2 UI.
- `/v3/whatsapp/flowmaker` serves the new React UI.
- V3 includes a visible fallback link back to V2 for operators.
- Production cutover is outside this implementation phase.

## Frontend Architecture

Create a dedicated Vite React entry for Flowmaker V3 under `laravel-app/resources/js`.

Use the local mockup files from `/Users/jorgeluisdevera/Downloads/flowmaker_v2` as source material, especially:

- `canvas.jsx`
- `nodes.jsx`
- `inspector.jsx`
- `phone.jsx`
- `structure.jsx`
- `flowmaker.css`
- `app.jsx`
- `data.js`
- `util.js`

Port them into module-based React instead of browser globals and Babel-in-browser scripts. The first implementation can keep the component boundaries close to the mockup, but the data and API layers must be separated from presentation.

Expected frontend units:

- `FlowmakerV3App`: top-level layout and editor state.
- `FlowCanvas`: pan, zoom, drag, connect, select, and delete graph operations.
- `NodePalette`: node creation surface.
- `NodeInspector`: type-specific node editors.
- `PhonePreview`: local visual preview that can also display authoritative backend simulation results.
- `StructurePanel`: compiled graph/contract visibility for debugging.
- `flowmakerApi`: typed adapter for Laravel endpoints.
- `graphCompiler`: transforms visual graph state into the current Flowmaker contract.

## Backend/API Architecture

Reuse existing V2 APIs first:

- `GET /v2/whatsapp/api/flowmaker/contract`
- `POST /v2/whatsapp/api/flowmaker/publish`
- `GET|POST /v2/whatsapp/api/flowmaker/simulate`
- `GET /v2/whatsapp/api/flowmaker/compare`
- `GET /v2/whatsapp/api/flowmaker/readiness`
- `GET /v2/whatsapp/api/flowmaker/shadow-runs`
- `GET /v2/whatsapp/api/flowmaker/shadow-summary`
- `GET /v2/whatsapp/api/templates`
- `GET /v2/whatsapp/api/knowledge-base`
- `POST /v2/whatsapp/api/media/upload`

If React needs a cleaner payload shape, add a V3 adapter endpoint only as a thin Laravel controller layer. That adapter must call existing services instead of duplicating runtime logic.

Do not add a new `flows` table, `Flow` model, or standalone Flowmaker V3 CRUD backend for this phase.

## Data Flow

Initial load:

1. Blade renders `whatsapp.v3-flowmaker` with CSRF token and endpoint configuration.
2. React loads the current Flowmaker contract.
3. React converts the contract into editable visual graph state.
4. The canvas renders nodes, edges, side panels, and preview.

Editing:

1. Operators edit graph nodes and edges visually.
2. Inspector changes mutate graph state only.
3. Unsaved and validation status are shown in the top bar.

Publishing:

1. `graphCompiler` converts graph state to the existing `flow` contract.
2. Frontend validates obvious graph errors before sending.
3. Laravel `FlowmakerService::publish()` remains the publishing authority.
4. Backend validation errors are surfaced inline and do not clear dirty state.

Simulation:

1. V3 sends the compiled draft contract to the existing simulate endpoint.
2. Backend simulation remains authoritative.
3. Phone preview can show local lightweight preview, but simulation results must be available for real validation.

## Graph Compiler Requirements

The compiler converts visual nodes and edges into:

```text
flow.name
flow.description
flow.settings
flow.scenarios[]
flow.scenarios[].conditions[]
flow.scenarios[].actions[]
```

The compiler must support the first staging scope:

- Keyword trigger or incoming-message trigger.
- Text message actions.
- Media message actions.
- Quick reply/button actions with the existing button limit.
- Template actions using real template identifiers.
- Conditional branches mapped to existing condition types.
- AI agent actions where supported by the current V2 contract.
- Handoff/end actions.

Unsupported graph constructs must produce clear validation errors instead of silently publishing lossy data.

## Error Handling

V3 must distinguish:

- Network/API failures.
- Auth/permission failures.
- Backend validation failures.
- Graph validation failures.
- Unsupported compiler mappings.

Publishing must not mark the graph as saved unless the backend returns a successful publish response.

## Staging Safety

This phase is staging-first and keeps V2 intact:

- No production route cutover.
- No replacement of `/v2/whatsapp/flowmaker`.
- No destructive migration.
- No new runtime storage table for V3.
- No removal of current V2 tests.

If staging V3 fails, operators can immediately use `/v2/whatsapp/flowmaker`.

## Testing Plan

Backend/feature tests:

- `/v3/whatsapp/flowmaker` renders for authorized users.
- V3 route uses the expected permissions.
- Existing V2 Flowmaker route still renders.
- Existing publish tests still pass.

Frontend/unit tests where feasible:

- Graph compiler maps a simple trigger -> message flow to a valid V2 contract.
- Graph compiler maps quick replies to action/transition data without exceeding the button limit.
- Graph compiler rejects graph states with no trigger, disconnected required nodes, or unsupported mappings.

Manual staging verification:

- Open `/v3/whatsapp/flowmaker`.
- Load current contract.
- Create or edit a small flow visually.
- Simulate against a draft.
- Publish through the existing Laravel API.
- Confirm database/runtime tables update through the existing publish path.
- Confirm `/v2/whatsapp/flowmaker` still works as fallback.

## Out Of Scope

- Production cutover.
- Deleting V2.
- Replacing the Flowmaker runtime.
- Adding a parallel `flows` CRUD backend.
- Full redesign of templates, knowledge base, media library, or AI agent management.
- Rewriting `FlowmakerService` beyond adapter changes needed for React.

## Acceptance Criteria

- `/v3/whatsapp/flowmaker` serves the React V3 builder in staging.
- V3 loads real Flowmaker data from Laravel APIs.
- V3 publishes through the existing Flowmaker publish service.
- V3 does not introduce a parallel runtime source of truth.
- V2 remains available and unchanged.
- Tests and manual checks prove the staging route is safe to evaluate.
