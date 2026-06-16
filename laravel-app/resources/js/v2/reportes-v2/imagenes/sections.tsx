import React from 'react';
import { TrendArea, DonutChart } from '../shared/charts';
import { Section, Kpi, BarsList, DonutLegend, Read, Recs, RecsCard, fmt } from '../shared/lib';
import type { ImagenesReport } from './types';

export function ImagenesContent({ r }: { r: ImagenesReport }) {
  const m = r.metrics;
  return (
    <>
      <Section num="02" kicker="Producción de imágenes"
        title="Volumen realizado y trazabilidad de facturación"
        lede={`En el período se realizaron <b>${fmt(m.realizados)} estudios</b> de ${fmt(m.solicTotal)} solicitados. La curva separa realizados de informados: la brecha es backlog de radiología, no producción perdida.`}>
        <div className="rep-grid rep-grid--3" style={{ marginBottom: 16 }}>
          <div className="rep-card rep-span2">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-chart-areaspline"></i>Producción por mes</h3>
              <span className="rep-card-note">Realizados vs informados</span>
            </div>
            <TrendArea data={r.produccionMensual} keys={['realizados', 'informados']} names={['Realizados', 'Informados']} height={246} />
            <div className="rep-chart-legend">
              <span className="rep-leg"><b style={{ background: '#5156be' }}></b>Realizados</span>
              <span className="rep-leg line"><b style={{ background: '#05825f' }}></b>Informados</span>
            </div>
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-chart-donut"></i>Trazabilidad</h3></div>
            <DonutChart data={r.trazabilidad} centerLabel="realizados" height={188} />
            <DonutLegend items={r.trazabilidad} />
          </div>
        </div>
        <Read>El <b>{m.cumplPct !== null ? Math.round(m.cumplPct) : '—'}% de cumplimiento al corte</b> es el indicador de conversión clave; el frente de valor está en cerrar los <b>{fmt(m.pendFact)} estudios pendientes</b> de facturar.</Read>
      </Section>

      <Section num="03" kicker="Mezcla de exámenes"
        title="Tipos de estudio, demanda y equipo"
        lede="Los exámenes más frecuentes concentran el volumen; analizar con el ticket promedio da la imagen completa de mezcla clínica.">
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-format-list-numbered"></i>Top exámenes</h3>
              <span className="rep-card-note">Por volumen solicitado</span>
            </div>
            {r.topExamenes.length > 0
              ? <BarsList items={r.topExamenes.slice(0, 7)} color="var(--primary)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de exámenes en el período.</p>
            }
          </div>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-account-arrow-right-outline"></i>Top doctores solicitantes</h3>
              <span className="rep-card-note">Origen de la demanda</span>
            </div>
            {r.topDoctores.length > 0
              ? <BarsList items={r.topDoctores.slice(0, 7)} color="var(--info)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de doctores solicitantes.</p>
            }
          </div>
        </div>
      </Section>

      <Section num="04" kicker="Pagadores y calidad"
        title="Mezcla de financiadores e indicadores de calidad"
        lede="La mezcla de convenios impacta el ciclo de cobro. Los indicadores de calidad —TAT de informe, SLA 48h— cierran la lectura clínica del período.">
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-timer-sand" label="TAT informe (prom.)" value={m.tatProm !== null ? Math.round(m.tatProm) : '—'} unit="h" sub={`Mediana <b>${m.tatMed !== null ? Math.round(m.tatMed) : '—'}h</b>`} accent="var(--warning)" />
          <Kpi icon="mdi-speedometer" label="TAT informe P90" value={m.tatP90 !== null ? Math.round(m.tatP90) : '—'} unit="h" sub="90% se cierra antes de este tiempo" accent="var(--warning)" />
          <Kpi icon="mdi-clock-check-outline" label="SLA informe ≤48h" value={m.sla48Pct !== null ? Math.round(m.sla48Pct) : '—'} unit="%" sub="Objetivo: 100%" accent="var(--success)" />
          <Kpi icon="mdi-cash" label="Ticket promedio" value={'$' + fmt(Math.round(m.ticket))} sub={`${fmt(m.facturados)} estudios facturados`} accent="var(--primary)" />
        </div>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-bank-outline"></i>Estudios por empresa de seguro</h3>
              <span className="rep-card-note">Volumen realizado</span>
            </div>
            {r.porConvenio.length > 0
              ? <BarsList items={r.porConvenio} color="var(--primary)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de convenios.</p>
            }
          </div>
        </div>
        <RecsCard>
          <Recs items={[
            `Cerrar los <b>${fmt(m.pendFact)} estudios pendientes de facturar</b> para destrabar billing y reducir el TAT P90 de ${m.tatP90 !== null ? Math.round(m.tatP90) : '—'}h.`,
            `Emitir billing del backlog realizado para recuperar la oportunidad estimada del mapa ejecutivo.`,
            `Vigilar la concentración del sector público en cartera para anticipar el pendiente de pago (${fmt(m.pendPago)} registros).`,
            `Monitorear el SLA de informe ≤48h (actual: ${m.sla48Pct !== null ? Math.round(m.sla48Pct) + '%' : '—'}) como indicador de calidad radiológica.`,
          ]} />
        </RecsCard>
      </Section>
    </>
  );
}
