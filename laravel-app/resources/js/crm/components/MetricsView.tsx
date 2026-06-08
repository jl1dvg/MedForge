import React, { useMemo } from 'react';
import type { OpportunityView } from '../types';
import { fmtMoney, initials } from '../helpers';

interface Props { ops: OpportunityView[]; }

// Stage progression index for funnel (maps production stages to funnel levels)
const FUNNEL_ORDER = ['nuevo', 'contactado', 'en_evaluacion', 'propuesta', 'comprometido', 'ganado'];

function stageIdx(slug: string): number {
  return FUNNEL_ORDER.indexOf(slug);
}

function reached(ops: OpportunityView[], slug: string): number {
  const threshold = stageIdx(slug);
  return ops.filter(o => {
    if (o.stage === 'ganado') return true;
    if (o.stage === 'perdido') {
      // count as reached up to en_evaluacion if they had a propuesta, otherwise contactado
      return threshold <= 1;
    }
    return stageIdx(o.stage) >= threshold;
  }).length;
}

interface FunnelStep { key: string; label: string; count: number; color: string; }
interface SourceRow { id: string; label: string; icon: string; total: number; conv: number; value: number; }
interface SedeRow { label: string; total: number; conv: number; }
interface AgentRow { id: string; full: string; role: string; open: number; won: number; conv: number; wonValue: number; }

interface Metrics {
  funnel: FunnelStep[];
  lossReasons: { label: string; count: number }[];
  bySource: SourceRow[];
  bySede: SedeRow[];
  byAgent: AgentRow[];
  wonValue: number;
  wonCount: number;
  lostCount: number;
  openCount: number;
  pipelineValue: number;
  convGlobal: number;
  avgTicket: number;
}

const SOURCE_META: Record<string, { label: string; icon: string }> = {
  whatsapp:  { label: 'WhatsApp',          icon: 'mdi-whatsapp' },
  solicitud: { label: 'Solicitud clínica',  icon: 'mdi-file-document' },
  examen:    { label: 'Examen solicitado',  icon: 'mdi-microscope' },
  manual:    { label: 'Alta manual',        icon: 'mdi-account-plus' },
};

function computeMetrics(ops: OpportunityView[]): Metrics {
  const won  = ops.filter(o => o.stage === 'ganado');
  const lost = ops.filter(o => o.stage === 'perdido');
  const open = ops.filter(o => o.stage !== 'ganado' && o.stage !== 'perdido');

  const wonValue = won.reduce((a, o) => a + (o.cierre?.valor_final || o.valor || 0), 0);
  const closed   = won.length + lost.length;

  const funnel: FunnelStep[] = [
    { key: 'total',       label: 'Oportunidades', count: ops.length,              color: '#5156be' },
    { key: 'contactado',  label: 'Contactadas',   count: reached(ops,'contactado'), color: '#3596f7' },
    { key: 'en_evaluacion', label: 'Cotizadas',   count: reached(ops,'en_evaluacion'), color: '#6f67d8' },
    { key: 'comprometido', label: 'Agendadas',    count: reached(ops,'comprometido'), color: '#1f9d7a' },
    { key: 'ganado',      label: 'Ganadas',       count: won.length,               color: '#05825f' },
  ];

  // loss reasons
  const lossMap: Record<string, number> = {};
  lost.forEach(o => {
    const l = o.cierre?.motivo_label || o.lost_reason || 'Sin motivo';
    lossMap[l] = (lossMap[l] || 0) + 1;
  });
  const lossReasons = Object.entries(lossMap)
    .map(([label, count]) => ({ label, count }))
    .sort((a, b) => b.count - a.count);

  // by source
  const sourceIds = [...new Set(ops.map(o => o.fuente))];
  const bySource: SourceRow[] = sourceIds.map(id => {
    const list = ops.filter(o => o.fuente === id);
    const w = list.filter(o => o.stage === 'ganado').length;
    const l = list.filter(o => o.stage === 'perdido').length;
    const value = list.filter(o => o.stage !== 'perdido').reduce((a, o) => a + (o.valor || 0), 0);
    const meta = SOURCE_META[id] || { label: id, icon: 'mdi-help-circle-outline' };
    return { id, label: meta.label, icon: meta.icon, total: list.length, conv: (w + l) ? Math.round((w / (w + l)) * 100) : 0, value };
  }).filter(s => s.total > 0).sort((a, b) => b.total - a.total);

  // by sede
  const sedeIds = [...new Set(ops.map(o => o.sede).filter(Boolean))];
  const bySede: SedeRow[] = sedeIds.map(label => {
    const list = ops.filter(o => o.sede === label);
    const w = list.filter(o => o.stage === 'ganado').length;
    const l = list.filter(o => o.stage === 'perdido').length;
    return { label: label!, total: list.length, conv: (w + l) ? Math.round((w / (w + l)) * 100) : 0 };
  }).filter(s => s.total > 0).sort((a, b) => b.conv - a.conv);

  // by agent (assigned_to)
  const agentIds = [...new Set(ops.map(o => o.assigned_to).filter(Boolean))] as number[];
  const byAgent: AgentRow[] = agentIds.map(id => {
    const list = ops.filter(o => o.assigned_to === id);
    const w = list.filter(o => o.stage === 'ganado');
    const l = list.filter(o => o.stage === 'perdido').length;
    const op = list.filter(o => o.stage !== 'ganado' && o.stage !== 'perdido').length;
    const full = list[0]?.responsable_name || `Asesor #${id}`;
    return {
      id: String(id), full, role: '', open: op, won: w.length,
      conv: (w.length + l) ? Math.round((w.length / (w.length + l)) * 100) : 0,
      wonValue: w.reduce((s, o) => s + (o.cierre?.valor_final || o.valor || 0), 0),
    };
  }).sort((a, b) => b.wonValue - a.wonValue);

  return {
    funnel, lossReasons, bySource, bySede, byAgent,
    wonValue, wonCount: won.length, lostCount: lost.length, openCount: open.length,
    pipelineValue: open.reduce((a, o) => a + (o.valor || 0), 0),
    convGlobal: closed ? Math.round((won.length / closed) * 100) : 0,
    avgTicket: won.length ? Math.round(wonValue / won.length) : 0,
  };
}

function StatTile({ tone, icon, value, label, sub }: { tone: string; icon: string; value: string; label: string; sub: string }) {
  return (
    <div className={`m-tile tone-${tone}`}>
      <span className="mt-ic"><i className={`mdi ${icon}`}></i></span>
      <div className="mt-body">
        <div className="mt-value">{value}</div>
        <div className="mt-label">{label}</div>
        <div className="mt-sub">{sub}</div>
      </div>
    </div>
  );
}

export function MetricsView({ ops }: Props) {
  const m = useMemo(() => computeMetrics(ops), [ops]);
  return (
    <div className="metrics">
      {/* Stat tiles */}
      <div className="m-tiles">
        <StatTile tone="money"    icon="mdi-cash-check"              value={fmtMoney(m.wonValue)}      label="Valor ganado"       sub={`${m.wonCount} oportunidades cerradas ganadas`} />
        <StatTile tone="pipeline" icon="mdi-chart-timeline-variant"  value={fmtMoney(m.pipelineValue)} label="Valor en embudo"    sub={`${m.openCount} oportunidades abiertas`} />
        <StatTile tone="conv"     icon="mdi-percent-outline"         value={`${m.convGlobal}%`}        label="Conversión global"  sub={`${m.wonCount} ganadas · ${m.lostCount} perdidas`} />
        <StatTile tone="ticket"   icon="mdi-tag-outline"             value={fmtMoney(m.avgTicket)}     label="Ticket promedio"    sub="Por procedimiento ganado" />
      </div>

      <div className="m-grid">
        {/* Funnel */}
        <div className="m-card m-span2">
          <div className="m-card-head">
            <h3><i className="mdi mdi-filter-variant"></i>Embudo de conversión</h3>
            <span className="m-tag">{m.funnel[0].count} oportunidades</span>
          </div>
          <div className="funnel">
            {m.funnel.map((f, i) => {
              const pct = m.funnel[0].count ? Math.round((f.count / m.funnel[0].count) * 100) : 0;
              const stepConv = i === 0 ? null : (m.funnel[i - 1].count ? Math.round((f.count / m.funnel[i - 1].count) * 100) : 0);
              return (
                <div className="fn-row" key={f.key}>
                  <div className="fn-label">
                    <span className="fn-dot" style={{ background: f.color }}></span>
                    {f.label}
                  </div>
                  <div className="fn-track">
                    <div className="fn-bar" style={{ width: `${Math.max(pct, 4)}%`, background: f.color }}>
                      <span className="fn-count">{f.count}</span>
                    </div>
                    <span className="fn-pct">{pct}%</span>
                  </div>
                  {stepConv != null && (
                    <span className={`fn-step ${stepConv >= 60 ? 'good' : stepConv >= 35 ? 'mid' : 'low'}`}>
                      <i className="mdi mdi-arrow-down"></i>{stepConv}%
                    </span>
                  )}
                </div>
              );
            })}
          </div>
        </div>

        {/* Motivos de pérdida */}
        <div className="m-card">
          <div className="m-card-head">
            <h3><i className="mdi mdi-close-octagon-outline"></i>Motivos de pérdida</h3>
            <span className="m-tag">{m.lostCount} perdidas</span>
          </div>
          {m.lossReasons.length === 0 ? (
            <div className="m-empty">Sin pérdidas registradas en el periodo.</div>
          ) : (
            <div className="bar-list">
              {m.lossReasons.map(r => (
                <div className="bar-row" key={r.label}>
                  <div className="br-top">
                    <span className="br-label">{r.label}</span>
                    <span className="br-val">{r.count}</span>
                  </div>
                  <div className="br-track">
                    <i style={{ width: `${(r.count / m.lostCount) * 100}%`, background: 'var(--danger)' }}></i>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Origen de demanda */}
        <div className="m-card m-span2">
          <div className="m-card-head">
            <h3><i className="mdi mdi-bullseye-arrow"></i>Origen de demanda</h3>
            <span className="m-tag">conversión y valor</span>
          </div>
          {m.bySource.length === 0 ? (
            <div className="m-empty">Sin datos de origen.</div>
          ) : (
            <div className="src-table">
              <div className="src-head"><span>Fuente</span><span>Oport.</span><span>Conv.</span><span>Valor</span></div>
              {m.bySource.map(s => (
                <div className="src-row" key={s.id}>
                  <span className="src-name"><i className={`mdi ${s.icon}`}></i>{s.label}</span>
                  <span className="src-n">{s.total}</span>
                  <span className="src-conv">
                    <span className="conv-bar"><i style={{ width: `${s.conv}%` }}></i></span>
                    <b>{s.conv}%</b>
                  </span>
                  <span className="src-val">{fmtMoney(s.value)}</span>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Conversión por sede */}
        <div className="m-card">
          <div className="m-card-head">
            <h3><i className="mdi mdi-map-marker-outline"></i>Conversión por sede</h3>
          </div>
          {m.bySede.length === 0 ? (
            <div className="m-empty">Sin datos de sede disponibles.</div>
          ) : (
            <div className="bar-list">
              {m.bySede.map(s => (
                <div className="bar-row" key={s.label}>
                  <div className="br-top">
                    <span className="br-label">{s.label}</span>
                    <span className="br-val">{s.conv}% · {s.total}</span>
                  </div>
                  <div className="br-track">
                    <i style={{ width: `${s.conv}%`, background: 'var(--success)' }}></i>
                  </div>
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Desempeño por asesor */}
        <div className="m-card m-span3">
          <div className="m-card-head">
            <h3><i className="mdi mdi-account-group-outline"></i>Desempeño por asesor</h3>
            <span className="m-tag">{m.byAgent.length} activos</span>
          </div>
          {m.byAgent.length === 0 ? (
            <div className="m-empty">Sin asesores asignados aún.</div>
          ) : (
            <div className="agent-grid">
              {m.byAgent.map((a, i) => (
                <div className="agent-card" key={a.id}>
                  <div className="agent-top">
                    <span className="agent-av">{initials(a.full)}</span>
                    <div className="agent-id">
                      <div className="an">{a.full}</div>
                      {a.role && <div className="ar">{a.role}</div>}
                    </div>
                    {i === 0 && a.won > 0 && <span className="agent-medal"><i className="mdi mdi-trophy-variant"></i></span>}
                  </div>
                  <div className="agent-stats">
                    <div className="ast"><div className="av">{a.open}</div><div className="al">abiertas</div></div>
                    <div className="ast"><div className="av" style={{ color: 'var(--success)' }}>{a.won}</div><div className="al">ganadas</div></div>
                    <div className="ast"><div className="av">{a.conv}%</div><div className="al">conv.</div></div>
                  </div>
                  <div className="agent-money"><i className="mdi mdi-cash"></i>{fmtMoney(a.wonValue)} ganado</div>
                </div>
              ))}
            </div>
          )}
        </div>
      </div>
    </div>
  );
}
