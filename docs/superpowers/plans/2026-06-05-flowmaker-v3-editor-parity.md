# Flowmaker V3 Editor Parity Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make `/v3/whatsapp/flowmaker` edit the real V2 Flowmaker scenario conditions and action payloads without degrading them when publishing from the React graph.

**Architecture:** Keep Laravel V2 APIs and runtime tables as the source of truth. Add a React-side action catalog that normalizes V2 actions into editable node data, renders specialized inspector forms, and compiles edited nodes back into the same V2 `flow.scenarios[].conditions/actions[]` contract.

**Tech Stack:** Laravel Blade/Vite shell, React 19, plain JavaScript modules, existing `/v2/whatsapp/api/flowmaker/*` endpoints, Node smoke tests.

---

### Task 1: Action Catalog And Lossless Action Mapping

**Files:**
- Create: `laravel-app/resources/js/whatsapp/flowmaker-v3/actionCatalog.js`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/graphAdapter.js`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/graphCompiler.js`
- Test: `laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs`

- [ ] **Step 1: Add smoke tests for preserving V2 action types**

Extend `flowmaker-v3-compiler-smoke.mjs` with a graph containing `set_state`, `store_consent`, `sigcenter_agenda`, `handoff_agent`, `ai_agent`, and `send_template` actions.

Run: `npm run test:flowmaker-v3`

Expected: FAIL until the compiler knows how to preserve specialized action types.

- [ ] **Step 2: Add action catalog helpers**

Create `actionCatalog.js` with:
- `actionToNodeType(action)`
- `actionToEditableData(action)`
- `editableDataToAction(node)`
- `ACTION_TYPE_OPTIONS`
- `STAGE_OPTIONS`
- `STATUS_OPTIONS`

The helpers must preserve unknown action properties by spreading `node.data.action` first and then applying edited fields.

- [ ] **Step 3: Wire graph adapter and compiler**

Update `graphAdapter.js` to call `actionToNodeType()` and `actionToEditableData()` for every action node.

Update `graphCompiler.js` to call `editableDataToAction(node)` instead of hardcoding every action conversion inline.

- [ ] **Step 4: Verify and commit**

Run:
```bash
npm run test:flowmaker-v3
npm run build
```

Expected: both pass.

Commit:
```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3 laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs
git commit -m "feat: preserve flowmaker v3 action payloads"
```

### Task 2: Specialized Inspector Editors

**Files:**
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodeInspector.jsx`
- Modify: `laravel-app/resources/css/flowmaker-v3.css`

- [ ] **Step 1: Replace the generic JSON fallback for known action types**

Add editor sections for:
- trigger scenario metadata: `name`, `status`, `stage`, `intercept_menu`, keywords
- `send_message`: text body, message subtype, optional link/caption
- `send_buttons`: header/body/footer and up to 3 buttons
- `set_state`: `state`, `next_state`, `save_response_as`, `awaiting_field`
- `store_consent`: consent storage fields plus optional message body
- `sigcenter_agenda`: `operation`, `send_result`, `store_result_as`, `save_response_as`, `next_state`
- `handoff_agent`: handoff message/queue metadata
- `ai_agent`: instructions, handoff flag, KB filters JSON
- `send_template`: template name/language and parameters JSON

- [ ] **Step 2: Keep generic JSON only for unknown action types**

The JSON editor must remain available when `node.data.actionType` is not in the supported list.

- [ ] **Step 3: Add compact inspector UI affordances**

Add CSS for subcards, section titles, segmented controls, compact two-column rows, and checkbox rows using existing mockup class names where possible.

- [ ] **Step 4: Verify and commit**

Run:
```bash
npm run build
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php
```

Expected: build passes; PHP route test passes with the existing non-fatal Vite warnings.

Commit:
```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodeInspector.jsx laravel-app/resources/css/flowmaker-v3.css
git commit -m "feat: add flowmaker v3 action editors"
```

### Task 3: Scenario Conditions And Preview Scope

**Files:**
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/graphAdapter.js`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/graphCompiler.js`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/components/PhonePreview.jsx`
- Test: `laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs`

- [ ] **Step 1: Preserve non-keyword scenario conditions**

Store full `scenario.conditions` on trigger node data as `conditions`. Keywords remain a friendly projection of `message_contains`, but compiler must use `conditions` when present and only rebuild conditions from keywords when the user edits keywords.

- [ ] **Step 2: Add tests for condition preservation**

Smoke test a scenario with `conditions: [{type: 'always'}, {type: 'state_equals', value: 'x'}]` and verify compile preserves both.

- [ ] **Step 3: Scope phone preview to selected scenario when possible**

Show messages following the selected trigger or selected action chain first. Fall back to global messages only when nothing is selected.

- [ ] **Step 4: Verify and commit**

Run:
```bash
npm run test:flowmaker-v3
npm run build
```

Expected: both pass.

Commit:
```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3 laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs
git commit -m "feat: preserve flowmaker v3 scenario conditions"
```

### Task 4: PR Update

**Files:**
- No code edits.

- [ ] **Step 1: Final verification**

Run:
```bash
npm run test:flowmaker-v3
npm run build
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php
```

Expected: JS smoke test and Vite build pass. PHP feature test passes with existing non-fatal Vite warnings.

- [ ] **Step 2: Push to PR 357 branch**

Run:
```bash
git push --force-with-lease origin HEAD:pr/flowmaker-ui-v3
```

Expected: PR 357 updates to the latest commit.

---

## Self-Review

- Spec coverage: Covers the approved goal of making scenarios, conditions, captures, state changes, and interactions easier to edit while preserving V2 backend compatibility.
- Placeholder scan: No TBD/TODO placeholder requirements.
- Type consistency: Uses existing `node.data.action`, `node.data.settings`, `node.data.keywords`, and V2 `flow.scenarios[].conditions/actions[]` contract names.
