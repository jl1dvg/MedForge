import React from 'react';
import { TrendArea, DonutChart, GroupedColumnChart } from '../shared/charts';
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

      <Section num="04" kicker="Rentabilidad y Oportunidad"
        title="Rentabilidad y Oportunidad"
        lede="Analiza qué pagadores generan valor, dónde existe demanda no capturada y qué oportunidades pueden transformarse en producción futura.">
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-cash" label="Ticket promedio" value={'$' + fmt(Math.round(m.ticket))} sub={`${fmt(m.facturados)} estudios facturados`} accent="var(--primary)" />
          <Kpi icon="mdi-bank-outline" label="Convenio líder" value={r.convenioLider.label} sub={`Producción facturada: $${fmt(Math.round(r.convenioLider.produccion))}`} accent="var(--info)" />
          <Kpi icon="mdi-cash-multiple" label="Oportunidad pendiente" value={'$' + fmt(Math.round(m.sinAgendaMonto))} sub="Solicitudes sin agenda valorizadas" accent="var(--warning)" />
          <Kpi icon="mdi-progress-clock" label="Solicitudes no concretadas" value={fmt(m.arrastreCorte)} sub="Arrastre al corte" accent="var(--danger)" />
        </div>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card rep-span2">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-chart-bar"></i>Producción facturada vs oportunidad pendiente por convenio</h3>
              <span className="rep-card-note">Top 10 convenios</span>
            </div>
            {r.produccionVsOportunidad.length > 0
              ? <GroupedColumnChart data={r.produccionVsOportunidad} keys={['produccion', 'oportunidad']} names={['Producción facturada', 'Oportunidad pendiente']} colors={['#5156be', '#d59623']} />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin datos de convenios.</p>
            }
          </div>
        </div>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head">
              <h3><i className="mdi mdi-target"></i>Exámenes con mayor oportunidad</h3>
              <span className="rep-card-note">Monto potencial sin agenda</span>
            </div>
            {r.examenesOportunidad.length > 0
              ? <BarsList items={r.examenesOportunidad} color="var(--warning)" />
              : <p style={{ color: 'var(--fg-mute)', padding: '16px 0' }}>Sin oportunidad detectada en el período.</p>
            }
          </div>
        </div>
        <RecsCard>
          <Recs items={[
            `Cerrar los <b>${fmt(m.pendFact)} estudios pendientes de facturar</b> para destrabar billing y consolidar la producción facturada.`,
            `Emitir billing del backlog realizado para recuperar la oportunidad estimada del mapa ejecutivo ($${fmt(Math.round(m.sinAgendaMonto))}).`,
            `Vigilar la concentración del sector público en cartera para anticipar el pendiente de pago (${fmt(m.pendPago)} registros).`,
            `Priorizar el agendamiento de las <b>${fmt(m.arrastreCorte)} solicitudes no concretadas</b> al corte para reducir el arrastre del próximo período.`,
          ]} />
        </RecsCard>
      </Section>
    </>
  );
}
