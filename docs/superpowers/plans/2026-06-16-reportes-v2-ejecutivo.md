# Reportes V2 Ejecutivos Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two new fullscreen executive report pages — `/v2/cirugias/dashboard/report` and `/v2/imagenes/dashboard/report` — compiled with Vite + React 19 + TypeScript + Recharts, faithful to the approved design, without modifying existing dashboard views or Excel exports.

**Architecture:** Each report is a standalone Laravel Blade view (no sidebar/topnav) that injects a JSON payload into `window.MF_CIR_REPORT` / `window.MF_IMG_REPORT` and mounts a Vite-compiled React app. Two independent Vite entry points share only CSS, generic chart components, and layout primitives. Filters (period preset, sede) are applied server-side on each page load via query params — no client-side data mixing between modules.

**Tech Stack:** Laravel 10 (Blade, PDO services, existing middleware), Vite + `laravel-vite-plugin`, React 19, TypeScript strict, Recharts, MDI icon font (already loaded in app), IBM Plex Sans + Rubik (already loaded).

---

## File Map

**New files — shared:**
- `public/css/v2/reportes-v2.css` — design tokens + all `.rep-*` styles (loaded via `<link>`, not Vite)
- `resources/js/v2/reportes-v2/shared/types.ts` — base interfaces shared by both modules
- `resources/js/v2/reportes-v2/shared/charts.tsx` — Recharts wrappers: `Measured`, `TrendArea`, `AreaSeries`, `ColumnChart`, `DonutChart`, `RepTooltip`
- `resources/js/v2/reportes-v2/shared/lib.tsx` — layout primitives: `Cover`, `Section`, `ExecutiveMap`, `Kpi`, `BarsList`, `DonutLegend`, `SynthCell`, `Read`, `Insight`, `Recs`, `Footnote`

**New files — cirugías module:**
- `resources/js/v2/reportes-v2/cirugias/types.ts` — `CirugiasReport` interface
- `resources/js/v2/reportes-v2/cirugias/toolbar.tsx` — period/sede selector (navigates via query params)
- `resources/js/v2/reportes-v2/cirugias/sections.tsx` — Sections 02/03/04 + `CirSurgeonTable`
- `resources/js/v2/reportes-v2/cirugias/app.tsx` — entry point, reads `window.MF_CIR_REPORT`, renders full page
- `resources/views/cirugias/v2-dashboard-report.blade.php` — fullscreen Blade view

**New files — imágenes module:**
- `resources/js/v2/reportes-v2/imagenes/types.ts` — `ImagenesReport` interface
- `resources/js/v2/reportes-v2/imagenes/toolbar.tsx` — period/sede selector
- `resources/js/v2/reportes-v2/imagenes/sections.tsx` — Sections 02/03/04 + `ImgReconTable`, `ImgDoctorTable`
- `resources/js/v2/reportes-v2/imagenes/app.tsx` — entry point, reads `window.MF_IMG_REPORT`
- `resources/views/examenes/v2-imagenes-dashboard-report.blade.php` — fullscreen Blade view

**Modified files:**
- `laravel-app/vite.config.js` — add two new entry points
- `laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php` — add `buildReportPayload()` + static schema cache (if `tableExists`/`columnExists` exist)
- `laravel-app/app/Modules/Cirugias/Http/Controllers/CirugiasUiController.php` — add `dashboardReport()` method
- `laravel-app/app/Modules/Examenes/Http/Controllers/ImagenesUiController.php` — add `dashboardReport()` method
- `laravel-app/routes/v2/cirugias.php` — add report route
- `laravel-app/routes/web.php` — add imagenes report route

---

## Task 1: Install Recharts and add Vite entries

**Files:**
- Modify: `laravel-app/package.json` (via npm install)
- Modify: `laravel-app/vite.config.js`

- [ ] **Step 1: Install recharts**

```bash
cd laravel-app && npm install recharts
```

Expected: `added N packages` with recharts appearing in `node_modules/recharts/`.

- [ ] **Step 2: Add two new entry points to vite.config.js**

In `laravel-app/vite.config.js`, add these two lines to the `input` array, after the existing `'resources/js/agenda/main.tsx'` line:

```js
'resources/js/v2/reportes-v2/cirugias/app.tsx',
'resources/js/v2/reportes-v2/imagenes/app.tsx',
```

The `input` array should now end with:
```js
'resources/js/agenda/main.tsx',
'resources/js/whatsapp/main.jsx',
'resources/js/v2/reportes-v2/cirugias/app.tsx',
'resources/js/v2/reportes-v2/imagenes/app.tsx',
```

- [ ] **Step 3: Verify vite config parses**

```bash
cd laravel-app && node -e "import('./vite.config.js').then(()=>console.log('OK'))" 2>&1 | head -5
```

Expected: `OK` (or no error output).

- [ ] **Step 4: Commit**

```bash
cd laravel-app && git add package.json package-lock.json vite.config.js
git commit -m "feat(reportes-v2): add recharts and vite entry points"
```

---

## Task 2: Create shared CSS file

**Files:**
- Create: `public/css/v2/reportes-v2.css`

The CSS contains design tokens from `colors_and_type.css` plus the full `.rep-*` ruleset from `report.styles.css` (417 lines). Read both source files and combine them.

- [ ] **Step 1: Read the design source CSS**

```bash
cat /tmp/design_extracted/medforge-design-system/project/reportes_v2/report.styles.css
```

- [ ] **Step 2: Create the combined CSS file**

Create `laravel-app/public/css/v2/reportes-v2.css` with this structure (prepend the token block, then append the full report.styles.css content):

```css
/* MedForge design tokens — reportes v2 */
:root {
  --primary:        #5156be;
  --primary-hover:  #3c40a0;
  --primary-light:  #c8c9ee;
  --primary-fade:   #edf2ff;
  --primary-on:     #ffffff;
  --info:           #3596f7;
  --success:        #05825f;
  --warning:        #ffa800;
  --danger:         #ee3158;
  --fg-1:           #172b4c;
  --fg-2:           #3f4254;
  --fg-3:           #5e6278;
  --fg-mute:        #7e8299;
  --fg-fade:        #b5b5c3;
  --bg:             #ffffff;
  --bg-soft:        #f3f6f9;
  --bg-softer:      #ebedf3;
  --border:         #e4e6ef;
  --border-strong:  #d1d3e0;
  --gray-300:       #e4e6ef;
  --gray-500:       #b5b5c3;
  --gray-600:       #7e8299;
  --brand-navy:     #060B28;
  --cat-cirugia:    #d34b5b;
  --font-body:      "IBM Plex Sans", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
  --font-display:   "Rubik", "IBM Plex Sans", system-ui, sans-serif;
  --radius:         8px;
  --radius-sm:      6px;
  --radius-lg:      14px;
  --radius-pill:    999px;
  --shadow:         0 4px 12px rgba(16, 24, 40, 0.08);
  --shadow-sm:      0 1px 3px rgba(16, 24, 40, 0.08);
  --dur-base:       200ms;
  --ease-out:       cubic-bezier(0.16, 1, 0.3, 1);
}

/* ---- paste full content of report.styles.css here ---- */
```

Then paste the entire content of `/tmp/design_extracted/medforge-design-system/project/reportes_v2/report.styles.css` below the comment.

- [ ] **Step 3: Verify the file exists and has content**

```bash
wc -l laravel-app/public/css/v2/reportes-v2.css
```

Expected: 450+ lines.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/public/css/v2/reportes-v2.css
git commit -m "feat(reportes-v2): add combined CSS with design tokens and rep-* styles"
```

---

## Task 3: Create shared TypeScript types

**Files:**
- Create: `resources/js/v2/reportes-v2/shared/types.ts`

- [ ] **Step 1: Create the types file**

Create `laravel-app/resources/js/v2/reportes-v2/shared/types.ts`:

```ts
export interface LabelValue {
  label: string;
  value: number;
}

export interface NameTotal {
  name: string;
  total: number;
}

export interface TrendPoint {
  label: string;
  realizadas: number;
  facturadas: number;
}

export interface ExecKpi {
  cls: string;
  source: string;
  label: string;
  value: string;
  hint: string;
}

export interface FlowStage {
  key: string;
  cls: string;
  label: string;
  value: number;
  context: string;
  leak?: { label: string; count: number; amount: number };
}

export interface FlowLink {
  pct: number;
}

export interface ExecSummary {
  oportunidad: string;
  arrastre: string;
  sla: string;
}

export interface ExecAction {
  severity: string;
  title: string;
  metric: string;
  owner: string;
  action: string;
}

export interface ExecLedger {
  label: string;
  value: string;
  tone?: string;
}

export interface ExecMap {
  kpis: ExecKpi[];
  flow: FlowStage[];
  links: FlowLink[];
  summary: ExecSummary;
  actions: ExecAction[];
  ledger: ExecLedger[];
}

export interface PeriodMeta {
  label: string;
  fromLabel: string;
  toLabel: string;
}

export interface SedeMeta {
  label: string;
}

export interface SynthCellData {
  label: string;
  value: string | number;
  unit?: string;
  delta?: number;
  deltaSuffix?: string;
  invert?: boolean;
}
```

- [ ] **Step 2: Verify TypeScript parses (no errors)**

```bash
cd laravel-app && npx tsc --noEmit --project tsconfig.json 2>&1 | grep "reportes-v2/shared/types" | head -5
```

Expected: no output (no errors for this file).

- [ ] **Step 3: Commit**

```bash
git add laravel-app/resources/js/v2/reportes-v2/shared/types.ts
git commit -m "feat(reportes-v2): add shared TypeScript base types"
```

---

## Task 4: Create shared chart components

**Files:**
- Create: `resources/js/v2/reportes-v2/shared/charts.tsx`

- [ ] **Step 1: Create charts.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/shared/charts.tsx`:

```tsx
import React, { useRef, useState, useLayoutEffect } from 'react';
import {
  ComposedChart, AreaChart, Area, Line,
  BarChart, Bar, PieChart, Pie, Cell,
  XAxis, YAxis, CartesianGrid, Tooltip,
  type TooltipProps,
} from 'recharts';

export const PALETTE = ['#5156be', '#3596f7', '#05825f', '#d59623', '#0e9bb3', '#d34b5b', '#7C4DFF', '#7e8299'];

const AXIS_TICK = { fill: '#7e8299', fontSize: 11, fontFamily: '"IBM Plex Sans", sans-serif' };
const AXIS_LINE = { stroke: '#e4e6ef' };

interface MeasuredProps {
  height: number;
  className?: string;
  children: (width: number) => React.ReactNode;
}

export function Measured({ height, className = 'rep-chart', children }: MeasuredProps) {
  const ref = useRef<HTMLDivElement>(null);
  const [w, setW] = useState(0);

  useLayoutEffect(() => {
    const el = ref.current;
    if (!el) return;
    const measure = () => {
      const cw = Math.round(el.clientWidth || el.getBoundingClientRect().width || 0);
      if (cw > 0) setW(prev => Math.abs(prev - cw) > 1 ? cw : prev);
    };
    measure();
    const raf = requestAnimationFrame(measure);
    let ro: ResizeObserver | undefined;
    if (typeof ResizeObserver !== 'undefined') {
      ro = new ResizeObserver(measure);
      ro.observe(el);
    }
    window.addEventListener('resize', measure);
    return () => { cancelAnimationFrame(raf); ro?.disconnect(); window.removeEventListener('resize', measure); };
  }, []);

  return (
    <div className={className} ref={ref} style={{ height, width: '100%' }}>
      {w > 0 ? children(w) : null}
    </div>
  );
}

interface TooltipPayloadItem {
  name: string;
  value: number;
  color?: string;
  fill?: string;
}

interface RepTooltipProps extends TooltipProps<number, string> {
  unit?: string;
  prefix?: string;
  title?: string;
}

export function RepTooltip({ active, payload, label, unit = '', prefix = '', title }: RepTooltipProps) {
  if (!active || !payload?.length) return null;
  return (
    <div style={{ background: '#fff', border: '1px solid #e4e6ef', borderRadius: 8, boxShadow: '0 4px 12px rgba(16,24,40,.12)', padding: '9px 12px', font: '12px "IBM Plex Sans", sans-serif' }}>
      <div style={{ fontWeight: 700, color: '#172b4c', marginBottom: 5 }}>{title ?? label}</div>
      {(payload as TooltipPayloadItem[]).map((p, i) => (
        <div key={i} style={{ display: 'flex', alignItems: 'center', gap: 7, color: '#3f4254', marginTop: 2 }}>
          <span style={{ width: 9, height: 9, borderRadius: 2, background: p.color ?? p.fill, display: 'inline-block' }} />
          <span style={{ flex: 1 }}>{p.name}</span>
          <strong style={{ color: '#172b4c', fontVariantNumeric: 'tabular-nums' }}>{prefix}{Number(p.value).toLocaleString('es-EC')}{unit}</strong>
        </div>
      ))}
    </div>
  );
}

interface TrendAreaProps {
  data: Record<string, string | number>[];
  keys: [string, string];
  names: [string, string];
  height?: number;
}

export function TrendArea({ data, keys, names, height = 250 }: TrendAreaProps) {
  const [k1, k2] = keys;
  return (
    <Measured height={height}>
      {w => (
        <ComposedChart width={w} height={height} data={data} margin={{ top: 10, right: 6, left: -14, bottom: 0 }}>
          <defs>
            <linearGradient id="gA" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor="#5156be" stopOpacity={0.24} />
              <stop offset="100%" stopColor="#5156be" stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey="label" axisLine={AXIS_LINE} tickLine={false} tick={AXIS_TICK} interval="preserveStartEnd" minTickGap={14} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS_TICK} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} />
          <Area type="monotone" dataKey={k1} name={names[0]} stroke="#5156be" strokeWidth={2.4} fill="url(#gA)" isAnimationActive={false} />
          <Line type="monotone" dataKey={k2} name={names[1]} stroke="#05825f" strokeWidth={2.4} dot={false} isAnimationActive={false} />
        </ComposedChart>
      )}
    </Measured>
  );
}

interface AreaSeriesProps {
  data: Record<string, string | number>[];
  dataKey?: string;
  name?: string;
  color?: string;
  height?: number;
}

export function AreaSeries({ data, dataKey = 'value', name = 'Valor', color = '#3596f7', height = 220 }: AreaSeriesProps) {
  return (
    <Measured height={height}>
      {w => (
        <AreaChart width={w} height={height} data={data} margin={{ top: 10, right: 6, left: -16, bottom: 0 }}>
          <defs>
            <linearGradient id="gS" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stopColor={color} stopOpacity={0.22} />
              <stop offset="100%" stopColor={color} stopOpacity={0.02} />
            </linearGradient>
          </defs>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey="label" axisLine={AXIS_LINE} tickLine={false} tick={AXIS_TICK} interval="preserveStartEnd" minTickGap={20} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS_TICK} width={42} allowDecimals={false} />
          <Tooltip content={<RepTooltip />} />
          <Area type="monotone" dataKey={dataKey} name={name} stroke={color} strokeWidth={2.2} fill="url(#gS)" isAnimationActive={false} />
        </AreaChart>
      )}
    </Measured>
  );
}

interface ColumnChartDataItem {
  label?: string;
  value?: number;
  color?: string;
  [key: string]: unknown;
}

interface ColumnChartProps {
  data: ColumnChartDataItem[];
  dataKey?: string;
  labelKey?: string;
  name?: string;
  height?: number;
  colors?: string[];
  money?: boolean;
}

export function ColumnChart({ data, dataKey = 'value', labelKey = 'label', name = 'Valor', height = 230, colors, money = false }: ColumnChartProps) {
  return (
    <Measured height={height}>
      {w => (
        <BarChart width={w} height={height} data={data} margin={{ top: 8, right: 6, left: money ? -4 : -16, bottom: 0 }}>
          <CartesianGrid vertical={false} stroke="#ebedf3" />
          <XAxis dataKey={labelKey} axisLine={AXIS_LINE} tickLine={false} tick={AXIS_TICK} />
          <YAxis axisLine={false} tickLine={false} tick={AXIS_TICK} width={money ? 52 : 42} allowDecimals={false}
            tickFormatter={money ? v => '$' + (v >= 1000 ? (v / 1000).toFixed(0) + 'k' : v) : undefined} />
          <Tooltip content={<RepTooltip prefix={money ? '$' : ''} />} cursor={{ fill: 'rgba(81,86,190,.05)' }} />
          <Bar dataKey={dataKey} name={name} radius={[6, 6, 0, 0]} maxBarSize={64} isAnimationActive={false}>
            {data.map((d, i) => (
              <Cell key={i} fill={(d.color as string) ?? colors?.[i % (colors?.length ?? 1)] ?? '#5156be'} />
            ))}
          </Bar>
        </BarChart>
      )}
    </Measured>
  );
}

interface DonutDataItem {
  label?: string;
  name?: string;
  total?: number;
  count?: number;
  color?: string;
  [key: string]: unknown;
}

interface DonutChartProps {
  data: DonutDataItem[];
  nameKey?: string;
  valueKey?: string;
  height?: number;
  unit?: string;
  innerR?: string;
  centerLabel?: string;
}

export function DonutChart({ data, nameKey = 'label', valueKey = 'total', height = 230, unit = '', innerR = '62%', centerLabel = 'total' }: DonutChartProps) {
  const total = data.reduce((a, d) => a + (Number(d[valueKey]) || 0), 0);
  return (
    <Measured height={height}>
      {w => (
        <div style={{ position: 'relative' }}>
          <PieChart width={w} height={height}>
            <Pie data={data} dataKey={valueKey} nameKey={nameKey} cx="50%" cy="50%" innerRadius={innerR} outerRadius="92%" paddingAngle={2} stroke="#fff" strokeWidth={2} isAnimationActive={false}>
              {data.map((d, i) => <Cell key={i} fill={d.color ?? PALETTE[i % PALETTE.length]} />)}
            </Pie>
            <Tooltip content={<RepTooltip unit={unit} />} />
          </PieChart>
          <div style={{ position: 'absolute', inset: 0, display: 'grid', placeItems: 'center', pointerEvents: 'none' }}>
            <div style={{ textAlign: 'center' }}>
              <div style={{ font: '600 26px/1 "Rubik", sans-serif', color: '#172b4c', fontVariantNumeric: 'tabular-nums' }}>{total.toLocaleString('es-EC')}</div>
              <div style={{ font: '600 10px "IBM Plex Sans", sans-serif', textTransform: 'uppercase', letterSpacing: '.06em', color: '#7e8299', marginTop: 3 }}>{centerLabel}</div>
            </div>
          </div>
        </div>
      )}
    </Measured>
  );
}
```

- [ ] **Step 2: Verify TypeScript compiles**

```bash
cd laravel-app && npx tsc --noEmit 2>&1 | grep "reportes-v2/shared/charts" | head -10
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add laravel-app/resources/js/v2/reportes-v2/shared/charts.tsx
git commit -m "feat(reportes-v2): add shared Recharts wrapper components"
```

---

## Task 5: Create shared lib components

**Files:**
- Create: `resources/js/v2/reportes-v2/shared/lib.tsx`

- [ ] **Step 1: Create lib.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/shared/lib.tsx`:

```tsx
import React from 'react';
import type { ExecMap, SynthCellData, PeriodMeta, SedeMeta } from './types';

export function fmt(n: number): string {
  return Number(n).toLocaleString('es-EC');
}

interface SynthCellProps extends SynthCellData {}

export function SynthCell({ label, value, unit, delta, deltaSuffix = '%', invert = false }: SynthCellProps) {
  let cls = 'flat', icon = 'mdi-minus', shown = 0;
  if (delta != null && delta !== 0) {
    const positive = delta > 0;
    const good = invert ? !positive : positive;
    cls = good ? 'up' : 'down';
    icon = positive ? 'mdi-arrow-up-thin' : 'mdi-arrow-down-thin';
    shown = Math.abs(delta);
  }
  return (
    <div className="rep-synth-cell">
      <div className="rep-synth-l">{label}</div>
      <div className="rep-synth-v">{value}{unit ? <small>{unit}</small> : null}</div>
      <div className={`rep-synth-d ${cls}`}><i className={`mdi ${icon}`}></i>{shown}{deltaSuffix} vs. período anterior</div>
    </div>
  );
}

interface CoverProps {
  unit: string;
  unitLabel: string;
  unitIcon: string;
  title: string;
  lede: string;
  period: PeriodMeta;
  sede: SedeMeta;
  generatedAt: string;
  synth: SynthCellData[];
}

export function Cover({ unit, unitLabel, unitIcon, title, lede, period, sede, generatedAt, synth }: CoverProps) {
  return (
    <header className="rep-cover" data-unit={unit}>
      <div className="rep-cover-eyebrow">
        <i className={`mdi ${unitIcon}`}></i>Reporte ejecutivo
        <span className="rep-unit-chip"><i className={`mdi ${unitIcon}`}></i>{unitLabel}</span>
      </div>
      <h1>{title}</h1>
      <p className="rep-cover-lede">{lede}</p>
      <div className="rep-cover-meta">
        <div className="m"><div className="ml">Período</div><div className="mv"><i className="mdi mdi-calendar-range"></i>{period.fromLabel} → {period.toLabel}</div></div>
        <div className="m"><div className="ml">Sede</div><div className="mv"><i className="mdi mdi-map-marker"></i>{sede.label}</div></div>
        <div className="m"><div className="ml">Unidad</div><div className="mv"><i className={`mdi ${unitIcon}`}></i>{unitLabel}</div></div>
        <div className="m"><div className="ml">Generado</div><div className="mv"><i className="mdi mdi-clock-outline"></i>{generatedAt}</div></div>
      </div>
      <div className="rep-synth">
        {synth.map((s, i) => <SynthCell key={i} {...s} />)}
      </div>
    </header>
  );
}

interface SectionProps {
  num: string;
  kicker: string;
  title: string;
  lede?: string;
  children?: React.ReactNode;
}

export function Section({ num, kicker, title, lede, children }: SectionProps) {
  return (
    <section className="rep-section">
      <div className="rep-sec-head">
        <div className="rep-sec-num">{num}</div>
        <div className="rep-sec-headmain">
          <div className="rep-sec-kicker">{kicker}</div>
          <h2>{title}</h2>
          {lede ? <p className="rep-sec-lede" dangerouslySetInnerHTML={{ __html: lede }} /> : null}
        </div>
      </div>
      {children}
    </section>
  );
}

export function ExecutiveMap({ exec, unit }: { exec: ExecMap; unit: string }) {
  const e = exec;
  const ledMoney = (tone?: string) => tone === 'warn' ? 'is-warn' : tone === 'danger' ? 'is-danger' : '';
  return (
    <section className="rep-execmap" id="mapa-ejecutivo">
      <div className="rep-execmap-head">
        <div>
          <span className="rep-execmap-kicker"><i className="mdi mdi-map-marker-path"></i>Mapa ejecutivo financiero</span>
          <h2>De la solicitud al cobro: dónde se gana, se bloquea o se pierde</h2>
          <p>Cada indicador está conectado con una etapa del flujo para diferenciar facturado real, oportunidad estimada, pendiente de pago y pérdida.</p>
        </div>
      </div>
      <div className="rep-execkpis">
        {e.kpis.map((k, i) => (
          <div key={i} className={`rep-execkpi is-${k.cls}`}>
            <span className="rep-execkpi-src"><i className="mdi mdi-checkbox-blank-circle"></i>{k.source}</span>
            <div className="rep-execkpi-label">{k.label}</div>
            <div className="rep-execkpi-val">{k.value}</div>
            <div className="rep-execkpi-hint">{k.hint}</div>
          </div>
        ))}
      </div>
      <div className="rep-execmap-body">
        <div className="rep-flowwrap">
          <div className="h">
            <h3><i className="mdi mdi-transit-connection-variant"></i>Flujo conectado</h3>
            <span className="note">Las fugas bajo cada etapa explican por qué el dinero no llega a facturación / cobro.</span>
          </div>
          <div className="rep-flow">
            {e.flow.map((stage, i) => (
              <React.Fragment key={stage.key}>
                <div className={`rep-flow-stage is-${stage.cls}`}>
                  <span className="st-label">{stage.label}</span>
                  <span className="st-val">{fmt(stage.value)}</span>
                  <span className="st-ctx">{stage.context}</span>
                  {stage.leak && (stage.leak.count > 0 || stage.leak.amount > 0) ? (
                    <div className="rep-flow-leak">
                      <b><i className="mdi mdi-arrow-down-thin"></i>{stage.leak.label}</b>
                      <em>{fmt(stage.leak.count)}</em>
                    </div>
                  ) : null}
                </div>
                {i < e.flow.length - 1 ? (
                  <div className="rep-flow-link" aria-hidden="true">
                    <span className="pct">{e.links[i].pct}%</span>
                    <i className="mdi mdi-arrow-right-thin"></i>
                    <span className="pctlabel">conv.</span>
                  </div>
                ) : null}
              </React.Fragment>
            ))}
          </div>
          <div className="rep-flow-summary">
            <div><span className="s-l"><i className="mdi mdi-cash-multiple"></i>Oportunidad estimada</span><span className="s-v">{e.summary.oportunidad}</span></div>
            <div><span className="s-l"><i className="mdi mdi-progress-clock"></i>Arrastre al corte</span><span className="s-v">{e.summary.arrastre}</span></div>
            <div><span className="s-l"><i className="mdi mdi-timer-outline"></i>Informes / SLA</span><span className="s-v">{e.summary.sla}</span></div>
          </div>
        </div>
        <aside className="rep-actions">
          <div className="h"><h3><i className="mdi mdi-flag-outline"></i>Acciones prioritarias</h3></div>
          <div className="rep-action-list">
            {e.actions.map((a, i) => (
              <a key={i} className={`rep-action sev-${a.severity}`} href="#mapa-ejecutivo">
                <span className="dot"></span>
                <span>
                  <span className="a-title">{a.title}</span>
                  <span className="a-meta"><b>{a.metric}</b> · {a.owner}</span>
                  <span className="a-do">{a.action}</span>
                </span>
              </a>
            ))}
          </div>
          <div className="rep-ledger">
            {e.ledger.map((l, i) => (
              <div key={i} className={ledMoney(l.tone)}>
                <span className="l-l">{l.label}</span>
                <span className="l-v">{l.value}</span>
              </div>
            ))}
          </div>
        </aside>
      </div>
    </section>
  );
}

interface KpiProps {
  icon?: string;
  label: string;
  value: string | number;
  unit?: string;
  sub?: string;
  accent?: string;
}

export function Kpi({ icon, label, value, unit, sub, accent }: KpiProps) {
  return (
    <div className="rep-kpi" style={accent ? { '--kpi-accent': accent } as React.CSSProperties : undefined}>
      <div className="rep-kpi-top">
        {icon ? <span className="rep-kpi-ic"><i className={`mdi ${icon}`}></i></span> : null}
        <span className="rep-kpi-label">{label}</span>
      </div>
      <div className="rep-kpi-valrow">
        <span className="rep-kpi-value">{value}{unit ? <small>{unit}</small> : null}</span>
      </div>
      {sub ? <div className="rep-kpi-sub" dangerouslySetInnerHTML={{ __html: sub }} /> : null}
    </div>
  );
}

interface BarsListItem {
  [key: string]: unknown;
}

interface BarsListProps {
  items: BarsListItem[];
  valueKey?: string;
  labelKey?: string;
  color?: string;
  format?: (v: number) => string;
  max?: number;
  suffix?: string;
}

export function BarsList({ items, valueKey = 'total', labelKey = 'label', color = 'var(--primary)', format, max, suffix = '' }: BarsListProps) {
  const mx = max ?? Math.max(1, ...items.map(d => Number(d[valueKey]) || 0));
  const f = format ?? (v => fmt(v) + suffix);
  return (
    <div className="rep-bars">
      {items.map((d, i) => {
        const val = Number(d[valueKey]) || 0;
        const lbl = String(d[labelKey] ?? d['name'] ?? '');
        return (
          <div key={i}>
            <div className="rep-bar-top">
              <span className="rep-bar-name"><span className="rep-bar-txt">{lbl}</span></span>
              <span className="rep-bar-meta"><strong>{f(val)}</strong></span>
            </div>
            <div className="rep-bar-track"><div className="rep-bar-fill" style={{ width: (val / mx * 100) + '%', background: color }}></div></div>
          </div>
        );
      })}
    </div>
  );
}

interface DonutLegendItem {
  label?: string;
  color?: string;
  [key: string]: unknown;
}

export function DonutLegend({ items, valueKey = 'total' }: { items: DonutLegendItem[]; valueKey?: string }) {
  const total = items.reduce((a, d) => a + (Number(d[valueKey]) || 0), 0);
  return (
    <div className="rep-chart-legend" style={{ flexDirection: 'column', gap: 9, marginTop: 14 }}>
      {items.map((d, i) => {
        const val = Number(d[valueKey]) || 0;
        return (
          <div key={i} className="rep-leg" style={{ width: '100%' }}>
            <b style={{ background: d.color }}></b>
            <span style={{ flex: 1 }}>{d.label}</span>
            <strong style={{ color: 'var(--fg-1)', fontVariantNumeric: 'tabular-nums' }}>{fmt(val)}</strong>
            <span style={{ color: 'var(--fg-mute)', minWidth: 42, textAlign: 'right' }}>{total > 0 ? Math.round(val / total * 100) : 0}%</span>
          </div>
        );
      })}
    </div>
  );
}

export function Read({ children }: { children: React.ReactNode }) {
  return <div className="rep-read"><i className="mdi mdi-lightbulb-on-outline lead"></i><p>{children}</p></div>;
}

export function Insight({ accent, title, children }: { accent?: string; title: string; children: string }) {
  return (
    <div className="rep-insight" style={accent ? { '--ins-accent': accent } as React.CSSProperties : undefined}>
      <div className="rep-insight-h"><span className="dot"></span><h4>{title}</h4></div>
      <p dangerouslySetInnerHTML={{ __html: children }} />
    </div>
  );
}

export function Recs({ items }: { items: string[] }) {
  return (
    <div className="rep-recs">
      {items.map((t, i) => (
        <div key={i} className="rep-rec"><span className="rep-rec-num">{i + 1}</span><p dangerouslySetInnerHTML={{ __html: t }} /></div>
      ))}
    </div>
  );
}

export function Footnote() {
  return (
    <footer className="rep-footnote">
      <span className="fn-legend"><span className="fn-chip">Reporte</span>Lectura ejecutiva — recalcula con los filtros de período y sede.</span>
      <span className="fn-brand">MedForge by Consulmed · Generado automáticamente</span>
    </footer>
  );
}

export function RecsCard({ children }: { children: React.ReactNode }) {
  return (
    <div className="rep-card" style={{ background: 'var(--bg-soft)' }}>
      <div className="rep-card-head"><h3><i className="mdi mdi-clipboard-check-outline"></i>Recomendaciones del período</h3></div>
      {children}
    </div>
  );
}
```

- [ ] **Step 2: Verify TypeScript compiles**

```bash
cd laravel-app && npx tsc --noEmit 2>&1 | grep "reportes-v2/shared/lib" | head -10
```

Expected: no output.

- [ ] **Step 3: Commit**

```bash
git add laravel-app/resources/js/v2/reportes-v2/shared/lib.tsx
git commit -m "feat(reportes-v2): add shared layout/lib components"
```

---

## Task 6: Add buildReportPayload to CirugiasDashboardService

**Files:**
- Modify: `laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php`

This task adds one new public method `buildReportPayload()` that aggregates existing service methods into the shape expected by the frontend `window.MF_CIR_REPORT`.

- [ ] **Step 1: Find the end of the class to add the method**

The method signature of all existing public methods uses `string $start, string $end, ...` as first two params. The new method will follow the same pattern but accept a filter array.

Add the following method to `CirugiasDashboardService` (before the closing `}` of the class). Find a good insertion point after `getCirugiasSinSolicitudPrevia()`.

```php
public function buildReportPayload(
    string $start,
    string $end,
    string $sedeFilter = ''
): array {
    $realizadas = $this->getTotalCirugias($start, $end, '', '', $sedeFilter);
    $trazabilidad = $this->getCirugiasFacturacionTrazabilidad($start, $end, '', '', $sedeFilter);
    $facturadas = (int) ($trazabilidad['facturados'] ?? 0);
    $pendienteFacturar = (int) ($trazabilidad['pendiente_facturar'] ?? 0);
    $pendientePago = (int) ($trazabilidad['pendiente_pago'] ?? 0);
    $produccionFacturada = (float) ($trazabilidad['produccion_facturada'] ?? 0);

    $programacion = $this->getProgramacionKpis($start, $end, '', '', $sedeFilter);
    $solicitudes = (int) ($programacion['solicitudes'] ?? $programacion['programadas'] ?? 0);
    $programadas = (int) ($programacion['programadas'] ?? 0);
    $informadas = (int) ($trazabilidad['informados'] ?? $facturadas);

    $porMes = $this->getCirugiasPorMes($start, $end, '', '', $sedeFilter);
    $labels = $porMes['labels'] ?? [];
    $totalsArr = $porMes['totals'] ?? [];
    $produccionMensual = [];
    foreach ($labels as $i => $lbl) {
        $produccionMensual[] = [
            'label'      => $lbl,
            'realizadas' => $totalsArr[$i] ?? 0,
            'facturadas' => 0, // facturación mensual detallada no disponible aún
        ];
    }

    $topProc = $this->getTopProcedimientos($start, $end, 10, '', '', $sedeFilter);
    $topCir  = $this->getTopCirujanos($start, $end, 10, '', '', $sedeFilter);
    $convenio = $this->getCirugiasPorConvenio($start, $end, '', '', $sedeFilter);

    $tatData   = $this->getTatRevisionProtocolos($start, $end, '', '', $sedeFilter);
    $duracion  = $this->getDuracionPromedioMinutos($start, $end, '', '', $sedeFilter);
    $reingreso = $this->getReingresoMismoDiagnostico($start, $end);

    $trazabilidadDonut = [
        ['label' => 'Facturadas',         'total' => $facturadas,      'color' => '#05825f'],
        ['label' => 'Pendiente facturar', 'total' => $pendienteFacturar,'color' => '#ffa800'],
        ['label' => 'Pendiente pago',     'total' => $pendientePago,    'color' => '#3596f7'],
    ];

    $cumplimiento = $solicitudes > 0 ? round($realizadas / $solicitudes * 100) : 0;

    $oportunidadEstimada = $pendienteFacturar * ($realizadas > 0 ? $produccionFacturada / $realizadas : 0);
    $moneyFmt = fn (float $v): string => '$' . number_format($v, 0, ',', '.');

    return [
        'unit'      => 'cirugias',
        'unitLabel' => 'Cirugías',
        'unitIcon'  => 'mdi-hospital-box-outline',
        'generatedAt' => now()->format('d/m/Y H:i'),
        'period'    => [
            'label'     => $start . ' → ' . $end,
            'fromLabel' => (new \DateTimeImmutable($start))->format('d/m/Y'),
            'toLabel'   => (new \DateTimeImmutable($end))->format('d/m/Y'),
        ],
        'sede'      => ['label' => $sedeFilter ?: 'Todas las sedes'],
        'synth'     => [
            ['label' => 'Cirugías realizadas',  'value' => $realizadas,   'unit' => '',    'delta' => 0],
            ['label' => 'Facturadas',            'value' => $facturadas,   'unit' => '',    'delta' => 0],
            ['label' => 'Cumplimiento',          'value' => $cumplimiento, 'unit' => '%',   'delta' => 0],
            ['label' => 'Producción facturada',  'value' => $moneyFmt($produccionFacturada), 'delta' => 0],
        ],
        'exec'      => [
            'kpis'    => [
                ['cls' => 'ok',   'source' => 'Programación', 'label' => 'Solicitudes',        'value' => number_format($solicitudes),  'hint' => 'Demanda del período'],
                ['cls' => 'ok',   'source' => 'Quirófano',    'label' => 'Realizadas',          'value' => number_format($realizadas),   'hint' => 'Cumplimiento ' . $cumplimiento . '%'],
                ['cls' => 'warn', 'source' => 'Billing',      'label' => 'Pendiente facturar',  'value' => number_format($pendienteFacturar), 'hint' => 'Backlog de billing'],
                ['cls' => 'info', 'source' => 'Cartera',      'label' => 'Pendiente de cobro',  'value' => number_format($pendientePago), 'hint' => 'Facturadas no cobradas'],
            ],
            'flow'    => [
                ['key' => 'sol',  'cls' => 'ok',   'label' => 'Solicitadas',   'value' => $solicitudes,      'context' => 'Solicitudes ingresadas', 'leak' => ['label' => 'Sin programar', 'count' => max(0, $solicitudes - $programadas), 'amount' => 0]],
                ['key' => 'prog', 'cls' => 'ok',   'label' => 'Programadas',   'value' => $programadas,      'context' => 'En agenda quirúrgica',   'leak' => ['label' => 'No realizadas',  'count' => max(0, $programadas - $realizadas), 'amount' => 0]],
                ['key' => 'real', 'cls' => 'ok',   'label' => 'Realizadas',    'value' => $realizadas,       'context' => 'Completadas en quirófano','leak' => ['label' => 'Sin billing',    'count' => $pendienteFacturar, 'amount' => 0]],
                ['key' => 'fact', 'cls' => 'warn', 'label' => 'Facturadas',    'value' => $facturadas,       'context' => 'Con emisión de factura',  'leak' => ['label' => 'Pendiente pago', 'count' => $pendientePago, 'amount' => 0]],
                ['key' => 'cobr', 'cls' => 'ok',   'label' => 'Cobradas',      'value' => max(0, $facturadas - $pendientePago), 'context' => 'Pagadas al corte', 'leak' => null],
            ],
            'links'   => [
                ['pct' => $solicitudes > 0 ? round($programadas / $solicitudes * 100) : 0],
                ['pct' => $programadas > 0 ? round($realizadas / $programadas * 100)  : 0],
                ['pct' => $realizadas > 0  ? round($facturadas / $realizadas * 100)   : 0],
                ['pct' => $facturadas > 0  ? round(max(0, $facturadas - $pendientePago) / $facturadas * 100) : 0],
            ],
            'summary' => [
                'oportunidad' => $moneyFmt($oportunidadEstimada),
                'arrastre'    => number_format($pendienteFacturar) . ' cirugías sin facturar al corte',
                'sla'         => round((float)($tatData['prom'] ?? 0)) . 'h promedio TAT protocolo',
            ],
            'actions' => [
                ['severity' => 'high',   'title' => 'Cerrar protocolos pendientes', 'metric' => number_format($pendienteFacturar) . ' pendientes', 'owner' => 'Coordinación quirúrgica', 'action' => 'Completar documentación para destrabar billing'],
                ['severity' => 'medium', 'title' => 'Emitir billing del backlog',   'metric' => $moneyFmt($oportunidadEstimada),                   'owner' => 'Facturación',            'action' => 'Priorizar cirugías realizadas sin factura'],
            ],
            'ledger'  => [
                ['label' => 'Producción facturada',    'value' => $moneyFmt($produccionFacturada),  'tone' => 'ok'],
                ['label' => 'Pendiente de cobro',      'value' => number_format($pendientePago) . ' registros', 'tone' => 'warn'],
                ['label' => 'Oportunidad estimada',    'value' => $moneyFmt($oportunidadEstimada),  'tone' => 'warn'],
            ],
        ],
        'metrics'   => [
            'solicitudes'    => $solicitudes,
            'programadas'    => $programadas,
            'realizadas'     => $realizadas,
            'informadas'     => $informadas,
            'facturadas'     => $facturadas,
            'pendienteFacturar' => $pendienteFacturar,
            'pendientePagoN' => $pendientePago,
            'cumplimiento'   => $cumplimiento,
            'duracionProm'   => round((float) $duracion),
            'tatProm'        => (float) ($tatData['prom'] ?? 0),
            'tatMed'         => (float) ($tatData['mediana'] ?? 0),
            'tatP90'         => (float) ($tatData['p90'] ?? 0),
            'tatMuestra'     => (int)   ($tatData['muestra'] ?? 0),
            'reingreso'      => (int)   ($reingreso['count'] ?? 0),
        ],
        'produccionMensual' => $produccionMensual,
        'trazabilidad'      => $trazabilidadDonut,
        'topProcedimientos' => array_map(fn ($r) => ['label' => $r['nombre'] ?? $r['name'] ?? '', 'total' => (int)($r['total'] ?? 0)], $topProc),
        'topProcIngreso'    => [], // requiere datos de tarifa por procedimiento — placeholder vacío
        'topCirujanos'      => array_map(fn ($r) => ['name' => $r['nombre'] ?? $r['name'] ?? '', 'realizadas' => (int)($r['total'] ?? 0)], $topCir),
        'topSolicitantes'   => [], // requiere getTopDoctoresSolicitudesRealizadas si existe
        'porConvenio'       => array_map(fn ($r) => ['label' => $r['nombre'] ?? $r['name'] ?? '', 'total' => (int)($r['total'] ?? 0)], $convenio),
        'mixCategoria'      => [], // requiere getCirugiasPorCategoriaAfiliacion
    ];
}
```

> **Note:** Check what keys `getTatRevisionProtocolos`, `getProgramacionKpis`, `getCirugiasPorConvenio`, `getTopCirujanos`, `getTopProcedimientos` actually return by reading those methods (lines ~617-1200) before inserting. Adjust array key access (`$r['nombre']` vs `$r['name']`) to match real keys. `getTopDoctoresSolicitudesRealizadas()` and `getCirugiasPorCategoriaAfiliacion()` exist if grep finds them — add them to `topSolicitantes` / `mixCategoria` if available.

- [ ] **Step 2: Grep actual return key names**

```bash
grep -A 20 "public function getTopCirujanos" laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php | head -25
grep -A 20 "public function getTopProcedimientos" laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php | head -25
grep -A 20 "public function getTatRevisionProtocolos" laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php | head -25
grep -A 20 "public function getProgramacionKpis" laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php | head -30
grep -A 15 "public function getCirugiasPorConvenio" laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php | head -20
grep -A 10 "getTopDoctoresSolicitudRealizadas\|getTopDoctoresSolicitudesRealizadas\|getCirugiasPorCategoria" laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php | head -20
```

Adjust the `buildReportPayload()` method's array key access based on what you find.

- [ ] **Step 3: Verify PHP syntax**

```bash
php -l laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php
```

Expected: `No syntax errors detected`.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Cirugias/Services/CirugiasDashboardService.php
git commit -m "feat(reportes-v2): add CirugiasDashboardService::buildReportPayload()"
```

---

## Task 7: Add CirugiasUiController::dashboardReport() and route

**Files:**
- Modify: `laravel-app/app/Modules/Cirugias/Http/Controllers/CirugiasUiController.php`
- Modify: `laravel-app/routes/v2/cirugias.php`

- [ ] **Step 1: Add the method to CirugiasUiController**

Add `dashboardReport()` immediately after the existing `dashboard()` method (around line 155). The method reuses the same helpers as `dashboard()`:

```php
public function dashboardReport(Request $request): JsonResponse|RedirectResponse|View|Response
{
    $unauthorized = $this->requireLegacyAuth($request);
    if ($unauthorized !== null) {
        return $unauthorized;
    }

    if (!$this->hasLegacyPermission($request, self::DASHBOARD_ALLOWED_PERMISSIONS)) {
        return response('Acceso denegado', 403);
    }

    $dateRange  = $this->resolveDateRange($request);
    $sedeFilter = $this->resolveSedeFilter($request);

    $start = $dateRange['start']->format('Y-m-d');
    $end   = $dateRange['end']->format('Y-m-d');

    $report = $this->dashboardService->buildReportPayload($start, $end, $sedeFilter);

    $sedeOptions = $this->dashboardService->getSedeOptions($start, $end);

    return view('cirugias.v2-dashboard-report', [
        'report'      => $report,
        'sedeOptions' => $sedeOptions,
        'startDate'   => $start,
        'endDate'     => $end,
        'sedeFilter'  => $sedeFilter,
    ]);
}
```

> **Note:** Check that `hasLegacyPermission()` exists as a private method (grep for it). If it doesn't exist — and the existing `dashboard()` uses a different pattern — copy that exact pattern instead. The `requireLegacyAuth()` is confirmed at line 131.

- [ ] **Step 2: Add route to routes/v2/cirugias.php**

Add before the closing `});` of the middleware group:

```php
Route::get('/cirugias/dashboard/report', [CirugiasUiController::class, 'dashboardReport']);
```

The route must appear BEFORE the export routes to avoid any prefix conflict — place it right after line 22 (`Route::get('/cirugias/dashboard', ...)`).

- [ ] **Step 3: Verify PHP syntax**

```bash
php -l laravel-app/app/Modules/Cirugias/Http/Controllers/CirugiasUiController.php
php -l laravel-app/routes/v2/cirugias.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 4: Commit**

```bash
git add laravel-app/app/Modules/Cirugias/Http/Controllers/CirugiasUiController.php
git add laravel-app/routes/v2/cirugias.php
git commit -m "feat(reportes-v2): add dashboardReport() route for cirugias"
```

---

## Task 8: Create Cirugías Blade view (fullscreen)

**Files:**
- Create: `resources/views/cirugias/v2-dashboard-report.blade.php`

- [ ] **Step 1: Create the Blade view**

Create `laravel-app/resources/views/cirugias/v2-dashboard-report.blade.php`:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte Ejecutivo · Cirugías · {{ $report['period']['fromLabel'] ?? '' }} → {{ $report['period']['toLabel'] ?? '' }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.materialdesignicons.com/7.2.96/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="{{ asset('css/v2/reportes-v2.css') }}">
  @vite(['resources/js/v2/reportes-v2/cirugias/app.tsx'])
</head>
<body>
  <script>
    window.MF_CIR_REPORT = @json($report);
    window.MF_CIR_SEDE_OPTIONS = @json($sedeOptions);
    window.MF_CIR_FILTERS = {
      startDate: @json($startDate),
      endDate: @json($endDate),
      sede: @json($sedeFilter),
    };
  </script>

  <div style="position:fixed;top:12px;left:16px;z-index:9999">
    <a href="/v2/cirugias/dashboard"
       style="display:inline-flex;align-items:center;gap:6px;font:500 13px 'IBM Plex Sans',sans-serif;color:#5e6278;text-decoration:none;background:#fff;border:1px solid #e4e6ef;border-radius:8px;padding:6px 14px;box-shadow:0 1px 3px rgba(16,24,40,.08)">
      <i class="mdi mdi-arrow-left" style="font-size:16px"></i>
      Volver al panel
    </a>
  </div>

  <div id="app"></div>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add laravel-app/resources/views/cirugias/v2-dashboard-report.blade.php
git commit -m "feat(reportes-v2): add cirugias fullscreen report blade view"
```

---

## Task 9: Create Cirugías frontend (types, toolbar, sections, app)

**Files:**
- Create: `resources/js/v2/reportes-v2/cirugias/types.ts`
- Create: `resources/js/v2/reportes-v2/cirugias/toolbar.tsx`
- Create: `resources/js/v2/reportes-v2/cirugias/sections.tsx`
- Create: `resources/js/v2/reportes-v2/cirugias/app.tsx`

- [ ] **Step 1: Create cirugias/types.ts**

Create `laravel-app/resources/js/v2/reportes-v2/cirugias/types.ts`:

```ts
import type { ExecMap, SynthCellData, PeriodMeta, SedeMeta } from '../shared/types';

export interface NameTotal {
  name: string;
  total: number;
}

export interface NameRealizadas {
  name: string;
  realizadas: number;
}

export interface TrendPoint {
  label: string;
  realizadas: number;
  facturadas: number;
}

export interface DonutItem {
  label: string;
  total: number;
  color: string;
}

export interface LabelTotal {
  label: string;
  total: number;
}

export interface CirugiasMetrics {
  solicitudes: number;
  programadas: number;
  realizadas: number;
  informadas: number;
  facturadas: number;
  pendienteFacturar: number;
  pendientePagoN: number;
  cumplimiento: number;
  duracionProm: number;
  tatProm: number;
  tatMed: number;
  tatP90: number;
  tatMuestra: number;
  reingreso: number;
}

export interface CirugiasReport {
  unit: string;
  unitLabel: string;
  unitIcon: string;
  generatedAt: string;
  period: PeriodMeta;
  sede: SedeMeta;
  synth: SynthCellData[];
  exec: ExecMap;
  metrics: CirugiasMetrics;
  produccionMensual: TrendPoint[];
  trazabilidad: DonutItem[];
  topProcedimientos: LabelTotal[];
  topProcIngreso: LabelTotal[];
  topCirujanos: NameRealizadas[];
  topSolicitantes: NameTotal[];
  porConvenio: LabelTotal[];
  mixCategoria: DonutItem[];
}

export interface SedeOption {
  value: string;
  label: string;
}

declare global {
  interface Window {
    MF_CIR_REPORT: CirugiasReport;
    MF_CIR_SEDE_OPTIONS: SedeOption[];
    MF_CIR_FILTERS: { startDate: string; endDate: string; sede: string };
  }
}
```

- [ ] **Step 2: Create cirugias/toolbar.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/cirugias/toolbar.tsx`:

```tsx
import React from 'react';
import type { SedeOption } from './types';

const PERIODS: Record<string, { label: string; days: number }> = {
  mes:  { label: 'Mes',  days: 30  },
  trim: { label: 'Trim', days: 90  },
  sem:  { label: 'Sem',  days: 180 },
  año:  { label: 'Año',  days: 365 },
};

function datesForPreset(preset: string): { start: string; end: string } {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - (PERIODS[preset]?.days ?? 30));
  return {
    end: end.toISOString().slice(0, 10),
    start: start.toISOString().slice(0, 10),
  };
}

function activePeriodKey(startDate: string, endDate: string): string {
  const days = Math.round((new Date(endDate).getTime() - new Date(startDate).getTime()) / 86400000);
  if (days <= 32)  return 'mes';
  if (days <= 92)  return 'trim';
  if (days <= 185) return 'sem';
  return 'año';
}

interface ToolbarProps {
  startDate: string;
  endDate: string;
  sede: string;
  sedeOptions: SedeOption[];
}

export function Toolbar({ startDate, endDate, sede, sedeOptions }: ToolbarProps) {
  const activePeriod = activePeriodKey(startDate, endDate);

  const navigate = (params: Record<string, string>) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    window.location.href = url.toString();
  };

  const selectPeriod = (key: string) => {
    const { start, end } = datesForPreset(key);
    navigate({ start_date: start, end_date: end });
  };

  const selectSede = (value: string) => {
    navigate({ sede: value });
  };

  const onExport = () => window.print();

  return (
    <div className="rep-toolbar">
      <div className="rep-toolbar-inner">
        <div className="rep-tb-brand">
          <span className="rep-tb-tag">
            Reporte ejecutivo<small>Cirugías</small>
          </span>
        </div>
        <div className="rep-filters">
          <span className="rep-flabel">Período</span>
          <div className="rep-seg">
            {Object.entries(PERIODS).map(([key, p]) => (
              <button
                key={key}
                className={activePeriod === key ? 'is-active' : ''}
                onClick={() => selectPeriod(key)}
              >
                {p.label}
              </button>
            ))}
          </div>
          <span className="rep-flabel">Sede</span>
          <div className="rep-seg rep-seg--solid">
            {sedeOptions.map(o => (
              <button
                key={o.value}
                className={sede === o.value ? 'is-active' : ''}
                onClick={() => selectSede(o.value)}
              >
                {o.label}
              </button>
            ))}
          </div>
        </div>
        <button className="rep-btn rep-btn--primary" onClick={onExport}>
          <i className="mdi mdi-file-pdf-box"></i>Exportar PDF
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Create cirugias/sections.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/cirugias/sections.tsx`:

```tsx
import React from 'react';
import { TrendArea, DonutChart, ColumnChart } from '../shared/charts';
import { Section, Kpi, BarsList, DonutLegend, Read, Recs, RecsCard, fmt } from '../shared/lib';
import type { CirugiasReport, NameRealizadas } from './types';

function CirSurgeonTable({ rows, total }: { rows: NameRealizadas[]; total: number }) {
  const mx = Math.max(1, ...rows.map(r => r.realizadas));
  return (
    <table className="rep-table">
      <thead>
        <tr><th>#</th><th>Cirujano</th><th className="num">Realizadas</th><th>Participación</th></tr>
      </thead>
      <tbody>
        {rows.map((r, i) => {
          const initials = r.name.replace(/^Dr[a]?\.\s*/, '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
          return (
            <tr key={i}>
              <td style={{ color: 'var(--fg-mute)', fontWeight: 700 }}>{i + 1}</td>
              <td className="name">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span className="rep-av">{initials}</span>{r.name}
                </div>
              </td>
              <td className="num">{fmt(r.realizadas)}</td>
              <td>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span className="rep-mini-bar">
                    <i style={{ width: (r.realizadas / mx * 100) + '%', background: 'var(--primary)' }}></i>
                  </span>
                  <span style={{ color: 'var(--fg-mute)', fontVariantNumeric: 'tabular-nums' }}>
                    {total > 0 ? Math.round(r.realizadas / total * 100) : 0}%
                  </span>
                </div>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

export function CirugiasContent({ r }: { r: CirugiasReport }) {
  const m = r.metrics;
  return (
    <>
      <Section num="02" kicker="Producción quirúrgica"
        title="Volumen realizado y trazabilidad de facturación"
        lede={`En el período se realizaron <b>${fmt(m.realizadas)} cirugías</b> de ${fmt(m.solicitudes)} solicitadas. La curva separa lo realizado de lo efectivamente facturado: la brecha entre ambas líneas es backlog de billing, no producción perdida.`}>
        <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
          <div className="rep-card rep-span2">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-chart-areaspline"></i>Producción por mes</h3>
              <span className="rep-card-note">Realizadas vs facturadas</span>
            </div>
            <TrendArea data={r.produccionMensual} keys={['realizadas', 'facturadas']} names={['Realizadas', 'Facturadas']} height={246} />
            <div className="rep-chart-legend">
              <span className="rep-leg"><b style={{ background: '#5156be' }}></b>Realizadas</span>
              <span className="rep-leg line"><b style={{ background: '#05825f' }}></b>Facturadas</span>
            </div>
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-chart-donut"></i>Trazabilidad</h3></div>
            <DonutChart data={r.trazabilidad} centerLabel="realizadas" height={188} />
            <DonutLegend items={r.trazabilidad} />
          </div>
        </div>
        <Read>El <b>{m.cumplimiento}% de cumplimiento al corte</b> es el indicador de conversión clave; el frente de valor está en cerrar los <b>{fmt(m.pendienteFacturar)} protocolos operatorios pendientes</b> de facturar.</Read>
      </Section>

      <Section num="03" kicker="Mezcla quirúrgica"
        title="Procedimientos, ingreso y equipo quirúrgico"
        lede="Los procedimientos más frecuentes concentran el volumen; analizar en paralelo con ingreso por caso da la imagen completa de mezcla quirúrgica.">
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-format-list-numbered"></i>Top procedimientos</h3>
              <span className="rep-card-note">Por volumen</span>
            </div>
            <BarsList items={r.topProcedimientos.slice(0, 7)} color="var(--primary)" />
          </div>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-cash"></i>Ingreso por procedimiento</h3>
              <span className="rep-card-note">Facturable estimado</span>
            </div>
            {r.topProcIngreso.length > 0
              ? <BarsList items={r.topProcIngreso.slice(0, 7)} color="#05825f" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Datos de tarifa por procedimiento no disponibles.</p>
            }
          </div>
        </div>
        <div className="rep-grid rep-grid--2">
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-doctor"></i>Top cirujanos</h3>
              <span className="rep-card-note">Realizadas en el período</span>
            </div>
            <CirSurgeonTable rows={r.topCirujanos} total={m.realizadas} />
          </div>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-account-arrow-right-outline"></i>Top doctores solicitantes</h3>
              <span className="rep-card-note">Origen de la demanda</span>
            </div>
            {r.topSolicitantes.length > 0
              ? <BarsList items={r.topSolicitantes} labelKey="name" color="var(--info)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Datos de solicitudes por doctor no disponibles.</p>
            }
          </div>
        </div>
      </Section>

      <Section num="04" kicker="Pagadores y calidad"
        title="Mezcla de financiadores e indicadores de calidad"
        lede="La mezcla de convenios impacta el ciclo de cobro. Los indicadores de calidad —TAT, duración, reingreso— cierran la lectura clínica del período.">
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-timer-sand" label="TAT protocolo (prom.)" value={Math.round(m.tatProm)} unit="h" sub={`Mediana <b>${Math.round(m.tatMed)}h</b> · muestra ${fmt(m.tatMuestra)}`} accent="var(--warning)" />
          <Kpi icon="mdi-speedometer" label="TAT protocolo P90" value={Math.round(m.tatP90)} unit="h" sub="90% se cierra antes de este tiempo" accent="var(--warning)" />
          <Kpi icon="mdi-clock-fast" label="Duración promedio" value={m.duracionProm} unit=" min" sub="Tiempo de quirófano por caso" accent="var(--primary)" />
          <Kpi icon="mdi-restore-alert" label="Reingreso mismo dx" value={m.realizadas > 0 ? Math.round(m.reingreso / m.realizadas * 100) : 0} unit="%" sub={`${fmt(m.reingreso)} casos en seguimiento`} accent="var(--cat-cirugia)" />
        </div>
        <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
          <div className="rep-card rep-span2">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-bank-outline"></i>Cirugías por empresa de seguro</h3>
              <span className="rep-card-note">Volumen realizado</span>
            </div>
            <BarsList items={r.porConvenio} color="var(--primary)" />
          </div>
          {r.mixCategoria.length > 0 && (
            <div className="rep-card">
              <div className="rep-card-head"><h3><i className="mdi mdi-chart-pie"></i>Mezcla por categoría</h3></div>
              <DonutChart data={r.mixCategoria} valueKey="count" centerLabel="realizadas" height={188} />
              <DonutLegend items={r.mixCategoria} valueKey="count" />
            </div>
          )}
        </div>
        <RecsCard>
          <Recs items={[
            `Cerrar los <b>${fmt(m.pendienteFacturar)} protocolos operatorios</b> pendientes para destrabar facturación y reducir el TAT P90 de ${Math.round(m.tatP90)}h.`,
            `Emitir el billing del backlog realizado para recuperar la oportunidad estimada del mapa ejecutivo.`,
            `Revisar con coordinación las <b>${fmt(Math.max(0, m.solicitudes - m.programadas))} solicitudes sin programar</b> — es la fuga de conversión más cara del flujo.`,
            `Vigilar la concentración del sector público en cartera para anticipar el pendiente de pago (${fmt(m.pendientePagoN)} registros).`,
          ]} />
        </RecsCard>
      </Section>
    </>
  );
}
```

- [ ] **Step 4: Create cirugias/app.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/cirugias/app.tsx`:

```tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { Cover, Section, ExecutiveMap, Footnote } from '../shared/lib';
import { Toolbar } from './toolbar';
import { CirugiasContent } from './sections';
import type { CirugiasReport } from './types';

const TITLE = 'Cómo rindió la unidad de Cirugías';
const LEDE  = 'Lectura ejecutiva del quirófano en el período: de la solicitud al cobro. Empieza por el mapa financiero —dónde se gana, se bloquea o se pierde— y baja al detalle de producción, procedimientos, equipo y calidad.';

function App() {
  const r: CirugiasReport = window.MF_CIR_REPORT;
  const sedeOptions = window.MF_CIR_SEDE_OPTIONS ?? [{ value: '', label: 'Todas las sedes' }];
  const filters     = window.MF_CIR_FILTERS ?? { startDate: '', endDate: '', sede: '' };

  React.useEffect(() => {
    document.title = `Reporte Cirugías · ${r.period?.fromLabel ?? ''} → ${r.period?.toLabel ?? ''}`;
  }, []);

  return (
    <div className="rep-app" data-unit="cirugias">
      <Toolbar
        startDate={filters.startDate}
        endDate={filters.endDate}
        sede={filters.sede}
        sedeOptions={sedeOptions}
      />
      <main className="rep-doc">
        <Cover
          unit={r.unit}
          unitLabel={r.unitLabel}
          unitIcon={r.unitIcon}
          title={TITLE}
          lede={LEDE}
          period={r.period}
          sede={r.sede}
          generatedAt={r.generatedAt}
          synth={r.synth}
        />
        <Section num="01" kicker="Mapa ejecutivo financiero"
          title="El flujo conectado, de la solicitud al cobro"
          lede="Conecta cada KPI financiero con la etapa del flujo que lo origina, para distinguir facturado real, oportunidad estimada, pendiente de pago y pérdida.">
          <ExecutiveMap exec={r.exec} unit={r.unit} />
        </Section>
        <CirugiasContent r={r} />
        <Footnote />
      </main>
    </div>
  );
}

const container = document.getElementById('app');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
```

- [ ] **Step 5: Verify TypeScript compiles**

```bash
cd laravel-app && npx tsc --noEmit 2>&1 | grep "reportes-v2/cirugias" | head -20
```

Expected: no output.

- [ ] **Step 6: Build Vite (cirugias entry only)**

```bash
cd laravel-app && npx vite build --mode development 2>&1 | tail -20
```

Expected: build succeeds (some warnings about chunk sizes are OK).

- [ ] **Step 7: Commit**

```bash
git add laravel-app/resources/js/v2/reportes-v2/cirugias/
git commit -m "feat(reportes-v2): add cirugias React app (types, toolbar, sections, entry)"
```

---

## Task 10: Add ImagenesUiController::dashboardReport() and route

**Files:**
- Modify: `laravel-app/app/Modules/Examenes/Http/Controllers/ImagenesUiController.php`
- Modify: `laravel-app/routes/web.php`

- [ ] **Step 1: Read what the existing imagenes dashboard() method returns**

```bash
grep -n "public function dashboard\|buildDashboard\|ImagenesUiService\|return view" laravel-app/app/Modules/Examenes/Http/Controllers/ImagenesUiController.php | head -30
```

Then read the ImagenesUiService to understand payload shape:
```bash
grep -n "public function getDashboard\|buildReport\|kpis\|por_modalidad\|top_examenes\|tendencia\|sla\|ingresos" laravel-app/app/Modules/Examenes/Services/ImagenesUiService.php | head -30
```

- [ ] **Step 2: Add dashboardReport() to ImagenesUiController**

The exact implementation depends on what you find in Step 1. The method must:
- Call `requireLegacyAuth()` (or equivalent) and return 403 if unauthorized
- Read `start_date`, `end_date`, `sede` from query params (same pattern as existing dashboard)
- Call `ImagenesUiService` to get the dashboard payload
- Reshape the payload into `MF_IMG_REPORT` format (see structure below)
- Return `view('examenes.v2-imagenes-dashboard-report', [...])`

The `MF_IMG_REPORT` shape expected by the frontend:
```php
[
  'unit'      => 'imagenes',
  'unitLabel' => 'Imágenes',
  'unitIcon'  => 'mdi-radiology-box-outline',
  'generatedAt' => now()->format('d/m/Y H:i'),
  'period'    => ['label' => '...', 'fromLabel' => '...', 'toLabel' => '...'],
  'sede'      => ['label' => '...'],
  'synth'     => [
    ['label' => 'Exámenes realizados', 'value' => N, 'delta' => 0],
    ['label' => 'Facturado real',      'value' => '$...', 'delta' => 0],
    ['label' => 'SLA ≤48h',           'value' => N, 'unit' => '%', 'delta' => 0],
    ['label' => 'Ticket promedio',     'value' => '$...', 'delta' => 0],
  ],
  'exec'      => [ /* same ExecMap shape as cirugias */ ],
  'metrics'   => [
    'solicitudes' => N, 'realizadas' => N, 'facturadoReal' => N,
    'cumplimiento' => N, 'sla48' => N, 'tatProm' => N, 'tatP90' => N,
    'ticketProm' => N, 'pendientesSinTarifa' => N,
  ],
  'rendimientoEconomico' => [ /* [{label, value}] for ColumnChart */ ],
  'reconciliacion'       => [ /* [{cat, fact, pend, estimado}] */ ],
  'backlogCategoria'     => [ /* [{label, count}] */ ],
  'trazabilidad'         => [ /* [{label, total, color}] */ ],
  'agendaVsCierre'       => [ /* [{label, agendadas, realizadas}] */ ],
  'traficoPorDia'        => [ /* [{label, value}] */ ],
  'serieDiaria'          => [ /* [{label, value}] */ ],
  'topExamenesRealizados'  => [ /* [{label, total}] */ ],
  'topExamenesSolicitados' => [ /* [{label, total}] */ ],
  'topMedicos'           => [ /* [{name, total}] */ ],
  'porConvenio'          => [ /* [{label, total}] */ ],
]
```

Populate each key from whatever `ImagenesUiService` provides. For any key where data is not yet available from the service, return `[]` (empty array) — do NOT invent fake data.

- [ ] **Step 3: Add route to routes/web.php**

Find the existing imagenes dashboard route (around line 73) and add the report route immediately after:

```php
Route::get('/v2/imagenes/dashboard/report', [ImagenesUiController::class, 'dashboardReport']);
```

- [ ] **Step 4: Verify PHP syntax**

```bash
php -l laravel-app/app/Modules/Examenes/Http/Controllers/ImagenesUiController.php
php -l laravel-app/routes/web.php
```

Expected: `No syntax errors detected` for both.

- [ ] **Step 5: Commit**

```bash
git add laravel-app/app/Modules/Examenes/Http/Controllers/ImagenesUiController.php
git add laravel-app/routes/web.php
git commit -m "feat(reportes-v2): add dashboardReport() route for imagenes"
```

---

## Task 11: Create Imágenes Blade view (fullscreen)

**Files:**
- Create: `resources/views/examenes/v2-imagenes-dashboard-report.blade.php`

- [ ] **Step 1: Create the Blade view**

Create `laravel-app/resources/views/examenes/v2-imagenes-dashboard-report.blade.php`:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Reporte Ejecutivo · Imágenes · {{ $report['period']['fromLabel'] ?? '' }} → {{ $report['period']['toLabel'] ?? '' }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=Rubik:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.materialdesignicons.com/7.2.96/css/materialdesignicons.min.css">
  <link rel="stylesheet" href="{{ asset('css/v2/reportes-v2.css') }}">
  @vite(['resources/js/v2/reportes-v2/imagenes/app.tsx'])
</head>
<body>
  <script>
    window.MF_IMG_REPORT = @json($report);
    window.MF_IMG_SEDE_OPTIONS = @json($sedeOptions ?? []);
    window.MF_IMG_FILTERS = {
      startDate: @json($startDate),
      endDate: @json($endDate),
      sede: @json($sedeFilter),
    };
  </script>

  <div style="position:fixed;top:12px;left:16px;z-index:9999">
    <a href="/v2/imagenes/dashboard"
       style="display:inline-flex;align-items:center;gap:6px;font:500 13px 'IBM Plex Sans',sans-serif;color:#5e6278;text-decoration:none;background:#fff;border:1px solid #e4e6ef;border-radius:8px;padding:6px 14px;box-shadow:0 1px 3px rgba(16,24,40,.08)">
      <i class="mdi mdi-arrow-left" style="font-size:16px"></i>
      Volver al panel
    </a>
  </div>

  <div id="app"></div>
</body>
</html>
```

- [ ] **Step 2: Commit**

```bash
git add laravel-app/resources/views/examenes/v2-imagenes-dashboard-report.blade.php
git commit -m "feat(reportes-v2): add imagenes fullscreen report blade view"
```

---

## Task 12: Create Imágenes frontend (types, toolbar, sections, app)

**Files:**
- Create: `resources/js/v2/reportes-v2/imagenes/types.ts`
- Create: `resources/js/v2/reportes-v2/imagenes/toolbar.tsx`
- Create: `resources/js/v2/reportes-v2/imagenes/sections.tsx`
- Create: `resources/js/v2/reportes-v2/imagenes/app.tsx`

- [ ] **Step 1: Create imagenes/types.ts**

Create `laravel-app/resources/js/v2/reportes-v2/imagenes/types.ts`:

```ts
import type { ExecMap, SynthCellData, PeriodMeta, SedeMeta } from '../shared/types';

export interface LabelTotal {
  label: string;
  total: number;
}

export interface LabelCount {
  label: string;
  count: number;
}

export interface LabelValue {
  label: string;
  value: number;
}

export interface AgendaPoint {
  label: string;
  agendadas: number;
  realizadas: number;
}

export interface DonutItem {
  label: string;
  total: number;
  color: string;
}

export interface ReconItem {
  cat: string;
  fact: number;
  pend: number;
  estimado: number;
}

export interface MedicoTotal {
  name: string;
  total: number;
}

export interface ImagenesMetrics {
  solicitudes: number;
  realizadas: number;
  facturadoReal: number;
  cumplimiento: number;
  sla48: number;
  tatProm: number;
  tatP90: number;
  ticketProm: number;
  pendientesSinTarifa: number;
}

export interface ImagenesReport {
  unit: string;
  unitLabel: string;
  unitIcon: string;
  generatedAt: string;
  period: PeriodMeta;
  sede: SedeMeta;
  synth: SynthCellData[];
  exec: ExecMap;
  metrics: ImagenesMetrics;
  rendimientoEconomico: LabelValue[];
  reconciliacion: ReconItem[];
  backlogCategoria: LabelCount[];
  trazabilidad: DonutItem[];
  agendaVsCierre: AgendaPoint[];
  traficoPorDia: LabelValue[];
  serieDiaria: LabelValue[];
  topExamenesRealizados: LabelTotal[];
  topExamenesSolicitados: LabelTotal[];
  topMedicos: MedicoTotal[];
  porConvenio: LabelTotal[];
}

export interface SedeOption {
  value: string;
  label: string;
}

declare global {
  interface Window {
    MF_IMG_REPORT: ImagenesReport;
    MF_IMG_SEDE_OPTIONS: SedeOption[];
    MF_IMG_FILTERS: { startDate: string; endDate: string; sede: string };
  }
}
```

- [ ] **Step 2: Create imagenes/toolbar.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/imagenes/toolbar.tsx`:

```tsx
import React from 'react';
import type { SedeOption } from './types';

const PERIODS: Record<string, { label: string; days: number }> = {
  mes:  { label: 'Mes',  days: 30  },
  trim: { label: 'Trim', days: 90  },
  sem:  { label: 'Sem',  days: 180 },
  año:  { label: 'Año',  days: 365 },
};

function datesForPreset(preset: string): { start: string; end: string } {
  const end = new Date();
  const start = new Date();
  start.setDate(end.getDate() - (PERIODS[preset]?.days ?? 30));
  return {
    end: end.toISOString().slice(0, 10),
    start: start.toISOString().slice(0, 10),
  };
}

function activePeriodKey(startDate: string, endDate: string): string {
  const days = Math.round((new Date(endDate).getTime() - new Date(startDate).getTime()) / 86400000);
  if (days <= 32)  return 'mes';
  if (days <= 92)  return 'trim';
  if (days <= 185) return 'sem';
  return 'año';
}

interface ToolbarProps {
  startDate: string;
  endDate: string;
  sede: string;
  sedeOptions: SedeOption[];
}

export function Toolbar({ startDate, endDate, sede, sedeOptions }: ToolbarProps) {
  const activePeriod = activePeriodKey(startDate, endDate);

  const navigate = (params: Record<string, string>) => {
    const url = new URL(window.location.href);
    Object.entries(params).forEach(([k, v]) => url.searchParams.set(k, v));
    window.location.href = url.toString();
  };

  return (
    <div className="rep-toolbar">
      <div className="rep-toolbar-inner">
        <div className="rep-tb-brand">
          <span className="rep-tb-tag">Reporte ejecutivo<small>Imágenes</small></span>
        </div>
        <div className="rep-filters">
          <span className="rep-flabel">Período</span>
          <div className="rep-seg">
            {Object.entries(PERIODS).map(([key, p]) => (
              <button key={key}
                className={activePeriod === key ? 'is-active' : ''}
                onClick={() => { const { start, end } = datesForPreset(key); navigate({ start_date: start, end_date: end }); }}>
                {p.label}
              </button>
            ))}
          </div>
          {sedeOptions.length > 0 && (
            <>
              <span className="rep-flabel">Sede</span>
              <div className="rep-seg rep-seg--solid">
                {sedeOptions.map(o => (
                  <button key={o.value}
                    className={sede === o.value ? 'is-active' : ''}
                    onClick={() => navigate({ sede: o.value })}>
                    {o.label}
                  </button>
                ))}
              </div>
            </>
          )}
        </div>
        <button className="rep-btn rep-btn--primary" onClick={() => window.print()}>
          <i className="mdi mdi-file-pdf-box"></i>Exportar PDF
        </button>
      </div>
    </div>
  );
}
```

- [ ] **Step 3: Create imagenes/sections.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/imagenes/sections.tsx`:

```tsx
import React from 'react';
import { TrendArea, AreaSeries, ColumnChart, DonutChart } from '../shared/charts';
import { Section, Kpi, BarsList, DonutLegend, Read, Recs, RecsCard, fmt } from '../shared/lib';
import type { ImagenesReport, MedicoTotal, ReconItem } from './types';

function money(v: number): string {
  return '$' + Number(v).toLocaleString('es-EC', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function ImgReconTable({ rows }: { rows: ReconItem[] }) {
  return (
    <table className="rep-table">
      <thead>
        <tr><th>Categoría</th><th className="num">Facturadas</th><th className="num">Pendientes</th><th className="num">Estimado</th></tr>
      </thead>
      <tbody>
        {rows.map((r, i) => (
          <tr key={i}>
            <td className="name">{r.cat}</td>
            <td className="num">{fmt(r.fact)}</td>
            <td className="num">{fmt(r.pend)}</td>
            <td className="num">{r.estimado > 0 ? money(r.estimado) : '—'}</td>
          </tr>
        ))}
      </tbody>
    </table>
  );
}

function ImgDoctorTable({ rows, total }: { rows: MedicoTotal[]; total: number }) {
  const mx = Math.max(1, ...rows.map(r => r.total));
  return (
    <table className="rep-table">
      <thead>
        <tr><th>#</th><th>Médico solicitante</th><th className="num">Solicitudes</th><th>Participación</th></tr>
      </thead>
      <tbody>
        {rows.map((r, i) => {
          const initials = r.name.replace(/^Dr[a]?\.\s*/, '').split(' ').map(w => w[0]).join('').slice(0, 2).toUpperCase();
          return (
            <tr key={i}>
              <td style={{ color: 'var(--fg-mute)', fontWeight: 700 }}>{i + 1}</td>
              <td className="name">
                <div style={{ display: 'flex', alignItems: 'center', gap: 10 }}>
                  <span className="rep-av" style={{ background: '#e6f9fc', color: '#0b5e6e' }}>{initials}</span>{r.name}
                </div>
              </td>
              <td className="num">{fmt(r.total)}</td>
              <td>
                <div style={{ display: 'flex', alignItems: 'center', gap: 8 }}>
                  <span className="rep-mini-bar">
                    <i style={{ width: (r.total / mx * 100) + '%', background: '#0e9bb3' }}></i>
                  </span>
                  <span style={{ color: 'var(--fg-mute)', fontVariantNumeric: 'tabular-nums' }}>
                    {total > 0 ? Math.round(r.total / total * 100) : 0}%
                  </span>
                </div>
              </td>
            </tr>
          );
        })}
      </tbody>
    </table>
  );
}

export function ImagenesContent({ r }: { r: ImagenesReport }) {
  const m = r.metrics;
  return (
    <>
      <Section num="02" kicker="Rendimiento económico"
        title="Facturado, oportunidad y reconciliación por categoría"
        lede={`El facturado real del período es <b>${money(m.facturadoReal)}</b>. La reconciliación por categoría muestra dónde se concentra el pendiente y qué bloquea su cobro.`}>
        <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-cash-multiple"></i>Rendimiento económico</h3></div>
            <ColumnChart data={r.rendimientoEconomico} money height={236} />
          </div>
          <div className="rep-card rep-span2">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-scale-balance"></i>Reconciliación financiera por categoría</h3>
              <span className="rep-card-note">Facturado vs pendiente</span>
            </div>
            {r.reconciliacion.length > 0
              ? <ImgReconTable rows={r.reconciliacion} />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Datos de reconciliación no disponibles.</p>
            }
            <p className="rep-kpi-sub" style={{ marginTop: 12 }}>
              Pendientes sin tarifa resoluble: <b>{fmt(m.pendientesSinTarifa)}</b> — requieren completar tarifa por código/categoría antes de facturar.
            </p>
          </div>
        </div>
        <div className="rep-grid rep-grid--2">
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-layers-triple-outline"></i>Backlog por categoría</h3>
              <span className="rep-card-note">Realizados sin facturar</span>
            </div>
            <BarsList items={r.backlogCategoria} valueKey="count" color="#0e9bb3" />
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-chart-donut"></i>Trazabilidad facturación</h3></div>
            <div className="rep-grid rep-grid--2" style={{ alignItems: 'center', gap: 8 }}>
              <DonutChart data={r.trazabilidad} centerLabel="realizados" height={176} />
              <DonutLegend items={r.trazabilidad} />
            </div>
          </div>
        </div>
        <div style={{ marginTop: 16 }}>
          <Read>La oportunidad estimada se desbloquea en dos frentes: resolver las <b>{fmt(m.pendientesSinTarifa)} tarifas faltantes</b> y agendar las solicitudes que hoy no llegan a realizarse.</Read>
        </div>
      </Section>

      <Section num="03" kicker="Operación, agenda y SLA"
        title="Capacidad de agenda, cierre y oportunidad de informe"
        lede={`El cumplimiento al corte es del <b>${m.cumplimiento}%</b> y el SLA de informe ≤48h alcanza <b>${m.sla48}%</b>.`}>
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-timer-check-outline" label="SLA informe ≤48h" value={m.sla48} unit="%" sub="Estudios informados dentro de meta" accent="var(--success)" />
          <Kpi icon="mdi-timer-sand" label="TAT informe (prom.)" value={Math.round(m.tatProm)} unit="h" sub="Desde realización a informe" accent="var(--warning)" />
          <Kpi icon="mdi-speedometer" label="TAT informe P90" value={Math.round(m.tatP90)} unit="h" sub="90% se informa antes" accent="var(--warning)" />
          <Kpi icon="mdi-ticket-confirmation-outline" label="Ticket promedio" value={money(m.ticketProm)} sub="Facturado por estudio" accent="#0e9bb3" />
        </div>
        {r.agendaVsCierre.length > 0 && (
          <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
            <div className="rep-card rep-span2">
              <div className="rep-card-head">
                <h3><i className="mdi mdi-chart-areaspline"></i>Agenda vs cierre</h3>
                <span className="rep-card-note">Agendadas vs realizadas</span>
              </div>
              <TrendArea data={r.agendaVsCierre} keys={['agendadas', 'realizadas']} names={['Agendadas', 'Realizadas']} height={234} />
              <div className="rep-chart-legend">
                <span className="rep-leg"><b style={{ background: '#5156be' }}></b>Agendadas</span>
                <span className="rep-leg line"><b style={{ background: '#05825f' }}></b>Realizadas</span>
              </div>
            </div>
            {r.traficoPorDia.length > 0 && (
              <div className="rep-card">
                <div className="rep-card-head"><h3><i className="mdi mdi-calendar-week"></i>Tráfico por día</h3></div>
                <ColumnChart data={r.traficoPorDia} colors={['#0e9bb3']} height={234} />
              </div>
            )}
          </div>
        )}
        {r.serieDiaria.length > 0 && (
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-pulse"></i>Serie diaria de estudios realizados</h3>
              <span className="rep-card-note">Picos de carga operativa</span>
            </div>
            <AreaSeries data={r.serieDiaria} color="#0e9bb3" name="Realizados" height={210} />
          </div>
        )}
      </Section>

      <Section num="04" kicker="Demanda y mezcla de estudios"
        title="Qué se pide, qué se realiza y quién lo origina"
        lede="El OCT y el campo visual concentran la demanda. Cruzar lo solicitado con lo realizado ayuda a dimensionar agenda y equipos por sede.">
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-check-decagram-outline"></i>Top exámenes realizados</h3></div>
            <BarsList items={r.topExamenesRealizados.slice(0, 7)} color="#0e9bb3" />
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-clipboard-text-outline"></i>Top exámenes solicitados</h3></div>
            <BarsList items={r.topExamenesSolicitados.slice(0, 7)} color="var(--info)" />
          </div>
        </div>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-account-arrow-right-outline"></i>Top médicos solicitantes</h3></div>
            <ImgDoctorTable rows={r.topMedicos} total={m.solicitudes} />
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-bank-outline"></i>Estudios por empresa de seguro</h3></div>
            <BarsList items={r.porConvenio} color="var(--primary)" />
          </div>
        </div>
        <RecsCard>
          <Recs items={[
            `Resolver las <b>${fmt(m.pendientesSinTarifa)} tarifas faltantes</b> es el desbloqueo de facturación más rápido y de menor esfuerzo.`,
            `Recuperar agenda: las solicitudes sin agendar son la mayor fuga de conversión del flujo.`,
            `Sostener el SLA ≤48h en <b>${m.sla48}%</b> reasignando lectura en los picos que muestra la serie diaria.`,
            `Equilibrar la carga entre sedes según el tráfico por día para reducir ausentismo y reprogramaciones.`,
          ]} />
        </RecsCard>
      </Section>
    </>
  );
}
```

- [ ] **Step 4: Create imagenes/app.tsx**

Create `laravel-app/resources/js/v2/reportes-v2/imagenes/app.tsx`:

```tsx
import React from 'react';
import { createRoot } from 'react-dom/client';
import { Cover, Section, ExecutiveMap, Footnote } from '../shared/lib';
import { Toolbar } from './toolbar';
import { ImagenesContent } from './sections';
import type { ImagenesReport } from './types';

const TITLE = 'Cómo rindió la unidad de Imágenes';
const LEDE  = 'Lectura ejecutiva de imagenología en el período: de la solicitud al cobro. Empieza por el mapa financiero —dónde se gana, se bloquea o se pierde— y baja al detalle económico, operación, SLA y mezcla de estudios.';

function App() {
  const r: ImagenesReport = window.MF_IMG_REPORT;
  const sedeOptions = window.MF_IMG_SEDE_OPTIONS ?? [];
  const filters     = window.MF_IMG_FILTERS ?? { startDate: '', endDate: '', sede: '' };

  React.useEffect(() => {
    document.title = `Reporte Imágenes · ${r.period?.fromLabel ?? ''} → ${r.period?.toLabel ?? ''}`;
  }, []);

  return (
    <div className="rep-app" data-unit="imagenes">
      <Toolbar
        startDate={filters.startDate}
        endDate={filters.endDate}
        sede={filters.sede}
        sedeOptions={sedeOptions}
      />
      <main className="rep-doc">
        <Cover
          unit={r.unit}
          unitLabel={r.unitLabel}
          unitIcon={r.unitIcon}
          title={TITLE}
          lede={LEDE}
          period={r.period}
          sede={r.sede}
          generatedAt={r.generatedAt}
          synth={r.synth}
        />
        <Section num="01" kicker="Mapa ejecutivo financiero"
          title="El flujo conectado, de la solicitud al cobro"
          lede="Conecta cada KPI financiero con la etapa del flujo que lo origina, para distinguir facturado real, oportunidad estimada, pendiente de pago y pérdida.">
          <ExecutiveMap exec={r.exec} unit={r.unit} />
        </Section>
        <ImagenesContent r={r} />
        <Footnote />
      </main>
    </div>
  );
}

const container = document.getElementById('app');
if (container) {
  createRoot(container).render(<React.StrictMode><App /></React.StrictMode>);
}
```

- [ ] **Step 5: Verify TypeScript compiles**

```bash
cd laravel-app && npx tsc --noEmit 2>&1 | grep "reportes-v2/imagenes" | head -20
```

Expected: no output.

- [ ] **Step 6: Full Vite build**

```bash
cd laravel-app && npx vite build --mode development 2>&1 | tail -20
```

Expected: build succeeds.

- [ ] **Step 7: Commit**

```bash
git add laravel-app/resources/js/v2/reportes-v2/imagenes/
git commit -m "feat(reportes-v2): add imagenes React app (types, toolbar, sections, entry)"
```

---

## Task 13: Push and create PR

- [ ] **Step 1: Final build in production mode**

```bash
cd laravel-app && npm run build 2>&1 | tail -30
```

Expected: build succeeds. Both `reportes-cirugias` and `reportes-imagenes` bundles appear in manifest.

- [ ] **Step 2: Push to feature branch**

```bash
git push -u origin claude/affectionate-knuth-tt0w7n
```

- [ ] **Step 3: Create PR via GitHub MCP**

Use `mcp__github__create_pull_request` to create a draft PR targeting `main` from `claude/affectionate-knuth-tt0w7n`.

Title: `feat(reportes-v2): add executive report pages for cirugías and imágenes`

Body should cover:
- Two new routes: `/v2/cirugias/dashboard/report` and `/v2/imagenes/dashboard/report`
- Fullscreen standalone Blade views, no sidebar
- React 19 + Recharts compiled with Vite
- New `buildReportPayload()` in `CirugiasDashboardService`
- Shared CSS/charts/lib components under `resources/js/v2/reportes-v2/shared/`
- Existing dashboards, Excel exports, and PDFs untouched
- "Volver al panel" links back to respective main dashboards

---

## Self-Review

### Spec Coverage
- ✅ Two independent reports (no shared data, no unit switcher)
- ✅ Fullscreen standalone pages (no sidebar/topnav)
- ✅ Vite + React 19 + TypeScript (no CDN Babel)
- ✅ Recharts (not UMD Recharts)
- ✅ `window.MF_CIR_REPORT` / `window.MF_IMG_REPORT` injected by Blade
- ✅ Period preset and sede filter → server-side reload (query params)
- ✅ "Volver al panel" link in fixed position
- ✅ Existing views/Excel/PDF exports untouched
- ✅ No mock data in production (empty arrays for unavailable data)
- ✅ New routes do not conflict with existing export routes (report is at `/dashboard/report`, exports at `/dashboard/export/*`)

### Type Consistency
- `ExecMap`, `PeriodMeta`, `SedeMeta`, `SynthCellData` defined in `shared/types.ts` and imported by both `cirugias/types.ts` and `imagenes/types.ts`
- `Cover` in `lib.tsx` uses flat props (not a `r` object) — all callers in `app.tsx` pass individual props
- `BarsList` `valueKey` defaults to `'total'`, used consistently in sections
- `DonutChart` and `DonutLegend` both default to `valueKey='total'`, sections that use `count` pass `valueKey="count"` explicitly

### Placeholder Scan
- Task 6 `topProcIngreso: []` and `mixCategoria: []` are documented as intentionally empty (data not available yet), not placeholders
- Task 10 Step 1 requires reading actual service method signatures before implementation — this is a deliberate "read first" step, not a vague TODO
