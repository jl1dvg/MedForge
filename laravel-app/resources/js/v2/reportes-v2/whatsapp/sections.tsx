import React from 'react';
import { Section, Kpi, BarsList, Read, Insight, Recs, RecsCard } from '../shared/lib';
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
  const appointmentTypes = r.appointmentTypes ?? [];
  const reminders = r.reminders ?? {
    summary: {
      total: 0,
      sent: 0,
      delivered: 0,
      failed: 0,
      responded: 0,
      confirmed: 0,
      agentRequested: 0,
      deliveryRate: 0,
      responseRate: 0,
      confirmationRate: 0,
    },
    bySourceWindow: [],
  };
  const topSource = r.sources[0];
  const opportunityLoss = r.opportunityLoss ?? {
    appointmentIntentConversations: 0,
    enteredScheduling: 0,
    identifiedConversations: 0,
    reachedConfirmation: 0,
    attributedAppointments,
    humanLostConversations: s.lostNeedsHuman ?? 0,
    schedulingDropoffs: 0,
    identifiedWithoutAppointment: 0,
    estimatedLostAppointments: 0,
    observedConversionRate: attributedBookingRate,
  };
  const opportunitySignals = Math.max(opportunityLoss.appointmentIntentConversations, opportunityLoss.enteredScheduling, opportunityLoss.identifiedConversations);
  const fmt = (value: number) => value.toLocaleString('es-EC');

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
            <TrendArea data={r.trend} keys={['conversaciones', 'citas', 'citasHumanas']} names={['Conversaciones', 'Citas por bot', 'Citas por humano']} />
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
        lede="Muestra de dónde llegó cada conversación y cuántas terminaron en una cita."
      >
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-source-branch"></i>Origen de la conversación</h3></div>
            <DonutChart data={r.sources} centerLabel="conversaciones" />
            <table className="rep-table" style={{ marginTop: 14 }}>
              <thead><tr><th>Origen</th><th>Conversaciones</th><th>Citas</th><th>Conv.</th></tr></thead>
              <tbody>
                {r.sources.map((source, i) => {
                  const sourceAppointments = source.attributedAppointments ?? source.bookings ?? 0;
                  const sourceRate = source.attributedRate ?? source.bookingRate ?? 0;
                  return (
                    <tr key={i}>
                      <td>{source.label}</td>
                      <td>{source.total.toLocaleString('es-EC')}</td>
                      <td>{sourceAppointments.toLocaleString('es-EC')}</td>
                      <td>{sourceRate}%</td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
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
          <Kpi
            icon="mdi-account-tie-outline"
            label="Citas creadas tras atención humana"
            value={humanAppointments.toLocaleString('es-EC')}
            sub={`Nacieron de <b>${humanAppointmentConversations.toLocaleString('es-EC')}</b> conversaciones y <b>${humanAppointmentPatients.toLocaleString('es-EC')}</b> pacientes únicos`}
          />
          <Kpi icon="mdi-calendar-sync-outline" label="Citas agendadas por bot" value={botBookings.toLocaleString('es-EC')} />
          <Kpi icon="mdi-calendar-clock-outline" label="Estimado amplio 72h" value={humanAppointmentsMedium.toLocaleString('es-EC')} />
          <Kpi icon="mdi-percent-outline" label="Conversión total a cita" value={`${attributedBookingRate}%`} sub={`${attributedAppointments.toLocaleString('es-EC')} citas de ${s.conversationsNew.toLocaleString('es-EC')} conversaciones`} />
        </div>
        <Read>
          Número principal: <b>{humanAppointments.toLocaleString('es-EC')}</b> citas creadas en Sigcenter después de una atención humana.
          Esas citas salieron de <b>{humanAppointmentConversations.toLocaleString('es-EC')}</b> conversaciones únicas; una conversación puede terminar en más de una cita.
          El estimado 72h es una mirada más amplia: cuenta citas creadas hasta 3 días después de la atención humana.
          Conversión total suma humano + bot.
        </Read>
        {topSource ? (
          <Read>
            El origen con más volumen fue <b>{topSource.label}</b>: {topSource.total.toLocaleString('es-EC')} conversaciones,
            {(topSource.attributedAppointments ?? topSource.bookings ?? 0).toLocaleString('es-EC')} citas y {topSource.attributedRate ?? topSource.bookingRate ?? 0}% de conversión.
          </Read>
        ) : null}
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-calendar-multiselect"></i>Tipo de cita agendada</h3></div>
            <table className="rep-table">
              <thead><tr><th>Tipo</th><th>Total</th><th>Humano</th><th>Bot</th><th>%</th></tr></thead>
              <tbody>
                {appointmentTypes.length > 0 ? appointmentTypes.map((row, i) => (
                  <tr key={i}>
                    <td>{row.label}</td>
                    <td>{fmt(row.total)}</td>
                    <td>{fmt(row.human)}</td>
                    <td>{fmt(row.bot)}</td>
                    <td>{row.share}%</td>
                  </tr>
                )) : (
                  <tr><td colSpan={5}>Sin citas clasificadas en el período.</td></tr>
                )}
              </tbody>
            </table>
          </div>
          <div className="rep-card rep-reminders-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-bell-ring-outline"></i>Recordatorios de cita</h3></div>
            <div className="rep-reminder-summary" aria-label="Resumen de recordatorios">
              <div>
                <span>Enviados</span>
                <strong>{fmt(reminders.summary.sent)}</strong>
              </div>
              <div>
                <span>Confirmaron</span>
                <strong>{fmt(reminders.summary.confirmed)}</strong>
              </div>
              <div>
                <span>Pidieron agente</span>
                <strong>{fmt(reminders.summary.agentRequested)}</strong>
              </div>
            </div>
            <p className="rep-muted rep-reminder-read">
              De cada recordatorio enviado, el reporte separa quién confirmó y quién pidió hablar con una persona.
            </p>
            <table className="rep-table">
              <thead><tr><th>Tipo</th><th>Ventana</th><th className="num">Enviados</th><th className="num">Confirm.</th><th className="num">Agente</th></tr></thead>
              <tbody>
                {reminders.bySourceWindow.length > 0 ? reminders.bySourceWindow.map((row, i) => (
                  <tr key={i}>
                    <td>{row.sourceLabel}</td>
                    <td>{row.windowLabel}</td>
                    <td className="num">{fmt(row.sent)}</td>
                    <td className="num">{fmt(row.confirmed)}</td>
                    <td className="num">{fmt(row.agentRequested)}</td>
                  </tr>
                )) : (
                  <tr><td colSpan={5}>Sin recordatorios registrados en el período.</td></tr>
                )}
              </tbody>
            </table>
          </div>
        </div>
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
        kicker="Oportunidad perdida"
        title="Cuántas citas pudo estar perdiendo la operación"
        lede="Traduce WhatsApp a una lectura simple: demanda generada, citas logradas y señales de pérdida por falta de cierre humano."
      >
        <div className="rep-grid rep-grid--4" style={{ marginBottom: 16 }}>
          <Kpi icon="mdi-bullseye-arrow" label="Oportunidades de cita" value={fmt(opportunitySignals)} sub="personas con señal de cita o paciente identificado" />
          <Kpi icon="mdi-calendar-check-outline" label="Citas logradas" value={fmt(opportunityLoss.attributedAppointments)} sub={`${fmt(humanAppointments)} humano · ${fmt(botBookings)} bot`} />
          <Kpi icon="mdi-account-alert-outline" label="Se perdieron por atención" value={fmt(opportunityLoss.humanLostConversations)} sub="necesitaban humano y no cerraron" />
          <Kpi icon="mdi-calendar-remove-outline" label="Citas que se pudieron perder" value={fmt(opportunityLoss.estimatedLostAppointments)} sub={`estimado con ${opportunityLoss.observedConversionRate}% de conversión`} />
        </div>
        <Read>
          Lectura directa: MedForge generó <b>{fmt(opportunitySignals)}</b> oportunidades claras desde WhatsApp y se lograron <b>{fmt(opportunityLoss.attributedAppointments)}</b> citas.
          Pero <b>{fmt(opportunityLoss.humanLostConversations)}</b> conversaciones que necesitaban al equipo quedaron sin cierre.
          Con la conversión actual, eso equivale aproximadamente a <b>{fmt(opportunityLoss.estimatedLostAppointments)}</b> citas que se pudieron perder.
        </Read>
        <div className="rep-grid rep-grid--2" style={{ marginBottom: 16 }}>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-alert-decagram-outline"></i>Dónde se está perdiendo</h3></div>
            <BarsList items={[
              { label: 'Necesitaban humano y quedaron perdidas', total: opportunityLoss.humanLostConversations, share: 0 },
              { label: 'Entraron a agendamiento y no confirmaron', total: opportunityLoss.schedulingDropoffs, share: 0 },
              { label: 'Paciente identificado sin cita atribuida', total: opportunityLoss.identifiedWithoutAppointment, share: 0 },
            ]} />
          </div>
          <div className="rep-card">
            <div className="rep-card-head"><h3><i className="mdi mdi-clipboard-text-outline"></i>Lectura para gerencia</h3></div>
            <table className="rep-table">
              <tbody>
                <tr><td>Pidieron o mostraron intención de cita</td><td>{fmt(opportunityLoss.appointmentIntentConversations)}</td></tr>
                <tr><td>Entraron al flujo de agendamiento</td><td>{fmt(opportunityLoss.enteredScheduling)}</td></tr>
                <tr><td>Llegaron a confirmación</td><td>{fmt(opportunityLoss.reachedConfirmation)}</td></tr>
                <tr><td>Citas logradas por WhatsApp</td><td>{fmt(opportunityLoss.attributedAppointments)}</td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </Section>

      <Section
        num="07"
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
