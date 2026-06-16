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
                    <span className="pct">{e.links[i]?.pct ?? 0}%</span>
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

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type BarsListItem = any;

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
  const f = format ?? ((v: number) => fmt(v) + suffix);
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

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type DonutLegendItem = any;

export function DonutLegend({ items, valueKey = 'total' }: { items: DonutLegendItem[]; valueKey?: string }) {
  const total = items.reduce((a, d) => a + (Number(d[valueKey]) || 0), 0);
  return (
    <div className="rep-chart-legend" style={{ flexDirection: 'column', gap: 9, marginTop: 14 }}>
      {items.map((d, i) => {
        const val = Number(d[valueKey]) || 0;
        return (
          <div key={i} className="rep-leg" style={{ width: '100%' }}>
            <b style={{ background: d.color as string }}></b>
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
