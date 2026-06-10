# Flowmaker V3 Staging Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Build `/v3/whatsapp/flowmaker` as the staging-only React Flowmaker candidate that preserves V2 behavior by using the existing Laravel Flowmaker APIs and services.

**Architecture:** Add a new Vite React entry and Blade route for V3 while leaving `/v2/whatsapp/flowmaker` untouched. Port the V3 mockup into module-based React, add a Laravel API adapter, and compile visual graph state into the existing V2 Flowmaker contract before simulation or publishing.

**Tech Stack:** Laravel routes/controllers/views, Laravel Vite plugin, React 19, plain JavaScript modules, existing WhatsApp Flowmaker APIs, PHPUnit feature tests, Node-based compiler smoke test.

---

## File Map

Create:

- `laravel-app/resources/views/whatsapp/v3-flowmaker.blade.php`  
  Minimal Blade shell for the React app. Injects CSRF token, route URLs, and fallback URLs through `window.__FLOWMAKER_V3__`.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/main.jsx`  
  Vite entry. Imports React, app component, CSS, and renders into `#flowmaker-v3-root`.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`  
  Top-level editor state, loading, publish, simulate, dirty state, and layout.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/flowmakerApi.js`  
  Fetch adapter for current Laravel endpoints: contract, publish, simulate, compare, readiness, shadow summary, templates, knowledge base, media upload.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/graphCompiler.js`  
  Converts V3 visual graph state into the existing Flowmaker V2 contract payload.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/graphAdapter.js`  
  Converts the current V2 contract into initial editable V3 graph state.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/domain.js`  
  Node metadata, button limits, operators, default graph builders, and shared domain constants.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/util.js`  
  UI helpers migrated from the mockup: ids, formatting, variable fill, edge paths.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodePalette.jsx`  
  Palette from the mockup, converted from globals to props/imports.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/components/FlowCanvas.jsx`  
  Canvas from the mockup, converted from globals to props/imports.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodeCard.jsx`  
  Node rendering from the mockup.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodeInspector.jsx`  
  Inspector from the mockup.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/components/PhonePreview.jsx`  
  Visual phone preview plus backend simulation result display.

- `laravel-app/resources/js/whatsapp/flowmaker-v3/components/StructurePanel.jsx`  
  Displays compiled contract/errors for operator debugging.

- `laravel-app/resources/css/flowmaker-v3.css`  
  CSS ported from `/Users/jorgeluisdevera/Downloads/flowmaker_v2/flowmaker.css`, with app-scoped class names kept.

- `laravel-app/tests/Feature/WhatsappFlowmakerV3Test.php`  
  Feature tests for route rendering, permission parity, and V2 fallback preservation.

- `laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs`  
  Node smoke test for `graphCompiler.js`.

Modify:

- `laravel-app/routes/web.php`  
  Add `/v3/whatsapp/flowmaker` route inside the existing authenticated WhatsApp route group.

- `laravel-app/vite.config.js`  
  Add `resources/js/whatsapp/flowmaker-v3/main.jsx` and `resources/css/flowmaker-v3.css` to Vite inputs if the CSS is not imported exclusively from `main.jsx`.

- `laravel-app/package.json`  
  Add a `test:flowmaker-v3` script only if the Node smoke test needs a stable command.

Do not modify:

- `Flowmaker/` legacy module.
- `laravel-app/app/Modules/Whatsapp/Services/FlowmakerService.php` unless execution proves a thin adapter is unavoidable.
- `/v2/whatsapp/flowmaker` behavior.
- Runtime persistence tables.

---

### Task 1: Add The V3 Route And Blade Shell

**Files:**
- Modify: `laravel-app/routes/web.php`
- Create: `laravel-app/resources/views/whatsapp/v3-flowmaker.blade.php`
- Modify: `laravel-app/vite.config.js`
- Test: `laravel-app/tests/Feature/WhatsappFlowmakerV3Test.php`

- [ ] **Step 1: Write the failing feature test**

Create `laravel-app/tests/Feature/WhatsappFlowmakerV3Test.php`:

```php
<?php

namespace Tests\Feature;

use App\Http\Middleware\LegacySessionBridge;
use App\Http\Middleware\RequireLegacyPermission;
use App\Http\Middleware\RequireLegacySession;
use Tests\TestCase;

class WhatsappFlowmakerV3Test extends TestCase
{
    public function test_v3_flowmaker_route_renders_react_shell(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v3/whatsapp/flowmaker');

        $response
            ->assertOk()
            ->assertSee('flowmaker-v3-root', false)
            ->assertSee('window.__FLOWMAKER_V3__', false)
            ->assertSee('/v2/whatsapp/flowmaker', false)
            ->assertSee('resources/js/whatsapp/flowmaker-v3/main.jsx', false);
    }

    public function test_v2_flowmaker_route_still_renders_existing_ui(): void
    {
        $response = $this
            ->withoutMiddleware([
                LegacySessionBridge::class,
                RequireLegacySession::class,
                RequireLegacyPermission::class,
            ])
            ->get('/v2/whatsapp/flowmaker');

        $response
            ->assertOk()
            ->assertSee('Flowmaker y automatización')
            ->assertSee('Publicar JSON');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php --filter=v3_flowmaker_route_renders_react_shell
```

Expected: FAIL because `/v3/whatsapp/flowmaker` is not registered or does not render the React shell.

- [ ] **Step 3: Add the route**

Modify `laravel-app/routes/web.php` inside the existing `Route::middleware(['app.auth'])->group(function (): void { ... })` WhatsApp block, immediately after the current `/v2/whatsapp/flowmaker` route:

```php
    Route::get('/v3/whatsapp/flowmaker', [WhatsappUiController::class, 'flowmakerV3'])
        ->middleware('app.permission:administrativo,whatsapp.manage,whatsapp.autoresponder.manage,settings.manage')
        ->middleware('whatsapp.feature:ui,/whatsapp/flowmaker');
```

- [ ] **Step 4: Add controller method**

Modify `laravel-app/app/Modules/Whatsapp/Http/Controllers/WhatsappUiController.php` and add this method near the existing `flowmaker()` method:

```php
    public function flowmakerV3(Request $request): View
    {
        return view('whatsapp.v3-flowmaker', [
            'pageTitle' => 'WhatsApp V3 - Flowmaker',
        ] + $this->buildWhatsappNotificationViewData($request, [
            'scope' => 'flowmaker',
        ]));
    }
```

If the file already imports `Illuminate\Http\Request` and `Illuminate\View\View`, do not add duplicate imports.

- [ ] **Step 5: Create Blade shell**

Create `laravel-app/resources/views/whatsapp/v3-flowmaker.blade.php`:

```blade
@extends('layouts.app')

@section('title', $pageTitle ?? 'WhatsApp V3 - Flowmaker')

@section('content')
    <script>
        window.__FLOWMAKER_V3__ = {
            csrfToken: @json(csrf_token()),
            routes: {
                fallbackV2: @json('/v2/whatsapp/flowmaker'),
                contract: @json('/v2/whatsapp/api/flowmaker/contract'),
                publish: @json('/v2/whatsapp/api/flowmaker/publish'),
                simulate: @json('/v2/whatsapp/api/flowmaker/simulate'),
                compare: @json('/v2/whatsapp/api/flowmaker/compare'),
                readiness: @json('/v2/whatsapp/api/flowmaker/readiness'),
                shadowRuns: @json('/v2/whatsapp/api/flowmaker/shadow-runs'),
                shadowSummary: @json('/v2/whatsapp/api/flowmaker/shadow-summary'),
                templates: @json('/v2/whatsapp/api/templates'),
                knowledgeBase: @json('/v2/whatsapp/api/knowledge-base'),
                mediaUpload: @json('/v2/whatsapp/api/media/upload'),
            },
        };
    </script>

    <div id="flowmaker-v3-root"></div>

    @vite('resources/js/whatsapp/flowmaker-v3/main.jsx')
@endsection
```

- [ ] **Step 6: Add temporary Vite entry**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/main.jsx`:

```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';

function FlowmakerV3BootPlaceholder() {
    return (
        <main className="fm-app">
            <header className="fm-topbar">
                <div className="fm-brand">Flowmaker V3</div>
                <a className="fm-btn" href="/v2/whatsapp/flowmaker">Volver a V2</a>
            </header>
            <section className="fm-loading">Cargando constructor visual...</section>
        </main>
    );
}

const root = document.getElementById('flowmaker-v3-root');

if (root) {
    createRoot(root).render(<FlowmakerV3BootPlaceholder />);
}
```

- [ ] **Step 7: Add Vite input**

Modify `laravel-app/vite.config.js` input list and add:

```js
                'resources/js/whatsapp/flowmaker-v3/main.jsx',
```

Place it near `resources/js/whatsapp/main.jsx`.

- [ ] **Step 8: Run route tests**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php
```

Expected: PASS for both V3 shell and V2 fallback route.

- [ ] **Step 9: Commit**

Run:

```bash
git add laravel-app/routes/web.php laravel-app/app/Modules/Whatsapp/Http/Controllers/WhatsappUiController.php laravel-app/resources/views/whatsapp/v3-flowmaker.blade.php laravel-app/resources/js/whatsapp/flowmaker-v3/main.jsx laravel-app/vite.config.js laravel-app/tests/Feature/WhatsappFlowmakerV3Test.php
git commit -m "feat: add flowmaker v3 staging route"
```

---

### Task 2: Add API Adapter And Load Real Contract

**Files:**
- Create: `laravel-app/resources/js/whatsapp/flowmaker-v3/flowmakerApi.js`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/main.jsx`
- Create: `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`

- [ ] **Step 1: Create API adapter**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/flowmakerApi.js`:

```js
const defaultConfig = {
    csrfToken: '',
    routes: {},
};

function getConfig() {
    return window.__FLOWMAKER_V3__ || defaultConfig;
}

async function requestJson(url, options = {}) {
    const config = getConfig();
    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken || '',
            'X-Requested-With': 'XMLHttpRequest',
            ...(options.headers || {}),
        },
        ...options,
    });

    const payload = await response.json().catch(() => null);

    if (!response.ok) {
        const message = payload?.message || `HTTP ${response.status}`;
        const error = new Error(message);
        error.status = response.status;
        error.payload = payload;
        throw error;
    }

    return payload;
}

export function flowmakerApi() {
    const config = getConfig();
    const routes = config.routes || {};

    return {
        fallbackV2: routes.fallbackV2 || '/v2/whatsapp/flowmaker',
        contract: () => requestJson(routes.contract || '/v2/whatsapp/api/flowmaker/contract'),
        templates: () => requestJson(routes.templates || '/v2/whatsapp/api/templates'),
        knowledgeBase: () => requestJson(routes.knowledgeBase || '/v2/whatsapp/api/knowledge-base'),
        readiness: () => requestJson(routes.readiness || '/v2/whatsapp/api/flowmaker/readiness'),
        shadowSummary: () => requestJson(routes.shadowSummary || '/v2/whatsapp/api/flowmaker/shadow-summary'),
        publish: (flow) => requestJson(routes.publish || '/v2/whatsapp/api/flowmaker/publish', {
            method: 'POST',
            body: JSON.stringify({ flow }),
        }),
        simulate: (input) => requestJson(routes.simulate || '/v2/whatsapp/api/flowmaker/simulate', {
            method: 'POST',
            body: JSON.stringify(input),
        }),
    };
}
```

- [ ] **Step 2: Create app shell that loads real contract**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`:

```jsx
import React, { useEffect, useMemo, useState } from 'react';
import { flowmakerApi } from './flowmakerApi';

export function FlowmakerV3App() {
    const api = useMemo(() => flowmakerApi(), []);
    const [status, setStatus] = useState('loading');
    const [contract, setContract] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let alive = true;

        api.contract()
            .then((payload) => {
                if (!alive) return;
                setContract(payload);
                setStatus('ready');
            })
            .catch((err) => {
                if (!alive) return;
                setError(err.message || 'No se pudo cargar Flowmaker.');
                setStatus('error');
            });

        return () => {
            alive = false;
        };
    }, [api]);

    const flowName = contract?.schema?.name || 'Flowmaker';
    const scenarios = Array.isArray(contract?.schema?.scenarios) ? contract.schema.scenarios : [];

    return (
        <main className="fm-app">
            <header className="fm-topbar">
                <div className="fm-brand">Flowmaker V3</div>
                <div className="fm-flowname">{flowName}</div>
                <a className="fm-btn" href={api.fallbackV2}>Volver a V2</a>
            </header>

            {status === 'loading' && <section className="fm-loading">Cargando contrato real...</section>}
            {status === 'error' && <section className="fm-error">{error}</section>}
            {status === 'ready' && (
                <section className="fm-loading">
                    Contrato cargado: {scenarios.length} escenario{scenarios.length === 1 ? '' : 's'}
                </section>
            )}
        </main>
    );
}
```

- [ ] **Step 3: Wire app entry**

Replace `laravel-app/resources/js/whatsapp/flowmaker-v3/main.jsx` with:

```jsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { FlowmakerV3App } from './FlowmakerV3App';
import '../../../css/flowmaker-v3.css';

const root = document.getElementById('flowmaker-v3-root');

if (root) {
    createRoot(root).render(<FlowmakerV3App />);
}
```

- [ ] **Step 4: Add minimal CSS**

Create `laravel-app/resources/css/flowmaker-v3.css`:

```css
.fm-app {
    min-height: calc(100vh - 64px);
    background: #f6f8fb;
    color: #172033;
    font-family: Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
}

.fm-topbar {
    display: flex;
    align-items: center;
    gap: 14px;
    min-height: 64px;
    padding: 0 20px;
    border-bottom: 1px solid #dfe5ee;
    background: #ffffff;
}

.fm-brand {
    font-weight: 800;
}

.fm-flowname {
    color: #526173;
}

.fm-btn {
    margin-left: auto;
    border: 1px solid #cbd5e1;
    border-radius: 8px;
    padding: 8px 12px;
    color: #172033;
    background: #ffffff;
    text-decoration: none;
    font-weight: 700;
}

.fm-loading,
.fm-error {
    padding: 24px;
}

.fm-error {
    color: #b42318;
}
```

- [ ] **Step 5: Build assets**

Run:

```bash
cd laravel-app
npm run build
```

Expected: PASS and Vite includes `resources/js/whatsapp/flowmaker-v3/main.jsx`.

- [ ] **Step 6: Run route test**

Run:

```bash
cd laravel-app
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php
```

Expected: PASS.

- [ ] **Step 7: Commit**

Run:

```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3 laravel-app/resources/css/flowmaker-v3.css laravel-app/resources/js/whatsapp/flowmaker-v3/main.jsx
git commit -m "feat: load flowmaker v3 contract"
```

---

### Task 3: Add Domain Model And Graph Adapter

**Files:**
- Create: `laravel-app/resources/js/whatsapp/flowmaker-v3/domain.js`
- Create: `laravel-app/resources/js/whatsapp/flowmaker-v3/graphAdapter.js`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`

- [ ] **Step 1: Add domain metadata**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/domain.js`:

```js
export const BUTTON_LIMIT = 3;

export const NODE_TYPES = {
    keyword_trigger: {
        label: 'Palabra clave',
        cat: 'Disparadores',
        accent: 'trigger',
        isTrigger: true,
        single: false,
    },
    incoming_message: {
        label: 'Cualquier mensaje',
        cat: 'Disparadores',
        accent: 'trigger',
        isTrigger: true,
        single: true,
    },
    message: {
        label: 'Mensaje de texto',
        cat: 'Enviar',
        accent: 'message',
        single: true,
    },
    media: {
        label: 'Media',
        cat: 'Enviar',
        accent: 'media',
        single: true,
    },
    quick_replies: {
        label: 'Botones rápidos',
        cat: 'Interacción',
        accent: 'buttons',
    },
    template: {
        label: 'Plantilla',
        cat: 'Interacción',
        accent: 'template',
    },
    branch: {
        label: 'Condición',
        cat: 'Lógica',
        accent: 'branch',
    },
    ai_agent: {
        label: 'Agente IA',
        cat: 'Inteligencia Artificial',
        accent: 'ai',
    },
    end: {
        label: 'Fin',
        cat: 'Lógica',
        accent: 'end',
        terminal: true,
    },
};

export function createNode(type, position = { x: 120, y: 120 }, data = {}) {
    return {
        id: `${type}_${Math.random().toString(36).slice(2, 8)}`,
        type,
        position,
        data,
    };
}
```

- [ ] **Step 2: Add graph adapter**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/graphAdapter.js`:

```js
import { createNode } from './domain';

export function contractToGraph(contract) {
    const flow = contract?.schema || contract?.flow || contract || {};
    const scenarios = Array.isArray(flow.scenarios) ? flow.scenarios : [];
    const nodes = [];
    const edges = [];

    scenarios.forEach((scenario, scenarioIndex) => {
        const trigger = createNode('keyword_trigger', { x: 40, y: 120 + scenarioIndex * 260 }, {
            scenarioId: scenario.id || `scenario_${scenarioIndex + 1}`,
            name: scenario.name || `Escenario ${scenarioIndex + 1}`,
            status: scenario.status || 'published',
            stage: scenario.stage || 'custom',
            keywords: extractKeywords(scenario),
        });
        nodes.push(trigger);

        const actions = Array.isArray(scenario.actions) ? scenario.actions : [];
        let previous = trigger;

        actions.forEach((action, actionIndex) => {
            const node = createNode(actionToNodeType(action), {
                x: 380 + actionIndex * 320,
                y: 120 + scenarioIndex * 260,
            }, {
                action,
            });

            nodes.push(node);
            edges.push({
                id: `edge_${previous.id}_${node.id}`,
                source: previous.id,
                sourceHandle: actionIndex === 0 ? 'source' : 'source',
                target: node.id,
                targetHandle: 'in',
            });
            previous = node;
        });
    });

    if (nodes.length === 0) {
        nodes.push(createNode('keyword_trigger', { x: 40, y: 180 }, {
            scenarioId: 'primer_contacto',
            name: 'Primer contacto',
            status: 'published',
            stage: 'arrival',
            keywords: [{ id: 'kw_hola', value: 'hola', matchType: 'contains' }],
        }));
        nodes.push(createNode('message', { x: 380, y: 180 }, {
            action: {
                type: 'send_message',
                message: { type: 'text', body: 'Hola, soy el asistente virtual. ¿En qué te ayudo?' },
            },
        }));
        edges.push({
            id: 'edge_default',
            source: nodes[0].id,
            sourceHandle: 'source',
            target: nodes[1].id,
            targetHandle: 'in',
        });
    }

    return {
        flowName: flow.name || 'Flujo principal de WhatsApp',
        flowDescription: flow.description || '',
        settings: flow.settings || { timezone: 'America/Guayaquil' },
        nodes,
        edges,
    };
}

function extractKeywords(scenario) {
    const conditions = Array.isArray(scenario.conditions) ? scenario.conditions : [];
    const messageCondition = conditions.find((condition) => condition?.type === 'message_contains');
    const keywords = Array.isArray(messageCondition?.keywords) ? messageCondition.keywords : [];

    return keywords.map((keyword, index) => ({
        id: `kw_${index + 1}`,
        value: String(keyword),
        matchType: 'contains',
    }));
}

function actionToNodeType(action) {
    switch (action?.type) {
        case 'send_template':
            return 'template';
        case 'send_buttons':
        case 'send_list':
            return 'quick_replies';
        case 'ai_agent':
            return 'ai_agent';
        case 'handoff_agent':
            return 'end';
        default:
            if (action?.message?.type && action.message.type !== 'text') {
                return 'media';
            }
            return 'message';
    }
}
```

- [ ] **Step 3: Use adapter in app**

Modify `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`:

```jsx
import React, { useEffect, useMemo, useState } from 'react';
import { flowmakerApi } from './flowmakerApi';
import { contractToGraph } from './graphAdapter';

export function FlowmakerV3App() {
    const api = useMemo(() => flowmakerApi(), []);
    const [status, setStatus] = useState('loading');
    const [graph, setGraph] = useState(null);
    const [error, setError] = useState('');

    useEffect(() => {
        let alive = true;

        api.contract()
            .then((payload) => {
                if (!alive) return;
                setGraph(contractToGraph(payload));
                setStatus('ready');
            })
            .catch((err) => {
                if (!alive) return;
                setError(err.message || 'No se pudo cargar Flowmaker.');
                setStatus('error');
            });

        return () => {
            alive = false;
        };
    }, [api]);

    const nodes = graph?.nodes || [];
    const edges = graph?.edges || [];

    return (
        <main className="fm-app">
            <header className="fm-topbar">
                <div className="fm-brand">Flowmaker V3</div>
                <div className="fm-flowname">{graph?.flowName || 'Flowmaker'}</div>
                <a className="fm-btn" href={api.fallbackV2}>Volver a V2</a>
            </header>

            {status === 'loading' && <section className="fm-loading">Cargando contrato real...</section>}
            {status === 'error' && <section className="fm-error">{error}</section>}
            {status === 'ready' && (
                <section className="fm-loading">
                    Grafo cargado: {nodes.length} nodos y {edges.length} conexiones
                </section>
            )}
        </main>
    );
}
```

- [ ] **Step 4: Build assets**

Run:

```bash
cd laravel-app
npm run build
```

Expected: PASS.

- [ ] **Step 5: Commit**

Run:

```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3
git commit -m "feat: adapt flowmaker contract to v3 graph"
```

---

### Task 4: Port Mockup UI Components Into Vite Modules

**Files:**
- Create/Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/components/*.jsx`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`
- Modify: `laravel-app/resources/css/flowmaker-v3.css`
- Source reference: `/Users/jorgeluisdevera/Downloads/flowmaker_v2/*.jsx`

- [ ] **Step 1: Copy mockup CSS into app CSS**

Replace `laravel-app/resources/css/flowmaker-v3.css` with the contents of:

```text
/Users/jorgeluisdevera/Downloads/flowmaker_v2/flowmaker.css
```

Keep the existing `.fm-*` class names. Do not import `colors_and_type.css`; V3 should be self-contained inside the Laravel app.

- [ ] **Step 2: Create utility module**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/util.js` by porting these functions from `/Users/jorgeluisdevera/Downloads/flowmaker_v2/util.js`:

```js
export function uid(prefix = 'id') {
    return `${prefix}_${Math.random().toString(36).slice(2, 9)}`;
}

export function edgePath(x1, y1, x2, y2, style = 'bezier') {
    if (style === 'step') {
        const mid = x1 + (x2 - x1) / 2;
        return `M ${x1} ${y1} L ${mid} ${y1} L ${mid} ${y2} L ${x2} ${y2}`;
    }

    const dx = Math.max(80, Math.abs(x2 - x1) * 0.45);
    return `M ${x1} ${y1} C ${x1 + dx} ${y1}, ${x2 - dx} ${y2}, ${x2} ${y2}`;
}

export function waFormat(text = '') {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\*(.*?)\*/g, '<strong>$1</strong>')
        .replace(/_(.*?)_/g, '<em>$1</em>')
        .replace(/\n/g, '<br>');
}

export function fillVars(text = '', values = {}) {
    return String(text).replace(/\{\{([^}]+)}}/g, (_, key) => values[key.trim()] ?? `{{${key}}}`);
}
```

- [ ] **Step 3: Port `NodePalette`**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodePalette.jsx`:

```jsx
import React from 'react';
import { NODE_TYPES } from '../domain';

const ORDER = ['Disparadores', 'Enviar', 'Interacción', 'Lógica', 'Inteligencia Artificial'];

export function NodePalette({ onAdd }) {
    const categories = Object.entries(NODE_TYPES).reduce((acc, [type, meta]) => {
        acc[meta.cat] = acc[meta.cat] || [];
        acc[meta.cat].push([type, meta]);
        return acc;
    }, {});

    return (
        <aside className="fm-palette">
            <p className="fm-palette-hint">Arrastra un bloque al lienzo o haz clic para agregarlo al centro.</p>
            {ORDER.map((category) => (
                <div key={category}>
                    <h6>{category}</h6>
                    {(categories[category] || []).map(([type, meta]) => (
                        <button
                            key={type}
                            type="button"
                            className="fm-pal-item"
                            draggable
                            onDragStart={(event) => {
                                event.dataTransfer.setData('nodeType', type);
                                event.dataTransfer.effectAllowed = 'copy';
                            }}
                            onClick={() => onAdd(type)}
                        >
                            <div className="fm-pal-ic" style={{ background: `var(--nt-${meta.accent})` }}>
                                <span className="mdi mdi-plus" />
                            </div>
                            <div className="fm-pal-tx">
                                <b>{meta.label}</b>
                                <span>{meta.cat}</span>
                            </div>
                        </button>
                    ))}
                </div>
            ))}
        </aside>
    );
}
```

- [ ] **Step 4: Port canvas, node card, inspector, phone, and structure components**

Create these files by copying the corresponding mockup components and replacing `window.*` references with imports/props:

```text
laravel-app/resources/js/whatsapp/flowmaker-v3/components/FlowCanvas.jsx
laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodeCard.jsx
laravel-app/resources/js/whatsapp/flowmaker-v3/components/NodeInspector.jsx
laravel-app/resources/js/whatsapp/flowmaker-v3/components/PhonePreview.jsx
laravel-app/resources/js/whatsapp/flowmaker-v3/components/StructurePanel.jsx
```

Required replacements:

```js
// Replace:
window.NODE_TYPES
window.edgePath
window.uid
window.waFormat
window.fillVars

// With imports:
import { NODE_TYPES } from '../domain';
import { edgePath, fillVars, uid, waFormat } from '../util';
```

For the first port, keep the mockup behavior intact and avoid adding new UX beyond the fallback link and backend status.

- [ ] **Step 5: Wire components into the app**

Update `FlowmakerV3App.jsx` so the ready state renders:

```jsx
<div className="fm-main" style={{ gridTemplateColumns: '240px 1fr 332px' }}>
    <NodePalette onAdd={addNode} />
    <FlowCanvas
        nodes={graph.nodes}
        edges={graph.edges}
        selectedNodeId={selectedNodeId}
        selectedEdgeId={selectedEdgeId}
        onSelectNode={setSelectedNodeId}
        onSelectEdge={setSelectedEdgeId}
        onClearSelection={() => {
            setSelectedNodeId(null);
            setSelectedEdgeId(null);
        }}
        onMoveNode={moveNode}
        onAddEdge={addEdge}
        onDeleteEdge={deleteEdge}
        onDeleteNode={deleteNode}
        onDropNode={addNode}
        edgeStyle="bezier"
        showMinimap={true}
    />
    <PhonePreview nodes={graph.nodes} edges={graph.edges} flowName={graph.flowName} simulationResult={simulationResult} />
</div>
```

Also add local state helpers in `FlowmakerV3App.jsx`:

```jsx
const [selectedNodeId, setSelectedNodeId] = useState(null);
const [selectedEdgeId, setSelectedEdgeId] = useState(null);
const [simulationResult, setSimulationResult] = useState(null);

function updateGraph(updater) {
    setGraph((current) => ({ ...current, ...updater(current) }));
}
```

- [ ] **Step 6: Build assets**

Run:

```bash
cd laravel-app
npm run build
```

Expected: PASS. Fix import/export errors until Vite builds.

- [ ] **Step 7: Commit**

Run:

```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3 laravel-app/resources/css/flowmaker-v3.css
git commit -m "feat: port flowmaker v3 visual builder"
```

---

### Task 5: Implement Graph Compiler And Smoke Test

**Files:**
- Create: `laravel-app/resources/js/whatsapp/flowmaker-v3/graphCompiler.js`
- Create: `laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs`
- Modify: `laravel-app/package.json`

- [ ] **Step 1: Add compiler**

Create `laravel-app/resources/js/whatsapp/flowmaker-v3/graphCompiler.js`:

```js
import { BUTTON_LIMIT, NODE_TYPES } from './domain.js';

export function compileGraphToFlowContract(graph) {
    const nodes = Array.isArray(graph?.nodes) ? graph.nodes : [];
    const edges = Array.isArray(graph?.edges) ? graph.edges : [];
    const triggerNodes = nodes.filter((node) => NODE_TYPES[node.type]?.isTrigger);
    const errors = [];

    if (triggerNodes.length === 0) {
        errors.push('El flujo necesita al menos un disparador.');
    }

    if (triggerNodes.length > 1) {
        errors.push('La primera versión de V3 permite un solo disparador publicado por flujo.');
    }

    const scenarios = triggerNodes.map((trigger, index) => {
        const actions = collectActionsFromTrigger(trigger, nodes, edges, errors);
        const keywords = Array.isArray(trigger.data?.keywords) ? trigger.data.keywords : [];
        const conditions = trigger.type === 'keyword_trigger' && keywords.length > 0
            ? [{
                type: 'message_contains',
                keywords: keywords.map((keyword) => keyword.value).filter(Boolean),
            }]
            : [];

        if (actions.length === 0) {
            errors.push(`El escenario ${trigger.data?.name || trigger.id} no tiene acciones conectadas.`);
        }

        return {
            id: trigger.data?.scenarioId || `scenario_${index + 1}`,
            name: trigger.data?.name || `Escenario ${index + 1}`,
            description: trigger.data?.description || '',
            status: trigger.data?.status || 'published',
            stage: trigger.data?.stage || 'custom',
            stage_id: trigger.data?.stage || 'custom',
            stageId: trigger.data?.stage || 'custom',
            intercept_menu: trigger.type === 'incoming_message',
            conditions,
            actions,
        };
    });

    if (errors.length > 0) {
        return { ok: false, errors, flow: null };
    }

    return {
        ok: true,
        errors: [],
        flow: {
            name: graph.flowName || 'Flujo principal de WhatsApp',
            description: graph.flowDescription || 'Publicado desde Flowmaker V3',
            settings: graph.settings || { timezone: 'America/Guayaquil' },
            scenarios,
        },
    };
}

function collectActionsFromTrigger(trigger, nodes, edges, errors) {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const actions = [];
    const seen = new Set();
    let currentId = firstTarget(trigger.id, edges);

    while (currentId && !seen.has(currentId)) {
        seen.add(currentId);
        const node = byId.get(currentId);

        if (!node) {
            errors.push(`La conexión apunta a un nodo inexistente: ${currentId}.`);
            break;
        }

        const action = nodeToAction(node, errors);
        if (action) {
            actions.push(action);
        }

        currentId = firstTarget(node.id, edges);
    }

    return actions;
}

function firstTarget(sourceId, edges) {
    return edges.find((edge) => edge.source === sourceId)?.target || null;
}

function nodeToAction(node, errors) {
    const action = node.data?.action;

    if (action && typeof action === 'object') {
        return action;
    }

    const settings = node.data?.settings || {};

    switch (node.type) {
        case 'message':
            return {
                type: 'send_message',
                message: {
                    type: 'text',
                    body: settings.body || node.data?.body || '',
                },
            };

        case 'media':
            return {
                type: 'send_message',
                message: {
                    type: settings.mediaType || 'image',
                    body: settings.caption || '',
                    media_url: settings.fileUrl || '',
                },
            };

        case 'quick_replies': {
            const buttons = Array.isArray(settings.buttons)
                ? settings.buttons
                : [settings.button1, settings.button2, settings.button3].filter(Boolean);

            if (buttons.length > BUTTON_LIMIT) {
                errors.push('WhatsApp permite máximo 3 botones rápidos.');
            }

            return {
                type: 'send_buttons',
                body: settings.body || '',
                buttons: buttons.slice(0, BUTTON_LIMIT).map((text) => ({ text })),
            };
        }

        case 'template':
            return {
                type: 'send_template',
                template_name: settings.templateName || settings.selectedTemplateId || '',
                language: settings.language || 'es',
                parameters: settings.parameters || {},
            };

        case 'ai_agent':
            return {
                type: 'ai_agent',
                settings,
            };

        case 'end':
            return settings.action === 'handoff'
                ? { type: 'handoff_agent', note: settings.note || '' }
                : { type: 'set_state', state: 'closed' };

        default:
            errors.push(`Tipo de nodo no soportado para publicar: ${node.type}.`);
            return null;
    }
}
```

- [ ] **Step 2: Add Node smoke test**

Create `laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs`:

```js
import assert from 'node:assert/strict';
import { compileGraphToFlowContract } from '../../resources/js/whatsapp/flowmaker-v3/graphCompiler.js';

const result = compileGraphToFlowContract({
    flowName: 'Prueba V3',
    settings: { timezone: 'America/Guayaquil' },
    nodes: [
        {
            id: 'trigger_1',
            type: 'keyword_trigger',
            position: { x: 0, y: 0 },
            data: {
                scenarioId: 'saludo',
                name: 'Saludo',
                status: 'published',
                stage: 'arrival',
                keywords: [{ id: 'kw_1', value: 'hola', matchType: 'contains' }],
            },
        },
        {
            id: 'message_1',
            type: 'message',
            position: { x: 320, y: 0 },
            data: {
                settings: { body: 'Hola, soy CIVE.' },
            },
        },
    ],
    edges: [
        { id: 'edge_1', source: 'trigger_1', sourceHandle: 'source', target: 'message_1', targetHandle: 'in' },
    ],
});

assert.equal(result.ok, true);
assert.equal(result.flow.name, 'Prueba V3');
assert.equal(result.flow.scenarios[0].id, 'saludo');
assert.equal(result.flow.scenarios[0].conditions[0].type, 'message_contains');
assert.equal(result.flow.scenarios[0].actions[0].type, 'send_message');
assert.equal(result.flow.scenarios[0].actions[0].message.body, 'Hola, soy CIVE.');

const invalid = compileGraphToFlowContract({ nodes: [], edges: [] });
assert.equal(invalid.ok, false);
assert.match(invalid.errors[0], /disparador/);
```

- [ ] **Step 3: Add package script**

Modify `laravel-app/package.json`:

```json
"scripts": {
    "build": "vite build",
    "dev": "vite",
    "test:flowmaker-v3": "node tests/js/flowmaker-v3-compiler-smoke.mjs"
}
```

Preserve existing script keys.

- [ ] **Step 4: Run smoke test**

Run:

```bash
cd laravel-app
npm run test:flowmaker-v3
```

Expected: PASS with no output except npm script headers.

- [ ] **Step 5: Run build**

Run:

```bash
cd laravel-app
npm run build
```

Expected: PASS.

- [ ] **Step 6: Commit**

Run:

```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3/graphCompiler.js laravel-app/tests/js/flowmaker-v3-compiler-smoke.mjs laravel-app/package.json
git commit -m "feat: compile flowmaker v3 graph to contract"
```

---

### Task 6: Wire Publish And Backend Simulation

**Files:**
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/FlowmakerV3App.jsx`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/components/PhonePreview.jsx`
- Modify: `laravel-app/resources/js/whatsapp/flowmaker-v3/components/StructurePanel.jsx`

- [ ] **Step 1: Import compiler**

Modify `FlowmakerV3App.jsx` imports:

```jsx
import { compileGraphToFlowContract } from './graphCompiler';
```

- [ ] **Step 2: Add publish and simulate state**

Inside `FlowmakerV3App`, add:

```jsx
const [dirty, setDirty] = useState(false);
const [saving, setSaving] = useState(false);
const [simulating, setSimulating] = useState(false);
const [simulationResult, setSimulationResult] = useState(null);
const [validationErrors, setValidationErrors] = useState([]);
const [notice, setNotice] = useState('');
```

- [ ] **Step 3: Add publish handler**

Inside `FlowmakerV3App`, add:

```jsx
async function publishGraph() {
    if (!graph || saving) return;

    const compiled = compileGraphToFlowContract(graph);
    if (!compiled.ok) {
        setValidationErrors(compiled.errors);
        setNotice('');
        return;
    }

    setSaving(true);
    setValidationErrors([]);
    setNotice('');

    try {
        await api.publish(compiled.flow);
        setDirty(false);
        setNotice('Flujo publicado correctamente desde V3.');
    } catch (err) {
        setValidationErrors([err.message || 'No se pudo publicar el flujo.']);
    } finally {
        setSaving(false);
    }
}
```

- [ ] **Step 4: Add simulate handler**

Inside `FlowmakerV3App`, add:

```jsx
async function simulateGraph() {
    if (!graph || simulating) return;

    const compiled = compileGraphToFlowContract(graph);
    if (!compiled.ok) {
        setValidationErrors(compiled.errors);
        return;
    }

    setSimulating(true);
    setValidationErrors([]);

    try {
        const result = await api.simulate({
            flow: compiled.flow,
            text: 'hola',
            wa_number: '593999000000',
            context: {},
        });
        setSimulationResult(result);
    } catch (err) {
        setValidationErrors([err.message || 'No se pudo simular el flujo.']);
    } finally {
        setSimulating(false);
    }
}
```

- [ ] **Step 5: Add topbar buttons**

In `FlowmakerV3App.jsx` topbar JSX, add:

```jsx
<button className="fm-btn" type="button" onClick={simulateGraph} disabled={simulating || status !== 'ready'}>
    {simulating ? 'Simulando...' : 'Simular'}
</button>
<button className="fm-btn fm-btn-primary" type="button" onClick={publishGraph} disabled={saving || status !== 'ready'}>
    {saving ? 'Publicando...' : 'Publicar'}
</button>
```

- [ ] **Step 6: Render validation and success feedback**

Below the topbar, add:

```jsx
{notice && <div className="fm-toast">{notice}</div>}
{validationErrors.length > 0 && (
    <div className="fm-error">
        {validationErrors.map((message) => <div key={message}>{message}</div>)}
    </div>
)}
```

- [ ] **Step 7: Pass simulation result to phone preview**

Ensure `PhonePreview` receives:

```jsx
<PhonePreview
    nodes={graph.nodes}
    edges={graph.edges}
    flowName={graph.flowName}
    simulationResult={simulationResult}
/>
```

In `PhonePreview.jsx`, render authoritative simulation result above the local preview:

```jsx
{simulationResult && (
    <div className="fm-wa-restart">
        Simulación backend: {simulationResult.matched ? 'escenario encontrado' : 'sin match'}
    </div>
)}
```

- [ ] **Step 8: Run tests and build**

Run:

```bash
cd laravel-app
npm run test:flowmaker-v3
npm run build
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php tests/Feature/WhatsappFlowmakerTest.php --filter=Flowmaker
```

Expected: PASS. If the last filtered PHPUnit command does not select the expected tests, run:

```bash
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php tests/Feature/WhatsappFlowmakerTest.php
```

- [ ] **Step 9: Commit**

Run:

```bash
git add laravel-app/resources/js/whatsapp/flowmaker-v3
git commit -m "feat: publish and simulate flowmaker v3 graphs"
```

---

### Task 7: Manual Staging Verification Checklist

**Files:**
- Modify: `docs/superpowers/plans/2026-06-05-flowmaker-v3-staging-implementation.md` only if verification discoveries require plan updates.

- [ ] **Step 1: Start local app stack**

Run the project-specific local server command used for this repo. If no server is running and the Laravel app is self-contained, run:

```bash
cd laravel-app
php artisan serve --host=127.0.0.1 --port=8000
```

Expected: Laravel reports it is serving at `http://127.0.0.1:8000`.

- [ ] **Step 2: Open V3 route**

Visit:

```text
http://127.0.0.1:8000/v3/whatsapp/flowmaker
```

Expected:

- React V3 builder renders.
- Topbar shows Flowmaker V3.
- “Volver a V2” link is visible.
- No browser console build/import errors.

- [ ] **Step 3: Confirm V2 fallback**

Visit:

```text
http://127.0.0.1:8000/v2/whatsapp/flowmaker
```

Expected: Existing V2 UI still renders with “Flowmaker y automatización” and “Publicar JSON”.

- [ ] **Step 4: Run final command verification**

Run:

```bash
cd laravel-app
npm run test:flowmaker-v3
npm run build
php artisan test tests/Feature/WhatsappFlowmakerV3Test.php
```

Expected: all commands PASS.

- [ ] **Step 5: Commit verification note if docs changed**

Only if the plan or docs were updated during verification:

```bash
git add docs/superpowers/plans/2026-06-05-flowmaker-v3-staging-implementation.md
git commit -m "docs: update flowmaker v3 verification notes"
```

---

## Self-Review

Spec coverage:

- `/v3/whatsapp/flowmaker` route: Task 1.
- V2 fallback unchanged: Task 1 tests and Task 7 manual check.
- Vite React entry: Tasks 1 and 2.
- Mockup port: Task 4.
- Existing Laravel APIs as source of truth: Tasks 2 and 6.
- Graph compiler: Task 5.
- Simulation and publish: Task 6.
- No parallel `flows` CRUD backend: file map explicitly excludes it.
- Staging safety and verification: Task 7.

Placeholder scan:

- No unresolved markers or unspecified “add tests” steps are intentionally left in this plan.
- The plan includes exact files, commands, and expected results.

Type consistency:

- `flowmakerApi()` is defined in Task 2 and used by `FlowmakerV3App`.
- `contractToGraph()` is defined in Task 3 and used by `FlowmakerV3App`.
- `compileGraphToFlowContract()` is defined in Task 5 and used by Task 6.
- `graph.nodes`, `graph.edges`, `graph.flowName`, `graph.settings`, and `graph.flowDescription` are produced by `contractToGraph()` and consumed by compiler/app code.
