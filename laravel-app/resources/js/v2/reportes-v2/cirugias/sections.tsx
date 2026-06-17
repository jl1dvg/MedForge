import React from 'react';
import { TrendArea, DonutChart } from '../shared/charts';
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
          const initials = r.name.replace(/^Dr[a]?\.\s*/, '').split(' ').map((w: string) => w[0]).join('').slice(0, 2).toUpperCase();
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
        lede={`En el período se realizaron <b>${fmt(m.realizadas)} cirugías</b> de ${fmt(m.programadas)} programadas. La curva separa lo realizado de lo efectivamente facturado: la brecha entre ambas líneas es backlog de billing, no producción perdida.`}>
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
        <Read>El <b>{m.cumplimiento}% de cumplimiento al corte</b> es el indicador de conversión clave; el frente de valor está en cerrar los <b>{fmt(m.pendienteFacturar)} cirugías pendientes</b> de facturar.</Read>
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
            {r.topProcedimientos.length > 0
              ? <BarsList items={r.topProcedimientos.slice(0, 7)} color="var(--primary)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de procedimientos en el período.</p>
            }
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
            {r.topCirujanos.length > 0
              ? <CirSurgeonTable rows={r.topCirujanos} total={m.realizadas} />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de cirujanos.</p>
            }
          </div>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-account-arrow-right-outline"></i>Top doctores solicitantes</h3>
              <span className="rep-card-note">Origen de la demanda</span>
            </div>
            {r.topSolicitantes.length > 0
              ? <BarsList items={r.topSolicitantes} labelKey="name" color="var(--info)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de doctores solicitantes.</p>
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
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
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
            `Revisar con coordinación las <b>${fmt(Math.max(0, m.programadas - m.realizadas))} cirugías programadas no realizadas</b> — es la fuga de conversión más cara del flujo.`,
            `Vigilar la concentración del sector público en cartera para anticipar el pendiente de pago (${fmt(m.pendientePagoN)} registros).`,
          ]} />
        </RecsCard>
      </Section>
    </>
  );
}
