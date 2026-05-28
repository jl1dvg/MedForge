# CRM Reinvention — Plan C: UI React (Panel Comercial)

> **For agentic workers:** REQUIRED SUB-SKILL: Use **superpowers:subagent-driven-development**.
>
> **Execution mode:** subagent-driven (~1.5 días)
>
> **Prerequisito:** Plan B completo — los endpoints `/api/v2/crm/*` deben estar operativos.

**Goal:** Construir la mini-SPA React del panel comercial: lista con filtros, panel de detalle 50% con dos columnas, barra de stats, y registro de actividades. Sin routing SPA — una sola pantalla.

**Architecture:** Vite + React 18 + Tailwind 4. El archivo de entrada es `laravel-app/resources/js/crm/main.tsx`. Laravel sirve `GET /crm` → Blade `crm/panel.blade.php` → monta `<div id="crm-root">` → React hidrata. Axios para llamadas a la API. Sin React Router — estado local con `useState` + `useReducer`. Pusher.js para notificación en tiempo real de nuevas oportunidades.

**Tech Stack:** React 18, TypeScript, Tailwind 4, Axios, Pusher.js (ya instalados). `npm install react react-dom @types/react @types/react-dom`.

---

## Mapa de archivos

| Archivo | Acción | Responsabilidad |
|---------|--------|----------------|
| `resources/js/crm/main.tsx` | Create | Entry point — monta App en #crm-root |
| `resources/js/crm/App.tsx` | Create | Layout: sidebar + topbar + panel principal |
| `resources/js/crm/api.ts` | Create | Axios wrapper — todas las llamadas a /api/v2/crm/* |
| `resources/js/crm/types.ts` | Create | Interfaces TS: Opportunity, Contact, Activity, Stats |
| `resources/js/crm/hooks/useOpportunities.ts` | Create | Fetch + filtros + paginación |
| `resources/js/crm/hooks/useStats.ts` | Create | Fetch KPIs con refresh cada 60s |
| `resources/js/crm/components/StatsBar.tsx` | Create | 5 tarjetas de KPIs |
| `resources/js/crm/components/FilterChips.tsx` | Create | Chips de filtro (Todas/Urgentes/Nuevas/etc.) |
| `resources/js/crm/components/OpportunityTable.tsx` | Create | Tabla principal con filas urgentes resaltadas |
| `resources/js/crm/components/OpportunityRow.tsx` | Create | Fila individual con botón contextual |
| `resources/js/crm/components/DetailPanel.tsx` | Create | Panel 50% con dos columnas |
| `resources/js/crm/components/ActivityTimeline.tsx` | Create | Timeline de actividades |
| `resources/js/crm/components/StageSelector.tsx` | Create | 6 chips de etapa clicables |
| `resources/js/crm/components/NoteForm.tsx` | Create | Formulario de nota/actividad |
| `resources/views/crm/panel.blade.php` | Create | Shell Blade que carga el bundle React |
| `vite.config.ts` | Modify | Agregar entrada crm/main.tsx |

---

## Task 1: Setup React + TypeScript en el proyecto

**Files:**
- Modify: `laravel-app/package.json`
- Modify: `laravel-app/vite.config.ts`

- [ ] **Step 1: Instalar dependencias React**

```bash
cd laravel-app && npm install react react-dom @types/react @types/react-dom typescript
```

Esperado: `react`, `react-dom`, `@types/react`, `@types/react-dom`, `typescript` en `package.json`.

- [ ] **Step 2: Crear tsconfig.json**

```json
// laravel-app/tsconfig.json
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx",
    "strict": true,
    "noUnusedLocals": false,
    "noUnusedParameters": false
  },
  "include": ["resources/js/**/*"]
}
```

- [ ] **Step 3: Agregar entrada CRM en vite.config.ts**

Abrir `laravel-app/vite.config.ts`. Localizar el array `input` del plugin `laravel(...)` y agregar la entrada CRM:

```ts
// Dentro del plugin laravel({ input: [...] })
// Agregar junto a las demás entradas:
'resources/js/crm/main.tsx',
```

- [ ] **Step 4: Crear directorio y verificar**

```bash
mkdir -p laravel-app/resources/js/crm/components laravel-app/resources/js/crm/hooks
```

- [ ] **Step 5: Commit**

```bash
git add package.json tsconfig.json vite.config.ts
git commit -m "feat(crm-ui): add React 18 + TypeScript + Vite entry for CRM panel"
```

---

## Task 2: Types, API client y Shell Blade

**Files:**
- Create: `laravel-app/resources/js/crm/types.ts`
- Create: `laravel-app/resources/js/crm/api.ts`
- Create: `laravel-app/resources/views/crm/panel.blade.php`

- [ ] **Step 1: Crear types.ts**

```ts
// laravel-app/resources/js/crm/types.ts

export type Resolution = 'provisional' | 'identified' | 'linked';
export type Source = 'whatsapp' | 'solicitud' | 'examen' | 'manual';
export type Stage =
  | 'nuevo'
  | 'en_contacto'
  | 'interesado'
  | 'propuesta_enviada'
  | 'ganado'
  | 'perdido';
export type ActivityType = 'nota' | 'llamada' | 'cambio_etapa' | 'email';

export interface CrmContact {
  id: number;
  patient_id: number | null;
  name: string;
  phone: string;
  email: string | null;
  cedula: string | null;
  resolution: Resolution;
  source: Source;
  created_at: string;
  updated_at: string;
}

export interface CrmActivity {
  id: number;
  opportunity_id: number;
  type: ActivityType;
  description: string;
  user_id: number | null;
  created_at: string;
}

export interface CrmOpportunity {
  id: number;
  contact_id: number;
  title: string;
  stage: Stage;
  source: Source;
  source_id: number | null;
  source_type: string | null;
  assigned_to: number | null;
  lost_reason: string | null;
  created_at: string;
  updated_at: string;
  contact?: CrmContact;
  activities?: CrmActivity[];
}

export interface PanelStats {
  urgent: number;
  active: number;
  won_this_month: number;
  avg_response_h: number;
  conversion_rate: number;
}

export interface ApiMeta {
  total: number;
  limit: number;
  offset: number;
}

export interface OpportunitiesResponse {
  data: CrmOpportunity[];
  meta: ApiMeta;
}
```

- [ ] **Step 2: Crear api.ts**

```ts
// laravel-app/resources/js/crm/api.ts

import axios from 'axios';
import type { CrmOpportunity, CrmContact, CrmActivity, OpportunitiesResponse, PanelStats } from './types';

const client = axios.create({ baseURL: '/api/v2/crm', headers: { 'X-Requested-With': 'XMLHttpRequest' } });

export interface OpportunityFilters {
  stage?: string;
  source?: string;
  search?: string;
  urgent?: boolean;
  limit?: number;
  offset?: number;
}

export const api = {
  opportunities: {
    list: (filters: OpportunityFilters = {}): Promise<OpportunitiesResponse> =>
      client.get('/opportunities', { params: filters }).then(r => r.data),

    get: (id: number): Promise<CrmOpportunity> =>
      client.get(`/opportunities/${id}`).then(r => r.data.data),

    update: (id: number, payload: Partial<Pick<CrmOpportunity, 'stage' | 'assigned_to' | 'lost_reason'>>): Promise<CrmOpportunity> =>
      client.patch(`/opportunities/${id}`, payload).then(r => r.data.data),

    addActivity: (id: number, type: string, description: string): Promise<CrmActivity> =>
      client.post(`/opportunities/${id}/activities`, { type, description }).then(r => r.data.data),
  },

  contacts: {
    update: (id: number, payload: Partial<CrmContact>): Promise<CrmContact> =>
      client.patch(`/contacts/${id}`, payload).then(r => r.data.data),

    merge: (id: number, mergeIntoId: number): Promise<CrmContact> =>
      client.post(`/contacts/${id}/merge`, { merge_into_id: mergeIntoId }).then(r => r.data.data),
  },

  stats: {
    panel: (): Promise<{ panel: PanelStats; by_stage: Record<string, number> }> =>
      client.get('/stats').then(r => r.data.data),
  },
};
```

- [ ] **Step 3: Crear Blade shell**

```blade
{{-- laravel-app/resources/views/crm/panel.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM — Panel Comercial</title>
    @vite(['resources/css/app.css', 'resources/js/crm/main.tsx'])
</head>
<body class="bg-slate-100">
    <div id="crm-root"></div>
</body>
</html>
```

- [ ] **Step 4: Crear main.tsx**

```tsx
// laravel-app/resources/js/crm/main.tsx

import React from 'react';
import { createRoot } from 'react-dom/client';
import App from './App';

const container = document.getElementById('crm-root');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/crm/types.ts resources/js/crm/api.ts \
        resources/js/crm/main.tsx resources/views/crm/panel.blade.php
git commit -m "feat(crm-ui): add types, api client, Blade shell, React entry point"
```

---

## Task 3: Hooks de datos

**Files:**
- Create: `laravel-app/resources/js/crm/hooks/useOpportunities.ts`
- Create: `laravel-app/resources/js/crm/hooks/useStats.ts`

- [ ] **Step 1: Crear useOpportunities.ts**

```ts
// laravel-app/resources/js/crm/hooks/useOpportunities.ts

import { useState, useEffect, useCallback } from 'react';
import { api, type OpportunityFilters } from '../api';
import type { CrmOpportunity, ApiMeta } from '../types';

interface State {
  data: CrmOpportunity[];
  meta: ApiMeta;
  loading: boolean;
  error: string | null;
}

const INITIAL: State = { data: [], meta: { total: 0, limit: 25, offset: 0 }, loading: true, error: null };

export function useOpportunities(filters: OpportunityFilters = {}) {
  const [state, setState] = useState<State>(INITIAL);

  const load = useCallback(async () => {
    setState(s => ({ ...s, loading: true, error: null }));
    try {
      const res = await api.opportunities.list(filters);
      setState({ data: res.data, meta: res.meta, loading: false, error: null });
    } catch {
      setState(s => ({ ...s, loading: false, error: 'No se pudo cargar las oportunidades' }));
    }
  }, [JSON.stringify(filters)]);

  useEffect(() => { void load(); }, [load]);

  return { ...state, refresh: load };
}
```

- [ ] **Step 2: Crear useStats.ts**

```ts
// laravel-app/resources/js/crm/hooks/useStats.ts

import { useState, useEffect } from 'react';
import { api } from '../api';
import type { PanelStats } from '../types';

interface State {
  stats: PanelStats | null;
  byStage: Record<string, number>;
  loading: boolean;
}

export function useStats() {
  const [state, setState] = useState<State>({ stats: null, byStage: {}, loading: true });

  const load = async () => {
    try {
      const res = await api.stats.panel();
      setState({ stats: res.panel, byStage: res.by_stage, loading: false });
    } catch {
      setState(s => ({ ...s, loading: false }));
    }
  };

  useEffect(() => {
    void load();
    const interval = setInterval(load, 60_000);
    return () => clearInterval(interval);
  }, []);

  return state;
}
```

- [ ] **Step 3: Commit**

```bash
git add resources/js/crm/hooks/
git commit -m "feat(crm-ui): add useOpportunities and useStats hooks"
```

---

## Task 4: Componentes StatsBar, FilterChips, StageSelector

**Files:**
- Create: `laravel-app/resources/js/crm/components/StatsBar.tsx`
- Create: `laravel-app/resources/js/crm/components/FilterChips.tsx`
- Create: `laravel-app/resources/js/crm/components/StageSelector.tsx`

- [ ] **Step 1: Crear StatsBar.tsx**

```tsx
// laravel-app/resources/js/crm/components/StatsBar.tsx

import React from 'react';
import type { PanelStats } from '../types';

interface Props { stats: PanelStats | null }

const cards = [
  { key: 'urgent',          label: '⚠️ Sin contactar', colorClass: 'text-red-600',    bg: 'border-red-200 bg-red-50' },
  { key: 'active',          label: '📋 Activas total',  colorClass: 'text-blue-600',   bg: '' },
  { key: 'won_this_month',  label: '✅ Ganadas mes',    colorClass: 'text-green-600',  bg: '' },
  { key: 'avg_response_h',  label: '⏱ Resp. prom. (h)', colorClass: 'text-amber-600', bg: '' },
  { key: 'conversion_rate', label: '📈 Conversión %',   colorClass: 'text-violet-600', bg: '' },
] as const;

export function StatsBar({ stats }: Props) {
  return (
    <div className="grid grid-cols-5 gap-3 mb-5">
      {cards.map(({ key, label, colorClass, bg }) => (
        <div key={key} className={`bg-white rounded-xl border p-4 ${bg}`}>
          <div className={`text-3xl font-extrabold leading-none mb-1 ${colorClass}`}>
            {stats ? String((stats as Record<string, number>)[key]) : '—'}
            {key === 'conversion_rate' && stats ? '%' : ''}
          </div>
          <div className="text-xs text-slate-500">{label}</div>
        </div>
      ))}
    </div>
  );
}
```

- [ ] **Step 2: Crear FilterChips.tsx**

```tsx
// laravel-app/resources/js/crm/components/FilterChips.tsx

import React from 'react';

export interface ActiveFilters {
  stage: string;
  source: string;
  urgent: boolean;
  search: string;
}

interface Props {
  filters: ActiveFilters;
  total: number;
  urgentCount: number;
  onChange: (f: Partial<ActiveFilters>) => void;
}

const STAGES = [
  { value: '', label: `Todas` },
  { value: '__urgent__', label: '⚠️ Urgentes' },
  { value: 'nuevo', label: '🆕 Nuevas' },
  { value: 'propuesta_enviada', label: '📋 Propuesta' },
];

const SOURCES = [
  { value: '', label: 'Todos los orígenes' },
  { value: 'whatsapp', label: '💬 WhatsApp' },
  { value: 'solicitud', label: '📝 Solicitudes' },
  { value: 'examen', label: '🧪 Exámenes' },
];

export function FilterChips({ filters, total, urgentCount, onChange }: Props) {
  return (
    <div className="flex items-center gap-2 mb-4 flex-wrap">
      {STAGES.map(({ value, label }) => {
        const isActive = value === '__urgent__' ? filters.urgent : filters.stage === value && !filters.urgent;
        return (
          <button
            key={value}
            onClick={() => value === '__urgent__'
              ? onChange({ urgent: !filters.urgent, stage: '' })
              : onChange({ stage: value, urgent: false })}
            className={`px-3 py-1.5 rounded-full text-xs font-semibold border-2 transition-all
              ${isActive
                ? value === '__urgent__'
                  ? 'bg-red-100 text-red-700 border-red-300'
                  : 'bg-blue-500 text-white border-blue-500'
                : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'}`}
          >
            {label} {value === '' && `(${total})`}
            {value === '__urgent__' && `(${urgentCount})`}
          </button>
        );
      })}

      <div className="h-5 w-px bg-slate-200 mx-1" />

      {SOURCES.slice(1).map(({ value, label }) => (
        <button
          key={value}
          onClick={() => onChange({ source: filters.source === value ? '' : value })}
          className={`px-3 py-1.5 rounded-full text-xs font-semibold border-2 transition-all
            ${filters.source === value
              ? 'bg-slate-700 text-white border-slate-700'
              : 'bg-white text-slate-500 border-slate-200 hover:border-slate-400'}`}
        >
          {label}
        </button>
      ))}

      <input
        className="ml-auto border border-slate-200 rounded-lg px-3 py-1.5 text-sm text-slate-600 bg-white outline-none w-52"
        placeholder="🔍  Buscar paciente o cédula..."
        value={filters.search}
        onChange={e => onChange({ search: e.target.value })}
      />
    </div>
  );
}
```

- [ ] **Step 3: Crear StageSelector.tsx**

```tsx
// laravel-app/resources/js/crm/components/StageSelector.tsx

import React from 'react';
import type { Stage } from '../types';

const STAGES: { value: Stage; label: string; classes: string }[] = [
  { value: 'nuevo',            label: '🆕 Nuevo',           classes: 'bg-sky-100 text-sky-700 border-sky-200' },
  { value: 'en_contacto',      label: '📞 En contacto',     classes: 'bg-yellow-100 text-yellow-700 border-yellow-200' },
  { value: 'interesado',       label: '💬 Interesado',      classes: 'bg-violet-100 text-violet-700 border-violet-200' },
  { value: 'propuesta_enviada',label: '📋 Propuesta',       classes: 'bg-pink-100 text-pink-700 border-pink-200' },
  { value: 'ganado',           label: '✅ Ganado',           classes: 'bg-green-100 text-green-700 border-green-200' },
  { value: 'perdido',          label: '❌ Perdido',          classes: 'bg-red-100 text-red-700 border-red-200' },
];

interface Props {
  current: Stage;
  onChange: (s: Stage) => void;
  loading?: boolean;
}

export function StageSelector({ current, onChange, loading }: Props) {
  return (
    <div className="flex gap-2 flex-wrap">
      {STAGES.map(({ value, label, classes }) => (
        <button
          key={value}
          disabled={loading}
          onClick={() => onChange(value)}
          className={`px-3 py-1 rounded-lg text-xs font-semibold border-2 transition-all
            ${current === value ? `${classes} ring-2 ring-offset-1 ring-blue-400` : 'bg-slate-50 text-slate-400 border-slate-200 hover:border-slate-400'}
            disabled:opacity-50`}
        >
          {label}
        </button>
      ))}
    </div>
  );
}
```

- [ ] **Step 4: Commit**

```bash
git add resources/js/crm/components/StatsBar.tsx \
        resources/js/crm/components/FilterChips.tsx \
        resources/js/crm/components/StageSelector.tsx
git commit -m "feat(crm-ui): add StatsBar, FilterChips, StageSelector components"
```

---

## Task 5: OpportunityTable, OpportunityRow, ActivityTimeline, NoteForm

**Files:**
- Create: `laravel-app/resources/js/crm/components/OpportunityRow.tsx`
- Create: `laravel-app/resources/js/crm/components/OpportunityTable.tsx`
- Create: `laravel-app/resources/js/crm/components/ActivityTimeline.tsx`
- Create: `laravel-app/resources/js/crm/components/NoteForm.tsx`

- [ ] **Step 1: Crear OpportunityRow.tsx**

```tsx
// laravel-app/resources/js/crm/components/OpportunityRow.tsx

import React from 'react';
import type { CrmOpportunity, Stage, Source } from '../types';

const STAGE_BADGE: Record<Stage, string> = {
  nuevo:             'bg-sky-100 text-sky-700',
  en_contacto:       'bg-yellow-100 text-yellow-700',
  interesado:        'bg-violet-100 text-violet-700',
  propuesta_enviada: 'bg-pink-100 text-pink-700',
  ganado:            'bg-green-100 text-green-700',
  perdido:           'bg-red-100 text-red-700',
};
const STAGE_LABEL: Record<Stage, string> = {
  nuevo: '🆕 Nuevo', en_contacto: '📞 En contacto', interesado: '💬 Interesado',
  propuesta_enviada: '📋 Propuesta', ganado: '✅ Ganado', perdido: '❌ Perdido',
};
const SOURCE_LABEL: Record<Source, string> = {
  whatsapp: '💬 WhatsApp', solicitud: '📝 Solicitud', examen: '🧪 Examen', manual: '📲 Manual',
};
const ACTION_LABEL: Partial<Record<Stage, string>> = {
  nuevo: 'Contactar →', en_contacto: 'Avanzar →',
  interesado: 'Avanzar →', propuesta_enviada: 'Seguimiento →',
};

function timeAgo(dateStr: string): { label: string; urgent: boolean } {
  const diffH = (Date.now() - new Date(dateStr).getTime()) / 3_600_000;
  if (diffH < 1) return { label: 'hace < 1h', urgent: false };
  if (diffH < 6) return { label: `hace ${Math.floor(diffH)}h`, urgent: false };
  if (diffH < 24) return { label: `⚠️ ${Math.floor(diffH)}h sin resp.`, urgent: true };
  return { label: `⚠️ ${Math.floor(diffH / 24)}d sin resp.`, urgent: true };
}

interface Props {
  opp: CrmOpportunity;
  onClick: (opp: CrmOpportunity) => void;
}

export function OpportunityRow({ opp, onClick }: Props) {
  const time = timeAgo(opp.updated_at);
  const isUrgent = time.urgent && !['ganado', 'perdido'].includes(opp.stage);

  return (
    <tr
      onClick={() => onClick(opp)}
      className={`border-b border-slate-100 cursor-pointer transition-colors
        ${isUrgent ? 'bg-amber-50 hover:bg-amber-100 border-l-4 border-l-amber-400' : 'hover:bg-slate-50'}`}
    >
      <td className="px-4 py-3">
        <div className="font-bold text-slate-900 text-sm">{opp.contact?.name ?? '—'}</div>
        <div className="text-xs text-slate-400 mt-0.5">
          {opp.contact?.cedula ? `🪪 ${opp.contact.cedula}` : `📱 ${opp.contact?.phone ?? '—'}`}
        </div>
      </td>
      <td className="px-4 py-3">
        <span className={`inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold ${STAGE_BADGE[opp.stage]}`}>
          {STAGE_LABEL[opp.stage]}
        </span>
      </td>
      <td className="px-4 py-3 text-xs text-slate-500">{SOURCE_LABEL[opp.source]}</td>
      <td className="px-4 py-3 text-xs text-slate-500">—</td>
      <td className={`px-4 py-3 text-xs font-semibold ${time.urgent ? 'text-red-600' : 'text-slate-400'}`}>
        {time.label}
      </td>
      <td className="px-4 py-3">
        {ACTION_LABEL[opp.stage] && (
          <button className="bg-blue-500 text-white text-xs font-semibold px-3 py-1.5 rounded-lg hover:bg-blue-600">
            {ACTION_LABEL[opp.stage]}
          </button>
        )}
      </td>
    </tr>
  );
}
```

- [ ] **Step 2: Crear OpportunityTable.tsx**

```tsx
// laravel-app/resources/js/crm/components/OpportunityTable.tsx

import React from 'react';
import type { CrmOpportunity } from '../types';
import { OpportunityRow } from './OpportunityRow';

interface Props {
  opportunities: CrmOpportunity[];
  loading: boolean;
  onSelect: (opp: CrmOpportunity) => void;
}

export function OpportunityTable({ opportunities, loading, onSelect }: Props) {
  return (
    <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
      <table className="w-full">
        <thead>
          <tr className="bg-slate-50 border-b border-slate-200">
            {['Paciente / Contacto', 'Etapa', 'Origen', 'Asignado a', 'Tiempo', 'Acción'].map(h => (
              <th key={h} className="px-4 py-2.5 text-left text-xs font-bold text-slate-500 uppercase tracking-wide">
                {h}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {loading && (
            <tr><td colSpan={6} className="text-center py-10 text-slate-400 text-sm">Cargando...</td></tr>
          )}
          {!loading && opportunities.length === 0 && (
            <tr><td colSpan={6} className="text-center py-10 text-slate-400 text-sm">No hay oportunidades con estos filtros</td></tr>
          )}
          {!loading && opportunities.map(opp => (
            <OpportunityRow key={opp.id} opp={opp} onClick={onSelect} />
          ))}
        </tbody>
      </table>
    </div>
  );
}
```

- [ ] **Step 3: Crear ActivityTimeline.tsx**

```tsx
// laravel-app/resources/js/crm/components/ActivityTimeline.tsx

import React from 'react';
import type { CrmActivity, ActivityType } from '../types';

const DOT_COLOR: Record<ActivityType, string> = {
  nota: 'bg-amber-400', llamada: 'bg-blue-400',
  cambio_etapa: 'bg-violet-500', email: 'bg-pink-400',
};

function formatDate(d: string): string {
  const diff = (Date.now() - new Date(d).getTime()) / 60_000;
  if (diff < 60) return `Hace ${Math.floor(diff)} min`;
  if (diff < 1440) return `Hace ${Math.floor(diff / 60)}h`;
  return `Hace ${Math.floor(diff / 1440)}d`;
}

interface Props { activities: CrmActivity[] }

export function ActivityTimeline({ activities }: Props) {
  if (activities.length === 0) {
    return <p className="text-sm text-slate-400 text-center py-4">Sin actividades registradas</p>;
  }
  return (
    <div className="relative pl-5">
      <div className="absolute left-2 top-0 bottom-0 w-0.5 bg-slate-200" />
      <div className="flex flex-col gap-3">
        {activities.map(a => (
          <div key={a.id} className="relative">
            <div className={`absolute -left-3 top-1 w-2.5 h-2.5 rounded-full ${DOT_COLOR[a.type]}`} />
            <div className="bg-white border border-slate-200 rounded-lg px-3 py-2.5 shadow-sm">
              <p className="text-xs text-slate-700 leading-relaxed">{a.description}</p>
              <p className="text-xs text-slate-400 mt-1">
                {formatDate(a.created_at)} · {a.user_id ? `Usuario #${a.user_id}` : 'Sistema'}
              </p>
            </div>
          </div>
        ))}
      </div>
    </div>
  );
}
```

- [ ] **Step 4: Crear NoteForm.tsx**

```tsx
// laravel-app/resources/js/crm/components/NoteForm.tsx

import React, { useState } from 'react';

interface Props {
  onSave: (type: string, description: string) => Promise<void>;
}

export function NoteForm({ onSave }: Props) {
  const [type, setType] = useState('nota');
  const [text, setText] = useState('');
  const [saving, setSaving] = useState(false);

  const handleSubmit = async () => {
    if (!text.trim()) return;
    setSaving(true);
    await onSave(type, text.trim());
    setText('');
    setSaving(false);
  };

  return (
    <div>
      <div className="flex gap-2 mb-2">
        {[{ v: 'nota', l: '📝 Nota' }, { v: 'llamada', l: '📞 Llamada' }, { v: 'email', l: '✉️ Email' }].map(({ v, l }) => (
          <button
            key={v}
            onClick={() => setType(v)}
            className={`text-xs px-2.5 py-1 rounded-lg border font-semibold transition-all
              ${type === v ? 'bg-slate-700 text-white border-slate-700' : 'bg-white text-slate-500 border-slate-200'}`}
          >
            {l}
          </button>
        ))}
      </div>
      <textarea
        className="w-full border border-slate-200 rounded-lg p-2.5 text-sm resize-none h-18 outline-none focus:border-blue-400"
        placeholder="¿Qué pasó? Ej: Llamé al paciente, quedó de confirmar la próxima semana..."
        value={text}
        onChange={e => setText(e.target.value)}
      />
      <button
        onClick={handleSubmit}
        disabled={saving || !text.trim()}
        className="mt-2 w-full bg-blue-500 text-white text-sm font-semibold py-2 rounded-lg hover:bg-blue-600 disabled:opacity-50"
      >
        {saving ? 'Guardando...' : '💾 Guardar nota'}
      </button>
    </div>
  );
}
```

- [ ] **Step 5: Commit**

```bash
git add resources/js/crm/components/
git commit -m "feat(crm-ui): add OpportunityTable, OpportunityRow, ActivityTimeline, NoteForm"
```

---

## Task 6: DetailPanel y App principal

**Files:**
- Create: `laravel-app/resources/js/crm/components/DetailPanel.tsx`
- Create: `laravel-app/resources/js/crm/App.tsx`

- [ ] **Step 1: Crear DetailPanel.tsx**

```tsx
// laravel-app/resources/js/crm/components/DetailPanel.tsx

import React, { useState } from 'react';
import type { CrmOpportunity, Stage } from '../types';
import { api } from '../api';
import { StageSelector } from './StageSelector';
import { ActivityTimeline } from './ActivityTimeline';
import { NoteForm } from './NoteForm';

interface Props {
  opportunity: CrmOpportunity;
  onClose: () => void;
  onUpdated: (opp: CrmOpportunity) => void;
}

const RESOLUTION_BADGE: Record<string, string> = {
  provisional: 'bg-yellow-100 text-yellow-700',
  identified:  'bg-sky-100 text-sky-700',
  linked:      'bg-green-100 text-green-700',
};

export function DetailPanel({ opportunity: initial, onClose, onUpdated }: Props) {
  const [opp, setOpp] = useState(initial);
  const [stageLoading, setStageLoading] = useState(false);

  const handleStageChange = async (stage: Stage) => {
    setStageLoading(true);
    const updated = await api.opportunities.update(opp.id, { stage });
    setOpp(updated);
    onUpdated(updated);
    setStageLoading(false);
  };

  const handleSaveNote = async (type: string, description: string) => {
    await api.opportunities.addActivity(opp.id, type, description);
    const refreshed = await api.opportunities.get(opp.id);
    setOpp(refreshed);
    onUpdated(refreshed);
  };

  const contact = opp.contact;
  const activities = opp.activities ?? [];
  const resolution = contact?.resolution ?? 'provisional';

  return (
    <div className="fixed inset-y-0 right-0 w-1/2 bg-white shadow-2xl border-l border-slate-200 z-50 flex flex-col">
      {/* Header */}
      <div className="px-5 py-4 border-b border-slate-200 flex items-start justify-between flex-shrink-0">
        <div>
          <h2 className="text-lg font-extrabold text-slate-900">{contact?.name ?? '—'}</h2>
          <div className="flex items-center gap-2 mt-1 flex-wrap">
            <span className={`text-xs px-2 py-0.5 rounded-full font-semibold ${RESOLUTION_BADGE[resolution] ?? ''}`}>
              {resolution === 'linked' ? '✅ vinculado' : resolution === 'identified' ? '🪪 identificado' : '⚠️ provisional'}
            </span>
          </div>
        </div>
        <button onClick={onClose} className="w-8 h-8 rounded-lg border border-slate-200 flex items-center justify-center text-slate-400 hover:bg-slate-100">
          ✕
        </button>
      </div>

      {/* Body — 2 columns */}
      <div className="flex flex-1 overflow-hidden">
        {/* Left column */}
        <div className="w-1/2 border-r border-slate-100 overflow-y-auto p-5 flex flex-col gap-5">
          {/* Contact info */}
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Contacto</p>
            <div className="bg-slate-50 rounded-xl p-3 text-sm space-y-1.5">
              <p className="text-slate-700">📱 {contact?.phone ?? '—'}</p>
              {contact?.cedula && <p className="text-slate-700">🪪 {contact.cedula}</p>}
              {contact?.email && <p className="text-blue-500">{contact.email}</p>}
            </div>
          </div>

          {/* Origin */}
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Origen</p>
            <div className="inline-flex items-center gap-2 bg-slate-100 rounded-lg px-3 py-2 text-sm text-blue-600 font-semibold">
              {opp.source === 'whatsapp' ? '💬' : opp.source === 'solicitud' ? '📝' : '🧪'} Ver {opp.source} #{opp.source_id} →
            </div>
          </div>

          {/* Stage */}
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Etapa actual</p>
            <StageSelector current={opp.stage} onChange={handleStageChange} loading={stageLoading} />
          </div>

          {/* Actions */}
          <div className="mt-auto grid grid-cols-2 gap-2">
            <button className="bg-amber-500 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-amber-600">📞 Llamar</button>
            <button className="bg-violet-500 text-white text-sm font-semibold py-2.5 rounded-lg hover:bg-violet-600">📧 Email</button>
            <button className="col-span-2 bg-red-100 text-red-600 text-sm font-semibold py-2.5 rounded-lg hover:bg-red-200">
              ❌ Marcar como perdido
            </button>
          </div>
        </div>

        {/* Right column */}
        <div className="w-1/2 overflow-y-auto p-5 bg-slate-50 flex flex-col gap-5">
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-2">Registrar actividad</p>
            <NoteForm onSave={handleSaveNote} />
          </div>
          <div>
            <p className="text-xs font-bold text-slate-400 uppercase tracking-wide mb-3">Historial</p>
            <ActivityTimeline activities={activities} />
          </div>
        </div>
      </div>
    </div>
  );
}
```

- [ ] **Step 2: Crear App.tsx**

```tsx
// laravel-app/resources/js/crm/App.tsx

import React, { useState, useCallback } from 'react';
import type { CrmOpportunity, Stage } from './types';
import type { ActiveFilters } from './components/FilterChips';
import { useOpportunities } from './hooks/useOpportunities';
import { useStats } from './hooks/useStats';
import { StatsBar } from './components/StatsBar';
import { FilterChips } from './components/FilterChips';
import { OpportunityTable } from './components/OpportunityTable';
import { DetailPanel } from './components/DetailPanel';

const DEFAULT_FILTERS: ActiveFilters = { stage: '', source: '', urgent: false, search: '' };

export default function App() {
  const [filters, setFilters] = useState<ActiveFilters>(DEFAULT_FILTERS);
  const [selected, setSelected] = useState<CrmOpportunity | null>(null);

  const apiFilters = {
    stage: filters.stage || undefined,
    source: filters.source || undefined,
    urgent: filters.urgent || undefined,
    search: filters.search || undefined,
  };

  const { data, meta, loading, refresh } = useOpportunities(apiFilters);
  const { stats } = useStats();

  const handleFilterChange = useCallback((partial: Partial<ActiveFilters>) => {
    setFilters(f => ({ ...f, ...partial }));
  }, []);

  const handleUpdated = useCallback((updated: CrmOpportunity) => {
    setSelected(updated);
    void refresh();
  }, [refresh]);

  return (
    <div className="flex h-screen bg-slate-100 overflow-hidden">
      {/* Sidebar */}
      <aside className="w-52 bg-slate-800 flex flex-col flex-shrink-0">
        <div className="px-4 py-5 border-b border-slate-700">
          <p className="text-white font-bold text-base">MedForge</p>
          <p className="text-slate-400 text-xs">Panel Comercial</p>
        </div>
        <nav className="p-2 flex-1">
          {[
            { icon: '🎯', label: 'Oportunidades', active: true, badge: stats?.urgent ?? 0 },
            { icon: '👤', label: 'Contactos',     active: false, badge: 0 },
            { icon: '📊', label: 'Reportes',      active: false, badge: 0 },
          ].map(({ icon, label, active, badge }) => (
            <div
              key={label}
              className={`flex items-center gap-2.5 px-3 py-2.5 rounded-lg text-sm cursor-pointer mb-0.5
                ${active ? 'bg-blue-500 text-white' : 'text-slate-400 hover:bg-slate-700 hover:text-white'}`}
            >
              <span>{icon}</span>
              <span>{label}</span>
              {badge > 0 && (
                <span className="ml-auto bg-red-500 text-white text-xs font-bold px-1.5 py-0.5 rounded-full">
                  {badge}
                </span>
              )}
            </div>
          ))}
        </nav>
      </aside>

      {/* Main */}
      <div className="flex-1 flex flex-col overflow-hidden">
        {/* Top bar */}
        <header className="bg-white border-b border-slate-200 px-6 h-14 flex items-center justify-between flex-shrink-0">
          <h1 className="text-lg font-bold text-slate-900">Oportunidades</h1>
          <button className="bg-blue-500 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-blue-600">
            + Nueva oportunidad
          </button>
        </header>

        {/* Content */}
        <main className="flex-1 overflow-y-auto p-6">
          <StatsBar stats={stats} />
          <FilterChips
            filters={filters}
            total={meta.total}
            urgentCount={stats?.urgent ?? 0}
            onChange={handleFilterChange}
          />
          <OpportunityTable
            opportunities={data}
            loading={loading}
            onSelect={setSelected}
          />
        </main>
      </div>

      {/* Detail panel */}
      {selected && (
        <DetailPanel
          opportunity={selected}
          onClose={() => setSelected(null)}
          onUpdated={handleUpdated}
        />
      )}
    </div>
  );
}
```

- [ ] **Step 3: Build y verificar sin errores TypeScript**

```bash
cd laravel-app && npm run build 2>&1 | tail -20
```

Esperado: build exitoso, sin errores TypeScript.

- [ ] **Step 4: Commit final**

```bash
git add resources/js/crm/ resources/views/crm/
git commit -m "feat(crm-ui): complete React SPA — App, DetailPanel, all components"
```

---

## Verificación final del Plan C

```bash
cd laravel-app && npm run build
# Esperado: sin errores

php artisan serve &
# Abrir http://localhost:8000/crm y verificar que el panel carga
```

Panel funcional cuando: stats bar visible, tabla de oportunidades carga datos de la API, clic en fila abre panel de detalle al 50%, cambio de etapa funciona y se refleja en el timeline. Continuar con `2026-05-28-onda5-crm-d-integration.md`.
