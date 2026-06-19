import React from 'react';
import { Section, Kpi, BarsList, DonutLegend, Read, Insight, Recs, RecsCard } from '../shared/lib';
import { TrendArea, DonutChart, GroupedColumnChart } from '../shared/charts';
import type { WhatsappReport } from './types';

export function WhatsappContent({ r }: { r: WhatsappReport }) {
  const s = r.summary;
  const botBookings = s.botBookings ?? s.bookings ?? 0;
  const humanAppointments = s.humanAttributedAppointments ?? 0;
  const humanAppointmentsMedium = s.humanAttributedAppointmentsMedium ?? humanAppointments;
  const humanAppointmentConversations = s.humanAttributedAppointmentConversations ?? 0;
  const humanAppointmentPatients = s.humanAttributedAppointmentPatients ?? 0;
  const attributedAppointments = s.attributedAppointments ?? (botBookings + humanAppointments);
  const attributedBookingRate = s.attributedBookingRate ?? s.bookingRate ?? 0;
  const humanAppointmentAgents = r.humanAppointmentAgents ?? [];

  return (
    <>
      <Section
        num="01"
        kicker="Volumen y atención"
        title="Cuánta gente escribió y cuánta fue atendida"
        lede="Conecta el volumen de conversaciones e inbound con la cobertura humana y el cumplimiento de SLA."
      >
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-message-text-outline" label="Conversaciones nuevas" value={s.conversationsNew.toLocaleString('es-EC')} />
          <Kpi icon="mdi-account-multiple-outline" label="Personas atendidas (inbound)" value={s.peopleInbound.toLocaleString('es-EC')} />
          <Kpi icon="mdi-account-check-outline" label="Tasa de atención" value={`${Math.round(s.attentionRate)}%`} />
          <Kpi icon="mdi-timer-sand" label="Cumplimiento SLA" value={`${Math.round(s.slaRate)}%`} sub={`Objetivo: ${r.slaTarget} min a la primera respuesta`} />
        </div>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-chart-line"></i>Tendencia de conversaciones y citas</h3></div>
            <TrendArea data={r.trend} keys={['conversaciones', 'citas', 'citasHumanas']} names={['Conversaciones', 'Citas bot/integración', 'Citas humanas atrib.']} />
          </div>
          <Read>
            Se atendió al <b>{Math.round(s.attentionRate)}%</b> de las conversaciones que requirieron una persona
            {s.medianFirstResp !== null ? <> con una mediana de <b>{Math.round(s.medianFirstResp)} min</b> a la primera respuesta</> : null}.
            Quedaron <b>{s.lostNeedsHuman.toLocaleString('es-EC')}</b> conversaciones perdidas que necesitaban atención humana.
          </Read>
        </div>
      </Section>

      <Section
        num="02"
        kicker="Origen e intención"
        title="De dónde llega la demanda y qué busca"
        lede="Mezcla de fuentes de contacto e intención declarada, con su tasa de identificación y de conversión a cita."
      >
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-source-branch"></i>Origen de la conversación</h3></div>
            <DonutChart data={r.sources} centerLabel="conversaciones" />
            <DonutLegend items={r.sources} />
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-text-search"></i>Intención declarada</h3></div>
            <BarsList items={r.intents} suffix=" conv." />
          </div>
        </div>
      </Section>

      <Section
        num="03"
        kicker="Conversión a cita"
        title="Del primer contacto a la cita agendada"
        lede="Embudo de conversión y desempeño por segmento de ciclo de vida del paciente."
      >
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-account-tie-outline" label="Citas humanas atribuibles" value={humanAppointments.toLocaleString('es-EC')} sub={`${humanAppointmentConversations.toLocaleString('es-EC')} conv. · ${humanAppointmentPatients.toLocaleString('es-EC')} pacientes`} />
          <Kpi icon="mdi-calendar-sync-outline" label="Citas bot/integración" value={botBookings.toLocaleString('es-EC')} />
          <Kpi icon="mdi-calendar-clock-outline" label="Ventana humana 72h" value={humanAppointmentsMedium.toLocaleString('es-EC')} />
          <Kpi icon="mdi-percent-outline" label="Tasa atribuida total" value={`${attributedBookingRate}%`} sub={`${attributedAppointments.toLocaleString('es-EC')} citas atribuidas`} />
        </div>
        <Read>
          Las <b>{botBookings.toLocaleString('es-EC')}</b> citas de bot/integración vienen del registro directo de WhatsApp.
          Las citas humanas atribuibles cruzan conversaciones con intervención humana en MedForge contra citas creadas en Sigcenter por paciente y ventana temporal.
        </Read>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-filter-variant"></i>Embudo de conversión</h3></div>
            <BarsList items={r.funnel} valueKey="value" />
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-account-group-outline"></i>Por ciclo de vida</h3></div>
            <BarsList items={r.lifecycle} />
          </div>
        </div>
      </Section>

      <Section
        num="04"
        kicker="Automatización y fricción"
        title="Qué contiene el bot y dónde se traba la conversación"
        lede="Contención automática frente a escalamiento humano, y los principales puntos de fricción detectados."
      >
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-robot-outline" label="Contención del bot" value={`${Math.round(s.containmentRate)}%`} />
          <Kpi icon="mdi-account-arrow-right-outline" label="Handoffs a humano" value={s.handoffs.toLocaleString('es-EC')} sub={`${Math.round(s.handoffRate)}% de las conversaciones`} />
          <Kpi icon="mdi-message-alert-outline" label="Resueltas por bot" value={s.resolvedBot.toLocaleString('es-EC')} />
        </div>
        <div className="rep-card">
          <div className="rep-card-head"><h3><i className="mdi mdi-alert-circle-outline"></i>Principales fricciones</h3></div>
          <BarsList items={r.frictions} />
        </div>
      </Section>

      <Section
        num="05"
        kicker="Equipo"
        title="Quién atendió y cómo se repartió la carga"
        lede="Desempeño de agentes humanos y distribución de handoffs por equipo/rol."
      >
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-account-tie-outline"></i>Citas atribuibles por agente</h3></div>
            <table className="rep-table">
              <thead><tr><th>Agente</th><th>Citas atrib.</th><th>Conv.</th><th>Pacientes</th></tr></thead>
              <tbody>
                {humanAppointmentAgents.length > 0 ? humanAppointmentAgents.map((a, i) => (
                  <tr key={i}>
                    <td>{a.name}</td>
                    <td>{a.appointments.toLocaleString('es-EC')}</td>
                    <td>{a.conversations.toLocaleString('es-EC')}</td>
                    <td>{a.patients.toLocaleString('es-EC')}</td>
                  </tr>
                )) : (
                  <tr>
                    <td colSpan={4}>Sin citas atribuibles a atención humana en la ventana seleccionada.</td>
                  </tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-account-multiple-outline"></i>Handoffs por equipo</h3></div>
            <GroupedColumnChart data={r.teams} keys={['assigned', 'resolved']} names={['Asignados', 'Resueltos']} money={false} />
          </div>
        </div>
      </Section>

      <Section
        num="06"
        kicker="Hallazgos"
        title="Lecturas y recomendaciones del período"
      >
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          {r.insights.map((ins, i) => (
            <Insight key={i} title={ins.title} accent={ins.tone === 'success' ? '#05825f' : ins.tone === 'danger' ? '#d34b5b' : '#d59623'}>
              {ins.body}
            </Insight>
          ))}
        </div>
        <RecsCard>
          <Recs items={r.recommendations} />
        </RecsCard>
      </Section>
    </>
  );
}
